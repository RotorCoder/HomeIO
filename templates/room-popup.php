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
            <!-- Add new room card -->
            <div class="room-card add-room-card">
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
                    <div class="room-input-group">
                        <input type="number" id="new-room-order" placeholder="Tab Order" class="room-input">
                    </div>
                    <button onclick="addNewRoom()" class="room-add-btn">
                        <i class="fas fa-plus"></i> Add Room
                    </button>
                </div>
            </div>

            <!-- Existing rooms container -->
            <div class="room-cards-container" id="room-list">
                <!-- Room cards will be inserted here by JavaScript -->
            </div>
        </div>
    </div>
</div>