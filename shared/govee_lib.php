<?php

class GoveeAPIRateLimiter {
    private $pdo;
    
    public function __construct($dbConfig) {
        $this->pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['user'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    public function logAPICall($headers) {
        // Debug log the raw headers
        error_log("Raw Headers received: " . print_r($headers, true));
        
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
    
        error_log("Processed header lines: " . print_r($headerLines, true));
    
        foreach ($headerLines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Skip the HTTP/1.1 status line
            if (strpos($line, 'HTTP/') === 0) continue;
            
            if (strpos($line, ':') === false) continue;
            
            list($name, $value) = array_map('trim', explode(':', $line, 2));
            error_log("Processing header - Name: $name, Value: $value");
            
            switch ($name) {
                case 'API-RateLimit-Remaining':
                case 'api-ratelimit-remaining':
                    $values['API-RateLimit-Remaining'] = (int)$value;
                    break;
                case 'API-RateLimit-Reset':
                case 'api-ratelimit-reset':
                    $values['API-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    break;
                case 'API-RateLimit-Limit':
                case 'api-ratelimit-limit':
                    $values['API-RateLimit-Limit'] = (int)$value;
                    break;
                case 'X-RateLimit-Limit':
                case 'x-ratelimit-limit':
                    $values['X-RateLimit-Limit'] = (int)$value;
                    break;
                case 'X-RateLimit-Remaining':
                case 'x-ratelimit-remaining':
                    $values['X-RateLimit-Remaining'] = (int)$value;
                    break;
                case 'X-RateLimit-Reset':
                case 'x-ratelimit-reset':
                    $values['X-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    break;
                case 'X-Response-Time':
                case 'x-response-time':
                    $values['X-Response-Time'] = (int)str_replace(['ms', ' '], '', $value);
                    break;
            }
        }
    
        error_log("Final parsed values: " . print_r($values, true));
    
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
    
    public function canMakeRequest() {
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
    
    public function getWaitTime() {
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
}

class GoveeCommandQueue {
    private $pdo;
    
    public function __construct($dbConfig) {
        $this->pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['user'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
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
}

class GoveeAPI {
    private $apiKey;
    private $dbConfig;
    private $rateLimiter;
    private $commandQueue;
    
    public function __construct($apiKey, $dbConfig) {
        $this->apiKey = $apiKey;
        $this->dbConfig = $dbConfig;
        
        // Initialize database connection
        $this->pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['user'],
            $dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Create devices table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS devices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device VARCHAR(255) NOT NULL UNIQUE,
                model VARCHAR(255),
                device_name VARCHAR(255),
                controllable BOOLEAN DEFAULT 1,
                retrievable BOOLEAN DEFAULT 1,
                supportCmds TEXT,
                colorTemp_rangeMin INT,
                colorTemp_rangeMax INT,
                brand VARCHAR(50),
                online BOOLEAN DEFAULT 0,
                powerState VARCHAR(10),
                brightness INT,
                colorTemp INT,
                x10Code VARCHAR(10),
                room INT,
                deviceGroup INT,
                showInGroupOnly BOOLEAN DEFAULT 0,
                low INT DEFAULT 25,
                medium INT DEFAULT 50,
                high INT DEFAULT 75,
                preferredColorTem INT
            )
        ");
        
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
        
        // Create command_queue table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS command_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device VARCHAR(255) NOT NULL,
                model VARCHAR(255),
                command TEXT,
                brand VARCHAR(50),
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                error_message TEXT
            )
        ");
    
        $this->rateLimiter = new GoveeAPIRateLimiter($dbConfig);
        $this->commandQueue = new GoveeCommandQueue($dbConfig);
    }
    
    public function getDevices() {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://developer-api.govee.com/v1/devices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => array(
                'Govee-API-Key: ' . $this->apiKey,
                'Content-Type: application/json'
            )
        ));
        
        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        $this->rateLimiter->logAPICall($headers);
        
        curl_close($curl);
        
        return [
            'headers' => $headers,
            'body' => $body,
            'statusCode' => $statusCode
        ];
    }

    public function getDeviceState($device) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://developer-api.govee.com/v1/devices/state?device=" . 
                      $device['device'] . "&model=" . $device['model'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => array(
            'Govee-API-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    $this->rateLimiter->logAPICall($headers);
    
    curl_close($curl);
    
    // Fix the status code variable name
    return [
        'headers' => $headers,
        'body' => $body,
        'statusCode' => $statusCode  // Changed from $state_status to $statusCode
    ];
}
    
    public function processBatch($maxCommands = 5) {
    global $log;  // Add this to use the logger
    
    if (!$this->rateLimiter->canMakeRequest()) {
        return [
            'success' => false,
            'message' => 'Rate limit reached, try again later'
        ];
    }
    
    $commands = $this->commandQueue->getNextBatch($maxCommands);
    $results = [];
    
    // Initialize PDO connection
    $pdo = new PDO(
        "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset=utf8mb4",
        $this->dbConfig['user'],
        $this->dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    foreach ($commands as $command) {
        try {
            $log->logInfoMsg("Processing command for device: " . $command['device']);
            
            // Decode command before sending
            $cmd = json_decode($command['command'], true);
            
            // Send the command to device
            $result = $this->sendCommand(
                $command['device'],
                $command['model'],
                $cmd
            );
            
            // Get device current state
            $deviceStmt = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
            $deviceStmt->execute([$command['device']]);
            $deviceData = $deviceStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($deviceData) {
                // Update state based on command type
                switch($cmd['name']) {
                    case 'turn':
                        $log->logInfoMsg("Updating power state to: " . $cmd['value'] . " for device: " . $command['device']);
                        $stmt = $pdo->prepare("UPDATE devices SET powerState = ?, online = 1 WHERE device = ?");
                        $stmt->execute([$cmd['value'], $command['device']]);
                        break;
                        
                    case 'brightness':
                        $brightness = intval($cmd['value']);
                        $log->logInfoMsg("Updating brightness to: " . $brightness . " for device: " . $command['device']);
                        $stmt = $pdo->prepare("UPDATE devices SET brightness = ?, powerState = 'on', online = 1 WHERE device = ?");
                        $stmt->execute([$brightness, $command['device']]);
                        break;
                }
                
                // Verify the update
                $verifyStmt = $pdo->prepare("SELECT powerState, brightness FROM devices WHERE device = ?");
                $verifyStmt->execute([$command['device']]);
                $newState = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                $log->logInfoMsg("New device state - Power: " . $newState['powerState'] . ", Brightness: " . $newState['brightness']);
            }
            
            $this->commandQueue->markCommandComplete($command['id'], true);
            $results[] = [
                'command_id' => $command['id'],
                'result' => $result,
                'success' => true
            ];
            
            // Check rate limit after each command
            if (!$this->rateLimiter->canMakeRequest()) {
                $log->logInfoMsg("Rate limit reached, stopping batch processing");
                break;
            }
            
        } catch (Exception $e) {
            $log->logErrorMsg("Error processing command: " . $e->getMessage());
            $this->commandQueue->markCommandComplete(
                $command['id'],
                false,
                $e->getMessage()
            );
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
    
    public function sendCommand($device, $model, $cmd) {
    // Validate basic parameters
    if (!$device || !$model) {
        throw new Exception('Device and model are required');
    }
    
    // Validate command structure
    if (!is_array($cmd) || !isset($cmd['name'])) {
        throw new Exception('Invalid command format');
    }
    
    // Transform command based on type
    $goveeCmd = ['name' => $cmd['name']];
    
    // Handle different command types
    switch ($cmd['name']) {
        case 'brightness':
            if (!isset($cmd['value']) || !is_numeric($cmd['value'])) {
                throw new Exception('Brightness value must be a number');
            }
            $goveeCmd['value'] = (int)$cmd['value'];
            break;
            
        case 'turn':
            if (!isset($cmd['value']) || !in_array($cmd['value'], ['on', 'off'])) {
                throw new Exception('Turn command must specify "on" or "off"');
            }
            $goveeCmd['value'] = $cmd['value'];
            break;
            
        default:
            throw new Exception('Unsupported command type: ' . $cmd['name']);
    }

    // Rate limit check
    if (!$this->rateLimiter->canMakeRequest()) {
        $waitTime = $this->rateLimiter->getWaitTime();
        if ($waitTime > 0) {
            sleep($waitTime);
        }
    }

    // Send command to Govee API
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://developer-api.govee.com/v1/devices/control',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode(array(
            'device' => $device,
            'model' => $model,
            'cmd' => $goveeCmd
        )),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Govee-API-Key: ' . $this->apiKey
        ),
    ));

    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    $this->rateLimiter->logAPICall($headers);
    
    curl_close($curl);
    
    $result = json_decode($body, true);
    
    if (!isset($result['code']) || $result['code'] !== 200) {
        throw new Exception($result['message'] ?? 'Failed to send command to device');
    }
    
    // Update device state in database after successful command
    $pdo = new PDO(
        "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset=utf8mb4",
        $this->dbConfig['user'],
        $this->dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Update state based on command type
    switch($cmd['name']) {
        case 'turn':
            $stmt = $pdo->prepare("UPDATE devices SET powerState = ? WHERE device = ?");
            $stmt->execute([$cmd['value'], $device]);
            break;
            
        case 'brightness':
            $stmt = $pdo->prepare("UPDATE devices SET brightness = ?, powerState = 'on' WHERE device = ?");
            $stmt->execute([(int)$cmd['value'], $device]);
            break;
    }
    
    return [
        'success' => true,
        'message' => 'Command sent successfully'
    ];
}

    public function updateDeviceDatabase($device) {
        $pdo = new PDO(
            "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset=utf8mb4",
            $this->dbConfig['user'],
            $this->dbConfig['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
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
            'colorTemp_rangeMin' => null,
            'colorTemp_rangeMax' => null,
            'brand' => 'govee'
        ];
        
        if (in_array('colorTem', $device['supportCmds']) && 
            isset($device['properties']) && 
            isset($device['properties']['colorTem']) && 
            isset($device['properties']['colorTem']['range'])) {
            
            $new_values['colorTemp_rangeMin'] = $device['properties']['colorTem']['range']['min'];
            $new_values['colorTemp_rangeMax'] = $device['properties']['colorTem']['range']['max'];
        }
        
        if (!$current) {
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
        }
        
        return false;
    }

    public function updateDeviceStateInDatabase($device, $device_states, $govee_device) {
    $pdo = new PDO(
        "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset=utf8mb4",
        $this->dbConfig['user'],
        $this->dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    try {
        $pdo->beginTransaction(); // Start transaction
        
        $cursor = $pdo->prepare("SELECT * FROM devices WHERE device = ?");
        $cursor->execute([$device['device']]);
        $current = $cursor->fetch(PDO::FETCH_ASSOC);
        
        // Initialize new values
        $new_values = [
            'online' => false,  // Default to false
            'powerState' => null,
            'brightness' => null
        ];
        
        // Update states from the properties array
        if (isset($device_states[$device['device']])) {
            foreach ($device_states[$device['device']] as $property) {
                if (isset($property['online'])) {
                    $new_values['online'] = $property['online'];
                }
                if (isset($property['powerState'])) {
                    $new_values['powerState'] = $property['powerState'];
                }
                if (isset($property['brightness'])) {
                    $new_values['brightness'] = $property['brightness'];
                }
            }
        }
        
        $changes = [];
        $updates = [];
        $params = [];
        
        foreach ($new_values as $key => $value) {
            if ($value === null) {
                continue;
            }
            
            if ($key === 'online') {
                $value = $value ? 1 : 0;
            }
            
            $updates[] = "$key = ?";
            $params[] = $value;
            $changes[] = "$key updated to $value";
        }
        
        if ($updates) {
            // Add device to params
            $params[] = $device['device'];
            
            $sql = "UPDATE devices SET " . implode(", ", $updates) . " WHERE device = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            global $log;
            $log->logInfoMsg("Updated Govee device {$device['device']}: " . implode(", ", $changes));
        }
        
        $pdo->commit(); // Commit the transaction
        return $device;
        
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback on error
        throw $e;
    } finally {
        $pdo = null; // Close connection
    }
}
}