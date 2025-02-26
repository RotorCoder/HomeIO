<?php
require_once __DIR__ . '/../config/config.php';
require $config['sharedpath'].'/logger.php';
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

try {
    $log->logInfoMsg("Starting Govee device updater");
    
    // Main processing loop
    while (true) {
        try {
            // Call the API endpoint to update devices
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://rotorcoder.com/homeio/api/update-govee-devices",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $config['api_keys'][0],
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
                    $log->logInfoMsg("Updated $deviceCount Govee devices. API timing: {$result['timing']['devices']['duration']}ms, DB timing: {$result['timing']['states']['duration']}ms");
                }
            } else {
                $log->logErrorMsg("API request failed with code $httpCode: $response");
            }
            
            // Sleep for 300 seconds before next update
            sleep(300);
            
        } catch (Exception $e) {
            $log->logErrorMsg("Error updating devices: " . $e->getMessage());
            sleep(5); // Sleep longer on error to prevent rapid error loops
        }
    }
    
} catch (Exception $e) {
    $log->logErrorMsg("Fatal error: " . $e->getMessage());
    exit(5);
}