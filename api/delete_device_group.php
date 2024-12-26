<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['groupId'])) {
        throw new Exception('Group ID is required');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // First update all devices in the group to remove group association
        $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = NULL, showInGroupOnly = 0 WHERE deviceGroup = ?");
        $stmt->execute([$data['groupId']]);
        
        // Then delete the group
        $stmt = $pdo->prepare("DELETE FROM device_groups WHERE id = ?");
        $stmt->execute([$data['groupId']]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Group deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}