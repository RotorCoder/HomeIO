<?php
// logout.php
session_start();

// Get the token from session
$token = $_SESSION['token'] ?? null;

// Invalidate the token in the database if it exists
if ($token) {
    require_once __DIR__ . '/config/config.php';
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
            $config['db_config']['user'],
            $config['db_config']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Mark session as inactive
        $stmt = $pdo->prepare("
            UPDATE user_sessions 
            SET is_active = 0 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        // Log error but continue with logout
        error_log('Error invalidating token: ' . $e->getMessage());
    }
}

// Clear the session
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Clear refresh token cookie if it exists
setcookie('homeio_refresh_token', '', [
    'expires' => time() - 3600,
    'path' => '/'
]);

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - HomeIO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
    <link rel="manifest" href="assets/images/site.webmanifest">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #c1e2f7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .logout-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo {
            margin-bottom: 1.5rem;
        }
        .logo h1 {
            font-size: 2rem;
            color: #2563eb;
        }
        .message {
            margin-bottom: 1.5rem;
            color: #4b5563;
        }
        .login-link {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .login-link:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logo">
            <h1><i class="fas fa-home"></i> HomeIO</h1>
        </div>
        <div class="message">
            <p>You have been successfully logged out.</p>
        </div>
        <a href="login.php" class="login-link">Log In Again</a>
    </div>
    
    <script src="js/login-session.js"></script>
    <script>
        // Clear login session from browser storage
        clearLoginSession();
        
        // Redirect to login page after 3 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
    </script>
</body>
</html>