<?php
// bank_reconciliation.php - Automated Bank Reconciliation & Statement Matcher Portal
require_once 'includes/db.php';
requirePermission($pdo, 'view_expenses');
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto-migrate table
try {
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
} catch (Exception $e) {}

$stmtRecs = $pdo->query("SELECT * FROM bank_reconciliations ORDER BY id DESC LIMIT 50");
$reconciliations = $stmtRecs->fetchAll(PDO::FETCH_ASSOC);

$totalReconciledAmt = $pdo->query("SELECT SUM(amount) FROM bank_reconciliations")->fetchColumn() ?: 0;
$totalRecCount = count($reconciliations);
?>

<style>
.rec-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.rec-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 24px;
}
.table-rec {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.table-rec th {
    text-align: left;
    padding: 12px 14px;
    background: var(--bg-main);
    color: var(--text-heading);
    font-weight: 800;
    border-bottom: 2px solid var(--border-card);
}
.table-rec td {
    padding: 14px;
    border-bottom: 1px solid var(--border-card);
    color: var(--text-body);
}
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Header -->
    <div class="rec-header">
        <div>
            <div style="font-size:24px; font-weight:800; color:var(--text-heading); display:flex; align-items:center; gap:12px;">
                🏛️ Automated Bank Reconciliation & Expense Matching Engine
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                Reconcile corporate bank statement deposits and withdrawals against CMS B2B Invoices, POS Cash collections, and Expense Vouchers.
            </p>
        </div>
    </div>

    <!-- Quick Statement Entry Form -->
    <div class="rec-card">
        <div style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:16px;">
            ⚡ Quick Bank Entry Reconciliation Matcher
        </div>

        <form id="recForm" onsubmit="submitReconciliation(event)">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:16px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Statement Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="inv-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Bank Narration / Description</label>
                    <input type="text" name="description" placeholder="e.g. NEFT-APEX HEALTHCARE-INV-B2B-1002" required class="inv-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="inv-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Transaction Type</label>
                    <select name="type" class="inv-select" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);">
                        <option value="Credit">Deposit / Customer Payment (Credit)</option>
                        <option value="Debit">Withdrawal / Vendor Expense (Debit)</option>
                    </select>
                </div>
            </div>

            <button type="submit" style="padding:12px 24px; background:#6366f1; color:white; border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:14px;">
                🔄 Match & Reconcile Bank Entry
            </button>
        </form>
    </div>

    <!-- Reconciled Ledger Table -->
    <div class="rec-card" style="padding:0; overflow:hidden;">
        <div style="padding:18px 24px; font-weight:800; font-size:16px; color:var(--text-heading); border-bottom:1px solid var(--border-card);">
            📋 Recent Reconciled Bank Ledger Entries (₹<?= number_format($totalReconciledAmt, 2) ?> Total)
        </div>
        <div style="overflow-x:auto;">
            <table class="table-rec">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Narration / Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Matched CMS Record</th>
                        <th>Status</th>
                        <th>Reconciled By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reconciliations)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No bank entries reconciled yet. Use the matcher above to process bank statements.</td></tr>
                    <?php else: foreach ($reconciliations as $r): ?>
                    <tr>
                        <td style="font-weight:700;"><?= date('d M Y', strtotime($r['txn_date'])) ?></td>
                        <td><?= htmlspecialchars($r['description']) ?></td>
                        <td>
                            <span style="padding:2px 8px; border-radius:99px; font-size:11px; font-weight:800; background:<?= $r['txn_type']==='Credit'?'#10b98120':'#ef444420' ?>; color:<?= $r['txn_type']==='Credit'?'#10b981':'#ef4444' ?>;">
                                <?= strtoupper($r['txn_type']) ?>
                            </span>
                        </td>
                        <td style="font-weight:800; color:var(--text-heading);">₹<?= number_format($r['amount'], 2) ?></td>
                        <td>
                            <strong style="color:#6366f1;"><?= htmlspecialchars($r['matched_type']) ?></strong>
                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($r['matched_id']) ?></div>
                        </td>
                        <td><span style="background:#10b98120; color:#10b981; padding:3px 10px; border-radius:99px; font-weight:800; font-size:11px;">RECONCILED</span></td>
                        <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($r['reconciled_by']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function submitReconciliation(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('recForm'));

    if (typeof Swal !== 'undefined') {
        Swal.fire({ title: 'Reconciling Bank Entry...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }

    try {
        const resp = await fetch('controllers/process_reconciliation.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Reconciliation Complete!', res.message, 'success').then(() => window.location.reload());
            } else {
                alert(res.message);
                window.location.reload();
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', res.error, 'error');
            } else {
                alert('Error: ' + res.error);
            }
        }
    } catch (err) {
        alert('Server connection error.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
