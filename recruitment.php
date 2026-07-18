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
.kanban-board { display:flex; gap:24px; overflow-x:auto; padding-bottom:24px; align-items:flex-start; min-height:650px; scroll-behavior: smooth; }
.kanban-col { 
    background:rgba(248, 250, 252, 0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    border-radius:20px; width:320px; flex-shrink:0; border:1px solid rgba(0,0,0,0.05); 
    display:flex; flex-direction:column; max-height:85vh; box-shadow: 0 10px 30px rgba(0,0,0,0.03);
}
.kanban-header { 
    padding:18px 20px; border-bottom:1px solid rgba(0,0,0,0.05); font-weight:800; font-size:15px;
    display:flex; justify-content:space-between; align-items:center; background: rgba(255,255,255,0.5);
    border-top-left-radius:20px; border-top-right-radius:20px;
}
.kanban-dropzone { padding:16px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:16px; min-height:300px; }
.k-card { 
    background:#ffffff; border:1px solid rgba(0,0,0,0.05); border-radius:12px; padding:16px; 
    cursor:grab; box-shadow:0 4px 12px rgba(0,0,0,0.03); transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
}
.k-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.06); border-color: rgba(79, 70, 229, 0.3); }
.k-card:active { cursor:grabbing; transform:scale(0.98); box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.k-card-title { font-weight:700; font-size:15px; margin-bottom:6px; color:var(--text-heading); }
.k-card-meta { font-size:12px; color:var(--text-muted); display:flex; gap:10px; margin-top:12px; flex-direction:column; }
</style>

<div class="content-section active">
    <div class="section-header">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg, #4f46e5, #4338ca); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 20px rgba(79,70,229,0.3);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <div>
                <h2 style="margin-bottom:4px; font-size:24px; font-weight:800; color:var(--text-heading);">Recruitment & ATS Pipeline</h2>
                <p style="color:var(--text-muted); font-size:15px;">Track and manage candidates through the hiring pipeline.</p>
            </div>
        </div>
        <button class="add-button" onclick="document.getElementById('applicantModal').style.display='flex'" style="background:linear-gradient(135deg, #4f46e5, #4338ca); box-shadow:0 4px 12px rgba(79,70,229,0.3); border-radius:10px; font-weight:700; padding:10px 20px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; margin-bottom:-4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Applicant
        </button>
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
