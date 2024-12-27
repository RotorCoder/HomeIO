<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

function getDatabaseConnection($config) {
    return new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function getHueDevices($config) {
    global $log;
    $log->logInfoMsg("Getting all devices from Hue Bridge");
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://{$config['hue_bridge_ip']}/clip/v2/resource/light",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTPHEADER => array(
            'hue-application-key: ' . $config['hue_api_key']
        )
    ));
    
    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($response === false) {
        throw new Exception('Failed to connect to Hue Bridge');
    }
    
    return [
        'body' => $response,
        'statusCode' => $statusCode
    ];
}

function updateHueDeviceDatabase($pdo, $device) {
    global $log;
    
    try {
        $stmt = $pdo->prepare("UPDATE devices SET 
            powerState = ?, 
            brightness = ?, 
            colorTemp = ?,
            online = 1
            WHERE device = ? AND brand = 'hue'");
            
        $stmt->execute([
            $device['on']['on'] ? 'on' : 'off',
            isset($device['dimming']) ? round($device['dimming']['brightness']) : null,
            isset($device['color_temperature']) ? $device['color_temperature']['mirek'] : null,
            $device['id']
        ]);
        
    } catch (Exception $e) {
        $log->logErrorMsg("Error updating Hue device {$device['id']}: " . $e->getMessage());
    }
}

function getDevicesFromDatabase($pdo, $single_device = null, $room = null, $exclude_room = null) {
    global $log, $config;
    $log->logInfoMsg("Getting devices from the database.");
    
    // Quick update of Hue devices first
    try {
        $hueResponse = getHueDevices($config);
        if ($hueResponse['statusCode'] === 200) {
            $devices = json_decode($hueResponse['body'], true);
            if ($devices && isset($devices['data'])) {
                foreach ($devices['data'] as $device) {
                    updateHueDeviceDatabase($pdo, $device);
                }
            }
        }
    } catch (Exception $e) {
        $log->logErrorMsg("Hue update error: " . $e->getMessage());
        // Continue even if Hue update fails
    }
    
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
            
            $log->logInfoMsg("Executing query: " . $query);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        }
        
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($devices as &$device) {
            if (!empty($device['device']) && !empty($device['deviceGroup'])) {
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

try {
    $start = microtime(true);
    $timing = array();
    
    $single_device = isset($_GET['device']) ? $_GET['device'] : null;
    $room = isset($_GET['room']) ? $_GET['room'] : null;
    $exclude_room = isset($_GET['exclude_room']) ? $_GET['exclude_room'] : null;
    $quick = isset($_GET['quick']) ? ($_GET['quick'] === 'true') : false;

    $pdo = getDatabaseConnection($config);
    
    $devices_start = microtime(true);

    // Only update Govee devices during full refresh
    if (!$quick) {
        $log->logInfoMsg("Starting Govee devices update (quick = false)");
        try {
            $govee_start = microtime(true);
            
            // Get the current script's directory
            $currentDir = dirname($_SERVER['SCRIPT_NAME']);
            $baseUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://$_SERVER[HTTP_HOST]$currentDir";
            
            // Make HTTP request to Govee update endpoint
            $curl = curl_init();
            $goveeUrl = $baseUrl . '/update_govee_devices.php';
            $log->logInfoMsg("Calling Govee update URL: " . $goveeUrl);
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => $goveeUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ));
            
            $response = curl_exec($curl);
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            if (curl_errno($curl)) {
                throw new Exception('Curl error: ' . curl_error($curl));
            }
            
            curl_close($curl);
            
            $log->logInfoMsg("Govee update response status: " . $statusCode);
            
            if ($statusCode !== 200) {
                throw new Exception('Failed to update Govee devices: HTTP ' . $statusCode);
            }
            
            $goveeData = json_decode($response, true);
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
    
    $devices = getDevicesFromDatabase($pdo, $single_device, $room, $exclude_room);
    $timing['devices'] = array('duration' => round((microtime(true) - $devices_start) * 1000));
    
    // Calculate database timing
    $timing['database'] = array('duration' => round((microtime(true) - $devices_start) * 1000));
    
    // Calculate total timing
    $timing['total'] = round((microtime(true) - $start) * 1000);

    echo json_encode([
        'success' => true,
        'devices' => $devices,
        'updated' => date('c'),
        'timing' => $timing,
        'quick' => $quick // Include this so frontend knows if it was a quick update
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}