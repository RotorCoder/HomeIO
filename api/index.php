<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';
require $config['sharedpath'].'/govee_lib.php';
require $config['sharedpath'].'/hue_lib.php';

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
        AND status IN ('xpending', 'xprocessing')
    ");
    $stmt->execute([$device]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['count'] > 0);
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
        
        // Filter out devices with pending commands
        $filteredDevices = array_filter($devices, function($device) use ($pdo) {
            return !hasDevicePendingCommand($pdo, $device['device']);
        });
        
        foreach ($filteredDevices as &$device) {
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
                
                // Filter group members with pending commands
                $groupMembers = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
                $device['group_members'] = array_filter($groupMembers, function($member) use ($pdo) {
                    return !hasDevicePendingCommand($pdo, $member['device']);
                });
            }
        }
        
        return array_values($filteredDevices); // Re-index array after filtering
        
    } catch (PDOException $e) {
        $log->logErrorMsg("Database error: " . $e->getMessage());
        throw new Exception("Database error occurred");
    } catch (Exception $e) {
        $log->logErrorMsg("Error in getDevicesFromDatabase: " . $e->getMessage());
        throw $e;
    }
}

// Create Slim app and configure middleware
$app = AppFactory::create();
$app->setBasePath('/homeio/api');
$app->addRoutingMiddleware();
//$app->add('validateApiKey');
$app->addErrorMiddleware(true, true, true);

// Initialize logger
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

