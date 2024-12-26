<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/govee_lib.php';
require $config['sharedpath'].'/logger.php';
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

try {
    $log->logInfoMsg("Starting Govee command processor");
    $goveeAPI = new GoveeAPI($config['govee_api_key'], $config['db_config']);
    
    // Main processing loop
    while (true) {
        try {
            // Try to process a batch of commands
            $result = $goveeAPI->processBatch(5);
            
            if ($result['success']) {
                if ($result['processed'] > 0) {
                    $log->logInfoMsg("Processed {$result['processed']} commands");
                    foreach ($result['results'] as $cmdResult) {
                        if ($cmdResult['success']) {
                            $log->logInfoMsg("Command {$cmdResult['command_id']} executed successfully");
                        } else {
                            $log->logInfoMsg("Command {$cmdResult['command_id']} failed: {$cmdResult['error']}");
                        }
                    }
                }
            } else {
                // If we hit rate limit, log it and wait
                $log->logInfoMsg("Rate limit reached: {$result['message']}");
            }
            
            // Sleep for a short interval before checking for new commands
            // Adjust this value based on your needs
            usleep(50000); // 50ms pause between checks
            
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