// assets/js/temperature.js

async function showTempHistory(mac, deviceName) {
    const popup = document.getElementById('history-popup');
    document.getElementById('history-device-title').textContent = `Temperature/Humidity - ${deviceName} `;
    popup.dataset.mac = mac;
    popup.style.display = 'block';
    await loadTempHistory();
}

function hideHistoryPopup() {
    document.getElementById('history-popup').style.display = 'none';
}



function toggleDataset(checkbox) {
    const chart = window.tempHistoryChart;
    const seriesName = checkbox.dataset.series;
    
    let datasetIndex;
    switch(seriesName) {
        case 'temp': datasetIndex = 0; break;
        case 'humidity': datasetIndex = 1; break;
        case 'battery': datasetIndex = 2; break;
        case 'rssi': datasetIndex = 3; break;
    }
    
    chart.data.datasets[datasetIndex].hidden = !checkbox.checked;
    
    const scale = chart.options.scales[seriesName];
    if (scale) {
        scale.display = checkbox.checked;
    }
    
    chart.update();
}

async function showAllTempHistory() {
    const popup = document.getElementById('all-temps-popup');
    popup.style.display = 'block';
    await loadAllTempHistory();
}

function hideAllTempsPopup() {
    document.getElementById('all-temps-popup').style.display = 'none';
}

function calculateHourlyAverages(data, hours = 24) {
    const timeGroups = {};
    const interval = hours == 24 ? 15 : (hours == 720 ? 240 : 60);
    
    console.error('Hours: ', hours);
    console.error('Interval: ', interval);
    
    // First sort data by timestamp
    data.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
    
    data.forEach(record => {
        const date = new Date(record.timestamp);
        // Round to nearest interval
        if (interval === 15) {
            const minutes = date.getMinutes();
            date.setMinutes(Math.floor(minutes / 15) * 15, 0, 0);
        } else if (interval === 240) {
            // For 4-hour intervals, round to nearest 4 hours
            const hours = date.getHours();
            date.setHours(Math.floor(hours / 4) * 4, 0, 0, 0);
        } else {
            // For hourly intervals
            date.setMinutes(0, 0, 0);
        }
        const timeKey = date.getTime();
        
        if (!timeGroups[timeKey]) {
            timeGroups[timeKey] = {
                temps: [],
                humidities: [],
                timestamp: date
            };
        }
        
        // Group all readings within this time period
        timeGroups[timeKey].temps.push(parseFloat(record.temperature));
        timeGroups[timeKey].humidities.push(parseFloat(record.humidity));
    });
    
    // Calculate averages for each time period
    const averagedData = Object.entries(timeGroups).map(([timestamp, data]) => ({
        timestamp: new Date(parseInt(timestamp)),
        temperature: data.temps.reduce((a, b) => a + b, 0) / data.temps.length,
        humidity: data.humidities.reduce((a, b) => a + b, 0) / data.humidities.length
    }));

    // Sort by timestamp
    averagedData.sort((a, b) => a.timestamp - b.timestamp);
    
    // Fill in missing intervals with null values
    if (averagedData.length > 1) {
        const filledData = [];
        const startTime = averagedData[0].timestamp;
        const endTime = averagedData[averagedData.length - 1].timestamp;
        
        for (let time = startTime; time <= endTime;) {
            const existingData = averagedData.find(d => d.timestamp.getTime() === time.getTime());
            if (existingData) {
                filledData.push(existingData);
            } else {
                filledData.push({
                    timestamp: new Date(time),
                    temperature: null,
                    humidity: null
                });
            }
            
            // Increment by interval
            if (interval === 15) {
                time = new Date(time.setMinutes(time.getMinutes() + 15));
            } else if (interval === 240) {
                time = new Date(time.setHours(time.getHours() + 4));
            } else {
                time = new Date(time.setHours(time.getHours() + 1));
            }
        }
        return filledData;
    }

    return averagedData;
}

async function loadTempHistory() {
    const popup = document.getElementById('history-popup');
    const mac = popup.dataset.mac;
    const hours = document.getElementById('history-range').value;
    
    try {
        const response = await apiFetch(`api/thermometer-history?mac=${mac}&hours=${hours}`);
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load temperature history');
        }

        function updateTable(chartInstance, historyData) {
            const tbody = document.querySelector('#history-table tbody');
            const thead = document.querySelector('#history-table thead tr');
            
            thead.innerHTML = `
                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Time</th>
                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Temperature</th>
                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Humidity</th>
            `;
            
            tbody.innerHTML = [...historyData].reverse().map(record => {
                const date = record.timestamp;
                let formattedDate = date.toLocaleString([], {
                    month: 'numeric',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
                
                const temp = record.temperature !== null && !isNaN(record.temperature) 
                    ? `${record.temperature.toFixed(1)}°F` 
                    : '-';
                const humidity = record.humidity !== null && !isNaN(record.humidity)
                    ? `${record.humidity.toFixed(1)}%` 
                    : '-';
                
                return `
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${formattedDate}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${temp}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${humidity}</td>
                    </tr>
                `;
            }).join('');
        }

        // Calculate hourly averages from raw data
        const hourlyData = calculateHourlyAverages(data.history, hours);

        if (hourlyData.length === 0) {
            document.getElementById('temp-history-chart').innerHTML = 
                '<div style="text-align: center; padding: 20px;">No data available for selected time period</div>';
            return;
        }

        const canvas = document.getElementById('temp-history-chart');
        const ctx = canvas.getContext('2d');

        if (window.tempHistoryChart) {
            window.tempHistoryChart.destroy();
        }

        // Generate color for temperature and humidity lines
        const hue = Math.floor(Math.random() * 360);
        const tempColor = `hsl(${hue}, 70%, 50%)`;
        const humidityColor = `hsl(${(hue + 120) % 360}, 70%, 50%)`;

        window.tempHistoryChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Temperature',
                        data: hourlyData.map(record => ({
                            x: record.timestamp,
                            y: record.temperature
                        })),
                        borderColor: tempColor,
                        tension: 0.3,
                        pointRadius: 2,
                        spanGaps: true
                    },
                    {
                        label: 'Humidity',
                        data: hourlyData.map(record => ({
                            x: record.timestamp,
                            y: record.humidity
                        })),
                        borderColor: humidityColor,
                        borderDash: [5, 5],
                        tension: 0.3,
                        pointRadius: 2,
                        spanGaps: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    intersect: false
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: hours === '24' ? 'hour' : 'day'
                        },
                        display: true
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Temperature (°F) / Humidity (%)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toFixed(1) + (context.dataset.label === 'Temperature' ? '°F' : '%');
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        updateTable(window.tempHistoryChart, hourlyData);
        
    } catch (error) {
        console.error('Error loading temperature history:', error);
        showError('Failed to load temperature history: ' + error.message);
    }
}

