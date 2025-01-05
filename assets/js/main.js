// assets/js/main.js

let deviceStates = new Map();
let rooms = [];

async function loadInitialData() {
    try {
        const response = await apiFetch('api/all-devices');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load devices');
        }
        window.apiResponse = response;  // Store the full response
        handleDevicesUpdate(response.devices);
        document.getElementById('error-message').style.display = 'none';
    } catch (error) {
        console.error('Initial data load error:', error);
        showError(error.message);
        throw error;
    }
}

async function initialize() {
    try {
        await fetchRooms();
        await loadInitialData();
        startAutoUpdate();
    } catch (error) {
        console.error('Initialization error:', error);
        showError(error.message);
    }
}

initialize();