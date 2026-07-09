<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_forms');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $pdo->prepare("DELETE FROM dynamic_forms WHERE id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM form_assignments WHERE form_id=?")->execute([$id]);
    
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Delete Form', 'Deleted form ID {$id}')");
    header("Location: ../forms.php");
}
?>
