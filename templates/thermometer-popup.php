<!-- templates/thermometer-popup.php -->

<div id="thermometer-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Sensor Management</h3>
            <button onclick="hideThermometerPopup()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <!-- Existing thermometers container -->
            <div class="room-cards-container" id="thermometer-list">
                <!-- Thermometer cards will be inserted here by JavaScript -->
            </div>
        </div>
    </div>
</div>