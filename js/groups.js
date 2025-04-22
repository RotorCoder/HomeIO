// js/groups.js

let currentGroupDevices = new Set();
let currentGroupId = null;

function showGroupManagement() {
    const popup = document.getElementById('group-popup');
    if (!popup) {
        console.error('Group popup template not found');
        return;
    }
    popup.style.display = 'block';
    loadGroups();
}

function hideGroupPopup() {
    document.getElementById('group-popup').style.display = 'none';
}

function showDevicePicker(groupId, groupName) {
    event.stopPropagation();
    currentGroupId = groupId;
    document.getElementById('device-picker-group-name').textContent = groupName;
    document.getElementById('device-picker-popup').style.display = 'block';
    loadDeviceList(groupId);
}

function hideDevicePicker() {
    document.getElementById('device-picker-popup').style.display = 'none';
    currentGroupId = null;
    currentGroupDevices.clear();
}

function toggleGroupCard(groupId) {
    event.stopPropagation();
    
    // First remove expanded class from all cards
    document.querySelectorAll('.room-card').forEach(card => {
        if (card.dataset.groupsGroupId !== groupId.toString()) {
            card.classList.remove('expanded');
            // Reset any expanded content
            const content = card.querySelector('.room-card-content');
            if (content) {
                content.style.display = 'none';
            }
        }
    });
    
    // Toggle the clicked card
    const clickedCard = document.querySelector(`div[data-groups-group-id="${groupId}"]`);
    if (clickedCard) {
        clickedCard.classList.toggle('expanded');
        const content = clickedCard.querySelector('.room-card-content');
        if (content) {
            content.style.display = clickedCard.classList.contains('expanded') ? 'block' : 'none';
        }
    }
}

function showNewGroupCard() {
    // Get references to the elements
    const addButton = document.querySelector('button[onclick="showNewGroupCard()"]');
    const groupList = document.getElementById('group-list');
    
    if (addButton) {
        addButton.style.setProperty('display', 'none', 'important');
    }

    // Check if form already exists
    let newGroupForm = document.getElementById('new-group-form');
    
    // If form doesn't exist, create it
    if (!newGroupForm) {
        newGroupForm = document.createElement('div');
        newGroupForm.id = 'new-group-form';
        newGroupForm.className = 'room-card';
        newGroupForm.innerHTML = `
            <div class="room-card-content">
                <div class="room-input-group">
                    <input type="text" id="new-group-name" placeholder="Group Name" class="room-input">
                </div>
                <div class="room-actions">
                    <button onclick="cancelNewGroup()" class="room-delete-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button onclick="saveNewGroup()" class="room-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        `;
    }

    // Reset form fields
    const nameInput = newGroupForm.querySelector('#new-group-name');
    if (nameInput) nameInput.value = '';

    // Show the form
    newGroupForm.style.display = 'block';
    newGroupForm.classList.add('expanded');

    // Add form to group list
    if (groupList) {
        groupList.appendChild(newGroupForm);
    } else {
        console.error('Group list container not found');
    }

    // Ensure the form is visible
    newGroupForm.scrollIntoView({ behavior: 'smooth' });
    
    // Hide the add button
    if (addButton) {
        addButton.style.display = 'none';
    }
}

function cancelNewGroup() {
    // Hide the form
    const form = document.getElementById('new-group-form');
    if (form) {
        form.style.display = 'none';
    }
    
    // Show the add button
    const addButton = document.querySelector('button[onclick="showNewGroupCard()"]');
    if (addButton) {
        addButton.style.removeProperty('display');
    }
}

