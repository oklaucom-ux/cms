<?php

require_once 'includes/db.php';
$companyName = $GLOBAL_SETTINGS['company_name'] ?? 'Cyno Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - <?= htmlspecialchars($companyName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
</head>
<body class="login-page">
    <div class="login-container">
        <h1><?= htmlspecialchars($companyName) ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 20px;">Enter your email address and we'll send you a link to reset your password.</p>
        
        <form action="controllers/request_reset.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="john.doe@example.com">
            </div>
            <button type="submit" class="login-button border-shadow">Send Reset Link</button>
            
            <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="success-message" style="color: #10B981; margin-top: 15px; text-align: center; padding: 10px; background: rgba(16,185,129,0.1); border-radius: 6px;">If the email exists, a reset link has been sent.</div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message" style="color: #EF4444; margin-top: 15px; text-align: center; padding: 10px; background: rgba(239,68,68,0.1); border-radius: 6px;"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.9em; font-weight: 500;">&larr; Back to Login</a>
            </div>
        </form>
    </div>
    <script>
      if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});
    </script>
</body>
</html>
