<?php
// controllers/download_pdf_invoice.php - Printable / Downloadable PDF Tax Invoice Generator with UPI QR Payment
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    die("Unauthorized access.");
}

$invoice_id = trim($_GET['invoice_id'] ?? $_GET['id'] ?? '');

if (empty($invoice_id)) {
    die("Invoice ID required.");
}

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ? OR id = ?");
$stmt->execute([$invoice_id, $invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    die("Invoice not found.");
}

$isB2B       = ($inv['client_type'] === 'B2B' || !empty($inv['company_name']));
$clientName  = $inv['company_name'] ?: $inv['client_name'];
$amount      = floatval($inv['amount']);
$taxRate     = floatval($inv['tax_rate'] ?: 18.00);
$taxAmount   = floatval($inv['tax_amount'] ?: round($amount * ($taxRate / 100), 2));
$subtotal    = max(0, $amount - $taxAmount);

// Dynamic UPI Payment QR Code string
$upiString = "upi://pay?pa=cynopharma@upi&pn=Cyno%20Pharmaceuticals&am={$amount}&cu=INR&tn=Invoice%20" . urlencode($inv['invoice_id']);
$qrCodeUrl = "https://quickchart.io/qr?text=" . urlencode($upiString) . "&size=140";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice #<?= htmlspecialchars($inv['invoice_id']) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            background: #f8fafc;
            margin: 0;
            padding: 30px;
        }
        .invoice-card {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 28px;
        }
        .company-logo {
            font-size: 24px;
            font-weight: 900;
            color: #4f46e5;
            letter-spacing: -0.5px;
        }
        .inv-title {
            font-size: 28px;
            font-weight: 900;
            color: #0f172a;
            text-align: right;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            font-size: 13px;
            line-height: 1.6;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table-items th {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 800;
            text-align: left;
            padding: 12px 14px;
            font-size: 13px;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-items td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .total-box {
            margin-left: auto;
            width: 280px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            font-size: 13.5px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .grand-total {
            font-size: 18px;
            font-weight: 900;
            color: #10b981;
            border-top: 2px dashed #cbd5e1;
            padding-top: 10px;
            margin-top: 10px;
        }
        .qr-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid #e2e8f0;
            padding-top: 24px;
            margin-top: 30px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #4f46e5;
            color: white;
            padding: 12px 28px;
            border-radius: 10px;
            border: none;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79,70,229,0.3);
        }
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .invoice-card { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">🖨️ Print / Save PDF Invoice</button>
</div>

<div class="invoice-card">
    <!-- Header -->
    <div class="header-flex">
        <div>
            <div class="company-logo">🏢 CYNO PHARMACEUTICALS LTD.</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                Corporate Office: Plot 42, Industrial Area Phase II, New Delhi<br>
                GSTIN: 07CYNO12345Z1 | Drug License #: DL-20B/21B-2026<br>
                Email: billing@cynopharma.com | Web: www.cynopharma.com
            </div>
        </div>
        <div>
            <div class="inv-title">TAX INVOICE</div>
            <div style="font-size: 13px; font-weight: 700; color: #64748b; text-align: right; margin-top: 4px;">
                #<?= htmlspecialchars($inv['invoice_id']) ?>
            </div>
            <div style="font-size: 12px; color: #64748b; text-align: right; margin-top: 2px;">
                Date: <?= date('d M Y', strtotime($inv['issue_date'])) ?>
            </div>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-box">
            <strong style="color: #4f46e5; font-size: 14px;">Billed To (Client Credentials):</strong><br>
            <strong style="font-size: 15px; color: #0f172a;"><?= htmlspecialchars($clientName) ?></strong><br>
            <?php if ($inv['gstin']): ?><strong>GSTIN:</strong> <?= htmlspecialchars($inv['gstin']) ?><br><?php endif; ?>
            <?php if ($inv['dl_number']): ?><strong>Drug License #:</strong> <?= htmlspecialchars($inv['dl_number']) ?><br><?php endif; ?>
            <strong>Category:</strong> <?= $isB2B ? 'B2B Wholesale' : 'B2C Retail' ?>
        </div>

        <div class="info-box">
            <strong style="color: #4f46e5; font-size: 14px;">Payment & Credit Terms:</strong><br>
            <strong>Status:</strong> <span style="color: <?= $inv['status']==='Paid'?'#10b981':'#ef4444' ?>; font-weight:800;"><?= strtoupper($inv['status']) ?></span><br>
            <strong>Due Date:</strong> <?= date('d M Y', strtotime($inv['due_date'])) ?><br>
            <strong>Terms:</strong> <?= htmlspecialchars($inv['payment_terms'] ?: 'Cash') ?><br>
            <strong>Payment Mode:</strong> Bank Transfer / UPI / Cash
        </div>
    </div>

    <!-- Items Table -->
    <table class="table-items">
        <thead>
            <tr>
                <th>Description / Product Line</th>
                <th>HSN Code</th>
                <th>Qty</th>
                <th>Rate (₹)</th>
                <th style="text-align: right;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Pharmaceutical Products / Wholesale Billing Order</strong></td>
                <td>3004</td>
                <td>1 Unit</td>
                <td>₹<?= number_format($subtotal, 2) ?></td>
                <td style="text-align: right; font-weight: 700;">₹<?= number_format($subtotal, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Totals Box -->
    <div class="total-box">
        <div class="total-row">
            <span>Taxable Amount:</span>
            <span>₹<?= number_format($subtotal, 2) ?></span>
        </div>
        <div class="total-row">
            <span>CGST (9%):</span>
            <span>₹<?= number_format($taxAmount / 2, 2) ?></span>
        </div>
        <div class="total-row">
            <span>SGST (9%):</span>
            <span>₹<?= number_format($taxAmount / 2, 2) ?></span>
        </div>
        <div class="total-row grand-total">
            <span>Grand Total:</span>
            <span>₹<?= number_format($amount, 2) ?></span>
        </div>
    </div>

    <!-- Payment QR Code & Bank Section -->
    <div class="qr-section">
        <div>
            <strong style="color: #0f172a; font-size: 13px;">Bank Transfer Payment Details:</strong>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.5;">
                Bank: HDFC Bank Ltd.<br>
                A/c Name: Cyno Pharmaceuticals Ltd.<br>
                A/c No: 50200012345678 | IFSC: HDFC0001234<br>
                UPI ID: cynopharma@upi
            </div>
        </div>
        <div style="text-align: center;">
            <img src="<?= $qrCodeUrl ?>" alt="Scan & Pay UPI QR" style="border: 2px solid #e2e8f0; border-radius: 8px; width: 120px; height: 120px;" /><br>
            <span style="font-size: 10.5px; font-weight: 700; color: #64748b;">Instant Scan & Pay (UPI)</span>
        </div>
    </div>
</div>

</body>
</html>
