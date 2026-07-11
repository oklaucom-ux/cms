<?php
session_start();
require_once 'includes/db.php';
if(isset($_SESSION['login_id'])) {
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, 'Logout', 'User logged out.')")->execute([$_SESSION['login_id']]);
}
session_destroy();
header("Location: login.php");
exit();
?>
