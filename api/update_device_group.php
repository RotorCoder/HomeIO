<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['device']) || !isset($data['action'])) {
        throw new Exception('Missing required parameters');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    if ($data['action'] === 'create') {
        if (!isset($data['groupName']) || !isset($data['model'])) {
            throw new Exception('Group name and model required for new group');
        }
        
        // Create new group
        $stmt = $pdo->prepare("INSERT INTO device_groups (name, model, reference_device) VALUES (?, ?, ?)");
        $stmt->execute([$data['groupName'], $data['model'], $data['device']]);
        $groupId = $pdo->lastInsertId();
        
        // Add device to group
        $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = ?, showInGroupOnly = 0 WHERE device = ?");
        $stmt->execute([$groupId, $data['device']]);
        
    } else if ($data['action'] === 'join') {
        if (!isset($data['groupId'])) {
            throw new Exception('Group ID required to join existing group');
        }
        
        // Add device to existing group
        $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = ?, showInGroupOnly = 1 WHERE device = ?");
        $stmt->execute([$data['groupId'], $data['device']]);
        
    } else if ($data['action'] === 'leave') {
        // Remove device from group
        $stmt = $pdo->prepare("UPDATE devices SET deviceGroup = NULL, showInGroupOnly = 0 WHERE device = ?");
        $stmt->execute([$data['device']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Device group updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}