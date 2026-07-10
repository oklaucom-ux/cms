<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] === 'Client' || $_SESSION['role'] === 'Vendor') die("Unauthorized Access.");

require_once 'includes/sidebar.php';
require_once 'includes/flash.php';
require_once 'includes/notifications.php';

// Auto Migrate
$pdo->exec("CREATE TABLE IF NOT EXISTS leaves (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT, start_date DATE, end_date DATE, leave_type TEXT, reason TEXT, status VARCHAR(255) DEFAULT 'Pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS leave_types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT NOT NULL, annual_entitlement INTEGER DEFAULT 12, carry_over_max INTEGER DEFAULT 5)");
$pdo->exec("CREATE TABLE IF NOT EXISTS leave_balances (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, leave_type TEXT NOT NULL, year INTEGER NOT NULL, entitlement INTEGER DEFAULT 0, used INTEGER DEFAULT 0, UNIQUE(user_id, leave_type, year))");

// Seed default leave types if empty
if ($pdo->query("SELECT COUNT(*) FROM leave_types")->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO leave_types (name, annual_entitlement, carry_over_max) VALUES ('Annual Leave',20,5),('Sick Leave',10,0),('Casual Leave',5,0),('Maternity Leave',90,0),('Paternity Leave',14,0)");
}

$leaveTypes = $pdo->query("SELECT * FROM leave_types")->fetchAll(PDO::FETCH_ASSOC);
$isAdmin = hasPermission($pdo, 'approve_leaves') || (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
$isManager = ($_SESSION['role'] === 'Manager');
$me = $_SESSION['login_id'];
$year = date('Y');

// Auto-init balances for current user
foreach ($leaveTypes as $lt) {
    $pdo->prepare("INSERT OR IGNORE INTO leave_balances (user_id, leave_type, year, entitlement, used) VALUES (?,?,?,?,0)")
        ->execute([$me, $lt['name'], $year, $lt['annual_entitlement']]);
}

// Fetch leaves
if ($isAdmin) {
    $leaves = $pdo->query("SELECT l.*, u.name as user_name, u.manager_id FROM leaves l JOIN users u ON l.user_id = u.login_id ORDER BY l.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else if ($isManager) {
    $stmt = $pdo->prepare("SELECT l.*, u.name as user_name, u.manager_id FROM leaves l JOIN users u ON l.user_id = u.login_id WHERE l.user_id = ? OR u.manager_id = ? ORDER BY l.created_at DESC");
    $stmt->execute([$me, $me]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT l.*, u.name as user_name, u.manager_id FROM leaves l JOIN users u ON l.user_id = u.login_id WHERE l.user_id = ? ORDER BY l.created_at DESC");
    $stmt->execute([$me]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch my balances
$myBalances = $pdo->prepare("SELECT lb.*, lt.annual_entitlement FROM leave_balances lb JOIN leave_types lt ON lb.leave_type = lt.name WHERE lb.user_id=? AND lb.year=?");
$myBalances->execute([$me, $year]);
$myBalances = $myBalances->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🌴 HR Leave Management / PTO</h2>
        <?php if(!$isAdmin): ?>
        <button class="add-button" onclick="openLeaveModal()">+ Request Leave</button>
        <?php endif; ?>
    </div>

    <?php renderFlash(); ?>

    <!-- Leave Balance Cards -->
    <?php if(!$isAdmin && !empty($myBalances)): ?>
    <h3 style="color:var(--text-heading);margin-bottom:16px;font-size:16px;">📊 My <?= $year ?> Leave Balances</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:32px;">
        <?php foreach($myBalances as $b):
            $remaining = $b['entitlement'] - $b['used'];
            $pct = $b['entitlement'] > 0 ? min(($b['used'] / $b['entitlement']) * 100, 100) : 0;
            $barColor = $pct >= 90 ? '#dc2626' : ($pct >= 70 ? '#f59e0b' : '#10b981');
        ?>
        <div style="background:white;border-radius:12px;padding:18px;box-shadow:0 4px 6px rgba(0,0,0,0.05);">
            <div style="font-size:12px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:8px;"><?= htmlspecialchars($b['leave_type']) ?></div>
            <div style="font-size:26px;font-weight:800;color:#111827;"><?= $remaining ?><span style="font-size:14px;color:#6b7280;font-weight:500;"> / <?= $b['entitlement'] ?> days</span></div>
            <div style="background:#f3f4f6;border-radius:99px;height:6px;margin-top:10px;overflow:hidden;">
                <div style="background:<?= $barColor ?>;height:100%;width:<?= round($pct) ?>%;border-radius:99px;"></div>
            </div>
            <div style="font-size:11px;color:#9ca3af;margin-top:6px;"><?= $b['used'] ?> used · <?= $remaining ?> remaining</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if($isAdmin): ?>
    <!-- Admin: Pending count -->
    <?php $pending = count(array_filter($leaves, fn($l) =>$l['status'] === 'Pending')); ?>
    <?php if($pending): ?><div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-weight:600;color:#92400e;">⏳ <?= $pending ?> leave request(s) awaiting approval</div><?php endif; ?>
    <?php endif; ?>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <?php if($isAdmin || $isManager): ?><th style="width:40px;"><input type="checkbox" id="selectAllLeaves" onclick="toggleAllLeaves(this)"></th><?php endif; ?>
                    <th>Employee</th><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Reason</th><th>Status</th>
                    <?php if($isAdmin || $isManager): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($leaves as $L):
                    $days = (strtotime($L['end_date']) - strtotime($L['start_date'])) / 86400 + 1;
                    $sc = $L['status']=='Approved' ? ['#dcfce7','#16a34a'] : ($L['status']=='Rejected' ? ['#fee2e2','#dc2626'] : ['#fef3c7','#d97706']);
                ?>
                <tr>
                    <?php if($isAdmin || $isManager): ?>
                        <td>
                            <?php if($L['status'] === 'Pending'): ?>
                                <input type="checkbox" class="leave-checkbox" value="<?= $L['id'] ?>">
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td><strong><?= htmlspecialchars($L['user_name']) ?></strong><br><small><?= htmlspecialchars($L['user_id']) ?></small></td>
                    <td><?= htmlspecialchars($L['leave_type']) ?></td>
                    <td><?= htmlspecialchars($L['start_date']) ?></td>
                    <td><?= htmlspecialchars($L['end_date']) ?></td>
                    <td><strong><?= $days ?></strong></td>
                    <td><?= htmlspecialchars(substr($L['reason'],0,40)).(strlen($L['reason'])>40?'...':'') ?></td>
                    <td><span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;"><?= htmlspecialchars($L['manager_status'] ?? $L['status']) ?></span></td>
                    <?php if($isAdmin || $isManager): ?>
                    <td class="action-buttons">
                        <?php 
                        $canApprove = false;
                        if ($L['status'] === 'Pending') {
                            if ($isAdmin) $canApprove = true;
                            // Manager can approve their direct reports
                            if ($isManager && $L['manager_id'] === $me) $canApprove = true;
                        }
                        if($canApprove): ?>
                        <form method="POST" action="controllers/update_leave.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $L['id'] ?>">
                            <input type="hidden" name="status" value="Approved">
                            <button type="submit" class="edit-button" style="background:#10b981;color:white;border:none;">✓ Approve</button>
                        </form>
                        <form method="POST" action="controllers/update_leave.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $L['id'] ?>">
                            <input type="hidden" name="status" value="Rejected">
                            <button type="submit" class="delete-button">✗ Reject</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#9ca3af;font-size:12px;">Locked</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($leaves)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No leave requests yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if($isAdmin || $isManager): ?>
    <div style="margin-top: 16px; padding: 16px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; display: flex; gap: 10px; align-items: center;">
        <span style="font-weight: 600; color: #4b5563;">Batch Actions:</span>
        <button onclick="batchAction('Approved')" class="edit-button" style="background: #10b981; color: white;">✓ Approve Selected</button>
        <button onclick="batchAction('Rejected')" class="delete-button">✗ Reject Selected</button>
    </div>
    <?php endif; ?>
</div>

<div id="leaveModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="document.getElementById('leaveModal').style.display='none'">&times;</span>
        <h2>Request Time Off</h2>
        <form method="POST" action="controllers/save_leave.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Leave Type</label>
                <select name="type" required>
                    <?php foreach($leaveTypes as $lt): ?>
                    <option value="<?= htmlspecialchars($lt['name']) ?>"><?= htmlspecialchars($lt['name']) ?> (<?= $lt['annual_entitlement'] ?> days/year)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:16px;">
                <div class="form-group" style="flex:1;">
                    <label>Start Date</label>
                    <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>" onchange="calcDays()">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>End Date</label>
                    <input type="date" name="end_date" required min="<?= date('Y-m-d') ?>" onchange="calcDays()">
                </div>
            </div>
            <div id="daysPreview" style="background:#f0fdf4;color:#16a34a;border-radius:8px;padding:10px 14px;font-weight:600;font-size:14px;margin-bottom:12px;display:none;"></div>
            <div class="form-group">
                <label>Reason / Note</label>
                <textarea name="reason" rows="3" required></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="document.getElementById('leaveModal').style.display='none'">Cancel</button>
                <button type="submit" class="submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>
<script>
function openLeaveModal(){ document.getElementById('leaveModal').style.display='block'; }
function calcDays(){
    const s=document.querySelector('[name=start_date]').value, e=document.querySelector('[name=end_date]').value;
    if(!s||!e)return;
    const days=Math.round((new Date(e)-new Date(s))/86400000)+1;
    const p=document.getElementById('daysPreview');
    p.style.display='block'; p.textContent=days+' working day(s) requested';
}

function toggleAllLeaves(source) {
    const checkboxes = document.querySelectorAll('.leave-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

async function batchAction(status) {
    const checkboxes = document.querySelectorAll('.leave-checkbox:checked');
    if (checkboxes.length === 0) return alert("Please select at least one leave request.");
    
    if (!confirm(`Are you sure you want to mark ${checkboxes.length} requests as ${status}?`)) return;
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let successCount = 0;
    
    for (let cb of checkboxes) {
        let fd = new FormData();
        fd.append('id', cb.value);
        fd.append('status', status);
        fd.append('csrf_token', csrfToken);
        
        try {
            await fetch('controllers/update_leave.php', { method: 'POST', body: fd });
            successCount++;
        } catch(e) {}
    }
    
    alert(`Successfully processed ${successCount} requests.`);
    location.reload();
}
</script>
<?php require_once 'includes/footer.php'; ?>

