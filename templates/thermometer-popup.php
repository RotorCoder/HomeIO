<!-- templates/thermometer-popup.php -->
<div id="thermometer-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Thermometer Management</h3>
            <button onclick="hideThermometerPopup()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <div class="device-table-container">
                <table class="therm-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Model</th>
                            <th>MAC</th>
                            <th>Room</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="thermometer-list">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>