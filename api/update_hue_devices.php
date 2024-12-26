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
        CURLOPT_URL => "https://{$config['hue_bridge_ip']}/api/v2/clip/resource/device",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array(
            'hue-application-key: ' . $config['hue_application_key'],
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return [
        'body' => $response,
        'statusCode' => $statusCode
    ];
}

function getDeviceState($device_id, $config) {
    global $log;
    $log->logInfoMsg("Getting device state for device ID: " . $device_id);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://{$config['hue_bridge_ip']}/api/v2/clip/resource/light/{$device_id}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array(
            'hue-application-key: ' . $config['hue_application_key'],
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return [
        'body' => $response,
        'statusCode' => $statusCode
    ];
}

function updateDeviceDatabase($pdo, $device) {
    global $log;
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device['id']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_values = [
        'device' => $device['id'],
        'model' => $device['product_data']['model_id'],
        'device_name' => $device['metadata']['name'],
        'controllable' => 1,  // Hue devices are always controllable
        'retrievable' => 1,   // Hue devices are always retrievable
        'supportCmds' => json_encode(['brightness', 'colorTem', 'color']),
        'brand' => 'hue'
    ];
    
    if (!$current) {
        $log->logInfoMsg("New Hue device detected: {$device['id']}");
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
    $params = [':device' => $device['id']];
    
    foreach ($new_values as $key => $value) {
        if ($value === null || !isset($current[$key])) {
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
        $log->logInfoMsg("Updated Hue device {$device['id']}: " . implode(", ", $changes));
    }
    
    return false;
}

function updateDeviceStateInDatabase($pdo, $device_id, $state) {
    global $log;
    
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
    $stmt->execute([$device_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $new_values = [
        'online' => $state['status'] === 'connected',
        'powerState' => $state['on']['on'] ? 'on' : 'off',
        'brightness' => isset($state['dimming']) ? round(($state['dimming']['brightness'] / 100) * 100) : null,
        'colorTemInKelvin' => isset($state['color_temperature']) ? $state['color_temperature']['mirek'] : null
    ];
    
    $changes = [];
    $updates = [];
    $params = [':device' => $device_id];
    
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
        $log->logInfoMsg("Updated Hue device state {$device_id}: " . implode(", ", $changes));
    }
    
    return array_merge(['device' => $device_id], $new_values);
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
    if (!$devices) {
        throw new Exception('Failed to parse Hue Bridge response');
    }
    
    $timing['devices'] = array('duration' => round((microtime(true) - $api_start) * 1000));
    
    // Update devices and their states
    $states_start = microtime(true);
    $updated_devices = array();
    
    foreach ($devices['data'] as $device) {
        // Only process light devices
        if ($device['type'] !== 'light') {
            continue;
        }
        
        // Update device basic info
        updateDeviceDatabase($pdo, $device);
        
        // Get and update device state
        $stateResponse = getDeviceState($device['id'], $config);
        if ($stateResponse['statusCode'] === 200) {
            $state_data = json_decode($stateResponse['body'], true);
            if ($state_data && isset($state_data['data'][0])) {
                $updated_device = updateDeviceStateInDatabase($pdo, $device['id'], $state_data['data'][0]);
                $updated_devices[] = $updated_device;
            }
        }
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