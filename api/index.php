<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

// Load brand-specific route handlers
require __DIR__ . '/govee.php';
require __DIR__ . '/hue.php';
require __DIR__ . '/vesync.php';

function validateApiKey($request, $handler) {
    global $config;
    
    // Get API key from header
    $apiKey = $request->getHeaderLine('X-API-Key');
    
    // Check if API key exists and is valid
    if (empty($apiKey) || !in_array($apiKey, $config['api_keys'])) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Invalid or missing API key'
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
    
    return $handler->handle($request);
}

// Helper Functions
function sendErrorResponse($response, $error, $log = null) {
    if ($log) {
        $log->logErrorMsg($error->getMessage());
    }
    
    $payload = json_encode([
        'success' => false,
        'error' => $error->getMessage()
    ]);
    
    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
}

function sendSuccessResponse($response, $data, $status = 200) {
    $payload = json_encode(array_merge(
        ['success' => true],
        $data
    ));
    
    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

function measureExecutionTime($callback) {
    $start = microtime(true);
    $result = $callback();
    $duration = round((microtime(true) - $start) * 1000);
    
    return [
        'result' => $result,
        'duration' => $duration
    ];
}

function validateRequiredParams($params, $required) {
    $missing = array_filter($required, function($param) use ($params) {
        return !isset($params[$param]);
    });
    
    if (!empty($missing)) {
        throw new Exception('Missing required parameters: ' . implode(', ', $missing));
    }
    
    return true;
}

function getDatabaseConnection($config) {
    return new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function hasDevicePendingCommand($pdo, $device) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM command_queue 
        WHERE device = ? 
        AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$device]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['count'] > 0);
}


