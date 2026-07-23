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
?>

<div class="content-section active">
    <div class="section-header">
        <h2>Finance & Invoicing</h2>
        <div>
            <button class="view-button" onclick="window.location.href='controllers/export_csv.php?table=invoices'" style="margin-right:8px;">📥 Export Data</button>
            <?php if($isAdmin): ?>
            <button class="add-button" onclick="openInvoiceModal()">Create Invoice</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="dashboard-grid" style="margin-bottom: 24px;">
        <div class="dashboard-card" style="">
            <h3><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalPaid, 2) ?></h3>
            <p>Total Revenue Collected</p>
        </div>
        <div class="dashboard-card" style="">
            <h3><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalUnpaid, 2) ?></h3>
            <p>Outstanding / Unpaid</p>
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

