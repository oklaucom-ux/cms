<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_support');

// Auto-migrate
$pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_base (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    content_body TEXT NOT NULL,
    is_public INTEGER DEFAULT 1,
    tags VARCHAR(255) DEFAULT '',
    created_by TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// CRUD Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM knowledge_base WHERE id=?")->execute([(int)$_POST['id']]);
        header("Location: kb.php?success=deleted"); exit;
    }

    $category   = trim($_POST['category'] ?? '');
    $title      = trim($_POST['title'] ?? '');
    $content    = trim($_POST['content_body'] ?? '');
    $tags       = trim($_POST['tags'] ?? '');
    $is_public  = isset($_POST['is_public']) ? 1 : 0;
    $id         = (int)($_POST['id'] ?? 0);

    if ($id) {
        $pdo->prepare("UPDATE knowledge_base SET category=?, title=?, content_body=?, tags=?, is_public=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$category, $title, $content, $tags, $is_public, $id]);
    } else {
        $pdo->prepare("INSERT INTO knowledge_base (category, title, content_body, tags, is_public, created_by) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$category, $title, $content, $tags, $is_public, $_SESSION['login_id']]);
    }
    header("Location: kb.php?success=saved"); exit;
}

$search   = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['cat'] ?? '');

$query = "SELECT kb.*, u.name as author_name FROM knowledge_base kb LEFT JOIN users u ON kb.created_by = u.login_id WHERE 1=1";
$params = [];
if ($search) { $query .= " AND (kb.title LIKE ? OR kb.content_body LIKE ? OR kb.tags LIKE ?)"; $s = "%{$search}%"; $params = [$s,$s,$s]; }
if ($catFilter) { $query .= " AND kb.category = ?"; $params[] = $catFilter; }
$query .= " ORDER BY kb.category ASC, kb.title ASC";

