<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_documents');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        $path = '../' . $doc['file_path'];
        if (file_exists($path)) {
            unlink($path); // physical delete
        }
        $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Delete Document']);
    }
    header("Location: ../documents.php");
}
?>
