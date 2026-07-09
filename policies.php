<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

$cats = ['Security','HR','IT','Finance','Operations'];
$statuses = ['Draft','Under Review','Active','Archived'];

$searchQ = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM policies WHERE 1=1";
$params = [];

if ($searchQ) {
    $sql .= " AND (title LIKE ? OR policy_id LIKE ? OR content LIKE ?)";
    $params = array_merge($params, ["%$searchQ%", "%$searchQ%", "%$searchQ%"]);
}
if ($catFilter) {
    $sql .= " AND category = ?";
    $params[] = $catFilter;
}
if ($statusFilter) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY category ASC, title ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalPolicies = $pdo->query("SELECT COUNT(*) FROM policies")->fetchColumn();
$activePolicies = $pdo->query("SELECT COUNT(*) FROM policies WHERE status='Active'")->fetchColumn();
$reviewPolicies = $pdo->query("SELECT COUNT(*) FROM policies WHERE status='Under Review'")->fetchColumn();

?>
<style>
.pol-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
.pol-card { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 14px; padding: 20px; box-shadow: var(--shadow-soft); transition: box-shadow 0.2s; display: flex; flex-direction: column; justify-content: space-between;}
.pol-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
.pol-title { font-size: 16px; font-weight: 700; color: var(--text-heading); margin-bottom: 8px; cursor: pointer;}
.pol-title:hover { color: var(--primary-color); }
.pol-id { font-size: 11px; font-weight: 600; color: var(--text-muted); }
.pol-cat { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; background: #e0e7ff; padding: 3px 10px; border-radius: 99px; display: inline-block; margin-bottom: 8px; }
.pol-status { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; display: inline-block; }
.pol-status.Active { background: #d1fae5; color: #10b981; }
.pol-status.Draft { background: #f3f4f6; color: #6b7280; }
.pol-status.Under { background: #fef3c7; color: #f59e0b; }
.pol-status.Archived { background: #fee2e2; color: #dc2626; }
.stat-mini { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; padding: 16px 24px; text-align: center; }

/* View modal */
.pol-view-body { white-space: pre-wrap; font-size: 14px; color: var(--text-body); line-height: 1.8; max-height: 60vh; overflow-y: auto; background: var(--bg-body); padding: 20px; border-radius: 10px; border: 1px solid var(--border-card); }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>📋 Policy Management</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openPolicyModal()">+ Add Policy</button>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:24px;">
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#4f46e5;"><?= $totalPolicies ?></div><div style="font-size:12px;color:var(--text-muted);">Total Policies</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#10b981;"><?= $activePolicies ?></div><div style="font-size:12px;color:var(--text-muted);">Active</div></div>
        <div class="stat-mini"><div style="font-size:28px;font-weight:800;color:#f59e0b;"><?= $reviewPolicies ?></div><div style="font-size:12px;color:var(--text-muted);">Under Review</div></div>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; align-items:center;">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="🔍 Search policies, IDs..." style="flex:1;min-width:200px;padding:10px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
        <select name="category" style="padding:10px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
            <option value="">All Categories</option>
            <?php foreach($cats as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $catFilter===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" style="padding:10px 14px;border:1px solid var(--border-card);border-radius:8px;background:var(--input-bg);color:var(--text-body);">
            <option value="">All Statuses</option>
            <?php foreach($statuses as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $statusFilter===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="add-button" style="background:#6366f1;">Filter</button>
        <?php if($searchQ || $catFilter || $statusFilter): ?><a href="policies.php" class="view-button">Clear</a><?php endif; ?>
    </form>

    <?php if(empty($policies)): ?>
    <div style="text-align:center;padding:60px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:16px;">📄</div>
        <p>No policies found matching criteria.</p>
    </div>
    <?php else: ?>
    <div class="pol-grid">
        <?php foreach($policies as $pol): 
            $statusClass = str_replace(' ', '', $pol['status']);
        ?>
        <div class="pol-card">
            <div>
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                    <span class="pol-cat"><?= htmlspecialchars($pol['category']) ?></span>
                    <span class="pol-status <?= $statusClass ?>"><?= htmlspecialchars($pol['status']) ?></span>
                </div>
                
                <div class="pol-title" onclick='viewPolicy(<?= json_encode($pol) ?>)'><?= htmlspecialchars($pol['title']) ?></div>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <span class="pol-id">ID: <?= htmlspecialchars($pol['policy_id']) ?></span>
                    <span style="font-size:11px; background:var(--bg-body); border:1px solid var(--border-card); padding:2px 8px; border-radius:6px;">v<?= htmlspecialchars($pol['version']) ?></span>
                </div>
                
                <div style="font-size:13px; color:var(--text-muted); line-height:1.5;">
                    <?= htmlspecialchars(substr($pol['content'], 0, 100)) ?>...
                </div>
            </div>

            <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-card); display:flex; justify-content:flex-end; gap:8px;">
                <button class="view-button" style="padding:6px 12px; font-size:12px;" onclick='viewPolicy(<?= json_encode($pol) ?>)'>Read</button>
                <?php if($isAdmin): ?>
                <button class="edit-button" style="padding:6px 12px; font-size:12px;" onclick='editPolicy(<?= json_encode($pol) ?>)'>Edit</button>
                <form method="POST" action="controllers/delete_policy.php" style="display:inline;" onsubmit="return confirm('Delete this policy permanently?')">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" value="<?= $pol['id'] ?>">
                    <button type="submit" class="delete-button" style="padding:6px 12px; font-size:12px;">Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Policy View Modal -->
<div id="polViewModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:700px; width:95vw;">
        <span class="close-modal" onclick="document.getElementById('polViewModal').style.display='none'">&times;</span>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div id="polViewCat" style="margin-bottom:0;"></div>
            <div id="polViewStatus"></div>
        </div>
        <h2 id="polViewTitle" style="margin:0 0 8px;"></h2>
        <div style="display:flex; gap:16px; font-size:12px; color:var(--text-muted); margin-bottom:20px; border-bottom:1px solid var(--border-card); padding-bottom:12px;">
            <span id="polViewId"></span>
            <span id="polViewVer"></span>
        </div>
        <div class="pol-view-body" id="polViewBody"></div>
    </div>
</div>

<script>
const cats = <?= json_encode($cats) ?>;
const statuses = <?= json_encode($statuses) ?>;

function viewPolicy(data) {
    document.getElementById('polViewCat').innerHTML = `<span class="pol-cat" style="margin:0;">${data.category}</span>`;
    let statusClass = data.status.replace(/\s+/g, '');
    document.getElementById('polViewStatus').innerHTML = `<span class="pol-status ${statusClass}">${data.status}</span>`;
    document.getElementById('polViewTitle').textContent = data.title;
    document.getElementById('polViewId').textContent = 'Policy ID: ' + data.policy_id;
    document.getElementById('polViewVer').textContent = 'Version: ' + data.version;
    document.getElementById('polViewBody').textContent = data.content;
    document.getElementById('polViewModal').style.display = 'block';
}

function openPolicyModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Policy" : "Add Policy";
    document.getElementById('modalForm').action = "controllers/save_policy.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div style="display:grid; grid-template-columns:1fr 2fr; gap:16px;">
                <div class="form-group"><label>Policy ID</label><input type="text" name="policy_id" required value="${data ? data.policy_id : ''}" placeholder="POL-001"></div>
                <div class="form-group"><label>Title</label><input type="text" name="title" required value="${data ? data.title : ''}"></div>
             </div>`;
    
    html += `<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div class="form-group"><label>Category</label><select name="category">`;
    cats.forEach(c => { html += `<option value="${c}" ${data&&data.category==c?'selected':''}>${c}</option>`; });
    html += `</select></div>
             <div class="form-group"><label>Version</label><input type="text" name="version" required value="${data ? data.version : '1.0'}"></div>
             <div class="form-group"><label>Status</label><select name="status">`;
    statuses.forEach(s => { html += `<option value="${s}" ${data&&data.status==s?'selected':''}>${s}</option>`; });
    html += `</select></div>
             </div>`;

    html += `<div class="form-group"><label>Content</label><textarea name="content" required rows="10" style="font-family:inherit; line-height:1.6;">${data ? data.content : ''}</textarea></div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editPolicy(data) { openPolicyModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>
