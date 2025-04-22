<?php

header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

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
        </script>
        <script src="js/api-secure.js"></script>
        <script src="js/ui.js"></script>
        <script src="js/devices.js"></script>
        <script src="js/groups.js"></script>
        <script src="js/config.js"></script>
        <script src="js/temperature.js"></script>
        <script src="js/main.js"></script>
        <script src="js/rooms.js"></script>
        <script src="js/remote.js"></script>
        <script src="js/users.js"></script>
    </body>
</html>