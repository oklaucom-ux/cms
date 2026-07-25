<?php
// controllers/adjust_stock.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$item_id         = (int)($_POST['item_id'] ?? 0);
$type            = trim($_POST['type'] ?? 'Stock In');
$quantity_change = (int)($_POST['quantity_change'] ?? 0);
$reason          = trim($_POST['reason'] ?? '');

if ($item_id <= 0 || $quantity_change <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid Item ID and positive quantity change required.']);
    exit();
}

try {
    $stmtItem = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmtItem->execute([$item_id]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Inventory item not found.']);
        exit();
    }

    $currentQty = (int)$item['quantity'];

    if ($type === 'Stock Out' || $type === 'Expired Write-off' || $type === 'Damage Write-off') {
        if ($quantity_change > $currentQty) {
            echo json_encode(['success' => false, 'error' => "Insufficient stock. Available stock is {$currentQty} units."]);
            exit();
        }
        $newQty = $currentQty - $quantity_change;
        $actualChange = -$quantity_change;
    } else {
        $newQty = $currentQty + $quantity_change;
        $actualChange = $quantity_change;
    }

    $pdo->beginTransaction();

    // Update item quantity
    $stmtUpd = $pdo->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
    $stmtUpd->execute([$newQty, $item_id]);

    // Insert transaction log
    $stmtLog = $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmtLog->execute([$item_id, $type, $actualChange, $reason ?: $type, $_SESSION['login_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'new_quantity' => $newQty,
        'message' => "Stock successfully updated! New quantity: {$newQty} units."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Stock adjustment error: ' . $e->getMessage()]);
}
