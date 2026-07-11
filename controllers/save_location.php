<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'create_locations');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $location_id = $_POST['location_id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $pin_code = $_POST['pin_code'];
    $zone = $_POST['zone'];
    $parent_location = $_POST['parent_location'];
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE locations SET location_id=?, name=?, address=?, pin_code=?, zone=?, parent_location=? WHERE id=?");
        $stmt->execute([$location_id, $name, $address, $pin_code, $zone, $parent_location, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Update Location']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO locations (location_id, name, address, pin_code, zone, parent_location) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$location_id, $name, $address, $pin_code, $zone, $parent_location]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Create Location']);
    }
    header("Location: ../locations.php");
}
?>
