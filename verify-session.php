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

// Extract credentials - accept either token from request or from cookie
$username = $inputData['username'] ?? null;
$clientToken = $inputData['token'] ?? null;
$refreshToken = $inputData['refresh_token'] ?? $_COOKIE['homeio_refresh_token'] ?? null;

// At least one identification method is required
if (!$username && !$refreshToken) {
    $response['message'] = 'Missing authentication credentials';
    echo json_encode($response);
    exit;
}

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
    
    $sessionFound = false;
    
    // First try with client token if available (regular session validation)
    if ($username && $clientToken) {
        // Look up user
        $stmt = $pdo->prepare("SELECT id, username, is_admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check for matching token
            $stmt = $pdo->prepare("
                SELECT * FROM user_sessions 
                WHERE user_id = ? AND token = ? AND expires_at > NOW() AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$user['id'], $clientToken]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                $sessionFound = true;
                
                // Set up PHP session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['token'] = $session['token'];
                
                // Update last active time
                $updateStmt = $pdo->prepare("
                    UPDATE user_sessions 
                    SET last_active_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$session['id']]);
                
                // Ensure refresh token cookie is set
                setcookie('homeio_refresh_token', $session['refresh_token'], [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Session verified successfully';
                $response['new_token'] = $session['token'];
            }
        }
    }
    
    // If client token didn't work, try with refresh token
    if (!$sessionFound && $refreshToken) {
        // THIS IS THE CRITICAL FIX - Look up session by refresh token
        $stmt = $pdo->prepare("
            SELECT us.*, u.id as user_id, u.username, u.is_admin 
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
            
            // Update session with new token and extend expiration
            $updateStmt = $pdo->prepare("
                UPDATE user_sessions 
                SET token = ?, 
                    expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
                    last_active_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$newToken, $refreshSession['id']]);
            
            // Set up PHP session with user data from refreshed session
            $_SESSION['user_id'] = $refreshSession['user_id'];
            $_SESSION['username'] = $refreshSession['username'];
            $_SESSION['is_admin'] = $refreshSession['is_admin'];
            $_SESSION['token'] = $newToken;
            
            // Renew refresh token cookie
            setcookie('homeio_refresh_token', $refreshSession['refresh_token'], [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Session refreshed successfully';
            $response['new_token'] = $newToken;
            $response['username'] = $refreshSession['username']; // Return username for client storage
        } else {
            $response['message'] = 'Invalid or expired refresh token';
        }
    }
    
    if (!$sessionFound && !$response['success']) {
        $response['message'] = 'No valid session found';
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);