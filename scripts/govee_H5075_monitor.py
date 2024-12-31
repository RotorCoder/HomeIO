# cd C:\Users\Tech\Documents\HomeIO
# python govee_H5075_monitor.py

import asyncio
from bleak import BleakScanner
from datetime import datetime
import json
import os
import mysql.connector

def decode_govee_thermometer(manufacturer_data):
    # For H5075 devices - keep existing working code

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
    if device.name and device.name.startswith("GVH5075"):
        if advertising_data.manufacturer_data:
            result = decode_govee_thermometer(advertising_data.manufacturer_data)
            if result:
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                print(f"\n=== {timestamp} ===")
                print(f"Device: {device.name} ({device.address})")
                print(f"RSSI: {advertising_data.rssi}")
                print(f"Temperature: {result['temperature_f']:.1f}°F")
                print(f"Humidity: {result['humidity']:.1f}%")
                print(f"Battery: {result['battery']}%")
                
                try:
                    # Database connection
                    db = mysql.connector.connect(
                        host="192.168.99.200",
                        user="homeio_rw",
                        password="sfdjhgHGFD23543$#@",
                        database="homeio"
                    )
                    cursor = db.cursor()
                    
                    # Check if device exists
                    cursor.execute("SELECT mac FROM thermometers WHERE mac = %s", (device.address,))
                    device_exists = cursor.fetchone()
                    
                    if device_exists:
                        # Update existing device
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
                        # Insert new device
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
                    
                    db.commit()
                    cursor.close()
                    db.close()
                    
                    # Save to JSON file
                    os.makedirs('device_data', exist_ok=True)
                    filename = f"device_data/{device.address.replace(':', '_')}.json"
                    device_data = {
                        "name": device.name,
                        "address": device.address,
                        "rssi": advertising_data.rssi,
                        "timestamp": timestamp,
                        "temperature_f": round(result['temperature_f'], 1),
                        "temperature_c": round(result['temperature_c'], 1),
                        "humidity": round(result['humidity'], 1),
                        "battery": result['battery']
                    }
                    with open(filename, 'w') as f:
                        json.dump(device_data, f, indent=2)
                        
                except Exception as e:
                    print(f"Error processing data: {e}")

async def run_scanner():
    """Main scanner function"""
    print("Starting Govee device monitor")
    print("Looking for devices with names starting with 'GVH5075'")
    print("Press Ctrl+C to stop...")
    
    async with BleakScanner(detection_callback=process_detection):
        while True:
            await asyncio.sleep(10)

if __name__ == "__main__":
    try:
        asyncio.run(run_scanner())
    except KeyboardInterrupt:
        print("\nScanner stopped by user")
    except Exception as e:
        print(f"Error: {e}")