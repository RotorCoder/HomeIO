<!-- templates/user-popup.php -->
<div id="user-popup" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3>User Management</h3>
            <button onclick="hideUserPopup()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <!-- User management content will go here -->
            <div class="room-cards-container" id="user-list">
                <!-- User cards will be inserted here by JavaScript -->
                <button onclick="showNewUserCard()" class="add-room-btn">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New User Form (hidden by default) -->
<div id="new-user-form" class="popup-overlay" style="display: none;">
    <div class="popup-container">
        <div class="popup-header">
            <h3 id="user-form-title">Add New User</h3>
            <button onclick="hideNewUserForm()" class="close-popup-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="popup-content">
            <form id="user-form">
                <input type="hidden" id="user-id" value="">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" class="room-input" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" class="room-input">
                    <small id="password-help" class="form-text">Leave blank to keep current password when editing</small>
                </div>
                
                <div class="form-group">
                    <label for="is-admin">User Type:</label>
                    <select id="is-admin" class="room-input">
                        <option value="0">Regular User</option>
                        <option value="1">Administrator</option>
                    </select>
                </div>
                
                <div class="room-actions">
                    <button type="button" onclick="hideNewUserForm()" class="room-delete-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" onclick="saveUser()" class="room-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>