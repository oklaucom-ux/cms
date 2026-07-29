<?php
// controllers/pos_checkout.php - B2C Retail & B2B Wholesale Corporate POS Checkout Controller
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

// Auto-migrate invoices table for B2B fields
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id {$pkDef},
        invoice_id VARCHAR(255) NOT NULL,
        client_name TEXT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        tax_rate DECIMAL(5,2) DEFAULT 18.00,
        tax_amount DECIMAL(10,2) DEFAULT 0.00,
        issue_date DATE NOT NULL,
        due_date DATE NOT NULL,
        status VARCHAR(255) DEFAULT 'Paid',
        client_type VARCHAR(50) DEFAULT 'B2C',
        company_name VARCHAR(255),
        gstin VARCHAR(50),
        dl_number VARCHAR(100),
        payment_terms VARCHAR(100) DEFAULT 'Cash'
    )");

    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 18.00"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN client_type VARCHAR(50) DEFAULT 'B2C'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN company_name VARCHAR(255)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN gstin VARCHAR(50)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN dl_number VARCHAR(100)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_terms VARCHAR(100) DEFAULT 'Cash'"); } catch(Exception $e){}
} catch (Exception $e) {}

$client_type   = trim($_POST['client_type'] ?? 'B2C');
$patient_name  = trim($_POST['patient_name'] ?? 'Walk-in Customer');
$company_name  = trim($_POST['company_name'] ?? '');
$gstin         = trim($_POST['gstin'] ?? '');
$dl_number     = trim($_POST['dl_number'] ?? '');
$payment_terms = trim($_POST['payment_terms'] ?? 'Cash');
$doctor_name   = trim($_POST['doctor_name'] ?? '');
$items_json    = $_POST['items'] ?? '[]';
$discount_val  = floatval($_POST['discount'] ?? 0);

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

        if (($item['prescription_required'] ?? 0) == 1) {
            $hasRxRequirement = true;
        }

        $price = floatval($item['unit_price']);
        if (($item['discount_percent'] ?? 0) > 0) {
            $price = $price * (1 - ($item['discount_percent'] / 100));
        }

        $subtotal += ($price * $qty);
    }

    if ($client_type === 'B2C' && $hasRxRequirement && empty($doctor_name)) {
        throw new Exception("Prescription Required (Rx): Please enter the prescribing Doctor Name before dispensing prescription medicines.");
    }

    if ($client_type === 'B2B' && empty($company_name)) {
        throw new Exception("B2B Sales Requirement: Please specify Business / Company Name for B2B wholesale invoice.");
    }

    $taxableTotal = max(0, $subtotal - $discount_val);
    $taxRate = 18.00; // 18% GST (9% CGST + 9% SGST / IGST)
    $taxAmount = round($taxableTotal * ($taxRate / 100), 2);
    $grandTotal = $taxableTotal + $taxAmount;

    $invoiceNum = 'INV-' . ($client_type === 'B2B' ? 'B2B-' : 'POS-') . date('Ymd-His');
    $todayDate  = date('Y-m-d');

    // Due date calculation for B2B Credit Terms
    $dueDate = $todayDate;
    $status  = 'Paid';
    if ($client_type === 'B2B') {
        if (strpos($payment_terms, '15') !== false) {
            $dueDate = date('Y-m-d', strtotime('+15 days'));
            $status  = 'Unpaid';
        } elseif (strpos($payment_terms, '30') !== false) {
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            $status  = 'Unpaid';
        } elseif (strpos($payment_terms, '60') !== false) {
            $dueDate = date('Y-m-d', strtotime('+60 days'));
            $status  = 'Unpaid';
        }
    }

    $billingName = ($client_type === 'B2B') ? $company_name : $patient_name;
    if ($client_type === 'B2C' && $doctor_name) {
        $billingName .= " (Dr. {$doctor_name})";
    }

    // Create Invoice record
    $stmtInv = $pdo->prepare("INSERT INTO invoices 
        (invoice_id, client_name, amount, tax_rate, tax_amount, issue_date, due_date, status, client_type, company_name, gstin, dl_number, payment_terms) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtInv->execute([
        $invoiceNum, $billingName, $grandTotal, $taxRate, $taxAmount, $todayDate, $dueDate, $status,
        $client_type, $company_name, $gstin, $dl_number, $payment_terms
    ]);

    // Deduct stock & log inventory transactions
    foreach ($cartItems as $c) {
        $itemId = (int)($c['id'] ?? 0);
        $qty    = (int)($c['qty'] ?? 1);

        $pdo->prepare("UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $itemId]);

        $reason = ($client_type === 'B2B') ? "B2B Wholesale Sale ({$invoiceNum}) to {$company_name}" : "B2C Dispense Sale ({$invoiceNum}) to {$patient_name}";
        $pdo->prepare("INSERT INTO inventory_transactions (item_id, type, quantity_change, reason, created_by, created_at) VALUES (?, 'Stock Out', ?, ?, ?, CURRENT_TIMESTAMP)")
            ->execute([$itemId, -$qty, $reason, $_SESSION['login_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice_number' => $invoiceNum,
        'grand_total' => $grandTotal,
        'taxable_amount' => $taxableTotal,
        'tax_amount' => $taxAmount,
        'client_type' => $client_type,
        'company_name' => $company_name,
        'gstin' => $gstin,
        'dl_number' => $dl_number,
        'payment_terms' => $payment_terms,
        'patient_name' => $patient_name,
        'message' => ($client_type === 'B2B' ? "B2B Wholesale Tax Invoice" : "B2C Retail POS Sale") . " ({$invoiceNum}) generated successfully!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
