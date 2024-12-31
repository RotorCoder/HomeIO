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
                if (data.success && data.temperature) {
                    tempInfo = `<span class="room-temp-info" onclick="showTempHistory('${data.mac}', '${room.room_name}')" style="cursor: pointer">
                        ${data.temperature}°F ${data.humidity}%
                    </span>`;
                }
            } catch (error) {
                console.error('Error fetching temperature:', error);
            }
            
            let room_icon;
            if (room.room_name.includes('Office')) {
                room_icon = '<i class="fa-solid fa-2x fa-computer"></i>';
            } else if (room.room_name.includes('Bed')) {
                room_icon = '<i class="fa-solid fa-2x fa-bed"></i>';
            } else if (room.room_name.includes('Living')) {
                room_icon = '<i class="fa-solid fa-2x fa-couch"></i>';
            } else {
                room_icon = '<i class="fa-solid fa-2x fa-house"></i>';
            }

            tabsHtml += `
                <button class="tab ${savedTab && savedTab === room.id.toString() ? 'active' : ''}" data-room="${room.id}">
                    ${room_icon}
                </button>`;
            contentsHtml += `
                <div class="tab-content ${savedTab && savedTab === room.id.toString() ? 'active' : ''}" data-room="${room.id}">
                    <h2 class="room-header">
                        <span>${room.room_name}</span>
                        ${tempInfo ? `<span class="room-temp-info">${tempInfo}</span>` : ''}
                    </h2>
                    <div class="device-grid" id="room-${room.id}-devices"></div>
                </div>`;
        }
    }
    
    tabsHtml += `
        <button class="tab ${!savedTab ? 'active' : ''}" data-room="config">
            <i class="fas fa-2x fa-cog"></i>
        </button>`;

    contentsHtml += `
        <div class="tab-content ${!savedTab ? 'active' : ''}" data-room="config">
            <div>
                <button onclick="showDefaultRoomDevices()" class="mobile-config-btn">
                    Show Unassigned Devices
                </button>
            </div>
            <div class="device-grid" id="room-config-devices"></div>
        </div>`;

    contentsHtml += `
        <div class="config-section">
            <h2 class="config-header" onclick="toggleConfigContent()">
                <i class="fas fa-cog"></i>
                Configuration
                <i class="fas fa-chevron-down"></i>
            </h2>
            <div class="config-content" id="desktop-config-content">
                <div class="auto-refresh-control" style="margin-bottom: 10px;">
                    <label class="refresh-toggle" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="auto-refresh-toggle" style="cursor: pointer;">
                        <span>Auto-refresh</span>
                    </label>
                    <p class="refresh-time" id="last-update" style="margin: 5px 0;"></p>
                </div>
                <button id="refresh-button" class="refresh-button desktop-config-btn" onclick="manualRefresh()" style="margin-bottom: 10px;">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
                <div id="timing-info" class="device-grid" style="margin-top: 10px;"></div>
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

function toggleConfigContent() {
    const configContent = document.getElementById('desktop-config-content');
    const chevron = document.querySelector('.config-header .fa-chevron-down');
    configContent.classList.toggle('show');
    chevron.style.transform = configContent.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
}

function showDefaultRoomDevices() {
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