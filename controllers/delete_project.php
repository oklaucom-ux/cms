<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_projects');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    
    // Sever related tasks
    $pdo->prepare("UPDATE tasks SET project_id = 0 WHERE project_id = ?")->execute([$id]);

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Delete Project', 'Deleted Project {$id}')");
    header("Location: ../projects.php");
    exit();
}
