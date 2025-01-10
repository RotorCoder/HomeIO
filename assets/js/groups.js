// groups.js

let currentGroupDevices = new Set();
let currentGroupId = null;

function showGroupManagement() {
    const popup = document.getElementById('group-popup');
    if (!popup) {
        console.error('Group popup template not found');
        return;
    }
    popup.style.display = 'block';
    loadGroups();
}

function hideGroupPopup() {
    document.getElementById('group-popup').style.display = 'none';
}

function showDevicePicker(groupId, groupName) {
    event.stopPropagation();
    currentGroupId = groupId;
    document.getElementById('device-picker-group-name').textContent = groupName;
    document.getElementById('device-picker-popup').style.display = 'block';
    loadDeviceList(groupId);
}

function hideDevicePicker() {
    document.getElementById('device-picker-popup').style.display = 'none';
}

function toggleGroupCard(groupId) {
    const card = document.querySelector(`div[data-groups-group-id="${groupId}"]`);
    if (card) {
        event.stopPropagation();
        card.classList.toggle('expanded');
    }
}


async function saveGroup(groupId) {
    const card = document.querySelector(`div[data-groups-group-id="${groupId}"]`);
    if (!card) return;
    console.error(card);
    const groupName = card.querySelector('.group-name').value;
    
    
    console.error(groupName);


    try {
        // First get current group data
        const getGroupResponse = await apiFetch('api/all-devices');
        if (!getGroupResponse.success) {
            throw new Error('Failed to fetch current group data');
        }

        const currentGroup = getGroupResponse.groups.find(g => g.id.toString() === groupId.toString());
        if (!currentGroup) {
            throw new Error('Group not found');
        }

        // Update group while preserving existing data
        const response = await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: groupId,
                name: groupName,
                model: currentGroup.model || 'group',
                devices: currentGroup.devices ? JSON.parse(currentGroup.devices) : [],
                rooms: currentGroup.rooms ? JSON.parse(currentGroup.rooms) : []
            })
        });

        if (!response.success) {
            throw new Error(response.error || 'Failed to update group');
        }

        await loadGroups();
        await loadInitialData();

    } catch (error) {
        console.error('Error saving group:', error);
        showError('Failed to save group: ' + error.message);
    }
}

async function deleteGroup(groupId) {
    event.stopPropagation();
    if (!confirm('Are you sure you want to delete this group? This cannot be undone.')) {
        return;
    }

    try {
        const response = await apiFetch('api/delete-device-group', {
            method: 'POST',
            body: JSON.stringify({ groupId })
        });
        
        if (!response.success) {
            throw new Error('Failed to delete group');
        }

        await loadGroups();

    } catch (error) {
        console.error('Error deleting group:', error);
        showError('Failed to delete group: ' + error.message);
    }
}

async function moveGroup(groupId, direction) {
    event.stopPropagation();
    
    const currentCard = document.querySelector(`div[data-groups-group-id="${groupId}"]`);
    if (!currentCard) return;

    const allCards = Array.from(document.querySelectorAll('.room-card'))
        .sort((a, b) => parseInt(a.dataset.tabOrder) - parseInt(b.dataset.tabOrder));
    
    const currentIndex = allCards.indexOf(currentCard);
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (targetIndex < 0 || targetIndex >= allCards.length) return;

    try {
        // Get target card
        const targetCard = allCards[targetIndex];
        
        // Update both groups' orders
        await Promise.all([
            updateGroupOrder(groupId, parseInt(targetCard.dataset.tabOrder)),
            updateGroupOrder(targetCard.dataset.groupId, parseInt(currentCard.dataset.tabOrder))
        ]);

        // Reload groups to reflect changes
        await loadGroups();

    } catch (error) {
        console.error('Error moving group:', error);
        showError('Failed to move group: ' + error.message);
    }
}

async function loadDeviceList(groupId) {
    try {
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error('Failed to load devices');
        }

        const group = response.groups.find(g => g.id === groupId);
        const currentDevices = new Set();
        if (group?.devices) {
            JSON.parse(group.devices).forEach(id => currentDevices.add(id));
        }

        const deviceList = document.getElementById('device-picker-list');
        const groupedDevices = {
            'Lights': [],
            'Fans': [],
            'Other': []
        };

        response.devices.forEach(device => {
            const name = device.device_name.toLowerCase();
            if (name.includes('light') || name.includes('lamp')) {
                groupedDevices['Lights'].push(device);
            } else if (name.includes('fan')) {
                groupedDevices['Fans'].push(device);
            } else {
                groupedDevices['Other'].push(device);
            }
        });

        deviceList.innerHTML = Object.entries(groupedDevices)
            .filter(([_, devices]) => devices.length > 0)
            .map(([groupName, devices]) => `
                <div class="device-picker-group">
                    <h4>${groupName}</h4>
                    ${devices.map(device => `
                        <div class="device-picker-item">
                            <label>
                                <input type="checkbox" value="${device.device}" 
                                       ${currentDevices.has(device.device) ? 'checked' : ''}>
                                ${device.device_name}
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

        // Update device-level group membership
        await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentGroupId,
                devices: selectedDevices
            })
        });

        hideDevicePicker();
        await loadRoomList();
        await loadInitialData();

    } catch (error) {
        console.error('Error saving device selection:', error);
        showError('Failed to save device selection: ' + error.message);
    }
}

