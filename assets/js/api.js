// assets/js/api.js

async function apiFetch(url, options = {}) {
    const defaultOptions = {
        headers: {
            'X-API-Key': API_KEY
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
    
    return fetch(url, mergedOptions);
}

async function fetchRooms() {
    try {
        const response = await apiFetch('api/rooms');
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Failed to fetch rooms');
        rooms = data.rooms;
        createTabs();
    } catch (error) {
        console.error('Error fetching rooms:', error);
        showError('Failed to load rooms: ' + error.message);
    }
}

async function loadInitialData() {
    try {
        const response = await apiFetch('api/devices?quick=true');
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
        const goveeResponse = await apiFetch('api/update-govee-devices');
        const goveeData = await goveeResponse.json();
        
        if (!goveeData.success) {
            throw new Error('Failed to update Govee devices: ' + goveeData.error);
        }

        const response = await apiFetch('api/devices?quick=false');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to update devices');
        }

        console.log(`[${new Date().toLocaleTimeString()}] Full update completed successfully`);
        
        if (data.devices && Array.isArray(data.devices)) {
            console.log('Updating devices:', data.devices.length);
            handleDevicesUpdate(data.devices);
        }
        
        updateLastRefreshTime(data.updated);
        document.getElementById('error-message').style.display = 'none';
        
    } catch (error) {
        console.error(`[${new Date().toLocaleTimeString()}] Full update error:`, error);
        showError(error.message);
    } finally {
        setRefreshing(false);
    }
}

async function updateBackgroundDevices() {
    if (isRefreshing) return;
    
    const currentRoomId = getCurrentRoomId();
    if (!currentRoomId) return;
    
    try {
        const response = await apiFetch(`api/devices?exclude_room=${currentRoomId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to update background devices');
        }

        handleDevicesUpdate(data.devices);
    } catch (error) {
        console.error('Background update error:', error);
    }
}

async function sendCommand(deviceId, command, value, model, groupId = null) {
    const previousState = {...deviceStates.get(deviceId)};
    let devicesToUpdate = [deviceId];
    const deviceElement = document.getElementById(`device-${deviceId}`);
    
    try {
        if (groupId) {
            const response = await apiFetch(`api/group-devices?groupId=${groupId}`);
            const data = await response.json();
            if (data.success) {
                devicesToUpdate = data.devices.map(d => d.device);
            } else {
                throw new Error('Failed to fetch group devices');
            }
        }

        const dbUpdatePromises = devicesToUpdate.map(deviceId => 
            apiFetch('api/update-device-state', {
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

        const newState = command === 'brightness' ? 
            { ...previousState, powerState: 'on', brightness: value } :
            { ...previousState, powerState: value };

        devicesToUpdate.forEach(deviceId => {
            deviceStates.set(deviceId, {...newState});
            updateDeviceUI(deviceId, newState);
        });

        const commandPromises = devicesToUpdate.map(deviceId => {
            const deviceElem = document.getElementById(`device-${deviceId}`);
            const cmd = {
                name: command,
                value: value
            };
            
            return apiFetch('api/send-command', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device: deviceId,
                    model: model,
                    cmd: cmd,
                    brand: deviceStates.get(deviceId)?.brand || 'unknown'
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
        
        try {
            const revertPromises = devicesToUpdate.map(deviceId =>
                apiFetch('api/update-device-state', {
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