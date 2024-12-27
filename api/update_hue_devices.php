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
    
    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new Exception('Failed to connect to Hue Bridge: ' . $error);
    }
    
    curl_close($curl);
    
    return [
        'body' => $response,
        'statusCode' => $statusCode
    ];
}

function updateDeviceDatabase($pdo, $device) {
    global $log;
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device['owner']['rid']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_values = [
        'device' => $device['owner']['rid'],
        'model' => null,  // Model ID not available in light endpoint
        'device_name' => $device['metadata']['name'],
        'controllable' => 1,
        'retrievable' => 1,
        'supportCmds' => json_encode(['brightness', 'colorTem', 'color']),
        'brand' => 'hue',
        'online' => true,
        'powerState' => $device['on']['on'] ? 'on' : 'off',
        'brightness' => isset($device['dimming']) ? round($device['dimming']['brightness']) : null,
        'colorTemInKelvin' => isset($device['color_temperature']) ? $device['color_temperature']['mirek'] : null
    ];
    
    if (!$current) {
        $log->logInfoMsg("New Hue device detected: {$device['owner']['rid']}");
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
        return $new_values;
    }
    
    $changes = [];
    $updates = [];
    $params = [':device' => $device['owner']['rid']];
    
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
            $changes[] = "$key changed from $current_value to $new_value";
            $updates[] = "$key = :$key";
            $params[":$key"] = $key === 'online' ? ($value ? 1 : 0) : $value;
        }
    }
    
    if (!empty($updates)) {
        $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = :device";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $log->logInfoMsg("Updated Hue device {$device['owner']['rid']}: " . implode(", ", $changes));
    }
    
    return $new_values;
}

// Main execution
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
    
    echo json_encode([
        'success' => true,
        'devices' => $updated_devices,
        'updated' => date('c'),
        'timing' => $timing
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}