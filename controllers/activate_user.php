<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';

if (!hasPermission($pdo, 'manage_users')) {
    die("Unauthorized.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = trim($_POST['user_id']);
    
    // Verify they are actually pending docs
    $chk = $pdo->prepare("SELECT status, name FROM users WHERE login_id = ?");
    $chk->execute([$user_id]);
    $user = $chk->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['status'] === 'Pending_Docs') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'Active' WHERE login_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'User Activation']);
        
        createNotification($pdo, $user_id, 'Account Activated', 'HR has successfully validated your documents. Your account is fully active on next login.', 'dashboard.php');
        
        $_SESSION['flash_success'] = "Documents verified! {$user['name']} has been granted full Active access.";
    } else {
        $_SESSION['flash_error'] = "User is not in a Pending Documents state.";
    }
}
header("Location: ../users.php");
exit();
