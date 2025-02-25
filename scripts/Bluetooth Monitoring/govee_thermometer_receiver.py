import asyncio
import signal
import time
import os
import logging
from logging.handlers import RotatingFileHandler
from bleak import BleakScanner
from datetime import datetime
from govee_ble import GoveeBluetoothDeviceData
from bleak.backends.device import BLEDevice
from bleak.backends.scanner import AdvertisementData
from dataclasses import dataclass
import mysql.connector
from govee_config import CONFIG

# Global variables
shutdown_requested = False
logger = None

@dataclass
class BluetoothServiceInfoBleak:
    name: str
    address: str
    rssi: int
    manufacturer_data: dict
    service_data: dict
    service_uuids: list
    source: str
    device: BLEDevice
    advertisement: AdvertisementData
    connectable: bool = True

# Track device storage times
device_storage_times = {}

def setup_logging(name):
    """Set up rotating file logging with console output."""
    log_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs")
    os.makedirs(log_dir, exist_ok=True)
    
    log = logging.getLogger(name)
    log.setLevel(logging.INFO)
    
    # Console handler
    console = logging.StreamHandler()
    console.setLevel(logging.INFO)
    
    # File handler - 1MB files, max 5 backup files
    file_handler = RotatingFileHandler(
        os.path.join(log_dir, f"{name}.log"),
        maxBytes=1024*1024,
        backupCount=5
    )
    file_handler.setLevel(logging.INFO)
    
    # Format
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    console.setFormatter(formatter)
    file_handler.setFormatter(formatter)
    
    log.addHandler(console)
    log.addHandler(file_handler)
    
    return log

def get_database_connection():
    """Get database connection with retry logic."""
    max_retries = 5
    retry_delay = 5  # seconds
    
    for attempt in range(max_retries):
        try:
            return mysql.connector.connect(**CONFIG['database'])
        except mysql.connector.Error as err:
            logger.error(f"Database connection error (attempt {attempt+1}/{max_retries}): {err}")
            if attempt < max_retries - 1:
                logger.info(f"Retrying in {retry_delay} seconds...")
                time.sleep(retry_delay)
            else:
                logger.critical("Failed to connect to database after maximum retries")
                raise

def validate_storage_interval(interval):
    """Validate and adjust the storage interval."""
    try:
        interval = int(interval)
        allowed_intervals = [1, 5, 10, 15, 30, 60]
        closest_interval = min(allowed_intervals, key=lambda x: abs(x - interval))
        return closest_interval
    except (ValueError, TypeError):
        logger.warning(f"Invalid storage interval specified: {interval}, defaulting to 15")
        return 15

def is_valid_storage_time(interval):
    """Check if current time is valid for storing data."""
    current_minute = datetime.now().minute
    
    if interval == 1:
        return True
    elif interval in [5, 10, 15, 30]:
        if interval == 5:
            valid_minutes = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]
        elif interval == 10:
            valid_minutes = [0, 10, 20, 30, 40, 50]
        elif interval == 15:
            valid_minutes = [0, 15, 30, 45]
        else:  # 30 minutes
            valid_minutes = [0, 30]
        return current_minute in valid_minutes
    elif interval >= 60:
        return current_minute == 0
    return False

