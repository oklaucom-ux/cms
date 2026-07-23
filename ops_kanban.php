<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_tasks');

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ops_tasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        assigned_type VARCHAR(255) DEFAULT 'User',
        assigned_to TEXT,
        priority VARCHAR(255) DEFAULT 'Medium',
        status VARCHAR(255) DEFAULT 'Backlog',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ops_subtasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        task_id INTEGER,
        title TEXT NOT NULL,
        is_completed INTEGER DEFAULT 0,
        FOREIGN KEY(task_id) REFERENCES ops_tasks(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ops_columns (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name TEXT NOT NULL,
        position INTEGER DEFAULT 0
    )");
    $colCount = $pdo->query("SELECT COUNT(*) FROM ops_columns")->fetchColumn();
    if ($colCount == 0) {
        $defaults = ['Backlog', 'To-Do', 'In Progress', 'Review', 'Done'];
        $stmt = $pdo->prepare("INSERT INTO ops_columns (name, position) VALUES (?, ?)");
        foreach($defaults as $i => $c) {
            $stmt->execute([$c, $i]);
        }
    }
} catch (Exception $e) {}

// Fetch users and departments for the modal
$users = $pdo->query("SELECT login_id, name FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.ops-board { display: flex; gap: 24px; min-height: calc(100vh - 250px); overflow-x: auto; padding-bottom: 20px; margin-top: 20px; }
.ops-col { flex: 1; min-width: 290px; background: var(--bg-card); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 16px; border: 1px solid var(--border-card); box-shadow: var(--shadow-xs); }
.ops-col-header { font-weight: 700; font-size: 15px; color: var(--text-heading); padding-bottom: 12px; border-bottom: 2px solid var(--border-card); display: flex; justify-content: space-between; align-items: center; text-transform: uppercase; letter-spacing: 0.05em; }
.ops-col-count { background: var(--border-card); padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; color: var(--text-muted); }

.ops-dropzone { flex: 1; display: flex; flex-direction: column; gap: 16px; min-height: 100px; }
.ops-dropzone.drag-over { background: rgba(79, 70, 229, 0.05); outline: 2px dashed var(--primary-color); border-radius: 8px; }

.ops-card { background: #ffffff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); cursor: grab; border: 1px solid var(--border-card); transition: transform 0.2s, box-shadow 0.2s; position: relative; }
.ops-card:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.06); border-color: var(--primary-color); }
.ops-card:active { cursor: grabbing; transform: scale(0.98); }
.ops-card.dragging { opacity: 0.5; }

.ops-card-title { font-weight: 600; font-size: 14px; margin-bottom: 8px; color: var(--text-heading); line-height: 1.4; }
.ops-card-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 12px; }
.ops-badge { padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }

.assignee-badge { display: flex; align-items: center; gap: 6px; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-weight: 600; color: #475569; }
.dept-badge { background: #e0e7ff; color: #4338ca; }

.subtask-progress { margin-top: 10px; background: #f1f5f9; height: 6px; border-radius: 99px; overflow: hidden; position: relative; }
.subtask-progress-bar { background: var(--success); height: 100%; transition: width 0.3s ease; }
.subtask-text { font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 4px; }

/* Modal Styles */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-overlay); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
.modal-content { background: var(--bg-card); width: 100%; max-width: 600px; border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-soft); position: relative; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-heading); }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); font-family: inherit; font-size: 13px; outline: none; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

.subtask-row { display: flex; gap: 10px; margin-bottom: 10px; }
.subtask-row input { flex: 1; }
</style>

