<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['device'])) {
        throw new Exception('Device ID is required');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Convert empty x10Code to NULL
    $x10Code = (!empty($data['x10Code'])) ? $data['x10Code'] : null;
    
    $stmt = $pdo->prepare("UPDATE devices SET room = ?, low = ?, medium = ?, high = ?, preferredColorTem = ?, x10Code = ? WHERE device = ?");
    $stmt->execute([
        $data['room'],
        $data['low'],
        $data['medium'],
        $data['high'],
        $data['preferredColorTem'],
        $x10Code,  // This will now be NULL if empty
        $data['device']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Device configuration updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}