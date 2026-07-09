<?php
session_start();
require_once '../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) die("Invalid invoice ID");

$invoice = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
$invoice->execute([$id]);
$invoice = $invoice->fetch(PDO::FETCH_ASSOC);
if (!$invoice) die("Invoice not found");

$company = $GLOBAL_SETTINGS['company_name'] ?? 'Company Management System';
$email   = $GLOBAL_SETTINGS['company_email'] ?? 'info@company.com';
$address = $GLOBAL_SETTINGS['company_address'] ?? '';
$statusColors = ['Paid'=>['#dcfce7','#16a34a'],'Unpaid'=>['#fef3c7','#d97706'],'Overdue'=>['#fee2e2','#dc2626']];
$sc = $statusColors[$invoice['status']] ?? $statusColors['Unpaid'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice <?= htmlspecialchars($invoice['invoice_id']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Arial,sans-serif; }
body { background:#f1f5f9; padding:40px; }
.invoice-wrap { max-width:800px; margin:0 auto; background:white; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.08); overflow:hidden; }
.inv-header { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; padding:40px 48px; display:flex; justify-content:space-between; align-items:flex-start; }
.inv-header h1 { font-size:32px; font-weight:800; letter-spacing:-1px; }
.inv-header .inv-id { font-size:16px; opacity:.8; margin-top:4px; }
.inv-meta { text-align:right; font-size:14px; opacity:.9; line-height:1.8; }
.inv-body { padding:48px; }
.inv-parties { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-bottom:40px; }
.inv-party h3 { font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:#9ca3af; font-weight:700; margin-bottom:8px; }
.inv-party p { font-size:15px; color:#111827; line-height:1.8; }
.inv-amount-box { background:linear-gradient(135deg,#f8faff,#f0f4ff); border:2px solid #e0e7ff; border-radius:12px; padding:32px 40px; margin-bottom:40px; display:flex; justify-content:space-between; align-items:center; }
.inv-amount-label { font-size:14px; color:#6b7280; font-weight:600; }
.inv-amount-value { font-size:48px; font-weight:800; color:#4f46e5; letter-spacing:-2px; }
.inv-amount-status { display:inline-block; background:<?= $sc[0] ?>; color:<?= $sc[1] ?>; padding:8px 20px; border-radius:20px; font-size:14px; font-weight:700; margin-top:8px; }
.inv-dates { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:40px; }
.inv-date-card { background:#f9fafb; border-radius:10px; padding:20px 24px; }
.inv-date-card label { font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:#9ca3af; font-weight:700; display:block; margin-bottom:4px; }
.inv-date-card span { font-size:18px; font-weight:700; color:#111827; }
.inv-footer { border-top:1px solid #f3f4f6; padding:24px 48px; text-align:center; color:#9ca3af; font-size:12px; }
@media print {
    body { background:white; padding:0; }
    .invoice-wrap { box-shadow:none; border-radius:0; }
    .no-print { display:none; }
}
</style>
</head>
<body>
<div class="no-print" style="text-align:center;margin-bottom:20px;display:flex;gap:12px;justify-content:center;">
    <button onclick="window.print()" style="background:#4f46e5;color:white;border:none;padding:12px 28px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;">🖨️ Print / Save as PDF</button>
    <button onclick="window.history.back()" style="background:#f3f4f6;color:#374151;border:none;padding:12px 28px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;">← Back</button>
</div>
<div class="invoice-wrap">
    <div class="inv-header">
        <div>
            <h1><?= htmlspecialchars($company) ?></h1>
            <div class="inv-id">Invoice #<?= htmlspecialchars($invoice['invoice_id']) ?></div>
        </div>
        <div class="inv-meta">
            <div><?= htmlspecialchars($email) ?></div>
            <?php if($address): ?><div><?= htmlspecialchars($address) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="inv-body">
        <div class="inv-parties">
            <div class="inv-party">
                <h3>Bill To</h3>
                <p><strong><?= htmlspecialchars($invoice['client_name']) ?></strong></p>
            </div>
            <div class="inv-party">
                <h3>From</h3>
                <p><strong><?= htmlspecialchars($company) ?></strong></p>
            </div>
        </div>
        <div class="inv-amount-box">
            <div>
                <div class="inv-amount-label">Total Amount</div>
                <div class="inv-amount-value">$<?= number_format($invoice['amount'], 2) ?></div>
                <div class="inv-amount-status"><?= htmlspecialchars($invoice['status']) ?></div>
            </div>
        </div>
        <div class="inv-dates">
            <div class="inv-date-card">
                <label>Issue Date</label>
                <span><?= date('F j, Y', strtotime($invoice['issue_date'])) ?></span>
            </div>
            <div class="inv-date-card">
                <label>Due Date</label>
                <span style="color:<?= $invoice['status']=='Overdue'?'#dc2626':'#111827' ?>"><?= date('F j, Y', strtotime($invoice['due_date'])) ?></span>
            </div>
        </div>
    </div>
    <div class="inv-footer">
        Generated by <?= htmlspecialchars($company) ?> Enterprise Management System · <?= date('F j, Y') ?> · This is an official invoice document.
    </div>
</div>
</body>
</html>
