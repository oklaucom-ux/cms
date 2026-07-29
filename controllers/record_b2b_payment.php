<?php
// controllers/record_b2b_payment.php - Process B2B Client Invoice Payment Recording
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$invoice_id   = trim($_POST['invoice_id'] ?? '');
$payment_amt  = floatval($_POST['payment_amount'] ?? 0);
$payment_mode = trim($_POST['payment_mode'] ?? 'Bank Transfer');
$reference_no = trim($_POST['reference_no'] ?? '');

if (empty($invoice_id) || $payment_amt <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid Invoice ID and Payment Amount are required.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ? OR id = ?");
    $stmt->execute([$invoice_id, $invoice_id]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        echo json_encode(['success' => false, 'error' => 'Invoice record not found.']);
        exit();
    }

    $currentAmount = floatval($inv['amount']);
    
    // Auto-migrate payment_history table
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS b2b_payment_records (
        id {$pkDef},
        invoice_id VARCHAR(255) NOT NULL,
        client_name VARCHAR(255),
        amount_paid DECIMAL(10,2) NOT NULL,
        payment_mode VARCHAR(100),
        reference_no VARCHAR(100),
        recorded_by VARCHAR(255),
        payment_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert payment record
    $stmtRec = $pdo->prepare("INSERT INTO b2b_payment_records (invoice_id, client_name, amount_paid, payment_mode, reference_no, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtRec->execute([$inv['invoice_id'], $inv['client_name'], $payment_amt, $payment_mode, $reference_no, $_SESSION['login_id']]);

    // Check total paid for this invoice
    $stmtSum = $pdo->prepare("SELECT SUM(amount_paid) FROM b2b_payment_records WHERE invoice_id = ?");
    $stmtSum->execute([$inv['invoice_id']]);
    $totalPaidSoFar = floatval($stmtSum->fetchColumn() ?: 0);

    // Update status to Paid if fully paid
    $newStatus = ($totalPaidSoFar >= $currentAmount) ? 'Paid' : 'Partial';
    $stmtUp = $pdo->prepare("UPDATE invoices SET status = ? WHERE invoice_id = ?");
    $stmtUp->execute([$newStatus, $inv['invoice_id']]);

    echo json_encode([
        'success' => true,
        'message' => "Payment of ₹" . number_format($payment_amt, 2) . " recorded successfully for Invoice #{$inv['invoice_id']}.",
        'new_status' => $newStatus
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
