<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasPermission($pdo, 'view_invoices') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $company = $_POST['company_name'];
        $contact = $_POST['contact_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $terms = $_POST['payment_terms'];
        $score = intval($_POST['scorecard_rating']);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE vendors SET company_name=?, contact_name=?, email=?, phone=?, payment_terms=?, scorecard_rating=? WHERE id=?");
            $stmt->execute([$company, $contact, $email, $phone, $terms, $score, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO vendors (company_name, contact_name, email, phone, payment_terms, scorecard_rating) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$company, $contact, $email, $phone, $terms, $score]);
        }
        
        header("Location: ../vendor_crm.php?msg=VendorSaved");
        exit;
    }
    
    if ($action === 'toggle_status') {
        $id = intval($_POST['id']);
        
        // get current status
        $stmt = $pdo->prepare("SELECT status FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        $curr = $stmt->fetchColumn();
        
        $newStat = $curr === 'Active' ? 'Inactive' : 'Active';
        $pdo->prepare("UPDATE vendors SET status = ? WHERE id = ?")->execute([$newStat, $id]);
        
        header("Location: ../vendor_crm.php?msg=StatusUpdated");
        exit;
    }
}
?>
