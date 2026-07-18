<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_vendors');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Optional: Could check if vendor has active contracts before deleting
    $pdo->prepare("DELETE FROM vendor_contracts WHERE vendor_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM vendors WHERE id=?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Delete Vendor', "Vendor ID $id deleted"]);
    $_SESSION['flash_success'] = "Vendor deleted successfully.";
}

header("Location: ../vendor_portal.php");
exit();
?>
