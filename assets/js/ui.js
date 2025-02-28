// assets/js/ui.js

function showError(message) {
    const errorElement = document.getElementById('error-message');
    errorElement.textContent = `Error: ${message}`;
    errorElement.style.display = 'block';
}

async function createTabs() {
    const tabsContainer = document.getElementById('tabs');
    const tabContents = document.getElementById('tab-contents');
    
    let tabsHtml = '';
    let contentsHtml = '';
    
    const savedTab = localStorage.getItem('selectedTab');
    
    for (const room of rooms) {
        if (room.id !== 1) {
            let tempInfo = '';
            try {
                const response = await apiFetch(`api/room-temperature?room=${room.id}`);
                const data = await response;
                if (data.success && data.thermometers) {
                    tempInfo = data.thermometers.map(therm => {
                        const displayName = therm.display_name || therm.name || 'Unknown Sensor';
                        return `<span class="room-temp-info" 
                                     onclick="showTempHistory('${therm.mac}', '${displayName}')" 
                                     title="${displayName}"
                                     style="cursor: pointer; margin-left: 10px;">
                            ${therm.temp}°F ${therm.humidity}%
                        </span>`;
                    }).join('');
                }
            } catch (error) {
                console.error('Error fetching temperature:', error);
            }
            
            // Use icon from database with fallback
            const room_icon = room.icon || 'fa-house';

            tabsHtml += `
                <button class="tab ${savedTab && savedTab === room.id.toString() ? 'active' : ''}" data-room="${room.id}">
                    <i class="fa-solid fa-xl ${room_icon}"></i>
                </button>`;
                
            contentsHtml += `
                <div class="tab-content ${savedTab && savedTab === room.id.toString() ? 'active' : ''}" data-room="${room.id}">
                    <h2 class="room-header">
                        <span><i class="fa-solid ${room_icon}"></i> ${room.room_name}</span>
                        ${tempInfo ? `<span class="room-temp-info">${tempInfo}</span>` : ''}
                    </h2>
                    <div class="device-grid" id="room-${room.id}-devices"></div>
                </div>`;
        }
    }
    
    // Keep the config tab for mobile view only
    tabsHtml += `
        <button class="tab ${!savedTab ? 'active' : ''}" data-room="config">
            <i class="fas fa-xl fa-cog"></i>
        </button>`;

    contentsHtml += generateConfigContent();
    
    tabsContainer.innerHTML = tabsHtml;
    tabContents.innerHTML = contentsHtml;
    
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.room));
    });

    if (savedTab) {
        console.log(`[${new Date().toLocaleTimeString()}] Loading saved tab: ${savedTab}`);
        switchTab(savedTab);
    }
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

}

