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
$app->add('validateApiKey'); // commant out to disable API key requirement (degugging only)
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

$app->get('/group-devices', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['groupId']);
        $groupId = $request->getQueryParams()['groupId'];
        
        $pdo = getDatabaseConnection($config);
        
        // First get the devices list from device_groups
        $stmt = $pdo->prepare("SELECT devices FROM device_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            throw new Exception('Group not found');
        }
        
        // Parse the JSON devices array
        $deviceIds = json_decode($group['devices'], true);
        if (!$deviceIds) {
            return sendSuccessResponse($response, ['devices' => []]);
        }
        
        // Now get the device details for each device in the group
        $placeholders = str_repeat('?,', count($deviceIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT device, device_name, powerState, online 
            FROM devices 
            WHERE device IN ($placeholders)
        ");
        $stmt->execute($deviceIds);
        
        return sendSuccessResponse($response, ['devices' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-group', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $pdo = getDatabaseConnection($config);
        
        // Handle updates vs creates
        if (isset($data['id'])) {
            // Update existing group
            validateRequiredParams($data, ['name', 'model']);
            
            // Update group data
            $stmt = $pdo->prepare("
                UPDATE device_groups 
                SET name = ?,
                    model = ?,
                    devices = ?,
                    rooms = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['model'],
                json_encode($data['devices'] ?? []),
                json_encode($data['rooms'] ?? []),  // Store rooms as JSON array
                $data['id']
            ]);
            
        } else {
            // Create new group
            validateRequiredParams($data, ['name', 'model']);
            
            $stmt = $pdo->prepare("
                INSERT INTO device_groups 
                (name, model, devices, rooms) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['model'], 
                json_encode($data['devices'] ?? []),
                json_encode($data['rooms'] ?? [])  // Store rooms as JSON array
            ]);
            
            $groupId = $pdo->lastInsertId();
        }

        return sendSuccessResponse($response, ['message' => 'Group updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/rooms', function (Request $request, Response $response) use ($config) {
    try {
        // Add connection error handling
        try {
            $pdo = getDatabaseConnection($config);
        } catch (Exception $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }

        // Add query error handling
        try {
            $stmt = $pdo->query("SELECT id, room_name, tab_order, icon FROM rooms ORDER BY tab_order");
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch rooms: ' . $e->getMessage());
        }
        
        return sendSuccessResponse($response, ['rooms' => $rooms]);
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

$app->post('/delete-room', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['id']);
        
        $pdo = getDatabaseConnection($config);

        // First let's just try to delete the room without the device check for testing
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

$app->get('/device-config', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['device']);
        $deviceId = $request->getQueryParams()['device'];
        
        $pdo = getDatabaseConnection($config);
        
       $groupStmt = $pdo->prepare("
            SELECT dg.*, d.low, d.medium, d.high, d.preferredColorTem,
                   GROUP_CONCAT(DISTINCT r.room_name) as room_names,
                   GROUP_CONCAT(DISTINCT r.id) as room_ids
            FROM device_groups dg
            LEFT JOIN (
                SELECT d.* 
                FROM devices d 
                INNER JOIN device_groups dg2 ON JSON_CONTAINS(dg2.devices, JSON_QUOTE(d.device))
                WHERE dg2.id = ?
                LIMIT 1
            ) d ON 1=1
            LEFT JOIN rooms r ON JSON_CONTAINS(r.devices, JSON_QUOTE(dg.id))
            WHERE dg.id = ?
            GROUP BY dg.id
        ");
        $groupStmt->execute([$deviceId, $deviceId]);
        $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($isGroup) {

            // Update settings for all devices in the group
            $deviceStmt = $pdo->prepare("
                UPDATE devices 
                SET low = ?,
                    medium = ?,
                    high = ?,
                    preferredColorTem = ?
                WHERE device IN (
                    SELECT d.device
                    FROM devices d
                    INNER JOIN device_groups dg ON JSON_CONTAINS(dg.devices, JSON_QUOTE(d.device))
                    WHERE dg.id = ?
                )
            ");
            $deviceStmt->execute([
                $data['low'],
                $data['medium'],
                $data['high'],
                $data['preferredColorTem'],
                $data['device']
            ]);
        
            // Handle room assignments for groups
            if (isset($data['rooms']) && is_array($data['rooms'])) {
                // First remove group from all rooms
                $stmt = $pdo->prepare("
                    UPDATE rooms 
                    SET devices = JSON_REMOVE(
                        devices, 
                        REPLACE(JSON_SEARCH(devices, 'one', ?), '\"', '')
                    )
                    WHERE JSON_CONTAINS(devices, ?)
                ");
                $stmt->execute([$data['device'], json_encode($data['device'])]);
        
                // Then add to selected rooms
                if (!empty($data['rooms'])) {
                    $stmt = $pdo->prepare("
                        UPDATE rooms 
                        SET devices = JSON_ARRAY_APPEND(
                            COALESCE(devices, JSON_ARRAY()),
                            '$',
                            ?
                        )
                        WHERE id IN (" . implode(',', array_fill(0, count($data['rooms']), '?')) . ")
                    ");
                    $params = [$data['device']];
                    $params = array_merge($params, $data['rooms']);
                    $stmt->execute($params);
                }
            }
        }
        
        // Get device configuration
        $stmt = $pdo->prepare("SELECT low, medium, high, preferredColorTem, show_in_room, preferredName FROM devices WHERE device = ?");
        $stmt->execute([$deviceId]);
        $deviceConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deviceConfig) {
            throw new Exception('Device not found');
        }

        // Get rooms this device belongs to
        $roomStmt = $pdo->prepare("SELECT id FROM rooms WHERE JSON_CONTAINS(devices, ?)");
        $roomStmt->execute([json_encode($deviceId)]);
        $rooms = $roomStmt->fetchAll(PDO::FETCH_COLUMN);

        return sendSuccessResponse($response, array_merge(
            $deviceConfig,
            ['rooms' => $rooms]
        ));
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-config', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device']);
        
        $pdo = getDatabaseConnection($config);

        // First check if this is a group ID
        $groupCheck = $pdo->prepare("SELECT id FROM device_groups WHERE id = ?");
        $groupCheck->execute([$data['device']]);
        $isGroup = $groupCheck->fetch(PDO::FETCH_ASSOC);

        if ($isGroup) {

            // Update settings for all devices in the group
            $deviceStmt = $pdo->prepare("
                UPDATE devices 
                SET low = ?,
                    medium = ?,
                    high = ?,
                    preferredColorTem = ?
                WHERE device IN (
                    SELECT d.device
                    FROM devices d
                    INNER JOIN device_groups dg ON JSON_CONTAINS(dg.devices, JSON_QUOTE(d.device))
                    WHERE dg.id = ?
                )
            ");
            $deviceStmt->execute([
                $data['low'],
                $data['medium'],
                $data['high'],
                $data['preferredColorTem'],
                $data['device']
            ]);
        } else {
            // Update device configuration
            $show_in_room = isset($data['show_in_room']) ? ($data['show_in_room'] ? 1 : 0) : 1;
            
            $stmt = $pdo->prepare("
                UPDATE devices 
                SET low = ?,
                    medium = ?,
                    high = ?,
                    preferredColorTem = ?,
                    show_in_room = ?,
                    preferredName = ? 
                WHERE device = ?
            ");
            $stmt->execute([
                $data['low'],
                $data['medium'],
                $data['high'],
                $data['preferredColorTem'],
                $show_in_room,
                $data['preferredName'] ?? null,  // Add the preferredName field
                $data['device']
            ]);

            // Update room assignments
            if (isset($data['rooms']) && is_array($data['rooms'])) {
                // First remove device from all rooms
                $stmt = $pdo->prepare("
                    UPDATE rooms 
                    SET devices = JSON_REMOVE(
                        devices, 
                        REPLACE(JSON_SEARCH(devices, 'one', ?), '\"', '')
                    )
                    WHERE JSON_CONTAINS(devices, ?)
                ");
                $stmt->execute([
                    $data['device'],
                    json_encode($data['device'])
                ]);

                // Then add to selected rooms
                $stmt = $pdo->prepare("
                    UPDATE rooms 
                    SET devices = JSON_ARRAY_APPEND(
                        COALESCE(devices, JSON_ARRAY()),
                        '$',
                        ?
                    )
                    WHERE id IN (" . implode(',', array_fill(0, count($data['rooms']), '?')) . ")
                ");
                $params = [$data['device']];
                $params = array_merge($params, $data['rooms']);
                $stmt->execute($params);
            }
        }
        
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
                t.temp,
                t.humidity,
                t.rssi,
                t.battery,
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
                   GROUP_CONCAT(DISTINCT r.room_name) as room_names,
                   GROUP_CONCAT(DISTINCT r.id) as room_ids,
                   g.name as group_name,
                   g.devices as group_devices,
                   g.id as group_id
            FROM devices d
            LEFT JOIN rooms r ON JSON_CONTAINS(r.devices, JSON_QUOTE(d.device))
            LEFT JOIN device_groups g ON JSON_CONTAINS(g.devices, JSON_QUOTE(d.device))
            GROUP BY d.device
            ORDER BY d.brand, d.model, d.device_name
        ");
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all groups
        $stmt = $pdo->prepare("
            SELECT g.*, 
                   GROUP_CONCAT(DISTINCT r.room_name) as room_names,
                   GROUP_CONCAT(DISTINCT r.id) as room_ids
            FROM device_groups g
            LEFT JOIN rooms r ON JSON_CONTAINS(r.devices, JSON_QUOTE(g.id))
            GROUP BY g.id
            ORDER BY g.name
        ");
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get room list for dropdown
        $roomStmt = $pdo->query("SELECT id, room_name FROM rooms ORDER BY room_name");
        $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

        return sendSuccessResponse($response, [
            'devices' => $devices,
            'groups' => $groups,
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

        // Map fields to database columns (removed 'room' from the mappings)
        $fieldMappings = [
            'preferredName' => 'preferredName',
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

        // Handle room assignments if provided
        if (isset($data['rooms']) && is_array($data['rooms'])) {
            // First remove device from all rooms
            $stmt = $pdo->prepare("
                UPDATE rooms 
                SET devices = JSON_REMOVE(
                    devices, 
                    REPLACE(JSON_SEARCH(devices, 'one', ?), '\"', '')
                )
                WHERE JSON_CONTAINS(devices, ?)
            ");
            $stmt->execute([$data['device'], json_encode($data['device'])]);

            // Then add to selected rooms
            if (!empty($data['rooms'])) {
                $stmt = $pdo->prepare("
                    UPDATE rooms 
                    SET devices = JSON_ARRAY_APPEND(
                        COALESCE(devices, JSON_ARRAY()),
                        '$',
                        ?
                    )
                    WHERE id IN (" . implode(',', array_fill(0, count($data['rooms']), '?')) . ")
                ");
                $params = [$data['device']];
                $params = array_merge($params, $data['rooms']);
                $stmt->execute($params);
            }
        }
        
        return sendSuccessResponse($response, ['message' => 'Device updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/remote-mappings', function (Request $request, Response $response) use ($config) {
    try {
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->query("
            SELECT * FROM remote_button_mappings 
            ORDER BY remote_name, button_number
        ");
        
        return sendSuccessResponse($response, [
            'mappings' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/remote-mapping', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['remote', 'button']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("
            SELECT * FROM remote_button_mappings 
            WHERE remote_name = ? AND button_number = ?
        ");
        $stmt->execute([
            $request->getQueryParams()['remote'],
            $request->getQueryParams()['button']
        ]);
        
        $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
        return sendSuccessResponse($response, ['mapping' => $mapping]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-remote-mapping', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, [
            'remote_name', 'button_number', 'target_type', 
            'target_id', 'command_name'
        ]);
        
        $pdo = getDatabaseConnection($config);
        
        // Check if mapping exists
        $stmt = $pdo->prepare("
            SELECT * FROM remote_button_mappings 
            WHERE remote_name = ? AND button_number = ?
        ");
        $stmt->execute([$data['remote_name'], $data['button_number']]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE remote_button_mappings 
                SET target_type = ?,
                    target_id = ?,
                    command_name = ?,
                    command_value = ?,
                    toggle_states = ?,
                    mapped = 1
                WHERE remote_name = ? AND button_number = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO remote_button_mappings 
                (remote_name, button_number, target_type, target_id, 
                 command_name, command_value, toggle_states, mapped)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
        }
        
        $params = $exists ? [
            $data['target_type'],
            $data['target_id'],
            $data['command_name'],
            $data['command_value'],
            json_encode($data['toggle_states']),
            $data['remote_name'],
            $data['button_number']
        ] : [
            $data['remote_name'],
            $data['button_number'],
            $data['target_type'],
            $data['target_id'],
            $data['command_name'],
            $data['command_value'],
            json_encode($data['toggle_states'])
        ];
        
        $stmt->execute($params);
        return sendSuccessResponse($response, ['message' => 'Mapping updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/delete-remote-mapping', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['remote_name', 'button_number']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("
            UPDATE remote_button_mappings 
            SET mapped = 0,
                target_type = NULL,
                target_id = NULL,
                command_name = 'toggle',
                command_value = NULL,
                toggle_states = NULL
            WHERE remote_name = ? AND button_number = ?
        ");
        
        $stmt->execute([$data['remote_name'], $data['button_number']]);
        return sendSuccessResponse($response, ['message' => 'Mapping deleted successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/service-status', function (Request $request, Response $response) {
    try {
        // Services to monitor
        $services = [
            'ble-remote-monitor' => 'Bluetooth Remote Processor',
            'state-synchronizer' => 'Device State Synchronizer',
            'govee-processor' => 'Govee Command Processor',
            'govee-updater' => 'Govee Device Updater',
            'hue-processor' => 'Hue Command Processor', 
            'hue-updater' => 'Hue Device Updater',
            'vesync-processor' => 'VeSync Command Processor',
            'vesync-updater' => 'VeSync Device Updater'
        ];

        $serviceStatuses = [];
        
        foreach ($services as $serviceName => $serviceTitle) {
            // Use systemctl to get service status
            exec("systemctl is-active $serviceName.service 2>&1", $output, $returnVar);
            $status = trim(implode('', $output));
            $output = [];
            
            $serviceStatuses[] = [
                'name' => $serviceName,
                'title' => $serviceTitle,
                'status' => $status
            ];
        }

        return sendSuccessResponse($response, ['services' => $serviceStatuses]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/service-logs', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['service']);
        $serviceName = $request->getQueryParams()['service'];
        
        // Whitelist of allowed services
        $allowedServices = [
            'govee-processor', 
            'hue-processor', 
            'vesync-processor',
            'ble-remote-monitor',
            'govee-updater',
            'hue-updater',
            'vesync-updater',
            'state-synchronizer'
        ];

        // Validate service name
        if (!in_array($serviceName, $allowedServices)) {
            throw new Exception('Invalid service name');
        }
        
        // Use journalctl to fetch the logs
        $command = "journalctl -u $serviceName.service -n 100 --no-pager -r";
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Failed to fetch logs for $serviceName");
        }
        
        return sendSuccessResponse($response, [
            'logs' => $output
        ]);
        
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/control-service', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['service', 'action']);

        // Whitelist of allowed services
        $allowedServices = [
            'govee-processor', 
            'hue-processor', 
            'vesync-processor',
            'ble-remote-monitor',
            'govee-updater',
            'hue-updater',
            'vesync-updater',
            'state-synchronizer'
        ];

        // Validate service name
        $serviceName = $data['service'];
        if (!in_array($serviceName, $allowedServices)) {
            throw new Exception('Invalid service name');
        }

        // Validate action
        $action = $data['action'];
        if (!in_array($action, ['start', 'stop', 'restart'])) {
            throw new Exception('Invalid action');
        }

        // Construct systemctl command
        $fullServiceName = $serviceName . '.service';
        
        // Use exec with escapeshellcmd to prevent command injection
        $output = [];
        $returnVar = null;
        $command = escapeshellcmd("sudo systemctl $action $fullServiceName");
        exec($command, $output, $returnVar);

        // Check if the command was successful
        if ($returnVar !== 0) {
            throw new Exception("Failed to $action service: " . implode("\n", $output));
        }

        // Give a short pause to allow service state to update
        sleep(1);

        // Verify the new service status
        exec("systemctl is-active $fullServiceName 2>&1", $statusOutput, $statusReturnVar);
        $status = trim(implode('', $statusOutput));

        return sendSuccessResponse($response, [
            'message' => ucfirst($action) . ' command sent successfully',
            'status' => $status
        ]);

    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

// Get all users
$app->get('/users', function (Request $request, Response $response) use ($config, $log) {
    try {
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->query("
            SELECT id, username, is_admin, created_at, last_login 
            FROM users 
            ORDER BY username
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return sendSuccessResponse($response, ['users' => $users]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

// Update or create user
$app->post('/update-user', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $pdo = getDatabaseConnection($config);
        
        if (isset($data['id']) && $data['id']) {
            // Update existing user
            if ($data['password']) {
                // Update with new password
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, 
                        email = ?, 
                        password = ?, 
                        is_admin = ? 
                    WHERE id = ?
                ");
                
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt->execute([
                    $data['username'],
                    $data['email'],
                    $password_hash,
                    $data['is_admin'],
                    $data['id']
                ]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, 
                        email = ?, 
                        is_admin = ? 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $data['username'],
                    $data['email'],
                    $data['is_admin'],
                    $data['id']
                ]);
            }
            
            return sendSuccessResponse($response, ['message' => 'User updated successfully']);
        } else {
            // Create new user
            validateRequiredParams($data, ['username', 'email', 'password', 'is_admin']);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, is_admin, created_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $data['username'],
                $data['email'],
                $password_hash,
                $data['is_admin']
            ]);
            
            return sendSuccessResponse($response, [
                'message' => 'User created successfully',
                'user_id' => $pdo->lastInsertId()
            ]);
        }
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

// Delete user
$app->post('/delete-user', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['id']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        return sendSuccessResponse($response, ['message' => 'User deleted successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->run();