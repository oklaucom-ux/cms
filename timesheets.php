<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_tasks');

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS timesheets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        project_id INTEGER NOT NULL,
        entry_date DATE NOT NULL,
        hours REAL NOT NULL,
        description TEXT,
        status VARCHAR(255) DEFAULT 'Pending Approval',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$isManager = hasPermission($pdo, 'manage_users') || in_array($_SESSION['role'], ['Admin', 'Super Admin']);
$myId = $_SESSION['login_id'];

// Fetch Active Projects
$projects = $pdo->query("SELECT id, name FROM projects WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch My Timesheets
$stmt = $pdo->prepare("SELECT t.*, p.name as project_name FROM timesheets t JOIN projects p ON t.project_id = p.id WHERE t.user_id = ? ORDER BY t.entry_date DESC LIMIT 50");
$stmt->execute([$myId]);
$myTimesheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pending Approvals (for Managers)
$pendingApprovals = [];
if ($isManager) {
    $pendingApprovals = $pdo->query("SELECT t.*, p.name as project_name, u.name as user_name FROM timesheets t JOIN projects p ON t.project_id = p.id JOIN users u ON t.user_id = u.login_id WHERE t.status = 'Pending Approval' ORDER BY t.entry_date ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>⏱️ Project Timesheets</h2>
        <button class="add-button" onclick="document.getElementById('timesheetModal').style.display='flex'">+ Log Hours</button>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        
        <!-- My Timesheets -->
        <div>
            <h3 style="color:var(--text-heading);">My Logged Hours</h3>
            <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:12px; color:#475569;">Date</th>
                            <th style="padding:12px; color:#475569;">Project</th>
                            <th style="padding:12px; color:#475569;">Hours</th>
                            <th style="padding:12px; color:#475569;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($myTimesheets as $t): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:12px; color:#1e293b; font-weight:600;"><?= htmlspecialchars($t['entry_date']) ?></td>
                            <td style="padding:12px; color:#3b82f6; font-weight:600;" title="<?= htmlspecialchars($t['description']) ?>"><?= htmlspecialchars($t['project_name']) ?></td>
                            <td style="padding:12px; font-weight:bold;"><?= $t['hours'] ?>h</td>
                            <td style="padding:12px;">
                                <span style="padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; <?= $t['status']=='Approved' ? 'background:#d1fae5; color:#065f46;' : 'background:#fef3c7; color:#92400e;' ?>">
                                    <?= $t['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($myTimesheets)) echo "<tr><td colspan='4' style='padding:12px; text-align:center; color:#64748b;'>No hours logged yet.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Manager Approvals -->
        <?php if($isManager): ?>
        <div>
            <h3 style="color:var(--text-heading);">Timesheets Awaiting Approval</h3>
            <?php foreach($pendingApprovals as $t): ?>
            <div style="background:white; border:1px solid #e2e8f0;  padding:15px; border-radius:12px; margin-bottom:15px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-weight:bold; font-size:15px;">👤 <?= htmlspecialchars($t['user_name']) ?></div>
                    <div style="font-size:12px; color:#64748b; font-weight:bold;"><?= htmlspecialchars($t['entry_date']) ?></div>
                </div>
                <div style="font-size:13px; color:#475569; margin-bottom:10px;">
                    Logged <strong style="color:#1e293b;"><?= $t['hours'] ?> hours</strong> on <strong style="color:#3b82f6;"><?= htmlspecialchars($t['project_name']) ?></strong>
                    <div style="margin-top:5px; background:#f8fafc; padding:8px; border-radius:6px; font-style:italic;">"<?= htmlspecialchars($t['description']) ?>"</div>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <form method="POST" action="controllers/save_timesheet.php" style="margin:0; flex:1;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" style="background:#10b981; color:white; border:none; padding:8px; border-radius:6px; font-size:12px; font-weight:bold; cursor:pointer; width:100%;">Approve</button>
                    </form>
                    <form method="POST" action="controllers/save_timesheet.php" style="margin:0; flex:1;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" style="background:#ef4444; color:white; border:none; padding:8px; border-radius:6px; font-size:12px; font-weight:bold; cursor:pointer; width:100%;">Reject</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($pendingApprovals)) echo "<p style='color:#64748b;'>No timesheets awaiting your approval.</p>"; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Log Hours Modal -->
<div class="modal" id="timesheetModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Log Project Hours</h2>
        <form method="POST" action="controllers/save_timesheet.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="log_hours">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Project</label>
            <select name="project_id" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
                <?php foreach($projects as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Date</label>
                    <input type="date" name="entry_date" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Hours</label>
                    <input type="number" step="0.5" min="0.5" max="24" name="hours" required placeholder="e.g. 4.5" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">What did you work on?</label>
            <textarea name="description" required rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;"></textarea>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('timesheetModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Log Hours</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

