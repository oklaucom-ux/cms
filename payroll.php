<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_payroll');
require_once 'includes/flash.php';
require_once 'includes/notifications.php';

// Self-service bypass
$isAdmin = hasPermission($pdo, 'manage_payroll');
// Auto-migrate payroll tables
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_profiles (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        base_salary REAL DEFAULT 0,
        tax_rate REAL DEFAULT 0.2,
        bank_account TEXT,
        currency VARCHAR(255) DEFAULT 'USD',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_runs (
        id {$pkDef},
        user_id TEXT NOT NULL,
        period TEXT NOT NULL,
        base_salary REAL,
        deductions REAL DEFAULT 0,
        bonuses REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        net_pay REAL,
        status VARCHAR(255) DEFAULT 'Draft',
        processed_by TEXT,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$period  = $_GET['period'] ?? date('Y-m');
$users   = $pdo->query("SELECT login_id, name, department FROM users WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Ensure payroll profiles exist for all active users (dual DB safe)
foreach ($users as $u) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM payroll_profiles WHERE user_id = ?");
    $check->execute([$u['login_id']]);
    if ($check->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO payroll_profiles (user_id, base_salary) VALUES (?, 0)")->execute([$u['login_id']]);
    }
}

// Fetch this month's payroll runs
if ($isAdmin) {
    $runs = $pdo->prepare("SELECT pr.*, pp.base_salary as profile_salary, pp.tax_rate, pp.bank_account, u.name, u.department FROM payroll_runs pr JOIN payroll_profiles pp ON pr.user_id=pp.user_id JOIN users u ON pr.user_id=u.login_id WHERE pr.period=? ORDER BY u.name");
    $runs->execute([$period]);
} else {
    $runs = $pdo->prepare("SELECT pr.*, pp.base_salary as profile_salary, pp.tax_rate, pp.bank_account, u.name, u.department FROM payroll_runs pr JOIN payroll_profiles pp ON pr.user_id=pp.user_id JOIN users u ON pr.user_id=u.login_id WHERE pr.period=? AND pr.user_id=?");
    $runs->execute([$period, $_SESSION['login_id']]);
}
$runs = $runs->fetchAll(PDO::FETCH_ASSOC);

// Summary
$totalGross = array_sum(array_column($runs, 'base_salary'));
$totalNet   = array_sum(array_column($runs, 'net_pay'));
$totalTax   = array_sum(array_column($runs, 'tax_amount'));
$totalBonus = array_sum(array_column($runs, 'bonuses'));

// Profiles for config
$profiles = $pdo->query("SELECT pp.*, u.name, u.department FROM payroll_profiles pp JOIN users u ON pp.user_id=u.login_id ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>💰 Payroll Engine</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <input type="month" value="<?= $period ?>" onchange="window.location='payroll.php?period='+this.value" style="padding:9px 14px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text-body);font-size:14px;">
            <a href="controllers/export_csv.php?table=payroll_runs" class="view-button" style="text-decoration:none;font-size:13px;font-weight:600;">📥 Export CSV</a>
            <?php if($isAdmin && !empty($runs)): ?>
            <button class="add-button" onclick="runPayroll('<?= $period ?>')" style="background:#10b981;">⚡ Process All</button>
            <?php endif; ?>
            <?php if($isAdmin && empty($runs)): ?>
            <button class="add-button" onclick="generatePayroll('<?= $period ?>')">📋 Generate <?= date('M Y', strtotime($period.'-01')) ?> Payroll</button>
            <?php endif; ?>
        </div>
    </div>

    <?php renderFlash(); ?>

    <?php if($isAdmin): ?>
    <!-- KPI Strip -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:28px;">
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Total Gross</div>
            <div style="font-size:28px;font-weight:800;color:#6366f1;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalGross, 0) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Tax Withheld</div>
            <div style="font-size:28px;font-weight:800;color:#dc2626;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalTax, 0) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Bonuses</div>
            <div style="font-size:28px;font-weight:800;color:#f59e0b;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalBonus, 0) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Total Net Pay</div>
            <div style="font-size:28px;font-weight:800;color:#10b981;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalNet, 0) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Employees</div>
            <div style="font-size:28px;font-weight:800;color:#3b82f6;"><?= count($runs) ?: count($users) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payroll Ledger -->
    <?php if(!empty($runs)): ?>
    <h3 style="color:var(--text-heading);font-size:16px;font-weight:700;margin-bottom:14px;">📋 Payroll Ledger — <?= date('F Y', strtotime($period.'-01')) ?></h3>
    <div class="data-table" style="margin-bottom:32px;">
        <table>
            <thead><tr><th>Employee</th><th>Dept</th><th>Base Salary</th><th>Bonus</th><th>Deductions</th><th>Tax</th><th>Net Pay</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($runs as $r):
                $sc = $r['status']==='Paid' ? ['#dcfce7','#16a34a'] : ($r['status']==='Processed' ? ['#dbeafe','#2563eb'] : ['#f3f4f6','#6b7280']);
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($r['user_id']) ?></small></td>
                <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
                <td><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($r['base_salary'], 2) ?></td>
                <td style="color:#f59e0b;font-weight:700;">+$<?= number_format($r['bonuses'], 2) ?></td>
                <td style="color:#ef4444;">-$<?= number_format($r['deductions'], 2) ?></td>
                <td style="color:#dc2626;">-$<?= number_format($r['tax_amount'], 2) ?></td>
                <td style="font-weight:800;color:#10b981;font-size:15px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($r['net_pay'], 2) ?></td>
                <td><span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;"><?= $r['status'] ?></span></td>
                <td class="action-buttons">
                    <a href="controllers/payslip.php?id=<?= $r['id'] ?>" target="_blank" class="edit-button" style="text-decoration:none;font-size:12px;">📄 Payslip</a>
                    <?php if($isAdmin && $r['status']==='Draft'): ?>
                    <button class="edit-button" onclick="editRun(<?= json_encode($r) ?>)" style="font-size:12px;">Edit</button>
                    <?php endif; ?>
                    <?php if($isAdmin): ?>
                    <form method="POST" action="controllers/duplicate_item.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="type" value="payslip">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="edit-button" style="font-size:12px; background:var(--bg-card); color:var(--text-body); border:1px solid var(--border-card);" title="Duplicate Payslip">📋 Copy</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif($isAdmin): ?>
    <div style="background:var(--bg-card);border-radius:14px;padding:40px;text-align:center;border:1px solid var(--border-card);margin-bottom:28px;">
        <div style="font-size:40px;margin-bottom:12px;">📋</div>
        <h3 style="color:var(--text-heading);margin-bottom:8px;">No payroll generated for <?= date('F Y', strtotime($period.'-01')) ?></h3>
        <p style="color:var(--text-muted);margin-bottom:20px;">Click "Generate Payroll" to create payroll entries for all active employees.</p>
    </div>
    <?php else: ?>
    <div style="background:var(--bg-card);border-radius:14px;padding:40px;text-align:center;border:1px solid var(--border-card);margin-bottom:28px;">
        <h3 style="color:var(--text-heading);margin-bottom:8px;">No Payslip Available</h3>
        <p style="color:var(--text-muted);">Your payslip for <?= date('F Y', strtotime($period.'-01')) ?> has not been generated yet.</p>
    </div>
    <?php endif; ?>

    <!-- Salary Profiles Config -->
    <?php if($isAdmin): ?>
    <h3 style="color:var(--text-heading);font-size:16px;font-weight:700;margin-bottom:14px;">⚙️ Salary Profiles</h3>
    <div class="data-table">
        <table>
            <thead><tr><th>Employee</th><th>Department</th><th>Base Salary</th><th>Tax Rate</th><th>Bank Account</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($profiles as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small><?= htmlspecialchars($p['user_id']) ?></small></td>
                <td><?= htmlspecialchars($p['department'] ?? '—') ?></td>
                <td><strong><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p['base_salary'], 2) ?></strong>/mo</td>
                <td><?= round($p['tax_rate']*100, 1) ?>%</td>
                <td><?= $p['bank_account'] ? '****'.substr($p['bank_account'],-4) : '<span style="color:#9ca3af;">Not set</span>' ?></td>
                <td><button class="edit-button" onclick="editProfile(">Edit Profile</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Run Modal -->
<div id="genericModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Edit Payroll Entry</h2>
        <form id="modalForm" method="POST" action="controllers/save_payroll.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div id="modalFields"></div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="submit">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function generatePayroll(period) {
    Swal.fire({
        title: 'Generate Payroll?',
        text: 'Generate payroll for all active employees for ' + period + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6366f1'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action','generate'); fd.append('period',period);
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
            fetch('controllers/save_payroll.php', {method:'POST',body:fd}).then(()=>location.reload());
        }
    });
}
function runPayroll(period) {
    Swal.fire({
        title: 'Process Payroll?',
        text: 'Mark all Draft entries as Processed for ' + period + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action','process_all'); fd.append('period',period);
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
            fetch('controllers/save_payroll.php', {method:'POST',body:fd}).then(()=>location.reload());
        }
    });
}
function editRun(r) {
    document.getElementById('modalTitle').textContent = 'Edit Payroll — ' + r.name;
    document.getElementById('modalForm').action = 'controllers/save_payroll.php';
    let html = `<input type="hidden" name="action" value="edit_run"><input type="hidden" name="id" value="${r.id}">`;
    html += `<div class="form-group"><label>Bonus (₹)</label><input type="number" step="0.01" name="bonuses" value="${r.bonuses}"></div>`;
    html += `<div class="form-group"><label>Additional Deductions (₹)</label><input type="number" step="0.01" name="deductions" value="${r.deductions}"></div>`;
    html += `<div class="form-group"><label>Status</label><select name="status"><option ${r.status=='Draft'?'selected':''}>Draft</option><option ${r.status=='Processed'?'selected':''}>Processed</option><option ${r.status=='Paid'?'selected':''}>Paid</option></select></div>`;
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}
function editProfile(p) {
    document.getElementById('modalTitle').textContent = 'Salary Profile — ' + p.name;
    document.getElementById('modalForm').action = 'controllers/save_payroll.php';
    let html = `<input type="hidden" name="action" value="edit_profile"><input type="hidden" name="user_id" value="${p.user_id}">`;
    html += `<div class="form-group"><label>Monthly Base Salary (₹)</label><input type="number" step="0.01" name="base_salary" value="${p.base_salary}" required></div>`;
    html += `<div class="form-group"><label>Tax Rate (0.0 – 1.0, e.g. 0.2 = 20%)</label><input type="number" step="0.01" min="0" max="1" name="tax_rate" value="${p.tax_rate}" required></div>`;
    html += `<div class="form-group"><label>Bank Account Number</label><input type="text" name="bank_account" value="${p.bank_account||''}"></div>`;
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}
function closeModal() { document.getElementById('genericModal').style.display = 'none'; }
</script>
<?php require_once 'includes/footer.php'; ?>
