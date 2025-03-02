// devices.js

function getDeviceIcon(deviceName, preferredPowerState = 'off') {
    deviceName = deviceName.toLowerCase();
    if (deviceName.includes('fan')) {
        return `fa-solid fa-2x fa-fan${preferredPowerState === 'on' ? ' fa-spin' : ''}`;
    } else if (deviceName.includes('tv') || deviceName.includes('glow')) {
        return 'fa-solid fa-2x fa-tv';
    } else if (deviceName.includes('light') || deviceName.includes('lamp')) {
        return 'fa-solid fa-2x fa-lightbulb';
    } else if (deviceName.includes('heater')) {
        return 'fa-solid fa-2x fa-temperature-arrow-up';
    } else if (deviceName.includes('filter')) {
        return 'fa-solid fa-2x fa-head-side-mask';
    } else if (deviceName.includes('dehumidifier')) {
        return 'fa-solid fa-2x fa-droplet-slash';
    } else if (deviceName.includes('humidifier')) {
        return 'fa-solid fa-2x fa-droplet';
    }
    return 'fa-solid fa-2x fa-plug';
}

function handleDevicesUpdate(devices) {
    if (devices.length === 0) return;
    
    // First, collect all rooms that need updating
    const updatedRooms = new Set();
    
    // Add rooms containing individual devices
    devices.forEach(device => {
        if (device.room_ids) {
            device.room_ids.split(',').forEach(roomId => updatedRooms.add(roomId));
        }
    });
    
    // Always include ALL rooms that have groups
    if (window.apiResponse && window.apiResponse.groups) {
        window.apiResponse.groups.forEach(group => {
            try {
                const groupRooms = JSON.parse(group.rooms || '[]');
                groupRooms.forEach(roomId => updatedRooms.add(roomId.toString()));
            } catch (e) {
                console.error('Error parsing group rooms:', e);
            }
        });
    }
    
    // Clear ALL affected room containers - this ensures rooms with only groups get cleared too
    updatedRooms.forEach(roomId => {
        const roomGrid = document.getElementById(`room-${roomId}-devices`);
        if (roomGrid) {
            // Clear the room grid completely
            roomGrid.innerHTML = '';
        }
    });

    // Create map of devices by device ID for quick lookup
    const deviceMap = new Map();
    devices.forEach(device => {
        deviceMap.set(device.device, device);
    });

    // Handle individual devices first
    devices.forEach(device => {
        // Skip devices that will be shown in groups unless explicitly set to show_in_room
        if (!device.group_id || device.show_in_room === 1) {
            const deviceHtml = createDeviceCard(device);
            
            // Add device to all its assigned rooms
            if (device.room_ids) {
                const roomIds = device.room_ids.split(',');
                roomIds.forEach(roomId => {
                    const roomGrid = document.getElementById(`room-${roomId}-devices`);
                    if (roomGrid) {
                        roomGrid.insertAdjacentHTML('beforeend', deviceHtml);
                    }
                });
            }
            
            deviceStates.set(device.device, {
                online: device.online ?? false,
                preferredPowerState: device.preferredPowerState || 'off',
                preferredBrightness: device.preferredBrightness,
                brand: device.brand
            });
        }
    });

    // In handleDevicesUpdate function in devices.js - modify the groups processing section
    if (window.apiResponse && window.apiResponse.groups) {
        //console.log('Processing all groups:', window.apiResponse.groups);
        window.apiResponse.groups.forEach(group => {
            // Parse devices array
            const groupDevices = Array.isArray(group.devices) ? 
                               group.devices : 
                               JSON.parse(group.devices || '[]');
            
            if (groupDevices.length === 0) {
                console.log('Skipping group - no devices:', group.name);
                return;
            }
    
            // Parse rooms array from the rooms column
            const groupRooms = JSON.parse(group.rooms || '[]');
            if (groupRooms.length === 0) {
                console.log('Skipping group - no rooms:', group.name);
                return;
            }
    
            const groupDeviceObjects = groupDevices
                .map(deviceId => deviceMap.get(deviceId))
                .filter(d => d);
    
            if (groupDeviceObjects.length === 0) {
                return;
            }
    
            const primaryDevice = groupDeviceObjects[0];
            const groupDevice = {
                device: group.id,
                device_name: group.name,
                model: 'group',
                room_ids: groupRooms.join(','), // Use rooms from group.rooms
                room_names: groupRooms.map(roomId => {
                    const room = rooms.find(r => r.id === roomId);
                    return room ? room.room_name : '';
                }).join(','),
                online: groupDevices.some(deviceId => {
                    const device = deviceMap.get(deviceId);
                    return device && device.online;
                }),
                supportCmds: primaryDevice.supportCmds,
                preferredPowerState: primaryDevice.preferredPowerState || 'off',
                preferredBrightness: primaryDevice.preferredBrightness,
                low: primaryDevice.low,
                medium: primaryDevice.medium,
                high: primaryDevice.high,
                brand: primaryDevice.brand,
                isGroup: true
            };
    
            // Add group to all its assigned rooms
            groupRooms.forEach(roomId => {
                const roomGrid = document.getElementById(`room-${roomId}-devices`);
                if (roomGrid) {
                    const groupHtml = createDeviceCard(groupDevice);
                    roomGrid.insertAdjacentHTML('beforeend', groupHtml);
                }
            });
    
            deviceStates.set(group.id, {
                online: groupDevice.online,
                preferredPowerState: groupDevice.preferredPowerState,
                preferredBrightness: groupDevice.preferredBrightness,
                brand: groupDevice.brand
            });
        });
    }
}

