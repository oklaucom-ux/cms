<?php
// client_ledger.php - B2B Corporate Wholesale Ledger & Client Credit Portal
require_once 'includes/db.php';
requirePermission($pdo, 'view_invoices');

// Auto-migrate B2B tables
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

    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN client_type VARCHAR(50) DEFAULT 'B2C'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN company_name VARCHAR(255)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN gstin VARCHAR(50)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN dl_number VARCHAR(100)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_terms VARCHAR(100) DEFAULT 'Cash'"); } catch(Exception $e){}

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
} catch (Exception $e) {}

$today = date('Y-m-d');

// Analytics Query
$totalB2bSales = $pdo->query("SELECT SUM(amount) FROM invoices WHERE client_type='B2B'")->fetchColumn() ?: 0;
$totalB2bPaid = $pdo->query("SELECT SUM(amount) FROM invoices WHERE client_type='B2B' AND status='Paid'")->fetchColumn() ?: 0;
$totalB2bUnpaid = $pdo->query("SELECT SUM(amount) FROM invoices WHERE client_type='B2B' AND status IN ('Unpaid','Partial')")->fetchColumn() ?: 0;
$overdueCount = $pdo->query("SELECT COUNT(*) FROM invoices WHERE client_type='B2B' AND status IN ('Unpaid','Partial') AND due_date < '{$today}'")->fetchColumn() ?: 0;

// Fetch B2B Invoices & Account Ledgers
$stmt = $pdo->query("SELECT i.*, 
    COALESCE((SELECT SUM(amount_paid) FROM b2b_payment_records p WHERE p.invoice_id = i.invoice_id), 
    CASE WHEN i.status='Paid' THEN i.amount ELSE 0 END) as total_paid_amount
    FROM invoices i 
    WHERE i.client_type = 'B2B' OR i.company_name IS NOT NULL AND i.company_name != ''
    ORDER BY i.id DESC");
$b2bInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
.ledger-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.ledger-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-heading);
    display: flex;
    align-items: center;
    gap: 12px;
}
.ledger-badge {
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 99px;
    background: #6366f1;
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 18px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
    flex-shrink: 0;
}
.stat-num { font-size: 22px; font-weight: 800; color: var(--text-heading); }
.stat-label { font-size: 12.5px; color: var(--text-muted); font-weight: 600; }

.ledger-box {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
}

.inv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.inv-table th {
    text-align: left;
    padding: 12px 14px;
    background: var(--bg-main);
    color: var(--text-heading);
    font-weight: 800;
    border-bottom: 2px solid var(--border-card);
}
.inv-table td {
    padding: 14px;
    border-bottom: 1px solid var(--border-card);
    color: var(--text-body);
}

