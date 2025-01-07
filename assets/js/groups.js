// assets/js/groups.js

async function loadAvailableGroups(model) {
    try {
        const response = await apiFetch(`api/available-groups?model=${model}`);
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error);
        }
        
        const groupSelect = document.getElementById('config-existing-groups');
        groupSelect.innerHTML = data.groups.map(group => 
            `<option value="${group.id}">${group.name}</option>`
        ).join('');
        
    } catch (error) {
        showError('Failed to load available groups: ' + error.message);
    }
}

function handleGroupActionChange() {
    const action = document.getElementById('config-group-action').value;
    const groupNameContainer = document.getElementById('group-name-container');
    const existingGroupsContainer = document.getElementById('existing-groups-container');
    
    window.groupAction = action;
    
    groupNameContainer.style.display = action === 'create' ? 'block' : 'none';
    existingGroupsContainer.style.display = action === 'join' ? 'block' : 'none';
}

async function deleteDeviceGroup(groupId) {
    if (!confirm('Are you sure you want to delete this group? All devices will be ungrouped.')) {
        return;
    }
    
    try {
        const response = await apiFetch('api/delete-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ groupId: groupId })
        });
        
        const data = await response;
        if (!data.success) {
            throw new Error(data.error || 'Failed to delete group');
        }
        
        hideConfigMenu();
        // Change this line to use loadInitialData instead of updateDevices
        loadInitialData();
        
    } catch (error) {
        showError('Failed to delete group: ' + error.message);
    }
}

// assets/js/groups.js

async function showGroupManagement() {
    const popup = document.getElementById('group-popup');
    if (!popup) {
        console.error('Group popup template not found');
        return;
    }
    popup.style.display = 'block';
    // Call and await loadGroupList() immediately after showing the popup
    await loadGroupList();
}

function hideGroupPopup() {
    document.getElementById('group-popup').style.display = 'none';
}

