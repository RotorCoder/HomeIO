#!/home/sjones/venv/bin/python3
import sys
import logging
import json
import os
import time
from typing import Dict, Any, List, Optional
import mysql.connector
from pyvesync import VeSync
import re

# Set up logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

class VeSyncCommandQueue:
    def __init__(self, dbConfig):
        self.pdo = self._init_db_connection(dbConfig)
        self._create_tables()
        
    def _init_db_connection(self, dbConfig):
        return mysql.connector.connect(
            host=dbConfig['host'],
            user=dbConfig['user'],
            password=dbConfig['password'],
            database=dbConfig['dbname']
        )
    
    def _create_tables(self):
        cursor = self.pdo.cursor()
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS command_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device VARCHAR(255) NOT NULL,
                model VARCHAR(255),
                command TEXT,
                brand VARCHAR(50),
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                error_message TEXT
            )
        """)
        self.pdo.commit()
        cursor.close()

    def getNextBatch(self, limit: int = 5) -> List[Dict]:
        cursor = self.pdo.cursor(dictionary=True)
        try:
            # Reset stuck commands
            cursor.execute("""
                UPDATE command_queue 
                SET status = 'pending',
                    processed_at = NULL
                WHERE status = 'processing' 
                AND processed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            """)
            
            # Get pending commands
            cursor.execute("""
                SELECT id, device, model, command 
                FROM command_queue
                WHERE status = 'pending'
                AND brand = 'vesync'
                ORDER BY created_at ASC
                LIMIT %s
            """, (limit,))
            commands = cursor.fetchall()
            
            if commands:
                # Mark as processing
                ids = [cmd['id'] for cmd in commands]
                placeholders = ','.join(['%s'] * len(ids))
                cursor.execute(f"""
                    UPDATE command_queue
                    SET status = 'processing',
                        processed_at = CURRENT_TIMESTAMP
                    WHERE id IN ({placeholders})
                """, ids)
            
            self.pdo.commit()
            return commands
            
        except Exception as e:
            self.pdo.rollback()
            logger.error(f"Error in getNextBatch: {str(e)}")
            raise e
        finally:
            cursor.close()

    def markCommandComplete(self, id: int, success: bool = True, errorMessage: str = None):
        cursor = self.pdo.cursor()
        try:
            cursor.execute("""
                UPDATE command_queue
                SET 
                    status = %s,
                    processed_at = CURRENT_TIMESTAMP,
                    error_message = %s
                WHERE id = %s
            """, (
                'completed' if success else 'failed',
                errorMessage,
                id
            ))
            self.pdo.commit()
        except Exception as e:
            self.pdo.rollback()
            logger.error(f"Error marking command complete: {str(e)}")
            raise e
        finally:
            cursor.close()

class VeSyncAPI:
    def __init__(self, username: str, password: str, dbConfig: Optional[Dict] = None):
        self.username = username
        self.password = password
        self.manager = VeSync(username, password)
        self.dbConfig = dbConfig
        self.commandQueue = None
        self.last_update = 0
        self.update_interval = 60  # Update device status every 60 seconds
        
        if dbConfig:
            # Initialize database connection
            self.pdo = mysql.connector.connect(
                host=dbConfig['host'],
                user=dbConfig['user'],
                password=dbConfig['password'],
                database=dbConfig['dbname']
            )
            self.commandQueue = VeSyncCommandQueue(dbConfig)
            self._init_database()

    def _init_database(self):
        cursor = self.pdo.cursor()
        try:
            # Create devices table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS devices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    device VARCHAR(255) NOT NULL UNIQUE,
                    model VARCHAR(255),
                    device_name VARCHAR(255),
                    controllable BOOLEAN DEFAULT 1,
                    retrievable BOOLEAN DEFAULT 1,
                    supportCmds TEXT,
                    brand VARCHAR(50),
                    online BOOLEAN DEFAULT 0,
                    powerState VARCHAR(10),
                    brightness INT,
                    energy_today FLOAT,
                    power FLOAT,
                    voltage FLOAT
                )
            """)
            self.pdo.commit()
        except Exception as e:
            logger.error(f"Error initializing database: {str(e)}")
            raise
        finally:
            cursor.close()

    def login(self) -> bool:
        return self.manager.login()

    def update_devices(self):
        """Update device lists and status if interval has passed"""
        current_time = time.time()
        if current_time - self.last_update >= self.update_interval:
            success = self.manager.update()  # This updates the device list and states
            if success:
                self.last_update = current_time
            return success
        return True
    
    def get_devices(self) -> Dict[str, List]:
        """Get all devices organized by type"""
        self.update_devices()
        devices = {
            'outlets': self.manager.outlets,
            'switches': self.manager.switches,
            'bulbs': self.manager.bulbs,
            'fans': self.manager.fans
        }
        return devices
    
    def update_device_database(self, device) -> Dict:
        """Update device information in database"""
        if not self.dbConfig:
            return {}
    
        cursor = self.pdo.cursor(dictionary=True)
        try:
            # Get current device data
            cursor.execute("SELECT * FROM devices WHERE device = %s", (device.cid,))
            current = cursor.fetchone()
            
            # Prepare new values - only updating actual states from API
            new_values = {
                'device': device.cid,
                'model': device.device_type,
                'device_name': device.device_name,
                'controllable': True,
                'retrievable': True,
                'brand': 'vesync',
                'online': device.connection_status == 'online',
                'powerState': device.device_status
            }
    
            # Add device-specific properties
            if hasattr(device, 'details'):
                if 'brightness' in device.details:
                    new_values['brightness'] = device.details['brightness']
                # Get energy data from details
                if 'energy' in device.details:
                    new_values['energy_today'] = device.details.get('energy', 0)
                    new_values['power'] = device.details.get('power', 0)
                    new_values['voltage'] = device.details.get('voltage', 0)
                    logger.debug(f"Energy values from details for {device.device_name}: energy={new_values['energy_today']}, power={new_values['power']}, voltage={new_values['voltage']}")
    
            if not current:
                # Insert new device
                columns = ', '.join(new_values.keys())
                placeholders = ', '.join(['%s'] * len(new_values))
                values = list(new_values.values())
                
                cursor.execute(f"""
                    INSERT INTO devices ({columns})
                    VALUES ({placeholders})
                """, values)
                
            else:
                # Update existing device - only updating actual states
                updates = [f"{k} = %s" for k in new_values.keys()]
                values = list(new_values.values()) + [device.cid]
                
                cursor.execute(f"""
                    UPDATE devices 
                    SET {', '.join(updates)}
                    WHERE device = %s
                """, values)
    
            self.pdo.commit()
            return new_values
    
        except Exception as e:
            self.pdo.rollback()
            logger.error(f"Error updating device database: {str(e)}")
            raise
        finally:
            cursor.close()
    
    def send_command(self, device_id: str, command: Dict) -> Dict:
        """Send command to device"""
        self.update_devices()
        
        # Find device
        device = None
        for dev_list in [self.manager.outlets, self.manager.switches, 
                        self.manager.bulbs, self.manager.fans]:
            for dev in dev_list:
                if dev.cid == device_id:
                    device = dev
                    break
            if device:
                break

        if not device:
            raise Exception('Device not found')

        # Process command
        try:
            if command['name'] == 'turn':
                if command['value'] == 'on':
                    device.turn_on()
                else:
                    device.turn_off()
            elif command['name'] == 'brightness' and hasattr(device, 'set_brightness'):
                device.set_brightness(int(command['value']))
            else:
                raise Exception(f"Unsupported command: {command['name']}")

            return {
                'success': True,
                'message': 'Command sent successfully'
            }
            
        except Exception as e:
            logger.error(f"Error sending command: {str(e)}")
            raise Exception(f"Failed to send command: {str(e)}")

    def process_batch(self, maxCommands: int = 5) -> Dict:
        """Process a batch of commands from the queue"""
        if not self.commandQueue:
            return {'success': False, 'message': 'No command queue configured'}

        commands = self.commandQueue.getNextBatch(maxCommands)
        results = []

        for command in commands:
            try:
                result = self.send_command(
                    command['device'],
                    json.loads(command['command'])
                )
                self.commandQueue.markCommandComplete(command['id'], True)
                results.append({
                    'command_id': command['id'],
                    'result': result,
                    'success': True
                })
                
            except Exception as e:
                self.commandQueue.markCommandComplete(
                    command['id'],
                    False,
                    str(e)
                )
                results.append({
                    'command_id': command['id'],
                    'error': str(e),
                    'success': False
                })

        return {
            'success': True,
            'processed': len(results),
            'results': results
        }

