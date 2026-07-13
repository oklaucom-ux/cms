<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_activities');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM activities WHERE id=?");
    $stmt->execute([$_POST['id']]);
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Delete Activity', '']);
    header("Location: ../activities.php");
}
?>
