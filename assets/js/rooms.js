// assets/js/rooms.js

function showRoomManagement() {
    const popup = document.getElementById('room-popup');
    if (!popup) {
        console.error('Room popup template not found');
        return;
    }
    popup.style.display = 'block';
    loadRoomList();
}

function hideRoomPopup() {
    document.getElementById('room-popup').style.display = 'none';
}

async function loadRoomList() {
    try {
        const response = await apiFetch('api/rooms');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load rooms');
        }

        const tbody = document.getElementById('room-list');
        if (!tbody) {
            console.error('Room list tbody element not found');
            return;
        }

        tbody.innerHTML = data.rooms.map(room => {
            if (room.id === 1) {
                return `
                    <tr>
                        <td>${room.room_name}</td>
                        <td><i class="fa-solid ${room.icon}"></i> ${room.icon}</td>
                        <td>${room.tab_order}</td>
                        <td>System Default</td>
                    </tr>
                `;
            }
            
            return `
                <tr data-room-id="${room.id}">
                    <td>
                        <input type="text" class="room-name" value="${room.room_name}" style="width: auto; padding: 4px;">
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid ${room.icon}"></i>
                            <input type="text" class="room-icon" value="${room.icon}" style="width: auto; padding: 4px;">
                        </div>
                    </td>
                    <td>
                        <input type="number" class="room-order" value="${room.tab_order}" style="width: 80px; padding: 4px;">
                    </td>
                    <td>
                        <button onclick="saveRoom(${room.id})" class="save-btn" style="padding: 4px 8px; margin-right: 5px;">Save</button>
                        <button onclick="deleteRoom(${room.id})" class="delete-btn" style="padding: 4px 8px; background: #ef4444;">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (error) {
        console.error('Error loading room list:', error);
        showError('Failed to load room list: ' + error.message);
    }
}

async function saveRoom(roomId) {
    const row = document.querySelector(`tr[data-room-id="${roomId}"]`);
    if (!row) return;

    const roomName = row.querySelector('.room-name').value;
    const icon = row.querySelector('.room-icon').value;
    const tabOrder = row.querySelector('.room-order').value;

    try {
        const response = await apiFetch('api/update-room', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: roomId,
                room_name: roomName,
                icon: icon,
                tab_order: tabOrder
            })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to update room');
        }

        // Reload room list and main UI
        await Promise.all([
            loadRoomList(),
            fetchRooms(),
            createTabs()
        ]);

    } catch (error) {
        console.error('Error saving room:', error);
        showError('Failed to save room: ' + error.message);
    }
}

async function addNewRoom() {
    const roomName = document.getElementById('new-room-name').value;
    const icon = document.getElementById('new-room-icon').value;
    const tabOrder = document.getElementById('new-room-order').value;

    if (!roomName || !icon || !tabOrder) {
        showError('Please fill in all fields');
        return;
    }

    try {
        const response = await apiFetch('api/add-room', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                room_name: roomName,
                icon: icon,
                tab_order: parseInt(tabOrder)
            })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to add room');
        }

        // Clear form
        document.getElementById('new-room-name').value = '';
        document.getElementById('new-room-icon').value = '';
        document.getElementById('new-room-order').value = '';

        // Reload room list and main UI
        await Promise.all([
            loadRoomList(),
            fetchRooms(),
            createTabs()
        ]);

    } catch (error) {
        console.error('Error adding room:', error);
        showError('Failed to add room: ' + error.message);
    }
}

async function deleteRoom(roomId) {
    if (!confirm('Are you sure you want to delete this room? This cannot be undone.')) {
        return;
    }

    try {
        const response = await apiFetch('api/delete-room', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: roomId
            })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to delete room');
        }

        // Reload room list and main UI
        await Promise.all([
            loadRoomList(),
            fetchRooms(),
            createTabs()
        ]);

    } catch (error) {
        console.error('Error deleting room:', error);
        showError('Failed to delete room: ' + error.message);
    }
}