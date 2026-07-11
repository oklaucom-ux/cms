<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_roles');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $designation_id = $_POST['designation_id'];
    $designation_name = $_POST['designation_name'];
    $department = $_POST['department'];
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE designations SET designation_id=?, designation_name=?, department=? WHERE id=?");
        $stmt->execute([$designation_id, $designation_name, $department, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Update Designation']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO designations (designation_id, designation_name, department) VALUES (?, ?, ?)");
        $stmt->execute([$designation_id, $designation_name, $department]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Create Designation']);
    }
    header("Location: ../roles.php");
}
?>
