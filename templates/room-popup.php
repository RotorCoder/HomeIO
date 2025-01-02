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
            <div class="room-table">
                <div class="device-table-container">
                    <table class="therm-table">
                        <thead>
                            <tr>
                                <th>Room Name</th>
                                <th>Icon</th>
                                <th>Tab Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="room-list">
                        </tbody>
                        <tr>
                            <td>
                                <input type="text" id="new-room-name" placeholder="Room Name" style="width: auto; padding: 4px;">
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-house"></i>
                                    <input type="text" id="new-room-icon" placeholder="fa-house" style="width: auto; padding: 4px;">
                                </div>
                            </td>
                            <td>
                                <input type="number" id="new-room-order" placeholder="Tab Order" style="width: 80px; padding: 4px;">
                            </td>
                            <td>
                                <button onclick="addNewRoom()" class="save-btn" style="padding: 4px 8px;">
                                    <i class="fas fa-plus"></i> Add Room
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>