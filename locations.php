<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

$zones = $pdo->query("SELECT zone_name FROM zones ORDER BY zone_name")->fetchAll(PDO::FETCH_COLUMN);
$parent_locations = $pdo->query("SELECT name FROM locations ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$searchQ = trim($_GET['q'] ?? '');
$zoneFilter = trim($_GET['zone'] ?? '');

$sql = "SELECT * FROM locations WHERE 1=1";
$params = [];

if ($searchQ) {
    $sql .= " AND (name LIKE ? OR location_id LIKE ? OR address LIKE ? OR pin_code LIKE ?)";
    $params = array_merge($params, ["%$searchQ%", "%$searchQ%", "%$searchQ%", "%$searchQ%"]);
}
if ($zoneFilter) {
    $sql .= " AND zone = ?";
    $params[] = $zoneFilter;
}
$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalLocs = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$totalZonesUsed = $pdo->query("SELECT COUNT(DISTINCT zone) FROM locations")->fetchColumn();
$totalParents = $pdo->query("SELECT COUNT(*) FROM locations WHERE parent_location = 'N/A' OR parent_location IS NULL")->fetchColumn();

?>
<style>
.loc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.loc-card { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 14px; padding: 20px; box-shadow: var(--shadow-soft); transition: box-shadow 0.2s; }
.loc-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
.loc-title { font-size: 18px; font-weight: 700; color: var(--text-heading); margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
.loc-id { font-size: 12px; font-weight: 600; color: var(--primary-color); background: var(--primary-light); padding: 2px 8px; border-radius: 99px; }
.loc-detail { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; }
.loc-detail i { margin-top: 2px; }
.stat-mini { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; padding: 16px 24px; text-align: center; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>📍 Location Management</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openLocationModal()">+ Add Location</button>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:24px;">
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#4f46e5;"><?= $totalLocs ?></div><div style="font-size:12px;color:var(--text-muted);">Total Locations</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#10b981;"><?= $totalZonesUsed ?></div><div style="font-size:12px;color:var(--text-muted);">Active Zones</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#f59e0b;"><?= $totalParents ?></div><div style="font-size:12px;color:var(--text-muted);">Primary Hubs</div></div>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; align-items:center;">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="🔍 Search locations, IDs, addresses..." style="flex:1;min-width:200px;padding:10px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
        <select name="zone" style="padding:10px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
            <option value="">All Zones</option>
            <?php foreach($zones as $z): ?>
            <option value="<?= htmlspecialchars($z) ?>" <?= $zoneFilter===$z?'selected':'' ?>><?= htmlspecialchars($z) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="add-button" style="background:#6366f1;">Filter</button>
        <?php if($searchQ || $zoneFilter): ?><a href="locations.php" class="view-button">Clear</a><?php endif; ?>
    </form>

    <?php if(empty($locations)): ?>
    <div style="text-align:center;padding:60px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:16px;">🏢</div>
        <p>No locations found matching criteria.</p>
    </div>
    <?php else: ?>
    <div class="loc-grid">
        <?php foreach($locations as $loc): ?>
        <div class="loc-card">
            <div class="loc-title">
                <?= htmlspecialchars($loc['name']) ?>
                <span class="loc-id"><?= htmlspecialchars($loc['location_id']) ?></span>
            </div>
            
            <div style="margin-top:16px;">
                <div class="loc-detail">
                    <span>🌍</span>
                    <div><strong>Zone:</strong> <?= htmlspecialchars($loc['zone']) ?></div>
                </div>
                <div class="loc-detail">
                    <span>🏢</span>
                    <div><strong>Parent:</strong> <?= htmlspecialchars($loc['parent_location'] ?? 'N/A') ?></div>
                </div>
                <div class="loc-detail">
                    <span>📍</span>
                    <div>
                        <?= nl2br(htmlspecialchars($loc['address'])) ?><br>
                        <?php if($loc['pin_code']): ?><strong>PIN:</strong> <?= htmlspecialchars($loc['pin_code']) ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if($isAdmin): ?>
            <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-card); display:flex; justify-content:flex-end; gap:8px;">
                <button class="edit-button" style="padding:6px 14px; font-size:12px;" onclick='editLocation(<?= json_encode($loc) ?>)'>Edit</button>
                <form method="POST" action="controllers/delete_location.php" style="display:inline;" onsubmit="return confirm('Delete this location permanently?')">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                    <button type="submit" class="delete-button" style="padding:6px 14px; font-size:12px;">Delete</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
const zones = <?= json_encode($zones) ?>;
const parents = <?= json_encode($parent_locations) ?>;

function openLocationModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Location" : "Add Location";
    document.getElementById('modalForm').action = "controllers/save_location.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group"><label>Location ID</label><input type="text" name="location_id" required value="${data ? data.location_id : ''}" placeholder="LOC-001"></div>
                <div class="form-group"><label>Name</label><input type="text" name="name" required value="${data ? data.name : ''}" placeholder="Main Office"></div>
             </div>`;
    
    html += `<div class="form-group"><label>Address</label><textarea name="address" rows="3">${data ? data.address : ''}</textarea></div>`;
    
    html += `<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div class="form-group"><label>PIN Code</label><input type="text" name="pin_code" value="${data ? data.pin_code : ''}"></div>
                <div class="form-group"><label>Zone</label><select name="zone">`;
    zones.forEach(z => { html += `<option value="${z}" ${data && data.zone==z?'selected':''}>${z}</option>`; });
    html += `</select></div>
             <div class="form-group"><label>Parent Location</label><select name="parent_location"><option value="N/A">N/A</option>`;
    parents.forEach(p => { html += `<option value="${p}" ${data && data.parent_location==p?'selected':''}>${p}</option>`; });
    html += `</select></div>
             </div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editLocation(data) { openLocationModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>
