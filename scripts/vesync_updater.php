<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

try {
    $log->logInfoMsg("Starting VeSync device updater");
    
    // Main processing loop
    while (true) {
        try {
            // Call the API endpoint to update VeSync devices
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://rotorcoder.com/homeio/api/update-vesync-devices",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $config['homeio_api_key'],
                    'Content-Type: application/json'
                ]
            ]);
            
            $response = curl_exec($curl);
            if ($response === false) {
                $error = curl_error($curl);
                $log->logErrorMsg("Curl error: " . $error);
            }
            
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result['success']) {
                    $devices = $result['devices'] ?? [];
                    $deviceCount = count($devices);
                    $log->logInfoMsg("Updated $deviceCount VeSync devices. API timing: {$result['timing']['devices']['duration']}ms, DB timing: {$result['timing']['states']['duration']}ms");
                }
            } else {
                $log->logErrorMsg("API request failed with code $httpCode: $response");
            }
            
            // Sleep for 300 seconds (5 minutes) before next update
            sleep(300);
            
        } catch (Exception $e) {
            $log->logErrorMsg("Error updating VeSync devices: " . $e->getMessage());
            sleep(30); // Sleep longer on error to prevent rapid error loops
        }
    }
    
} catch (Exception $e) {
    $log->logErrorMsg("Fatal error: " . $e->getMessage());
    exit(5);
}