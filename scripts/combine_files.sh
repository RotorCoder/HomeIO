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

# List of files to combine based on your actual file structure
files=(
    "index.php"
    "config/config.php"
    "components/config-popup.php"
    "components/history-popup.php"
    "assets/js/api.js"
    "assets/js/device-management.js"
    "assets/js/room-management.js"
    "assets/js/ui-controls.js"
    "assets/js/temperature-history.js"
    "assets/js/config-popup.js"
    "assets/js/init.js"
    "assets/css/styles.css"
    "../shared/govee_lib.php"
    "../shared/hue_lib.php"
    "../shared/logger.php"
)

# Process each file
for file in "${files[@]}"; do
    add_file "$file"
done

echo "Reference file created: $output_file"