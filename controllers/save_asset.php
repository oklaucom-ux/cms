<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        requirePermission($pdo, 'edit_assets');
    } else {
        requirePermission($pdo, 'create_assets');
    }

    $asset_tag   = $_POST['asset_tag'];
    $name        = $_POST['name'];
    $type        = $_POST['type'];
    $assigned_to = $_POST['assigned_to'] ?: null;
    $status      = $_POST['status'];
    $condition   = $_POST['condition'];

    // Auto-update status if assigned_to is set
    if ($assigned_to && $status === 'Unassigned') $status = 'Assigned';
    if (!$assigned_to && $status === 'Assigned')  $status = 'Unassigned';

    if ($id) {
        $stmt = $pdo->prepare("UPDATE assets SET asset_tag=?, name=?, type=?, assigned_to=?, status=?, condition=? WHERE id=?");
        $stmt->execute([$asset_tag, $name, $type, $assigned_to, $status, $condition, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Update Asset']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO assets (asset_tag, name, type, assigned_to, status, condition) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$asset_tag, $name, $type, $assigned_to, $status, $condition]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Register Asset']);
    }

    header("Location: ../assets.php");
    exit();
}
