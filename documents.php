<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto Migrate
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id {$pkDef},
        title TEXT NOT NULL,
        file_path TEXT NOT NULL,
        category TEXT NOT NULL,
        uploaded_by TEXT,
        visible_to_role VARCHAR(255) DEFAULT 'ALL',
        version INT DEFAULT 1,
        workspace_id INT DEFAULT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

requirePermission($pdo, 'view_documents');

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
$canUpload = hasPermission($pdo, 'upload_documents');
$canDelete = hasPermission($pdo, 'delete_documents');
$myRole = $_SESSION['role'];

$wsFilter = "";
$wsParams = [];
if (isset($_SESSION['active_workspace_id'])) {
    $wsFilter = " AND (d.workspace_id = ? OR d.workspace_id IS NULL)";
    $wsParams[] = $_SESSION['active_workspace_id'];
}

try {
    $query_base = "SELECT d.*, COALESCE(u.name, sa.name, d.uploaded_by) as uploader_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.login_id LEFT JOIN super_admins sa ON d.uploaded_by = sa.login_id WHERE d.version = (SELECT MAX(version) FROM documents d2 WHERE d2.title = d.title) {$wsFilter}";

    if ($isAdmin) {
        $stmt = $pdo->prepare($query_base . " ORDER BY d.uploaded_at DESC");
        $stmt->execute($wsParams);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $params = array_merge($wsParams, [$myRole]);
        $stmt = $pdo->prepare($query_base . " AND (d.visible_to_role = 'ALL' OR d.visible_to_role = ?) ORDER BY d.uploaded_at DESC");
        $stmt->execute($params);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $docs = []; }

// Group into categories
$grouped = [];
foreach($docs as $d) {
    $grouped[$d['category']][] = $d;
}

$totalDocsCount = count($docs);
$totalCategoryCount = count($grouped);
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">📁 Enterprise Document Drive</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Centralized document vault, version history, role-based visibility, and file assets.</p>
        </div>
        <?php if ($canUpload): ?>
        <button class="add-button" onclick="document.getElementById('uploadModal').style.display='flex'">
            <i class="fas fa-upload"></i> Upload Document
        </button>
        <?php endif; ?>
    </div>

    <!-- Top Executive Document Drive Analytics -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Total Documents</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalDocsCount) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Active Files</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Categories</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalCategoryCount) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Organized Asset Vaults</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Accessible Assets</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= number_format($totalDocsCount) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Role Permission Filtered</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Drive Engine</div>
            <div style="font-size:16px; font-weight:700; margin-top:6px; color:#6366f1;">
                🟢 100% Operational
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Version Controlled Vault</div>
        </div>
    </div>
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>Corporate Document Drive</h2>
        <?php if($canUpload): ?>
        <button class="add-button" onclick="openDocModal()">+ Upload File</button>
        <?php endif; ?>
    </div>

    <!-- Grouped Drives -->
    <?php foreach($grouped as $category =>$files): ?>
        <h3 style="margin-top: 32px; margin-bottom: 16px; color: var(--text-heading); border-bottom: 2px solid var(--border-card); padding-bottom: 8px;">📂 <?= htmlspecialchars($category) ?></h3>
        
        <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            <?php foreach($files as $f): ?>
                <div class="dashboard-card" style=" display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <h4 style="color:var(--text-heading); margin-bottom: 8px; font-size:18px; display:flex; justify-content:space-between;">
                            <?= htmlspecialchars($f['title']) ?>
                            <span style="font-size:12px; background:#e0e7ff; color:#4f46e5; padding:2px 6px; border-radius:8px;">v<?= $f['version'] ?? 1 ?>.0</span>
                        </h4>
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                            Uploaded by: <strong><?= htmlspecialchars($f['uploader_name'] ?? $f['uploaded_by'] ?? 'System') ?></strong><br>
                            Date: <?= explode(' ', $f['uploaded_at'])[0] ?><br>
                            Visiblity: <strong><?= $f['visible_to_role'] ?></strong>
                        </p>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <a href="<?= htmlspecialchars($f['file_path']) ?>" download class="view-button" style="text-decoration:none; text-align:center; flex:1; margin-right:8px;">⬇️ Download Latest</a>
                        <?php if($canDelete): ?>
                        <form method="POST" action="controllers/delete_document.php" onsubmit="return confirm('Permanently delete file?')" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="delete-button" style="height:35px;">Trash</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    
    <?php if(empty($grouped)): ?>
        <div style="padding: 40px; text-align:center; color: var(--text-muted); background:var(--bg-card); border-radius:12px; border:1px solid var(--border-card);">
            Empty Repository. Try uploading a File!
        </div>
    <?php endif; ?>
    
</div>

<!-- Upload Modal -->
<div id="docModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="document.getElementById('docModal').style.display='none'">&times;</span>
        <h2>Upload Document</h2>
        <form method="POST" action="controllers/save_document.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label>File (PDF, PNG, JPG, DOCX)</label>
                <input type="file" name="document" required accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
            </div>

            <div class="form-group">
                <label>Document Title</label>
                <input type="text" name="title" required placeholder="e.g. Q3 Tax Report">
            </div>

            <div class="form-group">
                <label>Category Folder</label>
                <select name="category" required>
                    <option value="HR & Policies">HR & Policies</option>
                    <option value="Training Material">Training Material</option>
                    <option value="Financial Disclosures">Financial Disclosures</option>
                    <option value="General Resources">General Resources</option>
                </select>
            </div>

            <div class="form-group">
                <label>Visibility / Permissions</label>
                <select name="visible_to_role">
                    <option value="ALL">ALL (Company Wide)</option>
                    <?php foreach($pdo->query("SELECT role_name FROM roles")->fetchAll() as $r): ?>
                        <option value="<?= $r['role_name'] ?>">Restricted: <?= $r['role_name'] ?> Only</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="submit">Secure Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDocModal() { document.getElementById('docModal').style.display='block'; }
</script>

<?php require_once 'includes/footer.php'; ?>

