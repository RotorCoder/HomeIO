<!-- index.php -->

<?php require_once __DIR__ . '/config/config.php'; ?>
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
    <div class="container">
    <div class="error-message" id="error-message"></div>
    <div id="tabs" class="tabs"></div>
    <div id="tab-contents"></div>

    <div class="config-popup-desktop" id="config-popup-desktop">
        <div class="config-content">
            <div class="header">
                <h2>Configuration</h2>
                <button onclick="hideDesktopConfig()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="content">
                <div class="auto-refresh-control">
                    <label class="refresh-toggle">
                        <input type="checkbox" id="desktop-auto-refresh-toggle">
                        <span>Auto-refresh</span>
                    </label>
                    <p class="refresh-time" id="desktop-last-update"></p>
                </div>
                <button onclick="manualRefresh()" class="config-button">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh Govee Devices</span>
                </button>
                <button onclick="showAllTempHistory()" class="config-button">
                    <i class="fas fa-temperature-high"></i>
                    <span>Thermometers</span>
                </button>
                <button onclick="showDefaultRoomDevices()" class="config-button">
                    <i class="fas fa-plug"></i>
                    <span>All Devices</span>
                </button>
            </div>
        </div>
    </div>

    <button onclick="showDesktopConfig()" class="desktop-config-btn config-button">
        <i class="fas fa-cog"></i>
        <span>Configuration</span>
    </button>
</div>
    
    <?php include 'templates/config-popup.php'; ?>
    <?php include 'templates/history-popup.php'; ?>
    <?php include 'templates/all-temps-popup.php'; ?>
    
    <script>
        const API_KEY = '<?php echo $config['homeio_api_key']; ?>';
    </script>
    <script src="assets/js/api.js"></script>
    <script src="assets/js/ui.js"></script>
    <script src="assets/js/devices.js"></script>
    <script src="assets/js/groups.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/temperature.js"></script>
    <script src="assets/js/main.js"></script>
    </body>
</html>