def parse_php_config(config_path: str) -> Dict[str, Any]:
    """Get configuration from PHP config file"""
    try:
        with open(config_path, 'r') as f:
            config_content = f.read()
            
        # Extract database configuration
        db_match = re.search(r"\['db_config'\]\s*=\s*\[(.*?)\];", config_content, re.DOTALL)
        if not db_match:
            raise Exception("Database configuration not found")
            
        db_str = db_match.group(1)
        db_config = {
            'host': re.search(r"'host'\s*=>\s*'(.*?)'", db_str).group(1),
            'dbname': re.search(r"'dbname'\s*=>\s*'(.*?)'", db_str).group(1),
            'user': re.search(r"'user'\s*=>\s*'(.*?)'", db_str).group(1),
            'password': re.search(r"'password'\s*=>\s*'(.*?)'", db_str).group(1)
        }

        # Extract VeSync credentials
        vesync_match = re.search(r"\['vesync_api'\]\s*=\s*\[(.*?)\];", config_content, re.DOTALL)
        if not vesync_match:
            raise Exception("VeSync configuration not found")
        
        vesync_str = vesync_match.group(1)
        vesync_config = {
            'username': re.search(r"'user'\s*=>\s*'(.*?)'", vesync_str).group(1),
            'password': re.search(r"'password'\s*=>\s*'(.*?)'", vesync_str).group(1)
        }

        return (db_config, vesync_config)

    except Exception as e:
        logger.error(f"Error getting config: {str(e)}")
        raise

