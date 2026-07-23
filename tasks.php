<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_tasks');

$canCreateTasks = hasPermission($pdo, 'create_tasks');
$canEditTasks   = hasPermission($pdo, 'edit_tasks');
$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

$ws_filter = "";
$ws_params = [];
if (isset($_SESSION['active_workspace_id'])) {
    $ws_filter = " AND (p.workspace_id = ? OR p.workspace_id IS NULL) ";
    $ws_params[] = $_SESSION['active_workspace_id'];
}

$stmt = $pdo->prepare("SELECT t.* FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.status != 'Deleted' $ws_filter");
$stmt->execute($ws_params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Custom Statuses
$taskStatuses = $pdo->query("SELECT status_name, color FROM custom_statuses WHERE module = 'tasks' ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
if (empty($taskStatuses)) {
    $taskStatuses = [
        ['status_name' => 'Pending', 'color' => '#6b7280'],
        ['status_name' => 'In Progress', 'color' => '#3b82f6'],
        ['status_name' => 'On Hold', 'color' => '#f59e0b'],
        ['status_name' => 'Completed', 'color' => '#10b981']
    ];
}
$statusMap = [];
foreach($taskStatuses as $st) {
    $statusMap[$st['status_name']] = $st['color'];
}

// Box them by status
$board = [];
foreach($taskStatuses as $st) {
    $board[$st['status_name']] = [];
}

// Fetch active projects and users for dropdowns
$pStmt = $pdo->prepare("SELECT id, name FROM projects p WHERE status != 'Completed' $ws_filter");
$pStmt->execute($ws_params);
$projects = $pStmt->fetchAll(PDO::FETCH_ASSOC);

$allUsers = $pdo->query("SELECT login_id, name FROM users")->fetchAll(PDO::FETCH_ASSOC);

// For clock-in system check
$activeClocks = $pdo->query("SELECT task_id FROM task_time_logs WHERE user_id = '{$_SESSION['login_id']}' AND clock_out IS NULL")->fetchAll(PDO::FETCH_COLUMN);

// Pre-calculate whether tasks are blocked by incomplete dependency
$tasksLookup = [];
foreach($tasks as $t) { $tasksLookup[$t['id']] = $t['status']; }

foreach($tasks as &$t) {
    $t['is_blocked'] = false;
    if (!empty($t['dependency_id'])) {
        $parentStatus = $tasksLookup[$t['dependency_id']] ?? 'Deleted';
        if ($parentStatus !== 'Completed') {
            $t['is_blocked'] = true;
        }
    }
    
    if(isset($board[$t['status']])) {
        $board[$t['status']][] = $t;
    } else {
        $firstSt = $taskStatuses[0]['status_name'] ?? 'Pending';
        if (!isset($board[$firstSt])) $board[$firstSt] = [];
        $board[$firstSt][] = $t; // fallback
    }
}
?>
<style>
.kanban-board { display: flex; gap: 24px; min-height: calc(100vh - 200px); overflow-x: auto; padding-bottom: 20px; }
.kanban-col { flex: 1; min-width: 280px; background: var(--table-header); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 16px; border: 1px solid var(--border-card); }
.kanban-col-header { font-weight: 700; font-size: 16px; color: var(--text-heading); padding-bottom: 12px; margin-bottom: 0; border-bottom: 2px solid var(--border-card); display:flex; justify-content:space-between; align-items:center; }
.kanban-col-count { background: var(--border-card); padding: 2px 8px; border-radius: 12px; font-size: 12px; color: var(--text-body); font-weight: 600; }

.kanban-card { background: var(--bg-card); border-radius: 8px; padding: 16px; box-shadow: var(--shadow-soft); cursor: grab; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid var(--border-card); }
.kanban-card:active { cursor: grabbing; transform: scale(0.98); }
.kanban-card.dragging { opacity: 0.5; }

.kanban-card-title { font-weight: 600; color: var(--text-heading); margin-bottom: 8px; font-size: 15px; }
.kanban-card-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.kanban-card-meta { display: flex; justify-content: space-between; align-items: center; font-size: 12px; }

.priority-badge { padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 11px; text-transform: uppercase; }
.priority-High { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
.priority-Medium { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
.priority-Low { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }

/* Dropzone mapping */
.kanban-col.drag-over { background: var(--border-card); outline: 2px dashed var(--primary-color); }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>Kanban Task Board</h2>
        <?php if($canCreateTasks): ?>
        <button class="add-button" onclick="openTaskModal()">+ Create Task</button>
        <?php endif; ?>
    </div>

    <div class="kanban-board">
        <?php foreach($board as $statusName =>$colTasks): ?>
            <div class="kanban-col" data-status="<?= $statusName ?>">
                <div class="kanban-col-header">
                    <?php $textColor = $statusMap[$statusName] ?? '#111827'; ?>
                    <span style="color:<?= $textColor ?>; border-bottom:2px solid <?= $textColor ?>; padding-bottom:5px; margin-bottom:-14px; z-index:2;"><?= $statusName ?></span>
                    <span class="kanban-col-count" id="count-<?= str_replace(' ', '', $statusName) ?>"><?= count($colTasks) ?></span>
                </div>
                
                <div class="kanban-dropzone" style="flex:1; display:flex; flex-direction:column; gap:16px;">
                    <?php foreach($colTasks as $task): 
                        $blocked = $task['is_blocked'];
                        $isClockedIn = in_array($task['id'], $activeClocks);
                    ?>
                        <div class="kanban-card <?= $blocked ? 'blocked' : '' ?>" draggable="<?= ($canEditTasks && !$blocked) ? 'true' : 'false' ?>" data-id="<?= $task['id'] ?>" data-task_id="<?= $task['task_id'] ?>" data-json='<?= htmlspecialchars(json_encode($task), ENT_QUOTES) ?>' onclick="<?= ($canEditTasks && !$blocked) ? 'editTask(JSON.parse(this.dataset.json))' : '' ?>" style="position:relative;">
                            
                            <?php if($blocked): ?>
                            <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.7); z-index:10; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-direction:column; cursor:not-allowed;">
                                <div style="font-size:32px; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.2));">🔒</div>
                                <div style="font-size:11px; font-weight:bold; color:#ef4444; background:white; padding:2px 6px; border-radius:4px; margin-top:4px;">Dependency Locked</div>
                            </div>
                            <?php endif; ?>

                            <div class="kanban-card-title">
                                <?= htmlspecialchars($task['name']) ?>
                                <?php if($task['is_milestone']): ?>
                                 <span title="Client Milestone" style="color:#d97706;font-size:12px;">🌟</span>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-card-desc"><?= htmlspecialchars($task['description']) ?></div>
                            
                            <!-- CLOCK IN ENGINE -->
                            <?php if($task['assigned_to'] === $_SESSION['login_id'] && $task['status'] !== 'Completed' && !$blocked): ?>
                                <div style="margin-bottom:12px; border-top:1px solid #e5e7eb; padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
                                <?php if($isClockedIn): ?>
                                    <form method="POST" action="controllers/clock_out_task.php" style="margin:0; width:100%;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <button type="submit" style="width:100%; background:#ef4444; color:white; border:none; padding:6px; border-radius:4px; font-weight:bold; cursor:pointer;" onclick="event.stopPropagation()">🛑 Clock Out</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="controllers/clock_in_task.php" style="margin:0; width:100%;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <button type="submit" style="width:100%; background:#10b981; color:white; border:none; padding:6px; border-radius:4px; font-weight:bold; cursor:pointer;" onclick="event.stopPropagation()">⏱ Clock In</button>
                                    </form>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="kanban-card-meta">
                                <span class="priority-badge priority-<?= htmlspecialchars($task['priority']) ?>"><?= htmlspecialchars($task['priority']) ?></span>
                                <span style="color: #9ca3af; font-weight: 500;">@<?= htmlspecialchars($task['assigned_to']) ?></span>
                            </div>
                            
                            <?php if($canCreateTasks): ?>
                            <div style="margin-top:10px; text-align:right;">
                                <form method="POST" action="controllers/duplicate_item.php" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="type" value="task">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <button type="submit" style="background:var(--bg-card); border:1px solid var(--border-card); padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;" onclick="event.stopPropagation()">📋 Copy</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal remains exactly the same logic -->
<script>
// Drag and Drop Logic
const cards = document.querySelectorAll('.kanban-card');
const columns = document.querySelectorAll('.kanban-col');

let draggedCard = null;

cards.forEach(card => {
    card.addEventListener('dragstart', () => {
        draggedCard = card;
        setTimeout(() => card.classList.add('dragging'), 0);
    });
    
    card.addEventListener('dragend', () => {
        draggedCard.classList.remove('dragging');
        draggedCard = null;
        updateCounts();
    });
});

columns.forEach(col => {
    col.addEventListener('dragover', e => {
        e.preventDefault();
        col.classList.add('drag-over');
    });

    col.addEventListener('dragleave', () => {
        col.classList.remove('drag-over');
    });

    col.addEventListener('drop', e => {
        e.preventDefault();
        col.classList.remove('drag-over');
        if(draggedCard) {
            const newStatus = col.dataset.status;
            const taskData = JSON.parse(draggedCard.dataset.json);
            
            // Check dependencies strictly on drop
            if (taskData.dependency_id && (newStatus === 'In Progress' || newStatus === 'Completed')) {
                const globalTasks = <?= json_encode($tasks) ?>;
                const parent = globalTasks.find(t => t.id == taskData.dependency_id);
                if (parent && parent.status !== 'Completed') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Dependency Locked',
                            text: `This task requires "${parent.name}" to be Completed first.`
                        });
                    } else {
                        alert('Dependency Locked: Requires ' + parent.name + ' to be completed.');
                    }
                    return; // abort drop
                }
            }
            
            // Client Milestone Workflow interjection (Awaiting Approval)
            let actualStatus = newStatus;
            if (taskData.is_milestone && newStatus === 'Completed') {
                actualStatus = 'Awaiting Approval'; 
                // We'll let the kanban visually snap back to original col or reload page.
                // It's cleaner to just reload page since "Awaiting Approval" isn't a column on this board (or it is? actually we don't have that col).
                // Let's reload to let PHP handle the logic or just let the user know.
                alert("Milestone Task! Sent to Client for Approval instead of Completion.");
            }

            const dropzone = col.querySelector('.kanban-dropzone');
            dropzone.appendChild(draggedCard);
            
            // Update the JSON data on the element so subsequent drags know its new status
            taskData.status = newStatus;
            draggedCard.dataset.json = JSON.stringify(taskData);
            
            // Fire AJAX to update DB
            const taskId = taskData.task_id;
            
            let formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('status', actualStatus);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

            fetch('controllers/update_task_status.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                if(actualStatus === 'Awaiting Approval') window.location.reload();
            });
            updateCounts();
        }
    });
});

