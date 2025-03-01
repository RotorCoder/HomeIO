<?php
session_start();
require_once __DIR__ . '/config/config.php';

// Only admin users can access this page
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Process registration 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['confirm_password'])) {
        $error = 'Please fill in all fields';
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = 'Passwords do not match';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
                $config['db_config']['user'],
                $config['db_config']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check if username already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$_POST['username']]);
            if ($stmt->fetch()) {
                $error = 'Username already exists';
            } else {
                // Create new user
                $stmt = $pdo->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)');
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
                
                $stmt->execute([$_POST['username'], $hashedPassword, $isAdmin]);
                $success = 'User created successfully';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch existing users
try {
    $pdo = new PDO(
        "mysql:host={$config['db_config']['host']};dbname={$config['db_config']['dbname']};charset=utf8mb4",
        $config['db_config']['user'],
        $config['db_config']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query('SELECT id, username, is_admin, created_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - HomeIO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
    <link rel="manifest" href="assets/images/site.webmanifest">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .containerx {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .card-header {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
            color: #64748b;
        }
        
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .submit-btn {
            padding: 0.5rem 1rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .submit-btn:hover {
            background: #2563eb;
        }
        
        .back-btn {
            padding: 0.5rem 1rem;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.875rem;
            
        }
        
        .back-btn:hover {
            background: #4b5563;
        }
        
        .message {
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .error {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
        }
        
        .success {
            background-color: #dcfce7;
            border: 1px solid #22c55e;
            color: #15803d;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .users-table th,
        .users-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .users-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #64748b;
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .admin-badge {
            background-color: #3b82f6;
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .user-badge {
            background-color: #6b7280;
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            background-color: #ef4444;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
        }
        
        .action-btn:hover {
            background-color: #b91c1c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">
                    <i class="fas fa-users"></i> User Management
                </h1>
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Exit
                </a>
            </div>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="is_admin" name="is_admin">
                    <label for="is_admin">Admin User</label>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i> Existing Users
                </h2>
            </div>
            
            <?php if (!empty($users)): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="admin-badge">Admin</span>
                                    <?php else: ?>
                                        <span class="user-badge">User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <form method="post" action="delete_user.php" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="action-btn">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>