<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    if (!isset($_GET['model'])) {
        throw new Exception('Model parameter is required');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get existing groups for this model
    $stmt = $pdo->prepare("SELECT id, name FROM device_groups WHERE model = ?");
    $stmt->execute([$_GET['model']]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'groups' => $groups
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}