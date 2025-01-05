<?php

//  http://192.168.99.221:8086/?x10command=DEVICE~sendplc~%22A1%20OFF%22&time=1735508950088

require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

// Global variables
$lastCommand = [
    'device' => '',
    'command' => '',
    'timestamp' => 0
];

$pdo = null; // Global PDO connection

// Initialize PDO 
try {
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    // Create devices table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device VARCHAR(255) NOT NULL UNIQUE,
            model VARCHAR(255),
            device_name VARCHAR(255),
            controllable BOOLEAN DEFAULT 1,
            retrievable BOOLEAN DEFAULT 1,
            supportCmds TEXT,
            preferredColorTemp_rangeMin INT,
            preferredColorTemp_rangeMax INT,
            brand VARCHAR(50),
            online BOOLEAN DEFAULT 0,
            preferredPowerState VARCHAR(10),
            preferredBrightness INT,
            preferredColorTemp INT,
            x10Code VARCHAR(10),
            room INT,
            deviceGroup INT,
            showInGroupOnly BOOLEAN DEFAULT 0,
            low INT DEFAULT 25,
            medium INT DEFAULT 50,
            high INT DEFAULT 75,
            preferredColorTem INT
        )
    ");
    
    // Create device_groups table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS device_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            model VARCHAR(255),
            reference_device VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create command_queue table if it doesn't exist
    $pdo->exec("
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
    ");
    
    // Create rooms table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_name VARCHAR(255) NOT NULL,
            thermometer VARCHAR(50),
            tab_order INT DEFAULT 0
        )
    ");

} catch (Exception $e) {
    $log->logErrorMsg("Database initialization error: " . $e->getMessage());
    exit(1);
}

