// js/remote.js

let currentRemoteName = null;
let currentButtonNumber = null;

function showRemoteManagement() {
    const popup = document.getElementById('remote-popup');
    if (!popup) {
        console.error('Remote popup template not found');
        return;
    }
    popup.style.display = 'block';
    loadRemoteButtonMappings();
}

function hideRemotePopup() {
    document.getElementById('remote-popup').style.display = 'none';
}

function hideButtonTargetPicker() {
    document.getElementById('button-target-popup').style.display = 'none';
    currentRemoteName = null;
    currentButtonNumber = null;
}

async function loadRemoteButtonMappings() {
    try {
        // Get remote button mappings from the database
        const response = await apiFetch('remote-mappings');
        if (!response.success) {
            throw new Error(response.error || 'Failed to load remote mappings');
        }

        // Group mappings by remote name
        const remoteGroups = {};
        response.mappings.forEach(mapping => {
            if (!remoteGroups[mapping.remote_name]) {
                remoteGroups[mapping.remote_name] = [];
            }
            remoteGroups[mapping.remote_name].push(mapping);
        });

        // Generate HTML for each remote
        const remoteList = document.getElementById('remote-list');
        remoteList.innerHTML = Object.entries(remoteGroups)
            .map(([remoteName, mappings]) => `
                <div class="room-card" data-remote="${remoteName}">
                    <div class="room-card-header" onclick="toggleRemoteCard('${remoteName}')">
                        <div class="room-card-header-content">
                            <i class="fas fa-gamepad"></i>
                            <span>${remoteName}</span>
                        </div>
                    </div>
                    <div class="room-card-content">
                        <div class="button-mappings">
                            ${mappings.sort((a, b) => a.button_number - b.button_number)
                                     .map(mapping => createButtonMappingHtml(mapping))
                                     .join('')}
                        </div>
                    </div>
                </div>
            `).join('');

    } catch (error) {
        console.error('Error loading remote mappings:', error);
        showError('Failed to load remote mappings: ' + error.message);
    }
}

function createButtonMappingHtml(mapping) {
    // Get target name - will be updated with actual target name when data loading is implemented
    let targetInfo = 'Not mapped';
    if (mapping.mapped) {
        targetInfo = `${mapping.target_type}: ${mapping.target_id}<br>`;
        targetInfo += `Command: ${mapping.command_name}`;
        if (mapping.command_value) {
            targetInfo += ` (${mapping.command_value})`;
        }
        if (mapping.toggle_states) {
            const states = JSON.parse(mapping.toggle_states);
            targetInfo += `<br>States: ${states.join(', ')}`;
        }
    }

    return `
        <div class="button-mapping">
            <div class="button-info">
                <span><h4>Button ${mapping.button_number}</h4></span>
                <div class="button-target"> ${targetInfo} </div>
            
                <button onclick="showButtonTargetPicker('${mapping.remote_name}', ${mapping.button_number})" 
                        title="Configure">
                    <i class="fas fa-xl fa-cog"></i>
                </button>
                
            </div>
        </div>
    `;
}

function toggleRemoteCard(remoteName) {
    // First remove expanded class from all cards
    document.querySelectorAll('.room-card').forEach(card => {
        if (card.dataset.remote !== remoteName) {
            card.classList.remove('expanded');
            const content = card.querySelector('.room-card-content');
            if (content) {
                content.style.display = 'none';
            }
        }
    });
    
    // Toggle the clicked card
    const clickedCard = document.querySelector(`div[data-remote="${remoteName}"]`);
    if (clickedCard) {
        clickedCard.classList.toggle('expanded');
        const content = clickedCard.querySelector('.room-card-content');
        if (content) {
            content.style.display = clickedCard.classList.contains('expanded') ? 'block' : 'none';
        }
    }
}

