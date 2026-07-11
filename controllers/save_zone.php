<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'create_zones');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $zone_id = $_POST['zone_id'];
    $zone_name = $_POST['zone_name'];
    $description = $_POST['description'];
    $created_date = $_POST['created_date'];
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE zones SET zone_id=?, zone_name=?, description=?, created_date=? WHERE id=?");
        $stmt->execute([$zone_id, $zone_name, $description, $created_date, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Update Zone']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO zones (zone_id, zone_name, description, created_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$zone_id, $zone_name, $description, $created_date]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Create Zone']);
    }
    header("Location: ../zones.php");
}
?>
