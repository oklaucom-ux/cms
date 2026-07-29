<?php
// controllers/auto_po_inventory.php - Automated Low-Stock & Expiry Restock PO Generator
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/send_push_notification.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

try {
    // Auto-migrate purchase_orders table
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
        id {$pkDef},
        po_number VARCHAR(100) NOT NULL,
        vendor_name VARCHAR(255) DEFAULT 'Primary Supplier',
        department VARCHAR(100) DEFAULT 'Pharmacy & Inventory',
        amount DECIMAL(12,2) DEFAULT 0,
        description TEXT,
        status VARCHAR(50) DEFAULT 'Pending Approval',
        created_by VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $today30Days = date('Y-m-d', strtotime('+30 days'));

    // Find low stock items or expiring items
    $stmtLow = $pdo->query("SELECT * FROM inventory_items WHERE quantity <= min_stock_alert OR (expiry_date IS NOT NULL AND expiry_date != '' AND expiry_date <= '{$today30Days}')");
    $lowItems = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
    $itemCount = count($lowItems);

    if ($itemCount === 0) {
        echo json_encode(['success' => false, 'error' => 'No low-stock or expiring items found. All inventory levels are optimal!']);
        exit();
    }

    $poNumber = 'PO-RESTOCK-' . date('Ymd-His');
    $totalAmount = 0;

    foreach ($lowItems as $it) {
        $reorderQty = max(($it['min_stock_alert'] * 3) - $it['quantity'], 20);
        $totalAmount += floatval($it['purchase_price']) * $reorderQty;
    }

    $pdo->beginTransaction();

    $description = "Auto-generated restock Purchase Order for {$itemCount} low-stock/expiring inventory items.";

    // Insert PO in purchase_orders table
    $stmtPO = $pdo->prepare("INSERT INTO purchase_orders (po_number, vendor_name, department, amount, description, status, created_by, created_at) VALUES (?, 'Primary Supplier', 'Pharmacy & Inventory', ?, ?, 'Pending Approval', ?, CURRENT_TIMESTAMP)");
    $stmtPO->execute([$poNumber, $totalAmount, $description, $_SESSION['login_id']]);

    $pdo->commit();

    // Trigger Web Push Notification
    sendWebPushNotification($pdo, 'all', "🛒 Restock PO Drafted", "Auto-PO {$poNumber} generated for {$itemCount} low-stock items.", "procurement.php");

    echo json_encode([
        'success' => true,
        'po_number' => $poNumber,
        'items_count' => $itemCount,
        'total_amount' => $totalAmount,
        'message' => "Restock Purchase Order draft ({$poNumber}) created for {$itemCount} low-stock & expiring items!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Auto PO Error: ' . $e->getMessage()]);
}
