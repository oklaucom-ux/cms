<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_audit_trail');

// Filters
$filterUser = $_GET['user'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';

$where = [];
$params = [];
if ($filterUser) { $where[] = "user_id LIKE ?"; $params[] = "%$filterUser%"; }
if ($filterAction) { $where[] = "action LIKE ?"; $params[] = "%$filterAction%"; }
if ($filterDate) { $where[] = "DATE(timestamp) = ?"; $params[] = $filterDate; }

$sql = "SELECT * FROM audit_trail" . (count($where) ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY timestamp DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalLogs = $pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn();
$todayLogs = $pdo->query("SELECT COUNT(*) FROM audit_trail WHERE DATE(timestamp) = DATE('now')")->fetchColumn();
$uniqueUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM audit_trail")->fetchColumn();
$actionTypes = $pdo->query("SELECT action, COUNT(*) as cnt FROM audit_trail GROUP BY action ORDER BY cnt DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📝 Security Audit Trail</h2>
        <button class="add-button" style="background:#4b5563;" onclick="window.location.href='controllers/export_csv.php?table=audit_trail'">📥 Export CSV</button>
    </div>

    <!-- KPI Strip -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:16px; margin-bottom:24px;">
        <div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:var(--shadow-xs); ">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Total Events</div>
            <div style="font-size:26px; font-weight:800; color:#6366f1; margin-top:4px;"><?= number_format($totalLogs) ?></div>
        </div>
        <div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:var(--shadow-xs); ">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Today's Activity</div>
            <div style="font-size:26px; font-weight:800; color:#10b981; margin-top:4px;"><?= number_format($todayLogs) ?></div>
        </div>
        <div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:var(--shadow-xs); ">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Unique Actors</div>
            <div style="font-size:26px; font-weight:800; color:#f59e0b; margin-top:4px;"><?= $uniqueUsers ?></div>
        </div>
        <div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:var(--shadow-xs); ">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Top Action</div>
            <div style="font-size:16px; font-weight:800; color:#ec4899; margin-top:4px;"><?= htmlspecialchars($actionTypes[0]['action'] ?? 'N/A') ?></div>
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
    <div style="font-size:12px; color:var(--text-muted); text-align:right;">Showing <?= count($logs) ?> of <?= number_format($totalLogs) ?> total events</div>
</div>

<?php require_once 'includes/footer.php'; ?>