function updateCounts() {
    columns.forEach(col => {
        const count = col.querySelectorAll('.kanban-card').length;
        const statusName = col.dataset.status.replace(/\s+/g, '');
        document.getElementById('count-' + statusName).textContent = count;
    });
}
</script>

<script>
function openTaskModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Task Matrix" : "Create Task Node";
    document.getElementById('modalForm').action = "controllers/save_task.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<input type="hidden" name="task_id" value="${data ? data.task_id : 'TSK-'+Math.floor(Math.random()*1000)}">`;
    html += `<div class="form-group"><label>Task Title</label><input type="text" name="name" required value="${data ? data.name : ''}"></div>`;
    html += `<div class="form-group"><label>Description</label><textarea name="description">${data ? data.description : ''}</textarea></div>`;
    
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">`;
    
    // User Assignment Dropdown
    let uList = <?= json_encode($allUsers) ?>;
    html += `<div class="form-group"><label>Assigned To</label><select name="assigned_to" required>`;
    uList.forEach(u => {
        let selu = (data && data.assigned_to == u.login_id) ? 'selected' : '';
        html += `<option value="${u.login_id}" ${selu}>${u.name} (${u.login_id})</option>`;
    });
    html += `</select></div>`;
    
    html += `<div class="form-group"><label>Due Date</label><input type="date" name="due_date" required value="${data ? data.due_date : new Date().toISOString().split('T')[0]}"></div>`;
    html += `</div>`;
    
    // Project Linker
    let pList = <?= json_encode($projects) ?>;
    html += `<div class="form-group"><label>Link to Macro-Project</label><select name="project_id">
              <option value="0">Standalone Task (No Project)</option>`;
    pList.forEach(p => {
        let sel = (data && data.project_id == p.id) ? 'selected' : '';
        html += `<option value="${p.id}" ${sel}>${p.name}</option>`;
    });
    html += `</select></div>`;

    // Dependency Linker
    let tList = <?= json_encode($tasks) ?>;
    html += `<div class="form-group"><label>Prerequisite Task</label><select name="dependency_id">
              <option value="">None</option>`;
    tList.forEach(t => {
        if (!data || t.id != data.id) { // Don't allow depending on itself
            let selt = (data && data.dependency_id == t.id) ? 'selected' : '';
            html += `<option value="${t.id}" ${selt}>${t.task_id} - ${t.name} (${t.status})</option>`;
        }
    });
    html += `</select></div>`;
    
    html += `<div class="form-group">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; background:#fffbeb; padding:10px; border:1px solid #fef3c7; border-radius:6px; color:#d97706; font-weight:bold;">
            <input type="checkbox" name="is_milestone" value="1" ${data && data.is_milestone ? 'checked' : ''}>
            🌟 Client Facing Milestone (Requires Client Portal Approval)
        </label>
    </div>`;
    
    const priorities = ['Low','Medium','High'];
    const statuses = <?= json_encode(array_column($taskStatuses, 'status_name')) ?>;

    html += `<div class="form-group"><label>Priority</label><select name="priority">`;
    priorities.forEach(p => { html += `<option value="${p}" ${data&&data.priority==p?'selected':''}>${p}</option>`; });
    html += `</select></div>`;

    html += `<div class="form-group"><label>Status</label><select name="status">`;
    statuses.forEach(s => { html += `<option value="${s}" ${data&&data.status==s?'selected':''}>${s}</option>`; });
    html += `</select></div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editTask(data) { openTaskModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>
