<?php
// controllers/pos_checkout.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$patient_name = trim($_POST['patient_name'] ?? 'Walk-in Customer');
$doctor_name  = trim($_POST['doctor_name'] ?? '');
$items_json   = $_POST['items'] ?? '[]';
$discount_val = floatval($_POST['discount'] ?? 0);
$payment_mode = trim($_POST['payment_mode'] ?? 'Cash');

$cartItems = json_decode($items_json, true) ?: [];

if (empty($cartItems)) {
    echo json_encode(['success' => false, 'error' => 'POS cart is empty. Please add items before checkout.']);
    exit();
}

try {
    $pdo->beginTransaction();

    $subtotal = 0;
    $hasRxRequirement = false;

    // Validate cart items and stock
    foreach ($cartItems as $c) {
        $itemId = (int)($c['id'] ?? 0);
        $qty    = (int)($c['qty'] ?? 1);

        $stmtItem = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Inventory item ID {$itemId} not found.");
        }

        if ($qty > (int)$item['quantity']) {
            throw new Exception("Insufficient stock for '{$item['name']}'. Available: {$item['quantity']}, Requested: {$qty}.");
        }

        if ($item['prescription_required'] == 1) {
            $hasRxRequirement = true;
        }

        $price = floatval($item['unit_price']);
        if ($item['discount_percent'] > 0) {
            $price = $price * (1 - ($item['discount_percent'] / 100));
        }

        $subtotal += ($price * $qty);
    }

    if ($hasRxRequirement && empty($doctor_name)) {
        throw new Exception("Prescription Required (Rx): Please enter the prescribing Doctor Name before dispensing prescription medicines.");
    }

    $grandTotal = max(0, $subtotal - $discount_val);
    $invoiceNum = 'INV-POS-' . date('Ymd-His');
    $todayDate  = date('Y-m-d');

    // Create Invoice in invoices table
    $stmtInv = $pdo->prepare("INSERT INTO invoices (invoice_id, client_name, amount, tax_rate, tax_amount, issue_date, due_date, status) VALUES (?, ?, ?, 0, 0, ?, ?, 'Paid')");
    $stmtInv->execute([$invoiceNum, $patient_name . ($doctor_name ? " (Dr. {$doctor_name})" : ""), $grandTotal, $todayDate, $todayDate]);
    $invoiceId = $pdo->lastInsertId();

    // Deduct inventory stock & log transactions
    foreach ($cartItems as $c) {
        $itemId = (int)($c['id'] ?? 0);
        $qty    = (int)($c['qty'] ?? 1);

        $pdo->prepare("UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $itemId]);

        $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, 'Stock Out', ?, ?, ?, CURRENT_TIMESTAMP)")
            ->execute([$itemId, -$qty, "POS Dispense Sale ({$invoiceNum}) to {$patient_name}", $_SESSION['login_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice_number' => $invoiceNum,
        'grand_total' => $grandTotal,
        'patient_name' => $patient_name,
        'message' => "POS Checkout Complete! Tax Invoice ({$invoiceNum}) generated successfully."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
