<!-- templates/all-temps-popup.php -->
<div id="all-temps-popup" style="display: none;">
    <div class="config-popup">
        <div class="header">
            <h3>All Temperature History</h3>
            <button onclick="hideAllTempsPopup()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="content">
            <div class="history-controls">
                <select id="all-temps-history-range" onchange="loadAllTempHistory()">
                    <option value="24">Last 24 Hours</option>
                    <option value="168">Last 7 Days</option>
                    <option value="720">Last 30 Days</option>
                </select>
            </div>
            <div class="chart-container" style="position: relative; height: 400px; width: 100%;">
                <canvas id="all-temps-chart"></canvas>
            </div>
        </div>
    </div>
</div>