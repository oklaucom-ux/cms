<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_vendors');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $service_category = trim($_POST['service_category'] ?? '');

    if (empty($company_name) || empty($contact_name) || empty($email)) {
        $_SESSION['flash_error'] = "Company Name, Contact Name, and Email are required.";
        header("Location: ../vendor_portal.php");
        exit();
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE vendors SET company_name=?, contact_name=?, email=?, phone=?, status=?, tax_id=?, service_category=? WHERE id=?");
        $stmt->execute([$company_name, $contact_name, $email, $phone, $status, $tax_id, $service_category, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Update Vendor', "Vendor $company_name updated"]);
        $_SESSION['flash_success'] = "Vendor updated successfully.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO vendors (company_name, contact_name, email, phone, status, tax_id, service_category) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$company_name, $contact_name, $email, $phone, $status, $tax_id, $service_category]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Add Vendor', "Vendor $company_name added"]);
        $_SESSION['flash_success'] = "Vendor added successfully.";
    }
}

header("Location: ../vendor_portal.php");
exit();
?>
