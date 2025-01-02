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
                <h4>Room Configuration</h4>
                <div class="device-table-container">
                    <table class="room-table">
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
                    </table>
                </div>
                <div class="add-room-form" style="margin-top: 20px;">
                    <h4>Add New Room</h4>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <input type="text" id="new-room-name" placeholder="Room Name" style="flex: 2;">
                        <input type="text" id="new-room-icon" placeholder="fa-house" style="flex: 2;">
                        <input type="number" id="new-room-order" placeholder="Tab Order" style="flex: 1;">
                        <button onclick="addNewRoom()" class="save-btn" style="flex: 1;">Add Room</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>