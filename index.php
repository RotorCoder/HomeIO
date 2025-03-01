<?php
// Start the session at the very beginning of the file
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>HomeIO</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
        <link rel="manifest" href="assets/images/site.webmanifest">
        <link rel="stylesheet" href="assets/css/styles.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
        <style>
            /* Add user menu styles */
            .user-menu {
                position: fixed;
                top: 6px;
                right: 8px;
                z-index: 1000;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                padding: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .user-menu .username {
                font-weight: 500;
                color: #1e293b;
            }
            
            .user-menu .menu-button {
                background: none;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
                color: #64748b;
                transition: background 0.2s;
            }
            
            .user-menu .menu-button:hover {
                background: #f1f5f9;
                color: #1e293b;
            }
            
            .user-menu .dropdown {
                position: absolute;
                top: 100%;
                right: 0;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                padding: 8px;
                display: none;
                min-width: 150px;
            }
            
            .user-menu .dropdown.active {
                display: block;
            }
            
            .user-menu .dropdown a {
                display: block;
                padding: 8px;
                color: #1e293b;
                text-decoration: none;
                border-radius: 4px;
            }
            
            .user-menu .dropdown a:hover {
                background: #f1f5f9;
            }
            
            .user-menu .dropdown .separator {
                height: 1px;
                background: #e2e8f0;
                margin: 8px 0;
            }
            
            .user-menu .dropdown .logout {
                color: #ef4444;
            }
        </style>
    </head>
    <body>
        <?php require_once __DIR__ . '/config/config.php'; ?>
        <div class="container">
            <div class="error-message" id="error-message"></div>
            <div id="tabs" class="tabs"></div>
            <div id="tab-contents"></div>
        </div>
        
        <?php require __DIR__ . '/templates/config-popup.php'; ?>
        <?php require __DIR__ . '/templates/history-popup.php'; ?>
        <?php require __DIR__ . '/templates/all-temps-popup.php'; ?>
        <?php require __DIR__ . '/templates/room-popup.php'; ?>
        <?php require __DIR__ . '/templates/group-popup.php'; ?>
        <?php require __DIR__ . '/templates/thermometer-popup.php'; ?>
        <?php require __DIR__ . '/templates/remote-popup.php'; ?>
        <?php require __DIR__ . '/templates/user-popup.php'; ?>
        
        <script>
            const API_CONFIG = {
                apiProxy: "api-proxy.php"
            };
            
            // User menu dropdown
            document.addEventListener('DOMContentLoaded', function() {
                const menuToggle = document.getElementById('userMenuToggle');
                const dropdown = document.getElementById('userDropdown');
                
                menuToggle.addEventListener('click', function() {
                    dropdown.classList.toggle('active');
                });
                
                // Close the dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.user-menu')) {
                        dropdown.classList.remove('active');
                    }
                });
            });
        </script>
        <script src="assets/js/api-secure.js"></script>
        <script src="assets/js/ui.js"></script>
        <script src="assets/js/devices.js"></script>
        <script src="assets/js/groups.js"></script>
        <script src="assets/js/config.js"></script>
        <script src="assets/js/temperature.js"></script>
        <script src="assets/js/main.js"></script>
        <script src="assets/js/rooms.js"></script>
        <script src="assets/js/remote.js"></script>
        <script src="assets/js/users.js"></script>
    </body>
</html>