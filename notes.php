<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_notes');

// Allow any authenticated user to view notes (we filter by branch/user in the query)
$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
$loginId = $_SESSION['login_id'];
$myBranch = $pdo->query("SELECT branch_id FROM users WHERE login_id = '{$loginId}'")->fetchColumn() ?: 'Global HQ';

$wsFilter = "";
$wsParams = [];
if (isset($_SESSION['active_workspace_id'])) {
    $wsFilter = " AND (n.workspace_id = ? OR n.workspace_id IS NULL)";
    $wsParams[] = $_SESSION['active_workspace_id'];
}

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT n.*, COALESCE(u.name, sa.name) as author_name FROM notes n LEFT JOIN users u ON n.created_by = u.login_id LEFT JOIN super_admins sa ON n.created_by = sa.username WHERE 1=1 {$wsFilter} ORDER BY n.is_pinned DESC, n.created_at DESC");
    $stmt->execute($wsParams);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Users can see their own notes, plus pinned/public notes from their branch
    $params = array_merge([$loginId, $myBranch], $wsParams);
    $stmt = $pdo->prepare("SELECT n.*, COALESCE(u.name, sa.name) as author_name FROM notes n LEFT JOIN users u ON n.created_by = u.login_id LEFT JOIN super_admins sa ON n.created_by = sa.username
        WHERE ((n.created_by = ?) OR (u.branch_id = ? AND n.is_pinned = 1)) {$wsFilter}
        ORDER BY n.is_pinned DESC, n.created_at DESC");
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch active projects for linking
$projects = $pdo->query("SELECT id, name FROM projects WHERE status != 'Completed'")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>Workspace Notes & Drafts</h2>
        <button class="add-button" onclick="openNoteModal()">+ New Note</button>
    </div>

    <?php if(isset($_SESSION['flash_message'])): ?>
        <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <?php unset($_SESSION['flash_message']); ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php foreach($notes as $note): 
            $bg = $note['color'] ?? '#ffffff';
            // Adjust border for dark notes
            $border = ($bg === '#ffffff') ? 'var(--border-card)' : 'rgba(0,0,0,0.1)';
        ?>
        <div style="background:<?= htmlspecialchars($bg) ?>; border-radius:12px; padding:20px; box-shadow:var(--shadow-soft); border:1px solid <?= $border ?>; position:relative; display:flex; flex-direction:column; min-height:200px;">
            <?php if($note['is_pinned']): ?>
                <div style="position:absolute; top:-10px; right:10px; font-size:24px; filter:drop-shadow(0 2px 2px rgba(0,0,0,0.2));" title="Pinned Note">📌</div>
            <?php endif; ?>
            
            <h3 style="font-size:18px; color:var(--text-heading); margin-top:0; margin-bottom:8px; padding-right:24px;">
                <?= htmlspecialchars($note['title'] ?: 'Untitled Note') ?>
            </h3>
            
            <?php if($note['project_id']): 
                $pName = 'Unknown Project';
                foreach($projects as $p) { if($p['id'] == $note['project_id']) $pName = $p['name']; }
            ?>
                <div style="font-size:12px; font-weight:bold; color:var(--primary-color); background:rgba(79, 70, 229, 0.1); padding:2px 8px; border-radius:12px; width:fit-content; margin-bottom:12px;">
                    📁 <?= htmlspecialchars($pName) ?>
                </div>
            <?php endif; ?>

            <div style="font-size:14px; color:var(--text-body); line-height:1.5; margin-bottom:20px; flex:1; white-space:pre-wrap; word-break:break-word;">
                <?= htmlspecialchars($note['content']) ?>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid rgba(0,0,0,0.05); padding-top:12px; margin-top:auto;">
                <div style="font-size:11px; color:var(--text-muted);">
                    <?= date('M j, Y', strtotime($note['created_at'])) ?><br>
                    <span style="font-weight:600;">@<?= htmlspecialchars($note['author_name']) ?></span>
                </div>
                
                <?php if($isAdmin || $note['created_by'] === $loginId): ?>
                <div style="display:flex; gap:6px;">
                    <button onclick="editNote(<?= htmlspecialchars(json_encode($note), ENT_QUOTES, 'UTF-8') ?>)" style="background:rgba(255,255,255,0.7); border:1px solid rgba(0,0,0,0.1); padding:6px; border-radius:6px; cursor:pointer;" title="Edit">✏️</button>
                    
                    <form method="POST" action="controllers/delete_note.php" style="margin:0;" onsubmit="return confirm('Delete this note forever?');">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id" value="<?= $note['id'] ?>">
                        <button type="submit" style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.2); padding:6px; border-radius:6px; cursor:pointer;" title="Delete">🗑️</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($notes)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:60px 20px; background:var(--bg-card); border-radius:12px; border:2px dashed var(--border-card);">
                <div style="font-size:48px; margin-bottom:16px;">📝</div>
                <h3 style="color:var(--text-heading); margin-bottom:8px;">No Notes Yet</h3>
                <p style="color:var(--text-muted);">Jot down ideas, project details, or private thoughts.</p>
                <button class="add-button" style="margin-top:16px;" onclick="openNoteModal()">Create First Note</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="noteModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeNoteModal()">&times;</span>
        <h2 id="modalTitle">Write Note</h2>
        <form id="modalForm" method="POST" action="controllers/save_note.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div id="modalFields"></div>
            <div class="form-actions" style="margin-top:20px;">
                <button type="button" class="cancel" onclick="closeNoteModal()">Cancel</button>
                <button type="submit" class="submit">Save Note</button>
            </div>
        </form>
    </div>
