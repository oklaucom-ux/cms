<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'manage_reviews');

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_reviews (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        cycle_name TEXT NOT NULL,
        self_assessment_text TEXT,
        self_score INTEGER,
        manager_id TEXT,
        manager_feedback TEXT,
        manager_score INTEGER,
        score_tech INTEGER DEFAULT 0,
        score_comm INTEGER DEFAULT 0,
        score_lead INTEGER DEFAULT 0,
        status VARCHAR(255) DEFAULT 'Pending Self',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE performance_reviews ADD COLUMN score_tech INTEGER DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE performance_reviews ADD COLUMN score_comm INTEGER DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE performance_reviews ADD COLUMN score_lead INTEGER DEFAULT 0"); } catch(Exception $e){}
} catch (Exception $e) {}

$isHR = hasPermission($pdo, 'manage_users') || in_array($_SESSION['role'], ['Admin', 'Super Admin']);
$myId = $_SESSION['login_id'];

// Fetch my self-assessments
$stmt = $pdo->prepare("SELECT * FROM performance_reviews WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$myId]);
$myReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch reviews I need to sign off as a manager
$stmt2 = $pdo->prepare("SELECT pr.*, u.name as employee_name FROM performance_reviews pr JOIN users u ON pr.user_id = u.login_id WHERE pr.manager_id = ? ORDER BY pr.created_at DESC");
$stmt2->execute([$myId]);
$managerReviews = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// If HR, fetch all for overview
$allReviews = [];
if ($isHR) {
    $allReviews = $pdo->query("SELECT pr.*, u.name as employee_name, m.name as manager_name FROM performance_reviews pr JOIN users u ON pr.user_id = u.login_id LEFT JOIN users m ON pr.manager_id = m.login_id ORDER BY pr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📈 360° Performance Reviews</h2>
        <?php if($isHR): ?>
        <button class="add-button" onclick="document.getElementById('cycleModal').style.display='flex'">+ Initiate Review Cycle</button>
        <?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        
        <!-- My Reviews -->
        <div>
            <h3 style="color:var(--text-heading);">My Self-Assessments</h3>
            <?php foreach($myReviews as $r): ?>
            <div style="background:white; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:15px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h4 style="margin:0; font-size:16px;"><?= htmlspecialchars($r['cycle_name']) ?></h4>
                    <span style="font-size:12px; font-weight:bold; padding:4px 8px; border-radius:4px; background:<?= $r['status']==='Completed' ? '#d1fae5; color:#065f46;' : '#fef3c7; color:#92400e;' ?>">
                        <?= $r['status'] ?>
                    </span>
                </div>
                
                <?php if($r['status'] === 'Pending Self'): ?>
                <form method="POST" action="controllers/save_review.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="self_assessment">
                    
                    <label style="font-size:12px; font-weight:bold; color:#64748b; margin-top:10px; display:block;">Self Assessment</label>
                    <textarea name="self_text" required rows="4" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:5px;" placeholder="Highlight your key achievements..."></textarea>
                    
                    <label style="font-size:12px; font-weight:bold; color:#64748b; margin-top:10px; display:block;">Self Score (1-5)</label>
                    <select name="self_score" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:5px;">
                        <option value="5">5 - Exceptional</option>
                        <option value="4">4 - Exceeds Expectations</option>
                        <option value="3" selected>3 - Meets Expectations</option>
                        <option value="2">2 - Needs Improvement</option>
                        <option value="1">1 - Unsatisfactory</option>
                    </select>
                    
                    <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; margin-top:15px; font-weight:bold; cursor:pointer;">Submit to Manager</button>
                </form>
                <?php else: ?>
                    <p style="font-size:13px; color:#475569;"><strong>My Score:</strong> <?= $r['self_score'] ?>/5</p>
                    <p style="font-size:13px; color:#475569;"><strong>Manager Score:</strong> <?= $r['manager_score'] ? $r['manager_score'].'/5' : 'Pending' ?></p>
                    <?php if($r['manager_feedback']): ?>
                    <div style="background:#f1f5f9; padding:10px; border-radius:6px; font-size:13px; color:#334155; margin-top:10px;">
                        <strong>Manager Feedback:</strong><br>
                        <?= nl2br(htmlspecialchars($r['manager_feedback'])) ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if(empty($myReviews)) echo "<p style='color:#64748b;'>No reviews assigned to you.</p>"; ?>
        </div>
        
        <!-- Manager Sign-offs -->
        <div>
            <h3 style="color:var(--text-heading);">Manager Sign-Offs</h3>
            <?php foreach($managerReviews as $r): ?>
            <div style="background:white; border:1px solid #e2e8f0;  padding:20px; border-radius:12px; margin-bottom:15px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h4 style="margin:0; font-size:16px;">👤 <?= htmlspecialchars($r['employee_name']) ?></h4>
                    <span style="font-size:12px; color:#64748b; font-weight:bold;"><?= htmlspecialchars($r['cycle_name']) ?></span>
                </div>
                
                <?php if($r['status'] === 'Pending Self'): ?>
                    <p style="font-size:13px; color:#f59e0b; font-weight:bold;">⏳ Waiting for Employee Self-Assessment</p>
                <?php elseif($r['status'] === 'Pending Manager'): ?>
                    <div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0; font-size:13px; color:#475569; margin-bottom:15px;">
                        <strong>Employee Self Score:</strong> <?= $r['self_score'] ?>/5<br>
                        <strong>Self Assessment:</strong><br>
                        <?= nl2br(htmlspecialchars($r['self_assessment_text'])) ?>
                    </div>
                    
                    <form method="POST" action="controllers/save_review.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="action" value="manager_signoff">
                        
                        <label style="font-size:12px; font-weight:bold; color:#64748b; display:block;">Manager Feedback</label>
                        <textarea name="manager_text" required rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:5px;"></textarea>
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-top:15px;">
                            <div>
                                <label style="font-size:11px; font-weight:bold; color:#64748b;">Tech/Execution</label>
                                <select name="score_tech" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                                    <option value="5">5 - Excels</option><option value="4">4 - High</option><option value="3" selected>3 - Meets</option><option value="2">2 - Low</option><option value="1">1 - Fails</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:bold; color:#64748b;">Communication</label>
                                <select name="score_comm" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                                    <option value="5">5 - Excels</option><option value="4">4 - High</option><option value="3" selected>3 - Meets</option><option value="2">2 - Low</option><option value="1">1 - Fails</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:bold; color:#64748b;">Leadership</label>
                                <select name="score_lead" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                                    <option value="5">5 - Excels</option><option value="4">4 - High</option><option value="3" selected>3 - Meets</option><option value="2">2 - Low</option><option value="1">1 - Fails</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" style="background:#10b981; color:white; border:none; padding:8px 16px; border-radius:6px; margin-top:15px; font-weight:bold; cursor:pointer;">Sign Off Review</button>
                    </form>
                <?php else: ?>
                    <p style="font-size:13px; color:#10b981; font-weight:bold;">✅ Completed & Signed Off</p>
                    <p style="font-size:13px; color:#475569;"><strong>Final Overall Score:</strong> <?= $r['manager_score'] ?>/5</p>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:11px;">🛠️ Tech: <?= $r['score_tech'] ?>/5</span>
                        <span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:11px;">🗣️ Comm: <?= $r['score_comm'] ?>/5</span>
                        <span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:11px;">👑 Lead: <?= $r['score_lead'] ?>/5</span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if(empty($managerReviews)) echo "<p style='color:#64748b;'>No team members awaiting your review.</p>"; ?>
        </div>
        
    </div>
</div>

<?php if($isHR): ?>
<!-- HR Initiate Cycle Modal -->
<div class="modal" id="cycleModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Initiate Review Cycle</h2>
        <form method="POST" action="controllers/save_review.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="initiate_cycle">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Cycle Name</label>
            <input type="text" name="cycle_name" required placeholder="e.g. Q1 2026 Annual Review" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Select Employee</label>
            <select name="user_id" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
                <?php
                $allU = $pdo->query("SELECT login_id, name FROM users WHERE status='Active'")->fetchAll();
                foreach($allU as $u) echo "<option value='{$u['login_id']}'>{$u['name']}</option>";
                ?>
            </select>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Assign Manager</label>
            <select name="manager_id" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;">
                <?php foreach($allU as $u) echo "<option value='{$u['login_id']}'>{$u['name']}</option>"; ?>
            </select>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('cycleModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Initiate</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

