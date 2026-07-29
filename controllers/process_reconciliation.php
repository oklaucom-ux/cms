<?php
// controllers/process_reconciliation.php - Bank Reconciliation & Statement Matcher Engine
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$txn_date = trim($_POST['date'] ?? date('Y-m-d'));
$description = trim($_POST['description'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$type = trim($_POST['type'] ?? 'Credit');

if ($amount <= 0 || empty($description)) {
    echo json_encode(['success' => false, 'error' => 'Valid transaction Description and positive Amount required.']);
    exit();
}

try {
    // Auto-migrate bank_reconciliations table
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS bank_reconciliations (
        id {$pkDef},
        txn_date DATE NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        txn_type VARCHAR(50) DEFAULT 'Credit',
        matched_type VARCHAR(50),
        matched_id VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Reconciled',
        reconciled_by VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $matchedId = null;
    $matchedType = null;

    if ($type === 'Credit') {
        // Search invoices for matching amount
        $stmtInv = $pdo->prepare("SELECT invoice_id FROM invoices WHERE ABS(amount - ?) < 0.5 LIMIT 1");
        $stmtInv->execute([$amount]);
        $matchedId = $stmtInv->fetchColumn();
        if ($matchedId) {
            $matchedType = 'Invoice';
            $pdo->prepare("UPDATE invoices SET status = 'Paid' WHERE invoice_id = ?")->execute([$matchedId]);
        }
    } else {
        // Search expenses for matching amount
        $stmtExp = $pdo->prepare("SELECT id FROM expenses WHERE ABS(amount - ?) < 0.5 LIMIT 1");
        $stmtExp->execute([$amount]);
        $matchedId = $stmtExp->fetchColumn();
        if ($matchedId) {
            $matchedType = 'Expense';
            $pdo->prepare("UPDATE expenses SET status = 'Approved' WHERE id = ?")->execute([$matchedId]);
        }
    }

    $stmtIns = $pdo->prepare("INSERT INTO bank_reconciliations (txn_date, description, amount, txn_type, matched_type, matched_id, status, reconciled_by) VALUES (?, ?, ?, ?, ?, ?, 'Reconciled', ?)");
    $stmtIns->execute([$txn_date, $description, $amount, $type, $matchedType ?: 'Manual Ledger', $matchedId ?: 'GEN-REC-' . rand(1000,9999), $_SESSION['login_id']]);

    echo json_encode([
        'success' => true,
        'matched' => (bool)$matchedId,
        'matched_type' => $matchedType ?: 'General Ledger',
        'matched_id' => $matchedId ?: 'Direct Deposit',
        'message' => $matchedId ? "Bank Entry reconciled & matched with {$matchedType} #{$matchedId}!" : "Bank Entry reconciled and posted to General Ledger."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Reconciliation Error: ' . $e->getMessage()]);
}
