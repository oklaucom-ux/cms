<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Ensure table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            po_number TEXT NOT NULL UNIQUE,
            vendor_name TEXT NOT NULL,
            department TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'Pending Approval',
            created_by TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    if ($action === 'create') {
        $vendor = $_POST['vendor_name'];
        $dept = $_POST['department'];
        $amount = floatval($_POST['amount']);
        $desc = $_POST['description'];
        $po_num = 'PO-' . date('Ym') . '-' . rand(1000,9999);
        
        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, vendor_name, department, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$po_num, $vendor, $dept, $amount, $desc, $_SESSION['login_id']]);
        
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Create PO', 'Raised PO {$po_num} for \${$amount}')");
        header("Location: ../procurement.php?msg=POCreated");
        exit;
    }
    
    if ($action === 'approve' || $action === 'reject') {
        if (!hasPermission($pdo, 'view_invoices') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', '{$status} PO', 'Changed status of PO ID {$id} to {$status}')");
        header("Location: ../procurement.php?msg=POUpdated");
        exit;
    }
}
?>
