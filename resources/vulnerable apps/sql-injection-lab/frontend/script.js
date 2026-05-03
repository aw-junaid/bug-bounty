const API_BASE_URL = 'http://localhost:5000/api';

// Tab switching
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const tabName = tab.dataset.tab;
        
        // Update active tab
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Show corresponding content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');
    });
});

// Login form
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    
    try {
        const response = await fetch(`${API_BASE_URL}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        displayResult(data, response.status);
    } catch (error) {
        displayError('Connection failed. Is the backend running?');
    }
});

// Registration form
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const userData = {
        username: document.getElementById('reg-username').value,
        password: document.getElementById('reg-password').value,
        age: parseInt(document.getElementById('reg-age').value) || 0,
        date_of_join: document.getElementById('reg-date').value
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
        
        const data = await response.json();
        displayResult(data, response.status);
    } catch (error) {
        displayError('Registration failed. Check your connection.');
    }
});

// SQL Analyzer
document.getElementById('analyze-btn').addEventListener('click', async () => {
    const input = document.getElementById('analyzer-input').value;
    
    if (!input) {
        alert('Please enter some text to analyze');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/analyze`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ input })
        });
        
        const data = await response.json();
        displayAnalysis(data.analysis);
    } catch (error) {
        displayError('Analysis failed. Is the backend running?');
    }
});

// Vulnerable login demo
document.getElementById('vulnerable-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('vuln-username').value;
    const password = document.getElementById('vuln-password').value;
    
    try {
        const response = await fetch(`${API_BASE_URL}/login/vulnerable`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        displayVulnerableResult(data);
    } catch (error) {
        displayError('Connection failed. Is the backend running?');
    }
});

// Payload click handler
document.querySelectorAll('.payload-item').forEach(item => {
    item.addEventListener('click', () => {
        const payload = item.dataset.payload;
        document.getElementById('analyzer-input').value = payload;
        
        // Switch to analyzer tab
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelector('[data-tab="analyzer"]').classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('analyzer-tab').classList.add('active');
        
        // Trigger analysis
        document.getElementById('analyze-btn').click();
    });
});

function displayResult(data, statusCode) {
    const container = document.getElementById('result-container');
    const content = document.getElementById('result-content');
    const title = document.getElementById('result-title');
    
    container.style.display = 'block';
    
    if (data.status === 'blocked' || data.injections) {
        // SQL Injection detected
        title.textContent = '🚫 SQL Injection Detected!';
        title.style.color = '#dc3545';
        
        let html = '<div class="injection-alert">';
        html += `<h3>⚠️ ${data.message || 'SQL Injection Attempt Blocked'}</h3>`;
        html += '<p>The system detected potential SQL injection patterns in your input:</p>';
        html += '<ul class="injection-list">';
        data.injections.forEach(injection => {
            html += `<li>🔍 ${injection}</li>`;
        });
        html += '</ul>';
        html += '</div>';
        
        content.innerHTML = html;
    } else if (data.status === 'success') {
        // Successful login/registration
        title.textContent = '✅ Success';
        title.style.color = '#28a745';
        
        let html = '<div class="success-box">';
        html += `<p><strong>${data.message}</strong></p>`;
        
        if (data.user) {
            html += '<div class="user-data">';
            html += '<h4>User Details:</h4>';
            for (const [key, value] of Object.entries(data.user)) {
                html += `<p><strong>${key}:</strong> ${value}</p>`;
            }
            html += '</div>';
        }
        
        if (data.user_id) {
            html += `<p>User ID: ${data.user_id}</p>`;
        }
        
        html += '</div>';
        content.innerHTML = html;
    } else {
        title.textContent = '❌ Error';
        title.style.color = '#dc3545';
        content.innerHTML = `<p>${data.message}</p>`;
    }
    
    container.scrollIntoView({ behavior: 'smooth' });
}

function displayAnalysis(analysis) {
    const resultBox = document.getElementById('analysis-result');
    const content = document.getElementById('analysis-content');
    
    resultBox.style.display = 'block';
    
    let html = '<div class="analysis-detail">';
    
    // Risk level badge
    const riskClass = `risk-${analysis.risk_level}`;
    html += `<p><strong>Risk Level:</strong> <span class="risk-badge ${riskClass}">${analysis.risk_level.toUpperCase()}</span></p>`;
    html += `<p><strong>Patterns Matched:</strong> ${analysis.total_patterns_matched}</p>`;
    
    if (analysis.has_injection) {
        html += '<div class="injection-alert">';
        html += '<h4>Detected SQL Injection Patterns:</h4>';
        html += '<ul class="injection-list">';
        analysis.injections_found.forEach(injection => {
            html += `<li>${injection}</li>`;
        });
        html += '</ul>';
        
        if (analysis.details) {
            html += '<h4>Detailed Analysis:</h4>';
            analysis.details.forEach(detail => {
                html += `<div class="injection-detail">`;
                html += `<span class="category-badge">${detail.category}</span>`;
                html += `<p>Matched: <code>${detail.matched_text}</code></p>`;
                html += `<p><small>${detail.description}</small></p>`;
                html += `</div><br>`;
            });
        }
        
        html += '</div>';
    } else {
        html += '<div class="success-box">';
        html += '<p>No SQL injection patterns detected. Input appears safe.</p>';
        html += '</div>';
    }
    
    html += '</div>';
    content.innerHTML = html;
    
    resultBox.scrollIntoView({ behavior: 'smooth' });
}

function displayVulnerableResult(data) {
    const container = document.getElementById('result-container');
    const content = document.getElementById('result-content');
    const title = document.getElementById('result-title');
    
    container.style.display = 'block';
    
    if (data.status === 'vulnerable') {
        title.textContent = '⚠️ Vulnerable Query Executed';
        title.style.color = '#ff6b6b';
        
        let html = '<div class="injection-alert">';
        html += '<h3>SQL Injection Possible!</h3>';
        
        // Show executed query
        html += '<h4>Executed Query:</h4>';
        html += `<div class="query-display">${data.query_executed}</div>`;
        
        // Show detected injections
        if (data.detected_injections && data.detected_injections.length > 0) {
            html += '<h4>Detected Patterns:</h4>';
            html += '<ul class="injection-list">';
            data.detected_injections.forEach(injection => {
                html += `<li>${injection}</li>`;
            });
            html += '</ul>';
        }
        
        // Show results
        if (data.results && data.results.length > 0) {
            html += '<h4>Query Results:</h4>';
            data.results.forEach(user => {
                html += '<div class="user-data">';
                for (const [key, value] of Object.entries(user)) {
                    html += `<p><strong>${key}:</strong> ${value}</p>`;
                }
                html += '</div>';
            });
        }
        
        html += '</div>';
        content.innerHTML = html;
    } else {
        displayResult(data, 200);
    }
    
    container.scrollIntoView({ behavior: 'smooth' });
}

function displayError(message) {
    const container = document.getElementById('result-container');
    const content = document.getElementById('result-content');
    const title = document.getElementById('result-title');
    
    container.style.display = 'block';
    title.textContent = '🔌 Connection Error';
    title.style.color = '#dc3545';
    content.innerHTML = `<div class="injection-alert"><p>${message}</p></div>`;
    
    container.scrollIntoView({ behavior: 'smooth' });
}
