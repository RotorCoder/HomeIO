<?php require_once __DIR__ . '/config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomeIO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
    <link rel="manifest" href="assets/images/site.webmanifest">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        
            <div class="timing-info" id="timing-info" style="display: none;">
                <button class="show-timing" onclick="toggleTimingDetails()">
                    <i class="fas fa-chevron-down timing-toggle-icon"></i>
                    <span>Refresh Timing Details</span>
                </button>
                <div class="timing-details" id="timing-details"></div>
            </div>
    
            <div class="error-message" id="error-message"></div>
    
            <div class="header-controls" style="display: none;">
                <div class="auto-refresh-control">
                    <label class="refresh-toggle" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="auto-refresh-toggle" style="cursor: pointer;">
                        <span>Auto-refresh</span>
                    </label>
                </div>
                <button id="refresh-button" class="refresh-button" onclick="manualRefresh()">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
                <p class="refresh-time" id="last-update"></p>
            </div>
        
        <div id="tabs" class="tabs">
            <!-- Tabs will be dynamically inserted here -->
        </div>

        <div id="tab-contents">
            <!-- Tab content will be dynamically inserted here -->
        </div>
   
    </div>

    <script>
        let deviceStates = new Map();
        let isRefreshing = false;
        let rooms = [];
        let visibleUpdateInterval;
        let backgroundUpdateInterval;
        const QUICK_UPDATE_INTERVAL = 1000;     // 3 seconds for quick refresh
        const VISIBLE_UPDATE_INTERVAL = 30000;  // 30 seconds for full refresh of tab devices
        const BACKGROUND_UPDATE_INTERVAL = 3000000;  // 300 seconds (5 minutes) for full refresh of all devices
        const refreshButton = document.getElementById('refresh-button');

        async function fetchRooms() {
            try {
                const response = await fetch('api/get_rooms.php');
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'Failed to fetch rooms');
                rooms = data.rooms;
                createTabs();
            } catch (error) {
                console.error('Error fetching rooms:', error);
                showError('Failed to load rooms: ' + error.message);
            }
        }

        function createTabs() {
    const tabsContainer = document.getElementById('tabs');
    const tabContents = document.getElementById('tab-contents');
    
    let tabsHtml = '';
    let contentsHtml = '';
    
    // Get saved tab
    const savedTab = localStorage.getItem('selectedTab');
    
    // Add room tabs and content, excluding room 1
    rooms.forEach((room, index) => {
        if (room.id !== 1) {  // Skip room 1 (default room)
            tabsHtml += `
                <button class="tab ${savedTab && savedTab === room.id.toString() ? 'active' : ''}" data-room="${room.id}">
                    ${room.room_name}
                </button>`;
            contentsHtml += `
                <div class="tab-content ${savedTab && savedTab === room.id.toString() ? 'active' : ''}" data-room="${room.id}">
                    <h2 class="room-header">${room.room_name}</h2>
                    <div class="device-grid" id="room-${room.id}-devices"></div>
                </div>`;
        }
    });
    
    // Add configuration tab for mobile
    tabsHtml += `
        <button class="tab ${!savedTab ? 'active' : ''}" data-room="config">
            <i class="fas fa-cog"></i>
        </button>`;

    // Add configuration content (visible in both mobile and desktop)
    contentsHtml += `
        <div class="tab-content ${!savedTab ? 'active' : ''}" data-room="config">
            <div>
                <button onclick="showDefaultRoomDevices()" class="mobile-config-btn">
                    Show Unassigned Devices
                </button>
            </div>
            <div class="device-grid" id="room-config-devices"></div>
        </div>`;

    // Add configuration section for desktop only
    contentsHtml += `
        <div class="config-section">
            <h2 class="config-header" onclick="toggleConfigContent()">
                <i class="fas fa-cog"></i>
                Configuration
                <i class="fas fa-chevron-down"></i>
            </h2>
            <div class="config-content" id="desktop-config-content">
                <button onclick="showDefaultRoomDevices()" class="desktop-config-btn">
                    Show Unassigned Devices
                </button>
            </div>
        </div>`;
    
    tabsContainer.innerHTML = tabsHtml;
    tabContents.innerHTML = contentsHtml;
    
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.room));
    });

    // If there's a saved tab, make sure we load its devices
    if (savedTab) {
        console.log(`[${new Date().toLocaleTimeString()}] Loading saved tab: ${savedTab}`);
        switchTab(savedTab);
    }
}

    function toggleConfigContent() {
    const configContent = document.querySelector('.config-content');
    const chevron = document.querySelector('.config-header .fa-chevron-down');
    
    configContent.classList.toggle('show');
    chevron.style.transform = configContent.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
}

    function showDefaultRoomDevices() {
    // Create and show popup
    const popup = document.createElement('div');
    popup.innerHTML = `
        <div class="popup-overlay" onclick="this.parentElement.remove()" style="
            background: #F3F4F6;
            padding: 20px;
        ">
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: white;
                padding: 20px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                align-items: center;
                z-index: 1002;
            ">
                <h3 style="margin: 0;">Unassigned Devices</h3>
                <button onclick="this.closest('.popup-overlay').parentElement.remove()" style="
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-size: 1.5rem;
                    padding: 5px;
                ">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="
                padding-top: 70px;
                height: 100vh;
                overflow-y: auto;
            ">
                <div class="device-grid" id="default-room-devices"></div>
            </div>
        </div>
    `;
    document.body.appendChild(popup);

    // Fetch and display default room devices
    fetch('api/get_devices.php?room=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const deviceGrid = document.getElementById('default-room-devices');
                deviceGrid.innerHTML = '';
                data.devices.forEach(device => {
                    deviceGrid.insertAdjacentHTML('beforeend', createDeviceCard(device));
                });
            }
        })
        .catch(error => {
            console.error('Error fetching default room devices:', error);
            showError('Failed to load default room devices');
        });
}

        function switchTab(roomId) {
    console.log(`[${new Date().toLocaleTimeString()}] Saving selected tab: ${roomId}`);
    if (roomId !== 'config') {
        console.log(`[${new Date().toLocaleTimeString()}] Saving selected tab: ${roomId}`);
        localStorage.setItem('selectedTab', roomId);
    }
    
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.room === roomId);
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.dataset.room === roomId);
    });
    
    // Always do a quick refresh when switching tabs
    console.log(`[${new Date().toLocaleTimeString()}] Tab switch - performing quick refresh for room ${roomId}`);
    fetch(`api/get_devices.php?room=${roomId}&quick=1`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`[${new Date().toLocaleTimeString()}] Tab switch quick refresh completed successfully`);
                handleDevicesUpdate(data.devices);
                updateLastRefreshTime(data.updated);
            }
        })
        .catch(error => {
            console.error(`[${new Date().toLocaleTimeString()}] Tab switch quick refresh error:`, error);
        });

    // Reset the timers if auto-refresh is enabled
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    if (autoRefreshToggle.checked) {
        resetUpdateTimers();
    }
}

        function updateLastRefreshTime(timestamp) {
            const date = timestamp ? new Date(timestamp) : new Date();
            const timeStr = date.toLocaleTimeString();
            document.getElementById('last-update').textContent = `Last updated: ${timeStr}`;
        }

        function getDeviceIcon(deviceName) {
            deviceName = deviceName.toLowerCase();
            if (deviceName.includes('fan')) {
                return 'fa-solid fa-2x fa-fan';
            } else if (deviceName.includes('tv')) {
                return 'fa-solid fa-2x fa-tv';
            } else if (deviceName.includes('light') || deviceName.includes('lamp')) {
                return 'fa-solid fa-2x fa-lightbulb';
            } else if (deviceName.includes('heater')) {
                return 'fa-solid fa-2x fa-temperature-high';
            } 
            return 'fa-solid fa-2x fa-plug';
        }

        function createDeviceCard(device) {
    const isOnline = device.online ?? false;
    const deviceClass = isOnline ? 'device-online' : 'device-offline';
    const icon = getDeviceIcon(device.device_name);
    const powerState = device.powerState || 'off';
    const supportedCmds = JSON.parse(device.supportCmds || '[]');
    
    // Store full names
    const fullDeviceName = device.device_name;
    const fullGroupName = device.group_name;
    
    // Display shortened version of either group name or device name
    const displayName = fullGroupName ? 
        (fullGroupName.includes('-') ? fullGroupName.split('-')[1].trim() : fullGroupName) :
        (fullDeviceName.includes('-') ? fullDeviceName.split('-')[1].trim() : fullDeviceName);
                    
    let controlButtons = '';
    if (isOnline) {
        if (supportedCmds.includes('brightness')) {
            controlButtons = `
                <div class="device-controls">
                    <button onclick="sendCommand('${device.device}', 'turn', 'off', '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${powerState === 'off' ? 'active' : ''}">Off</button>
                    <button onclick="sendCommand('${device.device}', 'brightness', ${device.low}, '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${powerState === 'on' && device.brightness == device.low ? 'active' : ''}"
                            data-brightness="${device.low}">Low</button>
                    <button onclick="sendCommand('${device.device}', 'brightness', ${device.medium}, '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${powerState === 'on' && device.brightness == device.medium ? 'active' : ''}"
                            data-brightness="${device.medium}">Medium</button>
                    <button onclick="sendCommand('${device.device}', 'brightness', ${device.high}, '${device.model}', ${device.group_id || 'null'})" 
                            class="btn ${powerState === 'on' && device.brightness == device.high ? 'active' : ''}"
                            data-brightness="${device.high}">High</button>
                </div>`;
        } else {
            controlButtons = `
                <div class="device-controls">
                    <button onclick="sendCommand('${device.device}', 'turn', '${powerState === 'off' ? 'on' : 'off'}', '${device.model}', ${device.group_id || 'null'})"
                            class="btn">${powerState === 'off' ? 'Turn On' : 'Turn Off'}</button>
                </div>`;
        }
    }

    // Status text logic
    let statusText = '';
    if (!isOnline) {
        statusText = 'Offline';
    } else if (powerState === 'off') {
        statusText = 'Off';
    } else if (supportedCmds.includes('brightness') && device.brightness) {
        statusText = `Brightness: ${device.brightness}%`;
    } else if (powerState === 'on') {
        statusText = 'On';
    }

    let iconColor = '#6b7280';
    if (isOnline) {
        iconColor = powerState === 'on' ? '#16a34a' : '#92400e';
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
            
            const currentDeviceIds = new Set();
            
            devices.forEach(device => {
                currentDeviceIds.add(device.device);
                const deviceHtml = createDeviceCard(device);
                
                if (device.room) {
                    const roomGrid = document.getElementById(`room-${device.room}-devices`);
                    if (roomGrid) {
                        roomGrid.insertAdjacentHTML('beforeend', deviceHtml);
                    }
                }
                
                deviceStates.set(device.device, {
                    online: device.online ?? false,
                    powerState: device.powerState,
                    brightness: device.brightness
                });
            });
        }

        async function sendCommand(deviceId, command, value, model, groupId = null) {
    // Store the current state before making changes
    const previousState = {...deviceStates.get(deviceId)};
    let devicesToUpdate = [deviceId];
    const deviceElement = document.getElementById(`device-${deviceId}`);
    
    try {
        // If this is a group command, get all devices in the group
        if (groupId) {
            const response = await fetch(`api/get_group_devices.php?groupId=${groupId}`);
            const data = await response.json();
            if (data.success) {
                devicesToUpdate = data.devices.map(d => d.device);
            } else {
                throw new Error('Failed to fetch group devices');
            }
        }

        // First, update the database for all devices
        const dbUpdatePromises = devicesToUpdate.map(deviceId => 
            fetch('api/update_device_state.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device: deviceId,
                    command: command,
                    value: value
                })
            }).then(response => response.json())
        );

        const dbResults = await Promise.all(dbUpdatePromises);
        if (dbResults.some(result => !result.success)) {
            throw new Error('Failed to update device state in database');
        }

        // Update UI state after successful database update
        const newState = command === 'brightness' ? 
            { ...previousState, powerState: 'on', brightness: value } :
            { ...previousState, powerState: value };

        // Update UI for all affected devices
        devicesToUpdate.forEach(deviceId => {
            deviceStates.set(deviceId, {...newState});
            updateDeviceUI(deviceId, newState);
        });

        // Now send commands to the devices
        const commandPromises = devicesToUpdate.map(deviceId => {
            const cmd = {
                name: command,
                value: value
            };
            
            return fetch('api/send_command.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device: deviceId,
                    model: model,
                    cmd: cmd,
                    brand: 'govee' // Default to govee if not specified
                })
            }).then(async response => {
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Command failed');
                }
                return data;
            });
        });

        await Promise.all(commandPromises);

    } catch (error) {
        console.error('Command error:', error);
        
        // Revert UI and database state
        try {
            // Revert database state
            const revertPromises = devicesToUpdate.map(deviceId =>
                fetch('api/update_device_state.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        device: deviceId,
                        command: command,
                        value: command === 'brightness' ? previousState.brightness : previousState.powerState
                    })
                })
            );

            await Promise.all(revertPromises);

            // Revert UI state
            devicesToUpdate.forEach(deviceId => {
                deviceStates.set(deviceId, previousState);
                updateDeviceUI(deviceId, previousState);
            });

        } catch (revertError) {
            console.error('Failed to revert state:', revertError);
        }

        showError('Command failed: ' + error.message);
    }
}
                
        function updateDeviceUI(deviceId, state) {
            const deviceElement = document.getElementById(`device-${deviceId}`);
            if (!deviceElement) return;
        
            const device = {...deviceStates.get(deviceId), ...state};
            const supportedCmds = JSON.parse(deviceElement.dataset.supportedCmds || '[]');
            const model = deviceElement.dataset.model;
            
            // Update icon color - only consider powerState
            const iconElement = deviceElement.querySelector('.device-icon i');
            if (iconElement) {
                const iconColor = device.online ? 
                    (device.powerState === 'on' ? '#16a34a' : '#92400e') : 
                    '#6b7280';
                iconElement.style.color = iconColor;
            }
        
            // Update status text - only show brightness if device is on
            const statusElement = deviceElement.querySelector('.device-status');
            if (statusElement) {
                let statusText = '';
                if (!device.online) {
                    statusText = 'Offline';
                } else if (device.powerState === 'off') {
                    statusText = 'Off';
                } else if (supportedCmds.includes('brightness') && device.brightness) {
                    statusText = `Brightness: ${device.brightness}%`;
                } else if (device.powerState === 'on') {
                    statusText = 'On';
                }
                statusElement.textContent = statusText;
            }
        
            // Update button states
            const buttons = deviceElement.querySelectorAll('.device-controls .btn');
            buttons.forEach(button => {
                if (supportedCmds.includes('brightness')) {
                    if (button.textContent === 'Off') {
                        button.classList.toggle('active', device.powerState === 'off');
                    } else {
                        const brightnessValue = parseInt(button.dataset.brightness);
                        button.classList.toggle('active', 
                            device.powerState === 'on' && device.brightness === brightnessValue);
                    }
                } else {
                    // For non-brightness devices
                    button.textContent = device.powerState === 'off' ? 'Turn On' : 'Turn Off';
                    button.onclick = () => sendCommand(deviceId, 'turn', device.powerState === 'off' ? 'on' : 'off', model);
                }
            });
        }

        function showError(message) {
            const errorElement = document.getElementById('error-message');
            errorElement.textContent = `Error: ${message}`;
            errorElement.style.display = 'block';
        }

        function updateTimingInfo(timing, rateLimits) {
            const timingInfo = document.getElementById('timing-info');
            const timingDetails = document.getElementById('timing-details');
            
            timingInfo.style.display = 'block';
            
            timingDetails.innerHTML = `
                ${rateLimits ? `
                    <div class="timing-row">
                        <span>API Rate Limit Remaining:</span>
                        <span>${rateLimits.apiRemaining !== null ? rateLimits.apiRemaining + ' requests' : 'N/A'}</span>
                    </div>
                    <div class="timing-row">
                        <span>Overall Rate Limit Remaining:</span>
                        <span>${rateLimits.xRemaining !== null ? rateLimits.xRemaining + ' requests' : 'N/A'}</span>
                    </div>
                ` : ''}
                <div class="timing-row">
                    <span>Get Devices:</span>
                    <span>${timing.devices?.duration || 0}ms</span>
                </div>
                <div class="timing-row">
                    <span>Get States:</span>
                    <span>${timing.states?.duration || 0}ms</span>
                </div>
                <div class="timing-row">
                    <span>Database Query:</span>
                    <span>${timing.database?.duration || 0}ms</span>
                </div>
                <div class="timing-row">
                    <span>Total Time:</span>
                    <span>${timing.total}ms</span>
                </div>
            `;
        }

        function toggleTimingDetails() {
            const timingInfo = document.getElementById('timing-info');
            timingInfo.classList.toggle('expanded');
        }

        function setRefreshing(refreshing) {
            isRefreshing = refreshing;
            refreshButton.disabled = refreshing;
            
            if (refreshing) {
                refreshButton.innerHTML = `
                    <i class="fas fa-sync-alt refresh-indicator"></i>
                    <span>Updating...</span>
                `;
            } else {
                refreshButton.innerHTML = `
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                `;
            }
        }

        async function loadInitialData() {
    try {
        // Just get initial device state from database without API updates
        const response = await fetch('api/get_devices.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load devices');
        }

        handleDevicesUpdate(data.devices);
        updateLastRefreshTime(data.updated);
        document.getElementById('error-message').style.display = 'none';
        
    } catch (error) {
        showError(error.message);
    }
}