async function saveGroup(groupId) {
    const card = document.querySelector(`div[data-groups-group-id="${groupId}"]`);
    if (!card) return;
    
    const groupName = card.querySelector('.group-name').value;
    
    try {
        // First get current group data
        const getGroupResponse = await apiFetch('all-devices');
        if (!getGroupResponse.success) {
            throw new Error('Failed to fetch current group data');
        }

        const currentGroup = getGroupResponse.groups.find(g => g.id.toString() === groupId.toString());
        if (!currentGroup) {
            throw new Error('Group not found');
        }

        // Update group while preserving existing data
        const response = await apiFetch('update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: groupId,
                name: groupName,
                model: currentGroup.model || 'group',
                devices: currentGroup.devices ? JSON.parse(currentGroup.devices) : [],
                rooms: currentGroup.rooms ? JSON.parse(currentGroup.rooms) : []
            })
        });

        if (!response.success) {
            throw new Error(response.error || 'Failed to update group');
        }

        await loadGroups();
        await loadInitialData();

    } catch (error) {
        console.error('Error saving group:', error);
        showError('Failed to save group: ' + error.message);
    }
}

async function deleteGroup(groupId) {
    event.stopPropagation();
    if (!confirm('Are you sure you want to delete this group? This cannot be undone.')) {
        return;
    }

    try {
        const response = await apiFetch('delete-device-group', {
            method: 'POST',
            body: JSON.stringify({ groupId })
        });
        
        if (!response.success) {
            throw new Error('Failed to delete group');
        }

        await loadGroups();

    } catch (error) {
        console.error('Error deleting group:', error);
        showError('Failed to delete group: ' + error.message);
    }
}

async function moveGroup(groupId, direction) {
    event.stopPropagation();
    
    const currentCard = document.querySelector(`div[data-groups-group-id="${groupId}"]`);
    if (!currentCard) return;

    const allCards = Array.from(document.querySelectorAll('.room-card'))
        .sort((a, b) => parseInt(a.dataset.tabOrder) - parseInt(b.dataset.tabOrder));
    
    const currentIndex = allCards.indexOf(currentCard);
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (targetIndex < 0 || targetIndex >= allCards.length) return;

    try {
        // Get target card
        const targetCard = allCards[targetIndex];
        
        // Update both groups' orders
        await Promise.all([
            updateGroupOrder(groupId, parseInt(targetCard.dataset.tabOrder)),
            updateGroupOrder(targetCard.dataset.groupId, parseInt(currentCard.dataset.tabOrder))
        ]);

        // Reload groups to reflect changes
        await loadGroups();

    } catch (error) {
        console.error('Error moving group:', error);
        showError('Failed to move group: ' + error.message);
    }
}

async function loadDeviceList(groupId) {
    try {
        const response = await apiFetch('all-devices');
        if (!response.success) {
            throw new Error('Failed to load devices');
        }

        // Reset current group devices
        currentGroupDevices.clear();

        // Group devices by type
        const deviceGroups = {
            'Lights': [],
            'Fans': [],
            'Other': []
        };

        response.devices.forEach(device => {
            // Add device to currentGroupDevices if it's part of this group
            if (device.group_id === groupId) {
                currentGroupDevices.add(device.device);
            }

            // Categorize device
            const name = device.device_name.toLowerCase();
            if (name.includes('light') || name.includes('lamp')) {
                deviceGroups['Lights'].push(device);
            } else if (name.includes('fan')) {
                deviceGroups['Fans'].push(device);
            } else {
                deviceGroups['Other'].push(device);
            }
        });

        // Generate HTML for device picker
        const deviceList = document.getElementById('device-picker-list');
        deviceList.innerHTML = Object.entries(deviceGroups)
            .filter(([_, devices]) => devices.length > 0)
            .map(([groupName, devices]) => `
                <div class="device-picker-group">
                    <h4>${groupName}</h4>
                    ${devices.map(device => `
                        <div class="device-picker-item">
                            <label>
                                <input type="checkbox" 
                                       value="${device.device}"
                                       ${currentGroupDevices.has(device.device) ? 'checked' : ''}>
                                <span>${device.device_name}</span>
                            </label>
                        </div>
                    `).join('')}
                </div>
            `).join('');

    } catch (error) {
        console.error('Error loading devices:', error);
        showError('Failed to load devices: ' + error.message);
    }
}

