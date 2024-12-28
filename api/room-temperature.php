<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    if (!isset($_GET['room'])) {
        throw new Exception('Room ID is required');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("SELECT temp, humidity FROM thermometers WHERE room = ? ORDER BY updated DESC LIMIT 1");
    $stmt->execute([$_GET['room']]);
    
    $tempData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tempData) {
        echo json_encode([
            'success' => true,
            'temperature' => $tempData['temp'],
            'humidity' => $tempData['humidity']
        ]);
    } else {
        throw new Exception('No temperature data found for this room');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}