async function updateDevices() {
    if (isRefreshing) return;
    
    setRefreshing(true);
    console.log(`[${new Date().toLocaleTimeString()}] Starting full device update`);

    try {
        // First update Govee devices
        console.log('Updating Govee devices...');
        const goveeResponse = await fetch('api/update_govee_devices.php');
        const goveeData = await goveeResponse.json();
        
        // Then update Hue devices
        console.log('Updating Hue devices...');
        const hueResponse = await fetch('api/update_hue_devices.php');
        const hueData = await hueResponse.json();
        
        // Finally get the current state of all devices
        console.log('Getting current device states...');
        const deviceResponse = await fetch('api/get_devices.php');
        const deviceData = await deviceResponse.json();
        
        if (!deviceData.success) {
            throw new Error(deviceData.error || 'Failed to get device states');
        }

        console.log(`[${new Date().toLocaleTimeString()}] Full update completed successfully`);
        handleDevicesUpdate(deviceData.devices);
        updateLastRefreshTime(deviceData.updated);
        
        // Combine timing info from all updates
        const timing = {
            govee: goveeData.timing,
            hue: hueData.timing,
            database: deviceData.timing,
            total: Date.now() - startTime
        };
        
        updateTimingInfo(timing);
        document.getElementById('error-message').style.display = 'none';
    } catch (error) {
        console.error(`[${new Date().toLocaleTimeString()}] Full update error:`, error);
        showError(error.message);
    } finally {
        setRefreshing(false);
    }
}

