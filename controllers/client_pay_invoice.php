<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$invoice_id = intval($_POST['invoice_id'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? 'Online');
$notes = trim($_POST['notes'] ?? '');

if ($invoice_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice ID']);
    exit;
}

// Ensure receipt directory exists
$upload_dir = '../uploads/receipts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$receipt_path = '';
if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        $receipt_path = 'uploads/receipts/' . uniqid('rec_') . '.' . $ext;
        move_uploaded_file($_FILES['receipt_file']['tmp_name'], '../' . $receipt_path);
    }
}

// Fetch Invoice
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
    exit;
}

// Update invoice status
$update = $pdo->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?");
$update->execute([$invoice_id]);

// Audit trail
$pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, 'Client Invoice Payment', ?)")
    ->execute([$_SESSION['login_id'] ?? $inv['client_name'] ?? 'Client', "Paid Invoice #{$invoice_id} via {$payment_method}"]);

// Send Email Receipt Notification to Finance Admin
$financeEmails = $pdo->query("SELECT email FROM users WHERE role IN ('Admin', 'Super Admin') AND email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN);
foreach ($financeEmails as $fEmail) {
    $sub = "Payment Received: Invoice #{$invoice_id}";
    $body = "<h3 style='color:#10b981;'>Invoice Payment Settlement</h3>
             <p>Invoice <strong>#{$invoice_id}</strong> for <strong>" . htmlspecialchars($inv['client_name'] ?? 'Client') . "</strong> has been marked as <strong>Paid</strong>.</p>
             <ul>
                 <li><strong>Amount Paid:</strong> ₹" . number_format($inv['amount'] ?? $inv['total_amount'] ?? 0, 2) . "</li>
                 <li><strong>Method:</strong> " . htmlspecialchars($payment_method) . "</li>
                 " . ($notes ? "<li><strong>Notes:</strong> " . htmlspecialchars($notes) . "</li>" : "") . "
             </ul>";
    sendSystemEmail($fEmail, $sub, $body);
}

echo json_encode(['status' => 'success', 'message' => 'Payment processed and invoice marked as Paid!']);
exit;