async function saveDeviceSelection() {
    try {
        // Get all selected devices
        const selectedDevices = Array.from(
            document.querySelectorAll('#device-picker-list input[type="checkbox"]:checked')
        ).map(cb => cb.value);

        // Get original group data first
        const response = await apiFetch('all-devices');
        if (!response.success) {
            throw new Error('Failed to fetch group data');
        }

        const currentGroup = response.groups.find(g => g.id === currentGroupId);
        if (!currentGroup) {
            throw new Error('Group not found');
        }

        // Update group with new device selection
        await apiFetch('update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: currentGroupId,
                name: currentGroup.name,
                model: currentGroup.model || 'group',
                devices: selectedDevices,
                rooms: currentGroup.rooms ? JSON.parse(currentGroup.rooms) : []
            })
        });

        hideDevicePicker();
        await loadGroups();
        await loadInitialData();

    } catch (error) {
        console.error('Error saving device selection:', error);
        showError('Failed to save device selection: ' + error.message);
    }
}

async function saveNewGroup() {
    const groupName = document.getElementById('new-group-name').value;

    if (!groupName) {
        showError('Please enter a group name');
        return;
    }

    try {
        const response = await apiFetch('update-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: groupName,
                model: 'group',
                devices: []
            })
        });

        if (!response.success) {
            throw new Error('Failed to create group');
        }

        // Hide the form and show the add button
        document.getElementById('new-group-form').style.display = 'none';
        const addButton = document.querySelector('button[onclick="showNewGroupCard()"]');
        if (addButton) {
            addButton.style.removeProperty('display');
        }

        // Reload everything
        await loadGroups();

    } catch (error) {
        console.error('Error adding group:', error);
        showError('Failed to add group: ' + error.message);
    }
}

async function updateGroupOrder(groupId, newOrder) {
    try {
        const response = await apiFetch('update-device-group', {
            method: 'POST',
            body: JSON.stringify({
                id: groupId,
                tab_order: newOrder
            })
        });
        
        if (!response.success) {
            throw new Error(response.error || 'Failed to update group order');
        }
    } catch (error) {
        throw error;
    }
}

async function loadGroups() {
    try {
        const response = await apiFetch('all-devices');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load groups');
        }

        const groupList = document.getElementById('group-list');
        if (!groupList) {
            console.error('Group list element not found');
            return;
        }

        // Sort groups by name
        const sortedGroups = response.groups
            .sort((a, b) => a.name.localeCompare(b.name));

        // Build the group cards HTML
        const groupCardsHtml = sortedGroups.map(group => `
            <div class="room-card" data-groups-group-id="${group.id}">
                <div class="room-card-header" onclick="toggleGroupCard(${group.id})">
                    <div class="room-card-header-content">
                        <i class="fas fa-layer-group"></i>
                        <span class="group-name">${group.name}</span>
                    </div>
                    <div class="room-order-buttons">
                        <button onclick="moveGroup(${group.id}, 'up')" class="order-btn">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button onclick="moveGroup(${group.id}, 'down')" class="order-btn">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </div>
                </div>
                <div class="room-card-content">  
                    <div class="room-input-group">
                        <input type="text" class="room-input group-name" value="${group.name}" placeholder="Group Name">
                    </div>
                    <div class="room-buttons">
                        <button onclick="showDevicePicker(${group.id}, '${group.name}')" class="devices-btn">
                            <i class="fas fa-lightbulb"></i> Devices
                            <span class="device-count">${JSON.parse(group.devices || '[]').length}</span>
                        </button>
                    </div>
                    <div class="room-actions">
                        <button onclick="deleteGroup(${group.id})" class="room-delete-btn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button onclick="saveGroup(${group.id})" class="room-save-btn">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        // Insert the cards BEFORE the button
        const addButton = groupList.querySelector('.add-room-btn');
        if (addButton) {
            // Remove existing cards while preserving the button
            const cards = groupList.querySelectorAll('.room-card');
            cards.forEach(card => card.remove());
            
            // Insert new cards before the button
            addButton.insertAdjacentHTML('beforebegin', groupCardsHtml);
        } else {
            // Fallback if button not found
            groupList.innerHTML = groupCardsHtml;
        }

    } catch (error) {
        console.error('Error loading groups:', error);
        showError('Failed to load groups: ' + error.message);
    }
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('group-list')?.addEventListener('click', function(e) {
        if (e.target.closest('button')) {
            e.stopPropagation();
        }
    });
});