function createDeviceCard(device) {
    const isOnline = device.online ?? false;
    const deviceClass = isOnline ? 'device-online' : 'device-offline';
    const preferredPowerState = device.preferredPowerState || 'off';
    const preferredBrightness = device.preferredBrightness;
    const icon = getDeviceIcon(device.device_name, preferredPowerState);
    const supportedCmds = JSON.parse(device.supportCmds || '[]');
    
    // Don't split group names
    const displayName = device.isGroup ? device.device_name : (
        device.preferredName || (device.device_name.includes('-') ? device.device_name.split('-')[1].trim() : device.device_name)
    );
                    
    let controlButtons = '';
    if (isOnline) {
        if (supportedCmds.includes('brightness')) {
            controlButtons = `
                <div class="device-controls">
                    <button onclick="sendCommand('${device.isGroup ? 'group' : 'device'}', '${device.device}', 'turn', 'off')" 
                            class="btn ${preferredPowerState === 'off' ? 'active' : ''}">Off</button>
                    <button onclick="sendCommand('${device.isGroup ? 'group' : 'device'}', '${device.device}', 'brightness', ${device.low})" 
                            class="btn ${preferredPowerState === 'on' && preferredBrightness == device.low ? 'active' : ''}"
                            data-brightness="${device.low}">Low</button>
                    <button onclick="sendCommand('${device.isGroup ? 'group' : 'device'}', '${device.device}', 'brightness', ${device.medium})" 
                            class="btn ${preferredPowerState === 'on' && preferredBrightness == device.medium ? 'active' : ''}"
                            data-brightness="${device.medium}">Medium</button>
                    <button onclick="sendCommand('${device.isGroup ? 'group' : 'device'}', '${device.device}', 'brightness', ${device.high})" 
                            class="btn ${preferredPowerState === 'on' && preferredBrightness == device.high ? 'active' : ''}"
                            data-brightness="${device.high}">High</button>
                </div>`;
        } else {
            controlButtons = `
                <div class="device-controls">
                    <button onclick="sendCommand('${device.isGroup ? 'group' : 'device'}', '${device.device}', 'turn', '${preferredPowerState === 'off' ? 'on' : 'off'}')"
                            class="btn toggle-btn">${preferredPowerState === 'off' ? 'Turn On' : 'Turn Off'}</button>
                </div>`;
        }
    }

    let statusText = '';
    if (!isOnline) {
        statusText = 'Offline';
    } else if (preferredPowerState === 'off') {
        statusText = 'Off';
    } else if (supportedCmds.includes('brightness') && preferredBrightness) {
        statusText = `Brightness: ${preferredBrightness}%`;
    } else if (preferredPowerState === 'on') {
        statusText = 'On';
    }

    let iconColor = '#6b7280';
    if (isOnline) {
        iconColor = preferredPowerState === 'on' ? '#16a34a' : '#92400e';
    }

    const deviceId = device.device;
    return `
        <div id="device-${deviceId}" 
            class="device-card ${deviceClass}" 
            data-supported-cmds='${device.supportCmds}'
            data-model="${device.model}"
            data-full-device-name="${device.device_name}"
            ${device.isGroup ? `data-group-id="${device.device}"` : ''}
            data-full-group-name="${device.isGroup ? device.device_name : ''}">
            <div class="device-info">
                <div class="device-icon">
                    <i class="${icon}" style="color: ${iconColor}"></i>
                </div>
                <div class="device-details">
                    <h3>${device.isGroup ? '👥 ' : ''}${displayName}</h3>
                    <p class="device-status">
                        ${statusText}
                    </p>
                </div>
                <button onclick="showConfigMenu('${deviceId}')" class="config-btn">
                    <i class="fas fa-xl fa-cog"></i>
                </button>
            </div>
            ${controlButtons}
        </div>
    `;
}

