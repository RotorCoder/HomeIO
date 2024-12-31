<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/hue_lib.php';
require $config['sharedpath'].'/logger.php';

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
            usleep(10000); // 50ms pause
            
        } catch (Exception $e) {
            $log->logInfoMsg("Error processing batch: " . $e->getMessage());
            // Sleep a bit longer on error to prevent rapid error loops
            sleep(5);
        }
    }
    
} catch (Exception $e) {
    $log->logInfoMsg("Fatal error: " . $e->getMessage());
    exit(5);
}
?>