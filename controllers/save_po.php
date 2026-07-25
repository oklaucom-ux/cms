<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Ensure table exists
if ($action === 'create') {
        $vendor = $_POST['vendor_name'];
        $dept = $_POST['department'];
        $amount = floatval($_POST['amount']);
        $desc = $_POST['description'];
        $po_num = 'PO-' . date('Ym') . '-' . rand(1000,9999);
        
        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, vendor_name, department, amount, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$po_num, $vendor, $dept, $amount, $desc, $_SESSION['login_id']]);
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Create PO', '']);
        header("Location: ../procurement.php?msg=POCreated");
        exit;
    }
    
    if ($action === 'approve' || $action === 'reject') {
        if (!hasPermission($pdo, 'view_invoices') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Auto-Restock Inventory if PO is Approved
        if ($status === 'Approved') {
            try {
                $stmtPO = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
                $stmtPO->execute([$id]);
                $po = $stmtPO->fetch(PDO::FETCH_ASSOC);

                if ($po) {
                    // Find low-stock items or match by department
                    $stmtItems = $pdo->query("SELECT * FROM inventory_items WHERE quantity <= min_stock_alert");
                    $lowStockItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($lowStockItems as $it) {
                        $reorderQty = max(($it['min_stock_alert'] * 3) - $it['quantity'], 20);
                        $pdo->prepare("UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?")->execute([$reorderQty, $it['id']]);

                        $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, 'Stock In', ?, ?, ?, CURRENT_TIMESTAMP)")
                            ->execute([$it['id'], $reorderQty, "Approved PO Restock ({$po['po_number']})", $_SESSION['login_id']]);
                    }
                }
            } catch (Exception $e) {}
        }
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], "{$status} PO", ""]);
        header("Location: ../procurement.php?msg=POUpdated");
        exit;
    }
}
?>


