// assets/js/rooms.js

let currentRoomDevices = new Set();
let currentRoomGroups = new Set();
let currentRoomId = null;

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

function showDevicePicker(roomId, roomName) {
    currentRoomId = roomId;
    document.getElementById('device-picker-room-name').textContent = roomName;
    document.getElementById('device-picker-popup').style.display = 'block';
    loadDeviceList(roomId);
}

function hideDevicePicker() {
    document.getElementById('device-picker-popup').style.display = 'none';
    currentRoomId = null;
    currentRoomDevices.clear();
}

async function saveRoom(roomId) {
    const roomCard = document.querySelector(`div[data-room-id="${roomId}"]`);
    if (!roomCard) return;
    console.error(roomCard);
    const roomName = roomCard.querySelector('.room-name').value;
    const icon = roomCard.querySelector('.room-icon').value;
    const tabOrder = roomCard.dataset.tabOrder || '0'; // Use the data attribute instead

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
                tab_order: parseInt(tabOrder)
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

async function deleteRoom(roomId) {
    if (!confirm('Are you sure you want to delete this room? This cannot be undone.')) {
        return;
    }

    try {
        const response = await apiFetch('api/delete-room', {
            method: 'POST',
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

function toggleRoomCard(roomId) {
    event.stopPropagation();
    
    // First remove expanded class from all cards
    document.querySelectorAll('.room-card').forEach(card => {
        if (card.dataset.roomId !== roomId.toString()) {
            card.classList.remove('expanded');
            // Reset any expanded content
            const content = card.querySelector('.room-card-content');
            if (content) {
                content.style.display = 'none';
            }
        }
    });
    
    // Toggle the clicked card
    const clickedCard = document.querySelector(`div[data-room-id="${roomId}"]`);
    if (clickedCard) {
        clickedCard.classList.toggle('expanded');
        const content = clickedCard.querySelector('.room-card-content');
        if (content) {
            content.style.display = clickedCard.classList.contains('expanded') ? 'block' : 'none';
        }
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

async function loadDeviceList(roomId) {
    try {
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load devices');
        }

        // Reset current room devices
        currentRoomDevices.clear();

        // Group devices by type
        const deviceGroups = {
            'Lights': [],
            'Fans': [],
            'Outlets': [],
            'Other': []
        };

        response.devices.forEach(device => {
            // Check if device is assigned to current room
            if (device.room_ids && device.room_ids.split(',').includes(roomId.toString())) {
                currentRoomDevices.add(device.device);
            }

            // Categorize device
            const deviceName = device.device_name.toLowerCase();
            if (deviceName.includes('light') || deviceName.includes('lamp')) {
                deviceGroups['Lights'].push(device);
            } else if (deviceName.includes('fan')) {
                deviceGroups['Fans'].push(device);
            } else if (deviceName.includes('outlet') || deviceName.includes('plug')) {
                deviceGroups['Outlets'].push(device);
            } else {
                deviceGroups['Other'].push(device);
            }
        });

        // Generate HTML for device picker
        const deviceList = document.getElementById('device-picker-list');
        deviceList.innerHTML = Object.entries(deviceGroups)
            .filter(([_, devices]) => devices.length > 0)
            .map(([groupName, devices]) => `
                <div class="device-picker-group">
                    <h4>${groupName} (${devices.length})</h4>
                    ${devices.map(device => `
                        <div class="device-picker-item">
                            <label>
                                <input type="checkbox" 
                                       value="${device.device}"
                                       ${currentRoomDevices.has(device.device) ? 'checked' : ''}>
                                <span>${device.device_name}</span>
                            </label>
                        </div>
                    `).join('')}
                </div>
            `).join('');

    } catch (error) {
        console.error('Error loading devices:', error);
        showError('Failed to load devices: ' + error.message);
    }
}

async function saveDeviceSelection() {
    try {
        // Get all selected devices
        const selectedDevices = Array.from(
            document.querySelectorAll('#device-picker-list input[type="checkbox"]:checked')
        ).map(cb => cb.value);

        // Update each device's room assignment
        for (const deviceId of selectedDevices) {
            await apiFetch('api/update-device-details', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device: deviceId,
                    rooms: [currentRoomId]
                })
            });
        }

        hideDevicePicker();
        await loadRoomList();
        await loadInitialData();

    } catch (error) {
        console.error('Error saving device selection:', error);
        showError('Failed to save device selection: ' + error.message);
    }
}

function showGroupPicker(roomId, roomName) {
    currentRoomId = roomId;
    document.getElementById('group-picker-room-name').textContent = roomName;
    document.getElementById('group-picker-popup').style.display = 'block';
    loadGroupList(roomId);
}

function hideGroupPicker() {
    document.getElementById('group-picker-popup').style.display = 'none';
    currentRoomId = null;
    currentRoomGroups.clear();
}

async function loadGroupList(roomId) {
    try {
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load groups');
        }

        // Reset current room groups
        currentRoomGroups.clear();

        // Get groups and their devices
        const groups = response.groups || [];
        const devices = response.devices || [];

        // Create a map of device names for quick lookup
        const deviceMap = new Map(devices.map(d => [d.device, d.device_name]));

        // Build the group list HTML
        const groupList = document.getElementById('group-picker-list');
        groupList.innerHTML = groups.map(group => {
            // Check if group is assigned to current room
            const groupDevices = JSON.parse(group.devices || '[]');
            const deviceNames = groupDevices
                .map(deviceId => deviceMap.get(deviceId))
                .filter(name => name)
                .join(', ');

            // Parse room assignments from the rooms column
            const groupRooms = JSON.parse(group.rooms || '[]');
            const isAssigned = groupRooms.includes(parseInt(roomId));
            if (isAssigned) {
                currentRoomGroups.add(group.id);
            }

            return `
                <div class="group-picker-item">
                    <label>
                        <input type="checkbox" 
                               value="${group.id}"
                               ${isAssigned ? 'checked' : ''}>
                        <span>${group.name}</span>
                    </label>
                    <div class="group-details">
                        ${deviceNames}
                    </div>
                </div>
            `;
        }).join('') || '<p>No groups available</p>';

    } catch (error) {
        console.error('Error loading groups:', error);
        showError('Failed to load groups: ' + error.message);
    }
}

async function saveGroupSelection() {
    try {
        // Get all selected groups
        const selectedGroups = Array.from(
            document.querySelectorAll('#group-picker-list input[type="checkbox"]:checked')
        ).map(cb => cb.value);

        // Get original group data first
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error('Failed to fetch group data');
        }
        const allGroups = response.groups || [];

        // Update each group
        for (const group of allGroups) {
            // Get the complete existing group data to ensure we don't lose anything
            // Parse current room assignments - ensure it's always an array
            let currentRooms = [];
            try {
                currentRooms = JSON.parse(group.rooms || '[]');
                if (!Array.isArray(currentRooms)) currentRooms = [];
            } catch (e) {
                currentRooms = [];
            }
            
            // Check if this group is selected
            const isSelected = selectedGroups.includes(group.id.toString());
            
            // If selected and not already in rooms, add it
            // If not selected and in rooms, remove it
            if (isSelected && !currentRooms.includes(currentRoomId)) {
                currentRooms.push(currentRoomId);
            } else if (!isSelected && currentRooms.includes(currentRoomId)) {
                currentRooms = currentRooms.filter(id => id !== currentRoomId);
            }

            // Parse devices - ensure it's always an array
            let devices = [];
            try {
                devices = JSON.parse(group.devices || '[]');
                if (!Array.isArray(devices)) devices = [];
            } catch (e) {
                devices = [];
            }

            // Send the complete group data
            await apiFetch('api/update-device-group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: group.id,
                    name: group.name || '',
                    model: group.model || '',
                    devices: devices,
                    rooms: currentRooms
                })
            });
        }

        hideGroupPicker();
        await loadRoomList();
        await loadInitialData();

    } catch (error) {
        console.error('Error saving group selection:', error);
        showError('Failed to save group selection: ' + error.message);
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

        // Get device counts for each room
        const deviceResponse = await apiFetch('api/all-devices');
        const deviceCounts = {};
        
        if (deviceResponse.success) {
            deviceResponse.devices.forEach(device => {
                if (device.room_ids) {
                    const roomIds = device.room_ids.split(',');
                    roomIds.forEach(roomId => {
                        deviceCounts[roomId] = (deviceCounts[roomId] || 0) + 1;
                    });
                }
            });
        }

        const sortedRooms = data.rooms
            .sort((a, b) => a.tab_order - b.tab_order);

        const roomCardsHtml = sortedRooms.map((room, index) => {
            const isFirst = index === 0;
            const isLast = index === sortedRooms.length - 1;
            const deviceCount = deviceCounts[room.id] || 0;

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
                        <div class="room-buttons">
                            <button onclick="showDevicePicker(${room.id}, '${room.room_name}')" class="devices-btn">
                                <i class="fas fa-lightbulb"></i> Devices
                                <span class="device-count">${deviceCount}</span>
                            </button>
                            <button onclick="showGroupPicker(${room.id}, '${room.room_name}')" class="groups-btn">
                                <i class="fas fa-object-group"></i> Groups
                                <span class="group-count">${deviceResponse.groups?.filter(g => 
                                    JSON.parse(g.rooms || '[]').includes(room.id)
                                ).length || 0}</span>
                            </button>
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

        // Insert the cards BEFORE the button - exactly like groups.js
        const addButton = roomList.querySelector('.add-room-btn');
        if (addButton) {
            // Remove existing cards while preserving the button
            const cards = roomList.querySelectorAll('.room-card');
            cards.forEach(card => card.remove());
            
            // Insert new cards before the button
            addButton.insertAdjacentHTML('beforebegin', roomCardsHtml);
        } else {
            // Fallback if button not found
            roomList.innerHTML = roomCardsHtml;
        }

    } catch (error) {
        console.error('Error loading room list:', error);
        showError('Failed to load room list: ' + error.message);
    }
}

function showNewRoomCard() {
    // Get references to the elements
    const addButton = document.querySelector('button[onclick="showNewRoomCard()"]');
    const roomList = document.getElementById('room-list');
    
    if (addButton) {
        addButton.style.setProperty('display', 'none', 'important');
    }

    // Check if form already exists
    let newRoomForm = document.getElementById('new-room-form');
    
    // If form doesn't exist, create it
    if (!newRoomForm) {
        newRoomForm = document.createElement('div');
        newRoomForm.id = 'new-room-form';
        newRoomForm.className = 'room-card';
        newRoomForm.innerHTML = `
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
                <div class="room-actions">
                    <button onclick="cancelNewRoom()" class="room-delete-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button onclick="saveNewRoom()" class="room-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        `;
    }

    // Reset form fields
    const nameInput = newRoomForm.querySelector('#new-room-name');
    const iconInput = newRoomForm.querySelector('#new-room-icon');
    if (nameInput) nameInput.value = '';
    if (iconInput) iconInput.value = 'fa-house';

    // Show the form
    newRoomForm.style.display = 'block';
    newRoomForm.classList.add('expanded');

    // Add form to room list
    if (roomList) {
        roomList.appendChild(newRoomForm);
    } else {
        console.error('Room list container not found');
    }

    // Ensure the form is visible
    newRoomForm.scrollIntoView({ behavior: 'smooth' });
    
    // Hide the add button
    if (addButton) {
        addButton.style.display = 'none';
    }
}

function cancelNewRoom() {
    // Hide and reset the form
    const form = document.getElementById('new-room-form');
    if (form) {
        form.style.display = 'none';
        form.classList.remove('expanded');
        
        // Reset form fields
        const nameInput = document.getElementById('new-room-name');
        const iconInput = document.getElementById('new-room-icon');
        if (nameInput) nameInput.value = '';
        if (iconInput) iconInput.value = 'fa-house';
        
        // Reset icon preview
        const iconPreview = form.querySelector('.icon-preview i');
        if (iconPreview) {
            iconPreview.className = 'fa-solid fa-house';
        }
    }
    
    // Show the add button
    const addButton = document.querySelector('.add-room-btn');
    if (addButton) {
        addButton.style.display = 'flex';
    }
}

async function saveNewRoom() {
    const roomName = document.getElementById('new-room-name').value;
    const icon = document.getElementById('new-room-icon').value;

    if (!roomName || !icon) {
        showError('Please fill in all fields');
        return;
    }

    try {
        // Get the current max tab order
        const response = await apiFetch('api/rooms');
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to get rooms');
        }

        // Calculate the new tab order by finding the highest current order and adding 1
        const maxTabOrder = data.rooms
            .reduce((max, room) => Math.max(max, parseInt(room.tab_order) || 0), 0);
        
        const newTabOrder = maxTabOrder + 1;

        // Send request to add the new room
        const addResponse = await apiFetch('api/add-room', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                room_name: roomName,
                icon: icon,
                tab_order: newTabOrder
            })
        });
        
        if (!addResponse.success) {
            throw new Error(addResponse.error || 'Failed to add room');
        }

        // Reset and hide the form
        const newRoomForm = document.getElementById('new-room-form');
        if (newRoomForm) {
            newRoomForm.style.display = 'none';
            newRoomForm.classList.remove('expanded');
        }
        
        // Show the add button
        const addButton = document.querySelector('.add-room-btn');
        if (addButton) {
            addButton.style.display = 'flex';
        }

        // Reload everything
        await loadInitialData();
        await loadRoomList();

    } catch (error) {
        console.error('Error adding room:', error);
        showError('Failed to add room: ' + error.message);
    }
}

// Add event listeners for icon preview updates
document.addEventListener('DOMContentLoaded', () => {
    // Event listener for the new room icon input
    document.getElementById('new-room-icon')?.addEventListener('input', function(e) {
        const iconPreview = document.querySelector('#new-room-form .icon-preview i');
        if (iconPreview) {
            iconPreview.className = `fa-solid ${e.target.value}`;
        }
    });

    // Delegate event listener for existing room icon inputs
    document.getElementById('room-list')?.addEventListener('input', function(e) {
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
    
    document.getElementById('room-list')?.addEventListener('click', function(e) {
        if (e.target.closest('.order-btn')) {
            e.stopPropagation();
        }
    });
});