async function updateDevicesInRoom(roomId) {
    if (isRefreshing) return;
    
    try {
        // For room updates, just get current device states
        const response = await fetch(`api/get_devices.php?room=${roomId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to update devices');
        }

        handleDevicesUpdate(data.devices);
        updateLastRefreshTime(data.updated);
        document.getElementById('error-message').style.display = 'none';
    } catch (error) {
        showError(error.message);
    }
}

async function updateBackgroundDevices() {
    if (isRefreshing) return;
    
    const currentRoomId = getCurrentRoomId();
    if (!currentRoomId) return;
    
    try {
        // For background updates, just get current device states
        const response = await fetch(`api/get_devices.php?exclude_room=${currentRoomId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to update background devices');
        }

        handleDevicesUpdate(data.devices);
    } catch (error) {
        console.error('Background update error:', error);
    }
}

        function getCurrentRoomId() {
            const activeTab = document.querySelector('.tab.active');
            return activeTab ? activeTab.dataset.room : null;
        }
        
        function resetUpdateTimers() {
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    
    // Clear any existing intervals
    if (visibleUpdateInterval) clearInterval(visibleUpdateInterval);
    if (backgroundUpdateInterval) clearInterval(backgroundUpdateInterval);
    
    // Always set up quick refresh of current room devices
    visibleUpdateInterval = setInterval(() => {
        const currentRoomId = getCurrentRoomId();
        if (currentRoomId) {
            console.log(`[${new Date().toLocaleTimeString()}] Performing quick refresh for room ${currentRoomId}`);
            // Just get current states from database
            fetch(`api/get_devices.php?room=${currentRoomId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log(`[${new Date().toLocaleTimeString()}] Quick refresh completed successfully`);
                        handleDevicesUpdate(data.devices);
                        updateLastRefreshTime(data.updated);
                    }
                })
                .catch(error => {
                    console.error(`[${new Date().toLocaleTimeString()}] Quick refresh error:`, error);
                });
        }
    }, QUICK_UPDATE_INTERVAL);
    
    // Only set up full device updates if auto-refresh is enabled
    if (autoRefreshToggle.checked) {
        backgroundUpdateInterval = setInterval(() => {
            console.log(`[${new Date().toLocaleTimeString()}] Starting scheduled full refresh`);
            updateDevices();  // This does a full refresh including API calls
        }, VISIBLE_UPDATE_INTERVAL);
    }
}

async function updateDevices() {
    if (isRefreshing) return;
    
    setRefreshing(true);
    console.log(`[${new Date().toLocaleTimeString()}] Starting full device update`);

    try {
        const response = await fetch('api/get_devices.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to update devices');
        }

        console.log(`[${new Date().toLocaleTimeString()}] Full update completed successfully`);
        handleDevicesUpdate(data.devices);
        updateLastRefreshTime(data.updated);
        if (!data.quick) {
            updateTimingInfo(data.timing, data.rateLimits);
        }
        document.getElementById('error-message').style.display = 'none';
    } catch (error) {
        console.error(`[${new Date().toLocaleTimeString()}] Full update error:`, error);
        showError(error.message);
    } finally {
        setRefreshing(false);
    }
}

async function manualRefresh() {
    console.log(`[${new Date().toLocaleTimeString()}] Manual refresh requested`);
    updateDevices();
}
        
        function toggleAutoRefresh(enabled) {
            if (backgroundUpdateInterval) clearInterval(backgroundUpdateInterval);
            backgroundUpdateInterval = null;
            
            if (enabled) {
                // Start the full refresh interval
                backgroundUpdateInterval = setInterval(() => {
                    updateDevices();
                }, VISIBLE_UPDATE_INTERVAL);
            }
            
            // Always reset the quick refresh timer
            resetUpdateTimers();
        }

        function manualRefresh() {
            updateDevices();
        }

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
    
    console.log('Device Group ID:', groupId);
    
    // Populate the basic form fields
    document.getElementById('config-device-id').value = deviceId;
    document.getElementById('config-device-name').value = deviceElement.dataset.fullGroupName || deviceElement.dataset.fullDeviceName;
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
        const configResponse = await fetch(`api/get_device_config.php?device=${deviceId}`);
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
            const groupResponse = await fetch(`api/get_group_devices.php?groupId=${groupId}`);
            const groupData = await groupResponse.json();
            
            if (groupData.success && groupData.devices) {
                const membersHtml = groupData.devices.map(member => {
                    const memberName = member.device_name || member.device;
                    const displayName = memberName.includes('-') ? 
                        memberName.split('-')[1].trim() : 
                        memberName;
                        
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
                    <button type="button" class="delete-btn" onclick="deleteDeviceGroup(${groupId})" 
                            style="background: #ef4444; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-right: auto;">
                        Delete Group
                    </button>
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
    console.log('Current group action:', window.groupAction); // Debug log

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
        // Get the parent container
        const formContainer = groupId ? 
            document.getElementById('group-config-elements') : 
            document.getElementById('regular-config-elements');
            
        // Get values from the correct container
        const config = {
            device: deviceId,
            room: document.getElementById('config-room').value,
            low: parseInt(formContainer.querySelector('input[id$="config-low"]').value) || 0,
            medium: parseInt(formContainer.querySelector('input[id$="config-medium"]').value) || 0,
            high: parseInt(formContainer.querySelector('input[id$="config-high"]').value) || 0,
            preferredColorTem: parseInt(formContainer.querySelector('input[id$="config-color-temp"]').value) || 0,
            x10Code: x10Code
        };

        // Update device configuration
        const configResponse = await fetch('api/update_device_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        });
        
        const configData = await configResponse.json();
        if (!configData.success) {
            throw new Error(configData.error || 'Failed to update device configuration');
        }
        
        // Handle group operations - check window.groupAction directly from dropdown
        const groupAction = document.getElementById('config-group-action').value;
        if (!groupId && groupAction && groupAction !== 'none') {
            console.log('Processing group action:', groupAction); // Debug log
            
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

            console.log('Sending group update with data:', groupData); // Debug log

            const groupResponse = await fetch('api/update_device_group.php', {
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
        updateDevices(); // Refresh the devices
        
    } catch (error) {
        showError('Failed to update configuration: ' + error.message);
        console.error('Configuration error:', error);
    }
}
        
        function handleGroupActionChange() {
            const action = document.getElementById('config-group-action').value;
            const groupNameContainer = document.getElementById('group-name-container');
            const existingGroupsContainer = document.getElementById('existing-groups-container');
            
            // Set the window.groupAction when dropdown changes
            window.groupAction = action;
            
            groupNameContainer.style.display = action === 'create' ? 'block' : 'none';
            existingGroupsContainer.style.display = action === 'join' ? 'block' : 'none';
        }
        
        async function loadAvailableGroups(model) {
            try {
                const response = await fetch(`api/get_available_groups.php?model=${model}`);
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
                const response = await fetch('api/delete_device_group.php', {
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
                updateDevices(); // Refresh the devices
                
            } catch (error) {
                showError('Failed to delete group: ' + error.message);
            }
        }
        
        async function checkX10CodeDuplicate(x10Code, currentDeviceId) {
            try {
                const response = await fetch(`api/check_x10_code.php?x10Code=${x10Code}&currentDevice=${currentDeviceId}`);
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
        
            // Add change event listeners
            letterSelect.addEventListener('change', checkX10Selection);
            numberSelect.addEventListener('change', checkX10Selection);
        
            // Return the validation function to be used during form submission
            return checkX10Selection;
        }
        
        function initializeX10Dropdowns() {
            const letterSelect = document.getElementById('config-x10-letter');
            const numberSelect = document.getElementById('config-x10-number');
        
            if (!letterSelect || !numberSelect) {
                console.error('X10 select elements not found');
                return;
            }
        
            // Clear existing options
            letterSelect.innerHTML = '';
            numberSelect.innerHTML = '';
            
            letterSelect.appendChild(new Option('Select Letter', ''));
            numberSelect.appendChild(new Option('Select Number', ''));
        
            // Add letter options (A-P)
            for (let i = 65; i <= 80; i++) {
                const letter = String.fromCharCode(i);
                const option = document.createElement('option');
                option.value = letter.toLowerCase();
                option.textContent = letter;
                letterSelect.appendChild(option);
            }
        
            // Add number options (1-16)
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

        async function initialize() {
            await fetchRooms();
            await loadInitialData();
            
            const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
            autoRefreshToggle.checked = false;  // Ensure it starts unchecked
            autoRefreshToggle.addEventListener('change', (e) => {
                toggleAutoRefresh(e.target.checked);
            });
            
            resetUpdateTimers();
        }

        initialize();
    </script>
    
    <div id="config-popup" style="display: none;">
    <div class="config-popup">
        <div class="header">
            <h3 id="config-device-title">Device Configuration</h3>
            <button onclick="hideConfigMenu()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; padding: 5px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="content">
            <form id="device-config-form">
                <!-- Hidden inputs -->
                <input type="hidden" id="config-device-id">
                <input type="hidden" id="config-device-name">

                <div class="form-group">
                    <label>Model:</label>
                    <input type="text" id="config-model" readonly>
                </div>

                <div class="form-group" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>X10 Letter:</label>
                        <select id="config-x10-letter" style="width: 100%;"></select>
                    </div>
                    <div style="flex: 1;">
                        <label>X10 Number:</label>
                        <select id="config-x10-number" style="width: 100%;"></select>
                    </div>
                </div>

                <div id="config-error-message" class="config-error-message" style="display: none;"></div>

                <div class="form-group">
                    <label>Room:</label>
                    <select id="config-room"></select>
                </div>

                <!-- Regular device settings -->
                <div id="regular-config-elements">
                    <div class="form-group">
                        <label>Low Brightness (%):</label>
                        <input type="number" id="config-low" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>Medium Brightness (%):</label>
                        <input type="number" id="config-medium" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>High Brightness (%):</label>
                        <input type="number" id="config-high" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>Preferred Color Temperature:</label>
                        <input type="number" id="config-color-temp" min="2000" max="9000">
                    </div>
                    <div class="form-group">
                        <label>Device Grouping:</label>
                        <select id="config-group-action" onchange="handleGroupActionChange()">
                            <option value="none">No Group</option>
                            <option value="create">Create New Group</option>
                            <option value="join">Join Existing Group</option>
                        </select>
                    </div>
                    <div class="form-group" id="group-name-container" style="display: none;">
                        <label>Group Name:</label>
                        <input type="text" id="config-group-name">
                    </div>
                    <div class="form-group" id="existing-groups-container" style="display: none;">
                        <label>Select Group:</label>
                        <select id="config-existing-groups"></select>
                    </div>
                </div>

                <!-- Group device settings -->
                <div id="group-config-elements" style="display: none;">
                    <div class="form-group">
                        <label>Low Brightness (%):</label>
                        <input type="number" id="config-low" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>Medium Brightness (%):</label>
                        <input type="number" id="config-medium" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>High Brightness (%):</label>
                        <input type="number" id="config-high" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>Preferred Color Temperature:</label>
                        <input type="number" id="config-color-temp" min="2000" max="9000">
                    </div>
                    <div class="group-members">
                        <h4>Group Members</h4>
                        <div id="group-members-list">
                            <!-- Group members will be inserted here dynamically -->
                        </div>
                    </div>

                    <button type="button" class="delete-btn" onclick="deleteDeviceGroup(groupId)" 
                            style="background: #ef4444; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px;">
                        Delete Group
                    </button>
                </div>
            </form>
        </div>

        <div class="buttons">
            <button type="button" class="cancel-btn" onclick="hideConfigMenu()">Cancel</button>
            <button type="button" class="save-btn" onclick="saveDeviceConfig()">Save</button>
        </div>
    </div>
</div>


</body>
</html>