<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SlackClone - Register</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<div class="login-container">
    <h1>Create Account</h1>

    <form id="register-form">
        <!-- VULN: No CSRF token -->
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <!-- VULN: minlength=4 reflects weak password policy -->
            <input type="password" id="password" name="password" required minlength="4">
        </div>
        <button type="submit" class="btn-primary">Register</button>
    </form>
    <div id="error-msg" class="error-message" style="display:none;margin-top:8px;"></div>
    <p style="margin-top:16px;text-align:center;">
        <a href="/login" style="color:#1164a3;">Already have an account?</a>
    </p>
</div>

<script>
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const email    = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    const resp = await fetch('/api/v1/auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, email, password }),
    });
    const data = await resp.json();

    if (data.success) {
        window.location.href = '/login';
    } else {
        const errEl = document.getElementById('error-msg');
        // VULN: Server error message reflected via innerHTML
        errEl.innerHTML = data.error;
        errEl.style.display = 'block';
    }
});
</script>
</body>
</html>
