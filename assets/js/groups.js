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

function showGroupManagement() {
    const popup = document.getElementById('group-popup');
    if (!popup) {
        console.error('Group popup template not found');
        return;
    }
    popup.style.display = 'block';
    loadGroupList();
}

function hideGroupPopup() {
    document.getElementById('group-popup').style.display = 'none';
}

async function loadGroupList() {
    try {
        const response = await apiFetch('api/all-devices');
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load groups');
        }

        // Populate rooms dropdown
        const roomSelect = document.getElementById('new-group-rooms');
        roomSelect.innerHTML = data.rooms.map(room => 
            `<option value="${room.id}">${room.room_name}</option>`
        ).join('');

        // Populate devices dropdown
        const deviceSelect = document.getElementById('new-group-devices');
        deviceSelect.innerHTML = data.devices.map(device => 
            `<option value="${device.device}">${device.device_name}</option>`
        ).join('');

        const tbody = document.getElementById('group-list');
        if (!tbody) {
            console.error('Group list tbody element not found');
            return;
        }

        tbody.innerHTML = data.groups.map(group => {
            // Parse devices array
            const devices = JSON.parse(group.devices || '[]');
            const deviceNames = devices.map(deviceId => {
                const device = data.devices.find(d => d.device === deviceId);
                return device ? device.device_name : deviceId;
            }).join(', ');

            // Parse room assignments from the rooms column
            const groupRooms = JSON.parse(group.rooms || '[]');

            // Create room options with selections
            const roomOptions = data.rooms.map(room => {
                const isSelected = groupRooms.includes(room.id);
                return `<option value="${room.id}" ${isSelected ? 'selected' : ''}>
                    ${room.room_name}
                </option>`;
            }).join('');

            // Create device options with selections
            const deviceOptions = data.devices.map(device => {
                const isSelected = devices.includes(device.device);
                return `<option value="${device.device}" ${isSelected ? 'selected' : ''}>
                    ${device.device_name}
                </option>`;
            }).join('');

            return `
                <tr data-group-id="${group.id}">
                    <td>
                        <input type="text" class="group-name" value="${group.name}" style="width: auto; padding: 4px;">
                    </td>
                    <td>
                        <select class="group-model" style="width: auto; padding: 4px;">
                            <option value="light" ${group.model === 'light' ? 'selected' : ''}>Light</option>
                            <option value="fan" ${group.model === 'fan' ? 'selected' : ''}>Fan</option>
                            <option value="outlet" ${group.model === 'outlet' ? 'selected' : ''}>Outlet</option>
                        </select>
                    </td>
                    <td>
                        <select class="group-rooms" multiple size="3" style="width: auto; padding: 4px;">
                            ${roomOptions}
                        </select>
                    </td>
                    <td>
                        <select class="group-devices" multiple size="3" style="width: auto; padding: 4px;">
                            ${deviceOptions}
                        </select>
                    </td>
                    <td>
                        <input type="text" class="group-x10" value="${group.x10Code || ''}" style="width: auto; padding: 4px;">
                    </td>
                    <td>
                        <button onclick="saveGroup('${group.id}')" class="save-btn" style="padding: 4px 8px; margin-right: 5px;">Save</button>
                        <button onclick="deleteGroup('${group.id}')" class="delete-btn" style="padding: 4px 8px; background: #ef4444;">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (error) {
        console.error('Error loading group list:', error);
        showError('Failed to load group list: ' + error.message);
    }
}

async function saveGroup(groupId) {
    const row = document.querySelector(`tr[data-group-id="${groupId}"]`);
    if (!row) return;

    const groupName = row.querySelector('.group-name').value;
    const model = row.querySelector('.group-model').value;
    const rooms = Array.from(row.querySelector('.group-rooms').selectedOptions)
        .map(opt => parseInt(opt.value)); // Convert room IDs to integers
    const devices = Array.from(row.querySelector('.group-devices').selectedOptions)
        .map(opt => opt.value);
    const x10Code = row.querySelector('.group-x10').value;

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
                rooms: rooms,
                devices: devices,
                x10Code: x10Code
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
    const rooms = Array.from(document.getElementById('new-group-rooms')?.selectedOptions || [])
        .map(opt => parseInt(opt.value));  // Convert room IDs to integers
    const devices = Array.from(document.getElementById('new-group-devices')?.selectedOptions || [])
        .map(opt => opt.value);
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
                rooms: rooms,
                devices: devices,
                x10Code: x10Code || null
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to create group');
        }

        // Clear form
        document.getElementById('new-group-name').value = '';
        document.getElementById('new-group-x10').value = '';
        document.getElementById('new-group-rooms').selectedIndex = -1;
        document.getElementById('new-group-devices').selectedIndex = -1;

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