async function loadGroups() {
        try {
            const response = await apiFetch('api/all-devices');
            if (!response.success) {
                throw new Error(response.error || 'Failed to load devices');
            }
    
            const groupList = document.getElementById('group-list');
            if (!groupList) {
                console.error('Group list element not found');
                return;
            }
    
            // Sort groups by name
            const sortedGroups = response.groups
                .filter(group => group.name !== 'Unassigned')
                .sort((a, b) => a.name.localeCompare(b.name));
    
            // Build the group cards HTML
            const groupCardsHtml = sortedGroups.map(group => {
                const groupDevices = JSON.parse(group.devices || '[]');
                const deviceCount = groupDevices.length;
            
                return `
                    <div class="room-card" data-groups-group-id="${group.id}">
                        <div class="room-card-header" onclick="toggleGroupCard(${group.id})">
                            <div class="room-card-header-content">
                                <i class="fas fa-layer-group"></i>
                                <span class="group-name">${group.name}</span>
                                <span class="device-count">${deviceCount} device${deviceCount !== 1 ? 's' : ''}</span>
                            </div>
                            <div class="room-order-buttons">
                                <button onclick="moveGroup(${group.id}, 'up')" class="order-btn">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <button onclick="moveGroup(${group.id}, 'down')" class="order-btn">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="room-card-content">  
                        
                            <div class="room-input-group">
                                <input type="text" class="room-input group-name" value="${group.name}" placeholder="Group Name">
                            </div>
                            
                            <div class="room-buttons">
                                <button onclick="showDevicePicker(${group.id}, '${group.name}')" class="devices-btn">
                                    <i class="fas fa-lightbulb"></i> Devices
                                    <span class="device-count">${deviceCount}</span>
                                </button>
                            </div>
                            <div class="room-actions">
                                <button onclick="deleteGroup(${group.id})" class="room-delete-btn">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <button onclick="saveGroup(${group.id}); event.stopPropagation();" class="room-save-btn">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </div>
                        </div>
                    </div>`;
            }).join('');
    
            groupList.innerHTML = groupCardsHtml;
    
        } catch (error) {
            console.error('Error loading groups:', error);
            showError('Failed to load groups: ' + error.message);
        }
    }

async function updateGroupOrder(groupId, newOrder) {
    try {
        const response = await apiFetch('api/update-device-group', {
            method: 'POST',
            body: JSON.stringify({
                id: groupId,
                tab_order: newOrder
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to update group order');
        }
    } catch (error) {
        throw error;
    }
}

function showNewGroup() {
    const addButton = document.querySelector('.add-group-btn');
    if (addButton) {
        addButton.style.display = 'none';
    }

    const newGroupForm = document.getElementById('new-group-form');
    if (newGroupForm) {
        newGroupForm.style.display = 'block';
        document.getElementById('new-group-name').value = '';
    }
}

function cancelNewGroup() {
    const form = document.getElementById('new-group-form');
    if (form) {
        form.style.display = 'none';
    }
    
    const addButton = document.querySelector('.add-group-btn');
    if (addButton) {
        addButton.style.display = 'block';
    }
}

async function saveNewGroup() {
    const groupName = document.getElementById('new-group-name').value;
    if (!groupName.trim()) {
        showError('Please enter a group name');
        return;
    }

    try {
        const response = await apiFetch('api/update-device-group', {
            method: 'POST',
            body: JSON.stringify({
                name: groupName,
                model: 'group',
                devices: []
            })
        });

        if (!response.success) {
            throw new Error('Failed to create group');
        }

        document.getElementById('new-group-form').style.display = 'none';
        document.querySelector('.add-group-btn').style.display = 'block';
        await loadGroups();

    } catch (error) {
        console.error('Error creating group:', error);
        showError('Failed to create group: ' + error.message);
    }
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('group-list')?.addEventListener('click', function(e) {
        if (e.target.closest('button')) {
            e.stopPropagation();
        }
    });
});