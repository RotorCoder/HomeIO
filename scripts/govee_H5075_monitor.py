# cd C:\Users\Tech\Documents\HomeIO
# python govee_H5075_monitor.py

import asyncio
from bleak import BleakScanner
from datetime import datetime
import json
import os
import mysql.connector
import time
from govee_config import CONFIG

# Store last update time for each device
device_last_update = {}

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
            temp DECIMAL(5,2),
            humidity DECIMAL(5,2),
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
            temperature DECIMAL(5,2),
            humidity DECIMAL(5,2),
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
                
            # Check if we should process this device now
            current_time = time.time()
            if device.address in device_last_update:
                time_since_last_update = current_time - device_last_update[device.address]
                if time_since_last_update < (CONFIG['monitoring']['update_interval'] * 60):
                    return
                    
            result = decode_govee_thermometer(advertising_data.manufacturer_data)
            if result:
                # Update the last update time for this device
                device_last_update[device.address] = current_time
                
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                print(f"\n=== {timestamp} ===")
                print(f"Device: {device.name} ({device.address})")
                print(f"RSSI: {advertising_data.rssi}")
                print(f"Temperature: {result['temperature_f']:.1f}°F")
                print(f"Humidity: {result['humidity']:.1f}%")
                print(f"Battery: {result['battery']}%")
                
                try:
                    # Database connection
                    db = mysql.connector.connect(**CONFIG['database'])
                    cursor = db.cursor()
                    
                    # Update thermometers table
                    cursor.execute("SELECT mac FROM thermometers WHERE mac = %s", (device.address,))
                    device_exists = cursor.fetchone()
                    
                    if device_exists:
                        update_query = """
                        UPDATE thermometers 
                        SET model = %s, 
                            name = %s,
                            rssi = %s,
                            temp = %s,
                            humidity = %s,
                            battery = %s,
                            updated = CURRENT_TIMESTAMP
                        WHERE mac = %s
                        """
                        update_values = (
                            device.name[:7],
                            device.name,
                            advertising_data.rssi,
                            round(result['temperature_f'], 1),
                            round(result['humidity'], 1),
                            result['battery'],
                            device.address
                        )
                        cursor.execute(update_query, update_values)
                    else:
                        insert_query = """
                        INSERT INTO thermometers 
                        (mac, model, name, rssi, temp, humidity, battery)
                        VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """
                        insert_values = (
                            device.address,
                            device.name[:7],
                            device.name,
                            advertising_data.rssi,
                            round(result['temperature_f'], 1),
                            round(result['humidity'], 1),
                            result['battery']
                        )
                        cursor.execute(insert_query, insert_values)
                    
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
                        round(result['temperature_f'], 1),
                        round(result['humidity'], 1),
                        result['battery']
                    )
                    cursor.execute(history_query, history_values)
                    
                    db.commit()
                    cursor.close()
                    db.close()
                    
                except Exception as e:
                    print(f"Error processing data: {e}")

async def run_scanner():
    """Main scanner function"""
    print("Starting Govee device monitor")
    print(f"Looking for devices with names starting with '{CONFIG['monitoring']['device_prefix']}'")
    print(f"Update interval: {CONFIG['monitoring']['update_interval']} minute(s)")
    print("Press Ctrl+C to stop...")
    
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