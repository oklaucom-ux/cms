<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'create_policies');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $policy_id = $_POST['policy_id'];
    $title = $_POST['title'];
    $category = $_POST['category'];
    $version = $_POST['version'];
    $content = $_POST['content'];
    $status = $_POST['status'];
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE policies SET policy_id=?, title=?, category=?, version=?, content=?, status=? WHERE id=?");
        $stmt->execute([$policy_id, $title, $category, $version, $content, $status, $id]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Update Policy', 'Updated policy {$policy_id}')");
    } else {
        $stmt = $pdo->prepare("INSERT INTO policies (policy_id, title, category, version, content, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$policy_id, $title, $category, $version, $content, $status]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Create Policy', 'Created policy {$policy_id}')");
    }
    header("Location: ../policies.php");
}
?>
