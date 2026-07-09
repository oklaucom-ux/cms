<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_zones');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM zones WHERE id=?");
    $stmt->execute([$_POST['id']]);
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Delete Zone', 'Deleted zone ID {$_POST['id']}')");
    header("Location: ../zones.php");
}
?>
