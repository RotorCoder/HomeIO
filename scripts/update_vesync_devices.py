#!/home/sjones/venv/bin/python3
import sys
import logging
import json
import os
import time
from typing import Dict, Any

# Set up logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

def parse_php_config(config_path: str) -> Dict[str, Any]:
    """Get configuration from PHP config file"""
    try:
        with open(config_path, 'r') as f:
            config_content = f.read()
            
        # Extract database configuration
        import re
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

        return db_config, vesync_config

    except Exception as e:
        logger.error(f"Error getting config: {str(e)}")
        raise

def measure_execution_time(func):
    """Measure execution time of a function"""
    start = time.time()
    result = func()
    duration = round((time.time() - start) * 1000)  # Convert to milliseconds
    return {'result': result, 'duration': duration}

def update_devices():
    """Main function to update VeSync devices"""
    try:
        # Get config file path
        script_dir = os.path.dirname(os.path.abspath(__file__))
        config_path = os.path.join(script_dir, '..', 'config', 'config.php')
        
        # Get configuration
        db_config, vesync_config = parse_php_config(config_path)
        
        # Add shared directory to Python path
        shared_path = "/var/www/html/shared"
        if shared_path not in sys.path:
            sys.path.append(shared_path)
            
        # Import VeSyncAPI after adding shared path
        from vesync_lib import VeSyncAPI
        
        timing = {}
        
        # Initialize VeSync API and get devices
        api_timing = measure_execution_time(lambda: VeSyncAPI(
            username=vesync_config['username'],
            password=vesync_config['password'],
            dbConfig=db_config
        ))
        timing['init'] = api_timing['duration']
        
        vesync = api_timing['result']
        
        # Login to VeSync
        login_timing = measure_execution_time(vesync.login)
        if not login_timing['result']:
            raise Exception("Failed to login to VeSync")
        timing['login'] = login_timing['duration']
        
        # Get devices
        devices_timing = measure_execution_time(vesync.get_devices)
        timing['devices'] = devices_timing['duration']
        devices = devices_timing['result']
        
        # Update devices in database
        updated_devices = []
        update_timing = measure_execution_time(lambda: [
            process_device_update(vesync, device_type, device)
            for device_type, device_list in devices.items()
            for device in device_list
        ])
        timing['updates'] = update_timing['duration']
        updated_devices = update_timing['result']
        
        result = {
            'devices': updated_devices,
            'updated': time.strftime('%Y-%m-%d %H:%M:%S'),
            'timing': timing
        }
        
        logger.info(json.dumps(result, indent=2))
        return result
        
    except Exception as e:
        logger.error(f"Error updating devices: {str(e)}")
        raise

def process_device_update(vesync, device_type, device):
    """Process individual device update"""
    try:
        device_info = {
            'device': device.cid,
            'model': device.device_type,
            'device_name': device.device_name,
            'brand': 'vesync',
            'online': device.connection_status == 'online',
            'powerState': 'on' if device.device_status == 'on' else 'off'
        }
        
        # Add device-specific properties
        if hasattr(device, 'brightness'):
            device_info['brightness'] = device.brightness
        if hasattr(device, 'energy_today'):
            device_info['energy_today'] = device.energy_today  # Changed from 'energy'
            device_info['power'] = device.power
            device_info['voltage'] = device.voltage
            
        return vesync.update_device_database(device)
        
    except Exception as e:
        logger.error(f"Error processing device {device.device_name}: {str(e)}")
        return {
            'device': device.cid,
            'success': False,
            'error': str(e)
        }

if __name__ == "__main__":
    try:
        update_devices()
    except Exception as e:
        logger.error(f"Script error: {str(e)}")
        sys.exit(1)