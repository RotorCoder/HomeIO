<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VeSyncRoutes {
    private $app;
    private $config;
    private $log;
    private $last_update;
    private $update_interval = 60;  // Update device status every 60 seconds
    private $token;
    private $account_id;
    
    public function __construct($app, $config, $log) {
        $this->app = $app;
        $this->config = $config;
        $this->log = $log;
    }

    public function register() {
        $vesyncInstance = $this;  // Store instance reference

        // Update VeSync devices route
        $this->app->get('/update-vesync-devices', function (Request $request, Response $response) use ($vesyncInstance) {
            try {
                $timing = [];
                $result = measureExecutionTime(function() use ($vesyncInstance, &$timing) {
                    // Initialize VeSync login
                    $api_timing = measureExecutionTime(function() use ($vesyncInstance) {
                        if (!$vesyncInstance->login()) {
                            throw new Exception('Failed to login to VeSync API');
                        }
                        
                        $devices = $vesyncInstance->getDevices();
                        if (!$devices) {
                            throw new Exception('Failed to get devices from VeSync API');
                        }
                        
                        return $devices;
                    });
                    $timing['devices'] = ['duration' => $api_timing['duration']];
                    
                    // Update device states
                    $states_timing = measureExecutionTime(function() use ($api_timing, $vesyncInstance) {
                        $updated_devices = [];
                        $pdo = getDatabaseConnection($vesyncInstance->config);
                        
                        foreach ($api_timing['result'] as $device) {
                            $vesyncInstance->updateDeviceInDatabase($pdo, $device);
                            $updated_devices[] = $device;
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
                return sendErrorResponse($response, $e, $vesyncInstance->log);
            }
        });

        // Process command queue for VeSync devices
        $this->app->get('/process-vesync-commands', function (Request $request, Response $response) use ($vesyncInstance) {
            try {
                $maxCommands = $request->getQueryParams()['max'] ?? 5;
                $result = $vesyncInstance->processBatch($maxCommands);
                return sendSuccessResponse($response, $result);
            } catch (Exception $e) {
                return sendErrorResponse($response, $e, $vesyncInstance->log);
            }
        });
    }

    private function login() {
        $curl = curl_init();
        
        // Calculate hashed password (MD5)
        $hashed_password = md5($this->config['vesync_api']['password']);
        
        $payload = [
            'email' => $this->config['vesync_api']['user'],
            'password' => $hashed_password,
            'timeZone' => 'America/New_York',
            'acceptLanguage' => 'en',
            'appVersion' => '2.8.6',
            'phoneBrand' => 'SM N9005',
            'phoneOS' => 'Android',
            'traceId' => time(),
            'devToken' => '',
            'userType' => '1',
            'method' => 'login'
        ];
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com/cloud/v1/user/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept-Language: en',
                'User-Agent: VeSync/3.2.39 (com.etekcity.vesyncPlatform; build:5; iOS 15.5.0) Alamofire/5.2.1'
            ),
        ));
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            $this->log->error("VeSync login failed with HTTP code: $httpCode");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['result']) || !isset($result['result']['token']) || !isset($result['result']['accountID'])) {
            $this->log->error("VeSync login response missing expected fields", ['response' => $response]);
            return false;
        }
        
        // Store token and account_id for subsequent requests
        $this->token = $result['result']['token'];
        $this->account_id = $result['result']['accountID'];
        
        return true;
    }

    private function getDevices() {
        if (!isset($this->token) || !isset($this->account_id)) {
            $this->log->error("Missing token or account_id. Login required.");
            return false;
        }
        
        $payload = [
            'timeZone' => 'America/New_York',
            'acceptLanguage' => 'en',
            'accountID' => $this->account_id,
            'token' => $this->token,
            'appVersion' => '2.8.6',
            'phoneBrand' => 'SM N9005',
            'phoneOS' => 'Android',
            'traceId' => time(),
            'method' => 'devices',
            'pageNo' => '1',
            'pageSize' => '100'
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com/cloud/v1/deviceManaged/devices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 8,  // API timeout is 7 seconds
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',  // Note: Changed from GET to POST
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept-Language: en',
                'accountId: ' . $this->account_id,
                'tk: ' . $this->token,
                'User-Agent: okhttp/3.12.1'  // Important for some endpoints
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            $this->log->error("Failed to get devices with HTTP code: $httpCode", ['response' => $response]);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['result']) || !isset($result['result']['list'])) {
            $this->log->error("Device list response missing expected fields", ['response' => $response]);
            return false;
        }
        
        return $result['result']['list'];
    }

    private function updateDeviceInDatabase($pdo, $device) {
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
        $stmt->execute([$device['cid']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_values = [
            'device' => $device['cid'],
            'model' => $device['deviceType'],
            'device_name' => $device['deviceName'],
            'controllable' => 1,
            'retrievable' => 1,
            'brand' => 'vesync',
            'online' => isset($device['connectionStatus']) && $device['connectionStatus'] === 'online' ? 1 : 0, // Fixed: explicit 1/0 values
            'powerState' => isset($device['deviceStatus']) ? $device['deviceStatus'] : 'off'
        ];
        
        // Add device-specific properties
        if (isset($device['extension'])) {
            if (isset($device['extension']['fanSpeedLevel'])) {
                $new_values['speed'] = $device['extension']['fanSpeedLevel'];
            }
            if (isset($device['extension']['mode'])) {
                $new_values['mode'] = $device['extension']['mode'];
            }
        }
        
        // Make sure all values are properly typed for the database
        foreach ($new_values as $key => $value) {
            if ($value === '' && in_array($key, ['online', 'controllable', 'retrievable'])) {
                $new_values[$key] = 0; // Convert empty strings to 0 for integer columns
            }
        }
        
        if (!$current) {
            $columns = implode(', ', array_keys($new_values));
            $placeholders = implode(', ', array_fill(0, count($new_values), '?'));
            
            $stmt = $pdo->prepare("INSERT INTO devices ($columns) VALUES ($placeholders)");
            $stmt->execute(array_values($new_values));
        } else {
            $updates = [];
            foreach ($new_values as $key => $value) {
                $updates[] = "$key = ?";
            }
            
            $stmt = $pdo->prepare("UPDATE devices SET " . implode(', ', $updates) . " WHERE device = ?");
            $values = array_values($new_values);
            $values[] = $device['cid'];
            $stmt->execute($values);
        }
        
        // Update device details if needed
        if (isset($device['deviceStatus']) && $device['deviceStatus'] === 'on' && 
            isset($device['connectionStatus']) && $device['connectionStatus'] === 'online') {
            $this->updateDeviceDetails($device);
        }
        
        return $new_values;
    }
    
    private function updateDeviceDetails($device) {
        // For each device type, call the appropriate method to get additional details
        // This is a placeholder - you'd implement this based on device types
        if (!isset($this->token) || !isset($this->account_id)) {
            return false;
        }
        
        // Example implementation for outlets
        if (in_array($device['deviceType'], ['ESW15-USA', 'ESW03-USA', 'ESW01-EU', 'wifi-switch-1.3'])) {
            // Get energy usage, power, etc for outlets
            // Implementation would depend on device type
        }
        
        // You can add similar implementations for other device types
    }

    private function getNextBatchWithSuperseding($limit = 20) {
        $pdo = getDatabaseConnection($this->config);
        $pdo->beginTransaction();
        try {
            // Add timeout check - reset commands stuck processing for >5 minutes
            $stmt = $pdo->prepare("
                UPDATE command_queue 
                SET status = 'pending',
                    processed_at = NULL
                WHERE status = 'processing' 
                AND processed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND brand = 'vesync'
            ");
            $stmt->execute();
    
            // First, get all pending commands for VeSync devices
            $stmt = $pdo->prepare("
                SELECT cq.id, cq.device, cq.model, cq.command, cq.created_at
                FROM command_queue cq
                WHERE cq.status = 'pending'
                AND cq.brand = 'vesync'
                ORDER BY cq.created_at ASC
            ");
            $stmt->execute();
            $allCommands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group commands by device
            $commandsByDevice = [];
            foreach ($allCommands as $command) {
                $deviceId = $command['device'];
                if (!isset($commandsByDevice[$deviceId])) {
                    $commandsByDevice[$deviceId] = [];
                }
                $commandsByDevice[$deviceId][] = $command;
            }
            
            // For each device, keep only the latest command of each type
            $finalCommands = [];
            foreach ($commandsByDevice as $deviceId => $deviceCommands) {
                $latestByType = [];
                
                foreach ($deviceCommands as $command) {
                    $cmdData = json_decode($command['command'], true);
                    $cmdType = $cmdData['name'] ?? 'unknown';
                    
                    // For each command type, keep track of the latest command
                    if (!isset($latestByType[$cmdType]) || 
                        strtotime($command['created_at']) > strtotime($latestByType[$cmdType]['created_at'])) {
                        $latestByType[$cmdType] = $command;
                    }
                }
                
                // Add all latest commands to final list
                foreach ($latestByType as $type => $command) {
                    $finalCommands[] = $command;
                }
            }
            
            // Sort by created_at and limit to requested number
            usort($finalCommands, function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            $finalCommands = array_slice($finalCommands, 0, $limit);
            
            // Mark selected commands as processing
            if (!empty($finalCommands)) {
                $ids = array_column($finalCommands, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("
                    UPDATE command_queue
                    SET status = 'processing',
                        processed_at = CURRENT_TIMESTAMP
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
                
                // Mark superseded commands as skipped
                if (count($allCommands) > count($finalCommands)) {
                    // Get IDs of commands we're not processing
                    $processedIds = array_column($finalCommands, 'id');
                    $skippedIds = array_values(array_filter(array_column($allCommands, 'id'), function($id) use ($processedIds) {
                        return !in_array($id, $processedIds);
                    }));
                    
                    if (!empty($skippedIds)) {
                        $skipPlaceholders = implode(',', array_fill(0, count($skippedIds), '?'));
                        $stmt = $pdo->prepare("
                            UPDATE command_queue
                            SET status = 'skipped',
                                processed_at = CURRENT_TIMESTAMP,
                                error_message = 'Superseded by newer command'
                            WHERE id IN ($skipPlaceholders)
                        ");
                        $stmt->execute($skippedIds);
                        
                        $this->log->logInfoMsg("Skipped " . count($skippedIds) . " superseded commands");
                    }
                }
            }
            
            $pdo->commit();
            return $finalCommands;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function markCommandComplete($id, $success = true, $errorMessage = null) {
        $pdo = getDatabaseConnection($this->config);
        $stmt = $pdo->prepare("
            UPDATE command_queue
            SET 
                status = :status,
                processed_at = CURRENT_TIMESTAMP,
                error_message = :error_message
            WHERE id = :id
        ");
        
        $stmt->execute([
            'status' => $success ? 'completed' : 'failed',
            'error_message' => $errorMessage,
            'id' => $id
        ]);
    }

    private function processBatch($maxCommands = 5) {
        try {
            // Login first if we need to process commands
            if (!$this->login()) {
                throw new Exception('Failed to login to VeSync API for command processing');
            }
            
            // Get next batch of commands with superseding logic
            $commands = $this->getNextBatchWithSuperseding($maxCommands);
            
            $results = [];
            foreach ($commands as $command) {
                try {
                    $cmd = json_decode($command['command'], true);
                    $result = $this->sendCommand($command['device'], $command['model'], $cmd);
                    
                    // Mark command as completed
                    $this->markCommandComplete($command['id'], true);
                    
                    $results[] = [
                        'command_id' => $command['id'],
                        'result' => $result,
                        'success' => true
                    ];
                } catch (Exception $e) {
                    // Mark command as failed
                    $this->markCommandComplete($command['id'], false, $e->getMessage());
                    
                    $results[] = [
                        'command_id' => $command['id'],
                        'error' => $e->getMessage(),
                        'success' => false
                    ];
                }
            }
            
            return [
                'success' => true,
                'processed' => count($results),
                'results' => $results
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to process VeSync commands: ' . $e->getMessage());
        }
    }

    public function sendCommand($deviceId, $deviceModel, $command) {
        if (!isset($command['name'])) {
            throw new Exception('Invalid command format');
        }
    
        if (!isset($this->token) || !isset($this->account_id)) {
            throw new Exception('Not logged in to VeSync API');
        }
    
        // Process command based on device type and command
        try {
            $result = false;
            
            // Different device types use different endpoints and payloads
            switch ($deviceModel) {
                case 'wifi-switch-1.3': // 7A outlet
                    $result = $this->send7ACommand($deviceId, $command);
                    break;
                    
                case 'ESW03-USA': // 10A outlet
                case 'ESW01-EU':
                    $result = $this->send10ACommand($deviceId, $command);
                    break;
                    
                case 'ESW15-USA': // 15A outlet
                    $result = $this->send15ACommand($deviceId, $command);
                    break;
                    
                case 'Core200S':
                case 'Core300S':
                case 'Core400S':
                case 'Core600S':
                    $result = $this->sendAirPurifierCommand($deviceId, $command);
                    break;
                
                case 'LUH-A602S-WUS': // Levoit LV600S model humidifier
                case 'LUH-A602S-WUSR':
                case 'LUH-A602S-WEUR':
                case 'LUH-A602S-WEU':
                case 'LUH-A602S-WJP':
                case 'LUH-A602S-WUSC':
                    $result = $this->sendHumidifierCommand($deviceId, $command);
                    break;
                    
                default:
                    throw new Exception('Unsupported device model: ' . $deviceModel);
            }
    
            if ($result) {
                // Update device state in database
                $pdo = getDatabaseConnection($this->config);
                switch($command['name']) {
                    case 'turn':
                        $stmt = $pdo->prepare("UPDATE devices SET powerState = ? WHERE device = ?");
                        $stmt->execute([$command['value'], $deviceId]);
                        break;
                        
                    case 'brightness':
                        $stmt = $pdo->prepare("UPDATE devices SET brightness = ?, powerState = 'on' WHERE device = ?");
                        $stmt->execute([(int)$command['value'], $deviceId]);
                        break;
                        
                    case 'speed':
                        $stmt = $pdo->prepare("UPDATE devices SET speed = ? WHERE device = ?");
                        $stmt->execute([(int)$command['value'], $deviceId]);
                        break;
                        
                    case 'mode':
                        $stmt = $pdo->prepare("UPDATE devices SET mode = ? WHERE device = ?");
                        $stmt->execute([$command['value'], $deviceId]);
                        break;
                }
            }
    
            return [
                'success' => $result,
                'message' => $result ? 'Command sent successfully' : 'Command failed'
            ];
    
        } catch (Exception $e) {
            throw new Exception('Failed to send command: ' . $e->getMessage());
        }
    }
    
    private function sendHumidifierCommand($deviceId, $command) {
        $endpoint = '/cloud/v2/deviceManaged/bypassV2';
        
        // Base payload - ensure structure matches Python's Helpers.bypass_body_v2()
        $basePayload = [
            'timeZone' => 'America/New_York',
            'acceptLanguage' => 'en',
            'accountID' => $this->account_id,
            'token' => $this->token,
            'appVersion' => '2.8.6',
            'phoneBrand' => 'SM N9005',
            'phoneOS' => 'Android',
            'traceId' => time(),
            'cid' => $deviceId,
            'configModule' => 'VeSyncHumid200300S',
            'method' => 'bypassV2',
            'debugMode' => false,
            'deviceRegion' => 'US'
        ];
        
        if ($command['name'] === 'turn') {
            $basePayload['payload'] = [
                'data' => [
                    'enabled' => ($command['value'] === 'on'), // Make sure this is a PHP boolean
                    'id' => 0
                ],
                'method' => 'setSwitch',
                'source' => 'APP'
            ];
        }
        
        // For headers, make sure to exactly match Python's Helpers.bypass_header()
        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'User-Agent: okhttp/3.12.1'
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($basePayload),
            CURLOPT_HTTPHEADER => $headers,
        ));
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Log the response
        //$this->log->logInfoMsg("Humidifier command response: " . $response);
        //$this->log->logInfoMsg("Sent payload: " . json_encode($basePayload));
        
        $result = json_decode($response, true);
        return (isset($result['code']) && $result['code'] === 0);
    }
    
    private function send7ACommand($deviceId, $command) {
        $endpoint = '';
        $method = 'PUT';
        $payload = [];
        
        switch ($command['name']) {
            case 'turn':
                $endpoint = '/v1/wifi-switch-1.3/' . $deviceId . '/status/' . strtolower($command['value']);
                break;
                
            default:
                throw new Exception('Unsupported command for 7A outlet: ' . $command['name']);
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept-Language: en',
                'accountId: ' . $this->account_id,
                'tk: ' . $this->token
            ),
        ));
        
        if (!empty($payload)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ($httpCode === 200);
    }
    
    private function send10ACommand($deviceId, $command) {
        $endpoint = '/10a/v1/device/devicestatus';
        $payload = [
            'accountID' => $this->account_id,
            'token' => $this->token,
            'uuid' => $deviceId,
            'timeZone' => 'America/New_York'
        ];
        
        switch ($command['name']) {
            case 'turn':
                $payload['status'] = $command['value'];
                break;
                
            default:
                throw new Exception('Unsupported command for 10A outlet: ' . $command['name']);
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept-Language: en',
                'accountId: ' . $this->account_id,
                'tk: ' . $this->token
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $result = json_decode($response, true);
        return (isset($result['code']) && $result['code'] === 0);
    }
    
    private function send15ACommand($deviceId, $command) {
        $endpoint = '/15a/v1/device/devicestatus';
        $payload = [
            'accountID' => $this->account_id,
            'token' => $this->token,
            'uuid' => $deviceId,
            'timeZone' => 'America/New_York'
        ];
        
        switch ($command['name']) {
            case 'turn':
                $payload['status'] = $command['value'];
                break;
                
            default:
                throw new Exception('Unsupported command for 15A outlet: ' . $command['name']);
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept-Language: en',
                'accountId: ' . $this->account_id,
                'tk: ' . $this->token
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $result = json_decode($response, true);
        return (isset($result['code']) && $result['code'] === 0);
    }
    
    private function sendAirPurifierCommand($deviceId, $command) {
        // Air purifiers use the bypass V2 API
        $endpoint = '/cloud/v2/deviceManaged/bypassV2';
        
        $basePayload = [
            'timeZone' => 'America/New_York',
            'acceptLanguage' => 'en',
            'accountID' => $this->account_id,
            'token' => $this->token,
            'appVersion' => '2.8.6',
            'phoneBrand' => 'SM N9005',
            'phoneOS' => 'Android',
            'traceId' => time(),
            'cid' => $deviceId,
            'configModule' => 'WiFiMeshAirPurifier', // This may need to be adjusted based on device
            'deviceRegion' => 'US'
        ];
        
        switch ($command['name']) {
            case 'turn':
                $basePayload['payload'] = [
                    'data' => [
                        'enabled' => ($command['value'] === 'on'),
                        'id' => 0
                    ],
                    'method' => 'setSwitch',
                    'source' => 'APP'
                ];
                break;
                
            case 'mode':
                $basePayload['payload'] = [
                    'data' => [
                        'mode' => $command['value']
                    ],
                    'method' => 'setPurifierMode',
                    'source' => 'APP'
                ];
                break;
                
            case 'speed':
                $basePayload['payload'] = [
                    'data' => [
                        'id' => 0,
                        'level' => (int)$command['value'],
                        'type' => 'wind',
                        'mode' => 'manual'
                    ],
                    'method' => 'setLevel',
                    'source' => 'APP'
                ];
                break;
                
            default:
                throw new Exception('Unsupported command for Air Purifier: ' . $command['name']);
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($basePayload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'User-Agent: okhttp/3.12.1'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $result = json_decode($response, true);
        return (isset($result['code']) && $result['code'] === 0);
    }
}