<?php
/**
 * This script initializes the authentication system by creating the users table
 * and adding a default admin user. Run this script once to set up authentication.
 */

require_once __DIR__ . '/config/config.php';

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create users table
    $userTableSql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    );";
    
    $pdo->exec($userTableSql);
    echo "Created users table.<br>";
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    $hasAdmin = (int)$stmt->fetchColumn();
    
    if (!$hasAdmin) {
        // Create default admin user (password: admin)
        $hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)");
        $stmt->execute(['admin', $hashedPassword]);
        echo "Created default admin user.<br>";
        echo "Username: admin<br>";
        echo "Password: admin<br>";
        echo "<strong>Important:</strong> Change this password immediately after first login!<br>";
    } else {
        echo "Admin user already exists.<br>";
    }
    
    echo "<br>Authentication setup complete.<br>";
    echo "<a href='login.php'>Go to login page</a>";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}