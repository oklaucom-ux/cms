<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_roles');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $role_id = $_POST['role_id'];
    $role_name = trim($_POST['role_name']);
    $description = trim($_POST['description']);
    $permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE roles SET role_id=?, role_name=?, description=?, permissions=? WHERE id=?");
        $stmt->execute([$role_id, $role_name, $description, $permissions, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Update Role']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO roles (role_id, role_name, description, permissions) VALUES (?, ?, ?, ?)");
        $stmt->execute([$role_id, $role_name, $description, $permissions]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Create Role']);
    }
    header("Location: ../roles.php");
}
?>
