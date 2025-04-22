<?php
// login.php
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/config.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        try {
            // Connect to database
            $pdo = new PDO(
                "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
                $config['db_config']['user'],
                $config['db_config']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Look up user
            $stmt = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Generate basic token
                $token = hash('sha256', $user['username'] . $_SERVER['HTTP_USER_AGENT']);
                $_SESSION['token'] = $token;
                
                // Check if user_sessions table exists
                try {
                    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
                    $tableExists = $checkTableStmt->rowCount() > 0;
                    
                    if ($tableExists) {
                        // Only try to use the table if it exists
                        // Set expiration based on remember me option
                        $expiresAt = $rememberMe 
                            ? date('Y-m-d H:i:s', strtotime('+30 days')) 
                            : date('Y-m-d H:i:s', strtotime('+24 hours'));
                        
                        // Store session in database with simple query
                        $sessionStmt = $pdo->prepare("
                            INSERT INTO user_sessions 
                            (user_id, token, refresh_token, user_agent, ip_address, created_at, expires_at, last_active_at)
                            VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())
                        ");
                        $sessionStmt->execute([
                            $user['id'],
                            $token,
                            $token, // use same token as refresh_token for simplicity
                            $_SERVER['HTTP_USER_AGENT'],
                            $_SERVER['REMOTE_ADDR'],
                            $expiresAt
                        ]);
                    }
                } catch (Exception $tableEx) {
                    // Just continue if there's an issue with the sessions table
                    // We don't want this to block the login process
                }
                
                // Update last login time
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Redirect to main page
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HomeIO</title>
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
        .login-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .logo h1 {
            font-size: 2rem;
            color: #2563eb;
        }
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        label {
            font-weight: 500;
            color: #4b5563;
        }
        input[type="text"],
        input[type="password"] {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        button {
            background-color: #2563eb;
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #1d4ed8;
        }
        .loading {
            text-align: center;
            padding: 1rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1><i class="fas fa-home"></i> HomeIO</h1>
        </div>
        <div class="error-message" id="error-message">
            <?php echo $error; ?>
        </div>
        <form id="login-form" method="POST" action="login.php">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="remember-me">
                <input type="checkbox" id="remember_me" name="remember_me" value="1">
                <label for="remember_me">Remember me</label>
            </div>
            <button type="submit">Log In</button>
        </form>
    </div>
    
    <script src="assets/js/login-session.js"></script>
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Process login session storage when the form is submitted
            const loginForm = document.getElementById('login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const username = document.getElementById('username').value;
                    const rememberMe = document.getElementById('remember_me').checked;
                    
                    // Generate a simple token for client storage
                    const token = btoa(username + ':' + navigator.userAgent);
                    
                    // Store in browser
                    storeLoginSession(username, token, rememberMe);
                });
            }
            
            // Auto login attempt
            attemptAutoLogin();
        });
    </script>
</body>
</html>