async function showButtonTargetPicker(remoteName, buttonNumber) {
    currentRemoteName = remoteName;
    currentButtonNumber = buttonNumber;

    // Update popup header
    document.getElementById('button-target-remote-name').textContent = remoteName;
    document.getElementById('button-target-number').textContent = buttonNumber;

    try {
        // Get current mapping if it exists
        const response = await apiFetch(`remote-mapping?remote=${remoteName}&button=${buttonNumber}`);
        if (!response.success) {
            throw new Error(response.error || 'Failed to load button mapping');
        }

        const mapping = response.mapping;

        // Set current values
        document.getElementById('button-target-type').value = mapping?.target_type || 'device';
        document.getElementById('button-command-type').value = mapping?.command_name || 'toggle';

        // Load targets based on type
        await loadTargets(mapping?.target_type || 'device', mapping?.target_id);

        // Set command options
        if (mapping?.command_name === 'toggle' && mapping?.toggle_states) {
            const states = JSON.parse(mapping.toggle_states);
            document.querySelectorAll('.toggle-states-options input').forEach(cb => {
                cb.checked = states.includes(cb.value);
            });
        } else if (mapping?.command_value) {
            document.getElementById('button-command-value').value = mapping.command_value;
        }

        // Show appropriate command options
        handleCommandTypeChange();

        // Show the popup
        document.getElementById('button-target-popup').style.display = 'block';

    } catch (error) {
        console.error('Error loading button mapping:', error);
        showError('Failed to load button mapping: ' + error.message);
    }
}

async function loadTargets(type, selectedId = null) {
    try {
        const response = await apiFetch('all-devices');
        if (!response.success) {
            throw new Error('Failed to load targets');
        }

        const targetSelect = document.getElementById('button-target-id');
        targetSelect.innerHTML = '';

        if (type === 'device') {
            response.devices
                .sort((a, b) => a.device_name.localeCompare(b.device_name))
                .forEach(device => {
                    const option = document.createElement('option');
                    option.value = device.device;
                    option.textContent = device.device_name;
                    option.selected = device.device === selectedId;
                    targetSelect.appendChild(option);
                });
        } else {
            response.groups
                .sort((a, b) => a.name.localeCompare(b.name))
                .forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.id;
                    option.textContent = group.name;
                    option.selected = group.id === selectedId;
                    targetSelect.appendChild(option);
                });
        }

    } catch (error) {
        console.error('Error loading targets:', error);
        showError('Failed to load targets: ' + error.message);
    }
}

function handleTargetTypeChange() {
    const type = document.getElementById('button-target-type').value;
    loadTargets(type);
}

function handleCommandTypeChange() {
    const commandType = document.getElementById('button-command-type').value;
    const toggleStatesContainer = document.getElementById('toggle-states-container');
    const commandValueContainer = document.getElementById('command-value-container');

    toggleStatesContainer.style.display = commandType === 'toggle' ? 'block' : 'none';
    commandValueContainer.style.display = commandType !== 'toggle' ? 'block' : 'none';
}

async function saveButtonMapping() {
    try {
        const targetType = document.getElementById('button-target-type').value;
        const targetId = document.getElementById('button-target-id').value;
        const commandType = document.getElementById('button-command-type').value;

        let commandValue = null;
        let toggleStates = null;

        if (commandType === 'toggle') {
            toggleStates = Array.from(document.querySelectorAll('.toggle-states-options input:checked'))
                .map(cb => cb.value);
            if (toggleStates.length === 0) {
                throw new Error('Please select at least one toggle state');
            }
        } else {
            commandValue = document.getElementById('button-command-value').value;
            if (!commandValue && commandType === 'turn') {
                commandValue = 'off'; // Default value for turn command
            }
            if (!commandValue && commandType === 'brightness') {
                throw new Error('Please enter a brightness value');
            }
        }

        const response = await apiFetch('update-remote-mapping', {
            method: 'POST',
            body: JSON.stringify({
                remote_name: currentRemoteName,
                button_number: currentButtonNumber,
                target_type: targetType,
                target_id: targetId,
                command_name: commandType,
                command_value: commandValue,
                toggle_states: toggleStates
            })
        });

        if (!response.success) {
            throw new Error(response.error || 'Failed to update button mapping');
        }

        hideButtonTargetPicker();
        await loadRemoteButtonMappings();

    } catch (error) {
        console.error('Error saving button mapping:', error);
        showError('Failed to save button mapping: ' + error.message);
    }
}

// Add event listener to handle the Remotes button click
document.addEventListener('DOMContentLoaded', () => {
    const remotesButton = document.querySelector('button.config-button i.fa-gamepad')?.closest('button');
    if (remotesButton) {
        remotesButton.onclick = showRemoteManagement;
    }
});