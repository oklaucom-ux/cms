<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_projects');

$canCreateProjects = hasPermission($pdo, 'create_projects');
$canEditProjects   = hasPermission($pdo, 'edit_projects');
$canDeleteProjects = hasPermission($pdo, 'delete_projects');

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

// Auto-Migrate schema to prevent fatal errors

// Fetch user branch
$myBranch = $pdo->query("SELECT branch_id FROM users WHERE login_id = '{$_SESSION['login_id']}'")->fetchColumn() ?: 'Global HQ';

$ws_filter = "";
$ws_params = [];
if (isset($_SESSION['active_workspace_id'])) {
    $ws_filter = " AND (workspace_id = ? OR workspace_id IS NULL) ";
    $ws_params[] = $_SESSION['active_workspace_id'];
}

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE 1=1 $ws_filter ORDER BY created_at DESC");
    $stmt->execute($ws_params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE branch_id = ? $ws_filter ORDER BY created_at DESC");
    $stmt->execute(array_merge([$myBranch], $ws_params));
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$allUsers = $pdo->query("SELECT login_id, name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Workspaces
$myWorkspaces = [];
if ($isAdmin) {
    $myWorkspaces = $pdo->query("SELECT id, name FROM workspaces")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $wsStmt = $pdo->prepare("SELECT w.id, w.name FROM workspaces w JOIN workspace_members wm ON w.id = wm.workspace_id WHERE wm.user_id = ?");
    $wsStmt->execute([$_SESSION['login_id']]);
    $myWorkspaces = $wsStmt->fetchAll(PDO::FETCH_ASSOC);
}
// Fetch Custom Statuses
$projectStatuses = $pdo->query("SELECT status_name, color FROM custom_statuses WHERE module = 'projects' ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
if (empty($projectStatuses)) {
    $projectStatuses = [
        ['status_name' => 'Planning', 'color' => '#6b7280'],
        ['status_name' => 'Active', 'color' => '#3b82f6'],
        ['status_name' => 'On Hold', 'color' => '#f59e0b'],
        ['status_name' => 'Completed', 'color' => '#10b981']
    ];
}
$statusMap = [];
foreach($projectStatuses as $st) {
    $statusMap[$st['status_name']] = $st['color'];
}

// Calculate burn rate for each project
$projectData = [];
foreach($projects as $p) {
    // Total expenses linked to project
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE project_id = ? AND status = 'Approved'");
    $stmt->execute([$p['id']]);
    $spent = $stmt->fetchColumn() ?: 0;
    
    // Total dynamic time-burn from task clock-ins
    $tLogStmt = $pdo->prepare("SELECT SUM(ttl.cost_incurred) FROM task_time_logs ttl JOIN tasks t ON ttl.task_id = t.id WHERE t.project_id = ?");
    $tLogStmt->execute([$p['id']]);
    $timeCost = $tLogStmt->fetchColumn() ?: 0;
    
    $spent += $timeCost;
    
    // Total tasks and completed tasks
    $tStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='Done' THEN 1 ELSE 0 END) as done FROM tasks WHERE project_id = ? AND status != 'Deleted'");
    $tStmt->execute([$p['id']]);
    $tData = $tStmt->fetch(PDO::FETCH_ASSOC);

    $p['spent'] = $spent;
    $p['tasks_total'] = $tData['total'] ?? 0;
    $p['tasks_done'] = $tData['done'] ?? 0;

    // Project Files
    $fStmt = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC");
    $fStmt->execute([$p['id']]);
    $p['files'] = $fStmt->fetchAll(PDO::FETCH_ASSOC);

    $projectData[] = $p;
}

?>

<div class="content-section active">
    <div class="section-header">
        <h2>Project Portfolio Management (PPM)</h2>
        <?php if($canCreateProjects): ?>
        <button class="add-button" onclick="openProjectModal()">🚀 Spin up Project</button>
        <?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px, 1fr)); gap:20px;">
        <?php foreach($projectData as $p): ?>
        <div style="background:white; border-radius:12px; padding:24px; box-shadow:0 4px 6px rgba(0,0,0,0.05); ">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <h3 style="font-size:20px; color:#111827; margin:0;"><?= htmlspecialchars($p['name']) ?></h3>
                <?php 
                $textColor = $statusMap[$p['status']] ?? '#4f46e5';
                $bgColorAlpha = $textColor . '22';
                ?>
                <span style="background:<?= $bgColorAlpha ?>; color:<?= $textColor ?>; border:1px solid <?= $textColor ?>44; padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold; height:fit-content;"><?= htmlspecialchars($p['status']) ?></span>
            </div>
            
            <div style="color:#6b7280; font-size:13px; margin-bottom:20px;">
                Client ID: <?= htmlspecialchars($p['client_id'] ?: 'Internal/None') ?> | 
                Deadline: <?= htmlspecialchars($p['deadline']) ?> | 
                <span style="font-weight:bold; color:#4338ca;">Branch: <?= htmlspecialchars($p['branch_id'] ?? 'Global HQ') ?></span>
            </div>

            <div style="display:flex; gap:10px; margin-bottom:20px;">
                <div style="flex:1; background:#f3f4f6; padding:12px; border-radius:8px; text-align:center;">
                    <div style="font-size:11px; color:#6b7280; font-weight:bold; text-transform:uppercase;">Live Realized Burn</div>
                    <div style="font-size:16px; font-weight:bold; color: <?= $p['spent'] > $p['budget'] ? '#dc2626' : '#10b981' ?>;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p['spent'], 2) ?> / <?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p['budget'], 2) ?>
                    </div>
                </div>
                <div style="flex:1; background:#f3f4f6; padding:12px; border-radius:8px; text-align:center;">
                    <div style="font-size:11px; color:#6b7280; font-weight:bold; text-transform:uppercase;">Task Progress</div>
                    <div style="font-size:16px; font-weight:bold; color:#f59e0b;">
                        <?= $p['tasks_done'] ?> / <?= $p['tasks_total'] ?> Done
                    </div>
                </div>
            </div>

            <?php if(!empty($p['ai_forecast'])): 
                $ai = json_decode($p['ai_forecast'], true);
                if($ai && isset($ai['risk_level'])):
                    $riskColor = $ai['risk_level'] === 'High' ? '#ef4444' : ($ai['risk_level'] === 'Medium' ? '#f59e0b' : '#10b981');
                    $riskBg = $ai['risk_level'] === 'High' ? '#fef2f2' : ($ai['risk_level'] === 'Medium' ? '#fffbeb' : '#ecfdf5');
            ?>
            <div style="background:<?= $riskBg ?>; border:1px solid <?= $riskColor ?>; border-radius:8px; padding:12px; margin-bottom:20px; font-size:13px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; align-items:center;">
                    <strong style="color:<?= $riskColor ?>; display:flex; align-items:center; gap:4px;">✨ AI Forecast (Risk: <?= htmlspecialchars($ai['risk_level']) ?>)</strong>
                </div>
                <div style="color:#4b5563; margin-bottom:4px;"><strong>Forecast:</strong> <?= htmlspecialchars($ai['forecast']) ?></div>
                <div style="color:#4b5563;"><strong>Advice:</strong> <?= htmlspecialchars($ai['recommendation']) ?></div>
            </div>
            <?php endif; endif; ?>

            <div style="display:flex; gap:10px;">
                <button class="view-button" style="flex:1;background:var(--bg-card);color:var(--text-body);border:1px solid var(--border-card);" onclick='openFilesModal(<?= json_encode($p) ?>)'>📎 Files (<?= count($p['files']) ?>)</button>
                <?php if($canEditProjects): ?>
                <button class="edit-button" style="flex:1;" onclick='editProject(<?= json_encode($p) ?>)'>Edit</button>
                <?php endif; ?>
                <?php if($canCreateProjects): ?>
                <form method="POST" action="controllers/duplicate_item.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="type" value="project">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="view-button" style="padding:10px;" title="Duplicate Project">📋</button>
                </form>
                <?php endif; ?>
                <?php if($canDeleteProjects): ?>
                <form method="POST" action="controllers/delete_project.php" onsubmit="return confirm('Delete Project? Connected tasks will revert to Unassigned.')" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="delete-button" style="padding:10px;">Del</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="genericModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Project Settings</h2>
        <form id="modalForm" method="POST" action="controllers/save_project.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div id="modalFields"></div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="submit">Save Matrix</button>
            </div>
        </form>
    </div>
