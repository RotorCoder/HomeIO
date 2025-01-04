<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VeSyncRoutes {
    private $app;
    private $config;
    private $log;
    private $last_update;
    private $update_interval = 60;  // Update device status every 60 seconds
    
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
                    
                    foreach ($api_timing['result'] as $type => $devices) {
                        foreach ($devices as $device) {
                            $vesyncInstance->updateDeviceInDatabase($pdo, $device);
                            $updated_devices[] = $device;
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
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com/cloud/v1/user/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'account' => $this->config['vesync_api']['user'],
                'password' => $this->config['vesync_api']['password']
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        $result = json_decode($response, true);
        return isset($result['result']) && $result['result']['code'] === 0;
    }

    private function getDevices() {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://smartapi.vesync.com/cloud/v1/deviceManaged/devices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        $result = json_decode($response, true);
        return isset($result['result']) && $result['result']['code'] === 0 ? $result['result']['list'] : false;
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
            'online' => $device['connectionStatus'] === 'online',
            'powerState' => $device['deviceStatus']
        ];
        
        // Add device-specific properties
        if (isset($device['details'])) {
            if (isset($device['details']['brightness'])) {
                $new_values['brightness'] = $device['details']['brightness'];
            }
            if (isset($device['details']['energy'])) {
                $new_values['energy_today'] = $device['details']['energy'];
                $new_values['power'] = $device['details']['power'];
                $new_values['voltage'] = $device['details']['voltage'];
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
        
        return $new_values;
    }

    private function processBatch($maxCommands = 5) {
        $pdo = getDatabaseConnection($this->config);
        
        // Get pending commands
        $stmt = $pdo->prepare("
            SELECT id, device, model, command 
            FROM command_queue
            WHERE status = 'pending'
            AND brand = 'vesync'
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$maxCommands]);
        $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($commands as $command) {
            try {
                $cmd = json_decode($command['command'], true);
                $result = $this->sendCommand($command['device'], $cmd);
                
                // Mark command as completed
                $stmt = $pdo->prepare("
                    UPDATE command_queue 
                    SET status = 'completed',
                        processed_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$command['id']]);
                
                $results[] = [
                    'command_id' => $command['id'],
                    'result' => $result,
                    'success' => true
                ];
            } catch (Exception $e) {
                // Mark command as failed
                $stmt = $pdo->prepare("
                    UPDATE command_queue 
                    SET status = 'failed',
                        processed_at = CURRENT_TIMESTAMP,
                        error_message = ?
                    WHERE id = ?
                ");
                $stmt->execute([$e->getMessage(), $command['id']]);
                
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
    }

    private function sendCommand($deviceId, $command) {
        if (!isset($command['name'])) {
            throw new Exception('Invalid command format');
        }

        // Process command
        try {
            $endpoint = '';
            $payload = [];
            
            switch ($command['name']) {
                case 'turn':
                    $endpoint = '/v1/device/' . $deviceId . '/status';
                    $payload = [
                        'status' => $command['value'] === 'on' ? 1 : 0
                    ];
                    break;
                    
                case 'brightness':
                    $endpoint = '/v1/device/' . $deviceId . '/brightness';
                    $payload = [
                        'brightness' => (int)$command['value']
                    ];
                    break;
                    
                default:
                    throw new Exception('Unsupported command: ' . $command['name']);
            }

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://smartapi.vesync.com/cloud' . $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode !== 200) {
                throw new Exception('Failed to send command to device');
            }

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
            }

            return [
                'success' => true,
                'message' => 'Command sent successfully'
            ];

        } catch (Exception $e) {
            throw new Exception('Failed to send command: ' . $e->getMessage());
        }
    }
}