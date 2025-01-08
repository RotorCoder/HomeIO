// assets/js/groups.js

let currentGroupDevices = new Set();
let currentGroupId = null;

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

function showDevicePicker(groupId, groupName) {
    currentGroupId = groupId;
    document.getElementById('device-picker-group-name').textContent = groupName;
    document.getElementById('device-picker-popup').style.display = 'block';
    loadDeviceList(groupId);
}

function hideDevicePicker() {
    document.getElementById('device-picker-popup').style.display = 'none';
    currentGroupId = null;
    currentGroupDevices.clear();
}

async function loadDeviceList(groupId) {
    try {
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load devices');
        }

        // Reset current group devices
        currentGroupDevices.clear();

        // Find current group's devices
        const group = response.groups.find(g => g.id === groupId);
        if (group && group.devices) {
            const groupDevices = JSON.parse(group.devices);
            groupDevices.forEach(deviceId => currentGroupDevices.add(deviceId));
        }

        // Group devices by type
        const deviceGroups = {
            'Lights': [],
            'Fans': [],
            'Outlets': [],
            'Other': []
        };

        response.devices.forEach(device => {
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
                                       ${currentGroupDevices.has(device.device) ? 'checked' : ''}>
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

        // Get current group data
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error('Failed to fetch current group data');
        }

        const group = response.groups.find(g => g.id === currentGroupId);
        if (!group) {
            throw new Error('Group not found');
        }

        // Update group with new device selection
        await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentGroupId,
                name: group.name,
                model: 'group',
                devices: selectedDevices
            })
        });

        hideDevicePicker();
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error saving device selection:', error);
        showError('Failed to save device selection: ' + error.message);
    }
}

async function loadGroupList() {
    try {
        const response = await apiFetch('api/all-devices');
        console.log('API Response:', response); // For debugging
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to load devices and groups');
        }

        const groupList = document.getElementById('group-list');
        if (!groupList) {
            console.error('Group list element not found');
            return;
        }

        // Show what groups we found
        console.log('Found groups:', response.groups);

        // Create a map of device names for quick lookup
        const deviceMap = new Map(response.devices.map(d => [d.device, d.device_name]));

        // Process each group
        const groupsHtml = response.groups
            .filter(group => group.name !== 'Unassigned')  // Skip the Unassigned group
            .map(group => {
                // Get list of devices in this group
                const groupDevices = JSON.parse(group.devices || '[]');
                const deviceCount = groupDevices.length;
                
                // Get device names for display
                const deviceNames = groupDevices
                    .map(deviceId => deviceMap.get(deviceId))
                    .filter(name => name)
                    .join(', ');

                return `
                    <div class="group-card" data-group-id="${group.id}">
                        <div class="group-card-header" onclick="toggleGroupCard(${group.id})">
                            <div class="group-card-header-content">
                                <span>${group.name}</span>
                                <span class="device-count">${deviceCount} device${deviceCount !== 1 ? 's' : ''}</span>
                            </div>
                        </div>
                        <div class="group-card-content">
                            <div class="group-input-group">
                                <input type="text" class="group-input group-name" value="${group.name}" placeholder="Group Name">
                            </div>
                            <div class="device-list">
                                <small>${deviceNames || 'No devices'}</small>
                            </div>
                            <button onclick="showDevicePicker(${group.id}, '${group.name}')" class="devices-btn">
                                <i class="fas fa-lightbulb"></i> Manage Devices
                                <span class="device-count">${deviceCount}</span>
                            </button>
                            <div class="group-actions">
                                <button onclick="deleteGroup(${group.id})" class="group-delete-btn">
                                    <i class="fas fa-trash"></i> Delete Group
                                </button>
                                <button onclick="saveGroup(${group.id})" class="group-save-btn">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

        // Update the group list container
        groupList.innerHTML = groupsHtml;

    } catch (error) {
        console.error('Error loading group list:', error);
        showError('Failed to load group list: ' + error.message);
    }
}

async function saveGroup(groupId) {
    const groupCard = document.querySelector(`div[data-group-id="${groupId}"]`);
    if (!groupCard) return;

    const groupName = groupCard.querySelector('.group-name').value;
    if (!groupName.trim()) {
        showError('Group name cannot be empty');
        return;
    }

    try {
        // Get current group data
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error('Failed to fetch current group data');
        }

        const group = response.groups.find(g => g.id === groupId);
        if (!group) {
            throw new Error('Group not found');
        }

        // Update group
        const updateResponse = await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: groupId,
                name: groupName,
                model: 'group',
                devices: JSON.parse(group.devices || '[]') // Preserve existing devices
            })
        });

        if (!updateResponse.success) {
            throw new Error(updateResponse.error || 'Failed to update group');
        }

        // Reload UI
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error saving group:', error);
        showError('Failed to save group: ' + error.message);
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
            body: JSON.stringify({ groupId })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to delete group');
        }

        // Reload UI
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error deleting group:', error);
        showError('Failed to delete group: ' + error.message);
    }
}

function toggleGroupCard(groupId) {
    const card = document.querySelector(`div[data-group-id="${groupId}"]`);
    if (card) {
        event.stopPropagation();
        card.classList.toggle('expanded');
    }
}

function showNewGroupCard() {
    // Hide the add button
    const addButton = document.querySelector('.add-group-btn');
    if (addButton) {
        addButton.style.display = 'none';
    }

    // Show and reset the form
    const newGroupForm = document.getElementById('new-group-form');
    if (newGroupForm) {
        newGroupForm.style.display = 'block';
        document.getElementById('new-group-name').value = '';
    }
}

function cancelNewGroup() {
    // Hide the form
    const form = document.getElementById('new-group-form');
    if (form) {
        form.style.display = 'none';
    }
    
    // Show the add button
    const addButton = document.querySelector('.add-group-btn');
    if (addButton) {
        addButton.style.display = 'flex';
    }
}

async function addNewGroup() {
    const groupName = document.getElementById('new-group-name').value;
    if (!groupName.trim()) {
        showError('Please enter a group name');
        return;
    }

    try {
        // Create new group
        const response = await apiFetch('api/update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: groupName,
                model: 'group',
                devices: []
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to add group');
        }

        // Hide the form and show the add button
        document.getElementById('new-group-form').style.display = 'none';
        document.querySelector('.add-group-btn').style.display = 'flex';

        // Reload everything
        await Promise.all([
            loadGroupList(),
            loadInitialData()
        ]);

    } catch (error) {
        console.error('Error adding group:', error);
        showError('Failed to add group: ' + error.message);
    }
}

// Add event listeners when document loads
document.addEventListener('DOMContentLoaded', () => {
    // Prevent click events from reaching cards when clicking buttons
    document.getElementById('group-list')?.addEventListener('click', function(e) {
        if (e.target.closest('button')) {
            e.stopPropagation();
        }
    });
});