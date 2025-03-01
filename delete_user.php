<?php
session_start();
require_once __DIR__ . '/config/config.php';

// Only admin users can delete users
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'You cannot delete your own account';
        header('Location: register.php');
        exit;
    }
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
            $config['db_config']['user'],
            $config['db_config']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = 'User deleted successfully';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
}

// Redirect back to the user management page
header('Location: register.php');
exit;