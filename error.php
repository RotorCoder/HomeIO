<?php
// error.php - Custom error handler for HomeIO
session_start();

// Get error code from URL parameter or default to 404
$error_code = isset($_GET['code']) ? intval($_GET['code']) : 404;

// Set the appropriate HTTP response code
http_response_code($error_code);

// Define error messages and descriptions
$error_data = [
    403 => [
        'title' => 'Access Forbidden',
        'message' => 'You don\'t have permission to access this resource.',
        'icon' => 'fa-ban',
        'color' => '#e74c3c'
    ],
    404 => [
        'title' => 'Page Not Found',
        'message' => 'The page you requested could not be found.',
        'icon' => 'fa-question-circle',
        'color' => '#3498db'
    ],
    500 => [
        'title' => 'Server Error',
        'message' => 'Something went wrong on our end. Please try again later.',
        'icon' => 'fa-exclamation-triangle',
        'color' => '#e67e22'
    ]
];

// Use default data if error code is not defined
if (!isset($error_data[$error_code])) {
    $error_code = 500;
}

$error = $error_data[$error_code];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error['title']; ?> - HomeIO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/homeio/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/homeio/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/homeio/assets/images/favicon-16x16.png">
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
        .error-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: <?php echo $error['color']; ?>;
        }
        .error-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        .error-code {
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 1.5rem;
        }
        .error-message {
            font-size: 1.1rem;
            color: #34495e;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .back-link {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .back-link:hover {
            background-color: #1d4ed8;
        }
        .links {
            margin-top: 1.5rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .links a {
            color: #2563eb;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas <?php echo $error['icon']; ?>"></i>
        </div>
        <h1 class="error-title"><?php echo $error['title']; ?></h1>
        <div class="error-code">Error <?php echo $error_code; ?></div>
        <p class="error-message"><?php echo $error['message']; ?></p>
        
        <a href="/homeio/" class="back-link">Go to Homepage</a>
        
        <div class="links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/homeio/logout.php">Log Out</a>
            <?php else: ?>
                <a href="/homeio/login.php">Log In</a>
            <?php endif; ?>
            
            <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
                <a href="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>">Go Back</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>