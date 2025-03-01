// api-secure.js - Secure replacement for api.js with improved error handling

async function apiFetch(url, options = {}) {
    try {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
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
        
        // Remove any API key from the headers to ensure it's not accidentally included
        if (mergedOptions.headers['X-API-Key']) {
            delete mergedOptions.headers['X-API-Key'];
        }
        
        // Construct the proxy URL
        const proxyUrl = `api-proxy.php?endpoint=${encodeURIComponent(url)}`;
        
        console.log(`Fetching API: ${url} via proxy`);
        
        // Execute the fetch with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        mergedOptions.signal = controller.signal;
        
        const response = await fetch(proxyUrl, mergedOptions);
        clearTimeout(timeoutId);
        
        // Check for non-JSON response before trying to parse
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // Try to get the text response for debugging
            const textResponse = await response.text();
            console.error('Non-JSON response:', textResponse);
            
            throw new Error('API returned non-JSON response: ' + 
                (textResponse.length > 100 ? textResponse.substring(0, 100) + '...' : textResponse));
        }
        
        const data = await response.json();
        
        // Check for API error
        if (!response.ok) {
            throw new Error(data.error || `HTTP error ${response.status}`);
        }
        
        return data;
    } catch (error) {
        console.error('Fetch Error for URL:', url, error);
        
        // Format error message based on error type
        let errorMessage = error.message || 'Unknown error';
        
        if (error.name === 'AbortError') {
            errorMessage = 'Request timed out';
        } else if (error instanceof SyntaxError) {
            errorMessage = 'Invalid JSON response';
        }
        
        // Return a standardized error object
        return {
            success: false,
            error: errorMessage,
            url: url,
            isApiError: true
        };
    }
}

async function fetchRooms() {
    try {
        const data = await apiFetch('rooms');
        
        // Check for API error response
        if (data.isApiError) {
            throw new Error(data.error || 'Failed to fetch rooms');
        }
        
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
        const response = await apiFetch('all-devices');
        
        // Check for API error response
        if (response.isApiError) {
            throw new Error(response.error || 'Failed to load devices');
        }
        
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

async function sendCommand(type, id, command, value) {
    const deviceElement = document.getElementById(`device-${id}`);
    if (!deviceElement) return;

    // Store previous state
    const previousState = {...deviceStates.get(id)};
    
    // Optimistically update UI with new state
    const newState = command === 'brightness' ? 
        { ...previousState, preferredPowerState: 'on', preferredBrightness: value, online: true } :
        { ...previousState, preferredPowerState: value, online: true };
        
    // Update UI immediately
    deviceStates.set(id, newState);
    updateDeviceUI(id, newState);
    
    try {
        const response = await apiFetch('queue-command', {
            method: 'POST',
            body: JSON.stringify({
                type,
                id,
                command,
                value
            })
        });

        // Check for API error response
        if (response.isApiError) {
            throw new Error(response.error || 'API communication error');
        }

        if (!response.success) {
            throw new Error(response.error || 'Failed to update device state preferences');
        }

        // Keep the successful state
        deviceStates.set(id, newState);
        updateDeviceUI(id, newState);

    } catch (error) {
        console.error('Command error:', error);
        // Revert to previous state on error
        deviceStates.set(id, previousState);
        updateDeviceUI(id, previousState);
        showError('Failed to send command: ' + error.message);
    }
}

let updateTimer = null;

function startAutoUpdate() {
    if (updateTimer) {
        clearInterval(updateTimer);
    }
    updateTimer = setInterval(autoUpdate, 10000); // Changed from 2s to 10s to reduce load
}

async function autoUpdate() {
    try {
        // Fetch devices first
        const devicesResponse = await apiFetch('all-devices');
        
        // Check for API error response
        if (devicesResponse.isApiError) {
            console.error('Auto-update error (devices):', devicesResponse.error);
            return;
        }

        if (!devicesResponse.success) {
            console.error('Auto-update error (devices):', devicesResponse.error || 'Unknown error');
            return;
        }

        // Fetch rooms
        const roomsResponse = await apiFetch('rooms');
        
        // Check for API error response
        if (roomsResponse.isApiError) {
            console.error('Auto-update error (rooms):', roomsResponse.error);
            return;
        }

        if (!roomsResponse.success) {
            console.error('Auto-update error (rooms):', roomsResponse.error || 'Unknown error');
            return;
        }

        // Update devices
        handleDevicesUpdate(devicesResponse.devices);

        // Update rooms
        rooms = roomsResponse.rooms;
    
        // Update temperature information for each room (if needed)
        for (const room of rooms) {
            if (room.id !== 1) {
                try {
                    const tempResponse = await apiFetch(`room-temperature?room=${room.id}`);
                    
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
                } catch (e) {
                    console.error(`Error updating temperature for room ${room.id}:`, e);
                }
            }
        }
    } catch (error) {
        console.error('Auto-update error:', error);
    }
}

// Initialize auto-update when page loads, with a short delay
document.addEventListener('DOMContentLoaded', () => {
    // Start auto-update after a 3 second delay to allow initial load to complete
    setTimeout(startAutoUpdate, 3000);
});