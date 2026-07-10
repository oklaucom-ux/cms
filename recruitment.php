<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'manage_recruitment');

if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>HR privileges required.</p></div>");
}
?>

<style>
.kanban-board { display:flex; gap:20px; overflow-x:auto; padding-bottom:20px; align-items:flex-start; min-height:600px; }
.kanban-col { background:var(--bg-card); border-radius:12px; width:300px; flex-shrink:0; border:1px solid var(--border-card); display:flex; flex-direction:column; max-height:80vh; }
.kanban-header { padding:15px; border-bottom:1px solid var(--border-card); font-weight:700; display:flex; justify-content:space-between; align-items:center; }
.kanban-dropzone { padding:15px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:12px; min-height:200px; }
.k-card { background:var(--bg-main); border:1px solid var(--border-card); border-radius:8px; padding:12px; cursor:grab; box-shadow:var(--shadow-sm); transition:transform .1s, box-shadow .1s; }
.k-card:active { cursor:grabbing; transform:scale(1.02); box-shadow:var(--shadow-md); }
.k-card-title { font-weight:600; font-size:14px; margin-bottom:4px; color:var(--text-heading); }
.k-card-meta { font-size:11px; color:var(--text-muted); display:flex; gap:10px; margin-top:8px; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>🎯 Recruitment & ATS Pipeline</h2>
        <button class="add-button" onclick="document.getElementById('applicantModal').style.display='flex'" style="background:#4f46e5;">+ Add Applicant</button>
    </div>

    <div class="kanban-board">
        <!-- Columns -->
        <?php
        $cols = [
            ['id' => 'Applied', 'title' => '📥 Applied', 'color' => '#64748b'],
            ['id' => 'Screening', 'title' => '🔎 Screening', 'color' => '#f59e0b'],
            ['id' => 'Interview', 'title' => '🗣️ Interview', 'color' => '#3b82f6'],
            ['id' => 'Offered', 'title' => '🎉 Offered', 'color' => '#10b981'],
            ['id' => 'Rejected', 'title' => '❌ Rejected', 'color' => '#ef4444']
        ];
        foreach($cols as $c):
        ?>
        <div class="kanban-col">
            <div class="kanban-header" style=" border-top-left-radius:12px; border-top-right-radius:12px;">
                <span style="color:var(--text-heading);"><?= $c['title'] ?></span>
                <span style="background:var(--bg-main); padding:2px 8px; border-radius:12px; font-size:12px; color:var(--text-muted);" id="count-<?= $c['id'] ?>">0</span>
            </div>
            <div class="kanban-dropzone" id="zone-<?= $c['id'] ?>" ondragover="allowDrop(event)" ondrop="drop(event, '<?= $c['id'] ?>')">
                <!-- Cards injected here via JS -->
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Applicant Modal -->
<div class="modal" id="applicantModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Add New Candidate</h2>
        <form onsubmit="saveApplicant(event)">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Candidate Name</label>
            <input type="text" id="app_name" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; outline:none;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Email</label>
            <input type="email" id="app_email" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; outline:none;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Phone</label>
            <input type="text" id="app_phone" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; outline:none;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Role Applied</label>
            <input type="text" id="app_role" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; outline:none;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Resume (PDF)</label>
            <input type="file" id="app_resume" accept=".pdf" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px; outline:none; background:#f8fafc;">
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('applicantModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Save Applicant</button>
            </div>
        </form>
    </div>
</div>

<script>
let globalApplicants = [];

function loadBoard() {
    fetch('controllers/recruitment_api.php?action=fetch_applicants')
    .then(r => r.json())
    .then(res => {
        globalApplicants = res.data;
        renderBoard();
    });
}

function renderBoard() {
    document.querySelectorAll('.kanban-dropzone').forEach(z => z.innerHTML = '');
    let counts = { 'Applied':0, 'Screening':0, 'Interview':0, 'Offered':0, 'Rejected':0 };
    
    globalApplicants.forEach(app => {
        let zone = document.getElementById('zone-' + app.status);
        if(!zone) return;
        
        counts[app.status]++;
        
        let card = document.createElement('div');
        card.className = 'k-card';
        card.draggable = true;
        card.dataset.id = app.id;
        card.ondragstart = drag;
        
        let metaHtml = `<span>📧 ${escapeHtml(app.email)}</span>`;
        if (app.resume_path) {
            metaHtml += ` <a href="${escapeHtml(app.resume_path)}" target="_blank" style="color:#4f46e5; text-decoration:none; margin-left:10px;">📄 Resume</a>`;
        }
        if (app.status === 'Offered') {
            metaHtml += ` <div style="margin-top:10px;"><button onclick="convertApplicant(${app.id})" style="background:#10b981; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer; width:100%;">Hire & Convert to Employee</button></div>`;
        }
        
        card.innerHTML = `
            <div class="k-card-title">${escapeHtml(app.name)}</div>
            <div style="font-size:12px; color:#475569;">💼 ${escapeHtml(app.role_applied)}</div>
            <div class="k-card-meta" style="flex-direction:column; gap:4px;">${metaHtml}
            </div>
        `;
        zone.appendChild(card);
    });
    
    for (let status in counts) {
        let countEl = document.getElementById('count-' + status);
        if(countEl) countEl.innerText = counts[status];
    }
}

function escapeHtml(str) {
    if(!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function saveApplicant(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'create_applicant');
    fd.append('name', document.getElementById('app_name').value);
    fd.append('email', document.getElementById('app_email').value);
    fd.append('phone', document.getElementById('app_phone').value);
    fd.append('role_applied', document.getElementById('app_role').value);
    
    let resumeFile = document.getElementById('app_resume').files[0];
    if (resumeFile) {
        fd.append('resume', resumeFile);
    }
    
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    fetch('controllers/recruitment_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            document.getElementById('applicantModal').style.display = 'none';
            e.target.reset();
            loadBoard();
        } else {
            alert(res.message);
        }
    });
}

function convertApplicant(id) {
    if(!confirm("Are you sure you want to convert this applicant into a full Employee account?")) return;
    
    let fd = new FormData();
    fd.append('action', 'convert_applicant');
    fd.append('id', id);
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    fetch('controllers/recruitment_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            alert('Successfully converted to Employee (Pending Docs)! They can now log in.');
            loadBoard();
        } else {
            alert(res.message);
        }
    });
}

// Drag & Drop
let draggedCard = null;
function drag(ev) {
    draggedCard = ev.target;
    ev.dataTransfer.setData("text", ev.target.dataset.id);
    ev.target.style.opacity = '0.5';
}

document.addEventListener('dragend', function(ev) {
    if(ev.target.classList.contains('k-card')) {
        ev.target.style.opacity = '1';
    }
});

function allowDrop(ev) {
    ev.preventDefault();
}

function drop(ev, newStatus) {
    ev.preventDefault();
    if(draggedCard) {
        let zone = document.getElementById('zone-' + newStatus);
        zone.appendChild(draggedCard);
        
        let fd = new FormData();
        fd.append('action', 'update_status');
        fd.append('id', draggedCard.dataset.id);
        fd.append('status', newStatus);
        
        let csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
        
        fetch('controllers/recruitment_api.php', { method: 'POST', body: fd })
        .then(() => loadBoard());
    }
}

document.addEventListener('DOMContentLoaded', loadBoard);
</script>

<?php require_once 'includes/footer.php'; ?>
