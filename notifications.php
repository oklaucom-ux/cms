<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/flash.php';

$me = $_SESSION['login_id'];

// Mark all as read when page is visited
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$me]);

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
$notifs->execute([$me]);
$notifs = $notifs->fetchAll(PDO::FETCH_ASSOC);

$canBroadcast = hasPermission($pdo, 'manage_broadcasts') || hasPermission($pdo, 'manage_users');
$roles = $pdo->query("SELECT role_name FROM roles")->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query("SELECT login_id, name FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content-section active">
    <div class="section-header"><h2>🔔 Notification Centre</h2></div>
    
    <?php if($canBroadcast): ?>
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:12px; padding:24px; margin-bottom:32px; box-shadow:0 10px 25px rgba(0,0,0,0.05); max-width:700px;">
        <h3 style="margin-top:0; color:var(--text-heading); font-size:16px; margin-bottom:16px;">📡 Corporate Broadcast Engine</h3>
        <form method="POST" action="controllers/push_broadcast.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div class="form-group" style="margin:0;">
                    <label>Target Audience</label>
                    <select name="target_type" id="targetType" onchange="toggleTargetParams()" required style="background:var(--bg-body);">
                        <option value="ALL">Entire Company</option>
                        <option value="ROLE">Specific Role</option>
                        <option value="USER">Individual Employee</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin:0; display:none;" id="targetRoleContainer">
                    <label>Select Role</label>
                    <select name="target_role" style="background:var(--bg-body);">
                        <?php foreach($roles as $r): ?><option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin:0; display:none;" id="targetUserContainer">
                    <label>Select Employee</label>
                    <select name="target_user" style="background:var(--bg-body);">
                        <?php foreach($users as $u): ?><option value="<?= htmlspecialchars($u['login_id']) ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['login_id']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notification Title</label>
                <input type="text" name="title" required placeholder="e.g. Critical Server Maintenance" style="background:var(--bg-body);">
            </div>
            
            <div class="form-group">
                <label>Message Body</label>
                <textarea name="body" required placeholder="Type the alert details..." style="background:var(--bg-body); height:80px;"></textarea>
            </div>
            
            <div class="form-group">
                <label>Action Link (Optional)</label>
                <input type="text" name="link" placeholder="e.g. https://payroll.company.com or tasks.php" style="background:var(--bg-body);">
            </div>
            
            <div style="text-align:right;">
                <button type="submit" class="submit" style="background:#ef4444; padding:10px 20px;">🚀 Push Notification</button>
            </div>
        </form>
    </div>
    <script>
    function toggleTargetParams() {
        const t = document.getElementById('targetType').value;
        document.getElementById('targetRoleContainer').style.display = (t === 'ROLE') ? 'block' : 'none';
        document.getElementById('targetUserContainer').style.display = (t === 'USER') ? 'block' : 'none';
    }
    </script>
    <?php endif; ?>

    <?php if(empty($notifs)): ?>
        <div style="text-align:center;padding:60px;color:var(--text-muted);">
            <div style="font-size:48px;margin-bottom:16px;">🎉</div>
            <h3>All caught up!</h3><p>No notifications yet.</p>
        </div>
    <?php else: ?>
    <div style="max-width:700px;">
        <?php foreach($notifs as $n):
            $timeago = round((time() - strtotime($n['created_at']))/60);
            $ago = $timeago < 60 ? $timeago.'m ago' : round($timeago/60).'h ago';
        ?>
        <div style="background:var(--bg-card);border-radius:12px;padding:16px 20px;margin-bottom:10px;border:1px solid var(--border-card);display:flex;justify-content:space-between;align-items:flex-start;box-shadow:0 4px 6px rgba(0,0,0,0.05); transition:transform 0.2s;">
            <div>
                <div style="font-weight:700;color:var(--text-heading);margin-bottom:4px;"><?= htmlspecialchars($n['title']) ?></div>
                <?php if($n['body']): ?><div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($n['body']) ?></div><?php endif; ?>
                <?php if($n['link']): ?><a href="<?= htmlspecialchars($n['link']) ?>" style="font-size:12px;color:var(--primary-color, #6366f1);text-decoration:none;margin-top:6px;display:inline-block;font-weight:600;">View →</a><?php endif; ?>
            </div>
            <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;margin-left:16px;"><?= $ago ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>

