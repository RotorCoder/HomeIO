<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit;
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temperature History Viewer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
    <link rel="manifest" href="assets/images/site.webmanifest">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #c1e2f7;
            padding: 5px;
        }

        .container {
            max-width: 96%;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            display: none;
        }

        .login-link {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            margin-top: 10px;
        }

        .controls {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-width: 200px;
            background-color: white;
        }

        select:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        /* New toggle styles */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-label {
            font-size: 14px;
            color: #1e293b;
            user-select: none;
        }

        #showOutside {
            width: 16px;
            height: 16px;
        }

        .current-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
            margin-bottom: 5px;
        }

        .stat-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            grid-column: span 1;
        }

        .stat-card.full-width {
            grid-column: span 1;
        }

        .stat-card.half-width {
            grid-column: span 1;
        }

        .temp-range-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            grid-column: span 1;
        }

        .stat-card h3 {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            height: 70vh;
            position: relative;
            margin-top: 20px;
        }

        .loading-state {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1rem;
            color: #6b7280;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .no-data {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #64748b;
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                max-width: 100%;
                padding: 10px;
            }
            
            .controls {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .controls select {
                flex: 1;
                min-width: 0;
                width: calc(50% - 5px);
            }
            
            .toggle-container {
                width: 100%;
                justify-content: flex-start;
            }
            
            .current-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 8px;
                grid-column: span 1;
            }

            .stat-card.full-width {
                grid-column: span 1;
            }

            .temp-range-container {
                grid-column: span 1;
            }

            .stat-card h3 {
                font-size: 0.75rem;
                margin-bottom: 2px;
            }

            .stat-card .stat-value {
                font-size: 1rem;
            }

            .stat-card h3 i {
                font-size: 0.75rem;
            }
            
            .chart-container {
                height: 400px;
                padding: 10px;
            }
        }
    </style>
    <script>
        // Function to get the API key stored in session/local storage
        function getApiKey() {
            // Try to get homeio_api_key first
            const apiKey = localStorage.getItem('homeio_api_key') || sessionStorage.getItem('homeio_api_key');
            if (apiKey) return apiKey;
            
            // If no dedicated API key, try to use the token
            return localStorage.getItem('homeio_token') || sessionStorage.getItem('homeio_token');
        }

        // Modified fetch function to include authentication
        // Replace the secureApiFetch function with this
        async function secureApiFetch(endpoint) {
            try {
                const response = await fetch(`api-proxy.php?endpoint=${encodeURIComponent(endpoint)}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
            } catch (error) {
                console.error('API fetch error:', error);
                throw error;
            }
        }

        // Function to show authentication error
        function showAuthError() {
            const errorElement = document.getElementById('error-message');
            errorElement.textContent = 'Authentication required. Please log in again.';
            errorElement.style.display = 'block';
            
            // Add a login button
            errorElement.innerHTML += '<br><a href="/homeio/login.php" class="login-link">Log In</a>';
        }
    </script>
</head>
<body>
    <div class="container">
        <div id="error-message" class="error-message"></div>
        
        <div class="controls">
            <select id="thermometer" disabled>
                <option value="">Loading thermometers...</option>
            </select>
            <select id="timeRange">
                <option value="24">Last 24 Hours</option>
                <option value="168">Last 7 Days</option>
                <option value="720">Last 30 Days</option>
            </select>
            <label class="toggle-container">
                <input type="checkbox" id="showOutside">
                <span class="toggle-label">Show Outside Temperature</span>
            </label>
            <label class="toggle-container">
                <input type="checkbox" id="showHumidity">
                <span class="toggle-label">Show Humidity</span>
            </label>
        </div>

        <div class="current-stats">
            <div class="stat-card full-width">
                <h3><i class="fas fa-temperature-half"></i> Temperature</h3>
                <div class="stat-value" id="currentTemp">--°F</div>
            </div>
            <div class="temp-range-container">
                <div class="stat-card half-width">
                    <h3><i class="fas fa-temperature-arrow-up"></i> Max</h3>
                    <div class="stat-value" id="maxTemp">--°F</div>
                </div>
                <div class="stat-card half-width">
                    <h3><i class="fas fa-temperature-arrow-down"></i> Min</h3>
                    <div class="stat-value" id="minTemp">--°F</div>
                </div>
            </div>
            <div class="stat-card full-width">
                <h3><i class="fas fa-droplet"></i> Humidity</h3>
                <div class="stat-value" id="currentHumidity">--%</div>
            </div>
            <div class="stat-card full-width">
                <h3><i class="fas fa-battery-three-quarters"></i> Battery Level</h3>
                <div class="stat-value" id="currentBattery">--%</div>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="tempChart"></canvas>
            <div class="loading-state text-center" id="loading-indicator">
                ⋯ Loading data...
            </div>
            <div class="no-data" id="no-data-message">
                <i class="fas fa-chart-line"></i>
                <p>No data available for selected time period</p>
            </div>
        </div>
    </div>

    <script>
        let chart = null;
        let thermometerData = [];
        let outsideThermometer = null;

        function savePreferences() {
            const thermometer = document.getElementById('thermometer').value;
            const timeRange = document.getElementById('timeRange').value;
            const showOutside = document.getElementById('showOutside').checked;
            const showHumidity = document.getElementById('showHumidity').checked;
            localStorage.setItem('selectedThermometer', thermometer);
            localStorage.setItem('selectedTimeRange', timeRange);
            localStorage.setItem('showOutside', showOutside);
            localStorage.setItem('showHumidity', showHumidity);
        }
        
        function loadPreferences() {
            // Default MAC address for Cat Condo thermometer from the database
            const CAT_CONDO_MAC = 'D0:05:85:46:3D:7C';
            
            return {
                // Set Cat Condo as default if no thermometer is selected
                thermometer: localStorage.getItem('selectedThermometer') || CAT_CONDO_MAC,
                timeRange: localStorage.getItem('selectedTimeRange') || '24',
                showOutside: localStorage.getItem('showOutside') !== null ? localStorage.getItem('showOutside') === 'true' : true,
                showHumidity: localStorage.getItem('showHumidity') !== null ? localStorage.getItem('showHumidity') === 'true' : false
            };
        }

        async function init() {
            try {
                await loadThermometers();
                
                const prefs = loadPreferences();
                
                if (prefs.timeRange) {
                    document.getElementById('timeRange').value = prefs.timeRange;
                }
                
                if (prefs.thermometer) {
                    const thermSelect = document.getElementById('thermometer');
                    if (thermSelect.querySelector(`option[value="${prefs.thermometer}"]`)) {
                        thermSelect.value = prefs.thermometer;
                    }
                }
        
                // Set toggle states from preferences
                document.getElementById('showOutside').checked = prefs.showOutside;
                document.getElementById('showHumidity').checked = prefs.showHumidity;
                
                setupEventListeners();
                
                if (document.getElementById('thermometer').value) {
                    await loadData();
                }
            } catch (error) {
                console.error('Failed to initialize page:', error);
                if (error.message && error.message.includes('401')) {
                    showAuthError();
                } else {
                    showError('Failed to initialize page: ' + error.message);
                }
            }
        }

        function showError(message) {
            const errorElement = document.getElementById('error-message');
            errorElement.textContent = `Error: ${message}`;
            errorElement.style.display = 'block';
        }

        function hideError() {
            document.getElementById('error-message').style.display = 'none';
        }

        async function loadThermometers() {
            try {
                const data = await secureApiFetch('thermometer-list');
                
                if (data.success) {
                    const select = document.getElementById('thermometer');
                    
                    if (!data.thermometers || data.thermometers.length === 0) {
                        select.innerHTML = '<option value="">No thermometers available</option>';
                        return;
                    }

                    thermometerData = data.thermometers;
                    
                    // Find the outside thermometer
                    outsideThermometer = thermometerData.find(t => t.display_name === 'Outside');

                    const sortedThermometers = thermometerData.sort((a, b) => {
                        const nameA = a.display_name || a.name || '';
                        const nameB = b.display_name || b.name || '';
                        return nameA.localeCompare(nameB);
                    });

                    select.innerHTML = sortedThermometers.map(therm => `
                        <option value="${therm.mac}">
                            ${therm.display_name || therm.name || 'Unknown Device'}
                        </option>
                    `).join('');
                    
                    select.disabled = false;
                    
                    const prefs = loadPreferences();
                    const selectedMac = prefs.thermometer || select.value;
                    const selectedThermometer = sortedThermometers.find(t => t.mac === selectedMac);
                    if (selectedThermometer) {
                        updateCurrentStats(selectedThermometer);
                    }
                } else {
                    throw new Error(data.error || 'Failed to load thermometer list');
                }
            } catch (error) {
                console.error('Error loading thermometers:', error);
                if (error.message && error.message.includes('401')) {
                    showAuthError();
                } else {
                    showError('Error loading thermometers: ' + error.message);
                }
                throw error;
            }
        }

        function setupEventListeners() {
            document.getElementById('timeRange').addEventListener('change', async () => {
                savePreferences();
                await loadData();
            });
            
            document.getElementById('thermometer').addEventListener('change', async () => {
                const mac = document.getElementById('thermometer').value;
                const selectedThermometer = thermometerData.find(t => t.mac === mac);
                if (selectedThermometer) {
                    updateCurrentStats(selectedThermometer);
                }
                savePreferences();
                await loadData();
            });
        
            document.getElementById('showOutside').addEventListener('change', async () => {
                savePreferences();
                await loadData();
            });
        
            // Add listener for humidity toggle
            document.getElementById('showHumidity').addEventListener('change', async () => {
                savePreferences();
                await loadData();
            });
        }

        function updateCurrentStats(data) {
            if (!data) return;
            
            document.getElementById('currentTemp').textContent = data.temp ? `${Math.round(data.temp)}°F` : '--°F';
            document.getElementById('currentHumidity').textContent = data.humidity ? `${data.humidity}%` : '--%';
            document.getElementById('currentBattery').textContent = data.battery ? `${data.battery}%` : '--%';
            
            if (data.history && data.history.length > 0) {
                const validTemps = data.history
                    .filter(record => record.temperature !== null && record.temperature !== undefined)
                    .map(record => Number(record.temperature));
                
                if (validTemps.length > 0) {
                    const maxTemp = Math.max(...validTemps);
                    const minTemp = Math.min(...validTemps);
                    
                    document.getElementById('maxTemp').textContent = `${Math.round(maxTemp)}°F`;
                    document.getElementById('minTemp').textContent = `${Math.round(minTemp)}°F`;
                }
            } else {
                document.getElementById('maxTemp').textContent = '--°F';
                document.getElementById('minTemp').textContent = '--°F';
            }
        }

        async function loadData() {
            const mac = document.getElementById('thermometer').value;
            const hours = document.getElementById('timeRange').value;
            const showOutside = document.getElementById('showOutside').checked;
            
            if (!mac) return;

            hideError();
            const loadingIndicator = document.getElementById('loading-indicator');
            const noDataMessage = document.getElementById('no-data-message');
            
            if (loadingIndicator) loadingIndicator.style.display = 'block';
            if (noDataMessage) noDataMessage.style.display = 'none';

            try {
                // Load selected thermometer data
                const data = await secureApiFetch(`thermometer-history?mac=${mac}&hours=${hours}`);
                let outsideData = null;
                
                // Load outside data if toggle is on and outside thermometer exists
                if (showOutside && outsideThermometer) {
                    outsideData = await secureApiFetch(`thermometer-history?mac=${outsideThermometer.mac}&hours=${hours}`);
                }

                if (data.success) {
                    if (!data.history || data.history.length === 0) {
                        if (noDataMessage) noDataMessage.style.display = 'block';
                        if (chart) {
                            chart.destroy();
                            chart = null;
                        }
                        document.getElementById('maxTemp').textContent = '--°F';
                        document.getElementById('minTemp').textContent = '--°F';
                    } else {
                        const validTemps = data.history
                            .filter(record => record.temperature !== null && record.temperature !== undefined)
                            .map(record => Number(record.temperature));
                        
                        if (validTemps.length > 0) {
                            const maxTemp = Math.max(...validTemps);
                            const minTemp = Math.min(...validTemps);
                            
                            document.getElementById('maxTemp').textContent = `${Math.round(maxTemp)}°F`;
                            document.getElementById('minTemp').textContent = `${Math.round(minTemp)}°F`;
                        }
                        
                        updateChart(data.history, hours, outsideData?.success ? outsideData.history : null);
                    }
                } else {
                    throw new Error(data.error || 'Failed to load history data');
                }
            } catch (error) {
                console.error('Error loading data:', error);
                if (error.message && error.message.includes('401')) {
                    showAuthError();
                } else {
                    showError('Error loading data: ' + error.message);
                }
            } finally {
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            }
        }

        function calculateHourlyAverages(data, hours = 24) {
            const timeGroups = {};
            // Change the interval calculation
            const interval = hours <= 24 ? 15 : // 15 minutes for 24 hours
                            hours <= 168 ? 60 : // 1 hour for 7 days
                            180;               // 3 hours for 30 days (changed from 240)
        
            data.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
            
            data.forEach(record => {
                const date = new Date(record.timestamp);
                if (interval === 15) {
                    const minutes = date.getMinutes();
                    date.setMinutes(Math.floor(minutes / 15) * 15, 0, 0);
                } else if (interval === 180) {  // Changed from 240
                    const hours = date.getHours();
                    date.setHours(Math.floor(hours / 3) * 3, 0, 0, 0);  // Changed from 4
                } else {
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
                
                if (record.temperature) timeGroups[timeKey].temps.push(parseFloat(record.temperature));
                if (record.humidity) timeGroups[timeKey].humidities.push(parseFloat(record.humidity));
            });
            
            const averagedData = Object.entries(timeGroups).map(([timestamp, data]) => ({
                timestamp: new Date(parseInt(timestamp)),
                temperature: data.temps.length ? data.temps.reduce((a, b) => a + b, 0) / data.temps.length : null,
                humidity: data.humidities.length ? data.humidities.reduce((a, b) => a + b, 0) / data.humidities.length : null
            }));

            averagedData.sort((a, b) => a.timestamp - b.timestamp);
            
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
                    
                    if (interval === 15) {
                        time = new Date(time.setMinutes(time.getMinutes() + 15));
                    } else if (interval === 180) {
                        time = new Date(time.setHours(time.getHours() + 3));
                    } else {
                        time = new Date(time.setHours(time.getHours() + 1));
                    }
                }
                return filledData;
            }

            return averagedData;
        }

        function updateChart(historyData, hours, outsideData = null) {
            const ctx = document.getElementById('tempChart').getContext('2d');
            const showHumidity = document.getElementById('showHumidity').checked;
            
            if (chart) {
                chart.destroy();
            }
        
            const averagedData = calculateHourlyAverages(historyData, hours);
            console.log("Averaged data:", averagedData);
            let averagedOutsideData = null;
            if (outsideData) {
                averagedOutsideData = calculateHourlyAverages(outsideData, hours);
            }
        
            const datasets = [
                {
                    label: 'Temperature',
                    data: averagedData.map(record => ({
                        x: record.timestamp,
                        y: record.temperature
                    })),
                    borderColor: '#D32F2F',
                    tension: 0.3,
                    pointRadius: 0,
                    spanGaps: true,
                    borderWidth: 2
                }
            ];
        
            // Only add humidity dataset if showHumidity is true
            if (showHumidity) {
                datasets.push({
                    label: 'Humidity',
                    data: averagedData.map(record => ({
                        x: record.timestamp,
                        y: record.humidity
                    })),
                    borderColor: '#1976D2',
                    borderDash: [5, 5],
                    tension: 0.3,
                    pointRadius: 2,
                    spanGaps: true
                });
            }
        
            if (averagedOutsideData) {
                datasets.push({
                    label: 'Outside Temperature',
                    data: averagedOutsideData.map(record => ({
                        x: record.timestamp,
                        y: record.temperature
                    })),
                    borderColor: '#FF9800',
                    tension: 0.3,
                    pointRadius: 0,
                    spanGaps: true,
                    borderWidth: 2
                });
        
                // Only add outside humidity dataset if showHumidity is true
                if (showHumidity) {
                    datasets.push({
                        label: 'Outside Humidity',
                        data: averagedOutsideData.map(record => ({
                            x: record.timestamp,
                            y: record.humidity
                        })),
                        borderColor: '#4CAF50',
                        borderDash: [5, 5],
                        tension: 0.3,
                        pointRadius: 2,
                        spanGaps: true
                    });
                }
            }
            console.log("Actual time scale options:", {
                unit: hours <= 24 ? 'hour' : 'day',
                min: averagedData[0]?.timestamp,
                max: averagedData[averagedData.length - 1]?.timestamp,
                ticks: {
                    maxTicksLimit: hours <= 24 ? 8 : hours <= 168 ? 7 : 10
                }
            });
            chart = new Chart(ctx, {
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
                                unit: hours <= 24 ? 'hour' : 'day',
                                displayFormats: {
                                    hour: 'ha',  // Shorter format for hours
                                    day: 'M/d',  // Shorter format for days
                                    week: 'M/d'  // Shorter format for weeks
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)',
                                drawBorder: false
                            },
                            display: true,
                            ticks: {
                                maxRotation: 0,
                                padding: 5,  // Reduced padding
                                color: '#666',
                                autoSkip: true,  // Allow auto-skipping of labels
                                maxTicksLimit: 8,  // Limit number of ticks for better spacing
                                font: {
                                    size: window.innerWidth < 768 ? 10 : 12  // Smaller font on mobile
                                }
                            }
                        },
                        y: {
                            min: -20,
                            max: 100,
                            display: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                stepSize: 20,  // Increased step size for fewer ticks
                                padding: 5,    // Reduced padding
                                color: '#666',
                                font: {
                                    size: window.innerWidth < 768 ? 10 : 12  // Smaller font on mobile
                                }
                            },
                            title: {
                                display: window.innerWidth >= 768,  // Hide title on mobile
                                text: 'Temperature (°F) / Humidity (%)',
                                color: '#666'
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
                                        if (label.includes('Temperature')) {
                                            label += Math.round(context.parsed.y) + '°F';
                                        } else {
                                            label += context.parsed.y.toFixed(1) + '%';
                                        }
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: window.innerWidth < 768 ? 10 : 20,  // Less padding on mobile
                                font: {
                                    size: window.innerWidth < 768 ? 10 : 12  // Smaller font on mobile
                                }
                            }
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>