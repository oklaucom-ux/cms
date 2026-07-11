<?php
session_start();
require_once '../includes/db.php';

if (!hasPermission($pdo, 'manage_users')) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $target_user = trim($_POST['user_id'] ?? 'unknown');
    
    $stmt = $pdo->prepare("SELECT file_path, title FROM user_documents WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        $base_dir = realpath(__DIR__ . '/../uploads/');
        $full_path = realpath('../' . $doc['file_path']);
        // Security: only delete if file is inside the uploads directory
        if ($full_path && $base_dir && strpos($full_path, $base_dir) === 0) {
            unlink($full_path);
        }
        $pdo->prepare("DELETE FROM user_documents WHERE id=?")->execute([$id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Delete HR File']);
    }
}
header("Location: ../users.php");
exit();