function updateDeviceUI(deviceId, state, targetElement = null) {
    // Use the provided targetElement if given, otherwise find it by ID
    const deviceElement = targetElement || document.getElementById(`device-${deviceId}`);
    if (!deviceElement) return;

    const device = {...deviceStates.get(deviceId), ...state};
    const supportedCmds = JSON.parse(deviceElement.dataset.supportedCmds || '[]');
    
    const iconElement = deviceElement.querySelector('.device-icon i');
    if (iconElement) {
        const iconColor = device.online ? 
            (device.preferredPowerState === 'on' ? '#16a34a' : '#92400e') : 
            '#6b7280';
        iconElement.style.color = iconColor;
    }

    const statusElement = deviceElement.querySelector('.device-status');
    if (statusElement) {
        let statusText = '';
        if (!device.online) {
            statusText = 'Offline';
        } else if (device.preferredPowerState === 'off') {
            statusText = 'Off';
        } else if (supportedCmds.includes('brightness') && device.preferredBrightness) {
            statusText = `Brightness: ${device.preferredBrightness}%`;
        } else if (device.preferredPowerState === 'on') {
            statusText = 'On';
        }
        statusElement.textContent = statusText;
    }

    const buttons = deviceElement.querySelectorAll('.device-controls .btn');
    buttons.forEach(button => {
        button.disabled = !device.online;
        
        if (supportedCmds.includes('brightness')) {
            if (button.textContent === 'Off') {
                button.classList.toggle('active', device.preferredPowerState === 'off');
            } else {
                const brightnessValue = parseInt(button.dataset.brightness);
                button.classList.toggle('active', 
                    device.preferredPowerState === 'on' && device.preferredBrightness === brightnessValue);
            }
        } else if (button.classList.contains('toggle-btn')) {
            // Update the button text
            button.textContent = device.preferredPowerState === 'off' ? 'Turn On' : 'Turn Off';
            
            // Update the onclick handler with a new one
            button.onclick = function() {
                sendCommand(
                    deviceElement.dataset.groupId ? 'group' : 'device', 
                    deviceId, 
                    'turn', 
                    device.preferredPowerState === 'off' ? 'on' : 'off'
                );
            };
        }
    });
}