// login-session.js

/**
 * Stores login session information in the browser's localStorage
 * @param {string} username - The user's username
 * @param {string} token - Authentication token or session ID
 * @param {boolean} rememberMe - Whether to keep the session persistent
 */
function storeLoginSession(username, token, rememberMe = false) {
    // Choose storage type based on remember me option
    const storage = rememberMe ? localStorage : sessionStorage;
    
    // Store login information
    storage.setItem('homeio_username', username);
    storage.setItem('homeio_token', token);
    storage.setItem('homeio_login_time', Date.now());
}

/**
 * Clears the stored login session from browser storage
 */
function clearLoginSession() {
    // Clear from both storage types to be safe
    localStorage.removeItem('homeio_username');
    localStorage.removeItem('homeio_token');
    localStorage.removeItem('homeio_login_time');
    
    sessionStorage.removeItem('homeio_username');
    sessionStorage.removeItem('homeio_token');
    sessionStorage.removeItem('homeio_login_time');
}

/**
 * Gets refresh token from cookie
 */
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

/**
 * Checks if a valid login session exists in browser storage
 * @returns {Object|null} Session information if valid, null otherwise
 */
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

/**
 * Attempts to auto-login using stored session data
 */
function attemptAutoLogin() {
    const session = getStoredSession();
    if (!session) return;
    
    // Show a loading indicator
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.innerHTML = '<div class="loading">Logging in...</div>';
    }
    
    // Send stored credentials to the server for validation
    fetch('verify-session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            username: session.username,
            token: session.token,
            refresh_token: session.refreshToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // If we got a new token, update it
            if (data.new_token) {
                storeLoginSession(session.username, data.new_token, !!session.refreshToken);
            }
            
            // Redirect to main page on success
            window.location.href = 'index.php';
        } else {
            // If validation fails, clear stored session and show login form
            clearLoginSession();
            if (loginForm) {
                // Reset the login form
                window.location.reload();
            }
        }
    })
    .catch(error => {
        console.error('Auto-login error:', error);
        // On error, clear session and show login form
        clearLoginSession();
        if (loginForm) {
            window.location.reload();
        }
    });
}

// Check for stored session when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Only run on login page
    if (document.getElementById('login-form')) {
        attemptAutoLogin();
    }
});