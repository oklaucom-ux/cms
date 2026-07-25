<?php
// controllers/audit_stock.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$item_id      = (int)($_POST['item_id'] ?? 0);
$physical_qty = (int)($_POST['physical_qty'] ?? 0);
$notes        = trim($_POST['notes'] ?? 'Physical Stock Audit');

if ($item_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Item ID is required.']);
    exit();
}

try {
    $stmtItem = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmtItem->execute([$item_id]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Item not found.']);
        exit();
    }

    $system_qty = (int)$item['quantity'];
    $variance   = $physical_qty - $system_qty;

    $pdo->beginTransaction();

    // Log physical stock audit
    $stmtAudit = $pdo->prepare("INSERT INTO inventory_audits (item_id, system_qty, physical_qty, variance, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmtAudit->execute([$item_id, $system_qty, $physical_qty, $variance, $notes, $_SESSION['login_id']]);

    // Update item quantity to match physical count
    $stmtUpd = $pdo->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
    $stmtUpd->execute([$physical_qty, $item_id]);

    // Log transaction
    $txnType = $variance >= 0 ? 'Audit Stock Increase' : 'Audit Stock Reduction';
    $stmtTxn = $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmtTxn->execute([$item_id, $txnType, $variance, "Physical Audit Reconciliation: {$notes}", $_SESSION['login_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'system_qty' => $system_qty,
        'physical_qty' => $physical_qty,
        'variance' => $variance,
        'message' => "Stock reconciled! Physical count ({$physical_qty}) saved with variance of " . ($variance >= 0 ? "+{$variance}" : "{$variance}") . " units."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Stock Audit error: ' . $e->getMessage()]);
}
