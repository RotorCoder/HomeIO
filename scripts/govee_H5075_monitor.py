import asyncio
from bleak import BleakScanner
from datetime import datetime, timedelta
import json
import os
import mysql.connector
import time
from govee_config import CONFIG

# Track device storage times to prevent multiple entries
device_storage_times = {}

def validate_storage_interval(interval):
    """
    Validate and adjust the storage interval.
    Supported intervals: 1, 5, 10, 15, 30, 60 minutes
    """
    try:
        interval = int(interval)
        # List of allowed intervals
        allowed_intervals = [1, 5, 10, 15, 30, 60]
        
        # Find the closest allowed interval
        closest_interval = min(allowed_intervals, key=lambda x: abs(x - interval))
        
        return closest_interval
    except (ValueError, TypeError):
        return 15  # Default to 15 minutes if invalid

def is_valid_storage_time(interval):
    """
    Check if the current time is valid for storing data based on the interval.
    """
    current_minute = datetime.now().minute
    
    if interval == 1:
        # Store every minute
        return True
    elif interval in [5, 10, 15, 30]:
        # Specific minute multiples for 5, 10, 15, 30 minute intervals
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
        # Store only at the top of the hour
        return current_minute == 0
    
    return False

def can_store_device_data(device_address, interval):
    """
    Check if a device can store data based on previous storage times.
    Prevents multiple entries within the specified interval.
    """
    current_time = datetime.now()
    
    # If device has no previous storage time, it can store data
    if device_address not in device_storage_times:
        return True
    
    last_storage_time = device_storage_times[device_address]
    
    # Determine the storage window start time based on interval
    if interval == 1:
        # For 1-minute interval, use the full minute
        current_window_start = current_time.replace(second=0, microsecond=0)
    elif interval >= 60:
        # For hourly or greater, use the top of the hour
        current_window_start = current_time.replace(minute=0, second=0, microsecond=0)
    else:
        # For other intervals, calculate the window start
        current_window_start = current_time.replace(
            minute=(current_time.minute // interval) * interval, 
            second=0, 
            microsecond=0
        )
    
    return last_storage_time < current_window_start

def setup_database():
    try:
        db = mysql.connector.connect(**CONFIG['database'])
        cursor = db.cursor()
        
        # Create thermometers table if it doesn't exist
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
        
        # Create thermometer_history table if it doesn't exist
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

def decode_govee_thermometer(manufacturer_data):
    if 0xec88 in manufacturer_data:
        data = manufacturer_data[0xec88]
        if len(data) == 6:
            basenum = (data[1] << 16) + (data[2] << 8) + data[3]
            
            temp_c = (basenum / 10000.0)
            temp_f = (temp_c * 9.0/5.0 + 32.0)
            humidity = (basenum % 1000) / 10.0
            battery = data[4]
            
            return {
                "temperature_f": temp_f,
                "temperature_c": temp_c,
                "humidity": humidity,
                "battery": battery
            }
            
    return None

def process_detection(device, advertising_data):
    if device.name and device.name.startswith(CONFIG['monitoring']['device_prefix']):
        if advertising_data.manufacturer_data:
            # Skip if RSSI indicates loss of signal
            if advertising_data.rssi == CONFIG['monitoring']['skip_rssi']:
                return
                
            # Validate and get storage interval
            storage_interval = validate_storage_interval(CONFIG['monitoring'].get('storage_interval', 15))
                
            result = decode_govee_thermometer(advertising_data.manufacturer_data)
            if result:
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                print(f"\n=== {timestamp} ===")
                print(f"Device: {device.name} ({device.address})")
                print(f"RSSI: {advertising_data.rssi}")
                print(f"Temperature: {result['temperature_f']:.0f}°F")  # Round to whole number
                print(f"Humidity: {result['humidity']:.0f}%")  # Round to whole number
                print(f"Battery: {result['battery']}%")
                
                try:
                    # Check if it's time to store data and if the device can store data
                    if is_valid_storage_time(storage_interval) and can_store_device_data(device.address, storage_interval):
                        # Database connection
                        db = mysql.connector.connect(**CONFIG['database'])
                        cursor = db.cursor()
                        
                        # Insert into history table
                        history_query = """
                        INSERT INTO thermometer_history 
                        (mac, name, rssi, temperature, humidity, battery)
                        VALUES (%s, %s, %s, %s, %s, %s)
                        """
                        history_values = (
                            device.address,
                            device.name,
                            advertising_data.rssi,
                            int(round(result['temperature_f'], 0)),  # Convert to int
                            int(round(result['humidity'], 0)),       # Convert to int
                            result['battery']
                        )
                        cursor.execute(history_query, history_values)
                        
                        # Update thermometers table
                        update_query = """
                        INSERT INTO thermometers 
                        (mac, model, name, rssi, temp, humidity, battery)
                        VALUES (%s, %s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE 
                        model = %s, 
                        name = %s,
                        rssi = %s,
                        temp = %s,
                        humidity = %s,
                        battery = %s,
                        updated = CURRENT_TIMESTAMP
                        """
                        update_values = (
                            device.address,
                            device.name[:7],
                            device.name,
                            advertising_data.rssi,
                            int(round(result['temperature_f'], 0)),  # Convert to int
                            int(round(result['humidity'], 0)),       # Convert to int
                            result['battery'],
                            # Duplicate values for ON DUPLICATE KEY UPDATE
                            device.name[:7],
                            device.name,
                            advertising_data.rssi,
                            int(round(result['temperature_f'], 0)),  # Convert to int
                            int(round(result['humidity'], 0)),       # Convert to int
                            result['battery']
                        )
                        cursor.execute(update_query, update_values)
                        
                        # Update the storage time for this device
                        if storage_interval == 1:
                            device_storage_times[device.address] = datetime.now().replace(second=0, microsecond=0)
                        elif storage_interval >= 60:
                            device_storage_times[device.address] = datetime.now().replace(minute=0, second=0, microsecond=0)
                        else:
                            device_storage_times[device.address] = datetime.now().replace(
                                minute=(datetime.now().minute // storage_interval) * storage_interval, 
                                second=0, 
                                microsecond=0
                            )
                        
                        db.commit()
                        cursor.close()
                        db.close()
                        
                except Exception as e:
                    print(f"Error processing data: {e}")

async def run_scanner():
    """Main scanner function"""
    # Validate and get storage interval
    storage_interval = validate_storage_interval(CONFIG['monitoring'].get('storage_interval', 15))
    
    print("Starting Govee device monitor")
    print(f"Looking for devices with names starting with '{CONFIG['monitoring']['device_prefix']}'")
    
    # Print storage interval details
    if storage_interval == 1:
        print("Data will be stored every minute")
    elif storage_interval in [5, 10, 15, 30]:
        print(f"Data will be stored every {storage_interval} minutes")
    elif storage_interval >= 60:
        print("Data will be stored once per hour at the top of the hour")
    
    # Set up database tables
    setup_database()
    
    async with BleakScanner(detection_callback=process_detection):
        while True:
            await asyncio.sleep(1)

if __name__ == "__main__":
    try:
        asyncio.run(run_scanner())
    except KeyboardInterrupt:
        print("\nScanner stopped by user")
    except Exception as e:
        print(f"Error: {e}")