function getDeviceConfig($type, $id) {
    global $config, $log, $pdo;
    
    try {
        if ($type === 'group') {
            // Get the devices array from device_groups
            $stmt = $pdo->prepare("SELECT devices FROM device_groups WHERE id = ?");
            $stmt->execute([$id]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($group && !empty($group['devices'])) {
                // Parse the JSON array and get the first device ID
                $devices = json_decode($group['devices'], true);
                if (!empty($devices)) {
                    $firstDevice = $devices[0];
                    
                    // Get settings from the first device
                    $stmt = $pdo->prepare("SELECT preferredPowerState, preferredBrightness, low, medium, high FROM devices WHERE device = ?");
                    $stmt->execute([$firstDevice]);
                    $config = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($config) {
                        $log->logInfoMsg("Config found for group $id using first device $firstDevice: " . json_encode($config));
                        return $config;
                    }
                }
            }
            $log->logInfoMsg("No config found for group $id");
            return null;
            
        } else {
            // Regular device lookup remains unchanged
            $stmt = $pdo->prepare("SELECT preferredPowerState, preferredBrightness, low, medium, high FROM devices WHERE device = ?");
            $stmt->execute([$id]);
            
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($config) {
                $log->logInfoMsg("Config found for device $id: " . json_encode($config));
            } else {
                $log->logInfoMsg("No config found for device $id");
            }
            return $config;
        }
        
    } catch (Exception $e) {
        $log->logInfoMsg("Database error: " . $e->getMessage());
        return null;
    }
}

function getX10DeviceMapping($x10Code) {
    global $config, $log, $pdo;
    try {
        $x10Code = strtolower(trim($x10Code));
        $log->logInfoMsg("Looking up X10 code: $x10Code");
        
        // First check individual devices
        $stmt = $pdo->prepare("SELECT device, model FROM devices WHERE x10Code = ?");
        $stmt->execute([$x10Code]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($device) {
            $log->logInfoMsg("Found device for X10 code: $x10Code");
            return [
                'type' => 'device',
                'id' => $device['device'],
                'model' => $device['model']
            ];
        }
        
        // If not found in devices, check device_groups table
        $stmt = $pdo->prepare("SELECT id, name, model FROM device_groups WHERE x10Code = ?");
        $stmt->execute([$x10Code]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($group) {
            $log->logInfoMsg("Found group for X10 code: $x10Code");
            return [
                'type' => 'group',
                'id' => $group['id'],
                'model' => $group['model']
            ];
        }
        
        $log->logInfoMsg("No mapping found for X10 code: $x10Code");
        return null;
        
    } catch (Exception $e) {
        $log->logInfoMsg("Database error in getX10DeviceMapping: " . $e->getMessage());
        return null;
    }
}

function getNextpreferredBrightnessLevel($currentpreferredBrightness, $deviceConfig, $command) {
    global $config, $log;
    
    $current = (int)$currentpreferredBrightness;
    $low = (int)$deviceConfig['low'];
    $medium = (int)$deviceConfig['medium'];
    $high = (int)$deviceConfig['high'];
    
    $log->logInfoMsg("Low: ".$current." - ".$low." - ".$medium." - ".$high);
    
    if ($command === 'Bright') {
        if ($current < $low) {
            return $low;
        } elseif ($current < $medium) {
            return $medium;
        } elseif ($current < $high) {
            return $high;
        } else {
            return $current;
        }
    } else if ($command === 'Dim') {
        if ($current > $high) {
            return $high;
        } elseif ($current > $medium) {
            return $medium;
        } elseif ($current > $low) {
            return $low;
        } else {
            return $current;
        }
    }

    return $current;
}

function queueDeviceCommand($x10Code, $command) {
    global $config, $log;
    
    $log->logInfoMsg("Queueing command for X10 code: $x10Code, command: $command");
    
    $deviceMapping = getX10DeviceMapping($x10Code);
    if (!$deviceMapping) {
        $log->logInfoMsg("ERROR: No device/group mapping found for X10 code: $x10Code");
        return;
    }

    // Get device or group configuration using first device's settings
    $deviceConfig = getDeviceConfig($deviceMapping['type'], $deviceMapping['id']);
    if (!$deviceConfig) {
        $log->logInfoMsg("ERROR: Could not get configuration for {$deviceMapping['type']} {$deviceMapping['id']}");
        return;
    }

    // Translate X10 command to API command
    $apiCommand = '';
    $apiValue = '';
    
    if ($command === 'Bright' || $command === 'Dim') {
        $apiCommand = 'brightness';
        $apiValue = getNextpreferredBrightnessLevel(
            $deviceConfig['preferredBrightness'], 
            $deviceConfig, 
            $command
        );
        
        // Skip if no brightness change needed
        if ($apiValue == $deviceConfig['preferredBrightness']) {
            $log->logInfoMsg("Skipping brightness command - already at " . 
                ($command === 'Bright' ? "maximum" : "minimum") . " level");
            return;
        }
    } else {
        $apiCommand = 'turn';
        $apiValue = (strtolower($command) === 'on' ? 'on' : 'off');
    }

    try {
        // Prepare the command data
        $commandData = [
            'type' => $deviceMapping['type'],
            'id' => $deviceMapping['id'],
            'command' => $apiCommand,
            'value' => $apiValue
        ];

        $ch = curl_init('https://mittencoder.com/homeio/api/queue-command');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($commandData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['homeio_api_key']
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            throw new Exception("CURL error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $response = json_decode($result, true);
            if ($response && isset($response['success']) && $response['success']) {
                $log->logInfoMsg("Command queued successfully for {$deviceMapping['type']} {$deviceMapping['id']} - $apiCommand - $apiValue");
            } else {
                throw new Exception("API returned success=false: " . ($response['error'] ?? 'Unknown error'));
            }
        } else {
            throw new Exception("HTTP error code: " . $httpCode . ", Response: " . $result);
        }
    } catch (Exception $e) {
        $log->logInfoMsg("ERROR queueing command: " . $e->getMessage());
    }
}

function isDuplicate($device, $command, $timestamp) {
    global $lastCommand, $log;
    
    $log->logInfoMsg("Checking for duplicate - Last Command: $lastCommand[device] $lastCommand[command] $lastCommand[timestamp]");
    $log->logInfoMsg("Checking for duplicate - Current Command: $device $command $timestamp");

    $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));
    
    if (empty($lastCommand['timestamp'])) {
        $lastCommand['device'] = $device;
        $lastCommand['command'] = $command;
        $lastCommand['timestamp'] = $timestamp;
        return false;
    }
    
    $timeDiff = strtotime($timestamp) - strtotime($lastCommand['timestamp']);
    $log->logInfoMsg("Time difference between commands: $timeDiff seconds");
    
    if ($device === $lastCommand['device'] && 
        $command === $lastCommand['command'] && 
        $timeDiff <= 1) {
        $log->logInfoMsg("Duplicate command detected - skipping");
        return true;
    }
    
    $lastCommand['device'] = $device;
    $lastCommand['command'] = $command;
    $lastCommand['timestamp'] = $timestamp;
    return false;
}

// Main execution loop
try {
    $log->logInfoMsg("Starting X10 monitor with command queuing");
    
    $tempDir = __DIR__ . '/temp';
    $positionFile = $tempDir . '/x10_log_last_position.txt';
    $logFile = $config['x10_log_file'];  // Store the log file path
    
    // Ensure temp directory exists with correct permissions
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0775, true);
        chgrp($tempDir, 'www-data');
    }
    
    // Ensure position file exists with correct permissions
    if (file_exists($positionFile)) {
        chgrp($positionFile, 'www-data');
        chmod($positionFile, 0664);
    }
    
    // Initialize state with current file position and inode
    if (file_exists($logFile)) {  // Use stored path
        clearstatcache(true, $logFile);  // Clear the file status cache
        $initialPosition = filesize($logFile);
        $initialInode = fileinode($logFile);
        
        $initialState = [
            'position' => $initialPosition,
            'inode' => $initialInode
        ];
        
        $log->logInfoMsg("Initial file position set to: $initialPosition, inode: $initialInode");
        file_put_contents($positionFile, json_encode($initialState));
    } else {
        $log->logInfoMsg("Log file not found, starting from position 0");
        file_put_contents($positionFile, json_encode(['position' => 0, 'inode' => null]));
    }

    while (true) {
        try {
            clearstatcache(true, $logFile);  // Clear cache before each check
            
            if (!file_exists($logFile)) {
                $log->logInfoMsg("Log file not found, waiting...");
                sleep(1);
                continue;
            }

            $handle = fopen($logFile, 'r');
            if (!$handle) {
                $log->logInfoMsg("Could not open log file, waiting...");
                sleep(1);
                continue;
            }

            // Check if file has been truncated or rotated
            $currentSize = filesize($logFile);
            $currentInode = fileinode($logFile);
            
            // Store these in a state file along with the position
            $stateData = is_file($positionFile) ? json_decode(file_get_contents($positionFile), true) : [];
            $lastInode = isset($stateData['inode']) ? $stateData['inode'] : null;
            $lastPosition = isset($stateData['position']) ? (int)$stateData['position'] : 0;
            
            // Reset position if file was truncated or rotated
            if ($currentSize < $lastPosition || ($lastInode && $currentInode !== $lastInode)) {
                $log->logInfoMsg("Log file has been truncated or rotated, resetting position to start of file");
                $lastPosition = 0;
            }
            
            fseek($handle, $lastPosition);

            $newPosition = $lastPosition;
            while (($line = fgets($handle)) !== false) {
                $newPosition = ftell($handle);
                
                if (strpos(strtolower($line), 'bszaction:"recvrf"') !== false) {
                    if (strpos(strtolower($line), 'bszparm3:0') !== false && strpos(strtolower($line), 'dim') === false && strpos(strtolower($line), 'bright') === false) {
                        if (preg_match('/bszParm1:([a-z][0-9]+),\s*bszParm2:(\w+),/', $line, $matches)) {
                            $x10Code = strtolower(trim($matches[1]));
                            $command = $matches[2];
                            $timestamp = substr($line, 0, 19);
                            
                            $log->logInfoMsg("Extracted X10 code: $x10Code");  // Add debug logging
                            
                            if (!isDuplicate($x10Code, $command, $timestamp)) {
                                $log->logInfoMsg("Received X10 command: $timestamp - Code $x10Code $command");
                                queueDeviceCommand($x10Code, $command);
                            }
                        }
                    }
                }
                if (strpos(strtolower($line), 'bszaction:"recvplc"') !== false) {
                    if (preg_match('/bszParm1:([a-z][0-9]+),\s*bszParm2:(\w+),/', $line, $matches)) {
                        $x10Code = strtolower(trim($matches[1]));
                        $command = $matches[2];
                        $timestamp = substr($line, 0, 19);
                        
                        $log->logInfoMsg("Extracted X10 code: $x10Code");  // Add debug logging
                        
                        if (!isDuplicate($x10Code, $command, $timestamp)) {
                            $log->logInfoMsg("Received X10 command: $timestamp - Code $x10Code $command");
                            queueDeviceCommand($x10Code, $command);
                        }
                    }
                }
            }

            $stateData = [
                'position' => $newPosition,
                'inode' => $currentInode
            ];
            file_put_contents($positionFile, json_encode($stateData));
            fclose($handle);

            // Small sleep to prevent excessive CPU usage
            usleep(10000); // 10ms pause

        } catch (Exception $e) {
            $log->logInfoMsg("Error in processing loop: " . $e->getMessage());
            sleep(1); // Longer sleep on error
        }
    }

} catch (Exception $e) {
    $log->logInfoMsg("Fatal error: " . $e->getMessage());
    exit(1);
}
