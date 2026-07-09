<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

$searchQ = trim($_GET['q'] ?? '');

$sql = "SELECT z.*, (SELECT COUNT(*) FROM locations l WHERE l.zone = z.zone_name) as location_count FROM zones z WHERE 1=1";
$params = [];

if ($searchQ) {
    $sql .= " AND (z.zone_name LIKE ? OR z.zone_id LIKE ? OR z.description LIKE ?)";
    $params = array_merge($params, ["%$searchQ%", "%$searchQ%", "%$searchQ%"]);
}
$sql .= " ORDER BY z.zone_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalZones = count($zones);
$totalLocationsMapped = $pdo->query("SELECT COUNT(*) FROM locations WHERE zone IN (SELECT zone_name FROM zones)")->fetchColumn();

?>
<style>
.zone-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
.zone-card { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 14px; padding: 20px; box-shadow: var(--shadow-soft); transition: box-shadow 0.2s; display: flex; flex-direction: column; justify-content: space-between;}
.zone-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
.zone-title { font-size: 18px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
.zone-id { font-size: 12px; font-weight: 600; color: #10b981; background: #d1fae5; padding: 2px 8px; border-radius: 99px; }
.zone-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; }
.stat-mini { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; padding: 16px 24px; text-align: center; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>🌍 Zone Management</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openZoneModal()">+ Add Zone</button>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:24px;">
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#4f46e5;"><?= $totalZones ?></div><div style="font-size:12px;color:var(--text-muted);">Total Zones</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#10b981;"><?= $totalLocationsMapped ?></div><div style="font-size:12px;color:var(--text-muted);">Mapped Locations</div></div>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; align-items:center;">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="🔍 Search zones, IDs, descriptions..." style="flex:1;min-width:200px;padding:10px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
        <button type="submit" class="add-button" style="background:#6366f1;">Search</button>
        <?php if($searchQ): ?><a href="zones.php" class="view-button">Clear</a><?php endif; ?>
    </form>

    <?php if(empty($zones)): ?>
    <div style="text-align:center;padding:60px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:16px;">🗺️</div>
        <p>No zones found.</p>
    </div>
    <?php else: ?>
    <div class="zone-grid">
        <?php foreach($zones as $z): ?>
        <div class="zone-card">
            <div>
                <div class="zone-title">
                    <?= htmlspecialchars($z['zone_name']) ?>
                    <span class="zone-id"><?= htmlspecialchars($z['zone_id']) ?></span>
                </div>
                <div class="zone-desc"><?= nl2br(htmlspecialchars($z['description'] ?? 'No description provided.')) ?></div>
                
                <div style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-muted); background:var(--bg-body); padding:8px 12px; border-radius:8px; border:1px solid var(--border-card);">
                    <span>📍</span>
                    <strong><?= $z['location_count'] ?></strong> Locations mapped
                </div>
            </div>

            <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border-card); padding-top:16px;">
                <span style="font-size:11px; color:var(--text-muted);">Created: <?= explode(' ', $z['created_date'])[0] ?></span>
                <?php if($isAdmin): ?>
                <div style="display:flex; gap:8px;">
                    <button class="edit-button" style="padding:4px 12px; font-size:12px;" onclick='editZone(<?= json_encode($z) ?>)'>Edit</button>
                    <form method="POST" action="controllers/delete_zone.php" style="display:inline;" onsubmit="return confirm('Delete this zone permanently?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id" value="<?= $z['id'] ?>">
                        <button type="submit" class="delete-button" style="padding:4px 12px; font-size:12px;">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function openZoneModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Zone" : "Add Zone";
    document.getElementById('modalForm').action = "controllers/save_zone.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group"><label>Zone ID</label><input type="text" name="zone_id" required value="${data ? data.zone_id : ''}" placeholder="ZN-001"></div>
                <div class="form-group"><label>Zone Name</label><input type="text" name="zone_name" required value="${data ? data.zone_name : ''}" placeholder="North America"></div>
             </div>`;
    
    html += `<div class="form-group"><label>Description</label><textarea name="description" rows="3" required>${data ? data.description : ''}</textarea></div>`;
    html += `<div class="form-group"><label>Created Date</label><input type="date" name="created_date" required value="${data ? data.created_date : new Date().toISOString().split('T')[0]}"></div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editZone(data) { openZoneModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>
