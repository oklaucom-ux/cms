<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if(!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>Admin privileges required.</p></div>");
}

$events = ['lead_created', 'task_completed', 'invoice_paid', 'project_created'];

try {
    $use_mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $pk = $use_mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS webhooks (
        id $pk,
        event_name TEXT NOT NULL,
        payload_url TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$stmt = $pdo->query("SELECT * FROM webhooks ORDER BY created_at DESC");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🔗 Advanced Webhooks Engine</h2>
        <p style="color:var(--text-muted);">Push real-time CMS events to external platforms like Zapier, Pabbly Connect, or Make.com.</p>
    </div>

    <div style="background:white; padding:20px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.02); border:1px solid #e2e8f0; margin-bottom:30px;">
        <h3 style="margin-top:0;">Register New Webhook</h3>
        <form method="POST" action="controllers/webhook_api.php" style="display:flex; gap:15px; align-items:flex-end;">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div style="flex:1;">
                <label style="display:block; font-size:12px; font-weight:bold; color:#64748b; margin-bottom:5px;">Trigger Event</label>
                <select name="event_name" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
                    <?php foreach($events as $e): ?>
                        <option value="<?= $e ?>"><?= $e ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex:2;">
                <label style="display:block; font-size:12px; font-weight:bold; color:#64748b; margin-bottom:5px;">Payload URL (POST)</label>
                <input type="url" name="payload_url" required placeholder="https://hooks.zapier.com/hooks/catch/..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
            </div>
            
            <button type="submit" class="add-button" style="background:#4f46e5; height:40px; padding:0 20px;">+ Add Webhook</button>
        </form>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event Trigger</th>
                    <th>Payload URL</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($webhooks as $wh): ?>
                <tr>
                    <td><?= $wh['id'] ?></td>
                    <td><span style="background:#e0e7ff; color:#4f46e5; padding:4px 8px; border-radius:4px; font-size:12px; font-family:monospace; font-weight:bold;"><?= htmlspecialchars($wh['event_name']) ?></span></td>
                    <td style="font-family:monospace; font-size:13px;"><?= htmlspecialchars($wh['payload_url']) ?></td>
                    <td>
                        <span style="color:<?= $wh['is_active'] ? '#10b981' : '#ef4444' ?>; font-weight:bold; font-size:12px;">
                            <?= $wh['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="controllers/webhook_api.php" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $wh['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" onclick="return confirm('Delete this webhook?');" style="background:#fee2e2; color:#ef4444; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($webhooks)): ?>
                <tr><td colspan="5" style="text-align:center;">No webhooks registered yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
