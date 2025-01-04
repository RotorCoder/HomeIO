// api.js

async function apiFetch(url, options = {}) {
    try {
        const defaultOptions = {
            headers: {
                'X-API-Key': API_KEY,
                'Content-Type': 'application/json'
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        const response = await fetch(url, mergedOptions);
        return await response.json();
    } catch (error) {
        console.error('Fetch Error:', url, error);
        throw error;
    }
}

async function fetchRooms() {
    try {
        const data = await apiFetch('api/rooms');
        if (!data.success) {
            throw new Error(data.error || 'Failed to fetch rooms');
        }
        rooms = data.rooms;
        await createTabs();
    } catch (error) {
        console.error('Error fetching rooms:', error);
        showError('Failed to load rooms: ' + error.message);
        throw error;
    }
}

async function loadInitialData() {
    try {
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load devices');
        }
        handleDevicesUpdate(response.devices);
        document.getElementById('error-message').style.display = 'none';
    } catch (error) {
        console.error('Initial data load error:', error);
        showError(error.message);
        throw error;
    }
}

async function sendCommand(deviceId, command, value, model, groupId = null) {
    const previousState = {...deviceStates.get(deviceId)};
    let devicesToUpdate = [deviceId];
    
    try {
        if (groupId) {
            const response = await apiFetch(`api/group-devices?groupId=${groupId}`);
            if (response.success) {
                devicesToUpdate = response.devices.map(d => d.device);
            }
        }

        const dbUpdatePromises = devicesToUpdate.map(deviceId => 
            apiFetch('api/update-device-state', {
                method: 'POST',
                body: JSON.stringify({
                    device: deviceId,
                    command: command,
                    value: value
                })
            })
        );

        const dbResults = await Promise.all(dbUpdatePromises);
        if (dbResults.some(result => !result.success)) {
            throw new Error('Failed to update device state preferences');
        }

        const newState = command === 'brightness' ? 
            { ...previousState, preferredPowerState: 'on', preferredBrightness: value } :
            { ...previousState, preferredPowerState: value };

        devicesToUpdate.forEach(deviceId => {
            deviceStates.set(deviceId, {...newState});
            updateDeviceUI(deviceId, newState);
        });

    } catch (error) {
        console.error('Command error:', error);
        devicesToUpdate.forEach(deviceId => {
            deviceStates.set(deviceId, previousState);
            updateDeviceUI(deviceId, previousState);
        });
        showError('Failed to send command: ' + error.message);
    }
}

let updateTimer = null;

function startAutoUpdate() {
    if (updateTimer) {
        clearInterval(updateTimer);
    }
    updateTimer = setInterval(autoUpdate, 2000);
}

async function autoUpdate() {
    try {
        const [devicesResponse, roomsResponse] = await Promise.all([
            apiFetch('api/all-devices'),
            apiFetch('api/rooms')
        ]);

        if (!devicesResponse.success || !roomsResponse.success) {
            throw new Error('Failed to fetch updates');
        }

        // Update devices
        handleDevicesUpdate(devicesResponse.devices);

        // Update rooms and temperatures
        rooms = roomsResponse.rooms;
        for (const room of rooms) {
            if (room.id !== 1) {
                const tempResponse = await apiFetch(`api/room-temperature?room=${room.id}`);
                if (tempResponse.success && tempResponse.thermometers) {
                    const tempInfo = tempResponse.thermometers.map(therm => {
                        const displayName = therm.display_name || therm.name || 'Unknown Sensor';
                        return `<span class="room-temp-info" 
                                   onclick="showTempHistory('${therm.mac}', '${displayName}')" 
                                   title="${displayName}">
                            ${therm.temp}°F ${therm.humidity}%
                        </span>`;
                    }).join('');
                    
                    const tempElement = document.querySelector(`#room-${room.id} .room-temp-info`);
                    if (tempElement) {
                        tempElement.innerHTML = tempInfo;
                    }
                }
            }
        }
    } catch (error) {
        console.error('Auto-update error:', error);
    }
}

// Initialize auto-update when page loads
document.addEventListener('DOMContentLoaded', startAutoUpdate);