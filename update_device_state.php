<?php
require_once __DIR__ . '/config/config.php';
require $config['sharedpath'].'/logger.php';
$log = new logger(basename(__FILE__, '.php')."_", __DIR__);

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['device']) || !isset($data['command']) || !isset($data['value'])) {
        throw new Exception('Missing required parameters');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Update the device state based on command type
    switch($data['command']) {
        case 'turn':
            $stmt = $pdo->prepare("UPDATE devices SET powerState = ? WHERE device = ?");
            $stmt->execute([$data['value'], $data['device']]);
            break;
            
        case 'brightness':
            $stmt = $pdo->prepare("UPDATE devices SET brightness = ?, powerState = 'on' WHERE device = ?");
            $stmt->execute([(int)$data['value'], $data['device']]);
            break;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Device state updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}