// js/api-secure.js

async function apiFetch(url, options = {}) {
    try {
        // Original functionality
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
        
        // Remove any API key from the headers
        if (mergedOptions.headers['X-API-Key']) {
            delete mergedOptions.headers['X-API-Key'];
        }
        
        // Construct the proxy URL
        const proxyUrl = `https://${window.location.host}/homeio/api-proxy.php?endpoint=${encodeURIComponent(url)}`;
        
        // Execute the fetch with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        mergedOptions.signal = controller.signal;
        
        const response = await fetch(proxyUrl, mergedOptions);
        clearTimeout(timeoutId);
        
        // Check for non-JSON response
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('Non-JSON response:', textResponse);
            
            throw new Error('API returned non-JSON response: ' + 
                (textResponse.length > 100 ? textResponse.substring(0, 100) + '...' : textResponse));
        }
        
        const data = await response.json();
        
        // Check for authentication error (new code)
        if (!response.ok && (response.status === 401 || (data && data.error && data.error.includes('Authentication')))) {
            console.log('Session may have expired, attempting to refresh...');
            
            // Attempt to refresh the session
            const refreshed = await refreshSession();
            if (refreshed) {
                // Retry the original request if session refresh was successful
                return apiFetch(url, options);
            }
        }
        
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

// In js/api-secure.js - update the refreshSession function

async function refreshSession() {
    try {
        // Get stored session data
        const session = getStoredSession();
        if (!session) return false;
        
        console.log('Attempting to refresh session...');
        
        // Call verify-session.php to refresh the session
        const response = await fetch('verify-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: session.username,
                token: session.token,
                refresh_token: session.refreshToken
            }),
            // Important: include credentials to send cookies
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Session refreshed successfully');
            // Update token if we received a new one
            if (data.new_token) {
                storeLoginSession(session.username, data.new_token, true);
            }
            return true;
        }
        
        console.log('Session refresh failed:', data.message);
        // Only reload if session is really expired or invalid
        if (data.message.includes('expired') || data.message.includes('invalid')) {
            window.location.href = 'login.php';
            return false;
        }
        
        return false;
    } catch (error) {
        console.error('Session refresh error:', error);
        return false;
    }
}

// Make sure this function is accessible (copied from login-session.js)
function getStoredSession() {
    // Check localStorage first, then sessionStorage
    let username = localStorage.getItem('homeio_username') || sessionStorage.getItem('homeio_username');
    let token = localStorage.getItem('homeio_token') || sessionStorage.getItem('homeio_token');
    let loginTime = localStorage.getItem('homeio_login_time') || sessionStorage.getItem('homeio_login_time');
    let refreshToken = getRefreshToken();
    
    if (!username || (!token && !refreshToken)) {
        return null;
    }
    
    // Optionally check session age (e.g., expire after 7 days)
    const MAX_SESSION_AGE = 30 * 24 * 60 * 60 * 1000; // 30 days in milliseconds
    if (loginTime && (Date.now() - parseInt(loginTime)) > MAX_SESSION_AGE) {
        clearLoginSession();
        return null;
    }
    
    return {
        username,
        token,
        refreshToken
    };
}

// Also copy this function from login-session.js
function getRefreshToken() {
    const cookieString = document.cookie;
    const cookies = cookieString.split(';');
    
    for (let i = 0; i < cookies.length; i++) {
        const cookie = cookies[i].trim();
        if (cookie.startsWith('homeio_refresh_token=')) {
            return cookie.substring('homeio_refresh_token='.length, cookie.length);
        }
    }
    
    return null;
}

// And this one
function storeLoginSession(username, token, rememberMe = false) {
    // Choose storage type based on remember me option
    const storage = rememberMe ? localStorage : sessionStorage;
    
    // Store login information
    storage.setItem('homeio_username', username);
    storage.setItem('homeio_token', token);
    storage.setItem('homeio_login_time', Date.now());
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
    // Escape the id to handle special characters like colons
    const escapedId = CSS.escape(id);
    
    // Look for the device in both the main UI and the All Devices popup
    const deviceElements = document.querySelectorAll(`#device-${escapedId}`);
    if (deviceElements.length === 0) return;

    // Store previous state
    const previousState = {...deviceStates.get(id)};
    
    // Optimistically update UI with new state
    const newState = command === 'brightness' ? 
        { ...previousState, preferredPowerState: 'on', preferredBrightness: value, online: true } :
        { ...previousState, preferredPowerState: value, online: true };
        
    // Update UI immediately
    deviceStates.set(id, newState);
    
    // Update all instances of this device card (both in main UI and in popup)
    deviceElements.forEach(element => {
        updateDeviceUI(id, newState, element);
    });
    
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
        
        // Update all instances again (for consistency)
        deviceElements.forEach(element => {
            updateDeviceUI(id, newState, element);
        });

    } catch (error) {
        console.error('Command error:', error);
        // Revert to previous state on error
        deviceStates.set(id, previousState);
        
        // Revert UI on error
        deviceElements.forEach(element => {
            updateDeviceUI(id, previousState, element);
        });
        
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