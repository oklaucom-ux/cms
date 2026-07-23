<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_invoices');

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Finance');

// Auto-migrate invoices table
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
        status VARCHAR(255) DEFAULT 'Unpaid'
    )");
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 18.00"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00"); } catch(Exception $e){}
} catch (Exception $e) {}

$totalPaid = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status='Paid'")->fetchColumn() ?: 0;
$totalUnpaid = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status='Unpaid'")->fetchColumn() ?: 0;
$totalInvoicesCount = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?: 0;
$paidInvoicesCount = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='Paid'")->fetchColumn() ?: 0;
$collectionRate = $totalInvoicesCount > 0 ? round(($paidInvoicesCount / $totalInvoicesCount) * 100) : 100;
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">💵 Financial Accounting & Invoicing</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Manage client invoices, track collected revenue, tax calculations, and outstanding balances.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="view-button" onclick="window.location.href='controllers/export_csv.php?table=invoices'" style="text-decoration:none; padding:10px 18px; border-radius:10px; background:var(--bg-card); border:1px solid var(--border-card); font-size:13px; font-weight:600; color:var(--text-body);">📥 Export Data</button>
            <?php if($isAdmin): ?>
            <button class="add-button" onclick="openInvoiceModal()">
                <i class="fas fa-plus"></i> Create Invoice
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Executive Financial Analytics -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Revenue Collected</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalPaid, 2) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Settled Payments</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Outstanding Balance</div>
            <div style="font-size:28px; font-weight:800; color:<?= $totalUnpaid > 0 ? '#ef4444' : 'var(--text-heading)' ?>;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalUnpaid, 2) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Pending Collections</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Total Invoices</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalInvoicesCount) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Issued Billing Documents</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Collection Rate</div>
            <div style="font-size:28px; font-weight:800; color:#6366f1;"><?= $collectionRate ?>%</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Settlement Performance</div>
        </div>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Client Name</th>
                    <th>Amount</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pdo->query("SELECT * FROM invoices ORDER BY id DESC") as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['invoice_id']) ?></strong></td>
                    <td><?= htmlspecialchars($row['client_name']) ?></td>
                    <td><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($row['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['issue_date']) ?></td>
                    <td><?= htmlspecialchars($row['due_date']) ?></td>
                    <td>
                        <span style="background: <?= $row['status']=='Paid' ? '#dcfce7' : ($row['status']=='Overdue'?'#fee2e2':'#fef3c7') ?>; 
                                     color: <?= $row['status']=='Paid' ? '#16a34a' : ($row['status']=='Overdue'?'#dc2626':'#d97706') ?>; 
                                     padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight:600;">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </td>
                    <td class="action-buttons">
                        <a href="controllers/invoice_pdf.php?id=<?= $row['id'] ?>" target="_blank" class="view-button" style="text-decoration:none;display:inline-block;">🖨️ PDF</a>
                        <?php if($isAdmin): ?>
                        <button class="edit-button" onclick='editInvoice(<?= json_encode($row) ?>)'>Edit</button>
                        <form method="POST" action="controllers/delete_invoice.php" style="display:inline;" onsubmit="return confirm('Delete this invoice?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="delete-button">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openInvoiceModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Invoice" : "Create Invoice";
    document.getElementById('modalForm').action = "controllers/save_invoice.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div class="form-group"><label>Invoice ID</label><input type="text" name="invoice_id" required value="${data ? data.invoice_id : 'INV-'+Math.floor(Math.random()*10000)}"></div>`;
    html += `<div class="form-group"><label>Client / Company Name</label><input type="text" name="client_name" required value="${data ? data.client_name : ''}"></div>`;
    html += `<div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="amount" required value="${data ? data.amount : ''}"></div>`;
    html += `<div class="form-group"><label>Issue Date</label><input type="date" name="issue_date" required value="${data ? data.issue_date : new Date().toISOString().split('T')[0]}"></div>`;
    
    let dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 14); // default 14 days
    html += `<div class="form-group"><label>Due Date</label><input type="date" name="due_date" required value="${data ? data.due_date : dueDate.toISOString().split('T')[0]}"></div>`;
    
    let stats = ['Unpaid', 'Paid', 'Overdue'];
    html += `<div class="form-group"><label>Status</label><select name="status">`;
    stats.forEach(s => { html += `<option value="${s}" ${data&&data.status==s?'selected':''}>${s}</option>`; });
    html += `</select></div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editInvoice(data) { openInvoiceModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>

