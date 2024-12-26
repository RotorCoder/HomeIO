<?php
// govee_lib.php

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
        
        // Parse headers string
        foreach (explode("\n", $headers) as $line) {
            if (empty(trim($line))) continue;
            
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) continue;
            
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            
            switch ($name) {
                case 'API-RateLimit-Remaining':
                    $values['API-RateLimit-Remaining'] = (int)$value;
                    break;
                case 'API-RateLimit-Reset':
                    $values['API-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    break;
                case 'API-RateLimit-Limit':
                    $values['API-RateLimit-Limit'] = (int)$value;
                    break;
                case 'X-RateLimit-Limit':
                    $values['X-RateLimit-Limit'] = (int)$value;
                    break;
                case 'X-RateLimit-Remaining':
                    $values['X-RateLimit-Remaining'] = (int)$value;
                    break;
                case 'X-RateLimit-Reset':
                    $values['X-RateLimit-Reset'] = date('Y-m-d H:i:s', (int)$value);
                    break;
                case 'X-Response-Time':
                    $values['X-Response-Time'] = (int)str_replace('ms', '', $value);
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
        $this->rateLimiter = new GoveeAPIRateLimiter($dbConfig);
        $this->commandQueue = new GoveeCommandQueue($dbConfig);
    }
    
    public function processBatch($maxCommands = 5) {
        if (!$this->rateLimiter->canMakeRequest()) {
            return [
                'success' => false,
                'message' => 'Rate limit reached, try again later'
            ];
        }
        
        $commands = $this->commandQueue->getNextBatch($maxCommands);
        $results = [];
        
        foreach ($commands as $command) {
            try {
                // Send the command
                $result = $this->sendCommand(
                    $command['device'],
                    $command['model'],
                    json_decode($command['command'], true)
                );
                
                $this->commandQueue->markCommandComplete($command['id'], true);
                $results[] = [
                    'command_id' => $command['id'],
                    'result' => $result,
                    'success' => true
                ];
                
                // Check rate limit after each command
                if (!$this->rateLimiter->canMakeRequest()) {
                    break;
                }
                
            } catch (Exception $e) {
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
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($curl);
    
    // Log rate limit headers
    $this->rateLimiter->logAPICall($headers);
    
    $result = json_decode($body, true);
    
    if (!isset($result['code']) || $result['code'] !== 200) {
        throw new Exception($result['message'] ?? 'Failed to send command to device');
    }
    
    return [
        'success' => true,
        'message' => 'Command sent successfully'
    ];
}
}