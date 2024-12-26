<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';

require $config['sharedpath'].'/logger.php';
$log  = new logger(basename(__FILE__, '.php')."_", __DIR__);

function getDatabaseConnection($config) {
    return new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function getDevicesFromDatabase($pdo, $single_device = null, $room = null, $exclude_room = null) {
    global $log;
    $log->logInfoMsg("Getting devices from the database.");
    
    try {
        // Base query with group information
        $baseQuery = "SELECT devices.*, rooms.room_name,
                      device_groups.name as group_name,
                      device_groups.id as group_id 
                      FROM devices 
                      LEFT JOIN rooms ON devices.room = rooms.id
                      LEFT JOIN device_groups ON devices.deviceGroup = device_groups.id";
        
        if ($single_device) {
            $stmt = $pdo->prepare($baseQuery . " WHERE devices.device = ?");
            $stmt->execute([$single_device]);
        } else {
            $whereConditions = [];
            $params = [];
            
            // Only show devices that are either:
            // 1. Not in a group (deviceGroup IS NULL)
            // 2. In a group but marked as visible (showInGroupOnly = 0)
            // 3. The reference device for their group
            $whereConditions[] = "(devices.deviceGroup IS NULL OR 
                                 devices.showInGroupOnly = 0 OR 
                                 devices.device IN (SELECT reference_device FROM device_groups))";
            
            if ($room !== null) {
                $whereConditions[] = "devices.room = ?";
                $params[] = $room;
            } elseif ($exclude_room !== null) {
                $whereConditions[] = "devices.room != ?";
                $params[] = $exclude_room;
            }
            
            $query = $baseQuery;
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $log->logInfoMsg("Executing query: " . $query);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }
        
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For devices that are group references or visible group members,
        // fetch their group members
        foreach ($devices as &$device) {
            if (!empty($device['device']) && !empty($device['deviceGroup'])) {
                // Get all other devices in the same group
                $groupStmt = $pdo->prepare(
                    "SELECT devices.device, devices.device_name, 
                            devices.powerState, devices.online, 
                            devices.brightness
                     FROM devices 
                     WHERE devices.deviceGroup = ? AND devices.device != ?
                     ORDER BY devices.device_name"
                );
                $groupStmt->execute([$device['deviceGroup'], $device['device']]);
                $device['group_members'] = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $log->logInfoMsg(
                    "Found " . count($device['group_members']) . 
                    " group members for device " . $device['device']
                );
            }
        }
        
        return $devices;
        
    } catch (PDOException $e) {
        $log->logErrorMsg("Database error: " . $e->getMessage());
        throw new Exception("Database error occurred");
    } catch (Exception $e) {
        $log->logErrorMsg("Error in getDevicesFromDatabase: " . $e->getMessage());
        throw $e;
    }
}

function getGoveeDevices($config) {
    global $log;
    $log->logInfoMsg("Getting all devices from Govee API.");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://developer-api.govee.com/v1/devices',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array(
            'Govee-API-Key: ' . $config['govee_api_key'],
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return [
        'headers' => $headers,
        'body' => $body,
        'statusCode' => $statusCode
    ];
}

function getDeviceState($device, $config) {
    global $log;
    $log->logInfoMsg("Getting device state for ".$device['device']." from Govee API");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://developer-api.govee.com/v1/devices/state?device=" . $device['device'] . "&model=" . $device['model'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array(
            'Govee-API-Key: ' . $config['govee_api_key'],
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $state_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return [
        'headers' => $headers,
        'body' => $body,
        'statusCode' => $state_status
    ];
}

