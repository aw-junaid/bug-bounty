<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- VULN: No Content-Security-Policy header -->
    <!-- VULN: No X-Frame-Options -->
    <title>SlackClone - Login</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<div class="login-container">
    <h1>SlackClone</h1>
    <?php
    // VULN: Error message reflected directly - XSS if error contains user input
    if (!empty($_GET['error'])):
    ?>
    <div class="error-message">
        <!-- VULN: GET parameter reflected without escaping -->
        <?php echo $_GET['error']; ?>
    </div>
    <?php endif; ?>

    <form id="login-form" method="POST" action="/api/v1/auth/login.php">
        <!-- VULN: No CSRF token -->
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-primary">Sign In</button>
    </form>
    <p style="margin-top:16px;text-align:center;">
        <a href="/register" style="color:#1164a3;">Create an account</a>
    </p>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    const resp = await fetch('/api/v1/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
    });
    const data = await resp.json();

    if (data.success) {
        // VULN: Stores sensitive tokens in localStorage
        localStorage.setItem('api_token', data.token);
        localStorage.setItem('jwt', data.jwt);
        localStorage.setItem('user_id', data.user.user_id);
        localStorage.setItem('username', data.user.username);
        window.location.href = '/';
    } else {
        document.querySelector('.error-message')?.remove();
        const err = document.createElement('div');
        err.className = 'error-message';
        // VULN: Server error message reflected without escaping
        err.innerHTML = data.error;
        document.getElementById('login-form').prepend(err);
    }
});
</script>
</body>
</html>
