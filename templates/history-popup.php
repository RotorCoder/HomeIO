<!-- templates/history-popup.php -->
<div id="history-popup" style="display: none;">
    <div class="config-popup">
        <div class="header">
            <h3 id="history-device-title">Temperature History</h3>
            <button onclick="hideHistoryPopup()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="content">
            <div class="history-controls">
                <select id="history-range" onchange="loadTempHistory()">
                    <option value="24">Last 24 Hours</option>
                    <option value="168">Last 7 Days</option>
                    <option value="720">Last 30 Days</option>
                </select>
            </div>
            <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                <canvas id="temp-history-chart"></canvas>
            </div>
            <div class="history-table-container" style="margin-top: 20px; max-height: 300px; overflow-y: auto;">
                <table id="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Time</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Temperature</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Humidity</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>