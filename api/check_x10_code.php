<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    if (!isset($_GET['x10Code'])) {
        throw new Exception('X10 code is required');
    }
    
    $x10Code = $_GET['x10Code'];
    $currentDevice = $_GET['currentDevice'] ?? null;
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check if X10 code exists for any other device
    $sql = "SELECT device, device_name FROM devices WHERE x10Code = ? AND device != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$x10Code, $currentDevice]);
    $existingDevice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingDevice) {
        echo json_encode([
            'success' => true,
            'isDuplicate' => true,
            'deviceName' => $existingDevice['device_name'],
            'device' => $existingDevice['device']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'isDuplicate' => false
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}