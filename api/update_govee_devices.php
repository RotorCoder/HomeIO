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
        'colorTem_rangeMin' => null,
        'colorTem_rangeMax' => null
    ];
    
    if (in_array('colorTem', $device['supportCmds']) && 
        isset($device['properties']) && 
        isset($device['properties']['colorTem']) && 
        isset($device['properties']['colorTem']['range'])) {
        
        $new_values['colorTem_rangeMin'] = $device['properties']['colorTem']['range']['min'];
        $new_values['colorTem_rangeMax'] = $device['properties']['colorTem']['range']['max'];
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
        'colorTemInKelvin' => null,
        'colorTem' => null
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

// Main execution
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