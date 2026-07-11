<?php
session_start();
require_once 'includes/db.php';
$companyName = $GLOBAL_SETTINGS['company_name'] ?? 'Cyno Management';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

$valid_token = false;

if ($token && $email) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > CURRENT_TIMESTAMP");
    $stmt->execute([$email, $token]);
    if ($stmt->fetch()) {
        $valid_token = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - <?= htmlspecialchars($companyName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
</head>
<body class="login-page">
    <div class="login-container">
        <h1><?= htmlspecialchars($companyName) ?></h1>
        
        <?php if ($valid_token): ?>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 20px;">Create a new secure password for your account.</p>
            <form action="controllers/process_reset.php" method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Minimum 8 characters">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" class="login-button border-shadow">Reset Password</button>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" style="color: #EF4444; margin-top: 15px; text-align: center; padding: 10px; background: rgba(239,68,68,0.1); border-radius: 6px;"><?= htmlspecialchars($_GET['error']) ?></div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="color: #EF4444; margin-bottom: 10px;">Invalid or Expired Link</h3>
                <p style="color: var(--text-muted);">The password reset link is invalid or has expired.</p>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="forgot_password.php" class="login-button border-shadow" style="display: inline-block; text-decoration: none; padding: 12px 20px;">Request New Link</a>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.9em; font-weight: 500;">&larr; Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
      if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});
    </script>
</body>
</html>