async function loadAllTempHistory() {
    const hours = document.getElementById('all-temps-history-range').value;
    
    try {
        const response = await apiFetch(`api/all-thermometer-history?hours=${hours}`);
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load temperature history');
        }

        const canvas = document.getElementById('all-temps-chart');
        const ctx = canvas.getContext('2d');

        // Group data by device and calculate hourly averages
        const deviceData = {};
        data.history.forEach(record => {
            if (!deviceData[record.device_name]) {
                deviceData[record.device_name] = [];
            }
            deviceData[record.device_name].push(record);
        });

        // Calculate hourly averages for each device
        const hourlyDeviceData = {};
        Object.entries(deviceData).forEach(([deviceName, records]) => {
            hourlyDeviceData[deviceName] = calculateHourlyAverages(records, hours);
        });
        
        // Create datasets for the chart
        const datasets = [];
        Object.entries(hourlyDeviceData).forEach(([deviceName, records], index) => {
            const hue = (index * 137.508) % 360; // Use golden angle approximation
            const color = `hsl(${hue}, 70%, 50%)`;

            datasets.push({
                label: `${deviceName} Temperature`,
                data: records.map(record => ({
                    x: record.timestamp,
                    y: record.temperature
                })),
                borderColor: color,
                tension: 0.3,
                pointRadius: 2,
                spanGaps: true
            });

            datasets.push({
                label: `${deviceName} Humidity`,
                data: records.map(record => ({
                    x: record.timestamp,
                    y: record.humidity
                })),
                borderColor: color,
                borderDash: [5, 5],
                tension: 0.3,
                pointRadius: 2,
                spanGaps: true
            });
        });

        if (window.allTempsChart) {
            window.allTempsChart.destroy();
        }

        window.allTempsChart = new Chart(ctx, {
            type: 'line',
            data: { datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    intersect: false
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: hours === '24' ? 'hour' : 'day'
                        },
                        display: true
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Temperature (°F) / Humidity (%)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toFixed(1) + (label.includes('Temperature') ? '°F' : '%');
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });

    } catch (error) {
        console.error('Error loading temperature history:', error);
        showError('Failed to load temperature history: ' + error.message);
    }
}

async function loadThermometerList() {
    try {
        const response = await apiFetch('api/thermometer-list');
        const data = await response;
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load thermometer list');
        }

        const tbody = document.getElementById('thermometer-list');
        tbody.innerHTML = data.thermometers.map(therm => {
            const roomOptions = data.rooms.map(room => 
                `<option value="${room.id}" ${room.id == therm.room_id ? 'selected' : ''}>
                    ${room.room_name}
                </option>`
            ).join('');

            return `
                <tr data-mac="${therm.mac}">
                    <td>
                        <input type="text" class="therm-display-name" 
                               value="${therm.display_name || ''}">
                    </td>
                    <td>${therm.model || ''}</td>
                    
                    <td>${therm.mac}</td>
                    <td>
                        <select class="therm-room">
                            <option value="">No Room</option>
                            ${roomOptions}
                        </select>
                    </td>
                    <td>${new Date(therm.updated).toLocaleString()}</td>
                    <td>
                        <button onclick="saveThermometer('${therm.mac}')" class="save-btn">
                            Save
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (error) {
        console.error('Error loading thermometer list:', error);
        showError('Failed to load thermometer list: ' + error.message);
    }
}

async function saveThermometer(mac) {
    const row = document.querySelector(`tr[data-mac="${mac}"]`);
    if (!row) return;

    const displayName = row.querySelector('.therm-display-name').value;
    const room = row.querySelector('.therm-room').value;

    try {
        const response = await apiFetch('api/update-thermometer', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                mac: mac,
                display_name: displayName,
                room: room
            })
        });
        
        const data = await response;
        if (!data.success) {
            throw new Error(data.error || 'Failed to update thermometer');
        }

        // Reload both the list and the chart
        await Promise.all([
            loadThermometerList(),
            loadAllTempHistory(),
            loadInitialData()  // Also reload main UI to reflect changes
        ]);

    } catch (error) {
        console.error('Error saving thermometer:', error);
        showError('Failed to save thermometer: ' + error.message);
    }
}

async function showAllTempHistory() {
    const popup = document.getElementById('all-temps-popup');
    popup.style.display = 'block';
    await Promise.all([
        loadAllTempHistory(),
        loadThermometerList()
    ]);
}