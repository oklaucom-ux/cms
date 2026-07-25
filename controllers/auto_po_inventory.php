<?php
// controllers/auto_po_inventory.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

try {
    // Find all low stock items
    $stmtLow = $pdo->query("SELECT * FROM inventory_items WHERE quantity <= min_stock_alert");
    $lowItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lowItems)) {
        echo json_encode(['success' => false, 'error' => 'No low-stock items found. All inventory levels are optimal!']);
        exit();
    }

    $poNumber = 'PO-RESTOCK-' . date('Ymd-His');
    $totalAmount = 0;

    foreach ($lowItems as $it) {
        $reorderQty = max(($it['min_stock_alert'] * 3) - $it['quantity'], 20);
        $totalAmount += floatval($it['purchase_price']) * $reorderQty;
    }

    $pdo->beginTransaction();

    // Insert PO in purchase_orders if table exists, or create procurement entry
    $stmtPO = $pdo->prepare("INSERT INTO purchase_orders (po_number, vendor_name, total_amount, status, created_by, created_at) VALUES (?, 'Multiple Suppliers', ?, 'Draft', ?, CURRENT_TIMESTAMP)");
    $stmtPO->execute([$poNumber, $totalAmount, $_SESSION['login_id']]);
    $poId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO po_items (po_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");

    $itemCount = 0;
    foreach ($lowItems as $it) {
        $reorderQty = max(($it['min_stock_alert'] * 3) - $it['quantity'], 20);
        $lineTotal  = floatval($it['purchase_price']) * $reorderQty;
        $stmtItem->execute([$poId, $it['name'] . ' (SKU: ' . $it['sku'] . ')', $reorderQty, $it['purchase_price'], $lineTotal]);
        $itemCount++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'po_number' => $poNumber,
        'items_count' => $itemCount,
        'total_amount' => $totalAmount,
        'message' => "Restock Purchase Order draft ({$poNumber}) created for {$itemCount} low-stock items!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Auto PO Error: ' . $e->getMessage()]);
}
