<?php
/**
 * This script tests direct API access to help diagnose connection issues
 */
require_once __DIR__ . '/config/config.php';

// Set content type for easier reading in browser
header('Content-Type: text/html');
echo "<h1>HomeIO API Path Test</h1>";

// Test API endpoints to diagnose path issues
$endpoints = [
    'homeio/api/rooms',
    'api/rooms',
    '/homeio/api/rooms',
    '/api/rooms',
    // Full URL tests - replace with your actual domain
    'http://localhost/homeio/api/rooms',
    'http://127.0.0.1/homeio/api/rooms',
    'http://rotorcoder.com/homeio/api/rooms'
];

// Add API key to request
$apiKey = $config['homeio_api_key'] ?? 'no-api-key-found';

echo "<h2>API Key Found: " . ($apiKey !== 'no-api-key-found' ? "Yes" : "No") . "</h2>";
echo "<p>Testing various API endpoint paths to find the correct one:</p>";
echo "<ul>";

foreach ($endpoints as $endpoint) {
    // Prepare cURL request
    $curl = curl_init();
    
    // Configure cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey],
        CURLOPT_TIMEOUT => 5
    ]);
    
    // Execute the API request
    $response = curl_exec($curl);
    $info = curl_getinfo($curl);
    $httpCode = $info['http_code'];
    $error = curl_error($curl);
    
    echo "<li>";
    echo "<strong>Testing: {$endpoint}</strong><br>";
    echo "HTTP Code: {$httpCode}<br>";
    
    if ($error) {
        echo "Error: {$error}<br>";
    } else {
        // Check if response looks like JSON
        $isJson = json_decode($response) !== null;
        echo "JSON Response: " . ($isJson ? "Yes" : "No") . "<br>";
        
        if ($isJson) {
            echo "Response Preview: <pre>" . htmlspecialchars(substr($response, 0, 100)) . "...</pre>";
            echo "<strong style='color:green'>✓ THIS PATH WORKS!</strong>";
        } else {
            echo "Response Preview: <pre>" . htmlspecialchars(substr($response, 0, 100)) . "...</pre>";
        }
    }
    
    echo "</li>";
    echo "<hr>";
    
    curl_close($curl);
}

echo "</ul>";
echo "<h2>Server Information</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script Filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Unknown') . "\n";
echo "</pre>";