<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_rewards');

// Auto-migrate schema
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS kudos (
        id {$pkDef},
        sender_id VARCHAR(255) NOT NULL,
        receiver_id VARCHAR(255) NOT NULL,
        points INT NOT NULL,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS points_ledger (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        points INT NOT NULL,
        reason TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add cyno_points to users if missing
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN cyno_points INT DEFAULT 0");
    } catch (Exception $e) {}
} catch (Exception $e) {}

$myId = $_SESSION['login_id'];

// Get all active users & super admins to send kudos to
$userStmt = $pdo->prepare("
    SELECT login_id, name FROM users WHERE status='Active' AND login_id != ?
    UNION
    SELECT login_id, name FROM super_admins WHERE login_id != ?
    ORDER BY name ASC
");
$userStmt->execute([$myId, $myId]);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Leaderboard (supporting both users and super_admins with strict MySQL ONLY_FULL_GROUP_BY compatibility)
$leaderboard = $pdo->query("
    SELECT COALESCE(MAX(u.name), MAX(sa.name), k.receiver_id) as name, SUM(k.points) as total_points 
    FROM kudos k 
    LEFT JOIN users u ON k.receiver_id = u.login_id 
    LEFT JOIN super_admins sa ON k.receiver_id = sa.login_id 
    GROUP BY k.receiver_id 
    ORDER BY total_points DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get Recent Kudos Stream (supporting both users and super_admins for sender & receiver)
$stream = $pdo->query("
    SELECT k.*, 
           COALESCE(s.name, sa_s.name, k.sender_id) as sender_name, 
           COALESCE(r.name, sa_r.name, k.receiver_id) as receiver_name 
    FROM kudos k 
    LEFT JOIN users s ON k.sender_id = s.login_id 
    LEFT JOIN super_admins sa_s ON k.sender_id = sa_s.login_id
    LEFT JOIN users r ON k.receiver_id = r.login_id 
    LEFT JOIN super_admins sa_r ON k.receiver_id = sa_r.login_id
    ORDER BY k.created_at DESC 
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// My Points (Kudos)
$myPoints = $pdo->prepare("SELECT SUM(points) FROM kudos WHERE receiver_id = ?");
$myPoints->execute([$myId]);
$myScore = $myPoints->fetchColumn() ?: 0;

// My Cyno Points (System)
$cynoPointsStmt = $pdo->prepare("SELECT cyno_points FROM users WHERE login_id = ?");
$cynoPointsStmt->execute([$myId]);
$myCynoPoints = $cynoPointsStmt->fetchColumn() ?: 0;

// System Points Ledger (parameterized)
$systemLedgerStmt = $pdo->prepare("SELECT * FROM points_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$systemLedgerStmt->execute([$myId]);
$systemLedger = $systemLedgerStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🏆 Peer Rewards & Kudos</h2>
        <button class="add-button" onclick="document.getElementById('kudosModal').style.display='flex'">🌟 Give Kudos</button>
    </div>

    <div style="display:grid; grid-template-columns:300px 1fr; gap:20px;">
        
        <!-- Sidebar / Leaderboard -->
        <div>
            <div style="background:linear-gradient(135deg, #4f46e5, #c026d3); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 10px 15px -3px rgba(79, 70, 229, 0.4);">
                <div style="font-size:12px; font-weight:bold; text-transform:uppercase; opacity:0.8;">My Total Kudos</div>
                <div style="font-size:36px; font-weight:900;"><?= $myScore ?> <span style="font-size:14px; font-weight:normal;">pts</span></div>
            </div>

            <div style="background:linear-gradient(135deg, #10b981, #059669); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 10px 15px -3px rgba(16, 185, 129, 0.4);">
                <div style="font-size:12px; font-weight:bold; text-transform:uppercase; opacity:0.8;">My Cyno Points (System)</div>
                <div style="font-size:36px; font-weight:900;"><?= $myCynoPoints ?> <span style="font-size:14px; font-weight:normal;">pts</span></div>
                <button onclick="alert('Rewards store coming soon! You can redeem points for gift cards and company swag.');" style="margin-top: 10px; background: white; color: #059669; border: none; padding: 6px 12px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 12px;">Redeem Points</button>
            </div>
            
            <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; padding:20px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <h3 style="margin-top:0; color:var(--text-heading); display:flex; align-items:center; gap:8px;">💳 Points Ledger</h3>
                <?php foreach($systemLedger as $sl): ?>
                <div style="padding:10px 0; border-bottom:1px solid #f1f5f9;">
                    <div style="font-weight:600; color:#1e293b; font-size: 13px;"><?= htmlspecialchars($sl['reason']) ?></div>
                    <div style="display:flex; justify-content:space-between; margin-top: 4px;">
                        <span style="font-size: 11px; color: #94a3b8;"><?= $sl['created_at'] ?></span>
                        <span style="font-weight:bold; color:#10b981; font-size: 12px;">+<?= $sl['points'] ?> pts</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($systemLedger)) echo "<p style='color:#64748b; font-size:13px;'>No system points earned yet.</p>"; ?>
            </div>
            <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <h3 style="margin-top:0; color:var(--text-heading); display:flex; align-items:center; gap:8px;">🏅 Top Performers</h3>
                <?php foreach($leaderboard as $idx =>$l): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-weight:bold; color:#94a3b8; width:20px;">#<?= $idx + 1 ?></span>
                        <span style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($l['name']) ?></span>
                    </div>
                    <span style="font-weight:bold; color:#10b981;"><?= $l['total_points'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if(empty($leaderboard)) echo "<p style='color:#64748b; font-size:13px;'>No points awarded yet.</p>"; ?>
            </div>
        </div>
        
        <!-- Kudos Feed -->
        <div>
            <h3 style="color:var(--text-heading); margin-top:0;">Company Activity Stream</h3>
            <div style="display:flex; flex-direction:column; gap:15px;">
                <?php foreach($stream as $k): ?>
                <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02); display:flex; gap:15px;">
                    <div style="font-size:32px;">🌟</div>
                    <div style="flex:1;">
                        <div style="font-size:14px; margin-bottom:5px;">
                            <strong style="color:#1e293b;"><?= htmlspecialchars($k['sender_name']) ?></strong> 
                            <span style="color:#64748b;">awarded</span> 
                            <strong style="color:#4f46e5;"><?= $k['points'] ?> pts</strong> 
                            <span style="color:#64748b;">to</span> 
                            <strong style="color:#1e293b;"><?= htmlspecialchars($k['receiver_name']) ?></strong>
                        </div>
                        <div style="font-size:15px; color:#334155; font-style:italic;">"<?= htmlspecialchars($k['message']) ?>"</div>
                        <div style="font-size:11px; color:#94a3b8; margin-top:8px;"><?= $k['created_at'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($stream)) echo "<p style='color:#64748b;'>No activity yet. Be the first to send kudos!</p>"; ?>
            </div>
        </div>

    </div>
</div>

<!-- Give Kudos Modal -->
<div class="modal" id="kudosModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Give Kudos</h2>
        <form method="POST" action="controllers/save_kudos.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Who deserves recognition?</label>
            <select name="receiver_id" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
                <?php foreach($users as $u): ?>
                <option value="<?= $u['login_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Points to Award</label>
            <select name="points" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
                <option value="10">10 pts - Small Help</option>
                <option value="50">50 pts - Great Job</option>
                <option value="100">100 pts - Going Above & Beyond</option>
            </select>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Why are you giving them Kudos?</label>
            <textarea name="message" required rows="3" placeholder="Thanks for helping me fix that bug..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;"></textarea>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('kudosModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Send Kudos</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
