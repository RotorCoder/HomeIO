<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/hue_lib.php';
require $config['sharedpath'].'/logger.php';

class HueCommandQueue {
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
            // Get next batch of pending Hue commands
            $stmt = $this->pdo->prepare("
                SELECT id, device, command 
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
}

$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

try {
    $log->logInfoMsg("Starting Hue command processor");
    $hueAPI = new HueAPI($config['hue_bridge_ip'], $config['hue_api_key']);
    $commandQueue = new HueCommandQueue($config['db_config']);
    
    // Main processing loop
    while (true) {
        try {
            // Get next batch of commands
            $commands = $commandQueue->getNextBatch(5);
            
            if (!empty($commands)) {
                $log->logInfoMsg("Processing " . count($commands) . " Hue commands");
                
                foreach ($commands as $command) {
                    try {
                        // Send the command
                        $result = $hueAPI->sendCommand(
                            $command['device'],
                            json_decode($command['command'], true)
                        );
                        
                        $commandQueue->markCommandComplete($command['id'], true);
                        $log->logInfoMsg("Command {$command['id']} executed successfully");
                        
                    } catch (Exception $e) {
                        $commandQueue->markCommandComplete(
                            $command['id'],
                            false,
                            $e->getMessage()
                        );
                        $log->logInfoMsg("Command {$command['id']} failed: " . $e->getMessage());
                    }
                }
            }
            
            // Short sleep between checks since Hue uses local API
            usleep(50000); // 50ms pause
            
        } catch (Exception $e) {
            $log->logInfoMsg("Error processing batch: " . $e->getMessage());
            // Sleep a bit longer on error to prevent rapid error loops
            sleep(1);
        }
    }
    
} catch (Exception $e) {
    $log->logInfoMsg("Fatal error: " . $e->getMessage());
    exit(1);
}
?>