<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';
require $config['sharedpath'].'/logger.php';
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

try {
    // Get the request body
    $requestBody = file_get_contents('php://input');
    if (!$requestBody) {
        throw new Exception('No request body received');
    }

    $data = json_decode($requestBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON in request body: ' . json_last_error_msg());
    }
    
    if (!isset($data['device']) || !isset($data['model']) || !isset($data['cmd']) || !isset($data['brand'])) {
        throw new Exception('Missing required parameters: device, model, cmd, or brand');
    }

    // Connect to database
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Insert into unified command queue
    $stmt = $pdo->prepare("
        INSERT INTO command_queue 
        (device, model, command, brand) 
        VALUES 
        (:device, :model, :command, :brand)
    ");

    $stmt->execute([
        'device' => $data['device'],
        'model' => $data['model'],
        'command' => json_encode($data['cmd']),
        'brand' => $data['brand']
    ]);

    $commandId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Command queued successfully',
        'command_id' => $commandId
    ]);

} catch (Exception $e) {
    $log->logInfoMsg("Error in send_command: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}