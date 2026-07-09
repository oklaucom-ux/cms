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
<style>
.kanban-board { display: flex; gap: 24px; min-height: calc(100vh - 250px); overflow-x: auto; padding-bottom: 20px; }
.kanban-col { flex: 1; min-width: 280px; background: var(--table-header, #f8fafc); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 16px; border: 1px solid var(--border-card, #e2e8f0); }
.kanban-col-header { font-weight: 700; font-size: 16px; color: var(--text-heading, #1e293b); padding-bottom: 12px; border-bottom: 2px solid var(--border-card, #e2e8f0); display: flex; justify-content: space-between; align-items: center; }
.kanban-col-count { background: var(--border-card, #e2e8f0); padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; color: var(--text-body, #475569); }

.kanban-dropzone { flex: 1; display: flex; flex-direction: column; gap: 16px; min-height: 100px; }
.kanban-dropzone.drag-over { background: rgba(59, 130, 246, 0.05); outline: 2px dashed #3b82f6; border-radius: 8px; }

.kanban-card { background: var(--bg-card, #ffffff); border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: grab; border: 1px solid var(--border-card, #e2e8f0); transition: transform 0.2s, box-shadow 0.2s; }
.kanban-card:active { cursor: grabbing; transform: scale(0.98); }
.kanban-card.dragging { opacity: 0.5; }
.kanban-card-title { font-weight: 600; font-size: 14px; margin-bottom: 8px; color: var(--text-heading, #1e293b); }
.kanban-card-assignee { font-size: 12px; color: #64748b; font-weight: 500; }

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

        const card = document.createElement('div');
        card.className = 'kanban-card';
        card.draggable = true;
        card.dataset.id = task.id;
        card.innerHTML = `
            <div class="kanban-card-title">${task.title}</div>
            <div class="kanban-card-assignee">@${task.assignee_name || 'unassigned'}</div>
        `;
        
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);

        columns[status].appendChild(card);
    });

    updateCounts();
}

function updateCounts() {
    ['Backlog', 'In Progress', 'QA', 'Done'].forEach(status => {
        const dropzone = document.getElementById('col-' + status);
        const count = dropzone.querySelectorAll('.kanban-card').length;
        document.getElementById('count-' + status.replace(' ', '')).textContent = count;
    });
}

function generateForecast() {
    const btn = document.getElementById('aiBtn');
    btn.innerHTML = '⏳ Analyzing...';
    btn.disabled = true;

    let formData = new FormData();
    formData.append('action', 'generate_forecast');
    formData.append('project_id', currentProjectId);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    fetch('controllers/kanban_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btn.innerHTML = '✨ AI Sprint Forecast';
        btn.disabled = false;
        
        if(res.status === 'success') {
            showForecast(res.data);
            // Update the option's dataset so it persists on dropdown toggle
            const sel = document.getElementById('projectSelector');
            sel.options[sel.selectedIndex].setAttribute('data-forecast', JSON.stringify(res.data));
        } else {
            alert(res.message || 'Error connecting to AI');
        }
    })
    .catch(() => {
        btn.innerHTML = '✨ AI Sprint Forecast';
        btn.disabled = false;
        alert('Request failed');
    });
}

function showForecast(data) {
    const panel = document.getElementById('aiForecastPanel');
    const riskBadge = document.getElementById('aiRiskBadge');
    
    document.getElementById('aiForecastText').textContent = data.forecast;
    document.getElementById('aiRecommendationText').textContent = data.recommendation;
    
    riskBadge.textContent = 'Risk: ' + data.risk_level;
    riskBadge.className = 'ai-risk-badge risk-' + data.risk_level;
    
    panel.style.display = 'flex';
}

// DRAG AND DROP SETUP
let draggedCard = null;

function handleDragStart(e) {
    draggedCard = this;
    setTimeout(() => this.classList.add('dragging'), 0);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    draggedCard = null;
    updateCounts();
}

document.querySelectorAll('.kanban-col').forEach(col => {
    col.addEventListener('dragover', e => {
        e.preventDefault();
        col.querySelector('.kanban-dropzone').classList.add('drag-over');
    });

    col.addEventListener('dragleave', e => {
        col.querySelector('.kanban-dropzone').classList.remove('drag-over');
    });

    col.addEventListener('drop', e => {
        e.preventDefault();
        const dropzone = col.querySelector('.kanban-dropzone');
        dropzone.classList.remove('drag-over');
        
        if (draggedCard) {
            dropzone.appendChild(draggedCard);
            const newStatus = col.dataset.status;
            const taskId = draggedCard.dataset.id;
            
            // Save to DB
            let formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('task_id', taskId);
            formData.append('status', newStatus);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            
            fetch('controllers/kanban_api.php', {
                method: 'POST',
                body: formData
            });
            
            updateCounts();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
