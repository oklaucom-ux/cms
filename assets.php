<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_assets');

$canCreateAssets = hasPermission($pdo, 'create_assets');
$canEditAssets   = hasPermission($pdo, 'edit_assets');
$canDeleteAssets   = hasPermission($pdo, 'delete_assets');
$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Manager');

// Auto-Migrate schema
try { $pdo->exec("ALTER TABLE assets ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'"); } catch(Exception $e){}
$pdo->exec("CREATE TABLE IF NOT EXISTS assets (id INTEGER PRIMARY KEY AUTO_INCREMENT, asset_tag VARCHAR(255) UNIQUE NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL, assigned_to TEXT, branch_id VARCHAR(255) DEFAULT 'Global HQ', status VARCHAR(255) DEFAULT 'Unassigned', `condition` VARCHAR(255) DEFAULT 'Good', registered_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS asset_logs (id INTEGER PRIMARY KEY AUTO_INCREMENT, asset_id INTEGER, action TEXT, performed_by TEXT, details TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

// Fetch assets based on role/branch
if ($isAdmin) {
    $assets = $pdo->query("SELECT * FROM assets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $myBranch = $pdo->query("SELECT branch_id FROM users WHERE login_id = '{$_SESSION['login_id']}'")->fetchColumn() ?: 'Global HQ';
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE branch_id = ? ORDER BY id DESC");
    $stmt->execute([$myBranch]);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='Assigned' THEN 1 ELSE 0 END) AS assigned,
        SUM(CASE WHEN status='Unassigned' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status='Retired' THEN 1 ELSE 0 END) AS retired,
        SUM(CASE WHEN `condition`='Poor' OR `condition`='Damaged' THEN 1 ELSE 0 END) AS needs_attention
    FROM assets
")->fetch(PDO::FETCH_ASSOC);

// Users for assignment dropdown
$users = $pdo->query("SELECT login_id, name FROM users WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🖥️ IT Asset Tracking</h2>
        <div style="display:flex;gap:10px;">
            <a href="controllers/export_csv.php?table=assets" class="view-button" style="text-decoration:none;padding:10px 18px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border-card);font-size:13px;font-weight:600;color:var(--text-body);">📥 Export CSV</a>
            <a href="controllers/asset_qr.php?all=1" target="_blank" class="view-button" style="text-decoration:none;padding:10px 18px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border-card);font-size:13px;font-weight:600;color:var(--text-body);">🏷️ Print All QR</a>
            <?php if($canCreateAssets): ?>
            <button class="add-button" onclick="openAssetModal()">+ Register Asset</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Strip -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:16px; margin-bottom:28px;">
        <div style="background:white; border-radius:12px; padding:18px; box-shadow:0 4px 6px rgba(0,0,0,0.05);  text-align:center;">
            <div style="font-size:28px; font-weight:800; color:#6366f1;"><?= $stats['total'] ?></div>
            <div style="font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase;">Total Assets</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px; box-shadow:0 4px 6px rgba(0,0,0,0.05);  text-align:center;">
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= $stats['assigned'] ?></div>
            <div style="font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase;">Assigned</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px; box-shadow:0 4px 6px rgba(0,0,0,0.05);  text-align:center;">
            <div style="font-size:28px; font-weight:800; color:#3b82f6;"><?= $stats['available'] ?></div>
            <div style="font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase;">Available</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px; box-shadow:0 4px 6px rgba(0,0,0,0.05);  text-align:center;">
            <div style="font-size:28px; font-weight:800; color:#9ca3af;"><?= $stats['retired'] ?></div>
            <div style="font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase;">Retired</div>
        </div>
        <div style="background:white; border-radius:12px; padding:18px; box-shadow:0 4px 6px rgba(0,0,0,0.05);  text-align:center;">
            <div style="font-size:28px; font-weight:800; color:#dc2626;"><?= $stats['needs_attention'] ?></div>
            <div style="font-size:12px; color:#6b7280; font-weight:600; text-transform:uppercase;">Poor Condition</div>
        </div>
    </div>

    <!-- Asset Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Asset Tag</th>
                    <th>Name / Model</th>
                    <th>Type</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Condition</th>
                    <?php if($isAdmin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($assets as $a):
                    $statusColor = $a['status'] === 'Assigned' ? '#6366f1' : ($a['status'] === 'Retired' ? '#9ca3af' : '#10b981');
                    $condColor   = in_array($a['condition'], ['Poor', 'Damaged']) ? '#dc2626' : ($a['condition'] === 'Fair' ? '#f59e0b' : '#10b981');
                ?>
                <tr>
                    <td><code style="background:#f3f4f6; padding:3px 8px; border-radius:4px; font-weight:700;"><?= htmlspecialchars($a['asset_tag']) ?></code></td>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td><?= htmlspecialchars($a['type']) ?></td>
                    <td><?= htmlspecialchars($a['assigned_to'] ?: '—') ?></td>
                    <td><span style="background:<?= $statusColor ?>22; color:<?= $statusColor ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:700;"><?= htmlspecialchars($a['status']) ?></span></td>
                    <td><span style="background:<?= $condColor ?>22; color:<?= $condColor ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:700;"><?= htmlspecialchars($a['condition']) ?></span></td>
                    <?php if($canEditAssets || $canDeleteAssets): ?>
                    <td class="action-buttons">
                        <a href="controllers/asset_qr.php?id=<?= $a['id'] ?>" target="_blank" class="edit-button" style="text-decoration:none;background:#f0fdf4;color:#16a34a;border:none;">🏷️ QR</a>
                        <?php if($canEditAssets): ?>
                        <button class="edit-button" onclick='editAsset(<?= json_encode($a) ?>)'>Edit</button>
                        <?php endif; ?>
                        <?php if($canDeleteAssets): ?>
                        <form method="POST" action="controllers/delete_asset.php" style="display:inline;" onsubmit="return confirm('Retire this asset from the registry?')">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="delete-button">Retire</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($assets)): ?>
                <tr><td colspan="7" style="text-align:center; color:#9ca3af; padding:40px;">No assets registered. Click "Register Asset" to begin tracking.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generic Modal -->
<div id="genericModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Asset Registration</h2>
        <form id="modalForm" method="POST" action="controllers/save_asset.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div id="modalFields"></div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="submit">Save Asset</button>
            </div>
        </form>
    </div>
</div>

<script>
const users = <?= json_encode($users) ?>;

function openAssetModal(d = null) {
    document.getElementById('modalTitle').textContent = d ? 'Edit Asset' : 'Register New Asset';

    let html = `<input type="hidden" name="id" value="${d ? d.id : ''}">`;
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div class="form-group"><label>Asset Tag (Unique ID)</label><input type="text" name="asset_tag" required value="${d ? d.asset_tag : 'AST-'+Math.floor(Math.random()*10000)}" ${d?'readonly':''}></div>
        <div class="form-group"><label>Type</label><select name="type" required>
            <option ${d&&d.type=='Laptop'?'selected':''}>Laptop</option>
            <option ${d&&d.type=='Desktop'?'selected':''}>Desktop</option>
            <option ${d&&d.type=='Monitor'?'selected':''}>Monitor</option>
            <option ${d&&d.type=='Phone'?'selected':''}>Phone</option>
            <option ${d&&d.type=='Tablet'?'selected':''}>Tablet</option>
            <option ${d&&d.type=='Server'?'selected':''}>Server</option>
            <option ${d&&d.type=='Network Device'?'selected':''}>Network Device</option>
            <option ${d&&d.type=='Peripheral'?'selected':''}>Peripheral</option>
            <option ${d&&d.type=='Other'?'selected':''}>Other</option>
        </select></div>
    </div>`;

    html += `<div class="form-group"><label>Name / Model Description</label><input type="text" name="name" required value="${d ? d.name : ''}" placeholder="e.g. Dell XPS 15 9500 - 16GB RAM"></div>`;

    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div class="form-group"><label>Assign To User</label><select name="assigned_to">
            <option value="">Unassigned</option>`;
    users.forEach(u => {
        let sel = d && d.assigned_to === u.login_id ? 'selected' : '';
        html += `<option value="${u.login_id}" ${sel}>${u.name} (${u.login_id})</option>`;
    });
    html += `</select></div>
        <div class="form-group"><label>Condition</label><select name="condition">
            <option ${d&&d.condition=='Excellent'?'selected':''}>Excellent</option>
            <option ${d&&d.condition=='Good'?'selected':''}>Good</option>
            <option ${d&&d.condition=='Fair'?'selected':''}>Fair</option>
            <option ${d&&d.condition=='Poor'?'selected':''}>Poor</option>
            <option ${d&&d.condition=='Damaged'?'selected':''}>Damaged</option>
        </select></div>
    </div>`;

    html += `<div class="form-group"><label>Status</label><select name="status">
        <option ${d&&d.status=='Unassigned'?'selected':''}>Unassigned</option>
        <option ${d&&d.status=='Assigned'?'selected':''}>Assigned</option>
        <option ${d&&d.status=='In Repair'?'selected':''}>In Repair</option>
        <option ${d&&d.status=='Retired'?'selected':''}>Retired</option>
    </select></div>`;

    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editAsset(d) { openAssetModal(d); }
function closeModal() { document.getElementById('genericModal').style.display = 'none'; }
</script>

<?php require_once 'includes/footer.php'; ?>

