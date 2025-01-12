#!/bin/bash

# Set the root path to your project directory
ROOT_PATH="/var/www/html/homeio"

# Create timestamp for filename
timestamp=$(date +"%Y-%m-%d_%H-%M-%S")
output_file="$ROOT_PATH/scripts/combined_reference_$timestamp.txt"

# Create or clear the output file
echo "Combined Reference File - Created on $(date)" > "$output_file"
echo "Root Path: $ROOT_PATH" >> "$output_file"
echo "" >> "$output_file"

# Function to add a file to the output
add_file() {
    full_path="$ROOT_PATH/$1"
    if [ -f "$full_path" ]; then
        echo "================================================================================" >> "$output_file"
        echo "FILE: $1" >> "$output_file"
        echo "================================================================================" >> "$output_file"
        echo "" >> "$output_file"
        cat "$full_path" >> "$output_file"
        echo "" >> "$output_file"
        echo "" >> "$output_file"
    else
        echo "Warning: File not found - $full_path" >&2
    fi
}

# Add each file individually instead of using an array
add_file "index.php"
add_file "api/index.php"
add_file "api/vesync.php"
add_file "api/govee.php"
add_file "api/hue.php"
add_file "scripts/govee_processor.php"
add_file "scripts/hue_processor.php"
add_file "scripts/hue_updater.php"
add_file "scripts/vesync_processor.py"
add_file "scripts/ble_remote_monitor.php"
add_file "config/config.php"
add_file "templates/config-popup.php"
add_file "templates/history-popup.php"
add_file "templates/all-temps-popup.php"
add_file "templates/room-popup.php"
add_file "templates/group-popup.php"
add_file "assets/js/api.js"
add_file "assets/js/config.js"
add_file "assets/js/devices.js"
add_file "assets/js/groups.js"
add_file "assets/js/main.js"
add_file "assets/js/temperature.js"
add_file "assets/js/ui.js"
add_file "assets/js/rooms.js"
add_file "assets/css/styles.css"
add_file "../shared/logger.php"

echo "Reference file created: $output_file"