function updateDeviceDatabase($pdo, $device) {
    global $log;
    
    // First get current device data from database
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device['device']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Define mappings for new values
    $new_values = [
        'device' => $device['device'],
        'model' => $device['model'],
        'device_name' => $device['deviceName'],
        'controllable' => $device['controllable'] ? 1 : 0,
        'retrievable' => $device['retrievable'] ? 1 : 0,
        'supportCmds' => json_encode($device['supportCmds']),
        'colorTem_rangeMin' => null,
        'colorTem_rangeMax' => null
    ];
    
    // Add color temperature range if available
    if (in_array('colorTem', $device['supportCmds']) && 
        isset($device['properties']) && 
        isset($device['properties']['colorTem']) && 
        isset($device['properties']['colorTem']['range'])) {
        
        $new_values['colorTem_rangeMin'] = $device['properties']['colorTem']['range']['min'];
        $new_values['colorTem_rangeMax'] = $device['properties']['colorTem']['range']['max'];
    }
    
    // Compare and log changes
    $changes = [];
    $updates = [];
    $params = [':device' => $device['device']];
    
    if (!$current) {
        // New device - log all values as changes
        $log->logInfoMsg("New device detected: {$device['device']}");
        foreach ($new_values as $key => $value) {
            if ($value !== null) {
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
                $changes[] = "Initial $key set to " . (is_bool($value) ? ($value ? 'true' : 'false') : $value);
            }
        }
        
        if (!empty($updates)) {
            $sql = "INSERT INTO devices SET " . implode(", ", $updates);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        return true;  // Return true for new devices
    } else {
        // Existing device - check for changes
        foreach ($new_values as $key => $value) {
            if ($value === null || !isset($current[$key])) {
                continue;
            }
            
            // Special handling for boolean values
            if (in_array($key, ['controllable', 'retrievable'])) {
                $current_value = (bool)$current[$key];
                $new_value = (bool)$value;
            } 
            // Special handling for supportCmds array
            else if ($key === 'supportCmds') {
                $current_value = json_decode($current[$key], true);
                $new_value = json_decode($value, true);
                if ($current_value === null) $current_value = [];
                if ($new_value === null) $new_value = [];
                
                // Compare arrays
                if (count($current_value) !== count($new_value) || 
                    count(array_diff($current_value, $new_value)) > 0 ||
                    count(array_diff($new_value, $current_value)) > 0) {
                    $changes[] = "$key changed from [" . implode(',', $current_value) . "] to [" . implode(',', $new_value) . "]";
                    $updates[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
                continue;
            }
            // Numeric values
            else if (is_numeric($value)) {
                $current_value = (float)$current[$key];
                $new_value = (float)$value;
            }
            // String values
            else {
                $current_value = (string)$current[$key];
                $new_value = (string)$value;
            }
            
            if ($current_value !== $new_value) {
                $changes[] = "$key changed from $current_value to $new_value";
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (!empty($changes)) {
            $log->logInfoMsg("Changes detected for device {$device['device']}: " . implode(", ", $changes));
            
            if (!empty($updates)) {
                $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = :device";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        } else {
            $log->logInfoMsg("No changes detected for device {$device['device']}");
        }
        return false;  // Return false for existing devices
    }
}

function updateDeviceStateInDatabase($pdo, $device, $device_states, $govee_device) {
    global $log;
    
    // First get current state from database
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device['device']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Define new values
    $new_values = [
        'online' => $govee_device ? ($govee_device['deviceName'] === $device['device_name']) : false,
        'model' => $govee_device ? $govee_device['model'] : $device['model'],
        'powerState' => null,
        'brightness' => null,
        'colorTemInKelvin' => null,
        'colorTem' => null
    ];
    
    // Update values from device states if available
    if (isset($device_states[$device['device']])) {
        foreach ($device_states[$device['device']] as $property) {
            foreach ($new_values as $key => $value) {
                if (isset($property[$key])) {
                    $new_values[$key] = $property[$key];
                }
            }
        }
    }
    
    // Compare and log changes
    $changes = [];
    $updates = [];
    $params = [':device' => $device['device']];
    
    foreach ($new_values as $key => $value) {
        // Skip null values unless they're different from current
        if ($value === null && (!isset($current[$key]) || $current[$key] === null)) {
            continue;
        }
        
        // Convert boolean online status for comparison
        if ($key === 'online') {
            $current_value = (bool)$current[$key];
            $new_value = (bool)$value;
        } else {
            $current_value = $current[$key];
            $new_value = $value;
        }
        
        if ($current_value !== $new_value) {
            $changes[] = "$key changed from " . 
                        (is_bool($current_value) ? ($current_value ? 'true' : 'false') : $current_value) . 
                        " to " . 
                        (is_bool($new_value) ? ($new_value ? 'true' : 'false') : $new_value);
            
            $updates[] = "$key = :$key";
            $params[":$key"] = $key === 'online' ? ($value ? 1 : 0) : $value;
        }
    }
    
    // Only update if there are changes
    if (!empty($changes)) {
        $log->logInfoMsg("Changes detected for device {$device['device']}: " . implode(", ", $changes));
        
        if (!empty($updates)) {
            $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = :device";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    } else {
        $log->logInfoMsg("No changes detected for device {$device['device']}");
    }
    
    // Update the device array for response
    foreach ($new_values as $key => $value) {
        if ($value !== null) {
            $device[$key] = $value;
        }
    }
    
    return $device;
}

function trackApiHeaders($headers) {
    global $log, $rateLimitsHistory;
    static $lastKnownValues = [
        'Date' => null,
        'API-RateLimit-Remaining' => null,
        'API-RateLimit-Reset' => null,
        'API-RateLimit-Limit' => null,
        'X-RateLimit-Limit' => null,
        'X-RateLimit-Remaining' => null,
        'X-RateLimit-Reset' => null,
        'X-Response-Time' => null
    ];

    // Only process if headers is not empty
    if (!empty($headers)) {
        foreach (explode("\n", $headers) as $header) {
            if (empty(trim($header))) continue;
            
            $parts = explode(':', $header, 2);
            if (count($parts) !== 2) continue;
            
            $name = trim($parts[0]);
            $value = trim($parts[1]);

            switch (strtolower($name)) {
                case 'date':
                    $timestamp = strtotime($value);
                    if ($timestamp !== false) {
                        $lastKnownValues['Date'] = date('Y-m-d H:i:s', $timestamp);
                    }
                    break;
                case 'api-ratelimit-remaining':
                    if (is_numeric($value)) {
                        $lastKnownValues['API-RateLimit-Remaining'] = (int)$value;
                    }
                    break;
                case 'api-ratelimit-reset':
                    if (is_numeric($value)) {
                        $lastKnownValues['API-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    }
                    break;
                case 'api-ratelimit-limit':
                    if (is_numeric($value)) {
                        $lastKnownValues['API-RateLimit-Limit'] = (int)$value;
                    }
                    break;
                case 'x-ratelimit-limit':
                    if (is_numeric($value)) {
                        $lastKnownValues['X-RateLimit-Limit'] = (int)$value;
                    }
                    break;
                case 'x-ratelimit-remaining':
                    if (is_numeric($value)) {
                        $lastKnownValues['X-RateLimit-Remaining'] = (int)$value;
                    }
                    break;
                case 'x-ratelimit-reset':
                    if (is_numeric($value)) {
                        $lastKnownValues['X-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    }
                    break;
                case 'x-response-time':
                    $lastKnownValues['X-Response-Time'] = trim(str_replace('ms', '', $value));
                    break;
            }
        }
    }

    $log->logInfoMsg("Updated header values: " . json_encode($lastKnownValues));
    return $lastKnownValues;
}

function logApiCall($pdo, $headerValues) {
    global $log;
    try {
        $log->logInfoMsg("Writing final API call data: " . json_encode($headerValues));

        $sql = "INSERT INTO govee_api_calls (
                    `Date`,
                    `API-RateLimit-Remaining`,
                    `API-RateLimit-Reset`,
                    `API-RateLimit-Limit`,
                    `X-RateLimit-Limit`,
                    `X-RateLimit-Remaining`,
                    `X-RateLimit-Reset`,
                    `X-Response-Time`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            $headerValues['Date'],
            $headerValues['API-RateLimit-Remaining'],
            $headerValues['API-RateLimit-Reset'],
            $headerValues['API-RateLimit-Limit'],
            $headerValues['X-RateLimit-Limit'],
            $headerValues['X-RateLimit-Remaining'],
            $headerValues['X-RateLimit-Reset'],
            $headerValues['X-Response-Time']
        ]);
        
        if ($result) {
            $log->logInfoMsg("Successfully inserted API call data");
        } else {
            $log->logErrorMsg("Failed to insert API call data. PDO error info: " . json_encode($stmt->errorInfo()));
        }
        
    } catch (Exception $e) {
        $log->logErrorMsg('Failed to log API call: ' . $e->getMessage());
    }
}

// Main execution
try {
    $single_device = isset($_GET['device']) ? $_GET['device'] : null;
    $quick = isset($_GET['quick']) && $_GET['quick'] == '1';
    $room = isset($_GET['room']) ? $_GET['room'] : null;
    $exclude_room = isset($_GET['exclude_room']) ? $_GET['exclude_room'] : null;
    $timing = array();
    $start = microtime(true);

    // Get database connection
    $db_start = microtime(true);
    $pdo = getDatabaseConnection($config);
    $devices = getDevicesFromDatabase($pdo, $single_device, $room, $exclude_room);
    $timing['database'] = array('duration' => round((microtime(true) - $db_start) * 1000));

    
    if (!$quick) {
        // Get devices from Govee API
        $api_start = microtime(true);
        $goveeResponse = getGoveeDevices($config);
        
        if ($goveeResponse['statusCode'] !== 200) {
            throw new Exception('Failed to get devices from Govee API');
        }
        
        $headerValues = trackApiHeaders($goveeResponse['headers']);
        $result = json_decode($goveeResponse['body'], true);
        
        $new_devices = [];  // Track new devices
        
        if ($result['code'] === 200) {
            $device_count = 0;
            foreach ($result['data']['devices'] as $device) {
                $device_count++;
                $is_new = updateDeviceDatabase($pdo, $device);
                if ($is_new) {
                    $new_devices[] = $device;
                }
            }
        } else {
            throw new Exception($result['message']);
        }
        
        $timing['devices'] = array('duration' => round((microtime(true) - $api_start) * 1000));
        
        // Immediately get states for new devices
        if (!empty($new_devices)) {
            foreach ($new_devices as $device) {
                $stateResponse = getDeviceState($device, $config);
                $headerValues = trackApiHeaders($goveeResponse['headers']);
                if ($stateResponse['statusCode'] === 200) {
                    $state_result = json_decode($stateResponse['body'], true);
                    if ($state_result['code'] === 200) {
                        $device_states[$device['device']] = $state_result['data']['properties'];
                        updateDeviceStateInDatabase($pdo, $device, $device_states, $device);
                    }
                }
            }
        }
        
        // Get device states
        $states_start = microtime(true);
        $govee_devices = $result['data']['devices'];
        $device_states = array();
        
        foreach ($govee_devices as $device) {
            if ($single_device && $device['device'] !== $single_device) {
                continue;
            }
            
            // Skip devices not in the requested room or in excluded room
            $deviceInList = false;
            foreach ($devices as $dbDevice) {
                if ($dbDevice['device'] === $device['device']) {
                    $deviceInList = true;
                    break;
                }
            }
            if (!$deviceInList) {
                continue;
            }
            
            $stateResponse = getDeviceState($device, $config);
            $headerValues = trackApiHeaders($goveeResponse['headers']);
            if ($stateResponse['statusCode'] === 200) {
                $state_result = json_decode($stateResponse['body'], true);
                if ($state_result['code'] === 200) {
                    $device_states[$device['device']] = $state_result['data']['properties'];
                    $device = updateDeviceStateInDatabase($pdo, $device, $device_states, $govee_device);
                }
            }
        }
        
        $timing['states'] = array('duration' => round((microtime(true) - $states_start) * 1000));

        // Use the lowest remaining values from all API calls
        foreach ($rateLimitsHistory as $limits) {
            if ($rateLimits['apiRemaining'] === null || 
                ($limits['apiRemaining'] !== null && $limits['apiRemaining'] < $rateLimits['apiRemaining'])) {
                $rateLimits['apiRemaining'] = $limits['apiRemaining'];
            }
            if ($rateLimits['xRemaining'] === null || 
                ($limits['xRemaining'] !== null && $limits['xRemaining'] < $rateLimits['xRemaining'])) {
                $rateLimits['xRemaining'] = $limits['xRemaining'];
            }
        }
        
        // Update devices with state information
        foreach ($devices as &$device) {
            $govee_device = null;
            foreach ($govee_devices as $gd) {
                if ($gd['device'] === $device['device']) {
                    $govee_device = $gd;
                    break;
                }
            }
            
            if ($govee_device) {
                $device['online'] = $govee_device['deviceName'] === $device['device_name'];
                $device['model'] = $govee_device['model'];
                
                if (isset($device_states[$device['device']])) {
                    foreach ($device_states[$device['device']] as $property) {
                        if ($property['powerState']) {
                            $device['powerState'] = $property['powerState'];
                        }
                    }
                }
            } else {
                $device['online'] = false;
            }
        }
    
        if ($headerValues) {
            logApiCall($pdo, $headerValues);
        }
    }
    
    $timing['total'] = round((microtime(true) - $start) * 1000);
    $rateLimits = [
        'apiRemaining' => $headerValues['API-RateLimit-Remaining'],
        'xRemaining' => $headerValues['X-RateLimit-Remaining']
    ];
    
    echo json_encode([
        'success' => true,
        'devices' => $devices,
        'updated' => date('c'),
        'quick' => $quick,
        'timing' => $quick ? null : $timing,
        'rateLimits' => $quick ? null : $rateLimits
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}