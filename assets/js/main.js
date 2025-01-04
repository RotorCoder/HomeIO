// assets/js/main.js

let deviceStates = new Map();
let rooms = [];

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