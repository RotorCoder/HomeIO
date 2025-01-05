<!-- templates/config-popup.php -->

<div id="config-popup" class="popup-overlay" style="display: none;">
        <div class="popup-container">
            <div class="popup-header">
                <h3 id="config-device-title">Device Configuration</h3>
                <button onclick="hideConfigMenu()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; padding: 5px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="popup-content">
                <form id="device-config-form">
                    <!-- Hidden inputs -->
                    <input type="hidden" id="config-device-id">
                    <input type="hidden" id="config-device-name">
                    
                    <div class="form-group" style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label>Brand:</label>
                            <input type="text" id="config-brand" readonly style="width: 100%;">
                        </div>
                        <div style="flex: 1;">
                            <label>Model:</label>
                            <input type="text" id="config-model" readonly style="width: 100%;">
                        </div>
                    </div>
    
                    <div class="form-group" style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label>X10 Letter:</label>
                            <select id="config-x10-letter" style="width: 100%;"></select>
                        </div>
                        <div style="flex: 1;">
                            <label>X10 Number:</label>
                            <select id="config-x10-number" style="width: 100%;"></select>
                        </div>
                    </div>
    
                    <div id="config-error-message" class="config-error-message" style="display: none;"></div>
    
                    <div class="form-group">
                        <label>Room:</label>
                        <select id="config-room"></select>
                    </div>
    
                    <!-- Regular device settings -->
                    <div id="regular-config-elements">
                        <div class="form-group">
                            <label>Low Brightness (%):</label>
                            <input type="number" id="config-low" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>Medium Brightness (%):</label>
                            <input type="number" id="config-medium" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>High Brightness (%):</label>
                            <input type="number" id="config-high" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>Preferred Color Temperature:</label>
                            <input type="number" id="config-color-temp" min="2000" max="9000">
                        </div>
                        <div class="form-group">
                            <label>Device Grouping:</label>
                            <select id="config-group-action" onchange="handleGroupActionChange()">
                                <option value="none">No Group</option>
                                <option value="create">Create New Group</option>
                                <option value="join">Join Existing Group</option>
                            </select>
                        </div>
                        <div class="form-group" id="group-name-container" style="display: none;">
                            <label>Group Name:</label>
                            <input type="text" id="config-group-name">
                        </div>
                        <div class="form-group" id="existing-groups-container" style="display: none;">
                            <label>Select Group:</label>
                            <select id="config-existing-groups"></select>
                        </div>
                    </div>
    
                    <!-- Group device settings -->
                    <div id="group-config-elements" style="display: none;">
                        <div class="form-group">
                            <label>Low Brightness (%):</label>
                            <input type="number" id="config-low" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>Medium Brightness (%):</label>
                            <input type="number" id="config-medium" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>High Brightness (%):</label>
                            <input type="number" id="config-high" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>Preferred Color Temperature:</label>
                            <input type="number" id="config-color-temp" min="2000" max="9000">
                        </div>
                        <div class="group-members">
                            <h4>Group Members</h4>
                            <div id="group-members-list">
                                <!-- Group members will be inserted here dynamically -->
                            </div>
                        </div>
    
                        
                    </div>
                </form>
            </div>
    
            <div class="buttons">
                <button type="button" class="delete-btn" onclick="deleteDeviceGroup(groupId)">Delete Group</button>
                <button type="button" class="cancel-btn" onclick="hideConfigMenu()">Cancel</button>
                <button type="button" class="save-btn" onclick="saveDeviceConfig()">Save</button>
            </div>
        </div>
    </div>