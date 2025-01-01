// assets/js/ui.js

function showError(message) {
    const errorElement = document.getElementById('error-message');
    errorElement.textContent = `Error: ${message}`;
    errorElement.style.display = 'block';
}

function setRefreshing(refreshing) {
    isRefreshing = refreshing;
    const refreshButton = document.getElementById('refresh-button');
    
    if (!refreshButton) {
        console.warn('Refresh button not found');
        return;
    }
    
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

function updateLastRefreshTime(timestamp) {
    const date = timestamp ? new Date(timestamp) : new Date();
    const timeStr = date.toLocaleTimeString();
    document.getElementById('last-update').textContent = `Last updated: ${timeStr}`;
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
                const data = await response.json();
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
            
            let room_icon;
            if (room.room_name.includes('Office')) {
                room_icon = 'fa-computer';
            } else if (room.room_name.includes('Bed')) {
                room_icon = 'fa-bed';
            } else if (room.room_name.includes('Living')) {
                room_icon = 'fa-couch';
            } else {
                room_icon = 'fa-house';
            }

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

    contentsHtml += `
        <div class="tab-content ${!savedTab ? 'active' : ''}" data-room="config">
            <div class="auto-refresh-control" style="margin-bottom: 10px;">
                <label class="refresh-toggle" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" id="auto-refresh-toggle" style="cursor: pointer;">
                    <span>Auto-refresh</span>
                </label>
                <p class="refresh-time" id="last-update" style="margin: 5px 0;"></p>
            </div>
            <button onclick="manualRefresh()" class="config-button">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh Govee Data</span>
            </button>
            <button onclick="showAllTempHistory()" class="config-button">
                <i class="fas fa-temperature-high"></i>
                <span>Thermometers</span>
            </button>
            <button onclick="showDefaultRoomDevices()" class="config-button">
                <i class="fas fa-plug"></i>
                <span>All Devices</span>
            </button>
        </div>`;
    
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

    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    if (autoRefreshToggle.checked) {
        resetUpdateTimers();
    }
}

function showDefaultRoomDevices() {
    const popup = document.createElement('div');
    popup.className = 'popup-overlay';
    popup.innerHTML = `
        <div class="popup-container">
            <div class="popup-header">
                <h3>Unassigned Devices</h3>
                <button onclick="this.closest('.popup-overlay').remove()" class="close-popup-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="popup-content">
                <div class="device-grid" id="default-room-devices"></div>
            </div>
        </div>
    `;
    document.body.appendChild(popup);

    apiFetch('api/devices?room=1&quick=true')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.devices) {
                const deviceGrid = document.getElementById('default-room-devices');
                deviceGrid.innerHTML = '';
                const unassignedDevices = data.devices.filter(device => device.room === 1);
                unassignedDevices.forEach(device => {
                    deviceGrid.insertAdjacentHTML('beforeend', createDeviceCard(device));
                });
                
                if (unassignedDevices.length === 0) {
                    deviceGrid.innerHTML = '<p style="text-align: center; padding: 20px;">No unassigned devices found.</p>';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching default room devices:', error);
            showError('Failed to load default room devices');
        });
}

function getCurrentRoomId() {
    const activeTab = document.querySelector('.tab.active');
    return activeTab ? activeTab.dataset.room : null;
}

function manualRefresh() {
    console.log(`[${new Date().toLocaleTimeString()}] Manual refresh requested`);
    updateDevices();
}

function showDesktopConfig() {
    const popup = document.getElementById('config-popup-desktop');
    popup.style.display = 'block';
    
    // Sync checkbox state
    const mobileCheckbox = document.getElementById('auto-refresh-toggle');
    const desktopCheckbox = document.getElementById('desktop-auto-refresh-toggle');
    desktopCheckbox.checked = mobileCheckbox.checked;
    
    // Sync last update time
    const mobileTime = document.getElementById('last-update').textContent;
    document.getElementById('desktop-last-update').textContent = mobileTime;
}

function hideDesktopConfig() {
    document.getElementById('config-popup-desktop').style.display = 'none';
}

// Update the existing toggleAutoRefresh function to sync both checkboxes
function toggleAutoRefresh(enabled) {
    const mobileCheckbox = document.getElementById('auto-refresh-toggle');
    const desktopCheckbox = document.getElementById('desktop-auto-refresh-toggle');
    
    mobileCheckbox.checked = enabled;
    desktopCheckbox.checked = enabled;
    
    if (backgroundUpdateInterval) clearInterval(backgroundUpdateInterval);
    backgroundUpdateInterval = null;
    
    if (enabled) {
        backgroundUpdateInterval = setInterval(() => {
            updateDevices();
        }, VISIBLE_UPDATE_INTERVAL);
    }
    
    localStorage.setItem('autoRefreshEnabled', enabled);
    resetUpdateTimers();
}