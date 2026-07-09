<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_locations');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM locations WHERE id=?");
    $stmt->execute([$_POST['id']]);
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Delete Location', 'Deleted location ID {$_POST['id']}')");
    header("Location: ../locations.php");
}
?>
