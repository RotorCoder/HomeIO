// assets/js/config.js

async function showConfigMenu(deviceId) {
    const deviceElement = document.getElementById(`device-${deviceId}`);
    const popup = document.getElementById('config-popup');
    
    if (!deviceElement || !popup) {
        console.error('Required elements not found');
        return;
    }
    // Set the device name in the header
    const deviceName = deviceElement.dataset.fullGroupName || deviceElement.dataset.fullDeviceName;
    document.getElementById('config-device-title').textContent = deviceName;

    const model = deviceElement.dataset.model;
    const groupId = deviceElement.dataset.groupId;

    // Populate the basic form fields
    document.getElementById('config-device-id').value = deviceId;
    document.getElementById('config-device-name').value = deviceElement.dataset.fullGroupName || deviceElement.dataset.fullDeviceName;
    // Add brand field population
    document.getElementById('config-brand').value = deviceStates.get(deviceId)?.brand || 'Unknown';
    document.getElementById('config-model').value = model;
    
    // Reset group-related fields
    const groupActionSelect = document.getElementById('config-group-action');
    const groupNameInput = document.getElementById('config-group-name');
    if (groupActionSelect) {
        groupActionSelect.value = 'none';
    }
    if (groupNameInput) {
        groupNameInput.value = '';
    }

    // Reset group containers visibility
    const groupNameContainer = document.getElementById('group-name-container');
    const existingGroupsContainer = document.getElementById('existing-groups-container');
    if (groupNameContainer) {
        groupNameContainer.style.display = 'none';
    }
    if (existingGroupsContainer) {
        existingGroupsContainer.style.display = 'none';
    }
    
    // Populate rooms dropdown
    const roomSelect = document.getElementById('config-room');
    roomSelect.innerHTML = rooms.map(room => 
        `<option value="${room.id}">${room.room_name}</option>`
    ).join('');

    // Initialize X10 dropdowns and set up validation
    initializeX10Dropdowns();
    const validateX10 = setupX10CodeValidation();
    popup.dataset.validateX10 = 'true';

    try {
        // Load device config
        const configResponse = await apiFetch(`api/device-config?device=${deviceId}`);
        const configData = await configResponse.json();
        
        // Store config values
        const configValues = {
            room: configData.success ? configData.room : '',
            low: configData.success ? configData.low : '',
            medium: configData.success ? configData.medium : '',
            high: configData.success ? configData.high : '',
            preferredColorTem: configData.success ? configData.preferredColorTem : '',
            x10Code: configData.success ? configData.x10Code : ''
        };

        // Set room value
        document.getElementById('config-room').value = configValues.room;

        if (configValues.x10Code && configValues.x10Code.trim()) {
            const letter = configValues.x10Code.charAt(0).toLowerCase();
            const number = configValues.x10Code.substring(1);
            document.getElementById('config-x10-letter').value = letter;
            document.getElementById('config-x10-number').value = number;
        } else {
            // Set to blank options if x10Code is null or empty
            document.getElementById('config-x10-letter').value = '';
            document.getElementById('config-x10-number').value = '';
        }

        // Handle group vs regular device display
        const groupConfigElements = document.getElementById('group-config-elements');
        const regularConfigElements = document.getElementById('regular-config-elements');
        
        if (groupId) {
            console.log('Showing group members for group:', groupId);
            groupConfigElements.style.display = 'block';
            regularConfigElements.style.display = 'none';
            
            // Get and display group members
            const groupResponse = await apiFetch(`api/group-devices?groupId=${groupId}`);
            const groupData = await groupResponse.json();
            
            if (groupData.success && groupData.devices) {
                const membersHtml = groupData.devices.map(member => {
                    const memberName = member.device_name || member.device;
                    const displayName = memberName;
                        
                    return `
                        <div class="group-member" data-full-name="${memberName}">
                            <span class="member-name">${displayName}</span>
                            <span class="member-status">
                                ${member.powerState === 'on' ? 'On' : 'Off'} 
                                (${member.online ? 'Online' : 'Offline'})
                            </span>
                        </div>
                    `;
                }).join('');
                
                // Add group settings
                const settingsHtml = `
                    <div class="form-group">
                        <label>Low Brightness (%):</label>
                        <input type="number" id="config-low" min="1" max="100" value="${configValues.low}">
                    </div>
                    <div class="form-group">
                        <label>Medium Brightness (%):</label>
                        <input type="number" id="config-medium" min="1" max="100" value="${configValues.medium}">
                    </div>
                    <div class="form-group">
                        <label>High Brightness (%):</label>
                        <input type="number" id="config-high" min="1" max="100" value="${configValues.high}">
                    </div>
                    <div class="form-group">
                        <label>Preferred Color Temperature:</label>
                        <input type="number" id="config-color-temp" min="2000" max="9000" value="${configValues.preferredColorTem}">
                    </div>
                    <div class="group-members">
                        <h4>Group Members:</h4>
                        ${membersHtml}
                    </div>`;
                
                groupConfigElements.innerHTML = settingsHtml;

                // Update buttons for group devices
                document.querySelector('.buttons').innerHTML = `
                    <button type="button" class="delete-btn" onclick="deleteDeviceGroup(${groupId})">Delete Group</button>
                    <button type="button" class="cancel-btn" onclick="hideConfigMenu()">Cancel</button>
                    <button type="button" class="save-btn" onclick="saveDeviceConfig()">Save</button>
                `;
            }
        } else {
            console.log('Showing regular config - no group ID');
            groupConfigElements.style.display = 'none';
            regularConfigElements.style.display = 'block';
            
            // Set values for regular device
            document.getElementById('config-low').value = configValues.low;
            document.getElementById('config-medium').value = configValues.medium;
            document.getElementById('config-high').value = configValues.high;
            document.getElementById('config-color-temp').value = configValues.preferredColorTem;
            
            // Regular device buttons
            document.querySelector('.buttons').innerHTML = `
                <button type="button" class="cancel-btn" onclick="hideConfigMenu()">Cancel</button>
                <button type="button" class="save-btn" onclick="saveDeviceConfig()">Save</button>
            `;
            
            // Load available groups for this model
            loadAvailableGroups(model);
        }

        // Show the popup
        popup.style.display = 'block';
        
    } catch (error) {
        console.error('Configuration error:', error);
        showError('Failed to load device configuration: ' + error.message);
    }
}

