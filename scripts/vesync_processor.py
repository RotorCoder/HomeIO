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

        # Return both configs as a tuple
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
        
        # Add shared directory to Python path
        shared_path = "/var/www/html/shared"
        if shared_path not in sys.path:
            sys.path.append(shared_path)
            
        # Import VeSyncAPI after adding shared path
        from vesync_lib import VeSyncAPI
        
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
                time.sleep(0.05)  # 50ms pause between checks
                
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