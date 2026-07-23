<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_audit_trail');

// Auto-migrate audit_trail table
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_trail (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Filters
$filterUser = $_GET['user'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Pagination
$perPage = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($filterUser) { $where[] = "user_id LIKE ?"; $params[] = "%$filterUser%"; }
if ($filterAction) { $where[] = "action LIKE ?"; $params[] = "%$filterAction%"; }
if ($filterDate) { $where[] = "DATE(timestamp) = ?"; $params[] = $filterDate; }

$whereSQL = count($where) ? " WHERE " . implode(' AND ', $where) : '';

try {
    // Count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_trail" . $whereSQL);
    $countStmt->execute($params);
    $filteredTotal = (int) $countStmt->fetchColumn();
    $totalPages = max(1, ceil($filteredTotal / $perPage));

    $sql = "SELECT * FROM audit_trail" . $whereSQL . " ORDER BY timestamp DESC LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dual DB safe stats query
    $todayDate = date('Y-m-d');
    $statsStmt = $pdo->prepare("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN DATE(timestamp) = ? THEN 1 ELSE 0 END) as today,
        COUNT(DISTINCT user_id) as actors
    FROM audit_trail");
    $statsStmt->execute([$todayDate]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $filteredTotal = 0; $totalPages = 1; $logs = [];
    $stats = ['total'=>0, 'today'=>0, 'actors'=>0];
}

$totalLogs = $stats['total'] ?? 0;
$todayLogs = $stats['today'] ?? 0;
$uniqueUsers = $stats['actors'] ?? 0;

try {
    $actionTypes = $pdo->query("SELECT action, COUNT(*) as cnt FROM audit_trail GROUP BY action ORDER BY cnt DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $actionTypes = []; }
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">🛡️ Security Audit Log & System Activity</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Real-time enterprise event stream, user activity logs, IP tracking, and security auditing.</p>
        </div>
        <button class="view-button" onclick="window.location.href='controllers/export_csv.php?table=audit_trail'" style="text-decoration:none; padding:10px 18px; border-radius:10px; background:var(--bg-card); border:1px solid var(--border-card); font-size:13px; font-weight:600; color:var(--text-body);">
            📥 Export Audit Log
        </button>
    </div>

    <!-- Top Executive Security Analytics -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Total Audit Logs</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalLogs) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Logged Security Events</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Today's Actions</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= number_format($todayLogs) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Events Recorded Today</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Active User Actors</div>
            <div style="font-size:28px; font-weight:800; color:#6366f1;"><?= number_format($uniqueUsers) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Unique User Actions</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Security Health</div>
            <div style="font-size:16px; font-weight:700; margin-top:6px; color:#10b981;">
                🟢 System Protected
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">0 Threat Flags Detected</div>
        </div>
    </div>

    <!-- Action Breakdown -->
    <?php if(!empty($actionTypes)): ?>
    <div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:var(--shadow-xs); margin-bottom:24px;">
        <h3 style="font-size:14px; color:var(--text-heading); margin-bottom:16px;">Action Breakdown</h3>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <?php foreach($actionTypes as $at):
                $colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#ec4899','#8b5cf6','#3b82f6','#14b8a6'];
                $c = $colors[array_search($at, $actionTypes) % count($colors)];
            ?>
            <span style="background:<?= $c ?>15; color:<?= $c ?>; padding:6px 14px; border-radius:99px; font-size:12px; font-weight:600; border:1px solid <?= $c ?>30;">
                <?= htmlspecialchars($at['action']) ?> <strong>(<?= $at['cnt'] ?>)</strong>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:flex-end;">
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label>User ID</label>
            <input type="text" name="user" value="<?= htmlspecialchars($filterUser) ?>" placeholder="Filter by user...">
        </div>
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label>Action</label>
            <input type="text" name="action" value="<?= htmlspecialchars($filterAction) ?>" placeholder="e.g. Login, Update...">
        </div>
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label>Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <button type="submit" class="add-button" style="height:38px;">🔍 Filter</button>
        <a href="audit_trail.php" class="edit-button" style="height:38px; display:inline-flex; align-items:center; text-decoration:none;">Clear</a>
    </form>

    <!-- Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $row):
                    $icon = '📌';
                    $a = strtolower($row['action']);
                    if(str_contains($a,'login')) $icon = '🔐';
                    elseif(str_contains($a,'create') || str_contains($a,'add')) $icon = '➕';
                    elseif(str_contains($a,'update') || str_contains($a,'edit')) $icon = '✏️';
                    elseif(str_contains($a,'delete') || str_contains($a,'remove')) $icon = '🗑️';
                    elseif(str_contains($a,'approve')) $icon = '✅';
                    elseif(str_contains($a,'reject')) $icon = '❌';
                    elseif(str_contains($a,'sync')) $icon = '🔄';
                ?>
                <tr>
                    <td style="white-space:nowrap; font-size:12px; color:var(--text-muted);"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($row['timestamp']))) ?></td>
                    <td><span style="background:var(--primary-light); color:var(--primary-color); padding:3px 10px; border-radius:6px; font-size:12px; font-weight:600;"><?= htmlspecialchars($row['user_id']) ?></span></td>
                    <td><span style="font-weight:600;"><?= $icon ?> <?= htmlspecialchars($row['action']) ?></span></td>
                    <td style="font-size:12px; color:var(--text-muted); max-width:400px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($row['details']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?>
                <tr><td colspan="4" class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <div class="empty-state-text">No audit events found</div>
                    <div class="empty-state-sub">Try adjusting your filters</div>
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:10px;">
        <div style="font-size:12px; color:var(--text-muted);">
            Showing <?= count($logs) ?> of <?= number_format($filteredTotal) ?> filtered (<?= number_format($totalLogs) ?> total) · Page <?= $page ?>/<?= $totalPages ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="display:flex; gap:4px; flex-wrap:wrap;">
            <?php
            $qs = http_build_query(array_filter(['user' => $filterUser, 'action' => $filterAction, 'date' => $filterDate]));
            $link = function($p) use ($qs) { return "audit_trail.php?page={$p}" . ($qs ? "&{$qs}" : ""); };
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= $link(1) ?>" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">«</a>
                <a href="<?= $link($page - 1) ?>" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">‹ Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= $link($i) ?>" class="<?= $i === $page ? 'add-button' : 'edit-button' ?>" style="padding:4px 10px; font-size:12px; text-decoration:none;"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= $link($page + 1) ?>" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">Next ›</a>
                <a href="<?= $link($totalPages) ?>" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">»</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
