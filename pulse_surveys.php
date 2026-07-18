<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_surveys');

// Auto-migrate schema
try {
    $pk = (isset($use_mysql) && $use_mysql) ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS pulse_surveys (
        id $pk,
        question TEXT NOT NULL,
        status VARCHAR(255) DEFAULT 'Active',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS pulse_responses (
        id $pk,
        survey_id INTEGER NOT NULL,
        score INTEGER NOT NULL,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Fallback: silently add columns if table was created by older baseline with different schema
    try {
        $pdo->exec("ALTER TABLE pulse_responses ADD COLUMN score INTEGER");
        $pdo->exec("ALTER TABLE pulse_responses ADD COLUMN comment TEXT");
        $pdo->exec("ALTER TABLE pulse_surveys ADD COLUMN question TEXT");
    } catch (Exception $e) {
        // Ignore if columns already exist
    }
} catch (Exception $e) {}

$isHR = hasPermission($pdo, 'manage_users') || in_array($_SESSION['role'], ['Admin', 'Super Admin']);

// Fetch active survey for employee to answer
$activeSurvey = $pdo->query("SELECT * FROM pulse_surveys WHERE status = 'Active' ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// If HR, fetch all surveys and stats
$allSurveys = [];
if ($isHR) {
    $surveys = $pdo->query("SELECT * FROM pulse_surveys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($surveys as $s) {
        // Calculate eNPS: % Promoters (9-10) - % Detractors (0-6)
        $resp = $pdo->prepare("SELECT score FROM pulse_responses WHERE survey_id = ?");
        $resp->execute([$s['id']]);
        $scores = $resp->fetchAll(PDO::FETCH_COLUMN);
        
        $total = count($scores);
        $promoters = 0; $detractors = 0;
        foreach($scores as $sc) {
            if($sc >= 9) $promoters++;
            elseif($sc <= 6) $detractors++;
        }
        
        $enps = 0;
        if($total > 0) {
            $enps = round((($promoters / $total) - ($detractors / $total)) * 100);
        }
        
        $s['total_responses'] = $total;
        $s['enps'] = $enps;
        $allSurveys[] = $s;
    }
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📊 Employee Pulse (eNPS)</h2>
        <?php if($isHR): ?>
        <button class="add-button" onclick="document.getElementById('surveyModal').style.display='flex'">+ New Pulse Survey</button>
        <?php endif; ?>
    </div>

    <!-- Active Survey for Employee -->
    <?php if($activeSurvey): ?>
    <div style="background:white; border-radius:12px; padding:30px; border:1px solid #e2e8f0; box-shadow:0 10px 25px rgba(0,0,0,0.05); text-align:center; max-width:800px; margin:0 auto 40px auto;">
        <div style="font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase; margin-bottom:10px;">Active Anonymous Survey</div>
        <h3 style="font-size:24px; color:#1e293b; margin-top:0; margin-bottom:20px;"><?= htmlspecialchars($activeSurvey['question']) ?></h3>
        
        <form method="POST" action="controllers/save_survey.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="submit_response">
            <input type="hidden" name="survey_id" value="<?= $activeSurvey['id'] ?>">
            
            <div style="display:flex; justify-content:space-between; max-width:600px; margin:0 auto 20px auto; gap:5px;">
                <?php for($i=0; $i<=10; $i++): 
                    $color = $i <= 6 ? '#fee2e2' : ($i <= 8 ? '#fef3c7' : '#d1fae5');
                    $border = $i <= 6 ? '#ef4444' : ($i <= 8 ? '#f59e0b' : '#10b981');
                ?>
                <label style="flex:1; cursor:pointer;">
                    <input type="radio" name="score" value="<?= $i ?>" required style="display:none;" id="score_<?= $i ?>">
                    <div class="score-box" style="background:<?= $color ?>; border:2px solid <?= $border ?>; border-radius:8px; padding:15px 0; font-size:20px; font-weight:bold; transition:transform 0.1s;" onclick="document.querySelectorAll('.score-box').forEach(b=>b.style.transform='scale(1)'); this.style.transform='scale(1.1)';">
                        <?= $i ?>
                    </div>
                </label>
                <?php endfor; ?>
            </div>
            
            <div style="display:flex; justify-content:space-between; max-width:600px; margin:0 auto 20px auto; font-size:12px; color:#64748b; font-weight:bold;">
                <span>Not Likely (0)</span>
                <span>Very Likely (10)</span>
            </div>
            
            <textarea name="comment" rows="3" placeholder="Optional: Tell us why you gave this score..." style="width:100%; max-width:600px; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;"></textarea>
            
            <div>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:12px 30px; border-radius:6px; font-weight:bold; font-size:16px; cursor:pointer;">Submit Anonymous Feedback</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- HR Dashboard -->
    <?php if($isHR): ?>
    <h3 style="color:var(--text-heading);">Historical Pulse Surveys</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px, 1fr)); gap:20px;">
        <?php foreach($allSurveys as $s): ?>
        <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <h4 style="margin:0 0 10px 0; font-size:16px;"><?= htmlspecialchars($s['question']) ?></h4>
                <span style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; <?= $s['status']=='Active' ? 'background:#d1fae5; color:#065f46;' : 'background:#f3f4f6; color:#475569;' ?>">
                    <?= $s['status'] ?>
                </span>
            </div>
            <div style="font-size:12px; color:#64748b; margin-bottom:15px;">Launched: <?= substr($s['created_at'], 0, 10) ?></div>
            
            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1; background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <div style="font-size:24px; font-weight:900; color:<?= $s['enps'] > 0 ? '#10b981' : ($s['enps'] < 0 ? '#ef4444' : '#f59e0b') ?>;">
                        <?= $s['enps'] ?>
                    </div>
                    <div style="font-size:11px; color:#64748b; font-weight:bold;">eNPS SCORE</div>
                </div>
                <div style="flex:1; background:#f8fafc; padding:15px; border-radius:8px; text-align:center;">
                    <div style="font-size:24px; font-weight:900; color:#1e293b;"><?= $s['total_responses'] ?></div>
                    <div style="font-size:11px; color:#64748b; font-weight:bold;">RESPONSES</div>
                </div>
            </div>
            
            <?php if($s['status'] === 'Active'): ?>
            <form method="POST" action="controllers/save_survey.php" onsubmit="return confirm('Close this survey?')">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="close_survey">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" style="width:100%; background:#f1f5f9; color:#475569; border:none; padding:8px; border-radius:6px; font-weight:bold; cursor:pointer;">Close Survey</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if($isHR): ?>
<!-- Create Survey Modal -->
<div class="modal" id="surveyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Launch Pulse Survey</h2>
        <p style="font-size:13px; color:#64748b; margin-bottom:20px;">Launching a new survey will automatically close any currently active survey.</p>
        <form method="POST" action="controllers/save_survey.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Survey Question</label>
            <input type="text" name="question" required value="On a scale of 0-10, how likely are you to recommend working here?" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;">
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('surveyModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Launch Survey</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

