<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    if (!isset($_GET['device'])) {
        throw new Exception('Device ID is required');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("SELECT room, low, medium, high, preferredColorTem, x10Code FROM devices WHERE device = ?");
    $stmt->execute([$_GET['device']]);
    
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo json_encode([
            'success' => true,
            'room' => $config['room'],
            'low' => $config['low'],
            'medium' => $config['medium'],
            'high' => $config['high'],
            'preferredColorTem' => $config['preferredColorTem'],
            'x10Code' => $config['x10Code']
        ]);
    } else {
        throw new Exception('Device not found');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}