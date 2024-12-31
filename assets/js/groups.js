// assets/js/groups.js

async function loadAvailableGroups(model) {
    try {
        const response = await apiFetch(`api/available-groups?model=${model}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error);
        }
        
        const groupSelect = document.getElementById('config-existing-groups');
        groupSelect.innerHTML = data.groups.map(group => 
            `<option value="${group.id}">${group.name}</option>`
        ).join('');
        
    } catch (error) {
        showError('Failed to load available groups: ' + error.message);
    }
}

function handleGroupActionChange() {
    const action = document.getElementById('config-group-action').value;
    const groupNameContainer = document.getElementById('group-name-container');
    const existingGroupsContainer = document.getElementById('existing-groups-container');
    
    window.groupAction = action;
    
    groupNameContainer.style.display = action === 'create' ? 'block' : 'none';
    existingGroupsContainer.style.display = action === 'join' ? 'block' : 'none';
}

async function deleteDeviceGroup(groupId) {
    if (!confirm('Are you sure you want to delete this group? All devices will be ungrouped.')) {
        return;
    }
    
    try {
        const response = await apiFetch('api/delete-device-group', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ groupId: groupId })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to delete group');
        }
        
        hideConfigMenu();
        updateDevices();
        
    } catch (error) {
        showError('Failed to delete group: ' + error.message);
    }
}