<?php
session_start();
require_once 'includes/db.php';
if (isset($_SESSION['login_id'])) {
    header("Location: dashboard.php");
    exit();
}
$companyName = 'Cyno Management';
$companyLogo = '';
foreach($pdo->query("SELECT * FROM settings") as $row) {
    if($row['setting_key'] === 'company_name') $companyName = $row['setting_value'];
    if($row['setting_key'] === 'company_logo') $companyLogo = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= htmlspecialchars($companyName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <style>
    .login-logo { width:auto; max-width:180px; height:auto; max-height:80px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
    .login-logo-placeholder { width:44px; height:44px; border-radius:12px; background:var(--primary-color); display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 20px; color:white; }
    .login-subtitle { font-size:13px; color:var(--text-muted); margin-bottom:28px; }
    .login-error { background:#fff5f5; border:1px solid #fecaca; color:#991b1b; border-radius:6px; padding:10px 14px; font-size:13px; margin-top:14px; text-align:left; }
    .login-page { background:var(--bg-body); }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <?php if ($companyLogo): ?>
            <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Logo" class="login-logo">
        <?php else: ?>
            <div class="login-logo-placeholder">🏢</div>
        <?php endif; ?>
        <h1><?= htmlspecialchars($companyName) ?></h1>
        <p class="login-subtitle">Sign in to your account</p>
        <form action="controllers/auth.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <div class="form-group">
                <label for="loginId">Login ID</label>
                <input type="text" id="loginId" name="login_id" required autocomplete="username" placeholder="Enter your login ID">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>
            <div style="text-align:right; margin-top:-8px; margin-bottom:20px;">
                <a href="forgot_password.php" style="font-size:13px;">Forgot Password?</a>
            </div>
            <button type="submit" class="login-button">Sign In</button>
            <?php if (isset($_GET['error'])): ?>
                <div class="login-error">⚠ <?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:6px;padding:10px 14px;font-size:13px;margin-top:14px;">✓ <?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
        </form>
    </div>
    <script>
      if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});
    </script>
</body>
</html>
