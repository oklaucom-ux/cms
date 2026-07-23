<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_workspaces');

// Fetch all workspaces user is a member of (or all if admin)
$workspaces = [];
if (in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    $stmt = $pdo->query("SELECT w.*, (SELECT COUNT(*) FROM workspace_members WHERE workspace_id = w.id) as member_count FROM workspaces w ORDER BY w.name ASC");
    $workspaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT w.*, (SELECT COUNT(*) FROM workspace_members WHERE workspace_id = w.id) as member_count FROM workspaces w JOIN workspace_members wm ON w.id = wm.workspace_id WHERE wm.user_id = ? ORDER BY w.name ASC");
    $stmt->execute([$_SESSION['login_id']]);
    $workspaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="content-section active">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0; color: var(--text-heading); font-size: 24px; font-weight: 700;">Dedicated Workspaces</h2>
            <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 14px;">Organize your company into distinct departments, teams, or client portals.</p>
        </div>
        <?php if (hasPermission($pdo, 'manage_settings') || in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
        <button onclick="openWorkspaceModal()" class="add-button" style="background: var(--primary-color); color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Create Workspace
        </button>
        <?php endif; ?>
    </div>

    <?php if (count($workspaces) === 0): ?>
        <div style="text-align: center; padding: 60px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-card);">
            <i class="fas fa-layer-group" style="font-size: 48px; color: var(--text-muted); opacity: 0.5; margin-bottom: 16px;"></i>
            <h3 style="margin: 0 0 8px 0; color: var(--text-heading);">No Workspaces Found</h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">You aren't a member of any workspaces yet.</p>
            <?php if (in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <button onclick="openWorkspaceModal()" class="add-button" style="background: var(--primary-color); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Create the First Workspace</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
            <?php foreach($workspaces as $w): ?>
                <div style="background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; padding: 20px; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <h3 style="margin: 0; font-size: 18px; color: var(--text-heading); font-weight: 700;"><?= htmlspecialchars($w['name']) ?></h3>
                        <?php if (isset($_SESSION['active_workspace_id']) && $_SESSION['active_workspace_id'] == $w['id']): ?>
                            <span style="font-size: 11px; padding: 4px 8px; border-radius: 12px; background: #dcfce7; color: #166534; font-weight: 700;">ACTIVE</span>
                        <?php endif; ?>
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); margin: 0 0 16px 0; min-height: 38px;">
                        <?= htmlspecialchars($w['description']) ?: 'No description provided.' ?>
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-card); padding-top: 16px; margin-top: 16px;">
                        <div style="font-size: 13px; color: var(--text-muted);">
                            <i class="fas fa-users"></i> <?= $w['member_count'] ?> Member(s)
                        </div>
                        <button onclick="switchWorkspace(<?= $w['id'] ?>)" style="background: <?= (isset($_SESSION['active_workspace_id']) && $_SESSION['active_workspace_id'] == $w['id']) ? 'var(--bg-hover)' : 'var(--primary-color)' ?>; color: <?= (isset($_SESSION['active_workspace_id']) && $_SESSION['active_workspace_id'] == $w['id']) ? 'var(--text-muted)' : 'white' ?>; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">
                            <?= (isset($_SESSION['active_workspace_id']) && $_SESSION['active_workspace_id'] == $w['id']) ? 'Current' : 'Switch To' ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Workspace Modal -->
<div id="wsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 450px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--border-card); overflow: hidden; transform: scale(0.95); transition: transform 0.2s;" id="wsModalContent">
        <div style="padding: 24px; border-bottom: 1px solid var(--border-card); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-heading); font-size: 18px;">Create Workspace</h3>
            <button onclick="closeWorkspaceModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 20px;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <form id="createWsForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-heading);">Workspace Name</label>
                    <input type="text" name="name" required placeholder="e.g., Marketing Team" style="width: 100%; padding: 10px; border: 1px solid var(--border-card); border-radius: 6px; background: var(--bg-main); color: var(--text-main);">
                </div>
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-heading);">Description</label>
                    <textarea name="description" rows="3" placeholder="What is this workspace for?" style="width: 100%; padding: 10px; border: 1px solid var(--border-card); border-radius: 6px; background: var(--bg-main); color: var(--text-main); resize: vertical;"></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeWorkspaceModal()" style="padding: 10px 16px; background: transparent; border: 1px solid var(--border-card); border-radius: 6px; color: var(--text-main); font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 10px 16px; background: var(--primary-color); border: none; border-radius: 6px; color: white; font-weight: 600; cursor: pointer;">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openWorkspaceModal() {
        const modal = document.getElementById('wsModal');
        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('wsModalContent').style.transform = 'scale(1)', 10);
    }

    function closeWorkspaceModal() {
        document.getElementById('wsModalContent').style.transform = 'scale(0.95)';
        setTimeout(() => document.getElementById('wsModal').style.display = 'none', 200);
    }

    document.getElementById('createWsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('controllers/workspace_api.php?action=create', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error creating workspace: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to communicate with server.');
        });
    });

    function switchWorkspace(id) {
        fetch('controllers/workspace_api.php?action=switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'workspace_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?>')
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error switching workspace: ' + (data.error || 'Unknown error'));
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
