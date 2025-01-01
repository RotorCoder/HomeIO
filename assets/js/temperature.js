// assets/js/temperature.js

async function showTempHistory(mac, deviceName) {
    const popup = document.getElementById('history-popup');
    document.getElementById('history-device-title').textContent = `Temperature History - ${deviceName}`;
    popup.dataset.mac = mac;
    popup.style.display = 'block';
    await loadTempHistory();
}

function hideHistoryPopup() {
    document.getElementById('history-popup').style.display = 'none';
}

async function loadTempHistory() {
    const popup = document.getElementById('history-popup');
    const mac = popup.dataset.mac;
    const hours = document.getElementById('history-range').value;
    
    try {
        const response = await apiFetch(`api/thermometer-history?mac=${mac}&hours=${hours}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load temperature history');
        }

        function updateTable(chartInstance, historyData) {
            const tbody = document.querySelector('#history-table tbody');
            const thead = document.querySelector('#history-table thead tr');
            
            thead.innerHTML = `
                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Time</th>
                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd; color: rgb(255, 99, 132);">Temperature</th>
                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd; color: rgb(54, 162, 235);">Humidity</th>
            `;
            
            tbody.innerHTML = [...historyData].reverse().map(record => {
                const date = new Date(record.timestamp);
                let formattedDate;
                
                if (hours === '24') {
                    formattedDate = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                } else {
                    formattedDate = `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`;
                }
                
                return `
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${formattedDate}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${record.temperature}°F</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${record.humidity}%</td>
                    </tr>
                `;
            }).join('');
        }

        const chartData = [...data.history].reverse();

        if (chartData.length === 0) {
            document.getElementById('temp-history-chart').innerHTML = 
                '<div style="text-align: center; padding: 20px;">No data available for selected time period</div>';
            return;
        }

        const labels = chartData.map(record => {
            const date = new Date(record.timestamp);
            if (hours === '24') {
                return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            } else {
                return `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`;
            }
        });

        const canvas = document.getElementById('temp-history-chart');
        const ctx = canvas.getContext('2d');

        if (window.tempHistoryChart) {
            window.tempHistoryChart.destroy();
        }

        window.tempHistoryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Temperature (°F)',
                        data: chartData.map(record => record.temperature),
                        borderColor: 'rgb(255, 99, 132)',
                        yAxisID: 'temp',
                        tension: 0.3,
                        pointRadius: 3,
                    },
                    {
                        label: 'Humidity (%)',
                        data: chartData.map(record => record.humidity),
                        borderColor: 'rgb(54, 162, 235)',
                        yAxisID: 'humidity',
                        tension: 0.3,
                        pointRadius: 3
                    },
                    {
                        label: 'Battery (%)',
                        data: chartData.map(record => record.battery),
                        borderColor: 'rgb(75, 192, 192)',
                        yAxisID: 'battery',
                        tension: 0.3,
                        pointRadius: 3,
                        hidden: true
                    },
                    {
                        label: 'Signal Strength (dBm)',
                        data: chartData.map(record => record.rssi),
                        borderColor: 'rgb(153, 102, 255)',
                        yAxisID: 'rssi',
                        tension: 0.3,
                        pointRadius: 3,
                        hidden: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        ticks: {
                            callback: function(val, index) {
                                return index % 2 === 0 ? this.getLabelForValue(val) : '';
                            }
                        }
                    },
                    y: {
                        display: true,
                        type: 'logarithmic',
                        ticks: {
                            display: false
                        }
                    },
                    temp: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Temperature °F',
                            color: 'rgb(255, 99, 132)',
                        },
                        ticks: {
                            stepSize: 1
                        },
                    },
                    humidity: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Humidity %',
                            color: 'rgb(54, 162, 235)',
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            stepSize: 5
                        }
                    },
                    battery: {
                        type: 'linear',
                        display: false,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Battery (%)'
                        },
                        min: 0,
                        max: 100,
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            stepSize: 10
                        }
                    },
                    rssi: {
                        type: 'linear',
                        display: false,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Signal Strength (dBm)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            stepSize: 5
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    legend: {
                        onClick: (e, legendItem, legend) => {
                            const index = legendItem.datasetIndex;
                            const ci = legend.chart;
                            const meta = ci.getDatasetMeta(index);

                            meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                            
                            const scaleId = ci.data.datasets[index].yAxisID;
                            const scale = ci.scales[scaleId];
                            scale.display = !meta.hidden;
                            
                            ci.update();
                            
                            updateTable(ci, chartData);
                        }
                    }
                }
            }
        });
        
        updateTable(window.tempHistoryChart, chartData);
        
    } catch (error) {
        console.error('Error loading temperature history:', error);
        showError('Failed to load temperature history: ' + error.message);
    }
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

async function loadAllTempHistory() {
    const hours = document.getElementById('all-temps-history-range').value;
    
    try {
        const response = await apiFetch(`api/all-thermometer-history?hours=${hours}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load temperature history');
        }

        const canvas = document.getElementById('all-temps-chart');
        const ctx = canvas.getContext('2d');
        
        // Create datasets for each device
        const datasets = [];
        const deviceData = {};

        // Group data by device
        data.history.forEach(record => {
            if (!deviceData[record.device_name]) {
                deviceData[record.device_name] = [];
            }
            deviceData[record.device_name].push(record);
        });

        // Create a dataset for each device
        Object.entries(deviceData).forEach(([deviceName, records], index) => {
            // Generate a color based on index
            const hue = (index * 137.508) % 360; // Use golden angle approximation
            const color = `hsl(${hue}, 70%, 50%)`;

            datasets.push({
                label: `${deviceName} Temperature`,
                data: records.map(record => ({
                    x: new Date(record.timestamp),
                    y: record.temperature
                })),
                borderColor: color,
                tension: 0.3,
                pointRadius: 2
            });

            datasets.push({
                label: `${deviceName} Humidity`,
                data: records.map(record => ({
                    x: new Date(record.timestamp),
                    y: record.humidity
                })),
                borderColor: color,
                borderDash: [5, 5],
                tension: 0.3,
                pointRadius: 2
            });
        });

        if (window.allTempsChart) {
            window.allTempsChart.destroy();
        }

        window.allTempsChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: datasets
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
                        intersect: false
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
        const data = await response.json();
        
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
                               value="${therm.display_name || ''}" 
                               style="width: 100%">
                    </td>
                    <td>${therm.name || ''}</td>
                    <td>${therm.mac}</td>
                    <td>${therm.model || ''}</td>
                    <td>
                        <select class="therm-room" style="width: 100%">
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
        
        const data = await response.json();
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