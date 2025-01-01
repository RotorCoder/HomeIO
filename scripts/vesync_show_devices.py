#!/home/sjones/venv/bin/python3
import sys
import logging
import json
import os
import re

# Set up logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

def get_config():
    """Get configuration from PHP config file"""
    try:
        # Get absolute path to config file
        script_dir = os.path.dirname(os.path.abspath(__file__))
        config_dir = os.path.dirname(script_dir)  # Go up one level to homeio root
        config_path = os.path.join(config_dir, 'config', 'config.php')
        
        logger.debug(f"Config path: {config_path}")
        logger.debug(f"Current directory: {os.getcwd()}")

        # Set correct shared path
        shared_path = "/var/www/html/shared"
        logger.debug(f"Using shared path: {shared_path}")

        # Extract database configuration
        with open(config_path, 'r') as f:
            config_content = f.read()
            
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
        vesync_user = re.search(r"'user'\s*=>\s*'(.*?)'", vesync_str).group(1)
        vesync_pass = re.search(r"'password'\s*=>\s*'(.*?)'", vesync_str).group(1)
        
        # Add the shared directory to Python path
        if shared_path not in sys.path:
            sys.path.append(shared_path)
            logger.debug(f"Full Python path: {sys.path}")
            
        # Check if vesync_lib.py exists
        vesync_lib_path = os.path.join(shared_path, 'vesync_lib.py')
        if not os.path.exists(vesync_lib_path):
            raise Exception(f"vesync_lib.py not found at {vesync_lib_path}")
        else:
            logger.debug(f"Found vesync_lib.py at {vesync_lib_path}")
            
        return db_config, vesync_user, vesync_pass

    except Exception as e:
        logger.error(f"Error getting config: {str(e)}")
        raise

def test_devices():
    try:
        # Get configuration
        db_config, vesync_user, vesync_pass = get_config()
        
        # Import VeSyncAPI after adding shared path to sys.path
        try:
            from vesync_lib import VeSyncAPI
        except ImportError as e:
            logger.error(f"Failed to import vesync_lib: {str(e)}")
            logger.error("Current sys.path:")
            for path in sys.path:
                logger.error(f"  {path}")
            raise
        
        # Initialize VeSync API
        vesync = VeSyncAPI(
            username=vesync_user,
            password=vesync_pass,
            dbConfig=db_config
        )
        
        # Login
        logger.info("Attempting to login to VeSync...")
        if not vesync.login():
            logger.error("Failed to login to VeSync")
            return
        logger.info("Login successful")
        
        # Get all devices
        logger.info("Fetching devices...")
        devices = vesync.get_devices()
        
        # Display devices by type
        for device_type, device_list in devices.items():
            print(f"\n{device_type.upper()}:")
            print("-" * 40)
            
            if not device_list:
                print("No devices found")
                continue
                
            for device in device_list:
                print(f"\nDevice Name: {device.device_name}")
                print(f"Device ID: {device.cid}")
                print(f"Model: {device.device_type}")
                print(f"Status: {device.device_status}")
                print(f"Connection: {device.connection_status}")
                
                # Print device-specific properties
                if hasattr(device, 'brightness'):
                    print(f"Brightness: {device.brightness}%")
                if hasattr(device, 'energy_today'):
                    print(f"Today's Energy: {device.energy_today} kWh")
                    print(f"Current Power: {device.power}W")
                    print(f"Voltage: {device.voltage}V")

    except Exception as e:
        logger.error(f"Error in test_devices: {str(e)}")
        raise

if __name__ == "__main__":
    test_devices()