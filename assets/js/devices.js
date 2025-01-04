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

function createDeviceCard(device) {
    // Skip non-reference devices that are part of a group
    if (device.deviceGroup && device.device !== device.reference_device) {
        return '';
    }

    const isOnline = device.online ?? false;
    const deviceClass = isOnline ? 'device-online' : 'device-offline';
    const preferredPowerState = device.preferredPowerState || 'off';
    const preferredBrightness = device.preferredBrightness;
    const icon = getDeviceIcon(device.device_name, preferredPowerState);
    const supportedCmds = JSON.parse(device.supportCmds || '[]');
    
    const fullDeviceName = device.device_name;
    const fullGroupName = device.group_name;
    
    const displayName = fullGroupName ? 
        (fullGroupName.includes('-') ? fullGroupName.split('-')[1].trim() : fullGroupName) :
        (fullDeviceName.includes('-') ? fullDeviceName.split('-')[1].trim() : fullDeviceName);
                    
    let controlButtons = '';
    if (isOnline) {
        if (supportedCmds.includes('brightness')) {
            controlButtons = `
                <div class="device-controls">
                    <button onclick="sendCommand('${device.device}', 'turn', 'off', '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${preferredPowerState === 'off' ? 'active' : ''}">Off</button>
                    <button onclick="sendCommand('${device.device}', 'brightness', ${device.low}, '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${preferredPowerState === 'on' && preferredBrightness == device.low ? 'active' : ''}"
                            data-brightness="${device.low}">Low</button>
                    <button onclick="sendCommand('${device.device}', 'brightness', ${device.medium}, '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${preferredPowerState === 'on' && preferredBrightness == device.medium ? 'active' : ''}"
                            data-brightness="${device.medium}">Medium</button>
                    <button onclick="sendCommand('${device.device}', 'brightness', ${device.high}, '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${preferredPowerState === 'on' && preferredBrightness == device.high ? 'active' : ''}"
                            data-brightness="${device.high}">High</button>
                </div>`;
        } else {
            controlButtons = `
                <div class="device-controls">
                    <button onclick="sendCommand('${device.device}', 'turn', '${preferredPowerState === 'off' ? 'on' : 'off'}', '${device.model}', ${device.group_id || 'null'})"
                            class="btn">${preferredPowerState === 'off' ? 'Turn On' : 'Turn Off'}</button>
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

    return `
        <div id="device-${device.device}" 
            class="device-card ${deviceClass}" 
            data-supported-cmds='${device.supportCmds}'
            data-model="${device.model}"
            data-full-device-name="${fullDeviceName}"
            data-full-group-name="${fullGroupName || ''}"
            ${device.deviceGroup ? `data-group-id="${device.deviceGroup}"` : ''}>
            <div class="device-info">
                <div class="device-icon">
                    <i class="${icon}" style="color: ${iconColor}"></i>
                </div>
                <div class="device-details">
                    <h3>${displayName}</h3>
                    <p class="device-status">
                        ${statusText}
                    </p>
                </div>
                <button onclick="showConfigMenu('${device.device}')" class="config-btn">
                    <i class="fas fa-xl fa-cog"></i>
                </button>
            </div>
            ${controlButtons}
        </div>
    `;
}

function handleDevicesUpdate(devices) {
    if (devices.length === 0) return;
    
    const updatedRooms = new Set(devices.map(device => device.room));
    updatedRooms.forEach(roomId => {
        const roomGrid = document.getElementById(`room-${roomId}-devices`);
        if (roomGrid) roomGrid.innerHTML = '';
    });

    // Create map of group IDs to their reference devices
    const groupReferenceDevices = new Set(
        devices
            .filter(d => d.deviceGroup && d.group_name)
            .map(d => d.reference_device)
    );
    
    devices.forEach(device => {
        // Skip non-reference devices that are part of a group
        if (device.deviceGroup && device.device !== device.reference_device) {
            return;
        }
        
        const deviceHtml = createDeviceCard(device);
        
        if (device.room) {
            const roomGrid = document.getElementById(`room-${device.room}-devices`);
            if (roomGrid) {
                roomGrid.insertAdjacentHTML('beforeend', deviceHtml);
            }
        }
        
        deviceStates.set(device.device, {
            online: device.online ?? false,
            preferredPowerState: device.preferredPowerState || 'off',
            preferredBrightness: device.preferredBrightness,
            brand: device.brand
        });
    });
}

function updateDeviceUI(deviceId, state) {
    const deviceElement = document.getElementById(`device-${deviceId}`);
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
        } else {
            button.textContent = device.preferredPowerState === 'off' ? 'Turn On' : 'Turn Off';
        }
    });
}