function getGroupDevices($pdo, $groupId) {
    $stmt = $pdo->prepare("SELECT devices FROM device_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? json_decode($result['devices'], true) : [];
}

// Create Slim app and configure middleware
$app = AppFactory::create();
$app->setBasePath('/homeio/api');
$app->addRoutingMiddleware();
//$app->add('validateApiKey');
$app->addErrorMiddleware(true, true, true);

// Initialize logger
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

// Initialize route handlers
$goveeRoutes = new GoveeRoutes($app, $config, $log);
$goveeRoutes->register();

$hueRoutes = new HueRoutes($app, $config, $log);
$hueRoutes->register();

$vesyncRoutes = new VeSyncRoutes($app, $config, $log);
$vesyncRoutes->register();

// Common API Routes
$app->get('/check-x10-code', function (Request $request, Response $response) use ($config, $log) {
    try {
        validateRequiredParams($request->getQueryParams(), ['x10Code']);
        $x10Code = $request->getQueryParams()['x10Code'];
        $currentDevice = $request->getQueryParams()['currentDevice'] ?? null;
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("SELECT device, device_name FROM devices WHERE x10Code = ? AND device != ?");
        $stmt->execute([$x10Code, $currentDevice]);
        $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return sendSuccessResponse($response, [
            'isDuplicate' => (bool)$existingDevice,
            'deviceName' => $existingDevice ? $existingDevice['device_name'] : null,
            'device' => $existingDevice ? $existingDevice['device'] : null
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->post('/delete-device-group', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['groupId']);
        
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("DELETE FROM device_groups WHERE id = ?");
        $stmt->execute([$data['groupId']]);
        
        return sendSuccessResponse($response, ['message' => 'Group deleted successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->get('/available-groups', function (Request $request, Response $response) use ($config, $log) {
    try {
        validateRequiredParams($request->getQueryParams(), ['model']);
        $model = $request->getQueryParams()['model'];
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("SELECT id, name FROM device_groups WHERE model = ?");
        $stmt->execute([$model]);
        
        return sendSuccessResponse($response, ['groups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->get('/group-devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        validateRequiredParams($request->getQueryParams(), ['groupId']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("SELECT device, device_name, powerState, online FROM devices WHERE deviceGroup = ?");
        $stmt->execute([$request->getQueryParams()['groupId']]);
        
        return sendSuccessResponse($response, ['devices' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->post('/update-device-group', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device', 'action']);
        
        $pdo = getDatabaseConnection($config);
        
        if ($data['action'] === 'create') {
            validateRequiredParams($data, ['groupName', 'model']);
            
            // Create new group with initial device
            $stmt = $pdo->prepare("INSERT INTO device_groups (name, model, devices) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['groupName'], 
                $data['model'],
                json_encode([$data['device']])
            ]);
            
        } else if ($data['action'] === 'join') {
            validateRequiredParams($data, ['groupId']);
            
            // Get current devices in group
            $stmt = $pdo->prepare("SELECT devices FROM device_groups WHERE id = ?");
            $stmt->execute([$data['groupId']]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$group) {
                throw new Exception('Group not found');
            }
            
            // Add device to group's device list
            $devices = json_decode($group['devices'], true) ?? [];
            if (!in_array($data['device'], $devices)) {
                $devices[] = $data['device'];
            }
            
            // Update group with new device list
            $stmt = $pdo->prepare("UPDATE device_groups SET devices = ? WHERE id = ?");
            $stmt->execute([json_encode($devices), $data['groupId']]);
            
        } else if ($data['action'] === 'leave') {
            // Find all groups containing this device
            $stmt = $pdo->prepare("SELECT id, devices FROM device_groups");
            $stmt->execute();
            
            while ($group = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $devices = json_decode($group['devices'], true) ?? [];
                
                // Remove device from group if present
                if (($key = array_search($data['device'], $devices)) !== false) {
                    unset($devices[$key]);
                    $devices = array_values($devices); // Reindex array
                    
                    // Update group with new device list
                    $updateStmt = $pdo->prepare("UPDATE device_groups SET devices = ? WHERE id = ?");
                    $updateStmt->execute([json_encode($devices), $group['id']]);
                    
                    // If group is empty, delete it
                    if (empty($devices)) {
                        $deleteStmt = $pdo->prepare("DELETE FROM device_groups WHERE id = ?");
                        $deleteStmt->execute([$group['id']]);
                    }
                }
            }
        }
        
        return sendSuccessResponse($response, ['message' => 'Device group updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

// Room routes
$app->get('/rooms', function (Request $request, Response $response) use ($config) {
    try {
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->query("SELECT id, room_name, tab_order, icon FROM rooms ORDER BY tab_order");
        
        return sendSuccessResponse($response, ['rooms' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-room', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['id', 'room_name', 'icon', 'tab_order']);
        
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("
            UPDATE rooms 
            SET room_name = ?,
                icon = ?,
                tab_order = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['room_name'],
            $data['icon'],
            $data['tab_order'],
            $data['id']
        ]);
        
        return sendSuccessResponse($response, ['message' => 'Room updated successfully']);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/add-room', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['room_name', 'icon', 'tab_order']);
        
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("
            INSERT INTO rooms (room_name, icon, tab_order)
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['room_name'],
            $data['icon'],
            $data['tab_order']
        ]);
        
        return sendSuccessResponse($response, [
            'message' => 'Room added successfully',
            'room_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->delete('/delete-room', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['id']);
        
        $pdo = getDatabaseConnection($config);
        
        // First check if room has any devices
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices WHERE room = ?");
        $stmt->execute([$data['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception('Cannot delete room with assigned devices');
        }
        
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ? AND id != 1");
        $stmt->execute([$data['id']]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Room not found or cannot be deleted');
        }
        
        return sendSuccessResponse($response, ['message' => 'Room deleted successfully']);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

// Device configuration and state routes
$app->get('/device-config', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['device']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("SELECT room, low, medium, high, preferredColorTem, x10Code, favorite, show_in_room FROM devices WHERE device = ?");
        $stmt->execute([$request->getQueryParams()['device']]);
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) {
            throw new Exception('Device not found');
        }
        
        return sendSuccessResponse($response, $config);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-config', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device']);
        
        $pdo = getDatabaseConnection($config);
        $x10Code = (!empty($data['x10Code'])) ? $data['x10Code'] : null;
        $favorite = isset($data['favorite']) ? ($data['favorite'] ? 1 : 0) : 0;
        $show_in_room = isset($data['show_in_room']) ? ($data['show_in_room'] ? 1 : 0) : 1; // Default to show
        
        $stmt = $pdo->prepare("UPDATE devices SET room = ?, low = ?, medium = ?, high = ?, preferredColorTem = ?, x10Code = ?, favorite = ?, show_in_room = ? WHERE device = ?");
        $stmt->execute([
            $data['room'],
            $data['low'],
            $data['medium'],
            $data['high'],
            $data['preferredColorTem'],
            $x10Code,
            $favorite,
            $show_in_room,
            $data['device']
        ]);
        
        return sendSuccessResponse($response, ['message' => 'Device configuration updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-state', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device', 'command', 'value']);
        
        $pdo = getDatabaseConnection($config);
        
        // Check if device is part of a group
        $stmt = $pdo->prepare("SELECT deviceGroup FROM devices WHERE device = ?");
        $stmt->execute([$data['device']]);
        $deviceGroup = $stmt->fetchColumn();
        
        $devicesToUpdate = [];
        if ($deviceGroup) {
            // If device is part of a group, get all devices in the group
            $devicesToUpdate = getGroupDevices($pdo, $deviceGroup);
        } else {
            // Otherwise just update the single device
            $devicesToUpdate = [$data['device']];
        }
        
        foreach ($devicesToUpdate as $deviceId) {
            // Get current device state
            $stmt = $pdo->prepare("SELECT preferredPowerState, preferredBrightness FROM devices WHERE device = ?");
            $stmt->execute([$deviceId]);
            $currentState = $stmt->fetch(PDO::FETCH_ASSOC);
            
            switch($data['command']) {
                case 'brightness':
                    $brightness = (int)$data['value'];
                    $stmt = $pdo->prepare("UPDATE devices SET preferredBrightness = ?, preferredPowerState = 'on' WHERE device = ?");
                    $stmt->execute([$brightness, $deviceId]);
                    
                    // Always queue the command - remove state checking
                    $shouldQueueCommand = true;
                    break;
                
                case 'turn':
                    $stmt = $pdo->prepare("UPDATE devices SET preferredPowerState = ? WHERE device = ?");
                    $stmt->execute([$data['value'], $deviceId]);
                    
                    // Always queue the command - remove state checking
                    $shouldQueueCommand = true;
                    break;
                
                default:
                    throw new Exception('Invalid command type');
            }
            
            if ($shouldQueueCommand) {
                $stmt = $pdo->prepare("
                    INSERT INTO command_queue 
                    (device, model, command, brand) 
                    SELECT device, model, :command, brand
                    FROM devices
                    WHERE device = :device
                ");
                
                $stmt->execute([
                    'command' => json_encode([
                        'name' => $data['command'],
                        'value' => $data['value']
                    ]),
                    'device' => $deviceId
                ]);
            }
        }
        
        return sendSuccessResponse($response, ['message' => 'Device state preferences updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->post('/queue-command', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['type', 'id', 'command', 'value']);
        
        $pdo = getDatabaseConnection($config);
        
        $devicesToUpdate = [];
        switch($data['type']) {
            case 'group':
                $devicesToUpdate = getGroupDevices($pdo, $data['id']);
                break;
            case 'device':    
                $devicesToUpdate = [$data['id']];
                break;
            default:
                    throw new Exception('Invalid device type');
        }
        
        foreach ($devicesToUpdate as $deviceId) {
        
            switch($data['command']) {
                
                case 'brightness':
                    $brightness = (int)$data['value'];
                    $stmt = $pdo->prepare("UPDATE devices SET preferredBrightness = ?, preferredPowerState = 'on' WHERE device = ?");
                    $stmt->execute([$brightness, $deviceId]);
                    break;
                
                case 'turn':
                    $stmt = $pdo->prepare("UPDATE devices SET preferredPowerState = ? WHERE device = ?");
                    $stmt->execute([$data['value'], $deviceId]);
                    break;
                
                default:
                    throw new Exception('Invalid command type');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO command_queue 
                (device, model, command, brand) 
                SELECT device, model, :command, brand
                FROM devices
                WHERE device = :device
            ");
            
            $stmt->execute([
                'command' => json_encode([
                    'name' => $data['command'],
                    'value' => $data['value']
                ]),
                'device' => $deviceId
            ]);
            
        }
        
        
        return sendSuccessResponse($response, ['message' => 'Device command queued successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

// Command handling
$app->post('/send-command', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device', 'model', 'cmd', 'brand']);
        
        $pdo = getDatabaseConnection($config);
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

        return sendSuccessResponse($response, [
            'message' => 'Command queued successfully',
            'command_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

// Thermometer routes
$app->get('/room-temperature', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['room']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("
            SELECT 
                t.mac,
                t.name,
                t.display_name,
                th.temperature as temp,
                th.humidity,
                th.timestamp as updated
            FROM thermometers t
            LEFT JOIN thermometer_history th ON t.mac = th.mac
            INNER JOIN (
                SELECT mac, MAX(timestamp) as max_timestamp
                FROM thermometer_history
                GROUP BY mac
            ) latest ON th.mac = latest.mac AND th.timestamp = latest.max_timestamp
            WHERE t.room = ?
            ORDER BY t.display_name, t.name
        ");
        $stmt->execute([$request->getQueryParams()['room']]);
        
        return sendSuccessResponse($response, [
            'thermometers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/thermometer-list', function (Request $request, Response $response) use ($config) {
    try {
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("
            SELECT 
                t.mac,
                t.name,
                t.display_name,
                t.model,
                t.room as room_id,
                r.room_name,
                t.updated
            FROM thermometers t
            LEFT JOIN rooms r ON t.room = r.id
            ORDER BY t.name
        ");
        $stmt->execute();
        $thermometers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $roomStmt = $pdo->query("SELECT id, room_name FROM rooms WHERE id != 1 ORDER BY room_name");
        $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

        return sendSuccessResponse($response, [
            'thermometers' => $thermometers,
            'rooms' => $rooms
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-thermometer', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['mac']);
        
        $pdo = getDatabaseConnection($config);
        
        $stmt = $pdo->prepare("
            UPDATE thermometers 
            SET display_name = ?,
                room = ?
            WHERE mac = ?
        ");
        
        $stmt->execute([
            $data['display_name'] ?: null,
            $data['room'] ?: null,
            $data['mac']
        ]);
        
        return sendSuccessResponse($response, ['message' => 'Thermometer updated successfully']);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/thermometer-history', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['mac']);
        $mac = $request->getQueryParams()['mac'];
        $hours = isset($request->getQueryParams()['hours']) ? (int)$request->getQueryParams()['hours'] : 24;

        $pdo = getDatabaseConnection($config);
        
        // Get device name and room info
        $stmt = $pdo->prepare("
            SELECT name, display_name, room
            FROM thermometers 
            WHERE mac = ?
        ");
        $stmt->execute([$mac]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get the history
        $stmt = $pdo->prepare("
            SELECT 
                temperature,
                humidity,
                battery,
                rssi,
                DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i') as timestamp
            FROM thermometer_history 
            WHERE mac = ? 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp DESC
        ");
        $stmt->execute([$mac, $hours]);
        
        return sendSuccessResponse($response, [
            'history' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'device_name' => $device ? ($device['display_name'] ?: $device['name']) : 'Unknown Device'
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/all-thermometer-history', function (Request $request, Response $response) use ($config) {
    try {
        // Get hours parameter, default to 24 if not specified
        $hours = isset($request->getQueryParams()['hours']) ? (int)$request->getQueryParams()['hours'] : 24;
        
        // Validate hours parameter
        if (!in_array($hours, [24, 168, 720])) {
            throw new Exception('Invalid hours parameter');
        }

        $pdo = getDatabaseConnection($config);
        
        // Get history for all thermometers with device names
        $stmt = $pdo->prepare("
            SELECT 
                th.temperature,
                th.humidity,
                th.battery,
                th.rssi,
                DATE_FORMAT(th.timestamp, '%Y-%m-%d %H:%i') as timestamp,
                COALESCE(t.display_name, t.name, d.device_name, th.mac) as device_name,
                th.mac
            FROM thermometer_history th
            LEFT JOIN thermometers t ON th.mac = t.mac
            LEFT JOIN devices d ON th.mac = d.device
            WHERE th.timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY th.timestamp ASC
        ");
        
        $stmt->execute([$hours]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format data for response
        $formattedHistory = array_map(function($record) {
            return [
                'device_name' => $record['device_name'],
                'mac' => $record['mac'],
                'temperature' => floatval($record['temperature']),
                'humidity' => floatval($record['humidity']),
                'battery' => intval($record['battery']),
                'rssi' => intval($record['rssi']),
                'timestamp' => $record['timestamp']
            ];
        }, $history);

        return sendSuccessResponse($response, [
            'history' => $formattedHistory
        ]);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/all-devices', function (Request $request, Response $response) use ($config) {
    try {
        $pdo = getDatabaseConnection($config);
        
        // Get all devices
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   r.room_name,
                   g.name as group_name,
                   g.devices as group_devices,
                   g.id as group_id
            FROM devices d
            LEFT JOIN rooms r ON d.room = r.id
            LEFT JOIN device_groups g ON JSON_CONTAINS(g.devices, JSON_QUOTE(d.device))
            ORDER BY d.brand, d.model, d.device_name
        ");
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all groups
        $stmt = $pdo->prepare("
            SELECT g.*, r.room_name 
            FROM device_groups g
            LEFT JOIN rooms r ON g.room = r.id
            ORDER BY g.name
        ");
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get room list for dropdown
        $roomStmt = $pdo->query("SELECT id, room_name FROM rooms ORDER BY room_name");
        $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

        return sendSuccessResponse($response, [
            'devices' => $devices,
            'groups' => $groups,  // Add groups to response
            'rooms' => $rooms
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-details', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device']);
        
        $pdo = getDatabaseConnection($config);
        
        // Build update query dynamically based on provided fields
        $updates = [];
        $params = [];

        // Map fields to database columns
        $fieldMappings = [
            'x10Code' => 'x10Code',
            'preferredName' => 'preferredName',
            'room' => 'room',
            'low' => 'low',
            'medium' => 'medium', 
            'high' => 'high',
            'preferredPowerState' => 'preferredPowerState',
            'preferredBrightness' => 'preferredBrightness',
            'preferredColorTem' => 'preferredColorTem',
            'deviceGroup' => 'deviceGroup'
        ];

        foreach ($fieldMappings as $requestField => $dbField) {
            if (isset($data[$requestField])) {
                // Handle empty string values
                if ($data[$requestField] === '') {
                    $updates[] = "$dbField = NULL";
                } else {
                    $updates[] = "$dbField = :$dbField";
                    $params[":$dbField"] = $data[$requestField];
                }
            }
        }

        // Add device parameter
        $params[':device'] = $data['device'];
        
        if (!empty($updates)) {
            $sql = "UPDATE devices SET " . implode(', ', $updates) . " WHERE device = :device";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        return sendSuccessResponse($response, ['message' => 'Device updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->run();