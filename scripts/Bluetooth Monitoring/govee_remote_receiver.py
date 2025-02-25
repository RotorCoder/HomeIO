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
    """Bluetooth service info."""
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

def setup_database():
    """Set up database tables with error handling."""
    max_retries = 3
    retry_delay = 5  # seconds
    
    for attempt in range(max_retries):
        try:
            db = get_database_connection()
            cursor = db.cursor()
            
            cursor.execute("""
            CREATE TABLE IF NOT EXISTS remote_buttons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                remote_name VARCHAR(50) NOT NULL,
                button_number INT NOT NULL,
                raw_data VARCHAR(100),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_remote_timestamp (remote_name, timestamp)
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

class GoveeRemoteReader:
    def __init__(self):
        self.TARGET_PREFIX = "GV512"
        self.device = GoveeBluetoothDeviceData()
        self.last_raw_data = None
        self.scanner = None
        
    async def callback(self, device, advertising_data):
        """Callback for when a device is detected with enhanced error handling"""
        try:
            if not device.name:
                return
                
            if not device.name.startswith(self.TARGET_PREFIX):
                return
                
            # Create proper BluetoothServiceInfoBleak object
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
            
            # Update the device with the new data
            try:
                update = self.device.update(service_info)
            except Exception as e:
                logger.error(f"Error updating device data: {e}")
                return
            
            # If this is a button event
            if update.events:
                for event_key, event in update.events.items():
                    if "button" in event_key.key:
                        # Get the raw data
                        current_raw_data = None
                        if advertising_data.manufacturer_data:
                            for company_id, data in advertising_data.manufacturer_data.items():
                                current_raw_data = data
                        
                        # Skip if this is a duplicate transmission
                        if current_raw_data == self.last_raw_data:
                            return
                        
                        # Update the last raw data
                        self.last_raw_data = current_raw_data
                        
                        button_num = int(event_key.key.split("_")[1]) + 1
                        raw_data_str = ':'.join(f'{b:02x}' for b in current_raw_data) if current_raw_data else None
                        
                        # Log detection
                        logger.info(f"Button Press Detected - Device: {device.name}, Button: {button_num}")
                        if raw_data_str:
                            logger.debug(f"Raw Data: {raw_data_str}")
                        
                        # Log to database with retry
                        max_db_retries = 3
                        for attempt in range(max_db_retries):
                            try:
                                db = get_database_connection()
                                cursor = db.cursor()
                                
                                query = """
                                INSERT INTO remote_buttons 
                                (remote_name, button_number, raw_data, timestamp)
                                VALUES (%s, %s, %s, %s)
                                """
                                values = (
                                    device.name,
                                    button_num,
                                    raw_data_str,
                                    datetime.now()
                                )
                                
                                cursor.execute(query, values)
                                db.commit()
                                cursor.close()
                                db.close()
                                logger.info(f"Button press recorded in database")
                                break
                                
                            except mysql.connector.Error as err:
                                logger.error(f"Database error (attempt {attempt+1}/{max_db_retries}): {err}")
                                if attempt < max_db_retries - 1:
                                    time.sleep(2)  # Short delay before retry
                                else:
                                    logger.error("Failed to record button press after maximum retries")
                            except Exception as e:
                                logger.error(f"Unexpected error recording button press: {e}")
                                break
        except Exception as e:
            logger.error(f"Unhandled error in callback: {e}")

    async def run(self):
        """Main run loop with improved error handling"""
        global shutdown_requested
        
        logger.info(f"Scanning for Govee remote {self.TARGET_PREFIX}...")
        
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
                    logger.info("Scanner recovered successfully")
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
        
        logger.info("Govee remote receiver stopped")
    
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
    logger = setup_logging("govee_remote")
    
    # Register signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Create and run the reader
    reader = GoveeRemoteReader()
    
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