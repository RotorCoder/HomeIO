import asyncio
from bleak import BleakScanner
from datetime import datetime
from govee_ble import GoveeBluetoothDeviceData
from bleak.backends.device import BLEDevice
from bleak.backends.scanner import AdvertisementData
from dataclasses import dataclass
import mysql.connector
from govee_config import CONFIG

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

def validate_storage_interval(interval):
    """Validate and adjust the storage interval."""
    try:
        interval = int(interval)
        allowed_intervals = [1, 5, 10, 15, 30, 60]
        closest_interval = min(allowed_intervals, key=lambda x: abs(x - interval))
        return closest_interval
    except (ValueError, TypeError):
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
    """Set up database tables."""
    try:
        db = mysql.connector.connect(**CONFIG['database'])
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
        
    except Exception as e:
        print(f"Database setup error: {e}")

class GoveeThermometerReader:
    def __init__(self):
        # From manifest.json
        self.GOVEE_PREFIXES = ["GVH5075", "GVH5100"]
        self.device = GoveeBluetoothDeviceData()
        self.storage_interval = validate_storage_interval(CONFIG['monitoring'].get('storage_interval', 15))
        
    async def callback(self, device, advertising_data):
        """Process BLE advertisements from Govee devices."""
        # Skip devices with weak signal
        if advertising_data.rssi <= -127:
            return

        if device.name and any(device.name.startswith(prefix) for prefix in self.GOVEE_PREFIXES):
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
            
            # Update device data
            update = self.device.update(service_info)
            
            if update.entity_values:
                # Process sensor values
                temp_value = humidity_value = battery_value = None
                
                for device_key, sensor_values in update.entity_values.items():
                    if "temperature" in device_key.key:
                        # Convert to Fahrenheit
                        temp_value = round((sensor_values.native_value * 9/5) + 32)
                    elif "humidity" in device_key.key:
                        humidity_value = round(sensor_values.native_value)
                    elif "battery" in device_key.key:
                        battery_value = round(sensor_values.native_value)
                
                # Only proceed if we have valid temperature or humidity readings
                if temp_value is not None or humidity_value is not None:
                    # Only store and display if it's time to store
                    if is_valid_storage_time(self.storage_interval) and can_store_device_data(device.address, self.storage_interval):
                        # Print current values that will be stored
                        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                        print(f"\n=== {timestamp} ===")
                        print(f"Device: {device.name} ({device.address})")
                        print(f"RSSI: {advertising_data.rssi}")
                        if temp_value is not None:
                            print(f"Temperature: {temp_value}°F")
                        if humidity_value is not None:
                            print(f"Humidity: {humidity_value}%")
                        if battery_value is not None:
                            print(f"Battery: {battery_value}%")
                        
                        try:
                            db = mysql.connector.connect(**CONFIG['database'])
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
                            
                        except Exception as e:
                            print(f"Database error: {e}")

    async def run(self):
        """Main run loop"""
        print("Starting Govee device monitor")
        print(f"Looking for devices with prefixes: {', '.join(self.GOVEE_PREFIXES)}")
        
        if self.storage_interval == 1:
            print("Data will be stored every minute")
        elif self.storage_interval in [5, 10, 15, 30]:
            print(f"Data will be stored every {self.storage_interval} minutes")
        elif self.storage_interval >= 60:
            print("Data will be stored once per hour")
        
        setup_database()
        
        scanner = BleakScanner(detection_callback=self.callback)
        await scanner.start()
        
        try:
            while True:
                await asyncio.sleep(0.1)
        except KeyboardInterrupt:
            print("\nStopping scanner...")
        finally:
            await scanner.stop()

if __name__ == "__main__":
    try:
        reader = GoveeThermometerReader()
        asyncio.run(reader.run())
    except KeyboardInterrupt:
        print("\nScanner stopped by user")
    except Exception as e:
        print(f"Error: {e}")