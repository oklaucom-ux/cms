<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_vendors');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $vendor_id = $_POST['vendor_id'] ?? null;
    $contract_title = trim($_POST['contract_title'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');

    if (empty($vendor_id) || empty($contract_title)) {
        $_SESSION['flash_error'] = "Vendor and Contract Title are required.";
        header("Location: ../vendor_portal.php");
        exit();
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE vendor_contracts SET contract_title=?, start_date=?, end_date=?, value=?, status=? WHERE id=? AND vendor_id=?");
            $stmt->execute([$contract_title, $start_date, $end_date, $value, $status, $id, $vendor_id]);
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Update Contract', "Contract $contract_title updated"]);
            $_SESSION['flash_success'] = "Contract updated successfully.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO vendor_contracts (vendor_id, contract_title, start_date, end_date, value, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$vendor_id, $contract_title, $start_date, $end_date, $value, $status]);
            
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Add Contract', "Contract '$contract_title' added for Vendor ID $vendor_id"]);
            $_SESSION['flash_success'] = "Contract added successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
    }
}

header("Location: ../vendor_portal.php");
exit();
?>
