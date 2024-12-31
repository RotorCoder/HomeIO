// assets/js/main.js

// Global constants
const QUICK_UPDATE_INTERVAL = 200000000;     // 2 seconds for quick refresh
const VISIBLE_UPDATE_INTERVAL = 300000000;  // 300 seconds for full refresh of tab devices
const BACKGROUND_UPDATE_INTERVAL = 300000000;  // 3000 seconds for full refresh of all devices

// Global variables
let deviceStates = new Map();
let isRefreshing = false;
let rooms = [];
let visibleUpdateInterval;
let backgroundUpdateInterval;

function resetUpdateTimers() {
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    
    if (visibleUpdateInterval) clearInterval(visibleUpdateInterval);
    if (backgroundUpdateInterval) clearInterval(backgroundUpdateInterval);
    
    visibleUpdateInterval = setInterval(() => {
        console.log(`[${new Date().toLocaleTimeString()}] Performing quick refresh`);
        apiFetch(`api/devices?quick=true`)
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
    }, QUICK_UPDATE_INTERVAL);
    
    if (autoRefreshToggle.checked) {
        backgroundUpdateInterval = setInterval(() => {
            console.log(`[${new Date().toLocaleTimeString()}] Starting scheduled full refresh`);
            updateDevices();
        }, VISIBLE_UPDATE_INTERVAL);
    }
}

function toggleAutoRefresh(enabled) {
    if (backgroundUpdateInterval) clearInterval(backgroundUpdateInterval);
    backgroundUpdateInterval = null;
    
    if (enabled) {
        backgroundUpdateInterval = setInterval(() => {
            updateDevices();
        }, VISIBLE_UPDATE_INTERVAL);
    }
    
    resetUpdateTimers();
}

async function initialize() {
    await fetchRooms();
    await createTabs();
    await loadInitialData();
    
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    const storedAutoRefresh = localStorage.getItem('autoRefreshEnabled');
    autoRefreshToggle.checked = storedAutoRefresh === null ? true : storedAutoRefresh === 'true';
    
    autoRefreshToggle.addEventListener('change', (e) => {
        localStorage.setItem('autoRefreshEnabled', e.target.checked);
        toggleAutoRefresh(e.target.checked);
    });
    
    toggleAutoRefresh(autoRefreshToggle.checked);
    
    if (autoRefreshToggle.checked) {
        updateDevices();
    }
}

// Initialize the application
initialize();