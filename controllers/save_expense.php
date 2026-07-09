<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';
requirePermission($pdo, 'create_expenses');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id    = $_SESSION['login_id'];
    $project_id = intval($_POST['project_id'] ?? 0);
    $category   = $_POST['category'];
    $amount     = floatval($_POST['amount']);
    $description= $_POST['description'];
    $receipt_url= $_POST['receipt_url'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, project_id, category, amount, description, receipt_url, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$user_id, $project_id, $category, $amount, $description, $receipt_url]);
    $expense_id = $pdo->lastInsertId();

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$user_id}', 'Log Expense', 'Logged \${$amount} expense: {$category}')");

    fireWebhook($pdo, 'expense_created', [
        'expense_id' => $expense_id,
        'user_id' => $user_id,
        'amount' => $amount,
        'category' => $category,
        'description' => $description
    ]);

    header("Location: ../expenses.php");
    exit();
}
