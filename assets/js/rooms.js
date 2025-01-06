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


async function saveRoom(roomId) {
    const roomCard = document.querySelector(`div[data-room-id="${roomId}"]`);
    if (!roomCard) return;

    const roomName = roomCard.querySelector('.room-name').value;
    const icon = roomCard.querySelector('.room-icon').value;
    const tabOrder = roomCard.querySelector('.room-order').value;

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
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to update room');
        }

        // Reload room list and main UI
        await Promise.all([
            loadRoomList(),
            fetchRooms(),
            createTabs()
        ]);

        // Update icon preview
        const iconPreview = roomCard.querySelector('.icon-preview i');
        if (iconPreview) {
            iconPreview.className = `fa-solid ${icon}`;
        }

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
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to add room');
        }

        // Clear form
        document.getElementById('new-room-name').value = '';
        document.getElementById('new-room-icon').value = '';
        document.getElementById('new-room-order').value = '';

        // Preview icon updates automatically due to loadRoomList
        
        // Reload everything
        await loadInitialData();
        await loadRoomList();

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
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to delete room');
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

async function loadRoomList() {
    try {
        const response = await apiFetch('api/rooms');
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load rooms');
        }

        const roomList = document.getElementById('room-list');
        if (!roomList) {
            console.error('Room list element not found');
            return;
        }

        // Filter out Unassigned room and sort by tab_order
        const sortedRooms = data.rooms
            .filter(room => room.room_name !== 'Unassigned')
            .sort((a, b) => a.tab_order - b.tab_order);

        roomList.innerHTML = sortedRooms.map((room, index) => {
            const isFirst = index === 0;
            const isLast = index === sortedRooms.length - 1;

            if (room.id === 1) {
                return `
                    <div class="room-card system-default">
                        <div class="room-card-header">
                            <div class="room-card-header-content">
                                <i class="fa-solid ${room.icon}"></i>
                                <span>${room.room_name}</span>
                                <span class="system-default-badge">System Default</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            return `
                <div class="room-card" data-room-id="${room.id}" data-tab-order="${room.tab_order}">
                    <div class="room-card-header" onclick="toggleRoomCard(${room.id})">
                        <div class="room-card-header-content">
                            <i class="fa-solid ${room.icon}"></i>
                            <span>${room.room_name}</span>
                        </div>
                        <div class="room-order-buttons">
                            <button onclick="moveRoom(${room.id}, 'up')" class="order-btn" ${isFirst ? 'disabled' : ''}>
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button onclick="moveRoom(${room.id}, 'down')" class="order-btn" ${isLast ? 'disabled' : ''}>
                                <i class="fas fa-arrow-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="room-card-content">
                        <div class="room-input-group">
                            <input type="text" class="room-input room-name" value="${room.room_name}" placeholder="Room Name">
                        </div>
                        <div class="room-input-group">
                            <div class="icon-preview">
                                <i class="fa-solid ${room.icon}"></i>
                            </div>
                            <input type="text" class="room-input room-icon" value="${room.icon}" placeholder="Icon Class">
                        </div>
                        <div class="room-actions">
                            <button onclick="deleteRoom(${room.id})" class="room-delete-btn">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button onclick="saveRoom(${room.id})" class="room-save-btn">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

    } catch (error) {
        console.error('Error loading room list:', error);
        showError('Failed to load room list: ' + error.message);
    }
}

function toggleRoomCard(roomId) {
    const card = document.querySelector(`div[data-room-id="${roomId}"]`);
    if (card) {
        // Prevent click event from reaching order buttons
        event.stopPropagation();
        card.classList.toggle('expanded');
    }
}

async function moveRoom(roomId, direction) {
    event.stopPropagation();
    
    const currentCard = document.querySelector(`div[data-room-id="${roomId}"]`);
    if (!currentCard) return;

    const allCards = Array.from(document.querySelectorAll('.room-card:not(.system-default)'))
        .sort((a, b) => parseInt(a.dataset.tabOrder) - parseInt(b.dataset.tabOrder));
    
    const currentIndex = allCards.indexOf(currentCard);
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (targetIndex < 0 || targetIndex >= allCards.length) return;

    const targetCard = allCards[targetIndex];
    const targetId = targetCard.dataset.roomId;

    try {
        // Update both rooms' orders simultaneously
        await Promise.all([
            apiFetch('api/update-room', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: roomId,
                    room_name: currentCard.querySelector('.room-name').value,
                    icon: currentCard.querySelector('.room-icon').value,
                    tab_order: parseInt(targetCard.dataset.tabOrder)
                })
            }),
            apiFetch('api/update-room', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: targetId,
                    room_name: targetCard.querySelector('.room-name').value,
                    icon: targetCard.querySelector('.room-icon').value,
                    tab_order: parseInt(currentCard.dataset.tabOrder)
                })
            })
        ]);

        // Reload room list and main UI
        await Promise.all([
            loadRoomList(),
            fetchRooms(),
            createTabs()
        ]);

    } catch (error) {
        console.error('Error moving room:', error);
        showError('Failed to move room: ' + error.message);
    }
}

// Add event listeners for icon preview updates
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('new-room-icon').addEventListener('input', function(e) {
        const iconPreview = document.querySelector('.add-room-card .icon-preview i');
        if (iconPreview) {
            iconPreview.className = `fa-solid ${e.target.value}`;
        }
    });

    // Delegate event listener for existing room icon inputs
    document.getElementById('room-list').addEventListener('input', function(e) {
        if (e.target.classList.contains('room-icon')) {
            const roomCard = e.target.closest('.room-card');
            if (roomCard) {
                const iconPreview = roomCard.querySelector('.icon-preview i');
                if (iconPreview) {
                    iconPreview.className = `fa-solid ${e.target.value}`;
                }
            }
        }
    });
    
    document.getElementById('room-list').addEventListener('click', function(e) {
        if (e.target.closest('.order-btn')) {
            e.stopPropagation();
        }
    });
});