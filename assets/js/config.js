// assets/js/config.js

// Configuration popup functions
function hideConfigMenu() {
    const popup = document.getElementById('config-popup');
    if (popup) {
        popup.style.display = 'none';
    }
}

// In config.js, update the showConfigMenu function

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

        // Update rooms multiselect
        const roomsSelect = document.getElementById('config-rooms');
        if (roomsSelect) {
            roomsSelect.innerHTML = rooms.map(room => 
                `<option value="${room.id}">${room.room_name}</option>`
            ).join('');

            // Set selected rooms
            if (configData.rooms) {
                Array.from(roomsSelect.options).forEach(option => {
                    option.selected = configData.rooms.includes(parseInt(option.value));
                });
            }
        }

        // Update groups multiselect
        const groupsSelect = document.getElementById('config-groups');
        if (groupsSelect && window.apiResponse && window.apiResponse.groups) {
            groupsSelect.innerHTML = window.apiResponse.groups
                .map(group => `<option value="${group.id}">${group.name}</option>`)
                .join('');

            // Set selected groups
            if (configData.groups) {
                Array.from(groupsSelect.options).forEach(option => {
                    option.selected = configData.groups.includes(parseInt(option.value));
                });
            }
        }

        // Initialize and set X10 code if exists
        initializeX10Dropdowns();
        if (configData.x10Code && configData.x10Code.trim()) {
            const letter = configData.x10Code.charAt(0).toLowerCase();
            const number = configData.x10Code.substring(1);
            setInputValue('config-x10-letter', letter);
            setInputValue('config-x10-number', number);
        }

        // Setup X10 validation
        const validateX10 = setupX10CodeValidation();
        popup.dataset.validateX10 = 'true';

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
    
    const letterSelect = document.getElementById('config-x10-letter');
    const numberSelect = document.getElementById('config-x10-number');
    let x10Code = null;
    if (letterSelect.value && numberSelect.value) {
        x10Code = letterSelect.value + numberSelect.value;
    }
    
    try {
        const formContainer = groupId ? 
            document.getElementById('group-config-elements') : 
            document.getElementById('regular-config-elements');
            
        // Get selected rooms
        const selectedRooms = Array.from(document.getElementById('config-rooms').selectedOptions)
            .map(option => parseInt(option.value));

        // Get selected groups
        const selectedGroups = Array.from(document.getElementById('config-groups').selectedOptions)
            .map(option => parseInt(option.value));

        const config = {
            device: deviceId,
            rooms: selectedRooms,
            groups: selectedGroups,
            preferredName: document.getElementById('config-preferred-name').value.trim(),
            low: parseInt(formContainer.querySelector('input[id$="config-low"]').value) || 0,
            medium: parseInt(formContainer.querySelector('input[id$="config-medium"]').value) || 0,
            high: parseInt(formContainer.querySelector('input[id$="config-high"]').value) || 0,
            preferredColorTem: parseInt(formContainer.querySelector('input[id$="config-color-temp"]').value) || 0,
            x10Code: x10Code
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

async function checkX10CodeDuplicate(x10Code, currentDeviceId) {
    try {
        const response = await apiFetch(`api/check-x10-code?x10Code=${x10Code}&currentDevice=${currentDeviceId}`);
        const data = await response;
        return data;
    } catch (error) {
        console.error('Error checking X10 code:', error);
        throw error;
    }
}

function setupX10CodeValidation() {
    const letterSelect = document.getElementById('config-x10-letter');
    const numberSelect = document.getElementById('config-x10-number');
    
    if (!letterSelect || !numberSelect) return;

    async function checkX10Selection() {
        const letter = letterSelect.value;
        const number = numberSelect.value;
        const deviceId = document.getElementById('config-device-id').value;

        if (letter && number) {
            const x10Code = letter + number;
            try {
                const duplicateCheck = await checkX10CodeDuplicate(x10Code, deviceId);
                if (duplicateCheck.isDuplicate) {
                    showConfigError(`X10 code ${x10Code.toUpperCase()} is already in use by device: ${duplicateCheck.deviceName}`);
                    return false;
                } else {
                    document.getElementById('config-error-message').style.display = 'none';
                    return true;
                }
            } catch (error) {
                console.error('Error checking X10 code:', error);
                showError('Failed to validate X10 code: ' + error.message);
                return false;
            }
        }
        return true;
    }

    letterSelect.addEventListener('change', checkX10Selection);
    numberSelect.addEventListener('change', checkX10Selection);

    return checkX10Selection;
}

function initializeX10Dropdowns() {
    const letterSelect = document.getElementById('config-x10-letter');
    const numberSelect = document.getElementById('config-x10-number');

    if (!letterSelect || !numberSelect) {
        console.error('X10 select elements not found');
        return;
    }

    letterSelect.innerHTML = '';
    numberSelect.innerHTML = '';
    
    letterSelect.appendChild(new Option('Select Letter', ''));
    numberSelect.appendChild(new Option('Select Number', ''));

    for (let i = 65; i <= 80; i++) {
        const letter = String.fromCharCode(i);
        const option = document.createElement('option');
        option.value = letter.toLowerCase();
        option.textContent = letter;
        letterSelect.appendChild(option);
    }

    for (let i = 1; i <= 16; i++) {
        const option = document.createElement('option');
        option.value = i.toString();
        option.textContent = i.toString();
        numberSelect.appendChild(option);
    }
}

function showConfigError(message) {
    const errorElement = document.getElementById('config-error-message');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}