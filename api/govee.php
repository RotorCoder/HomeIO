<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GoveeRoutes {
    private $app;
    private $config;
    private $log;
    
    public function __construct($app, $config, $log) {
        $this->app = $app;
        $this->config = $config;
        $this->log = $log;
    }

    public function register() {
        $this->app->get('/update-govee-devices', [$this, 'handleUpdateDevices']);
        $this->app->get('/process-govee-commands', [$this, 'handleProcessCommands']);
    }

    public function handleUpdateDevices(Request $request, Response $response) {
        try {
            $devices = $this->getDevicesFromAPI();
            $states = $this->updateDeviceStates($devices);
            
            return sendSuccessResponse($response, [
                'devices' => $states,
                'updated' => date('c')
            ]);
        } catch (Exception $e) {
            return sendErrorResponse($response, $e, $this->log);
        }
    }

    public function handleProcessCommands(Request $request, Response $response) {
        try {
            $maxCommands = $request->getQueryParams()['max'] ?? 5;
            $processed = $this->processPendingCommands($maxCommands);
            
            return sendSuccessResponse($response, [
                'processed' => count($processed),
                'results' => $processed
            ]);
        } catch (Exception $e) {
            return sendErrorResponse($response, $e, $this->log);
        }
    }

    private function getDevicesFromAPI() {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->config['govee_api_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Govee-API-Key: ' . $this->config['govee_api_key'],
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($statusCode !== 200 || !$response) {
            throw new Exception('Failed to get devices from Govee API');
        }
        
        $result = json_decode($response, true);
        return $result['data']['devices'] ?? [];
    }

    private function updateDeviceStates($devices) {
        $pdo = getDatabaseConnection($this->config);
        $updatedDevices = [];
        
        foreach ($devices as $device) {
            $this->updateDeviceInDB($pdo, $device);
            $state = $this->getDeviceState($device);
            if ($state) {
                $this->updateStateInDB($pdo, $device['device'], $state);
                $updatedDevices[] = array_merge($device, ['state' => $state]);
            }
        }
        
        return $updatedDevices;
    }

    private function updateDeviceInDB($pdo, $device) {
        $stmt = $pdo->prepare("
            INSERT INTO devices (device, model, device_name, brand, online)
            VALUES (?, ?, ?, 'govee', 1)
            ON DUPLICATE KEY UPDATE
            model = VALUES(model),
            device_name = VALUES(device_name),
            online = VALUES(online)
        ");
        
        $stmt->execute([
            $device['device'],
            $device['model'],
            $device['deviceName']
        ]);
    }

    private function getDeviceState($device) {
        $curl = curl_init();
        $url = $this->config['govee_api_url'] . '/state';
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Govee-API-Key: ' . $this->config['govee_api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'device' => $device['device'],
                'model' => $device['model']
            ])
        ]);
        
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($statusCode === 200 && $response) {
            $result = json_decode($response, true);
            return $result['data']['properties'] ?? null;
        }
        return null;
    }

    private function updateStateInDB($pdo, $deviceId, $state) {
        $updates = [];
        $values = [];
        
        if (isset($state['powerState'])) {
            $updates[] = "powerState = ?";
            $values[] = $state['powerState'];
        }
        if (isset($state['brightness'])) {
            $updates[] = "brightness = ?";
            $values[] = $state['brightness'];
        }
        
        if ($updates) {
            $values[] = $deviceId;
            $stmt = $pdo->prepare("UPDATE devices SET " . implode(',', $updates) . " WHERE device = ?");
            $stmt->execute($values);
        }
    }

    private function processPendingCommands($maxCommands) {
        $pdo = getDatabaseConnection($this->config);
        $stmt = $pdo->prepare("
            SELECT id, device, model, command 
            FROM command_queue
            WHERE status = 'pending'
            AND brand = 'govee'
            LIMIT ?
        ");
        
        $stmt->execute([$maxCommands]);
        $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        
        foreach ($commands as $command) {
            try {
                $success = $this->executeCommand($command);
                $this->markCommandComplete($pdo, $command['id']);
                $results[] = [
                    'command_id' => $command['id'],
                    'success' => true
                ];
            } catch (Exception $e) {
                $this->markCommandFailed($pdo, $command['id'], $e->getMessage());
                $results[] = [
                    'command_id' => $command['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    private function executeCommand($command) {
        $cmd = json_decode($command['command'], true);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->config['govee_api_url'] . '/control',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Govee-API-Key: ' . $this->config['govee_api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'device' => $command['device'],
                'model' => $command['model'],
                'cmd' => $cmd
            ])
        ]);
        
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($statusCode !== 200) {
            throw new Exception('Failed to send command');
        }
        
        return true;
    }

    private function markCommandComplete($pdo, $commandId) {
        $stmt = $pdo->prepare("
            UPDATE command_queue 
            SET status = 'completed',
                processed_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$commandId]);
    }

    private function markCommandFailed($pdo, $commandId, $error) {
        $stmt = $pdo->prepare("
            UPDATE command_queue 
            SET status = 'failed',
                processed_at = CURRENT_TIMESTAMP,
                error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$error, $commandId]);
    }
}