<?php
// reports/gst_tax_report.php - Tax & GST Compliance Summary Report
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    die("Unauthorized Access");
}

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');
$exportFormat = $_GET['export'] ?? '';

// Fetch all sales & invoices in tax period
$stmtInv = $pdo->prepare("SELECT invoice_id, client_name, amount, tax_rate, tax_amount, issue_date, status FROM invoices WHERE issue_date >= ? AND issue_date <= ? ORDER BY issue_date DESC");
$stmtInv->execute([$startDate, $endDate]);
$invoices = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

// Export CSV
if ($exportFormat === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="GST_Tax_Report_' . $startDate . '_to_' . $endDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice ID', 'Client Name', 'Issue Date', 'Taxable Value (₹)', 'Tax Rate (%)', 'CGST (9%) (₹)', 'SGST (9%) (₹)', 'Total Tax (₹)', 'Invoice Total (₹)', 'Status']);

    foreach ($invoices as $i) {
        $taxable = floatval($i['amount']);
        $tax = floatval($i['tax_amount'] ?: ($taxable * 0.18));
        $cgst = $tax / 2;
        $sgst = $tax / 2;
        $total = $taxable + $tax;
        fputcsv($out, [
            $i['invoice_id'],
            $i['client_name'],
            $i['issue_date'],
            number_format($taxable, 2),
            $i['tax_rate'] ?? 18,
            number_format($cgst, 2),
            number_format($sgst, 2),
            number_format($tax, 2),
            number_format($total, 2),
            $i['status']
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Calculate KPI Summary
$totalTaxable = 0;
$totalTax = 0;
foreach ($invoices as $i) {
    $taxable = floatval($i['amount']);
    $tax = floatval($i['tax_amount'] ?: ($taxable * 0.18));
    $totalTaxable += $taxable;
    $totalTax += $tax;
}
$cgstTotal = $totalTax / 2;
$sgstTotal = $totalTax / 2;
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h2 style="font-size:24px; font-weight:800; color:var(--text-heading);">📊 GST & Tax Compliance Report</h2>
        <div style="display:flex; gap:12px;">
            <form method="GET" style="display:flex; gap:10px; align-items:center;">
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" style="padding:8px 12px; border-radius:8px; border:1px solid var(--border-card); background:var(--input-bg); color:var(--text-body);">
                <span style="color:var(--text-muted);">to</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" style="padding:8px 12px; border-radius:8px; border:1px solid var(--border-card); background:var(--input-bg); color:var(--text-body);">
                <button type="submit" class="premium-btn" style="padding:8px 16px;">Filter</button>
            </form>
            <a href="reports/gst_tax_report.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=csv" class="premium-btn" style="background:linear-gradient(135deg, #10b981, #059669); text-decoration:none;">📥 Export GSTR CSV</a>
        </div>
    </div>

    <!-- Summary KPIs -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:32px;">
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Total Taxable Sales</div>
            <div style="font-size:26px; font-weight:900; color:#6366f1; margin-top:6px;">₹<?= number_format($totalTaxable, 2) ?></div>
        </div>
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Total GST Collected</div>
            <div style="font-size:26px; font-weight:900; color:#10b981; margin-top:6px;">₹<?= number_format($totalTax, 2) ?></div>
        </div>
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">CGST (Central Tax)</div>
            <div style="font-size:26px; font-weight:900; color:#3b82f6; margin-top:6px;">₹<?= number_format($cgstTotal, 2) ?></div>
        </div>
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">SGST (State Tax)</div>
            <div style="font-size:26px; font-weight:900; color:#ec4899; margin-top:6px;">₹<?= number_format($sgstTotal, 2) ?></div>
        </div>
    </div>

    <!-- Detailed Tax Invoices Table -->
    <div class="glass-card" style="padding:24px; border-radius:16px;">
        <h3 style="font-size:18px; font-weight:800; margin-bottom:16px; color:var(--text-heading);">Detailed Tax Invoices (<?= count($invoices) ?>)</h3>
        <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
            <thead>
                <tr style="border-bottom:2px solid var(--border-card); text-align:left; color:var(--text-muted);">
                    <th style="padding:12px;">Invoice ID</th>
                    <th style="padding:12px;">Client</th>
                    <th style="padding:12px;">Date</th>
                    <th style="padding:12px;">Taxable (₹)</th>
                    <th style="padding:12px;">CGST (9%)</th>
                    <th style="padding:12px;">SGST (9%)</th>
                    <th style="padding:12px;">Total GST (₹)</th>
                    <th style="padding:12px;">Grand Total (₹)</th>
                    <th style="padding:12px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="9" style="text-align:center; padding:24px; color:var(--text-muted);">No invoices found for selected tax period.</td></tr>
                <?php endif; ?>
                <?php foreach($invoices as $inv):
                    $tx = floatval($inv['amount']);
                    $gst = floatval($inv['tax_amount'] ?: ($tx * 0.18));
                    $cgst = $gst / 2;
                    $sgst = $gst / 2;
                    $grand = $tx + $gst;
                ?>
                <tr style="border-bottom:1px solid var(--border-card);">
                    <td style="padding:12px; font-weight:800; color:var(--primary-color);"><?= htmlspecialchars($inv['invoice_id']) ?></td>
                    <td style="padding:12px; color:var(--text-heading); font-weight:600;"><?= htmlspecialchars($inv['client_name']) ?></td>
                    <td style="padding:12px; color:var(--text-muted);"><?= htmlspecialchars($inv['issue_date']) ?></td>
                    <td style="padding:12px; font-weight:700;">₹<?= number_format($tx, 2) ?></td>
                    <td style="padding:12px; color:#3b82f6;">₹<?= number_format($cgst, 2) ?></td>
                    <td style="padding:12px; color:#ec4899;">₹<?= number_format($sgst, 2) ?></td>
                    <td style="padding:12px; font-weight:800; color:#10b981;">₹<?= number_format($gst, 2) ?></td>
                    <td style="padding:12px; font-weight:900; color:var(--text-heading);">₹<?= number_format($grand, 2) ?></td>
                    <td style="padding:12px;"><span style="padding:4px 8px; border-radius:99px; font-size:11px; font-weight:800; background:<?= $inv['status']==='Paid'?'#dcfce7':'#fee2e2' ?>; color:<?= $inv['status']==='Paid'?'#15803d':'#b91c1c' ?>;"><?= htmlspecialchars($inv['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
