<?php
// controllers/get_stock_history.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$item_id = (int)($_GET['item_id'] ?? 0);

if ($item_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid Item ID is required.']);
    exit();
}

try {
    $stmtItem = $pdo->prepare("SELECT id, sku, name, quantity, batch_number FROM inventory_items WHERE id = ?");
    $stmtItem->execute([$item_id]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Inventory item not found.']);
        exit();
    }

    $stmtTxn = $pdo->prepare("SELECT * FROM inventory_transactions WHERE item_id = ? ORDER BY id DESC LIMIT 100");
    $stmtTxn->execute([$item_id]);
    $transactions = $stmtTxn->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'item' => $item,
        'transactions' => $transactions
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
