<?php
// controllers/generate_recurring_invoices.php - Automated Recurring Invoice Engine
require_once __DIR__ . '/../includes/db.php';

try {
    // Ensure is_recurring column exists on invoices
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    try {
        if ($isMysql) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN is_recurring INT DEFAULT 0");
            $pdo->exec("ALTER TABLE invoices ADD COLUMN recurring_frequency VARCHAR(50) DEFAULT 'Monthly'");
        } else {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN is_recurring INTEGER DEFAULT 0");
            $pdo->exec("ALTER TABLE invoices ADD COLUMN recurring_frequency VARCHAR(50) DEFAULT 'Monthly'");
        }
    } catch (Exception $e) {}

    $today = date('Y-m-d');
    $dueNextMonth = date('Y-m-d', strtotime('+30 days'));

    // Select invoices set as recurring
    $stmtRec = $pdo->prepare("SELECT * FROM invoices WHERE is_recurring = 1 AND due_date <= ?");
    $stmtRec->execute([$today]);
    $recurringInvoices = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

    $generatedCount = 0;

    foreach ($recurringInvoices as $inv) {
        $newInvId = 'INV-REC-' . date('Ym') . '-' . rand(1000, 9999);
        $stmtIns = $pdo->prepare("INSERT INTO invoices (invoice_id, client_name, amount, tax_rate, tax_amount, issue_date, due_date, status, is_recurring, recurring_frequency) VALUES (?, ?, ?, ?, ?, ?, ?, 'Unpaid', 1, ?)");
        $stmtIns->execute([
            $newInvId,
            $inv['client_name'],
            $inv['amount'],
            $inv['tax_rate'] ?? 18.00,
            $inv['tax_amount'] ?? ($inv['amount'] * 0.18),
            $today,
            $dueNextMonth,
            $inv['recurring_frequency'] ?? 'Monthly'
        ]);

        // Advance next due date of original invoice by 30 days so it doesn't duplicate immediately
        $pdo->prepare("UPDATE invoices SET due_date = ? WHERE id = ?")->execute([$dueNextMonth, $inv['id']]);
        $generatedCount++;
    }

    if (php_sapi_name() === 'cli') {
        echo "    -> Generated " . $generatedCount . " recurring invoices.\n\n";
    }
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "    -> Recurring Invoices Error: " . $e->getMessage() . "\n\n";
    }
}
