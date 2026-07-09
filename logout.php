<?php
session_start();
require_once 'includes/db.php';
if(isset($_SESSION['login_id'])) {
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Logout', 'User logged out.')");
}
session_destroy();
header("Location: login.php");
exit();
?>
