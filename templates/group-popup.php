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
            <div class="group-table">
                <div class="device-table-container">
                    <table class="therm-table">
                        <thead>
                            <tr>
                                <th>Group Name</th>
                                <th>Model</th>
                                <th>Rooms</th>
                                <th>Devices</th>
                                <th>X10 Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="group-list">
                        </tbody>
                        <tr>
                            <td>
                                <input type="text" id="new-group-name" placeholder="Group Name" style="width: auto; padding: 4px;">
                            </td>
                            <td>
                                <select id="new-group-model" style="width: auto; padding: 4px;">
                                    <option value="light">Light</option>
                                    <option value="fan">Fan</option>
                                    <option value="outlet">Outlet</option>
                                </select>
                            </td>
                            <td>
                                <select id="new-group-rooms" multiple size="3" style="width: auto; padding: 4px;">
                                    <!-- Rooms will be populated by JavaScript -->
                                </select>
                            </td>
                            <td>
                                <select id="new-group-devices" multiple size="3" style="width: auto; padding: 4px;">
                                    <!-- Devices will be populated by JavaScript -->
                                </select>
                            </td>
                            <td>
                                <input type="text" id="new-group-x10" placeholder="X10 Code" style="width: auto; padding: 4px;">
                            </td>
                            <td>
                                <button onclick="addNewGroup()" class="save-btn" style="padding: 4px 8px;">
                                    <i class="fas fa-plus"></i> Add Group
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>