<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GoveeRoutes {
    private $app;
    private $config;
    private $log;
    private $pdo;
    
    public function __construct($app, $config, $log) {
        $this->app = $app;
        $this->config = $config;
        $this->log = $log;
        
        // Initialize Govee API configuration
        if (empty($this->config['govee_api_key']) || empty($this->config['govee_api_url'])) {
            $this->log->logErrorMsg("Govee API configuration missing in config");
        }
        
        // Initialize database connection
        try {
            $this->pdo = getDatabaseConnection($config);
            
            // Create govee_api_calls table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS govee_api_calls (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    `API-RateLimit-Remaining` INT,
                    `API-RateLimit-Reset` DATETIME,
                    `API-RateLimit-Limit` INT,
                    `X-RateLimit-Limit` INT,
                    `X-RateLimit-Remaining` INT,
                    `X-RateLimit-Reset` DATETIME,
                    `X-Response-Time` INT,
                    `Date` DATETIME
                )
            ");
        } catch (Exception $e) {
            $this->log->logErrorMsg("Failed to initialize database connection: " . $e->getMessage());
            throw $e;
        }
    }

    private function logAPICall($headers) {
        // Parse all known rate limit headers
        $values = [
            'API-RateLimit-Remaining' => null,
            'API-RateLimit-Reset' => null,
            'API-RateLimit-Limit' => null,
            'X-RateLimit-Limit' => null,
            'X-RateLimit-Remaining' => null,
            'X-RateLimit-Reset' => null,
            'X-Response-Time' => null
        ];
    
        // If headers is a string, split it into lines
        if (is_string($headers)) {
            $headerLines = array_filter(explode("\r\n", $headers));
        } else {
            $headerLines = $headers;
        }
    
        foreach ($headerLines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Skip the HTTP/1.1 status line
            if (strpos($line, 'HTTP/') === 0) continue;
            
            if (strpos($line, ':') === false) continue;
            
            list($name, $value) = array_map('trim', explode(':', $line, 2));
            
            switch (strtolower($name)) {
                case 'api-ratelimit-remaining':
                    $values['API-RateLimit-Remaining'] = (int)$value;
                    break;
                case 'api-ratelimit-reset':
                    $values['API-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    break;
                case 'api-ratelimit-limit':
                    $values['API-RateLimit-Limit'] = (int)$value;
                    break;
                case 'x-ratelimit-limit':
                    $values['X-RateLimit-Limit'] = (int)$value;
                    break;
                case 'x-ratelimit-remaining':
                    $values['X-RateLimit-Remaining'] = (int)$value;
                    break;
                case 'x-ratelimit-reset':
                    $values['X-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    break;
                case 'x-response-time':
                    $values['X-Response-Time'] = (int)str_replace(['ms', ' '], '', $value);
                    break;
            }
        }
    
        // Insert into database
        $sql = "INSERT INTO govee_api_calls (
            `API-RateLimit-Remaining`,
            `API-RateLimit-Reset`,
            `API-RateLimit-Limit`,
            `X-RateLimit-Limit`,
            `X-RateLimit-Remaining`,
            `X-RateLimit-Reset`,
            `X-Response-Time`,
            `Date`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $values['API-RateLimit-Remaining'],
            $values['API-RateLimit-Reset'],
            $values['API-RateLimit-Limit'],
            $values['X-RateLimit-Limit'],
            $values['X-RateLimit-Remaining'],
            $values['X-RateLimit-Reset'],
            $values['X-Response-Time']
        ]);
        
        return $values;
    }
    
    private function canMakeRequest() {
        // Get the most recent rate limit information
        $stmt = $this->pdo->query("
            SELECT 
                `API-RateLimit-Remaining`,
                `API-RateLimit-Reset`,
                `X-RateLimit-Remaining`,
                `X-RateLimit-Reset`
            FROM govee_api_calls
            ORDER BY id DESC
            LIMIT 1
        ");
        
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$limits) {
            return true; // If no limits are recorded, allow the request
        }
        
        $now = time();
        
        // Check API rate limit
        if ($limits['API-RateLimit-Remaining'] !== null) {
            $resetTime = strtotime($limits['API-RateLimit-Reset']);
            if ($now < $resetTime && $limits['API-RateLimit-Remaining'] <= 0) {
                return false;
            }
        }
        
        // Check X rate limit
        if ($limits['X-RateLimit-Remaining'] !== null) {
            $resetTime = strtotime($limits['X-RateLimit-Reset']);
            if ($now < $resetTime && $limits['X-RateLimit-Remaining'] <= 0) {
                return false;
            }
        }
        
        return true;
    }
    
    private function getWaitTime() {
        $stmt = $this->pdo->query("
            SELECT 
                `API-RateLimit-Reset`,
                `X-RateLimit-Reset`
            FROM govee_api_calls
            WHERE `API-RateLimit-Remaining` = 0 
            OR `X-RateLimit-Remaining` = 0
            ORDER BY `Date` DESC
            LIMIT 1
        ");
        
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$limits) {
            return 0;
        }
        
        $now = time();
        $waitTime = 0;
        
        if ($limits['API-RateLimit-Reset']) {
            $resetTime = strtotime($limits['API-RateLimit-Reset']);
            if ($resetTime > $now) {
                $waitTime = max($waitTime, $resetTime - $now);
            }
        }
        
        if ($limits['X-RateLimit-Reset']) {
            $resetTime = strtotime($limits['X-RateLimit-Reset']);
            if ($resetTime > $now) {
                $waitTime = max($waitTime, $resetTime - $now);
            }
        }
        
        return $waitTime;
    }

    public function register() {
        $goveeInstance = $this;  // Store instance reference
    
        // Update Govee devices route
        $this->app->get('/update-govee-devices', function (Request $request, Response $response) use ($goveeInstance) {
            try {
                $timing = [];
                $result = measureExecutionTime(function() use ($goveeInstance, &$timing) {
                    // Initialize API timing measurement
                    $api_timing = measureExecutionTime(function() use ($goveeInstance) {
                        $goveeResponse = $goveeInstance->getDevices();
                        if ($goveeResponse['statusCode'] !== 200) {
                            throw new Exception('Failed to get devices from Govee API');
                        }
                        
                        $devices = json_decode($goveeResponse['body'], true);
                        if (!$devices || !isset($devices['data']['devices'])) {
                            throw new Exception('Failed to parse Govee API response');
                        }
                        
                        return $devices['data']['devices'];
                    });
                    $timing['devices'] = ['duration' => $api_timing['duration']];
                    
                    // Process each device
                    $states_timing = measureExecutionTime(function() use ($api_timing, $goveeInstance) {
                        $updated_devices = array();
                        
                        foreach ($api_timing['result'] as $device) {
                            $updated_devices[] = $goveeInstance->updateDeviceDatabase($device);
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
                return sendErrorResponse($response, $e, $goveeInstance->log);
            }
        });
    
        // Process command queue for Govee devices
        $this->app->get('/process-govee-commands', function (Request $request, Response $response) use ($goveeInstance) {
            try {
                $maxCommands = $request->getQueryParams()['max'] ?? 5;
                $result = $goveeInstance->processBatch($maxCommands);
                return sendSuccessResponse($response, $result);
            } catch (Exception $e) {
                return sendErrorResponse($response, $e, $goveeInstance->log);
            }
        });
    }

    public function getDevices() {
        $this->log->logInfoMsg("Getting all devices from Govee API");
        
        if (!$this->canMakeRequest()) {
            throw new Exception("Rate limit reached. Please try again later.");
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://developer-api.govee.com/v1/devices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Govee-API-Key: ' . $this->config['govee_api_key'],
                'Content-Type: application/json'
            )
        ));
        
        $response = curl_exec($curl);
        
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception('Failed to connect to Govee API: ' . $error);
        }
        
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        // Log API rate limits
        $this->logAPICall($headers);
        
        return [
            'body' => $body,
            'statusCode' => $statusCode
        ];
    }

    public function getDeviceState($device) {
        //$this->log->logInfoMsg("Getting state for Govee device: {$device['device']}");
        
        if (!$this->canMakeRequest()) {
            throw new Exception("Rate limit reached. Please try again later.");
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://developer-api.govee.com/v1/devices/state?device=' . 
                          urlencode($device['device']) . "&model=" . urlencode($device['model']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Govee-API-Key: ' . $this->config['govee_api_key'],
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception('Failed to get device state: ' . $error);
        }
        
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        // Log API rate limits
        $this->logAPICall($headers);
        
        if ($statusCode !== 200) {
            throw new Exception("Failed to get device state (HTTP $statusCode)");
        }
        
        $result = json_decode($body, true);
        return $result['data']['properties'] ?? null;
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
    
            // Get next batch of pending Govee commands
            $stmt = $this->pdo->prepare("
                SELECT id, device, model, command 
                FROM command_queue
                WHERE status = 'pending'
                AND brand = 'govee'
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

    public function processBatch($maxCommands = 5) {
        try {
            if (!$this->canMakeRequest()) {
                return [
                    'success' => false,
                    'message' => 'Rate limit reached, try again later'
                ];
            }
            
            // Get next batch of commands
            $commands = $this->getNextBatch($maxCommands);
            
            // Process commands
            $results = [];
            foreach ($commands as $command) {
                try {
                    // Send command to Govee API
                    $result = $this->sendCommand(
                        $command['device'],
                        $command['model'],
                        json_decode($command['command'], true)
                    );
                    
                    $this->markCommandComplete($command['id'], true);
                    
                    $results[] = [
                        'command_id' => $command['id'],
                        'result' => $result,
                        'success' => true
                    ];
                    
                    // Check rate limit after each command
                    if (!$this->canMakeRequest()) {
                        $this->log->logInfoMsg("Rate limit reached, stopping batch processing");
                        break;
                    }
                    
                } catch (Exception $e) {
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
            throw new Exception('Failed to process Govee commands: ' . $e->getMessage());
        }
    }

    public function sendCommand($device, $model, $cmd) {
        $this->log->logInfoMsg("Sending command to Govee API for device: $device");
        
        // Validate basic parameters
        if (!$device || !$model) {
            throw new Exception('Device and model are required');
        }
        
        // Validate command structure
        if (!is_array($cmd) || !isset($cmd['name'])) {
            throw new Exception('Invalid command format');
        }
        
        // Transform command based on type
        $goveeCmd = [];
        
        switch ($cmd['name']) {
            case 'brightness':
                if (!isset($cmd['value']) || !is_numeric($cmd['value'])) {
                    throw new Exception('Brightness value must be a number');
                }
                $goveeCmd = [
                    'name' => 'brightness',
                    'value' => (int)$cmd['value']
                ];
                break;
                
            case 'turn':
                if (!isset($cmd['value']) || !in_array($cmd['value'], ['on', 'off'])) {
                    throw new Exception('Turn command must specify "on" or "off"');
                }
                $goveeCmd = [
                    'name' => 'turn',
                    'value' => $cmd['value']
                ];
                break;
                
            default:
                throw new Exception('Unsupported command type: ' . $cmd['name']);
        }
        
        if (!$this->canMakeRequest()) {
            $waitTime = $this->getWaitTime();
            if ($waitTime > 0) {
                sleep($waitTime);
            }
        }
        
        $curl = curl_init();
        
        if ($curl === false) {
            throw new Exception('Failed to initialize curl');
        }
        
        $payload = json_encode([
            'device' => $device,
            'model' => $model,
            'cmd' => $goveeCmd
        ]);

        //$this->log->logInfoMsg("Command payload: " . $payload);
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://developer-api.govee.com/v1/devices/control',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Govee-API-Key: ' . $this->config['govee_api_key'],
                'Content-Type: application/json'
            )
        ));
        
        $response = curl_exec($curl);
        
        if ($response === false) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            throw new Exception("Curl error ($errno): $error");
        }
        
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        //$this->log->logInfoMsg("Govee API response (HTTP $httpCode): $body");
        
        // Log API rate limits
        $this->logAPICall($headers);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to communicate with Govee API (HTTP $httpCode): $body");
        }
        
        $result = json_decode($body, true);
        
        // Check for Govee API errors
        if (isset($result['code']) && $result['code'] !== 200) {
            throw new Exception($result['message'] ?? 'Failed to send command to device');
        }
        
        try {
            // Update device state in database
            $stmt = $this->pdo->prepare("
                UPDATE devices 
                SET powerState = CASE 
                        WHEN :cmd_name = 'turn' THEN :cmd_value 
                        WHEN :cmd_name = 'brightness' THEN 'on'
                        ELSE powerState 
                    END,
                    brightness = CASE 
                        WHEN :cmd_name = 'brightness' THEN :brightness_value
                        ELSE brightness 
                    END,
                    online = 1
                WHERE device = :device
            ");
            
            $stmt->execute([
                'cmd_name' => $cmd['name'],
                'cmd_value' => $cmd['name'] === 'turn' ? $cmd['value'] : null,
                'brightness_value' => $cmd['name'] === 'brightness' ? (int)$cmd['value'] : null,
                'device' => $device
            ]);
            
        } catch (Exception $e) {
            $this->log->logErrorMsg("Failed to update device state in database: " . $e->getMessage());
            // Don't throw here as the command was successful
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
    
        //$this->log->logInfoMsg("Raw Govee device data: " . json_encode($device, JSON_PRETTY_PRINT));
        
        try {
            // Get device state separately since Govee requires individual calls
            $state = $this->getDeviceState($device);
        } catch (Exception $e) {
            $this->log->logErrorMsg("Failed to get state for device {$device['device']}: " . $e->getMessage());
            $state = null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE device = ?");
        $stmt->execute([$device['device']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // CHANGE HERE: Use the actual supportCmds from the device data instead of hardcoding
        $supportCmds = [];
        if (isset($device['supportCmds']) && is_array($device['supportCmds'])) {
            $supportCmds = $device['supportCmds'];
        }
        
        $new_values = [
            'device' => $device['device'],
            'model' => $device['model'],
            'device_name' => $device['deviceName'],
            'controllable' => 1,
            'retrievable' => 1,
            'supportCmds' => json_encode($supportCmds),  // Use the actual supportCmds
            'brand' => 'govee',
            'online' => 1,  // Govee API only returns online devices
            'powerState' => $state['powerState'] ?? null,
            'brightness' => $state['brightness'] ?? null
        ];
        
        if (!$current) {
            $this->log->logInfoMsg("New Govee device detected: {$device['device']}");
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
                $changes[] = "$key changed from $current_value to $new_value";
                $updates[] = "$key = :$key";
                $params[":$key"] = $key === 'online' ? ($value ? 1 : 0) : $value;
            }
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = :device";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->log->logInfoMsg("Updated Govee device {$device['device']}: " . implode(", ", $changes));
        }
        
        return $new_values;
    }
}