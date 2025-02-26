// assets/js/config.js

// Configuration popup functions
function hideConfigMenu() {
    const popup = document.getElementById('config-popup');
    if (popup) {
        popup.style.display = 'none';
    }
}

async function showConfigMenu(deviceId) {
    const deviceElement = document.getElementById(`device-${deviceId}`);
    const popup = document.getElementById('config-popup');
    
    if (!deviceElement || !popup) {
        console.error('Required elements not found');
        return;
    }

    try {
        // Load device config first
        const configResponse = await apiFetch(`api/device-config?device=${deviceId}`);
        if (!configResponse.success) {
            throw new Error(configResponse.error || 'Failed to load device configuration');
        }
        const configData = configResponse;

        // Set form title and basic info
        document.getElementById('config-device-title').textContent = deviceElement.dataset.fullGroupName || deviceElement.dataset.fullDeviceName;
        document.getElementById('config-device-id').value = deviceId;
        document.getElementById('config-device-name').value = deviceElement.dataset.fullGroupName || deviceElement.dataset.fullDeviceName;
        document.getElementById('config-brand').value = deviceStates.get(deviceId)?.brand || 'Unknown';
        document.getElementById('config-model').value = deviceElement.dataset.model || '';

        // Safely set all form values with null checks
        const setInputValue = (elementId, value) => {
            const element = document.getElementById(elementId);
            if (element) {
                element.value = value || '';
            }
        };

        // Set preferred name
        setInputValue('config-preferred-name', configData.preferredName);

        // Set brightness values
        setInputValue('config-low', configData.low);
        setInputValue('config-medium', configData.medium);
        setInputValue('config-high', configData.high);
        setInputValue('config-color-temp', configData.preferredColorTem);

        const roomsList = document.getElementById('config-rooms');
        if (roomsList) {
            roomsList.innerHTML = rooms.map(room => 
                `<div class="checkbox-item">
                    <input type="checkbox" id="room-${room.id}" value="${room.id}" 
                        ${configData.rooms && configData.rooms.includes(parseInt(room.id)) ? 'checked' : ''}>
                    <label for="room-${room.id}">${room.room_name}</label>
                </div>`
            ).join('');
        }
        
        const groupsList = document.getElementById('config-groups');
        if (groupsList && window.apiResponse && window.apiResponse.groups) {
            groupsList.innerHTML = window.apiResponse.groups
                .map(group => 
                    `<div class="checkbox-item">
                        <input type="checkbox" id="group-${group.id}" value="${group.id}" 
                            ${configData.groups && configData.groups.includes(parseInt(group.id)) ? 'checked' : ''}>
                        <label for="group-${group.id}">${group.name}</label>
                    </div>`
                ).join('');
        }
        
                // Finally show the popup
                popup.style.display = 'block';
        
            } catch (error) {
                console.error('Configuration error:', error);
                showError('Failed to load device configuration: ' + error.message);
            }
        }

async function saveDeviceConfig() {
    const deviceId = document.getElementById('config-device-id').value;
    const deviceElement = document.getElementById(`device-${deviceId}`);
    const model = document.getElementById('config-model').value;
    const groupId = deviceElement?.dataset.groupId;
    
    try {
        const formContainer = groupId ? 
            document.getElementById('group-config-elements') : 
            document.getElementById('regular-config-elements');
            
        // Get selected rooms
        const selectedRooms = Array.from(
            document.querySelectorAll('#config-rooms input[type="checkbox"]:checked')
        ).map(cb => parseInt(cb.value));
        
        // Get selected groups
        const selectedGroups = Array.from(
            document.querySelectorAll('#config-groups input[type="checkbox"]:checked')
        ).map(cb => parseInt(cb.value));

        const config = {
            device: deviceId,
            rooms: selectedRooms,
            groups: selectedGroups,
            preferredName: document.getElementById('config-preferred-name').value.trim(),
            low: parseInt(formContainer.querySelector('input[id$="config-low"]').value) || 0,
            medium: parseInt(formContainer.querySelector('input[id$="config-medium"]').value) || 0,
            high: parseInt(formContainer.querySelector('input[id$="config-high"]').value) || 0,
            preferredColorTem: parseInt(formContainer.querySelector('input[id$="config-color-temp"]').value) || 0,
        };

        const configResponse = await apiFetch('api/update-device-config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        });
        
        if (!configResponse.success) {
            throw new Error(configResponse.error || 'Failed to update device configuration');
        }
        
        hideConfigMenu();
        loadInitialData();
        
    } catch (error) {
        showError('Failed to update configuration: ' + error.message);
        console.error('Configuration error:', error);
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
        loadInitialData();
        
    } catch (error) {
        showError('Failed to delete group: ' + error.message);
    }
}

function showConfigError(message) {
    const errorElement = document.getElementById('config-error-message');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}