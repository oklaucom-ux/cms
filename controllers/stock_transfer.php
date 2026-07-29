<?php
// controllers/stock_transfer.php - Multi-Branch Inter-Warehouse Stock Transfer Engine
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/send_push_notification.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

// Auto-migrate inventory_transfers table
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_transfers (
        id {$pkDef},
        item_id INT NOT NULL,
        from_branch VARCHAR(100) NOT NULL,
        to_branch VARCHAR(100) NOT NULL,
        quantity INT NOT NULL,
        notes TEXT,
        requested_by VARCHAR(255),
        status VARCHAR(50) DEFAULT 'Completed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$item_id     = (int)($_POST['item_id'] ?? 0);
$from_branch = trim($_POST['from_branch'] ?? 'Global HQ Warehouse');
$to_branch   = trim($_POST['to_branch'] ?? 'North Branch Store');
$quantity    = (int)($_POST['quantity'] ?? 0);
$notes       = trim($_POST['notes'] ?? '');

if ($item_id <= 0 || $quantity <= 0 || $from_branch === $to_branch) {
    echo json_encode(['success' => false, 'error' => 'Valid Item, positive Quantity, and different Source/Destination branches are required.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Check item
    $stmtItem = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmtItem->execute([$item_id]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Inventory item ID {$item_id} not found.");
    }

    if ($quantity > (int)$item['quantity']) {
        throw new Exception("Insufficient stock in source warehouse. Available: {$item['quantity']} units.");
    }

    // Log stock transfer
    $stmtIns = $pdo->prepare("INSERT INTO inventory_transfers (item_id, from_branch, to_branch, quantity, notes, requested_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->execute([$item_id, $from_branch, $to_branch, $quantity, $notes, $_SESSION['login_id']]);
    $transferId = $pdo->lastInsertId();

    // Log transaction
    $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, 'Stock Transfer', 0, ?, ?, CURRENT_TIMESTAMP)")
        ->execute([$item_id, "Inter-branch transfer of {$quantity} units from {$from_branch} to {$to_branch}", $_SESSION['login_id']]);

    $pdo->commit();

    // Trigger Web Push Notification
    sendWebPushNotification($pdo, 'all', "🔀 Inter-Branch Stock Transfer", "{$quantity} units of '{$item['name']}' transferred from {$from_branch} to {$to_branch}.", "inventory.php");

    echo json_encode([
        'success' => true,
        'transfer_id' => $transferId,
        'item_name' => $item['name'],
        'quantity' => $quantity,
        'from_branch' => $from_branch,
        'to_branch' => $to_branch,
        'message' => "Successfully transferred {$quantity} units of '{$item['name']}' from {$from_branch} to {$to_branch}!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