def process_commands():
    """Main function to process VeSync commands"""
    try:
        # Get config file path
        script_dir = os.path.dirname(os.path.abspath(__file__))
        config_path = os.path.join(script_dir, '..', 'config', 'config.php')
        
        # Get configuration
        db_config, vesync_config = parse_php_config(config_path)
        
        # Initialize VeSync API
        vesync = VeSyncAPI(
            username=vesync_config['username'],
            password=vesync_config['password'],
            dbConfig=db_config
        )
        
        # Login to VeSync
        if not vesync.login():
            raise Exception("Failed to login to VeSync")
            
        # Main processing loop
        while True:
            try:
                # Process a batch of commands
                result = vesync.process_batch(5)
                
                if result['success']:
                    if result['processed'] > 0:
                        logger.info(f"Processed {result['processed']} commands")
                        for cmd_result in result['results']:
                            if cmd_result['success']:
                                logger.info(f"Command {cmd_result['command_id']} executed successfully")
                            else:
                                logger.info(f"Command {cmd_result['command_id']} failed: {cmd_result['error']}")
                                
                # Sleep briefly between batches
                time.sleep(0.01)  # 100ms pause between checks
                
            except Exception as e:
                logger.error(f"Error processing batch: {str(e)}")
                time.sleep(5)  # Sleep longer on error
                
    except Exception as e:
        logger.error(f"Fatal error: {str(e)}")
        raise

if __name__ == "__main__":
    try:
        process_commands()
    except Exception as e:
        logger.error(f"Script error: {str(e)}")
        sys.exit(1)