<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HueRoutes {
    private $app;
    private $config;
    private $log;
    private $pdo;
    
    public function __construct($app, $config, $log) {
        $this->app = $app;
        $this->config = $config;
        $this->log = $log;
        
        // Initialize Hue bridge configuration
        $this->bridgeIP = $config['hue_bridge_ip'];
        $this->apiKey = $config['hue_api_key'];
        
        if (empty($this->bridgeIP) || empty($this->apiKey)) {
            $this->log->logErrorMsg("Hue bridge configuration missing in config");
        }
        
        // Initialize database connection
        try {
            $this->pdo = getDatabaseConnection($config);
        } catch (Exception $e) {
            $this->log->logErrorMsg("Failed to initialize database connection: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function register() {
        $hueInstance = $this;  // Store instance reference
    
        // Update Hue devices route
        $this->app->get('/update-hue-devices', function (Request $request, Response $response) use ($hueInstance) {
            try {
                $timing = [];
                $result = measureExecutionTime(function() use ($hueInstance, &$timing) {
                    // Initialize API timing measurement
                    $api_timing = measureExecutionTime(function() use ($hueInstance) {
                        $hueResponse = $hueInstance->getDevices();
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
                    
                    // Process each device
                    $states_timing = measureExecutionTime(function() use ($api_timing, $hueInstance) {
                        $pdo = getDatabaseConnection($hueInstance->config);
                        $updated_devices = array();
                        
                        foreach ($api_timing['result']['data'] as $device) {
                            $updated_devices[] = $hueInstance->updateDeviceDatabase($device);
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
                return sendErrorResponse($response, $e, $hueInstance->log);
            }
        });
    
        // Process command queue for Hue devices
        $this->app->get('/process-hue-commands', function (Request $request, Response $response) use ($hueInstance) {
            try {
                $maxCommands = $request->getQueryParams()['max'] ?? 5;
                $result = $hueInstance->processBatch($maxCommands);
                return sendSuccessResponse($response, $result);
            } catch (Exception $e) {
                return sendErrorResponse($response, $e, $hueInstance->log);
            }
        });
    }
    
    public function getNextBatch($limit = 5) {
        $this->pdo->beginTransaction();
        try {
            // Add timeout check - reset commands stuck processing for >5 minutes
            $stmt = $this->pdo->prepare("
                UPDATE command_queue 
                SET status = 'pending',
                    processed_at = NULL
                WHERE status = 'processing' 
                AND processed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
    
            // Get next batch of pending Hue commands
            $stmt = $this->pdo->prepare("
                SELECT id, device, model, command 
                FROM command_queue
                WHERE status = 'pending'
                AND brand = 'hue'
                ORDER BY created_at ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark these commands as processing
            if (!empty($commands)) {
                $ids = array_column($commands, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->pdo->prepare("
                    UPDATE command_queue
                    SET status = 'processing',
                        processed_at = CURRENT_TIMESTAMP
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
            }
            
            $this->pdo->commit();
            return $commands;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function markCommandComplete($id, $success = true, $errorMessage = null) {
        $stmt = $this->pdo->prepare("
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

    public function getDevices() {
        global $log;
        $log->logInfoMsg("Getting all devices from Hue Bridge");
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://{$this->bridgeIP}/clip/v2/resource/light",
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
                'hue-application-key: ' . $this->apiKey
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
    
    public function processBatch($maxCommands = 5) {
        try {
            // Get database connection
            $pdo = getDatabaseConnection($this->config);
            
            // Get next batch of commands
            $pdo->beginTransaction();
            try {
                // Reset stuck commands
                $stmt = $pdo->prepare("
                    UPDATE command_queue 
                    SET status = 'pending',
                        processed_at = NULL
                    WHERE status = 'processing' 
                    AND processed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    AND brand = 'hue'
                ");
                $stmt->execute();
        
                // Get pending commands
                $stmt = $pdo->prepare("
                    SELECT id, device, model, command 
                    FROM command_queue
                    WHERE status = 'pending'
                    AND brand = 'hue'
                    ORDER BY created_at ASC
                    LIMIT :limit
                ");
                $stmt->bindValue(':limit', $maxCommands, PDO::PARAM_INT);
                $stmt->execute();
                $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Mark commands as processing
                if (!empty($commands)) {
                    $ids = array_column($commands, 'id');
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("
                        UPDATE command_queue
                        SET status = 'processing',
                            processed_at = CURRENT_TIMESTAMP
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($ids);
                }
                
                $pdo->commit();
                
                // Process commands
                $results = [];
                foreach ($commands as $command) {
                    try {
                        // Send command to Hue bridge
                        $result = $this->sendCommand(
                            $command['device'],
                            json_decode($command['command'], true)
                        );
                        
                        // Mark as complete
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
                        // Mark as failed
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
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            throw new Exception('Failed to process Hue commands: ' . $e->getMessage());
        }
    }
    
    public function sendCommand($device, $cmd) {
        // Validate basic parameters
        if (!$device) {
            throw new Exception('Device ID is required');
        }
        
        // Validate command structure
        if (!is_array($cmd) || !isset($cmd['name'])) {
            throw new Exception('Invalid command format');
        }
        
        // Transform command based on type
        $hueCmd = [];
        
        // Handle different command types
        switch ($cmd['name']) {
            case 'brightness':
                if (!isset($cmd['value']) || !is_numeric($cmd['value'])) {
                    throw new Exception('Brightness value must be a number');
                }
                $hueCmd = [
                    'on' => [
                        'on' => true
                    ],
                    'dimming' => [
                        'brightness' => (int)$cmd['value']
                    ]
                ];
                break;
                
            case 'turn':
                if (!isset($cmd['value']) || !in_array($cmd['value'], ['on', 'off'])) {
                    throw new Exception('Turn command must specify "on" or "off"');
                }
                $hueCmd = [
                    'on' => [
                        'on' => ($cmd['value'] === 'on')
                    ]
                ];
                break;
                
            default:
                throw new Exception('Unsupported command type: ' . $cmd['name']);
        }
    
        // Validate bridge IP and API key
        if (empty($this->bridgeIP) || empty($this->apiKey)) {
            throw new Exception('Hue bridge configuration missing');
        }
    
        // Set up curl with more robust error handling
        $curl = curl_init();
        
        if ($curl === false) {
            throw new Exception('Failed to initialize curl');
        }
        
        $url = "https://{$this->bridgeIP}/clip/v2/resource/light/{$device}";
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CONNECTTIMEOUT => 5, // 5 second connection timeout
            CURLOPT_TIMEOUT => 10,       // 10 second execution timeout
            CURLOPT_HTTPHEADER => array(
                'hue-application-key: ' . $this->apiKey,
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode($hueCmd)
        ));
    
        $this->log->logInfoMsg("Sending command to Hue bridge: $url");
        $this->log->logInfoMsg("Command payload: " . json_encode($hueCmd));
    
        $response = curl_exec($curl);
        
        if ($response === false) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            throw new Exception("Curl error ($errno): $error");
        }
        
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $this->log->logInfoMsg("Hue bridge response (HTTP $httpCode): $response");
        
        if ($httpCode === 0) {
            throw new Exception("Failed to connect to Hue bridge at {$this->bridgeIP}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to communicate with Hue bridge (HTTP $httpCode): $response");
        }
        
        $result = json_decode($response, true);
        
        // Check for Hue API errors
        if (is_array($result) && isset($result[0]['error'])) {
            throw new Exception($result[0]['error']['description']);
        }
        
        return [
            'success' => true,
            'message' => 'Command sent successfully'
        ];
    }

    public function updateDeviceDatabase($device) {
        if (!$this->pdo) {
            throw new Exception("Database connection not initialized");
        }
    
        global $log;
        $log->logInfoMsg("Raw Hue device data: " . json_encode($device, JSON_PRETTY_PRINT));
        
        $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE device = ?");
        $stmt->execute([$device['id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update the online status to use the device's reachable status
        $new_values = [
            'device' => $device['id'],
            'model' => $device['type'],
            'device_name' => $device['metadata']['name'],
            'controllable' => 1,
            'retrievable' => 1,
            'supportCmds' => json_encode(['brightness', 'colorTem', 'color']),
            'brand' => 'hue',
            'online' => isset($device['status']) && isset($device['status']['reachable']) ? $device['status']['reachable'] : true,
            'powerState' => $device['on']['on'] ? 'on' : 'off',
            'brightness' => isset($device['dimming']) ? round($device['dimming']['brightness']) : null,
            'colorTemp' => isset($device['color_temperature']) ? $device['color_temperature']['mirek'] : null
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
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }
            return $new_values;
        }
        
        $changes = [];
        $updates = [];
        $params = [':device' => $device['id']];
        
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
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $log->logInfoMsg("Updated Hue device {$device['id']}: " . implode(", ", $changes));
        }
        
        return $new_values;
    }
}