// Routes
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
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = NULL, showInGroupOnly = 0 WHERE deviceGroup = ?");
            $stmt->execute([$data['groupId']]);
            
            $stmt = $pdo->prepare("DELETE FROM device_groups WHERE id = ?");
            $stmt->execute([$data['groupId']]);
            
            $pdo->commit();
            return sendSuccessResponse($response, ['message' => 'Group deleted successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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

$app->get('/device-config', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['device']);
        
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->prepare("SELECT room, low, medium, high, preferredColorTem, x10Code FROM devices WHERE device = ?");
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

$app->get('/devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $timing = [];
        $result = measureExecutionTime(function() use ($request, $config, $log, &$timing) {
            $single_device = $request->getQueryParams()['device'] ?? null;
            $room = $request->getQueryParams()['room'] ?? null;
            $exclude_room = $request->getQueryParams()['exclude_room'] ?? null;
            $quick = isset($request->getQueryParams()['quick']) ? 
                ($request->getQueryParams()['quick'] === 'true') : false;

            $pdo = getDatabaseConnection($config);
            
            if (!$quick) {
    // Only update Govee on non-quick refreshes
    $govee_timing = measureExecutionTime(function() use ($config, $log) {
        $log->logInfoMsg("Starting Govee devices update (quick = false)");
        try {
            $goveeApi = new GoveeAPI($config['govee_api_key'], $config['db_config']);
            $goveeDevices = $goveeApi->getDevices();
            
            if ($goveeDevices['statusCode'] !== 200) {
                throw new Exception('Failed to update Govee devices: HTTP ' . $goveeDevices['statusCode']);
            }
            
            $goveeData = json_decode($goveeDevices['body'], true);
            if (!isset($goveeData['data']) || !isset($goveeData['data']['devices'])) {
                throw new Exception('Invalid response format from Govee API');
            }
            
            foreach ($goveeData['data']['devices'] as $device) {
                // First update device info
                $goveeApi->updateDeviceDatabase($device);
                
                // Then get and update device state
                $log->logInfoMsg("Fetching state for device: " . $device['device']);
                $stateResponse = $goveeApi->getDeviceState($device);
                
                if ($stateResponse['statusCode'] !== 200) {
                    $log->logErrorMsg("Failed to get state for device " . $device['device'] . ": HTTP " . $stateResponse['statusCode']);
                    continue;
                }
                
                $stateData = json_decode($stateResponse['body'], true);
                if (!$stateData || !isset($stateData['data']) || !isset($stateData['data']['properties'])) {
                    $log->logErrorMsg("Invalid state data for device " . $device['device']);
                    continue;
                }
                
                $log->logInfoMsg("Updating state for device: " . $device['device']);
                $device_states = [$device['device'] => $stateData['data']['properties']];
                $goveeApi->updateDeviceStateInDatabase($device, $device_states, $device);
            }
            
            $log->logInfoMsg("Govee update completed successfully");
        } catch (Exception $e) {
            $log->logErrorMsg("Govee update error: " . $e->getMessage());
            throw $e;
        }
    });
    $timing['govee'] = ['duration' => $govee_timing['duration']];
}

            // Always update Hue devices
            $hue_timing = measureExecutionTime(function() use ($config, $log) {
                $log->logInfoMsg("Starting Hue devices update");
                try {
                    $hueApi = new HueAPI($config['hue_bridge_ip'], $config['hue_api_key'], $config['db_config']);
                    $hueResponse = $hueApi->getDevices();
                    
                    if ($hueResponse['statusCode'] !== 200) {
                        throw new Exception('Failed to get devices from Hue Bridge');
                    }
                    
                    $devices = json_decode($hueResponse['body'], true);
                    if (!$devices || !isset($devices['data'])) {
                        throw new Exception('Failed to parse Hue Bridge response');
                    }
                    
                    foreach ($devices['data'] as $device) {
                        $hueApi->updateDeviceDatabase($device);
                    }
                    
                    $log->logInfoMsg("Hue update completed successfully");
                } catch (Exception $e) {
                    $log->logErrorMsg("Hue update error: " . $e->getMessage());
                }
            });
            $timing['hue'] = ['duration' => $hue_timing['duration']];

            // Get all devices from database
            $devices_timing = measureExecutionTime(function() use ($pdo, $single_device, $room, $exclude_room) {
                return getDevicesFromDatabase($pdo, $single_device, $room, $exclude_room);
            });
            
            $timing['devices'] = ['duration' => $devices_timing['duration']];
            $timing['database'] = ['duration' => $devices_timing['duration']];
            
            return [
                'devices' => $devices_timing['result'],
                'updated' => date('c'),
                'timing' => $timing,
                'quick' => $quick
            ];
        });
        
        return sendSuccessResponse($response, $result['result']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->get('/rooms', function (Request $request, Response $response) use ($config) {
    try {
        $pdo = getDatabaseConnection($config);
        $stmt = $pdo->query("SELECT id, room_name FROM rooms ORDER BY tab_order");
        
        return sendSuccessResponse($response, ['rooms' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->get('/room-temperature', function (Request $request, Response $response) use ($config) {
    try {
        validateRequiredParams($request->getQueryParams(), ['room']);
        
        $pdo = getDatabaseConnection($config);
        // Get temperature data by joining on MAC address
        $stmt = $pdo->prepare("
            SELECT t.temp, t.humidity 
            FROM rooms r
            JOIN thermometers t ON t.mac = r.thermometer 
            WHERE r.id = ? 
            ORDER BY t.updated DESC 
            LIMIT 1
        ");
        $stmt->execute([$request->getQueryParams()['room']]);
        
        $tempData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tempData) {
            throw new Exception('No temperature data found for this room');
        }
        
        return sendSuccessResponse($response, [
            'temperature' => $tempData['temp'],
            'humidity' => $tempData['humidity']
        ]);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

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

$app->post('/update-device-config', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device']);
        
        $pdo = getDatabaseConnection($config);
        $x10Code = (!empty($data['x10Code'])) ? $data['x10Code'] : null;
        
        $stmt = $pdo->prepare("UPDATE devices SET room = ?, low = ?, medium = ?, high = ?, preferredColorTem = ?, x10Code = ? WHERE device = ?");
        $stmt->execute([
            $data['room'],
            $data['low'],
            $data['medium'],
            $data['high'],
            $data['preferredColorTem'],
            $x10Code,
            $data['device']
        ]);
        
        return sendSuccessResponse($response, ['message' => 'Device configuration updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-group', function (Request $request, Response $response) use ($config) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device', 'action']);
        
        $pdo = getDatabaseConnection($config);
        
        if ($data['action'] === 'create') {
            validateRequiredParams($data, ['groupName', 'model']);
            
            $stmt = $pdo->prepare("INSERT INTO device_groups (name, model, reference_device) VALUES (?, ?, ?)");
            $stmt->execute([$data['groupName'], $data['model'], $data['device']]);
            $groupId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = ?, showInGroupOnly = 0 WHERE device = ?");
            $stmt->execute([$groupId, $data['device']]);
            
        } else if ($data['action'] === 'join') {
            validateRequiredParams($data, ['groupId']);
            
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = ?, showInGroupOnly = 1 WHERE device = ?");
            $stmt->execute([$data['groupId'], $data['device']]);
            
        } else if ($data['action'] === 'leave') {
            $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = NULL, showInGroupOnly = 0 WHERE device = ?");
            $stmt->execute([$data['device']]);
        }
        
        return sendSuccessResponse($response, ['message' => 'Device group updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e);
    }
});

$app->post('/update-device-state', function (Request $request, Response $response) use ($config, $log) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        validateRequiredParams($data, ['device', 'command', 'value']);
        
        $pdo = getDatabaseConnection($config);
        
        switch($data['command']) {
            case 'turn':
                $stmt = $pdo->prepare("UPDATE devices SET powerState = ? WHERE device = ?");
                $stmt->execute([$data['value'], $data['device']]);
                break;
                
            case 'brightness':
                $stmt = $pdo->prepare("UPDATE devices SET brightness = ?, powerState = 'on' WHERE device = ?");
                $stmt->execute([(int)$data['value'], $data['device']]);
                break;
            
            default:
                throw new Exception('Invalid command type');
        }
        
        return sendSuccessResponse($response, ['message' => 'Device state updated successfully']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->get('/update-govee-devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $timing = [];
        $result = measureExecutionTime(function() use ($config, $log, &$timing) {
            $goveeApi = new GoveeAPI($config['govee_api_key'], $config['db_config']);
            
            $api_timing = measureExecutionTime(function() use ($goveeApi) {
                $goveeResponse = $goveeApi->getDevices();
                if ($goveeResponse['statusCode'] !== 200) {
                    throw new Exception('Failed to get devices from Govee API');
                }
                
                $result = json_decode($goveeResponse['body'], true);
                if ($result['code'] !== 200) {
                    throw new Exception($result['message']);
                }
                
                return $result;
            });
            $timing['devices'] = ['duration' => $api_timing['duration']];
            
            $states_timing = measureExecutionTime(function() use ($goveeApi, $api_timing) {
                $govee_devices = $api_timing['result']['data']['devices'];
                $device_states = array();
                $updated_devices = array();
                
                foreach ($govee_devices as $device) {
                    $goveeApi->updateDeviceDatabase($device);
                    
                    $stateResponse = $goveeApi->getDeviceState($device);
                    if ($stateResponse['statusCode'] === 200) {
                        $state_result = json_decode($stateResponse['body'], true);
                        if ($state_result['code'] === 200) {
                            $device_states[$device['device']] = $state_result['data']['properties'];
                            $updated_devices[] = $goveeApi->updateDeviceStateInDatabase($device, $device_states, $device);
                        }
                    }
                }
                
                return $updated_devices;
            });
            
            $timing['states'] = ['duration' => $states_timing['duration']];
            
            return [
                'devices' => $states_timing['result'],
                'updated' => date('c'),
                'timing' => $timing
            ];
        });
        
        return sendSuccessResponse($response, $result['result']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->get('/update-hue-devices', function (Request $request, Response $response) use ($config, $log) {
    try {
        $timing = [];
        $result = measureExecutionTime(function() use ($config, &$timing) {
            // Initialize HueAPI
            $hueApi = new HueAPI($config['hue_bridge_ip'], $config['hue_api_key'], $config['db_config']);
            
            $api_timing = measureExecutionTime(function() use ($hueApi) {
                $hueResponse = $hueApi->getDevices();
                if ($hueResponse['statusCode'] !== 200) {
                    throw new Exception('Failed to get devices from Hue Bridge');
                }
                
                $devices = json_decode($hueResponse['body'], true);
                if (!$devices || !isset($devices['data'])) {
                    throw new Exception('Failed to parse Hue Bridge response');
                }
                
                return $devices;
            });
            $timing['devices'] = ['duration' => $api_timing['duration']];
            
            $states_timing = measureExecutionTime(function() use ($hueApi, $api_timing) {
                $updated_devices = array();
                foreach ($api_timing['result']['data'] as $device) {
                    $updated_devices[] = $hueApi->updateDeviceDatabase($device);
                }
                return $updated_devices;
            });
            
            $timing['states'] = ['duration' => $states_timing['duration']];
            
            return [
                'devices' => $states_timing['result'],
                'updated' => date('c'),
                'timing' => $timing
            ];
        });
        
        return sendSuccessResponse($response, $result['result']);
    } catch (Exception $e) {
        return sendErrorResponse($response, $e, $log);
    }
});

$app->run();