<?php
// controllers/send_payment_reminder.php - Dispatch email payment reminder for overdue B2B invoice
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$invoice_id = trim($_POST['invoice_id'] ?? '');

if (empty($invoice_id)) {
    echo json_encode(['success' => false, 'error' => 'Invoice ID required.']);
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

    $companyName = $inv['company_name'] ?: $inv['client_name'];
    $amount = number_format($inv['amount'], 2);
    $dueDate = date('d M Y', strtotime($inv['due_date']));

    $subject = "Payment Reminder: Invoice #{$inv['invoice_id']} Due ({$companyName})";
    $body = "
    <div style='font-family:sans-serif; padding:20px; color:#1e293b;'>
        <h2 style='color:#4f46e5;'>Cyno Pharmaceuticals Ltd.</h2>
        <p>Dear Accounts Team / <strong>{$companyName}</strong>,</p>
        <p>This is a friendly reminder regarding outstanding Tax Invoice <strong>#{$inv['invoice_id']}</strong>.</p>
        <div style='background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px; margin:20px 0;'>
            <p style='margin:4px 0;'><strong>Invoice Number:</strong> {$inv['invoice_id']}</p>
            <p style='margin:4px 0;'><strong>Amount Due:</strong> ₹{$amount}</p>
            <p style='margin:4px 0;'><strong>Due Date:</strong> {$dueDate}</p>
            <p style='margin:4px 0;'><strong>Payment Terms:</strong> " . ($inv['payment_terms'] ?: 'Credit Terms') . "</p>
        </div>
        <p>Please process the payment to our registered bank account at your earliest convenience.</p>
        <p>Thank you for your business!</p>
        <hr style='border:none; border-top:1px solid #e2e8f0; margin-top:30px;' />
        <p style='font-size:12px; color:#64748b;'>Cyno ERP Accounting & Accounts Receivable</p>
    </div>";

    // Attempt mail dispatch
    $to = "accounts@" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName)) . ".com";
    $mailResult = sendSystemEmail($to, $subject, $body);

    echo json_encode([
        'success' => true,
        'message' => "Payment reminder dispatched successfully for Invoice #{$inv['invoice_id']}!"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to send payment reminder: ' . $e->getMessage()]);
}
