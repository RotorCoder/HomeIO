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

def setup_database():
    """Set up database tables."""
    try:
        db = mysql.connector.connect(**CONFIG['database'])
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
        
    except Exception as e:
        print(f"Database setup error: {e}")

class GoveeRemoteReader:
    def __init__(self):
        self.TARGET_PREFIX = "GV512"
        self.device = GoveeBluetoothDeviceData()
        self.last_raw_data_by_device = {}  # Track last raw data per device
        
    async def callback(self, device, advertising_data):
        """Callback for when a device is detected"""
        if device.name and device.name.startswith(self.TARGET_PREFIX):
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
            update = self.device.update(service_info)
            
            # If this is a button event
            if update.events:
                for event_key, event in update.events.items():
                    if "button" in event_key.key:
                        # Get the raw data
                        current_raw_data = None
                        if advertising_data.manufacturer_data:
                            for company_id, data in advertising_data.manufacturer_data.items():
                                current_raw_data = data
                        
                        # Skip if this is a duplicate transmission for this specific device
                        if (device.address in self.last_raw_data_by_device and 
                            current_raw_data == self.last_raw_data_by_device[device.address]):
                            return
                        
                        # Update the last raw data for this device
                        self.last_raw_data_by_device[device.address] = current_raw_data
                        
                        button_num = int(event_key.key.split("_")[1]) + 1
                        raw_data_str = ':'.join(f'{b:02x}' for b in current_raw_data) if current_raw_data else None
                        
                        # Print to console
                        print(f"\n=== Button Press Detected ===")
                        print(f"Timestamp: {datetime.now()}")
                        print(f"Device: {device.name}")
                        print(f"Button: {button_num}")
                        if raw_data_str:
                            print(f"Raw Data: {raw_data_str}")
                        
                        # Log to database
                        try:
                            db = mysql.connector.connect(**CONFIG['database'])
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
                            
                        except Exception as e:
                            print(f"Database error: {e}")

    async def run(self):
        """Main run loop"""
        print(f"Scanning for Govee remote {self.TARGET_PREFIX}... Press Ctrl+C to stop")
        
        # Set up database
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

async def main():
    reader = GoveeRemoteReader()
    await reader.run()

if __name__ == "__main__":
    asyncio.run(main())