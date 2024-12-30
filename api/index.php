<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

// Add your existing functions
function getDatabaseConnection($config) {
    return new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
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

function getDevicesFromDatabase($pdo, $single_device = null, $room = null, $exclude_room = null) {
    global $log;
    $log->logInfoMsg("Getting devices from the database.");
    
    try {
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
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }
        
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($devices as &$device) {
            if (!empty($device['device']) && !empty($device['deviceGroup'])) {
                $groupStmt = $pdo->prepare(
                    "SELECT devices.device, devices.device_name, 
                            devices.powerState, devices.online, 
                            devices.brightness, devices.brand
                     FROM devices 
                     WHERE devices.deviceGroup = ? AND devices.device != ?
                     ORDER BY devices.device_name"
                );
                $groupStmt->execute([$device['deviceGroup'], $device['device']]);
                $device['group_members'] = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
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

function updateDeviceDatabase($pdo, $device) {
    global $log;
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device['device']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_values = [
        'device' => $device['device'],
        'model' => $device['model'],
        'device_name' => $device['deviceName'],
        'controllable' => $device['controllable'] ? 1 : 0,
        'retrievable' => $device['retrievable'] ? 1 : 0,
        'supportCmds' => json_encode($device['supportCmds']),
        'colorTemp_rangeMin' => null,
        'colorTemp_rangeMax' => null
    ];
    
    if (in_array('colorTem', $device['supportCmds']) && 
        isset($device['properties']) && 
        isset($device['properties']['colorTem']) && 
        isset($device['properties']['colorTem']['range'])) {
        
        $new_values['colorTemp_rangeMin'] = $device['properties']['colorTem']['range']['min'];
        $new_values['colorTemp_rangeMax'] = $device['properties']['colorTem']['range']['max'];
    }
    
    if (!$current) {
        $log->logInfoMsg("New device detected: {$device['device']}");
        $updates = [];
        $params = [];
        foreach ($new_values as $key => $value) {
            if ($value !== null) {
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (!empty($updates)) {
            $sql = "INSERT INTO devices SET " . implode(", ", $updates);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        return true;
    }
    
    $changes = [];
    $updates = [];
    $params = [':device' => $device['device']];
    
    foreach ($new_values as $key => $value) {
        if ($value === null || !isset($current[$key])) {
            continue;
        }
        
        if ($key === 'supportCmds') {
            $current_value = json_decode($current[$key], true);
            $new_value = json_decode($value, true);
            if ($current_value === null) $current_value = [];
            if ($new_value === null) $new_value = [];
            
            if (count($current_value) !== count($new_value) || 
                count(array_diff($current_value, $new_value)) > 0 ||
                count(array_diff($new_value, $current_value)) > 0) {
                $changes[] = "$key changed";
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
            continue;
        }
        
        $current_value = $current[$key];
        if ($current_value != $value) {
            $changes[] = "$key changed from $current_value to $value";
            $updates[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = :device";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $log->logInfoMsg("Updated device {$device['device']}: " . implode(", ", $changes));
    }
    
    return false;
}

function updateDeviceStateInDatabase($pdo, $device, $device_states, $govee_device) {
    global $log;
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device['device']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_values = [
        'online' => $govee_device ? ($govee_device['deviceName'] === $device['device_name']) : false,
        'model' => $govee_device ? $govee_device['model'] : $device['model'],
        'powerState' => null,
        'brightness' => null,
        'colorTemp' => null
    ];
    
    if (isset($device_states[$device['device']])) {
        foreach ($device_states[$device['device']] as $property) {
            foreach ($new_values as $key => $value) {
                if (isset($property[$key])) {
                    $new_values[$key] = $property[$key];
                }
            }
        }
    }
    
    $changes = [];
    $updates = [];
    $params = [':device' => $device['device']];
    
    foreach ($new_values as $key => $value) {
        if ($value === null && (!isset($current[$key]) || $current[$key] === null)) {
            continue;
        }
        
        if ($key === 'online') {
            $current_value = (bool)$current[$key];
            $new_value = (bool)$value;
        } else {
            $current_value = $current[$key];
            $new_value = $value;
        }
        
        if ($current_value !== $new_value) {
            $changes[] = "$key changed";
            $updates[] = "$key = :$key";
            $params[":$key"] = $key === 'online' ? ($value ? 1 : 0) : $value;
        }
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = :device";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $log->logInfoMsg("Updated device state {$device['device']}: " . implode(", ", $changes));
    }
    
    foreach ($new_values as $key => $value) {
        if ($value !== null) {
            $device[$key] = $value;
        }
    }
    
    return $device;
}

// Create Slim app
$app = AppFactory::create();

// Add base path
$app->setBasePath('/homeio/api');

// Add routing middleware
$app->addRoutingMiddleware();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// Initialize logger
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

// X10 code check route
$app->get('/check-x10-code', function (Request $request, Response $response) use ($config, $log) {
    try {
        $queryParams = $request->getQueryParams();
        
        if (!isset($queryParams['x10Code'])) {
            throw new Exception('X10 code is required');
        }
        
        $x10Code = $queryParams['x10Code'];
        $currentDevice = $queryParams['currentDevice'] ?? null;
        
        $pdo = getDatabaseConnection($config);
        
        // Check if X10 code exists for any other device
        $sql = "SELECT device, device_name FROM devices WHERE x10Code = ? AND device != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$x10Code, $currentDevice]);
        $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $payload = json_encode([
            'success' => true,
            'isDuplicate' => (bool)$existingDevice,
            'deviceName' => $existingDevice ? $existingDevice['device_name'] : null,
            'device' => $existingDevice ? $existingDevice['device'] : null
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
            
    } catch (Exception $e) {
        $log->logErrorMsg("Error checking X10 code: " . $e->getMessage());
        
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

// Delete device group route
$app->post('/delete-device-group', function (Request $request, Response $response) use ($config, $log) {
    try {
        // Get raw input and decode JSON
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['groupId'])) {
            throw new Exception('Group ID is required');
        }
        
        $pdo = getDatabaseConnection($config);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // First update all devices in the group to remove group association
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = NULL, showInGroupOnly = 0 WHERE deviceGroup = ?");
            $stmt->execute([$data['groupId']]);
            
            // Then delete the group
            $stmt = $pdo->prepare("DELETE FROM device_groups WHERE id = ?");
            $stmt->execute([$data['groupId']]);
            
            // Commit transaction
            $pdo->commit();
            
            $payload = json_encode([
                'success' => true,
                'message' => 'Group deleted successfully'
            ]);
            
            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
                
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $log->logErrorMsg("Error deleting device group: " . $e->getMessage());
        
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

// Get available device groups route
$app->get('/available-groups', function (Request $request, Response $response) use ($config, $log) {
    try {
        $queryParams = $request->getQueryParams();
        
        if (!isset($queryParams['model'])) {
            throw new Exception('Model parameter is required');
        }
        
        $pdo = getDatabaseConnection($config);
        
        // Get existing groups for this model
        $stmt = $pdo->prepare("SELECT id, name FROM device_groups WHERE model = ?");
        $stmt->execute([$queryParams['model']]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payload = json_encode([
            'success' => true,
            'groups' => $groups
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
            
    } catch (Exception $e) {
        $log->logErrorMsg("Error getting available groups: " . $e->getMessage());
        
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

// Get group devices route
$app->get('/group-devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $queryParams = $request->getQueryParams();
        
        if (!isset($queryParams['groupId'])) {
            throw new Exception('Group ID is required');
        }
        
        $pdo = getDatabaseConnection($config);
        
        // Get all devices in the group, including device_name
        $stmt = $pdo->prepare("SELECT device, device_name, powerState, online FROM devices WHERE deviceGroup = ?");
        $stmt->execute([$queryParams['groupId']]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payload = json_encode([
            'success' => true,
            'devices' => $devices
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
            
    } catch (Exception $e) {
        $log->logErrorMsg("Error getting group devices: " . $e->getMessage());
        
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/device-config', function (Request $request, Response $response) use ($config) {
    try {
        $queryParams = $request->getQueryParams();
        if (!isset($queryParams['device'])) {
            throw new Exception('Device ID is required');
        }
        
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("SELECT room, low, medium, high, preferredColorTem, x10Code FROM devices WHERE device = ?");
        $stmt->execute([$queryParams['device']]);
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception('Device not found');
        }
        
        $payload = json_encode([
            'success' => true,
            'room' => $config['room'],
            'low' => $config['low'],
            'medium' => $config['medium'],
            'high' => $config['high'],
            'preferredColorTem' => $config['preferredColorTem'],
            'x10Code' => $config['x10Code']
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
            
    } catch (Exception $e) {
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $start = microtime(true);
        $timing = array();
        
        $single_device = $request->getQueryParams()['device'] ?? null;
        $room = $request->getQueryParams()['room'] ?? null;
        $exclude_room = $request->getQueryParams()['exclude_room'] ?? null;
        $quick = isset($request->getQueryParams()['quick']) ? 
            ($request->getQueryParams()['quick'] === 'true') : false;

        $pdo = getDatabaseConnection($config);
        $devices_start = microtime(true);

        // Only update Govee devices during full refresh
        if (!$quick) {
            $log->logInfoMsg("Starting Govee devices update (quick = false)");
            try {
                $govee_start = microtime(true);
                
                // Make HTTP request to Govee update endpoint
                $curl = curl_init();
                $goveeUrl = "/api/update-govee-devices"; // Updated URL to use new Slim route
                $log->logInfoMsg("Calling Govee update URL: " . $goveeUrl);
                
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $goveeUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ));
                
                $goveeResponse = curl_exec($curl);
                $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                
                if (curl_errno($curl)) {
                    throw new Exception('Curl error: ' . curl_error($curl));
                }
                
                curl_close($curl);
                
                $log->logInfoMsg("Govee update response status: " . $statusCode);
                
                if ($statusCode !== 200) {
                    throw new Exception('Failed to update Govee devices: HTTP ' . $statusCode);
                }
                
                $goveeData = json_decode($goveeResponse, true);
                if (!$goveeData['success']) {
                    throw new Exception($goveeData['error'] ?? 'Unknown error updating Govee devices');
                }
                
                $log->logInfoMsg("Govee update completed successfully");
                $timing['govee'] = array('duration' => round((microtime(true) - $govee_start) * 1000));
                
            } catch (Exception $e) {
                $log->logErrorMsg("Govee update error: " . $e->getMessage());
                // Continue even if Govee update fails
            }
        }

        // Get devices from the database
        $devices = getDevicesFromDatabase($pdo, $single_device, $room, $exclude_room);
        $timing['devices'] = array('duration' => round((microtime(true) - $devices_start) * 1000));
        
        // Calculate database timing
        $timing['database'] = array('duration' => round((microtime(true) - $devices_start) * 1000));
        
        // Calculate total timing
        $timing['total'] = round((microtime(true) - $start) * 1000);

        $payload = json_encode([
            'success' => true,
            'devices' => $devices,
            'updated' => date('c'),
            'timing' => $timing,
            'quick' => $quick
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
            
    } catch (Exception $e) {
        $log->logErrorMsg("Error getting devices: " . $e->getMessage());
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/rooms', function (Request $request, Response $response) use ($config) {
    try {
        // Connect to database
        $pdo = getDatabaseConnection($config);
        
        // Get rooms
        $stmt = $pdo->query("SELECT id, room_name FROM rooms ORDER BY tab_order");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payload = json_encode([
            'success' => true,
            'rooms' => $rooms
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
        
    } catch (PDOException $e) {
        $payload = json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    } catch (Exception $e) {
        $payload = json_encode([
            'success' => false, 
            'error' => 'Server error: ' . $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/room-temperature', function (Request $request, Response $response) use ($config) {
    try {
        $queryParams = $request->getQueryParams();
        if (!isset($queryParams['room'])) {
            throw new Exception('Room ID is required');
        }
        
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("SELECT temp, humidity FROM thermometers WHERE room = ? ORDER BY updated DESC LIMIT 1");
        $stmt->execute([$queryParams['room']]);
        
        $tempData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tempData) {
            $payload = json_encode([
                'success' => true,
                'temperature' => $tempData['temp'],
                'humidity' => $tempData['humidity']
            ]);
        } else {
            throw new Exception('No temperature data found for this room');
        }
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
        
    } catch (Exception $e) {
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->post('/send-command', function (Request $request, Response $response) use ($config, $log) {
    try {
        // Get the request body
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['device']) || !isset($data['model']) || !isset($data['cmd']) || !isset($data['brand'])) {
            throw new Exception('Missing required parameters: device, model, cmd, or brand');
        }

        // Connect to database
        $pdo = getDatabaseConnection($config);

        // Insert into unified command queue
        $stmt = $pdo->prepare("
            INSERT INTO command_queue 
            (device, model, command, brand) 
            VALUES 
            (:device, :model, :command, :brand)
        ");

        $stmt->execute([
            'device' => $data['device'],
            'model' => $data['model'],
            'command' => json_encode($data['cmd']),
            'brand' => $data['brand']
        ]);

        $commandId = $pdo->lastInsertId();
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Command queued successfully',
            'command_id' => $commandId
        ]);

        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Exception $e) {
        $log->logInfoMsg("Error in send_command: " . $e->getMessage());
        
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->post('/update-device-config', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['device'])) {
            throw new Exception('Device ID is required');
        }
        
        $pdo = getDatabaseConnection($config);
        
        // Convert empty x10Code to NULL
        $x10Code = (!empty($data['x10Code'])) ? $data['x10Code'] : null;
        
        $stmt = $pdo->prepare("UPDATE devices SET room = ?, low = ?, medium = ?, high = ?, preferredColorTem = ?, x10Code = ? WHERE device = ?");
        $stmt->execute([
            $data['room'],
            $data['low'],
            $data['medium'],
            $data['high'],
            $data['preferredColorTem'],
            $x10Code,  // This will now be NULL if empty
            $data['device']
        ]);
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Device configuration updated successfully'
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
        
    } catch (Exception $e) {
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->post('/update-device-group', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['device']) || !isset($data['action'])) {
            throw new Exception('Missing required parameters');
        }
        
        $pdo = getDatabaseConnection($config);
        
        if ($data['action'] === 'create') {
            if (!isset($data['groupName']) || !isset($data['model'])) {
                throw new Exception('Group name and model required for new group');
            }
            
            // Create new group
            $stmt = $pdo->prepare("INSERT INTO device_groups (name, model, reference_device) VALUES (?, ?, ?)");
            $stmt->execute([$data['groupName'], $data['model'], $data['device']]);
            $groupId = $pdo->lastInsertId();
            
            // Add device to group
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = ?, showInGroupOnly = 0 WHERE device = ?");
            $stmt->execute([$groupId, $data['device']]);
            
        } else if ($data['action'] === 'join') {
            if (!isset($data['groupId'])) {
                throw new Exception('Group ID required to join existing group');
            }
            
            // Add device to existing group
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = ?, showInGroupOnly = 1 WHERE device = ?");
            $stmt->execute([$data['groupId'], $data['device']]);
            
        } else if ($data['action'] === 'leave') {
            // Remove device from group
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = NULL, showInGroupOnly = 0 WHERE device = ?");
            $stmt->execute([$data['device']]);
        }
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Device group updated successfully'
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
        
    } catch (Exception $e) {
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->post('/update-device-state', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['device']) || !isset($data['command']) || !isset($data['value'])) {
            throw new Exception('Missing required parameters');
        }
        
        $pdo = getDatabaseConnection($config);
        
        // Update the device state based on command type
        switch($data['command']) {
            case 'turn':
                $stmt = $pdo->prepare("UPDATE devices SET powerState = ? WHERE device = ?");
                $stmt->execute([$data['value'], $data['device']]);
                break;
                
            case 'brightness':
                $stmt = $pdo->prepare("UPDATE devices SET brightness = ?, powerState = 'on' WHERE device = ?");
                $stmt->execute([(int)$data['value'], $data['device']]);
                break;
        }
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Device state updated successfully'
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Exception $e) {
        $log->logInfoMsg("Error in update_device_state: " . $e->getMessage());
        
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/update-govee-devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $start = microtime(true);
        $timing = array();
        
        // Get database connection
        $pdo = getDatabaseConnection($config);
        
        // Get devices from Govee API
        $api_start = microtime(true);
        $goveeResponse = getGoveeDevices($config);
        
        if ($goveeResponse['statusCode'] !== 200) {
            throw new Exception('Failed to get devices from Govee API');
        }
        
        $result = json_decode($goveeResponse['body'], true);
        
        if ($result['code'] !== 200) {
            throw new Exception($result['message']);
        }
        
        $timing['devices'] = array('duration' => round((microtime(true) - $api_start) * 1000));
        
        // Update devices and their states
        $states_start = microtime(true);
        $govee_devices = $result['data']['devices'];
        $device_states = array();
        $updated_devices = array();
        
        foreach ($govee_devices as $device) {
            // Update device basic info
            $is_new = updateDeviceDatabase($pdo, $device);
            
            // Get and update device state
            $stateResponse = getDeviceState($device, $config);
            if ($stateResponse['statusCode'] === 200) {
                $state_result = json_decode($stateResponse['body'], true);
                if ($state_result['code'] === 200) {
                    $device_states[$device['device']] = $state_result['data']['properties'];
                    $updated_device = updateDeviceStateInDatabase($pdo, $device, $device_states, $device);
                    $updated_devices[] = $updated_device;
                }
            }
        }
        
        $timing['states'] = array('duration' => round((microtime(true) - $states_start) * 1000));
        $timing['total'] = round((microtime(true) - $start) * 1000);
        
        $payload = json_encode([
            'success' => true,
            'devices' => $updated_devices,
            'updated' => date('c'),
            'timing' => $timing
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
        
    } catch (Exception $e) {
        $log->logErrorMsg("Error updating Govee devices: " . $e->getMessage());
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/update-hue-devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $start = microtime(true);
        $timing = array();
        
        // Get database connection
        $pdo = getDatabaseConnection($config);
        
        // Get devices from Hue Bridge
        $api_start = microtime(true);
        $hueResponse = getHueDevices($config);
        
        if ($hueResponse['statusCode'] !== 200) {
            throw new Exception('Failed to get devices from Hue Bridge');
        }
        
        $devices = json_decode($hueResponse['body'], true);
        if (!$devices || !isset($devices['data'])) {
            throw new Exception('Failed to parse Hue Bridge response');
        }
        
        $timing['devices'] = array('duration' => round((microtime(true) - $api_start) * 1000));
        
        // Update devices and their states
        $states_start = microtime(true);
        $updated_devices = array();
        
        foreach ($devices['data'] as $device) {
            $updated_device = updateDeviceDatabase($pdo, $device);
            $updated_devices[] = $updated_device;
        }
        
        $timing['states'] = array('duration' => round((microtime(true) - $states_start) * 1000));
        $timing['total'] = round((microtime(true) - $start) * 1000);
        
        $payload = json_encode([
            'success' => true,
            'devices' => $updated_devices,
            'updated' => date('c'),
            'timing' => $timing
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
        
    } catch (Exception $e) {
        $log->logErrorMsg("Error updating Hue devices: " . $e->getMessage());
        $payload = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

$app->run();