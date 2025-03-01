// assets/js/users.js

let currentUserId = null;

function showUserManagement() {
    const popup = document.getElementById('user-popup');
    if (!popup) {
        console.error('User popup template not found');
        return;
    }
    popup.style.display = 'block';
    loadUserList();
}

function hideUserPopup() {
    document.getElementById('user-popup').style.display = 'none';
}

function showNewUserCard() {
    // Reset form
    document.getElementById('user-form-title').textContent = 'Add New User';
    document.getElementById('user-id').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('is-admin').value = '0';
    document.getElementById('password').required = true;
    document.getElementById('password-help').style.display = 'none';
    
    // Show the form
    document.getElementById('new-user-form').style.display = 'block';
}

function hideNewUserForm() {
    document.getElementById('new-user-form').style.display = 'none';
}

function editUser(userId) {
    currentUserId = userId;
    
    // Get user data
    const userCard = document.querySelector(`div[data-user-id="${userId}"]`);
    if (!userCard) {
        console.error('User card not found');
        return;
    }
    
    const username = userCard.dataset.username;
    const isAdmin = userCard.dataset.isAdmin;
    
    // Update form
    document.getElementById('user-form-title').textContent = 'Edit User';
    document.getElementById('user-id').value = userId;
    document.getElementById('username').value = username;
    document.getElementById('is-admin').value = isAdmin;
    document.getElementById('password').required = false;
    document.getElementById('password').value = '';
    document.getElementById('password-help').style.display = 'block';
    
    // Show the form
    document.getElementById('new-user-form').style.display = 'block';
}

async function saveUser() {
    const userId = document.getElementById('user-id').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const isAdmin = document.getElementById('is-admin').value;
    
    if (!username) {
        showError('Username is required');
        return;
    }
    
    if (!userId && !password) {
        showError('Password is required for new users');
        return;
    }
    
    try {
        const response = await apiFetch('update-user', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: userId || null,
                username: username,
                password: password || null,
                is_admin: isAdmin
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to save user');
        }
        
        hideNewUserForm();
        await loadUserList();
        
    } catch (error) {
        console.error('Error saving user:', error);
        showError('Failed to save user: ' + error.message);
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This cannot be undone.')) {
        return;
    }
    
    try {
        const response = await apiFetch('delete-user', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: userId
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to delete user');
        }
        
        await loadUserList();
        
    } catch (error) {
        console.error('Error deleting user:', error);
        showError('Failed to delete user: ' + error.message);
    }
}

function toggleUserCard(userId) {
    event.stopPropagation();
    
    // First remove expanded class from all cards
    document.querySelectorAll('.room-card').forEach(card => {
        if (card.dataset.userId !== userId.toString()) {
            card.classList.remove('expanded');
            const content = card.querySelector('.room-card-content');
            if (content) {
                content.style.display = 'none';
            }
        }
    });
    
    // Toggle the clicked card
    const clickedCard = document.querySelector(`div[data-user-id="${userId}"]`);
    if (clickedCard) {
        clickedCard.classList.toggle('expanded');
        const content = clickedCard.querySelector('.room-card-content');
        if (content) {
            content.style.display = clickedCard.classList.contains('expanded') ? 'block' : 'none';
        }
    }
}

async function loadUserList() {
    try {
        const response = await apiFetch('users');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load users');
        }
        
        const userList = document.getElementById('user-list');
        if (!userList) {
            console.error('User list element not found');
            return;
        }
        
        // Sort users by username
        const sortedUsers = response.users.sort((a, b) => a.username.localeCompare(b.username));
        
        // Create HTML for user cards
        const userCardsHtml = sortedUsers.map(user => `
            <div class="room-card" 
                 data-user-id="${user.id}" 
                 data-username="${user.username}" 
                 data-is-admin="${user.is_admin}">
                <div class="room-card-header" onclick="toggleUserCard(${user.id})">
                    <div class="room-card-header-content">
                        <i class="fas fa-user"></i>
                        <span>${user.username}</span>
                        ${user.is_admin == 1 ? '<span class="admin-badge">Admin</span>' : ''}
                    </div>
                </div>
                <div class="room-card-content">
                    <div class="user-details">
                        <div><strong>Role:</strong> ${user.is_admin == 1 ? 'Administrator' : 'Regular User'}</div>
                        <div><strong>Created:</strong> ${user.created_at || 'Unknown'}</div>
                        <div><strong>Last Login:</strong> ${user.last_login || 'Never'}</div>
                    </div>
                    <div class="room-actions">
                        <button onclick="deleteUser(${user.id})" class="room-delete-btn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button onclick="editUser(${user.id})" class="room-save-btn">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Add the "Add User" button after the user cards
        const addButton = userList.querySelector('.add-room-btn');
        if (addButton) {
            // Remove existing cards while preserving the button
            const cards = userList.querySelectorAll('.room-card');
            cards.forEach(card => card.remove());
            
            // Insert new cards before the button
            addButton.insertAdjacentHTML('beforebegin', userCardsHtml);
        } else {
            // Fallback if button not found
            userList.innerHTML = userCardsHtml + `
                <button onclick="showNewUserCard()" class="add-room-btn">
                    <i class="fas fa-plus"></i> Add User
                </button>
            `;
        }
        
    } catch (error) {
        console.error('Error loading users:', error);
        showError('Failed to load users: ' + error.message);
    }
}
