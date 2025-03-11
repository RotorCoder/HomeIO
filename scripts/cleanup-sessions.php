<?php
// cleanup-sessions.php - Run this via cron daily
require_once __DIR__ . '/config/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Delete expired sessions 
    $stmt = $pdo->prepare("
        DELETE FROM user_sessions 
        WHERE expires_at < NOW() OR 
              (is_active = 0 AND last_active_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->execute();
    
    echo "Deleted " . $stmt->rowCount() . " expired sessions.\n";
    
} catch (PDOException $e) {
    echo "Error cleaning up sessions: " . $e->getMessage() . "\n";
}