async function loadGroupList() {
    try {
        console.log('Loading groups list...');
        const response = await apiFetch('api/all-devices');
        console.log('API response:', response);
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to load groups');
        }

        const groupList = document.getElementById('group-list');
        if (!groupList) {
            console.error('Group list container not found');
            return;
        }

        const groups = response.groups || [];
        console.log('Groups to display:', groups);

        groupList.innerHTML = groups.map(group => {
            // Safely parse devices array
            let groupDevices = [];
            try {
                groupDevices = JSON.parse(group.devices || '[]');
            } catch (e) {
                console.error('Error parsing devices for group:', group.id, e);
                groupDevices = [];
            }

            // Safely parse rooms array 
            let groupRooms = [];
            try {
                groupRooms = JSON.parse(group.rooms || '[]');
            } catch (e) {
                console.error('Error parsing rooms for group:', group.id, e);
                groupRooms = [];
            }

            return `
                <div class="room-card" data-group-id="${group.id}">
                    <div class="room-card-header" onclick="toggleGroupCard(${group.id})">
                        <div class="room-card-header-content">
                            <i class="fas fa-object-group"></i>
                            <span>${group.name || 'Unnamed Group'}</span>
                            <span class="device-count">${groupDevices.length} device${groupDevices.length !== 1 ? 's' : ''}</span>
                            <span class="device-count">${groupRooms.length} room${groupRooms.length !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div class="room-card-content">
                        <div class="room-input-group">
                            <input type="text" class="room-input group-name" value="${group.name || ''}" placeholder="Group Name">
                        </div>
                        <div class="room-input-group">
                            <select class="room-input group-model">
                                <option value="light" ${group.model === 'light' ? 'selected' : ''}>Light</option>
                                <option value="fan" ${group.model === 'fan' ? 'selected' : ''}>Fan</option>
                                <option value="outlet" ${group.model === 'outlet' ? 'selected' : ''}>Outlet</option>
                            </select>
                        </div>
                        <div class="room-input-group">
                            <input type="text" class="room-input group-x10" value="${group.x10Code || ''}" placeholder="X10 Code">
                        </div>
                        <div class="room-buttons">
                            <button onclick="showDevicePickerForGroup(${group.id}, '${group.name || ''}')" class="devices-btn">
                                <i class="fas fa-lightbulb"></i> Devices
                                <span class="device-count">${groupDevices.length}</span>
                            </button>
                            <button onclick="showRoomPickerForGroup(${group.id}, '${group.name || ''}')" class="groups-btn">
                                <i class="fas fa-home"></i> Rooms
                                <span class="device-count">${groupRooms.length}</span>
                            </button>
                        </div>
                        <div class="room-actions">
                            <button onclick="deleteGroup(${group.id})" class="room-delete-btn">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button onclick="saveGroup(${group.id})" class="room-save-btn">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        console.log('Group list HTML updated');

    } catch (error) {
        console.error('Error loading group list:', error);
        showError('Failed to load group list: ' + error.message);
    }
}

async function saveGroup(groupId) {
    // Get the group card instead of row
    const card = document.querySelector(`div[data-group-id="${groupId}"]`);
    if (!card) return;

    const groupName = card.querySelector('.group-name').value;
    const model = card.querySelector('.group-model').value;
    const x10Code = card.querySelector('.group-x10').value;

    try {
        const response = await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: groupId,
                name: groupName,
                model: model,
                x10Code: x10Code || null
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to update group');
        }

        // Reload both the group list and main UI
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error saving group:', error);
        showError('Failed to save group: ' + error.message);
    }
}

async function addNewGroup() {
    const groupName = document.getElementById('new-group-name').value;
    const model = document.getElementById('new-group-model').value;
    const x10Code = document.getElementById('new-group-x10').value;

    if (!groupName || !model) {
        showError('Please enter a group name and select a model');
        return;
    }

    try {
        const response = await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: groupName,
                model: model,
                x10Code: x10Code || null
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to create group');
        }

        // Clear form and hide it
        document.getElementById('new-group-name').value = '';
        document.getElementById('new-group-x10').value = '';
        document.getElementById('new-group-form').style.display = 'none';
        
        // Show add button again
        document.querySelector('#group-popup .add-room-btn').style.display = 'flex';

        // Reload both the group list and main UI
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error adding group:', error);
        showError('Failed to add group: ' + error.message);
    }
}

async function deleteGroup(groupId) {
    if (!confirm('Are you sure you want to delete this group? This cannot be undone.')) {
        return;
    }

    try {
        const response = await apiFetch('api/delete-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                groupId: groupId  // Fix parameter name to match what backend expects
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to delete group');
        }

        // Reload both the group list and main UI
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error deleting group:', error);
        showError('Failed to delete group: ' + error.message);
    }
}

// Add to groups.js

function showNewGroupCard() {
    const addButton = document.querySelector('#group-popup .add-room-btn');
    const newGroupForm = document.getElementById('new-group-form');

    if (addButton) {
        addButton.style.display = 'none';
    }

    if (newGroupForm) {
        newGroupForm.style.display = 'block';
        // Reset form fields
        document.getElementById('new-group-name').value = '';
        document.getElementById('new-group-model').value = 'light';
        document.getElementById('new-group-x10').value = '';
    }
}

function cancelNewGroup() {
    const form = document.getElementById('new-group-form');
    if (form) {
        form.style.display = 'none';
    }
    
    const addButton = document.querySelector('#group-popup .add-room-btn');
    if (addButton) {
        addButton.style.display = 'flex';
    }
}

function toggleGroupCard(groupId) {
    const card = document.querySelector(`div[data-group-id="${groupId}"]`);
    if (card) {
        event.stopPropagation();
        card.classList.toggle('expanded');
    }
}



// Add event listener for initialization
document.addEventListener('DOMContentLoaded', () => {
    // Delegate event listener for preventing propagation of action buttons
    document.getElementById('group-list')?.addEventListener('click', function(e) {
        if (e.target.closest('.room-actions') || e.target.closest('.room-buttons')) {
            e.stopPropagation();
        }
    });
});