</div>

<!-- Files Modal -->
<div id="filesModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeFilesModal()">&times;</span>
        <h2 id="filesModalTitle">Project Files</h2>
        
        <form method="POST" action="controllers/upload_project_file.php" enctype="multipart/form-data" style="margin-bottom:20px; padding:16px; background:#f9fafb; border-radius:8px; border:1px dashed #cbd5e1;">
            <input type="hidden" name="project_id" id="uploadProjectId">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="file" name="file" required style="flex:1; font-size:13px;">
                <button type="submit" class="submit" style="padding:8px 16px;">Upload</button>
            </div>
        </form>

        <div id="filesList" style="display:flex; flex-direction:column; gap:10px; max-height:400px; overflow-y:auto;"></div>
    </div>
</div>

<script>
function openProjectModal(d = null) {
    document.getElementById('modalTitle').textContent = d ? "Edit Project" : "Spin up Project";
    
    let html = `<input type="hidden" name="id" value="${d ? d.id : ''}">`;
    html += `<div class="form-group"><label>Project Name</label><input type="text" name="name" required value="${d ? d.name : ''}"></div>`;
    
    let uList = <?= json_encode($allUsers) ?>;
    html += `<div class="form-group"><label>Bound Client Identity (For Portal Access)</label><select name="client_id">`;
    html += `<option value="">-- Internal Only (No Client) --</option>`;
    uList.forEach(u => {
        let sel = (d && d.client_id == u.login_id) ? 'selected' : '';
        html += `<option value="${u.login_id}" ${sel}>${u.name} [${u.role}] (${u.login_id})</option>`;
    });
    html += `</select></div>`;

    let wsList = <?= json_encode($myWorkspaces) ?>;
    html += `<div class="form-group"><label>Workspace</label><select name="workspace_id">`;
    html += `<option value="">-- Global / Unassigned --</option>`;
    wsList.forEach(w => {
        let sel = (d && d.workspace_id == w.id) ? 'selected' : '';
        html += `<option value="${w.id}" ${sel}>${w.name}</option>`;
    });
    html += `</select></div>`;
    
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">`;
    html += `<div class="form-group"><label>Assigned Budget (₹)</label><input type="number" step="0.01" name="budget" required value="${d ? d.budget : '0'}"></div>`;
    html += `<div class="form-group"><label>Hard Deadline</label><input type="date" name="deadline" required value="${d ? d.deadline : ''}"></div>`;
    html += `</div>`;
    
    html += `<div class="form-group"><label>Status</label><select name="status">`;
    const stArr = <?= json_encode(array_column($projectStatuses, 'status_name')) ?>;
    stArr.forEach(s => {
        let sel = (d && d.status == s) ? 'selected' : '';
        html += `<option value="${s}" ${sel}>${s}</option>`;
    });
    html += `</select></div>`;

    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}
function editProject(d) { openProjectModal(d); }
function closeModal() { document.getElementById('genericModal').style.display = 'none'; }

function openFilesModal(p) {
    document.getElementById('filesModalTitle').textContent = `Files: ${p.name}`;
    document.getElementById('uploadProjectId').value = p.id;
    
    let html = '';
    if (!p.files || p.files.length === 0) {
        html = '<p style="color:#6b7280; font-size:13px; text-align:center;">No files uploaded yet.</p>';
    } else {
        p.files.forEach(f => {
            html += `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; background:white; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="display:flex; flex-direction:column;">
                    <span style="font-size:14px; font-weight:600; color:#111827;">${f.file_name}</span>
                    <span style="font-size:11px; color:#6b7280;">Uploaded: ${f.uploaded_at} by ${f.uploader_id}</span>
                </div>
                <a href="${f.file_path}" target="_blank" style="padding:6px 12px; background:#e0e7ff; color:#4f46e5; border-radius:6px; font-size:12px; font-weight:bold; text-decoration:none;">Download</a>
            </div>`;
        });
    }
    document.getElementById('filesList').innerHTML = html;
    document.getElementById('filesModal').style.display = 'block';
}

function closeFilesModal() {
    document.getElementById('filesModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>

