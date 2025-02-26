<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';

$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

try {
    $log->logInfoMsg("Starting Govee command processor");
    
    // Main processing loop
    while (true) {
        try {
            // Call the API endpoint to process commands
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://rotorcoder.com/homeio/api/process-govee-commands?max=5",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $config['api_keys'][0],
                    'Content-Type: application/json'
                ]
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result['success'] && $result['processed'] > 0) {
                    $log->logInfoMsg("Processed {$result['processed']} Govee commands");
                }
            } else {
                $log->logErrorMsg("API request failed with code $httpCode: $response");
            }
            
            // Short sleep between checks
            usleep(100000); // 100ms pause
            
        } catch (Exception $e) {
            $log->logErrorMsg("Error processing batch: " . $e->getMessage());
            sleep(5);
        }
    }
    
} catch (Exception $e) {
    $log->logErrorMsg("Fatal error: " . $e->getMessage());
    exit(5);
}