.badge-paid { background: #10b98120; color: #10b981; border: 1px solid #10b98150; padding: 3px 10px; border-radius: 99px; font-weight: 800; font-size: 11px; }
.badge-unpaid { background: #ef444420; color: #ef4444; border: 1px solid #ef444450; padding: 3px 10px; border-radius: 99px; font-weight: 800; font-size: 11px; }
.badge-partial { background: #f59e0b20; color: #f59e0b; border: 1px solid #f59e0b50; padding: 3px 10px; border-radius: 99px; font-weight: 800; font-size: 11px; }

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary { background: #6366f1; color: white; }
.btn-primary:hover { background: #4f46e5; }
.btn-outline { background: transparent; border: 1px solid var(--border-card); color: var(--text-body); }
.btn-outline:hover { background: rgba(255,255,255,0.05); }
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Header -->
    <div class="ledger-header">
        <div>
            <div class="ledger-title">
                🏛️ B2B Wholesale Ledger & Corporate Credit Portal
                <span class="ledger-badge">Wholesale Accounts Receivable</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Track B2B client credit limits, outstanding balances, GSTIN tax invoices, drug license compliance, and payment collections.
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" onclick="exportLedgerCsv()" class="btn-sm btn-outline" style="padding:10px 16px;">
                <i class="fas fa-file-csv"></i> 📥 Export Statement CSV
            </button>
            <a href="inventory_pos.php" class="btn-sm btn-primary" style="padding:10px 18px; text-decoration:none;">
                <i class="fas fa-plus"></i> New B2B Sale
            </a>
        </div>
    </div>

    <!-- Analytics Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <div class="stat-num">₹<?= number_format($totalB2bSales, 2) ?></div>
                <div class="stat-label">Total B2B Billing Volume</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div>
                <div class="stat-num">₹<?= number_format($totalB2bUnpaid, 2) ?></div>
                <div class="stat-label">Outstanding B2B Credit Balance</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="stat-num">₹<?= number_format($totalB2bPaid, 2) ?></div>
                <div class="stat-label">Total Collections Received</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="stat-num"><?= $overdueCount ?></div>
                <div class="stat-label">Overdue B2B Credit Accounts</div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="ledger-box" style="margin-bottom:20px; padding:16px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
            <input type="text" id="ledgerSearchInput" onkeyup="filterLedgerTable()" placeholder="Search by Company Name, GSTIN, Invoice #, DL..." class="inv-input" style="width: 360px; padding:10px 14px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />

            <div style="display:flex; gap:12px;">
                <select id="ledgerStatusFilter" onchange="filterLedgerTable()" style="padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);">
                    <option value="">All Payment Statuses</option>
                    <option value="Paid">Fully Paid</option>
                    <option value="Unpaid">Unpaid Credit</option>
                    <option value="Partial">Partial Payment</option>
                    <option value="Overdue">Overdue Credit (>30 Days)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="ledger-box" style="padding:0; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="inv-table" id="b2bLedgerTable">
                <thead>
                    <tr>
                        <th>Invoice # & Date</th>
                        <th>Client / Company Name</th>
                        <th>Tax & DL Credentials</th>
                        <th>Credit Terms & Due</th>
                        <th>Invoice Amount</th>
                        <th>Paid & Balance</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (empty($b2bInvoices)): 
                    ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px; color:var(--text-muted);">
                            No B2B Corporate Wholesale invoices recorded yet. Use 🏥 <strong>Pharmacy POS</strong> in 🏢 <strong>B2B Corporate Mode</strong> to generate wholesale billing statements.
                        </td>
                    </tr>
                    <?php 
                    else:
                        foreach ($b2bInvoices as $inv):
                            $amt = floatval($inv['amount']);
                            $paid = floatval($inv['total_paid_amount'] ?? 0);
                            $balance = max(0, $amt - $paid);
                            $isOverdue = ($inv['status'] !== 'Paid') && (!empty($inv['due_date']) && $inv['due_date'] < $today);
                            $searchKey = strtolower(($inv['invoice_id'] ?? '') . ' ' . ($inv['company_name'] ?? '') . ' ' . ($inv['client_name'] ?? '') . ' ' . ($inv['gstin'] ?? '') . ' ' . ($inv['dl_number'] ?? ''));
                    ?>
                    <tr class="ledger-row-item" data-search="<?= htmlspecialchars($searchKey) ?>" data-status="<?= $isOverdue ? 'Overdue' : $inv['status'] ?>">
                        <td style="font-weight:700;">
                            <div style="color:var(--text-heading); font-family:monospace; font-size:13.5px;"><?= htmlspecialchars($inv['invoice_id']) ?></div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Issued: <?= date('d M Y', strtotime($inv['issue_date'])) ?></div>
                        </td>
                        <td>
                            <strong style="color:var(--text-heading); font-size:13.5px;"><?= htmlspecialchars($inv['company_name'] ?: $inv['client_name']) ?></strong>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($inv['client_name']) ?></div>
                        </td>
                        <td style="font-size:11.5px; color:var(--text-muted);">
                            <div><strong>GSTIN:</strong> <?= $inv['gstin'] ? htmlspecialchars($inv['gstin']) : '—' ?></div>
                            <div><strong>DL #:</strong> <?= $inv['dl_number'] ? htmlspecialchars($inv['dl_number']) : '—' ?></div>
                        </td>
                        <td>
                            <div style="font-size:12px; font-weight:700; color:var(--text-heading);"><?= htmlspecialchars($inv['payment_terms'] ?: 'Cash') ?></div>
                            <div style="font-size:11px; color:<?= $isOverdue ? '#ef4444' : 'var(--text-muted)' ?>; font-weight:<?= $isOverdue ? '800' : 'normal' ?>;">
                                <?= $isOverdue ? '🚨 Overdue: ' : 'Due: ' ?><?= date('d M Y', strtotime($inv['due_date'])) ?>
                            </div>
                        </td>
                        <td style="font-weight:800; font-size:14px; color:var(--text-heading);">
                            ₹<?= number_format($amt, 2) ?>
                            <div style="font-size:10px; color:var(--text-muted);">Tax (18%): ₹<?= number_format($inv['tax_amount'], 2) ?></div>
                        </td>
                        <td>
                            <div style="color:#10b981; font-weight:700; font-size:12px;">Paid: ₹<?= number_format($paid, 2) ?></div>
                            <div style="color:<?= $balance > 0 ? '#ef4444' : '#10b981' ?>; font-weight:800; font-size:12.5px;">
                                Bal: ₹<?= number_format($balance, 2) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($inv['status'] === 'Paid'): ?>
                                <span class="badge-paid">PAID</span>
                            <?php elseif ($isOverdue): ?>
                                <span class="badge-unpaid">OVERDUE</span>
                            <?php elseif ($inv['status'] === 'Partial'): ?>
                                <span class="badge-partial">PARTIAL</span>
                            <?php else: ?>
                                <span class="badge-unpaid">UNPAID</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:6px; justify-content:flex-end;">
                                <?php if ($balance > 0): ?>
                                    <button type="button" onclick="openPaymentModal('<?= htmlspecialchars($inv['invoice_id']) ?>', '<?= htmlspecialchars(addslashes($inv['company_name'] ?: $inv['client_name'])) ?>', <?= $balance ?>)" class="btn-sm btn-primary" title="Record Payment Collection">
                                        <i class="fas fa-hand-holding-usd"></i> Pay
                                    </button>
                                    <button type="button" onclick="sendPaymentReminder('<?= htmlspecialchars($inv['invoice_id']) ?>')" class="btn-sm btn-outline" title="Send Payment Reminder Email">
                                        <i class="fas fa-bell"></i> Remind
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endforeach; 
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RECORD PAYMENT MODAL -->
<div id="paymentModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:24px; padding:28px; width:90%; max-width:480px; box-shadow:0 20px 50px rgba(0,0,0,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="font-weight:800; font-size:18px; color:var(--text-heading);" id="payModalTitle">Record Payment Collection</div>
            <button type="button" onclick="document.getElementById('paymentModal').style.display='none'" style="background:none; border:none; color:var(--text-muted); font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <form id="paymentForm" onsubmit="submitPaymentRecord(event)">
            <input type="hidden" id="pay_invoice_id" name="invoice_id" />

            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Payment Amount Received (₹) *</label>
                    <input type="number" step="0.01" id="pay_amount" name="payment_amount" class="inv-input" required />
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Payment Method</label>
                    <select id="pay_mode" name="payment_mode" class="inv-select">
                        <option value="Bank Transfer (NEFT/RTGS)">Bank Transfer (NEFT / RTGS)</option>
                        <option value="Cheque / DD">Cheque / Demand Draft</option>
                        <option value="UPI / Online QR">UPI / Digital QR</option>
                        <option value="Cash">Cash Collection</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Reference # / UTR Transaction ID</label>
                    <input type="text" id="pay_ref" name="reference_no" class="inv-input" placeholder="e.g. UTR-9988776611" />
                </div>

                <button type="submit" class="btn-sm btn-primary" style="padding:12px; margin-top:10px; justify-content:center;">
                    <i class="fas fa-check"></i> Save Payment Entry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function filterLedgerTable() {
    const q = document.getElementById('ledgerSearchInput').value.toLowerCase();
    const st = document.getElementById('ledgerStatusFilter').value;

    document.querySelectorAll('.ledger-row-item').forEach(row => {
        const matchQ = !q || row.dataset.search.includes(q);
        const matchSt = !st || row.dataset.status === st;
        row.style.display = (matchQ && matchSt) ? 'table-row' : 'none';
    });
}

function openPaymentModal(invId, clientName, bal) {
    document.getElementById('pay_invoice_id').value = invId;
    document.getElementById('pay_amount').value = bal.toFixed(2);
    document.getElementById('payModalTitle').innerText = 'Record Payment - ' + clientName;
    document.getElementById('paymentModal').style.display = 'flex';
}

async function submitPaymentRecord(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('paymentForm'));

    if (typeof Swal !== 'undefined') {
        Swal.fire({ title: 'Recording Payment...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }

    try {
        const resp = await fetch('controllers/record_b2b_payment.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Payment Recorded!', res.message, 'success').then(() => window.location.reload());
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

async function sendPaymentReminder(invId) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ title: 'Sending Payment Reminder...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }

    const formData = new FormData();
    formData.append('invoice_id', invId);

    try {
        const resp = await fetch('controllers/send_payment_reminder.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Reminder Sent!', res.message, 'success');
            } else {
                alert(res.message);
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', res.error, 'error');
            } else {
                alert('Error: ' + res.error);
            }
        }
    } catch (err) {
        alert('Failed to communicate with server.');
    }
}

function exportLedgerCsv() {
    window.location.href = 'controllers/export_csv.php?table=invoices';
}
</script>

<?php require_once 'includes/footer.php'; ?>
