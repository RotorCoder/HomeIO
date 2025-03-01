<?php
// api-proxy.php
// This file serves as a proxy between the client and your APIs

require_once __DIR__ . '/config/config.php';

// CORS headers to allow requests from your domain
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the API endpoint from the URL
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

if (empty($endpoint)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing endpoint parameter']);
    exit;
}

// Construct the full API URL 
$baseApiUrl = 'https://rotorcoder.com/homeio/api/'; // Replace with your actual API base URL
$url = $baseApiUrl . $endpoint;

// Setup cURL session
$ch = curl_init();

// Set method and get request data
$method = $_SERVER['REQUEST_METHOD'];
$data = null;

if ($method === 'POST' || $method === 'PUT') {
    $data = file_get_contents('php://input');
}

// Setup cURL options
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ' . $config['homeio_api_key'] // Add API key from server-side config
    ]
]);

// Add data for POST or PUT requests
if ($data && ($method === 'POST' || $method === 'PUT')) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
}

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Curl error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Return the response with the same status code
http_response_code($httpCode);
echo $response;