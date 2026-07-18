<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Vendor') {
    die("<div class='content-section active'><h2>Unauthorized Access</h2><p>This portal is exclusively for Vendors and Subcontractors.</p></div>");
}

$me = $_SESSION['login_id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $taskId = (int)$_POST['task_id'];
    $newStatus = trim($_POST['status']);
    
    // Security check: Only update if assigned to this vendor
    $checkStmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to LIKE ?");
    $checkStmt->execute([$taskId, "%$me%"]);
    if ($checkStmt->fetch(PDO::FETCH_ASSOC) && in_array($newStatus, ['Pending', 'In Progress', 'Completed'])) {
        $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$newStatus, $taskId]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$me, 'Vendor Task Update', "Updated task #{$taskId} to {$newStatus}"]);
        header("Location: vendor_portal.php?updated=1");
        exit;
    }
}

// Fetch tasks
$tasksStmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to LIKE ? AND status != 'Deleted' ORDER BY due_date ASC");
$tasksStmt->execute(["%$me%"]);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.vendor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; margin-top: 24px; }
.task-card { 
    background: var(--bg-card); 
    border-radius: 16px; 
    padding: 24px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.04); 
    border: 1px solid var(--border-card);
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.task-card:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 20px 40px rgba(0,0,0,0.08); 
}
.task-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 4px; height: 100%;
}
.task-card.status-Pending::before { background: #f59e0b; }
.task-card.status-InProgress::before { background: #6366f1; }
.task-card.status-Completed::before { background: #10b981; }

.task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.task-id { font-size: 11px; color: var(--text-muted); font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; }
.task-title { font-size: 17px; font-weight: 700; color: var(--text-heading); margin-top: 6px; line-height: 1.4; }
.task-desc { font-size: 14px; color: var(--text-muted); margin-bottom: 24px; line-height: 1.6; flex: 1; }
.task-meta { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-body); margin-bottom: 20px; font-weight: 500; }

.status-badge { 
    padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;
}
.badge-Pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-InProgress { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.badge-Completed { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

.update-form { display: flex; gap: 12px; align-items: center; margin-top: auto; padding-top: 20px; border-top: 1px dashed var(--border-card); }
.status-select { 
    flex: 1; padding: 10px 14px; border-radius: 10px; border: 1px solid #cbd5e1; 
    font-size: 14px; outline: none; background: #f8fafc; color: var(--text-heading); font-weight: 500;
    transition: all 0.2s;
}
.status-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(79,70,229,0.1); background: #ffffff; }
.btn-save { 
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); 
    color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; 
    cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(79,70,229,0.25);
}
.btn-save:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(79,70,229,0.35); }
.urgent-date { color: #dc2626; font-weight: 700; display: flex; align-items: center; gap: 4px; background: #fee2e2; padding: 4px 10px; border-radius: 6px; }
.normal-date { color: var(--text-body); background: var(--bg-hover); padding: 4px 10px; border-radius: 6px; }
</style>

<div class="content-section active">
    <div class="section-header">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg, #10b981, #059669); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 20px rgba(16,185,129,0.3);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div>
                <h2 style="margin-bottom:4px; font-size:24px; font-weight:800; color:var(--text-heading);">Vendor Portal</h2>
                <p style="color:var(--text-muted); font-size:15px;">Manage your allocated tasks and update their progression status.</p>
            </div>
        </div>
    </div>

    <?php if(isset($_GET['updated'])): ?>
    <div style="background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); display:flex; align-items:center; gap:12px; font-weight:500;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        Task status updated successfully.
    </div>
    <?php endif; ?>

    <div class="vendor-grid">
        <?php foreach($tasks as $t): 
            $statusClass = str_replace(' ', '', $t['status']); // e.g. InProgress
            $dueDate = $t['due_date'];
            $isUrgent = (strtotime($dueDate) <= strtotime('+1 day')) && $t['status'] !== 'Completed';
            
            $statusIcon = '';
            if($t['status'] === 'Pending') $statusIcon = '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>';
            elseif($t['status'] === 'In Progress') $statusIcon = '<path d="M2 12h4l2-8 4 16 2-8h4"></path>';
            elseif($t['status'] === 'Completed') $statusIcon = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>';
        ?>
        <div class="task-card status-<?= $statusClass ?>">
            <div class="task-header">
                <div>
                    <div class="task-id">Task #<?= htmlspecialchars($t['id']) ?></div>
                    <div class="task-title"><?= htmlspecialchars($t['name']) ?></div>
                </div>
                <div class="status-badge badge-<?= $statusClass ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $statusIcon ?></svg>
                    <?= htmlspecialchars($t['status']) ?>
                </div>
            </div>
            
            <div class="task-desc">
                <?= htmlspecialchars($t['description']) ?>
            </div>
            
            <div class="task-meta">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-muted);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span class="<?= $isUrgent ? 'urgent-date' : 'normal-date' ?>">
                    <?php if($isUrgent): ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <?php endif; ?>
                    <?= htmlspecialchars($dueDate) ?>
                </span>
            </div>
            
            <?php if($t['status'] !== 'Completed'): ?>
            <form method="POST" class="update-form">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <select name="status" class="status-select">
                    <option value="Pending" <?= $t['status']=='Pending'?'selected':'' ?>>⏳ Pending</option>
                    <option value="In Progress" <?= $t['status']=='In Progress'?'selected':'' ?>>🚀 In Progress</option>
                    <option value="Completed">✅ Completed</option>
                </select>
                <button type="submit" class="btn-save">Update</button>
            </form>
            <?php else: ?>
            <div class="update-form" style="justify-content:center; background:#f8fafc; border-radius:10px; margin-top:auto; padding:12px; border:none; color:#10b981; font-weight:700;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Task Completed
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if(empty($tasks)): ?>
    <div style="background:var(--bg-card); border-radius:20px; padding:60px 20px; text-align:center; border:1px dashed var(--border-card); margin-top:24px;">
        <div style="width:80px; height:80px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
        </div>
        <h3 style="font-size:20px; font-weight:700; color:var(--text-heading); margin-bottom:8px;">No Tasks Assigned</h3>
        <p style="color:var(--text-muted); max-width:400px; margin:0 auto;">You currently don't have any active tasks assigned to your vendor profile. Take a break!</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
