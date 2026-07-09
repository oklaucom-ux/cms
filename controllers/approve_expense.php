<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'approve_expenses');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['action'])) {
    $id     = intval($_POST['id']);
    $action = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';

    $stmt = $pdo->prepare("UPDATE expenses SET status = ? WHERE id = ?");
    $stmt->execute([$action, $id]);

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', '{$action} Expense', 'Expense ID {$id} {$action}')");

    header("Location: ../expenses.php");
    exit();
}
