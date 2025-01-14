<!-- templates/remote-popup.php -->
<div id="remote-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Remote Button Management</h3>
            <button onclick="hideRemotePopup()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <!-- Remote cards container -->
            <div class="room-cards-container" id="remote-list">
                <!-- Remote cards will be inserted here by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Button Target Picker Popup -->
<div id="button-target-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>Select Target for <span id="button-target-remote-name"></span> Button <span id="button-target-number"></span></h3>
            <button onclick="hideButtonTargetPicker()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <div class="form-group">
                <label>Target Type:</label>
                <select id="button-target-type" onchange="handleTargetTypeChange()">
                    <option value="device">Device</option>
                    <option value="group">Group</option>
                </select>
            </div>
            <div class="form-group">
                <label>Target:</label>
                <select id="button-target-id"></select>
            </div>
            <div class="form-group">
                <label>Command Type:</label>
                <select id="button-command-type" onchange="handleCommandTypeChange()">
                    <option value="toggle">Toggle</option>
                    <option value="turn">On/Off</option>
                    <option value="brightness">Brightness</option>
                </select>
            </div>
            <div id="toggle-states-container" class="form-group" style="display: none;">
                <label>Toggle States:</label>
                <div class="toggle-states-options">
                    <label><input type="checkbox" value="off"> Off</label>
                    <label><input type="checkbox" value="on"> On</label>
                    <label><input type="checkbox" value="low"> Low</label>
                    <label><input type="checkbox" value="medium"> Medium</label>
                    <label><input type="checkbox" value="high"> High</label>
                </div>
            </div>
            <div id="command-value-container" class="form-group" style="display: none;">
                <label>Command Value:</label>
                <input type="text" id="button-command-value">
            </div>
        </div>
        <div class="device-picker-buttons">
            <button onclick="hideButtonTargetPicker()" class="cancel-btn">Cancel</button>
            <button onclick="saveButtonMapping()" class="save-btn">Save Changes</button>
        </div>
    </div>
</div>