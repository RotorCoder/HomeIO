<?php

require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

$log = new logger(basename(__FILE__, '.php')."_", __DIR__);
$dbConfig = $config['db_config'];

function getDatabaseConnection($dbConfig) {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];

    try {
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

function addUnmappedButton($pdo, $remoteName, $buttonNumber) {
    $stmt = $pdo->prepare("
        INSERT INTO remote_button_mappings 
        (remote_name, button_number, command_name, mapped) 
        VALUES (?, ?, 'toggle', FALSE)
        ON DUPLICATE KEY UPDATE mapped = mapped
    ");
    
    try {
        $stmt->execute([$remoteName, $buttonNumber]);
        if ($stmt->rowCount() > 0) {
            $log->logInfoMsg("Added new unmapped button for remote {$remoteName} button {$buttonNumber}");
        }
    } catch (Exception $e) {
        $log->logErrorMsg("Failed to add unmapped button: " . $e->getMessage());
        throw $e;
    }
}

function getButtonMapping($pdo, $remoteName, $buttonNumber) {
    // First try to get existing mapping
    $stmt = $pdo->prepare("
        SELECT * 
        FROM remote_button_mappings 
        WHERE remote_name = ? AND button_number = ?
    ");
    $stmt->execute([$remoteName, $buttonNumber]);
    $mapping = $stmt->fetch();
    
    if (!$mapping) {
        // If no mapping exists, add unmapped button entry
        addUnmappedButton($pdo, $remoteName, $buttonNumber);
        return null;
    }

    // If button exists but is unmapped, return null
    if (!$mapping['mapped']) {
        return null;
    }

    // Convert database record to command structure
    $command = [
        'name' => $mapping['command_name']
    ];

    if ($mapping['command_value']) {
        $command['value'] = $mapping['command_value'];
    }

    if ($mapping['toggle_states']) {
        $command['states'] = json_decode($mapping['toggle_states'], true);
    }

    // Only include target if it exists
    $result = ['command' => $command];
    if ($mapping['target_type'] && $mapping['target_id']) {
        $result[$mapping['target_type']] = $mapping['target_id'];
    }

    return $result;
}

function getGroupDevices($pdo, $groupId) {
    $stmt = $pdo->prepare("SELECT devices FROM device_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? json_decode($result['devices'], true) : [];
}

function getCurrentGroupState($pdo, $groupId) {
    $devices = getGroupDevices($pdo, $groupId);
    if (empty($devices)) return false;

    $stmt = $pdo->prepare("SELECT powerState FROM devices WHERE device = ?");
    $stmt->execute([$devices[0]]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['powerState'] : false;
}

function getCurrentDeviceState($pdo, $deviceId) {
    $stmt = $pdo->prepare("SELECT powerState FROM devices WHERE device = ?");
    $stmt->execute([$deviceId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['powerState'] : false;
}

function getNextToggleState($pdo, $deviceId, $states) {
    // Get current device preferred states
    $stmt = $pdo->prepare("
        SELECT preferredPowerState, preferredBrightness, high, medium, low 
        FROM devices 
        WHERE device = ?
    ");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        return null;
    }

    // Create map of brightness levels to their values
    $brightnessLevels = [
        'high' => $device['high'],
        'medium' => $device['medium'],
        'low' => $device['low']
    ];

    // Determine current state from preferred states
    $currentState = 'off';
    if ($device['preferredPowerState'] === 'on') {
        if ($device['preferredBrightness'] !== null) {
            // Find closest brightness level to preferred brightness
            $currentBrightness = $device['preferredBrightness'];
            $minDiff = PHP_FLOAT_MAX;
            foreach ($brightnessLevels as $level => $value) {
                if ($value !== null) {
                    $diff = abs($currentBrightness - $value);
                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $currentState = $level;
                    }
                }
            }
            // If no brightness level matches closely enough, use 'on'
            if ($minDiff > 10) {  // threshold of 10% difference
                $currentState = 'on';
            }
        } else {
            $currentState = 'on';
        }
    }

    // For simple on/off toggle, handle directly
    if ($states === ['on', 'off']) {
        return [
            'name' => 'turn',
            'value' => ($device['preferredPowerState'] === 'on') ? 'off' : 'on'
        ];
    }

    // Find current state in array and get next state
    $currentIndex = array_search($currentState, $states);
    if ($currentIndex === false) {
        // If current state isn't in array, start from beginning
        $nextState = $states[0];
    } else {
        // Move to next state, wrapping around to start if at end
        $nextIndex = ($currentIndex + 1) % count($states);
        $nextState = $states[$nextIndex];
    }

    // Convert state to command
    if ($nextState === 'off') {
        return [
            'name' => 'turn',
            'value' => 'off'
        ];
    } else if ($nextState === 'on') {
        return [
            'name' => 'turn',
            'value' => 'on'
        ];
    } else if (isset($brightnessLevels[$nextState]) && $brightnessLevels[$nextState] !== null) {
        return [
            'name' => 'brightness',
            'value' => $brightnessLevels[$nextState]
        ];
    }

    // Fallback
    return [
        'name' => 'turn',
        'value' => 'on'
    ];
}

function updateDevicePreferences($pdo, $deviceId, $command) {
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET preferredPowerState = CASE 
                WHEN :cmd_name = 'turn' THEN :power_state
                WHEN :cmd_name = 'brightness' THEN 'on'
                ELSE preferredPowerState 
            END,
            preferredBrightness = CASE 
                WHEN :cmd_name = 'brightness' THEN :brightness
                ELSE preferredBrightness 
            END
        WHERE device = :device
    ");
    
    $stmt->execute([
        'cmd_name' => $command['name'],
        'power_state' => $command['name'] === 'turn' ? $command['value'] : null,
        'brightness' => $command['name'] === 'brightness' ? $command['value'] : null,
        'device' => $deviceId
    ]);
}

try {
    $log->logInfoMsg("Starting remote button monitor");
    $pdo = getDatabaseConnection($dbConfig);
    
    while (true) {
        try {
            // Test connection and reconnect if needed
            try {
                $pdo->query("SELECT 1");
            } catch (PDOException $e) {
                $log->logInfoMsg("Reconnecting to database...");
                $pdo = getDatabaseConnection($dbConfig);
            }

            // Get new button presses
            $stmt = $pdo->prepare("
                SELECT id, remote_name, button_number 
                FROM remote_buttons 
                WHERE status = 'received'
                ORDER BY timestamp ASC
                LIMIT 10
            ");
            $stmt->execute();
            $buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($buttons as $button) {
                $mapping = getButtonMapping($pdo, $button['remote_name'], $button['button_number']);

                if ($mapping) {
                    try {
                        $pdo->beginTransaction();

                        // Update status to processing
                        $stmt = $pdo->prepare("
                            UPDATE remote_buttons 
                            SET status = 'processing' 
                            WHERE id = ? AND status = 'received'
                        ");
                        $stmt->execute([$button['id']]);

                        if ($stmt->rowCount() > 0) {
                            if (isset($mapping['group'])) {
                                // Handle group command
                                $groupId = $mapping['group'];
                                $devices = getGroupDevices($pdo, $groupId);
                                
                                if ($mapping['command']['name'] === 'toggle' && isset($mapping['command']['states'])) {
                                    if (!empty($devices)) {
                                        // Use first device in group to determine next state
                                        $command = getNextToggleState($pdo, $devices[0], $mapping['command']['states']);
                                    }
                                } else {
                                    $command = $mapping['command'];
                                }
                                
                                // Queue command and update preferences for each device in the group
                                foreach ($devices as $deviceId) {
                                    // Update device preferences first
                                    updateDevicePreferences($pdo, $deviceId, $command);
                                    
                                    // Then queue the command
                                    $stmt = $pdo->prepare("
                                        INSERT INTO command_queue 
                                        (device, model, command, brand) 
                                        SELECT device, model, :command, brand
                                        FROM devices
                                        WHERE device = :device
                                    ");
                                    $stmt->execute([
                                        'command' => json_encode($command),
                                        'device' => $deviceId
                                    ]);
                                }
                            } else {
                                // Handle individual device command
                                $stmt = $pdo->prepare("
                                    SELECT model, brand 
                                    FROM devices 
                                    WHERE device = ?
                                ");
                                $stmt->execute([$mapping['device']]);
                                $device = $stmt->fetch(PDO::FETCH_ASSOC);

                                if ($device) {
                                    if ($mapping['command']['name'] === 'toggle' && isset($mapping['command']['states'])) {
                                        $command = getNextToggleState($pdo, $mapping['device'], $mapping['command']['states']);
                                    } else {
                                        $command = $mapping['command'];
                                    }

                                    // Update device preferences first
                                    updateDevicePreferences($pdo, $mapping['device'], $command);

                                    // Then queue the command
                                    $stmt = $pdo->prepare("
                                        INSERT INTO command_queue 
                                        (device, model, command, brand) 
                                        VALUES (?, ?, ?, ?)
                                    ");
                                    $stmt->execute([
                                        $mapping['device'],
                                        $device['model'],
                                        json_encode($command),
                                        $device['brand']
                                    ]);
                                }
                            }

                            // Mark as executed
                            $stmt = $pdo->prepare("
                                UPDATE remote_buttons 
                                SET status = 'executed' 
                                WHERE id = ?
                            ");
                            $stmt->execute([$button['id']]);

                            $log->logInfoMsg("Command processed for " . 
                                (isset($mapping['group']) ? "group {$mapping['group']}" : "device {$mapping['device']}"));
                        }
                        
                        $pdo->commit();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                } else {
                    // Mark invalid buttons as executed
                    $stmt = $pdo->prepare("
                        UPDATE remote_buttons 
                        SET status = 'executed' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$button['id']]);

                    $log->logInfoMsg("No mapping found for remote {$button['remote_name']} button {$button['button_number']}");
                }
            }

            usleep(100000); // 100ms pause
            
        } catch (Exception $e) {
            $log->logErrorMsg("Error processing buttons: " . $e->getMessage());
            sleep(5);
            
            // Attempt to reconnect
            try {
                $pdo = getDatabaseConnection($dbConfig);
            } catch (Exception $e) {
                $log->logErrorMsg("Failed to reconnect: " . $e->getMessage());
            }
        }
    }
    
} catch (Exception $e) {
    $log->logErrorMsg("Fatal error: " . $e->getMessage());
    exit(1);
}