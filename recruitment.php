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
