<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        po_number VARCHAR(255) NOT NULL UNIQUE,
        vendor_name TEXT NOT NULL,
        department TEXT NOT NULL,
        amount REAL NOT NULL,
        description TEXT,
        status VARCHAR(255) DEFAULT 'Pending Approval',
        created_by TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS budgets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        department VARCHAR(255) NOT NULL UNIQUE,
        allocated_amount REAL NOT NULL,
        year INTEGER NOT NULL
    )");
} catch (Exception $e) {}

$isFinance = hasPermission($pdo, 'view_invoices') || in_array($_SESSION['role'], ['Admin', 'Super Admin']);
$myId = $_SESSION['login_id'];

// Fetch Budgets vs Actuals
$currentYear = date('Y');
$budgets = $pdo->query("SELECT * FROM budgets WHERE year = $currentYear")->fetchAll(PDO::FETCH_ASSOC);
$dept_spend = [];
foreach ($budgets as $b) {
    $dept = $b['department'];
    // Spent = Approved POs + Approved Expenses
    $po_spend = $pdo->prepare("SELECT SUM(amount) FROM purchase_orders WHERE department = ? AND status IN ('Approved', 'Paid')");
    $po_spend->execute([$dept]);
    $po = $po_spend->fetchColumn() ?: 0;
    
    $exp_spend = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE category = ? AND status = 'Approved'"); // Assuming category acts as department or we can map it
    $exp_spend->execute([$dept]);
    $exp = $exp_spend->fetchColumn() ?: 0;
    
    $dept_spend[$dept] = [
        'allocated' =>$b['allocated_amount'],
        'spent' =>$po + $exp,
        'remaining' =>$b['allocated_amount'] - ($po + $exp)
    ];
}

// Fetch POs
if ($isFinance) {
    $pos = $pdo->query("SELECT * FROM purchase_orders ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE created_by = ? ORDER BY created_at DESC");
    $stmt->execute([$myId]);
    $pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🛒 Procurement & Budgets</h2>
        <button class="add-button" onclick="document.getElementById('poModal').style.display='flex'">+ Create Purchase Order</button>
    </div>

    <!-- Budgets Overview -->
    <?php if($isFinance && !empty($dept_spend)): ?>
    <div style="display:flex; gap:20px; overflow-x:auto; padding-bottom:20px; margin-bottom:20px;">
        <?php foreach($dept_spend as $dept =>$data): 
            $pct = $data['allocated'] > 0 ? min(100, round(($data['spent'] / $data['allocated']) * 100)) : 0;
            $color = $pct > 90 ? '#ef4444' : ($pct > 75 ? '#f59e0b' : '#10b981');
        ?>
        <div style="background:white; min-width:250px; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 10px 0; color:var(--text-heading);"><?= htmlspecialchars($dept) ?> Budget</h4>
            <div style="font-size:24px; font-weight:bold; color:var(--text-heading);"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($data['spent'], 2) ?> <span style="font-size:12px; color:#64748b; font-weight:normal;">/ <?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($data['allocated'], 2) ?></span></div>
            <div style="background:#e2e8f0; height:8px; border-radius:4px; margin-top:10px; overflow:hidden;">
                <div style="background:<?= $color ?>; height:100%; width:<?= $pct ?>%;"></div>
            </div>
            <div style="font-size:11px; color:#64748b; margin-top:5px; text-align:right; font-weight:bold;"><?= $pct ?>% Used</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- PO Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Vendor</th>
                    <th>Department</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <?php if($isFinance): ?><th>Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pos as $po): ?>
                <tr>
                    <td style="font-family:monospace; font-weight:bold;"><?= htmlspecialchars($po['po_number']) ?></td>
                    <td style="font-weight:bold; color:var(--text-heading);"><?= htmlspecialchars($po['vendor_name']) ?></td>
                    <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px;"><?= htmlspecialchars($po['department']) ?></span></td>
                    <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($po['description']) ?>"><?= htmlspecialchars($po['description']) ?></td>
                    <td style="font-weight:bold;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($po['amount'], 2) ?></td>
                    <td>
                        <span style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; 
                            background: <?= $po['status']=='Approved' ? '#d1fae5; color:#065f46;' : ($po['status']=='Rejected' ? '#fee2e2; color:#991b1b;' : '#fef3c7; color:#92400e;') ?>">
                            <?= htmlspecialchars($po['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px; color:#64748b;"><?= htmlspecialchars($po['created_by']) ?></td>
                    <?php if($isFinance): ?>
                    <td>
                        <?php if($po['status'] === 'Pending Approval'): ?>
                        <form method="POST" action="controllers/save_po.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= $po['id'] ?>">
                            <button type="submit" style="background:#10b981; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">Approve</button>
                        </form>
                        <form method="POST" action="controllers/save_po.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= $po['id'] ?>">
                            <button type="submit" style="background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">Reject</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:11px; color:#9ca3af;">No Action</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pos)) echo "<tr><td colspan='8' style='text-align:center;'>No Purchase Orders found.</td></tr>"; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create PO Modal -->
<div class="modal" id="poModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Raise Purchase Order</h2>
        <form method="POST" action="controllers/save_po.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Vendor Name</label>
            <input type="text" name="vendor_name" required placeholder="e.g. Dell Technologies" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Department</label>
            <input type="text" name="department" required placeholder="e.g. Engineering" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Amount (₹)</label>
            <input type="number" step="0.01" name="amount" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Description / Justification</label>
            <textarea name="description" required rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;"></textarea>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('poModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Submit PO</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>


