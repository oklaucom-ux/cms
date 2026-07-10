<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
$me = $_SESSION['login_id'];

// Auto-migrate extra columns
try { $pdo->exec("ALTER TABLE activities ADD COLUMN priority VARCHAR(255) DEFAULT 'Normal'"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE activities ADD COLUMN progress INTEGER DEFAULT 0"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE activities ADD COLUMN created_by VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}

// Fetch all users for member selection
$allUsers = $pdo->query("SELECT login_id, name FROM users WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Filters
$statusFilter   = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$searchQ        = trim($_GET['q'] ?? '');

$sql = "SELECT a.*, u.name as creator_name FROM activities a LEFT JOIN users u ON a.created_by = u.login_id WHERE 1=1";
$params = [];
if ($statusFilter)   { $sql .= " AND a.status = ?";   $params[] = $statusFilter; }
if ($priorityFilter) { $sql .= " AND a.priority = ?"; $params[] = $priorityFilter; }
if ($searchQ)        { $sql .= " AND (a.name LIKE ? OR a.description LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
$sql .= " ORDER BY CASE a.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Normal' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END, a.due_date ASC";

$activities = $pdo->prepare($sql);
$activities->execute($params);
$activities = $activities->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalActs     = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$completedActs = $pdo->query("SELECT COUNT(*) FROM activities WHERE status='Completed'")->fetchColumn();
$overdueActs   = $pdo->query("SELECT COUNT(*) FROM activities WHERE status!='Completed' AND date(due_date) < date('now')")->fetchColumn();
$myActs        = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE included_members LIKE ?"); $myActs->execute(["%$me%"]); $myActsCount = $myActs->fetchColumn();

$priorityColors = ['Critical'=>'#dc2626','High'=>'#f59e0b','Normal'=>'#3b82f6','Low'=>'#10b981'];
$statusColors   = ['Pending'=>'#f59e0b','In Progress'=>'#3b82f6','Completed'=>'#10b981','On Hold'=>'#6b7280'];
?>
<style>
.act-card { background:var(--bg-card); border:1px solid var(--border-card); border-radius:14px; padding:20px; margin-bottom:16px; box-shadow:var(--shadow-soft); transition:box-shadow 0.2s; }
.act-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
.act-title { font-size:16px; font-weight:700; color:var(--text-heading); margin-bottom:6px; }
.act-desc { font-size:13px; color:var(--text-muted); line-height:1.5; margin-bottom:12px; }
.badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:99px; display:inline-block; }
.progress-bar-wrap { background:#e5e7eb; border-radius:99px; height:6px; margin:10px 0; overflow:hidden; }
.progress-bar-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#6366f1,#4f46e5); transition:width 0.4s; }
.filter-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px; align-items:center; }
.stat-mini { background:var(--bg-card); border:1px solid var(--border-card); border-radius:12px; padding:16px 24px; text-align:center; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>🎯 Activity Management</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openActivityModal()">+ New Activity</button>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:16px; margin-bottom:24px;">
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#4f46e5;"><?= $totalActs ?></div><div style="font-size:12px;color:var(--text-muted);">Total Activities</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#10b981;"><?= $completedActs ?></div><div style="font-size:12px;color:var(--text-muted);">Completed</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#ef4444;"><?= $overdueActs ?></div><div style="font-size:12px;color:var(--text-muted);">Overdue</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#3b82f6;"><?= $myActsCount ?></div><div style="font-size:12px;color:var(--text-muted);">Assigned to Me</div></div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-row">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="🔍 Search activities..." style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
        <select name="status" style="padding:9px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
            <option value="">All Statuses</option>
            <?php foreach(['Pending','In Progress','Completed','On Hold'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <select name="priority" style="padding:9px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
            <option value="">All Priorities</option>
            <?php foreach(['Critical','High','Normal','Low'] as $p): ?>
            <option value="<?= $p ?>" <?= $priorityFilter===$p?'selected':'' ?>><?= $p ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="add-button" style="background:#6366f1;">Filter</button>
        <?php if($statusFilter || $priorityFilter || $searchQ): ?><a href="activities.php" class="view-button">Clear</a><?php endif; ?>
    </form>

    <!-- Activity Cards -->
    <?php if(empty($activities)): ?>
    <div style="text-align:center;padding:60px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:16px;">📋</div>
        <p>No activities found matching your filters.</p>
    </div>
    <?php endif; ?>

    <?php foreach($activities as $act):
        $pColor = $priorityColors[$act['priority']] ?? '#6b7280';
        $sColor = $statusColors[$act['status']] ?? '#6b7280';
        $progress = min(100, max(0, (int)($act['progress'] ?? 0)));
        $isOverdue = ($act['status'] !== 'Completed') && !empty($act['due_date']) && strtotime($act['due_date']) < time();
        $members = array_filter(array_map('trim', explode(',', $act['included_members'] ?? '')));
    ?>
    <div class="act-card" style="">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div style="flex:1;">
                <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px; flex-wrap:wrap;">
                    <span class="badge" style="background:<?= $pColor ?>22; color:<?= $pColor ?>;"><?= htmlspecialchars($act['priority'] ?? 'Normal') ?></span>
                    <span class="badge" style="background:<?= $sColor ?>22; color:<?= $sColor ?>;"><?= htmlspecialchars($act['status']) ?></span>
                    <?php if($isOverdue): ?><span class="badge" style="background:#fee2e2;color:#dc2626;">⚠️ Overdue</span><?php endif; ?>
                    <span style="font-size:11px; color:var(--text-muted);">ID: <?= htmlspecialchars($act['activity_id'] ?? $act['id']) ?></span>
                </div>
                <div class="act-title"><?= htmlspecialchars($act['name']) ?></div>
                <div class="act-desc"><?= htmlspecialchars($act['description']) ?></div>
                
                <!-- Progress Bar -->
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar-fill" style="width:<?= $progress ?>%;"></div></div>
                    <span style="font-size:12px; font-weight:700; color:var(--text-heading); white-space:nowrap;"><?= $progress ?>%</span>
                </div>

                <!-- Members -->
                <?php if(!empty($members)): ?>
                <div style="margin-top:10px; display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    <span style="font-size:11px;color:var(--text-muted);">👥</span>
                    <?php foreach($members as $m): ?>
                    <span style="font-size:11px; background:var(--bg-body); border:1px solid var(--border-card); padding:2px 8px; border-radius:99px; color:var(--text-body);"><?= htmlspecialchars($m) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="text-align:right; flex-shrink:0;">
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;">
                    📅 Due: <strong style="color:<?= $isOverdue ? '#dc2626' : 'var(--text-heading)' ?>;"><?= htmlspecialchars($act['due_date'] ?? '—') ?></strong>
                </div>
                <?php if($isAdmin): ?>
                <div style="display:flex; gap:6px;">
                    <button class="edit-button" style="font-size:12px;padding:5px 12px;" onclick='openActivityModal(<?= json_encode($act) ?>)'>Edit</button>
                    <form method="POST" action="controllers/delete_activity.php" style="display:inline;" onsubmit="return confirm('Delete this activity?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id" value="<?= $act['id'] ?>">
                        <button type="submit" class="delete-button" style="font-size:12px;padding:5px 12px;">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
const allUsers = <?= json_encode($allUsers) ?>;

function openActivityModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Activity" : "Add Activity";
    document.getElementById('modalForm').action = "controllers/save_activity.php";

    const statuses   = ['Pending','In Progress','Completed','On Hold'];
    const priorities = ['Critical','High','Normal','Low'];

    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group"><label>Activity ID</label><input type="text" name="activity_id" value="${data ? (data.activity_id||'') : ''}" placeholder="ACT-001"></div>
        <div class="form-group"><label>Priority</label><select name="priority">`;
    priorities.forEach(p => { html += `<option value="${p}" ${data&&data.priority==p?'selected':''}>${p}</option>`; });
    html += `</select></div></div>`;

    html += `<div class="form-group"><label>Activity Name</label><input type="text" name="name" required value="${data ? data.name : ''}"></div>`;
    html += `<div class="form-group"><label>Description</label><textarea name="description" rows="3" required>${data ? data.description : ''}</textarea></div>`;

    html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group"><label>Status</label><select name="status">`;
    statuses.forEach(s => { html += `<option value="${s}" ${data&&data.status==s?'selected':''}>${s}</option>`; });
    html += `</select></div>
        <div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="${data ? (data.due_date||'') : ''}"></div></div>`;

    html += `<div class="form-group"><label>Progress (${data ? (data.progress||0) : 0}%)</label>
        <input type="range" name="progress" min="0" max="100" value="${data ? (data.progress||0) : 0}" oninput="this.previousElementSibling.textContent='Progress ('+this.value+'%)'"></div>`;

    html += `<div class="form-group"><label>Included Members (comma-separated login IDs or names)</label>
        <input type="text" name="included_members" value="${data ? (data.included_members||'') : ''}" placeholder="admin, john.doe, ...">
        <div style="margin-top:6px;font-size:12px;color:var(--text-muted);">Quick add: `;
    allUsers.forEach(u => {
        html += `<span onclick="addMember('${u.login_id}')" style="cursor:pointer;background:#e0e7ff;color:#4338ca;padding:2px 8px;border-radius:99px;margin:2px;display:inline-block;font-size:11px;">${u.name}</span>`;
    });
    html += `</div></div>`;

    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function addMember(id) {
    let inp = document.querySelector('[name="included_members"]');
    if (!inp) return;
    let current = inp.value.trim();
    if (!current) { inp.value = id; }
    else if (!current.split(',').map(x=>x.trim()).includes(id)) { inp.value = current + ', ' + id; }
}

function editActivity(data) { openActivityModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>

