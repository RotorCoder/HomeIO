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
$token = $inputData['token'];

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
    
    // Check if session token matches (in a real implementation, you would store and verify tokens)
    // For now, we're using a simplified validation since we don't have a token table
    if ($user && $token === hash('sha256', $user['username'] . $_SERVER['HTTP_USER_AGENT'])) {
        // Session is valid, set up PHP session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        // Update last login time
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        $response['success'] = true;
        $response['message'] = 'Session verified successfully';
    } else {
        $response['message'] = 'Invalid session';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);