<!-- templates/group-popup.php -->
<div id="group-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Group Management</h3>
            <button onclick="hideGroupPopup()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <!-- Existing groups container -->
            <div class="room-cards-container" id="group-list">
                <!-- Group cards will be inserted here by JavaScript -->
                <button onclick="showNewGroupCard()" class="add-room-btn">
                    <i class="fas fa-plus"></i> Add Group
                </button>
            </div>

            <!-- New Group Form (hidden by default) -->
            <div id="new-group-form" class="room-card" style="display: none;">
                <div class="room-card-content">
                    <div class="room-input-group">
                        <input type="text" id="new-group-name" placeholder="Group Name" class="room-input">
                    </div>
                    <div class="room-actions">
                        <button onclick="cancelNewGroup()" class="room-delete-btn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="saveNewGroup()" class="room-save-btn">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Device Picker Popup -->
<div id="device-picker-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Select Devices for <span id="device-picker-group-name"></span></h3>
            <button onclick="hideDevicePicker()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <div class="device-list-container">
                <div id="device-picker-list" class="device-picker-list">
                    <!-- Devices will be populated here -->
                </div>
            </div>
            <div class="device-picker-buttons">
                <button onclick="hideDevicePicker()" class="cancel-btn">Cancel</button>
                <button onclick="saveDeviceSelection()" class="save-btn">Save Changes</button>
            </div>
        </div>
    </div>
</div>