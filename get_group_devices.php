<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';

try {
    if (!isset($_GET['groupId'])) {
        throw new Exception('Group ID is required');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get all devices in the group
    $stmt = $pdo->prepare("SELECT device, powerState, online FROM devices WHERE deviceGroup = ?");
    $stmt->execute([$_GET['groupId']]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'devices' => $devices
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}