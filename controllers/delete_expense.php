<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_expenses');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Delete Expense', 'Deleted expense ID {$id}')");
    header("Location: ../expenses.php");
    exit();
}
