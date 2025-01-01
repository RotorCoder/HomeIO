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

def update_devices(self):
    """Update device lists and status if interval has passed"""
    current_time = time.time()
    if current_time - self.last_update >= self.update_interval:
        # Update with details=True to get all info in one call
        success = self.manager.update(details=True)  
        if success:
            self.last_update = current_time
        return success
    return True

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