def can_store_device_data(device_address, interval):
    """Check if device can store data based on interval."""
    current_time = datetime.now()
    
    if device_address not in device_storage_times:
        return True
    
    last_storage_time = device_storage_times[device_address]
    
    if interval == 1:
        current_window_start = current_time.replace(second=0, microsecond=0)
    elif interval >= 60:
        current_window_start = current_time.replace(minute=0, second=0, microsecond=0)
    else:
        current_window_start = current_time.replace(
            minute=(current_time.minute // interval) * interval,
            second=0,
            microsecond=0
        )
    
    return last_storage_time < current_window_start

def setup_database():
    """Set up database tables with error handling."""
    max_retries = 3
    retry_delay = 5  # seconds
    
    for attempt in range(max_retries):
        try:
            db = get_database_connection()
            cursor = db.cursor()
            
            cursor.execute("""
            CREATE TABLE IF NOT EXISTS thermometers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mac VARCHAR(17) NOT NULL UNIQUE,
                model VARCHAR(50),
                name VARCHAR(50),
                rssi INT,
                temp INT,
                humidity INT,
                battery INT,
                updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
            """)
            
            cursor.execute("""
            CREATE TABLE IF NOT EXISTS thermometer_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mac VARCHAR(17) NOT NULL,
                name VARCHAR(50),
                rssi INT,
                temperature INT,
                humidity INT,
                battery INT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mac_timestamp (mac, timestamp)
            )
            """)
            
            db.commit()
            cursor.close()
            db.close()
            logger.info("Database tables checked/created successfully")
            return True
            
        except Exception as e:
            logger.error(f"Database setup error (attempt {attempt+1}/{max_retries}): {e}")
            if attempt < max_retries - 1:
                logger.info(f"Retrying in {retry_delay} seconds...")
                time.sleep(retry_delay)
            else:
                logger.critical("Failed to set up database after maximum retries")
                return False

def signal_handler(sig, frame):
    """Handle termination signals properly."""
    global shutdown_requested
    logger.info(f"Received signal {sig}, shutting down gracefully...")
    shutdown_requested = True

class GoveeThermometerReader:
    def __init__(self):
        # From manifest.json
        self.GOVEE_PREFIXES = ["GVH5075", "GVH5100"]
        self.device = GoveeBluetoothDeviceData()
        self.storage_interval = validate_storage_interval(CONFIG['monitoring'].get('storage_interval', 15))
        self.scanner = None
        
    async def callback(self, device, advertising_data):
        """Process BLE advertisements with improved error handling."""
        try:
            # Skip devices with weak signal
            if advertising_data.rssi <= -127:
                return

            if not device.name:
                return
                
            if not any(device.name.startswith(prefix) for prefix in self.GOVEE_PREFIXES):
                return
                
            # Create service info object
            service_info = BluetoothServiceInfoBleak(
                name=device.name,
                address=device.address,
                rssi=advertising_data.rssi,
                manufacturer_data=advertising_data.manufacturer_data,
                service_data=advertising_data.service_data,
                service_uuids=advertising_data.service_uuids,
                source="local",
                device=device,
                advertisement=advertising_data,
                connectable=False
            )
            
            # Update device data with exception handling
            try:
                update = self.device.update(service_info)
            except Exception as e:
                logger.error(f"Error updating device data for {device.name} ({device.address}): {e}")
                return
            
            if update.entity_values:
                # Process sensor values
                temp_value = humidity_value = battery_value = None
                
                for device_key, sensor_values in update.entity_values.items():
                    try:
                        if "temperature" in device_key.key:
                            # Convert to Fahrenheit
                            temp_value = round((sensor_values.native_value * 9/5) + 32)
                        elif "humidity" in device_key.key:
                            humidity_value = round(sensor_values.native_value)
                        elif "battery" in device_key.key:
                            battery_value = round(sensor_values.native_value)
                    except (TypeError, ValueError) as e:
                        logger.error(f"Error processing sensor value for {device_key.key}: {e}")
                
                # Only proceed if we have valid temperature or humidity readings
                if temp_value is not None or humidity_value is not None:
                    # Only store and display if it's time to store
                    if is_valid_storage_time(self.storage_interval) and can_store_device_data(device.address, self.storage_interval):
                        # Log current values
                        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                        logger.info(f"Device: {device.name} ({device.address})")
                        logger.info(f"RSSI: {advertising_data.rssi}")
                        if temp_value is not None:
                            logger.info(f"Temperature: {temp_value}°F")
                        if humidity_value is not None:
                            logger.info(f"Humidity: {humidity_value}%")
                        if battery_value is not None:
                            logger.info(f"Battery: {battery_value}%")
                        
                        # Database operations with retry
                        max_db_retries = 3
                        for attempt in range(max_db_retries):
                            try:
                                db = get_database_connection()
                                cursor = db.cursor()
                                
                                # Store in history
                                history_query = """
                                INSERT INTO thermometer_history 
                                (mac, name, rssi, temperature, humidity, battery)
                                VALUES (%s, %s, %s, %s, %s, %s)
                                """
                                history_values = (
                                    device.address,
                                    device.name,
                                    advertising_data.rssi,
                                    temp_value,
                                    humidity_value,
                                    battery_value
                                )
                                cursor.execute(history_query, history_values)
                                
                                # Update current values
                                update_query = """
                                INSERT INTO thermometers 
                                (mac, model, name, rssi, temp, humidity, battery)
                                VALUES (%s, %s, %s, %s, %s, %s, %s)
                                ON DUPLICATE KEY UPDATE 
                                model = %s, name = %s, rssi = %s,
                                temp = %s, humidity = %s, battery = %s,
                                updated = CURRENT_TIMESTAMP
                                """
                                update_values = (
                                    device.address, device.name[:7], device.name,
                                    advertising_data.rssi, temp_value, humidity_value,
                                    battery_value,
                                    device.name[:7], device.name, advertising_data.rssi,
                                    temp_value, humidity_value, battery_value
                                )
                                cursor.execute(update_query, update_values)
                                
                                # Update storage time
                                if self.storage_interval == 1:
                                    device_storage_times[device.address] = datetime.now().replace(second=0, microsecond=0)
                                elif self.storage_interval >= 60:
                                    device_storage_times[device.address] = datetime.now().replace(minute=0, second=0, microsecond=0)
                                else:
                                    device_storage_times[device.address] = datetime.now().replace(
                                        minute=(datetime.now().minute // self.storage_interval) * self.storage_interval,
                                        second=0,
                                        microsecond=0
                                    )
                                
                                db.commit()
                                cursor.close()
                                db.close()
                                logger.info(f"Data for {device.name} stored successfully")
                                break
                                
                            except mysql.connector.Error as err:
                                logger.error(f"Database error (attempt {attempt+1}/{max_db_retries}): {err}")
                                if attempt < max_db_retries - 1:
                                    await asyncio.sleep(2)  # Short delay before retry
                                else:
                                    logger.error(f"Failed to store data for {device.name} after maximum retries")
                            except Exception as e:
                                logger.error(f"Unexpected error storing data: {e}")
                                break
        except Exception as e:
            logger.error(f"Unhandled error in callback: {e}")

    async def run(self):
        """Main run loop with improved error handling"""
        global shutdown_requested
        
        logger.info("Starting Govee device monitor")
        logger.info(f"Looking for devices with prefixes: {', '.join(self.GOVEE_PREFIXES)}")
        
        if self.storage_interval == 1:
            logger.info("Data will be stored every minute")
        elif self.storage_interval in [5, 10, 15, 30]:
            logger.info(f"Data will be stored every {self.storage_interval} minutes")
        elif self.storage_interval >= 60:
            logger.info("Data will be stored once per hour")
        
        # Set up database
        if not setup_database():
            logger.critical("Cannot continue without database setup")
            return
        
        # Setup BLE scanner with reconnection logic
        max_restart_attempts = float('inf')  # Run indefinitely with restarts
        restart_attempt = 0
        restart_delay = 30  # seconds
        
        while restart_attempt < max_restart_attempts and not shutdown_requested:
            try:
                self.scanner = BleakScanner(detection_callback=self.callback)
                await self.scanner.start()
                logger.info("BLE scanner started successfully")
                
                # Reset attempt counter on successful start
                if restart_attempt > 0:
                    logger.info("Scanner recovered successfully after failures")
                    restart_attempt = 0
                
                # Main loop
                while not shutdown_requested:
                    await asyncio.sleep(1)
                    
            except asyncio.CancelledError:
                logger.info("Task cancelled, shutting down...")
                if self.scanner:
                    await self.scanner.stop()
                break
            except Exception as e:
                logger.error(f"Scanner error: {e}")
                try:
                    if self.scanner:
                        await self.scanner.stop()
                except Exception as stop_error:
                    logger.error(f"Error stopping scanner: {stop_error}")
                
                if shutdown_requested:
                    break
                    
                restart_attempt += 1
                logger.warning(f"Scanner crashed. Restart attempt {restart_attempt} in {restart_delay} seconds")
                await asyncio.sleep(restart_delay)
        
        logger.info("Govee thermometer monitor stopped")
    
    async def shutdown(self):
        """Graceful shutdown"""
        logger.info("Shutting down scanner...")
        if self.scanner:
            try:
                await self.scanner.stop()
            except Exception as e:
                logger.error(f"Error stopping scanner during shutdown: {e}")

async def main():
    """Main entry point with signal handling"""
    global logger, shutdown_requested
    
    # Set up logging first
    logger = setup_logging("govee_thermometer")
    
    # Register signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Create and run the reader
    reader = GoveeThermometerReader()
    
    try:
        # Run the main loop
        await reader.run()
    except Exception as e:
        logger.critical(f"Unhandled exception in main: {e}")
    finally:
        # Ensure cleanup
        await reader.shutdown()
        logger.info("Program terminated")

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        pass  # This will be caught by signal handler
    except Exception as e:
        if logger:
            logger.critical(f"Fatal error: {e}")
        else:
            print(f"Fatal error before logger initialized: {e}")