<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_tasks');

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

// Fetch active projects for the dropdown
$projects = $pdo->query("SELECT id, name, status, deadline, ai_forecast FROM projects WHERE status != 'Completed'")->fetchAll(PDO::FETCH_ASSOC);

$defaultProjectId = $projects[0]['id'] ?? 0;
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<style>
/* Glassmorphic Columns */
.kanban-board { display: flex; gap: 24px; min-height: calc(100vh - 250px); overflow-x: auto; padding-bottom: 20px; }
.kanban-col { 
    flex: 1; 
    min-width: 300px; 
    background: rgba(248, 250, 252, 0.7); 
    backdrop-filter: blur(10px);
    border-radius: 12px; 
    padding: 16px; 
    display: flex; 
    flex-direction: column; 
    gap: 16px; 
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.kanban-col-header { font-weight: 800; font-size: 16px; color: var(--text-heading, #1e293b); padding-bottom: 12px; border-bottom: 2px solid rgba(226, 232, 240, 0.8); display: flex; justify-content: space-between; align-items: center; }
.kanban-col-count { background: #e2e8f0; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; color: #475569; }

.kanban-dropzone { flex: 1; display: flex; flex-direction: column; gap: 12px; min-height: 150px; padding-bottom: 30px; }

/* Glassmorphic Cards */
.kanban-card { 
    background: rgba(255, 255, 255, 0.9); 
    backdrop-filter: blur(5px);
    border-radius: 10px; 
    padding: 16px; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.04); 
    cursor: grab; 
    border: 1px solid rgba(226, 232, 240, 0.8); 
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
    position: relative;
}
.kanban-card:hover { transform: translateY(-3px); box-shadow: 0 8px 12px rgba(0,0,0,0.08); border-color: #cbd5e1; }
.kanban-card:active { cursor: grabbing; transform: scale(0.98); }
.kanban-card.sortable-ghost { opacity: 0.4; background: #f1f5f9; border: 2px dashed #94a3b8; }
.kanban-card.sortable-drag { cursor: grabbing !important; box-shadow: 0 10px 20px rgba(0,0,0,0.15); transform: rotate(2deg); }

.kanban-card-title { font-weight: 600; font-size: 14px; margin-bottom: 8px; color: #0f172a; line-height: 1.4; }
.kanban-card-desc { font-size: 12px; color: #64748b; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.kanban-card-meta { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px; }

/* Badges & Assignee */
.priority-badge { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.priority-High { background: #fee2e2; color: #ef4444; }
.priority-Medium { background: #fef3c7; color: #d97706; }
.priority-Low { background: #d1fae5; color: #10b981; }

.kanban-card-assignee { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; font-weight: 500; }
.assignee-avatar { width: 24px; height: 24px; background: #e2e8f0; color: #475569; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; }
.due-date { font-size: 11px; color: #94a3b8; font-weight: 500; display: flex; align-items: center; gap: 4px; }

.ai-forecast-panel { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border-radius: 12px; padding: 20px; color: white; margin-bottom: 24px; display: flex; flex-direction: column; gap: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
.ai-forecast-header { display: flex; justify-content: space-between; align-items: center; }
.ai-forecast-title { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.ai-forecast-content { font-size: 14px; line-height: 1.5; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; }
.ai-risk-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
.risk-Low { background: rgba(16, 185, 129, 0.2); color: #a7f3d0; border: 1px solid #10b981; }
.risk-Medium { background: rgba(245, 158, 11, 0.2); color: #fde68a; border: 1px solid #f59e0b; }
.risk-High { background: rgba(239, 68, 68, 0.2); color: #fecaca; border: 1px solid #ef4444; }

.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.toolbar select { padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; min-width: 250px; }
.btn-ai { background: #1e293b; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 6px; transition: background 0.2s; }
.btn-ai:hover { background: #0f172a; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>Agile Project Management</h2>
    </div>

    <div class="toolbar">
        <div>
            <label style="font-weight: 600; margin-right: 10px; color: #475569;">Select Project:</label>
            <select id="projectSelector" onchange="loadBoard()">
                <?php foreach($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" data-forecast="<?= htmlspecialchars($p['ai_forecast'] ?? '') ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['status'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if($isAdmin): ?>
        <button class="btn-ai" onclick="generateForecast()" id="aiBtn">
            ✨ AI Sprint Forecast
        </button>
        <?php endif; ?>
    </div>

    <div class="ai-forecast-panel" id="aiForecastPanel" style="display: none;">
        <div class="ai-forecast-header">
            <div class="ai-forecast-title">✨ OpenAI Sprint Analysis</div>
            <div class="ai-risk-badge" id="aiRiskBadge">Risk: Unknown</div>
        </div>
        <div class="ai-forecast-content">
            <div style="margin-bottom: 8px;"><strong>Forecast:</strong> <span id="aiForecastText">...</span></div>
            <div><strong>Recommendation:</strong> <span id="aiRecommendationText">...</span></div>
        </div>
    </div>

    <div class="kanban-board">
        <!-- Backlog -->
        <div class="kanban-col" data-status="Backlog">
            <div class="kanban-col-header">
                <span>Backlog</span>
                <span class="kanban-col-count" id="count-Backlog">0</span>
            </div>
            <div class="kanban-dropzone" id="col-Backlog"></div>
        </div>

        <!-- In Progress -->
        <div class="kanban-col" data-status="In Progress">
            <div class="kanban-col-header">
                <span>In Progress</span>
                <span class="kanban-col-count" id="count-InProgress">0</span>
            </div>
            <div class="kanban-dropzone" id="col-In Progress"></div>
        </div>

        <!-- QA -->
        <div class="kanban-col" data-status="QA">
            <div class="kanban-col-header">
                <span>QA / Review</span>
                <span class="kanban-col-count" id="count-QA">0</span>
            </div>
            <div class="kanban-dropzone" id="col-QA"></div>
        </div>

        <!-- Done -->
        <div class="kanban-col" data-status="Done">
            <div class="kanban-col-header">
                <span>Done</span>
                <span class="kanban-col-count" id="count-Done">0</span>
            </div>
            <div class="kanban-dropzone" id="col-Done"></div>
        </div>
    </div>
</div>

<script>
let currentProjectId = <?= $defaultProjectId ?>;

document.addEventListener('DOMContentLoaded', () => {
    if(currentProjectId > 0) {
        loadBoard();
    }
});

function loadBoard() {
    const sel = document.getElementById('projectSelector');
    currentProjectId = sel.value;
    
    // Check if there is existing AI forecast
    const opt = sel.options[sel.selectedIndex];
    const forecastStr = opt.getAttribute('data-forecast');
    if(forecastStr && forecastStr.trim() !== '') {
        try {
            const data = JSON.parse(forecastStr);
            showForecast(data);
        } catch(e) {
            document.getElementById('aiForecastPanel').style.display = 'none';
        }
    } else {
        document.getElementById('aiForecastPanel').style.display = 'none';
    }

    // Fetch Board tasks
    fetch(`controllers/kanban_api.php?action=get_board&project_id=${currentProjectId}`)
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            renderBoard(res.tasks);
        } else {
            alert(res.message);
        }
    });
}

function renderBoard(tasks) {
    const columns = {
        'Backlog': document.getElementById('col-Backlog'),
        'In Progress': document.getElementById('col-In Progress'),
        'QA': document.getElementById('col-QA'),
        'Done': document.getElementById('col-Done')
    };

    // Clear columns
    Object.values(columns).forEach(col => col.innerHTML = '');

    tasks.forEach(task => {
        let status = task.status;
        if (!columns[status]) status = 'Backlog';

        const priority = task.priority || 'Medium';
        const assignee = task.assignee_name || 'Unassigned';
        const avatarInitial = assignee !== 'Unassigned' ? assignee.charAt(0).toUpperCase() : '?';
        const desc = task.description ? `<div class="kanban-card-desc">${task.description}</div>` : '';
        const dueDateHtml = task.due_date ? `<div class="due-date">📅 ${new Date(task.due_date).toLocaleDateString()}</div>` : '';

        const card = document.createElement('div');
        card.className = 'kanban-card';
        card.dataset.id = task.id;
        card.innerHTML = `
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span class="priority-badge priority-${priority}">${priority}</span>
                ${dueDateHtml}
            </div>
            <div class="kanban-card-title">${task.title}</div>
            ${desc}
            <div class="kanban-card-meta">
                <div class="kanban-card-assignee">
                    <div class="assignee-avatar">${avatarInitial}</div>
                    ${assignee}
                </div>
            </div>
        `;

        columns[status].appendChild(card);
    });

    updateCounts();
    initSortable();
}

function updateCounts() {
    ['Backlog', 'In Progress', 'QA', 'Done'].forEach(status => {
        const dropzone = document.getElementById('col-' + status);
        const count = dropzone.querySelectorAll('.kanban-card').length;
        document.getElementById('count-' + status.replace(' ', '')).textContent = count;
    });
}

// DRAG AND DROP SETUP WITH SORTABLEJS
let sortables = [];

function initSortable() {
    // Destroy existing instances if any
    sortables.forEach(s => s.destroy());
    sortables = [];

    document.querySelectorAll('.kanban-dropzone').forEach(dropzone => {
        const s = new Sortable(dropzone, {
            group: 'kanban', // set both lists to same group
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            easing: "cubic-bezier(0.4, 0.0, 0.2, 1)",
            onEnd: function (evt) {
                updateCounts();
                const itemEl = evt.item;  // dragged HTMLElement
                const toList = evt.to;    // target list
                
                // Find status from parent column dataset
                const newStatus = toList.closest('.kanban-col').dataset.status;
                const taskId = itemEl.dataset.id;

                // Save to DB (only if status changed)
                if (evt.from !== evt.to) {
                    let formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('task_id', taskId);
                    formData.append('status', newStatus);
                    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                    
                    fetch('controllers/kanban_api.php', {
                        method: 'POST',
                        body: formData
                    });
                }
            }
        });
        sortables.push(s);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