function showDefaultRoomDevices() {
    const popup = document.createElement('div');
    popup.className = 'popup-overlay';
    popup.innerHTML = `
        <div class="popup-container">
            <div class="popup-header">
                <h3>All Devices</h3>
                <button onclick="this.closest('.popup-overlay').remove()" class="close-popup-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="popup-content">
                <div class="device-grid" id="all-devices-list">
                    <!-- Device cards will be inserted here -->
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(popup);

    loadAllDevices();
}

async function loadAllDevices() {
    try {
        const response = await apiFetch('api/all-devices');
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load devices');
        }

        const deviceGrid = document.getElementById('all-devices-list');
        deviceGrid.innerHTML = data.devices.map(device => {
            return createDeviceCard(device);
        }).join('');

    } catch (error) {
        console.error('Error loading devices:', error);
        showError('Failed to load devices: ' + error.message);
    }
}

async function saveDeviceDetails(deviceId) {
    const row = document.querySelector(`tr[data-device="${deviceId}"]`);
    if (!row) return;

    // Get selected rooms
    const selectedRooms = Array.from(row.querySelector('.rooms').selectedOptions)
        .map(option => parseInt(option.value));

    // Get selected groups
    const selectedGroups = Array.from(row.querySelector('.groups').selectedOptions)
        .map(option => parseInt(option.value));

    const data = {
        device: deviceId,
        preferredName: row.querySelector('.pref-name').value,
        rooms: selectedRooms,
        groups: selectedGroups,
        preferredPowerState: row.querySelector('.pref-power').value,
        preferredBrightness: row.querySelector('.pref-bright').value,
        preferredColorTem: row.querySelector('.pref-color').value,
        low: row.querySelector('.low').value,
        medium: row.querySelector('.medium').value,
        high: row.querySelector('.high').value
    };

    try {
        const response = await apiFetch('api/update-device-details', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response;
        if (!result.success) {
            throw new Error(result.error || 'Failed to update device');
        }

        // Add device to selected groups
        if (selectedGroups.length > 0) {
            for (const groupId of selectedGroups) {
                await apiFetch('api/update-device-group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        device: deviceId,
                        action: 'join',
                        groupId: groupId
                    })
                });
            }
        }

        // Remove device from unselected groups
        const allGroupOptions = Array.from(row.querySelector('.groups').options);
        const unselectedGroups = allGroupOptions
            .filter(option => !option.selected)
            .map(option => parseInt(option.value));

        if (unselectedGroups.length > 0) {
            await apiFetch('api/update-device-group', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device: deviceId,
                    action: 'leave'
                })
            });
        }

        // Reload the devices to show updated data
        await loadAllDevices();
        
    } catch (error) {
        console.error('Error saving device:', error);
        showError('Failed to save device: ' + error.message);
    }
}

function getCurrentRoomId() {
    const activeTab = document.querySelector('.tab.active');
    return activeTab ? activeTab.dataset.room : null;
}


function showDesktopConfig() {
    const popup = document.getElementById('config-popup-desktop');
    popup.style.display = 'block';
}

function hideDesktopConfig() {
    document.getElementById('config-popup-desktop').style.display = 'none';
}


function generateRoomTab(room, savedTab) {
    return `<button class="tab ${savedTab === room.id ? 'active' : ''}" 
            data-room="${room.id}">
            <i class="fa-solid ${room.icon}"></i> ${room.room_name}
        </button>`;
}

function generateRoomContent(room, tempInfo) {
    return `<div class="tab-content ${room.id === 1 ? '' : ''}" data-room="${room.id}">
        <div class="room-header">
            ${room.room_name}${tempInfo}
        </div>
        <div id="room-${room.id}-devices" class="device-grid"></div>
    </div>`;
}

function generateConfigTab(savedTab) {
    return `<button class="tab ${savedTab === 'config' ? 'active' : ''}" 
            data-room="config">
            <i class="fas fa-lg fa-cog"></i>
        </button>`;
}

function showServicesManagement() {
    const popup = document.createElement('div');
    popup.className = 'popup-overlay';
    popup.innerHTML = `
        <div class="popup-container">
            <div class="popup-header">
                <h3>System Services Management</h3>
                <button onclick="this.closest('.popup-overlay').remove()" class="close-popup-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="popup-content">
                <div class="room-cards-container" id="services-list">
                    <div class="loading-state">Loading services...</div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(popup);

    // Fetch service statuses
    fetch('api/service-status')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch service statuses');
            }
            return response.json();
        })
        .then(data => {
            const servicesList = document.getElementById('services-list');
            servicesList.innerHTML = data.services.map(service => `
                <div class="room-card expanded">
                    <div>
                        <span class="room-card-header">${service.title}</span>
                        <span class="service-status ${service.status === 'active' ? 'status-active' : 'status-inactive'}">
                            ${service.status}
                        </span>
                    </div>
                    <div class="room-card-content">
                        <div class="room-actions">
                            <button onclick="controlService('${service.name}', 'start')" ${service.status === 'active' ? 'disabled' : ''}>
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button onclick="controlService('${service.name}', 'stop')" ${service.status !== 'active' ? 'disabled' : ''}>
                                <i class="fas fa-stop"></i> Stop
                            </button>
                            <button onclick="controlService('${service.name}', 'restart')">
                                <i class="fas fa-redo"></i> Restart
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        })
        .catch(error => {
            console.error('Error:', error);
            const servicesList = document.getElementById('services-list');
            servicesList.innerHTML = `<div class="error-message">${error.message}</div>`;
        });
}

async function controlService(serviceName, action) {
    try {
        const response = await fetch('api/control-service', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ service: serviceName, action })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to control service');
        }

        // Reload services list
        showServicesManagement();
    } catch (error) {
        console.error('Error:', error);
        showError(error.message);
    }
}

function generateConfigContent() {
    return `<div class="tab-content" data-room="config">
        <h2 class="room-header">
            <span><i class="fas fa-cog"></i> Configuration</span>
        </h2>
        <div class="config-content">
            <button class="config-button" onclick="showDefaultRoomDevices()">
                <i class="fas fa-xl fa-list"></i>
                <span>Devices</span>
            </button>
            <button class="config-button" onclick="showGroupManagement()">
                <i class="fas fa-xl fa-object-group"></i>
                <span>Groups</span>
            </button>
            <button class="config-button" onclick="showRoomManagement()">
                <i class="fas fa-xl fa-home"></i>
                <span>Rooms</span>
            </button>
            <button class="config-button" onclick="showAllTempHistory()">
                <i class="fas fa-xl fa-temperature-half"></i>
                <span>Temperature & Humidity</span>
            </button>
            <button class="config-button" onclick="showThermometerManagement()">
                <i class="fas fa-xl fa-temperature-half"></i>
                <span>Sensors</span>
            </button>
            <button onclick="showRemoteManagement()" class="config-button">
                <i class="fas fa-xl fa-gamepad"></i>
                <span>Remotes</span>
            </button>
            <button onclick="showServicesManagement()" class="config-button">
                <i class="fas fa-xl fa-gears"></i>
                <span>Services</span>
            </button>
            <button class="config-button">
                <i class="fas fa-xl fa-user"></i>
                <span>Users</span>
            </button>
        </div>
    </div>
    <div class="config-popup-desktop" id="config-popup-desktop">
        <div class="config-content">
            <h2 class="room-header">
                <span><i class="fas fa-cog"></i> Configuration</span>
                <button onclick="hideDesktopConfig()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </h2>
            <div class="content">
                <button onclick="showDefaultRoomDevices()" class="config-button">
                    <i class="fas fa-xl fa-list"></i>
                    <span>Devices</span>
                </button>
                <button onclick="showGroupManagement()" class="config-button">
                    <i class="fas fa-xl fa-object-group"></i>
                    <span>Groups</span>
                </button>
                <button onclick="showRoomManagement()" class="config-button">
                    <i class="fas fa-xl fa-home"></i>
                    <span>Rooms</span>
                </button>
                <button onclick="showAllTempHistory()" class="config-button">
                    <i class="fas fa-xl fa-temperature-half"></i>
                    <span>Temperature & Humidity</span>
                </button>
                <button onclick="showThermometerManagement()" class="config-button">
                    <i class="fas fa-xl fa-temperature-half"></i>
                    <span>Sensors</span>
                </button>
                <button onclick="showRemoteManagement()" class="config-button">
                    <i class="fas fa-xl fa-gamepad"></i>
                    <span>Remotes</span>
                </button>
                <button onclick="showServicesManagement()" class="config-button">
                    <i class="fas fa-xl fa-gears"></i>
                    <span>Services</span>
                </button>
                <button class="config-button">
                    <i class="fas fa-xl fa-user"></i>
                    <span>Users</span>
                </button>
            </div>
        </div>
    </div>
    <button onclick="showDesktopConfig()" class="desktop-config-btn config-button">
        <i class="fas fa-cog"></i>
        <span>Configuration</span>
    </button>`;
}