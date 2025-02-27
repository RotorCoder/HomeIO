<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

// Define database connection function locally since it's not available in scripts
function getDatabaseConnection($config) {
    return new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

try {
    $log->logInfoMsg("Starting device state synchronizer");
    
    // Main processing loop
    while (true) {
        try {
            $pdo = getDatabaseConnection($config);
            
            // Find all devices where current state doesn't match preferred state
            // and there are no pending or processing commands for the device
            $stmt = $pdo->prepare("
                UPDATE devices d
                LEFT JOIN (
                    SELECT device 
                    FROM command_queue 
                    WHERE status IN ('pending', 'processing')
                ) q ON d.device = q.device
                SET 
                    d.preferredPowerState = d.powerState,
                    d.preferredBrightness = d.brightness,
                    d.preferredColorTem = d.colorTemp
                WHERE 
                    q.device IS NULL
                    AND (
                        d.preferredPowerState != d.powerState
                        OR (d.preferredBrightness IS NOT NULL AND d.brightness IS NOT NULL AND d.preferredBrightness != d.brightness)
                        OR (d.preferredColorTem IS NOT NULL AND d.colorTemp IS NOT NULL AND d.preferredColorTem != d.colorTemp)
                    )
            ");
            
            $stmt->execute();
            $updatedRows = $stmt->rowCount();
            
            if ($updatedRows > 0) {
                $log->logInfoMsg("Synchronized $updatedRows devices' preferred states with actual states");
            }
            
            // Sleep for 1 seconds before next check
            sleep(1);
            
        } catch (Exception $e) {
            $log->logErrorMsg("Error synchronizing states: " . $e->getMessage());
            sleep(10); // Sleep longer on error to prevent rapid error loops
        }
    }
    
} catch (Exception $e) {
    $log->logErrorMsg("Fatal error: " . $e->getMessage());
    exit(5);
}