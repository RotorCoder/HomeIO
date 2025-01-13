<!-- templates/room-popup.php -->
<div id="room-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Room Management</h3>
            <button onclick="hideRoomPopup()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <!-- Existing rooms container -->
            <div class="room-cards-container" id="room-list">
                <!-- Room cards will be inserted here by JavaScript -->
                <button onclick="showNewRoomCard()" class="add-room-btn">
                    <i class="fas fa-plus"></i> Add Room
                </button>
            </div>
            
            <!-- New Room Form (hidden by default) -->
            <div id="new-room-form" class="room-card" style="display: none;">
                <div class="room-card-content">
                    <div class="room-input-group">
                        <input type="text" id="new-room-name" placeholder="Room Name" class="room-input">
                    </div>
                    <div class="room-input-group">
                        <div class="icon-preview">
                            <i class="fa-solid fa-house"></i>
                        </div>
                        <input type="text" id="new-room-icon" placeholder="fa-house" class="room-input">
                    </div>
                    <div class="room-actions">
                        <button onclick="cancelNewRoom()" class="room-delete-btn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="addNewRoom()" class="room-save-btn">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Group Picker Popup -->
<div id="group-picker-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Select Groups for <span id="group-picker-room-name"></span></h3>
            <button onclick="hideGroupPicker()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <div class="group-list-container">
                <div id="group-picker-list" class="device-picker-list">
                    <!-- Groups will be populated here -->
                </div>
            </div>
            
        </div>
        <div class="device-picker-buttons">
            <button onclick="hideGroupPicker()" class="cancel-btn">Cancel</button>
            <button onclick="saveGroupSelection()" class="save-btn">Save Changes</button>
        </div>
    </div>
</div>

<!-- Device Picker Popup -->
<div id="device-picker-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Select Devices for <span id="device-picker-room-name"></span></h3>
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
        </div>
        <div class="device-picker-buttons">
                <button onclick="hideDevicePicker()" class="cancel-btn">Cancel</button>
                <button onclick="saveDeviceSelection()" class="save-btn">Save Changes</button>
            </div>
    </div>
</div>