<div class="content-section active">
    <div class="section-header">
        <div>
            <h2>🎯 Internal Ops Task Board</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-top:4px;">Advanced department and user task tracking.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <button class="view-button" onclick="openAddColumnModal()" style="background:var(--bg-card);border:1px solid var(--border-card);color:var(--text-body);padding:10px 18px;border-radius:10px;font-weight:600;cursor:pointer;">+ Add Column</button>
            <?php endif; ?>
            <button class="add-button" onclick="openCreateModal()">+ Create Task</button>
        </div>
    </div>

    <div class="ops-board" id="boardContainer">
        <?php
        $columns = $pdo->query("SELECT name FROM ops_columns ORDER BY position ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach($columns as $col):
        ?>
        <div class="ops-col" data-status="<?= htmlspecialchars($col) ?>">
            <div class="ops-col-header">
                <span><?= htmlspecialchars($col) ?></span>
                <span class="ops-col-count" id="count-<?= preg_replace('/[^a-zA-Z0-9]/', '', $col) ?>">0</span>
            </div>
            <div class="ops-dropzone" id="col-<?= preg_replace('/[^a-zA-Z0-9]/', '', $col) ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-content">
        <h3 style="margin-top:0; margin-bottom:20px; font-size:18px;">Create Internal Task</h3>
        <form id="createForm" onsubmit="submitTask(event)">
            <div class="form-group">
                <label>Task Title</label>
                <input type="text" name="title" required placeholder="e.g. Q3 Financial Audit Preparation">
            </div>
            
            <div style="display:flex; gap:15px;">
                <div class="form-group" style="flex:1;">
                    <label>Assignment Type</label>
                    <select name="assigned_type" id="assignType" onchange="toggleAssignType()" required>
                        <option value="User">Specific User</option>
                        <option value="Department">Entire Department</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;" id="wrapUser">
                    <label>Assign to User</label>
                    <select name="assigned_user">
                        <option value="">-- Select User --</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?= htmlspecialchars($u['login_id']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1; display:none;" id="wrapDept">
                    <label>Assign to Department</label>
                    <select name="assigned_dept">
                        <option value="">-- Select Dept --</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d['department']) ?>"><?= htmlspecialchars($d['department']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Priority</label>
                <select name="priority">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description (Optional)</label>
                <textarea name="description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label style="display:flex; justify-content:space-between;">
                    <span>Child Subtasks</span>
                    <button type="button" onclick="addSubtaskRow()" style="background:none; border:none; color:var(--primary-color); font-weight:600; cursor:pointer; font-size:12px;">+ Add Subtask</button>
                </label>
                <div id="subtasksContainer">
                    <div class="subtask-row">
                        <input type="text" class="subtask-input" placeholder="Subtask item...">
                        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:var(--danger); cursor:pointer;">✖</button>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px;">
                <button type="button" class="cancel" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
                <button type="submit" class="submit" id="saveBtn">Save Task</button>
            </div>
        </form>
    </div>
</div>

<!-- View Task Modal (for checking off subtasks) -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <h3 id="viewTitle" style="margin-top:0; margin-bottom:10px; font-size:18px;"></h3>
        <div style="display:flex; gap:10px; margin-bottom:15px;" id="viewBadges"></div>
        
        <div style="background:#f8fafc; padding:15px; border-radius:8px; font-size:13px; color:var(--text-body); margin-bottom:20px; white-space:pre-wrap;" id="viewDesc"></div>

        <h4 style="font-size:14px; margin-bottom:10px; color:var(--text-heading);">Checklist / Subtasks</h4>
        <div id="viewSubtasks" style="display:flex; flex-direction:column; gap:8px;"></div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px;">
            <button type="button" class="cancel" onclick="document.getElementById('viewModal').style.display='none'">Close</button>
            <button type="button" class="delete-button" onclick="deleteTask()" id="delBtn">Delete Task</button>
        </div>
    </div>
</div>

<!-- Add Column Modal -->
<div class="modal-overlay" id="addColumnModal">
    <div class="modal-content" style="max-width: 400px;">
        <h3 style="margin-top:0; margin-bottom:20px; font-size:18px;">Add New Column</h3>
        <form onsubmit="submitAddColumn(event)">
            <div class="form-group">
                <label>Column Name</label>
                <input type="text" name="name" required placeholder="e.g. Waiting on Client">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px;">
                <button type="button" class="cancel" onclick="document.getElementById('addColumnModal').style.display='none'">Cancel</button>
                <button type="submit" class="submit">Save Column</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentTasks = [];
let viewingTaskId = null;

function toggleAssignType() {
    const type = document.getElementById('assignType').value;
    if (type === 'User') {
        document.getElementById('wrapUser').style.display = 'block';
        document.getElementById('wrapDept').style.display = 'none';
    } else {
        document.getElementById('wrapUser').style.display = 'none';
        document.getElementById('wrapDept').style.display = 'block';
    }
}

function addSubtaskRow() {
    const div = document.createElement('div');
    div.className = 'subtask-row';
    div.innerHTML = `
        <input type="text" class="subtask-input" placeholder="Subtask item...">
        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:var(--danger); cursor:pointer;">✖</button>
    `;
    document.getElementById('subtasksContainer').appendChild(div);
}

function openCreateModal() {
    document.getElementById('createForm').reset();
    document.getElementById('subtasksContainer').innerHTML = `
        <div class="subtask-row">
            <input type="text" class="subtask-input" placeholder="Subtask item...">
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:var(--danger); cursor:pointer;">✖</button>
        </div>
    `;
    toggleAssignType();
    document.getElementById('createModal').style.display = 'flex';
}

function loadBoard() {
    fetch('controllers/ops_task_api.php?action=list')
    .then(r=>r.json())
    .then(data => {
        if(data.success) {
            currentTasks = data.tasks;
            renderBoard();
        }
    });
}

function openAddColumnModal() {
    document.getElementById('addColumnModal').style.display = 'flex';
}

function submitAddColumn(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fetch('controllers/ops_task_api.php?action=add_column', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(data => {
        if(data.success) {
            window.location.reload(); // Reload to fetch new columns from PHP
        } else {
            alert(data.error);
        }
    });
}

function renderBoard() {
    const cols = Array.from(document.querySelectorAll('.ops-col')).map(el => el.getAttribute('data-status'));
    cols.forEach(c => {
        let safeCol = c.replace(/[^a-zA-Z0-9]/g, '');
        document.getElementById('col-' + safeCol).innerHTML = '';
        document.getElementById('count-' + safeCol).textContent = '0';
    });

    currentTasks.forEach(t => {
        let safeCol = t.status.replace(/[^a-zA-Z0-9]/g, '');
        const dropzone = document.getElementById('col-' + safeCol);
        if (!dropzone) return;

        let totalSubs = t.subtasks.length;
        let compSubs = t.subtasks.filter(s => s.is_completed == 1).length;
        let prog = totalSubs > 0 ? (compSubs / totalSubs) * 100 : 0;

        let prioColor = t.priority === 'Critical' ? '#ef4444' : (t.priority === 'High' ? '#f97316' : (t.priority === 'Medium' ? '#3b82f6' : '#10b981'));
        let prioBg = prioColor + '20';

        let assignHtml = '';
        if (t.assigned_type === 'Department') {
            assignHtml = `<div class="assignee-badge dept-badge">🏢 ${t.assigned_to || 'Unassigned'}</div>`;
        } else {
            assignHtml = `<div class="assignee-badge">👤 ${t.assignee_name || 'Unassigned'}</div>`;
        }

        let subHtml = '';
        if (totalSubs > 0) {
            subHtml = `
                <div class="subtask-progress"><div class="subtask-progress-bar" style="width:${prog}%"></div></div>
                <div class="subtask-text">☑ ${compSubs}/${totalSubs} subtasks</div>
            `;
        }

        const card = document.createElement('div');
        card.className = 'ops-card';
        card.draggable = true;
        card.dataset.id = t.id;
        card.onclick = (e) => { if(!e.target.closest('button')) openViewModal(t.id); };
        
        card.innerHTML = `
            <div class="ops-badge" style="background:${prioBg}; color:${prioColor}; margin-bottom:8px; display:inline-block;">${t.priority}</div>
            <div class="ops-card-title">${t.title}</div>
            ${subHtml}
            <div class="ops-card-meta">
                ${assignHtml}
            </div>
        `;

        card.addEventListener('dragstart', (e) => {
            card.classList.add('dragging');
            e.dataTransfer.setData('text/plain', t.id);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });

        dropzone.appendChild(card);
    });

    cols.forEach(c => {
        const colId = c.replace(/[^a-zA-Z0-9]/g, '');
        document.getElementById('count-' + colId).textContent = document.getElementById('col-' + colId).children.length;
    });
}

function openViewModal(id) {
    const t = currentTasks.find(x => x.id == id);
    if (!t) return;
    viewingTaskId = id;
    
    document.getElementById('viewTitle').textContent = t.title;
    document.getElementById('viewDesc').textContent = t.description || 'No description provided.';
    
    let prioColor = t.priority === 'Critical' ? '#ef4444' : (t.priority === 'High' ? '#f97316' : (t.priority === 'Medium' ? '#3b82f6' : '#10b981'));
    let assignHtml = t.assigned_type === 'Department' ? `🏢 ${t.assigned_to}` : `👤 ${t.assignee_name}`;
    
    document.getElementById('viewBadges').innerHTML = `
        <span class="ops-badge" style="background:${prioColor}20; color:${prioColor};">${t.priority}</span>
        <span class="assignee-badge" style="padding:4px 8px; font-size:11px;">${assignHtml}</span>
    `;

    const subsBox = document.getElementById('viewSubtasks');
    if (t.subtasks.length === 0) {
        subsBox.innerHTML = '<div style="color:var(--text-muted); font-size:12px;">No subtasks.</div>';
    } else {
        subsBox.innerHTML = t.subtasks.map(s => `
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; background:#fff; border:1px solid var(--border-card); padding:10px 12px; border-radius:8px;">
                <input type="checkbox" onchange="toggleSubtask(${s.id}, this.checked)" ${s.is_completed == 1 ? 'checked' : ''} style="width:16px; height:16px;">
                <span style="font-size:13px; font-weight:500; ${s.is_completed == 1 ? 'text-decoration:line-through; color:var(--text-muted);' : 'color:var(--text-heading);'}">${s.title}</span>
            </label>
        `).join('');
    }

    document.getElementById('viewModal').style.display = 'flex';
}

function toggleSubtask(id, isCompleted) {
    const fd = new FormData();
    fd.append('subtask_id', id);
    fd.append('is_completed', isCompleted);
    fetch('controllers/ops_task_api.php?action=toggle_subtask', {method:'POST', body:fd})
    .then(() => loadBoard());
}

function submitTask(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.textContent = 'Saving...';
    btn.disabled = true;

    const fd = new FormData(e.target);
    if (fd.get('assigned_type') === 'User') fd.set('assigned_to', fd.get('assigned_user'));
    else fd.set('assigned_to', fd.get('assigned_dept'));

    const subs = [];
    document.querySelectorAll('.subtask-input').forEach(inp => { if(inp.value.trim()) subs.push(inp.value.trim()); });
    fd.append('subtasks', JSON.stringify(subs));

    fetch('controllers/ops_task_api.php?action=create', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(data => {
        if(data.success) {
            document.getElementById('createModal').style.display = 'none';
            loadBoard();
        } else {
            alert(data.error);
        }
    }).finally(() => {
        btn.textContent = 'Save Task';
        btn.disabled = false;
    });
}

function deleteTask() {
    if(!confirm("Are you sure you want to delete this task?")) return;
    const fd = new FormData(); fd.append('id', viewingTaskId);
    fetch('controllers/ops_task_api.php?action=delete', {method:'POST', body:fd})
    .then(() => {
        document.getElementById('viewModal').style.display = 'none';
        loadBoard();
    });
}

// Drag and Drop Logic
document.querySelectorAll('.ops-dropzone').forEach(zone => {
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', () => {
        zone.classList.remove('drag-over');
    });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const id = e.dataTransfer.getData('text/plain');
        const newStatus = zone.id.replace('col-', '').replace(/([a-z])([A-Z])/g, '$1 $2'); // Quick un-camelcase if needed, but actually we kept dashes or exact status?
        // Wait, the id is 'col-InProgress'. Let's find the parent ops-col data-status.
        const parentCol = zone.closest('.ops-col');
        const exactStatus = parentCol.getAttribute('data-status');

        const card = document.querySelector(`[data-id="${id}"]`);
        zone.appendChild(card); // instant visual update

        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', exactStatus);
        fetch('controllers/ops_task_api.php?action=update_status', {method:'POST', body:fd})
        .then(() => loadBoard());
    });
});

document.addEventListener('DOMContentLoaded', loadBoard);
</script>

<?php require_once 'includes/footer.php'; ?>