$articles = $pdo->prepare($query);
$articles->execute($params);
$articles = $articles->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT DISTINCT category FROM knowledge_base ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<style>
.kb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
.kb-card { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 14px; padding: 20px; box-shadow: var(--shadow-soft); transition: box-shadow 0.2s; }
.kb-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
.kb-cat { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; background: #e0e7ff; padding: 3px 10px; border-radius: 99px; display: inline-block; margin-bottom: 10px; }
.kb-title { font-size: 16px; font-weight: 700; color: var(--text-heading); margin-bottom: 8px; cursor: pointer; }
.kb-title:hover { color: #4f46e5; }
.kb-snippet { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 12px; }
.kb-tag { font-size: 11px; background: #f3f4f6; color: #6b7280; padding: 2px 8px; border-radius: 99px; display: inline-block; margin: 2px; }
.kb-meta { font-size: 11px; color: var(--text-muted); border-top: 1px solid var(--border-card); padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }

/* View modal */
.kb-view-body { white-space: pre-wrap; font-size: 14px; color: var(--text-body); line-height: 1.8; max-height: 60vh; overflow-y: auto; background: var(--bg-body); padding: 20px; border-radius: 10px; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>📚 Knowledge Base</h2>
        <button class="add-button" onclick="openKBModal()">+ New Article</button>
    </div>

    <?php if(isset($_GET['success'])): ?>
    <div style="background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#065f46; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        ✅ Article <?= $_GET['success'] === 'deleted' ? 'deleted' : 'saved' ?> successfully.
    </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Search articles, tags..." style="flex:1; min-width:200px; padding:10px 14px; border:1px solid var(--border-card); border-radius:8px; background:var(--input-bg); color:var(--text-body);">
        <select name="cat" style="padding:10px 14px; border:1px solid var(--border-card); border-radius:8px; background:var(--input-bg); color:var(--text-body);">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $catFilter===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="add-button" style="background:#6366f1;">Search</button>
        <?php if($search || $catFilter): ?><a href="kb.php" class="view-button">Clear</a><?php endif; ?>
    </form>

    <!-- Stats Row -->
    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px;">
        <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:12px; padding:16px 24px; text-align:center; min-width:120px;">
            <div style="font-size:26px; font-weight:800; color:#4f46e5;"><?= count($articles) ?></div>
            <div style="font-size:12px; color:var(--text-muted);">Articles Found</div>
        </div>
        <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:12px; padding:16px 24px; text-align:center; min-width:120px;">
            <div style="font-size:26px; font-weight:800; color:#10b981;"><?= count($categories) ?></div>
            <div style="font-size:12px; color:var(--text-muted);">Categories</div>
        </div>
        <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:12px; padding:16px 24px; text-align:center; min-width:120px;">
            <div style="font-size:26px; font-weight:800; color:#f59e0b;"><?= $pdo->query("SELECT COUNT(*) FROM knowledge_base WHERE is_public=1")->fetchColumn() ?></div>
            <div style="font-size:12px; color:var(--text-muted);">Public</div>
        </div>
    </div>

    <!-- Article Grid -->
    <?php if(empty($articles)): ?>
    <div style="text-align:center; padding:60px; color:var(--text-muted);">
        <div style="font-size:48px; margin-bottom:16px;">📭</div>
        <p>No articles found. Create the first one!</p>
    </div>
    <?php else: ?>
    <div class="kb-grid">
        <?php foreach($articles as $a): ?>
        <div class="kb-card">
            <span class="kb-cat"><?= htmlspecialchars($a['category']) ?></span>
            <?php if(!$a['is_public']): ?><span style="font-size:11px; background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:99px; font-weight:700; margin-left:4px;">🔒 Internal</span><?php endif; ?>
            <div class="kb-title" onclick='viewArticle(<?= json_encode($a) ?>)'><?= htmlspecialchars($a['title']) ?></div>
            <div class="kb-snippet"><?= htmlspecialchars(substr($a['content_body'], 0, 120)) ?>...</div>
            <?php if($a['tags']): ?>
            <div style="margin-bottom:8px;">
                <?php foreach(array_filter(array_map('trim', explode(',', $a['tags']))) as $tag): ?>
                <span class="kb-tag">#<?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="kb-meta">
                <span>By <?= htmlspecialchars($a['author_name'] ?? $a['created_by'] ?? 'System') ?></span>
                <div style="display:flex;gap:8px;">
                    <button class="edit-button" style="padding:4px 10px;font-size:11px;" onclick='openKBModal(<?= json_encode($a) ?>)'>Edit</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this article permanently?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="delete-button" style="padding:4px 10px;font-size:11px;">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Article View Modal -->
<div id="kbViewModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:700px; width:95vw;">
        <span class="close-modal" onclick="document.getElementById('kbViewModal').style.display='none'">&times;</span>
        <div id="kbViewCat" style="margin-bottom:8px;"></div>
        <h2 id="kbViewTitle" style="margin:0 0 16px;"></h2>
        <div class="kb-view-body" id="kbViewBody"></div>
        <div id="kbViewMeta" style="margin-top:12px; font-size:12px; color:var(--text-muted);"></div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="kbModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:640px; width:95vw;">
        <span class="close-modal" onclick="document.getElementById('kbModal').style.display='none'">&times;</span>
        <h2 id="kbModalTitle" style="margin-bottom:20px;">Add Article</h2>
        <form method="POST" id="kbForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="kbId" value="">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="kbCategory" required placeholder="e.g. HR, Technical, Billing" list="kbCatList">
                    <datalist id="kbCatList">
                        <?php foreach($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Tags (comma-separated)</label>
                    <input type="text" name="tags" id="kbTags" placeholder="e.g. leave, hr-policy, faq">
                </div>
            </div>
            <div class="form-group">
                <label>Article Title</label>
                <input type="text" name="title" id="kbTitle" required>
            </div>
            <div class="form-group">
                <label>Content</label>
                <textarea name="content_body" id="kbContent" rows="10" required style="font-family:inherit; line-height:1.6;"></textarea>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="is_public" id="kbPublic" value="1">
                <label for="kbPublic" style="margin:0;">Publicly Visible (accessible to all employees)</label>
            </div>
            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="button" onclick="document.getElementById('kbModal').style.display='none'" style="flex:1;padding:12px;border-radius:10px;border:1px solid var(--border-card);background:transparent;color:var(--text-body);cursor:pointer;font-weight:600;">Cancel</button>
                <button type="submit" class="submit" style="flex:2;">Publish Article</button>
            </div>
        </form>
    </div>
</div>

<script>
function openKBModal(data = null) {
    document.getElementById('kbModalTitle').textContent = data ? 'Edit Article' : 'New Article';
    document.getElementById('kbId').value       = data ? data.id : '';
    document.getElementById('kbCategory').value = data ? data.category : '';
    document.getElementById('kbTitle').value    = data ? data.title : '';
    document.getElementById('kbContent').value  = data ? data.content_body : '';
    document.getElementById('kbTags').value     = data ? (data.tags || '') : '';
    document.getElementById('kbPublic').checked = data ? (data.is_public == 1) : true;
    document.getElementById('kbModal').style.display = 'block';
}

function viewArticle(data) {
    document.getElementById('kbViewCat').innerHTML = `<span class="kb-cat">${data.category}</span>`;
    document.getElementById('kbViewTitle').textContent = data.title;
    document.getElementById('kbViewBody').textContent = data.content_body;
    document.getElementById('kbViewMeta').textContent = 'By ' + (data.author_name || data.created_by || 'System') + ' · ' + (data.updated_at || data.created_at || '');
    document.getElementById('kbViewModal').style.display = 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>