function hideConfigMenu() {
    const popup = document.getElementById('config-popup');
    if (popup) {
        popup.style.display = 'none';
    }
}

async function saveDeviceConfig() {
    console.log('saveDeviceConfig called');
    console.log('Current group action:', window.groupAction);

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
            
        const config = {
            device: deviceId,
            room: document.getElementById('config-room').value,
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
        
        const configData = await configResponse.json();
        if (!configData.success) {
            throw new Error(configData.error || 'Failed to update device configuration');
        }
        
        const groupAction = document.getElementById('config-group-action').value;
        if (!groupId && groupAction && groupAction !== 'none') {
            console.log('Processing group action:', groupAction);
            
            const groupData = {
                device: deviceId,
                model: model,
                action: groupAction
            };
            
            if (groupAction === 'create') {
                const groupName = document.getElementById('config-group-name').value;
                if (!groupName) {
                    throw new Error('Group name is required');
                }
                groupData.groupName = groupName;
            } else if (groupAction === 'join') {
                const groupId = document.getElementById('config-existing-groups').value;
                if (!groupId) {
                    throw new Error('Group selection is required');
                }
                groupData.groupId = groupId;
            }

            console.log('Sending group update with data:', groupData);

            const groupResponse = await apiFetch('api/update-device-group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(groupData)
            });
            
            const groupResult = await groupResponse.json();
            if (!groupResult.success) {
                throw new Error(groupResult.error || 'Failed to update group');
            }
        }
        
        hideConfigMenu();
        updateDevices();
        
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
        const data = await response.json();
        
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
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to delete group');
        }
        
        hideConfigMenu();
        updateDevices();
        
    } catch (error) {
        showError('Failed to delete group: ' + error.message);
    }
}

async function checkX10CodeDuplicate(x10Code, currentDeviceId) {
    try {
        const response = await apiFetch(`api/check-x10-code?x10Code=${x10Code}&currentDevice=${currentDeviceId}`);
        const data = await response.json();
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