</div>

<script>
function openNoteModal(d = null) {
    document.getElementById('modalTitle').textContent = d ? "Edit Note" : "Write Note";
    
    let html = `<input type="hidden" name="id" value="${d ? d.id : ''}">`;
    
    html += `<div class="form-group"><label>Title (Optional)</label><input type="text" name="title" value="${d ? d.title : ''}" placeholder="E.g. Meeting Minutes"></div>`;
    
    html += `<div class="form-group"><label>Content</label><textarea name="content" required rows="6" placeholder="Write something...">${d ? d.content : ''}</textarea></div>`;
    
    // Project Linker
    let pList = <?= json_encode($projects) ?>;
    html += `<div class="form-group"><label>Link to Project (Optional)</label><select name="project_id">
              <option value="">-- No Project --</option>`;
    pList.forEach(p => {
        let sel = (d && d.project_id == p.id) ? 'selected' : '';
        html += `<option value="${p.id}" ${sel}>${p.name}</option>`;
    });
    html += `</select></div>`;

    html += `<div style="display:flex; gap:20px; align-items:center; margin-top:20px;">`;
    
    // Color Picker
    html += `<div style="display:flex; flex-direction:column; gap:8px;">
                <label style="font-size:13px; font-weight:600; color:var(--text-heading);">Note Color</label>
                <div style="display:flex; gap:8px;">`;
    const colors = ['#ffffff', '#fef3c7', '#dcfce7', '#e0e7ff', '#fce7f3'];
    colors.forEach(c => {
        let chk = (d && d.color === c) || (!d && c === '#ffffff') ? 'checked' : '';
        html += `<label style="cursor:pointer; position:relative;">
                    <input type="radio" name="color" value="${c}" ${chk} style="opacity:0; position:absolute;">
                    <div style="width:32px; height:32px; border-radius:50%; background:${c}; border:2px solid ${chk ? 'var(--primary-color)' : 'rgba(0,0,0,0.1)'};"></div>
                 </label>`;
    });
    html += `   </div>
             </div>`;
             
    // Pin Checkbox
    let pinChk = (d && parseInt(d.is_pinned)) ? 'checked' : '';
    html += `<label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:var(--text-heading); margin-top:16px;">
                <input type="checkbox" name="is_pinned" value="1" ${pinChk}> 📌 Pin Note
             </label>`;
             
    html += `</div>`;

    document.getElementById('modalFields').innerHTML = html;
    
    // Add click listeners to custom radio buttons to update borders
    setTimeout(() => {
        const radios = document.querySelectorAll('input[name="color"]');
        radios.forEach(r => {
            r.addEventListener('change', (e) => {
                radios.forEach(other => {
                    other.nextElementSibling.style.borderColor = 'rgba(0,0,0,0.1)';
                });
                e.target.nextElementSibling.style.borderColor = 'var(--primary-color)';
            });
        });
    }, 50);

    document.getElementById('noteModal').style.display = 'block';
}

function editNote(d) { openNoteModal(d); }
function closeNoteModal() { document.getElementById('noteModal').style.display = 'none'; }
</script>

<?php require_once 'includes/footer.php'; ?>
