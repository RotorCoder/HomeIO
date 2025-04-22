<?php

// verify-session.php

session_start();

// Get JSON data from request
$inputData = json_decode(file_get_contents('php://input'), true);

// Prepare response
$response = [
    'success' => false,
    'message' => ''
];

// Check if data is valid
if (empty($inputData) || !isset($inputData['username']) || !isset($inputData['token'])) {
    $response['message'] = 'Invalid request data';
    echo json_encode($response);
    exit;
}

// Extract credentials
$username = $inputData['username'];
$clientToken = $inputData['token']; // This is the client-generated token

// Get database configuration
require_once __DIR__ . '/config/config.php';

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Look up user in the database
    $stmt = $pdo->prepare("SELECT id, username, is_admin FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = 'User not found';
        echo json_encode($response);
        exit;
    }
    
    // Check for any unexpired, active session for this user
    $stmt = $pdo->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? AND expires_at > NOW() AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        // Found a valid session
        $dbToken = $session['token'];
        
        // Set up PHP session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['token'] = $dbToken;
        
        // Update last active time
        $updateStmt = $pdo->prepare("
            UPDATE user_sessions 
            SET last_active_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$session['id']]);
        
        $response['success'] = true;
        $response['message'] = 'Session verified successfully';
        $response['new_token'] = $dbToken; // Return token to update client storage
    } else {
        // Try with refresh token from cookie
        $refreshToken = $_COOKIE['homeio_refresh_token'] ?? null;

        if ($refreshToken) {
            $stmt = $pdo->prepare("
                SELECT us.*, u.username, u.is_admin 
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.refresh_token = ? AND us.expires_at > NOW() AND us.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$refreshToken]);
            $refreshSession = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($refreshSession) {
                // Generate new token
                $newToken = bin2hex(random_bytes(32));
                
                // Update session with new token and extend expiration (30 days)
                $updateStmt = $pdo->prepare("
                    UPDATE user_sessions 
                    SET token = ?, 
                        expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
                        last_active_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newToken, $refreshSession['id']]);
                
                // Set up PHP session
                $_SESSION['user_id'] = $refreshSession['user_id'];
                $_SESSION['username'] = $refreshSession['username'];
                $_SESSION['is_admin'] = $refreshSession['is_admin'];
                $_SESSION['token'] = $newToken;
                
                $response['success'] = true;
                $response['message'] = 'Session refreshed successfully';
                $response['new_token'] = $newToken;
            }
        }
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);