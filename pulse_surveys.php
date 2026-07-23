<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_surveys');

// Auto-migrate schema
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS pulse_surveys (
        id {$pkDef},
        question TEXT NOT NULL,
        status VARCHAR(255) DEFAULT 'Active',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS pulse_responses (
        id {$pkDef},
        survey_id INT NOT NULL,
        score INT NOT NULL,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    try { $pdo->exec("ALTER TABLE pulse_responses ADD COLUMN score INT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pulse_responses ADD COLUMN comment TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pulse_surveys ADD COLUMN question TEXT"); } catch (Exception $e) {}
} catch (Exception $e) {}

$isHR = hasPermission($pdo, 'manage_users') || in_array($_SESSION['role'], ['Admin', 'Super Admin']);

// Fetch active survey for employee to answer
$activeSurvey = $pdo->query("SELECT * FROM pulse_surveys WHERE status = 'Active' ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Overall analytics metrics
$totalSurveysCount = $pdo->query("SELECT COUNT(*) FROM pulse_surveys")->fetchColumn() ?: 0;
$totalResponsesCount = $pdo->query("SELECT COUNT(*) FROM pulse_responses")->fetchColumn() ?: 0;

$allScores = $pdo->query("SELECT score FROM pulse_responses")->fetchAll(PDO::FETCH_COLUMN);
$totalScoresCount = count($allScores);
$overallPromoters = 0; $overallDetractors = 0;
foreach ($allScores as $sc) {
    if ($sc >= 9) $overallPromoters++;
    elseif ($sc <= 6) $overallDetractors++;
}
$overallEnps = 0;
if ($totalScoresCount > 0) {
    $overallEnps = round((($overallPromoters / $totalScoresCount) - ($overallDetractors / $totalScoresCount)) * 100);
}

// Fetch all surveys with stats
$allSurveys = [];
if ($isHR) {
    $surveys = $pdo->query("SELECT * FROM pulse_surveys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($surveys as $s) {
        $resp = $pdo->prepare("SELECT score FROM pulse_responses WHERE survey_id = ?");
        $resp->execute([$s['id']]);
        $scores = $resp->fetchAll(PDO::FETCH_COLUMN);
        
        $total = count($scores);
        $promoters = 0; $passives = 0; $detractors = 0;
        foreach($scores as $sc) {
            if($sc >= 9) $promoters++;
            elseif($sc >= 7) $passives++;
            else $detractors++;
        }
        
        $enps = 0;
        if($total > 0) {
            $enps = round((($promoters / $total) - ($detractors / $total)) * 100);
        }
        
        $s['total_responses'] = $total;
        $s['promoters'] = $promoters;
        $s['passives'] = $passives;
        $s['detractors'] = $detractors;
        $s['enps'] = $enps;
        $allSurveys[] = $s;
    }
}
?>

<div class="content-section active">
    <!-- Header -->
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">📊 Employee Pulse (eNPS)</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Continuous employee engagement monitoring & real-time satisfaction metrics.</p>
        </div>
        <?php if($isHR): ?>
        <button class="add-button" onclick="document.getElementById('surveyModal').style.display='flex'">
            <i class="fas fa-plus"></i> New Pulse Survey
        </button>
        <?php endif; ?>
    </div>

    <!-- Top Executive Analytics Cards -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Overall eNPS Index</div>
            <div style="font-size:28px; font-weight:800; color:<?= $overallEnps > 0 ? '#10b981' : ($overallEnps < 0 ? '#ef4444' : '#f59e0b') ?>;">
                <?= ($overallEnps > 0 ? '+' : '') . $overallEnps ?>
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Benchmark: Range -100 to +100</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Total Responses</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalResponsesCount) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Anonymous Submissions</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Surveys Conducted</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalSurveysCount) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Historical Pulse Surveys</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Active Pulse Status</div>
            <div style="font-size:16px; font-weight:700; margin-top:6px; color:<?= $activeSurvey ? '#10b981' : 'var(--text-muted)' ?>;">
                <?= $activeSurvey ? '🟢 Active Survey Running' : '⚪ No Active Survey' ?>
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:6px;"><?= $activeSurvey ? htmlspecialchars(substr($activeSurvey['question'], 0, 35)) . '...' : 'Launch a new survey above' ?></div>
        </div>
    </div>

    <!-- Active Survey for Employee -->
    <?php if($activeSurvey): ?>
    <div style="background:var(--bg-card); border-radius:16px; padding:32px; border:1px solid var(--border-card); box-shadow:var(--shadow-soft); text-align:center; max-width:850px; margin:0 auto 36px auto;">
        <span style="font-size:11px; background:rgba(79,70,229,0.1); color:#4f46e5; padding:4px 12px; border-radius:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; display:inline-block; margin-bottom:12px;">Active Anonymous Pulse Survey</span>
        <h3 style="font-size:22px; color:var(--text-heading); font-weight:700; margin-top:0; margin-bottom:24px; line-height:1.4;"><?= htmlspecialchars($activeSurvey['question']) ?></h3>
        
        <form method="POST" action="controllers/save_survey.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="submit_response">
            <input type="hidden" name="survey_id" value="<?= $activeSurvey['id'] ?>">
            
            <div style="display:flex; justify-content:space-between; max-width:650px; margin:0 auto 16px auto; gap:6px;">
                <?php for($i=0; $i<=10; $i++): 
                    $color = $i <= 6 ? 'rgba(239,68,68,0.1)' : ($i <= 8 ? 'rgba(245,158,11,0.1)' : 'rgba(16,185,129,0.1)');
                    $border = $i <= 6 ? '#ef4444' : ($i <= 8 ? '#f59e0b' : '#10b981');
                    $txtColor = $i <= 6 ? '#ef4444' : ($i <= 8 ? '#d97706' : '#10b981');
                ?>
                <label style="flex:1; cursor:pointer;">
                    <input type="radio" name="score" value="<?= $i ?>" required style="display:none;" id="score_<?= $i ?>">
                    <div class="score-box" style="background:<?= $color ?>; border:1.5px solid <?= $border ?>; color:<?= $txtColor ?>; border-radius:8px; padding:14px 0; font-size:18px; font-weight:800; transition:all 0.15s; text-align:center;" onclick="document.querySelectorAll('.score-box').forEach(b=>b.style.transform='scale(1)'); this.style.transform='scale(1.1)';">
                        <?= $i ?>
                    </div>
                </label>
                <?php endfor; ?>
            </div>
            
            <div style="display:flex; justify-content:space-between; max-width:650px; margin:0 auto 20px auto; font-size:12px; color:var(--text-muted); font-weight:600;">
                <span>0 = Not Likely</span>
                <span>10 = Extremely Likely</span>
            </div>
            
            <textarea name="comment" rows="3" placeholder="Optional: Share additional anonymous feedback or reasons for your rating..." style="width:100%; max-width:650px; padding:12px 16px; border:1px solid var(--input-border); border-radius:8px; background:var(--input-bg); color:var(--text-body); font-size:13.5px; margin-bottom:20px; outline:none;"></textarea>
            
            <div>
                <button type="submit" class="add-button" style="padding:12px 32px; font-size:15px; border-radius:8px;">Submit Anonymous Feedback</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- HR Dashboard - Historical Pulse Surveys -->
    <?php if($isHR): ?>
    <div style="margin-top:10px;">
        <h3 style="color:var(--text-heading); font-size:18px; font-weight:700; margin-bottom:16px;">Historical Pulse Surveys</h3>
        
        <?php if(count($allSurveys) === 0): ?>
            <div style="background:var(--bg-card); border-radius:12px; border:1px solid var(--border-card); padding:40px; text-align:center; color:var(--text-muted);">
                <i class="fas fa-poll-h" style="font-size:36px; opacity:0.4; margin-bottom:12px;"></i>
                <p style="margin:0; font-size:15px; font-weight:500;">No pulse surveys launched yet.</p>
                <button class="add-button" onclick="document.getElementById('surveyModal').style.display='flex'" style="margin-top:16px;">Launch First Pulse Survey</button>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
                <?php foreach($allSurveys as $s): ?>
                <div style="background:var(--bg-card); border-radius:14px; border:1px solid var(--border-card); padding:20px; box-shadow:var(--shadow-xs); display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:10px;">
                            <h4 style="margin:0; font-size:15px; font-weight:600; color:var(--text-heading); line-height:1.4;"><?= htmlspecialchars($s['question']) ?></h4>
                            <span style="padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; white-space:nowrap; <?= $s['status']=='Active' ? 'background:rgba(16,185,129,0.15); color:#10b981;' : 'background:var(--bg-hover); color:var(--text-muted);' ?>">
                                <?= $s['status'] == 'Active' ? '🟢 Active' : 'Closed' ?>
                            </span>
                        </div>
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:16px;">
                            <i class="far fa-calendar-alt"></i> Launched: <?= substr($s['created_at'], 0, 10) ?>
                        </div>
                        
                        <!-- eNPS Metric Cards -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                            <div style="background:var(--bg-main); padding:12px; border-radius:10px; text-align:center; border:1px solid var(--border-card);">
                                <div style="font-size:22px; font-weight:900; color:<?= $s['enps'] > 0 ? '#10b981' : ($s['enps'] < 0 ? '#ef4444' : '#f59e0b') ?>;">
                                    <?= ($s['enps'] > 0 ? '+' : '') . $s['enps'] ?>
                                </div>
                                <div style="font-size:10px; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-top:2px;">eNPS SCORE</div>
                            </div>
                            <div style="background:var(--bg-main); padding:12px; border-radius:10px; text-align:center; border:1px solid var(--border-card);">
                                <div style="font-size:22px; font-weight:900; color:var(--text-heading);"><?= $s['total_responses'] ?></div>
                                <div style="font-size:10px; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-top:2px;">RESPONSES</div>
                            </div>
                        </div>

                        <!-- Response Grade Breakdown -->
                        <div style="font-size:11px; color:var(--text-muted); display:flex; justify-content:space-between; margin-bottom:14px; padding:0 4px;">
                            <span style="color:#10b981; font-weight:600;">Promoters: <strong><?= $s['promoters'] ?></strong></span>
                            <span style="color:#f59e0b; font-weight:600;">Passives: <strong><?= $s['passives'] ?></strong></span>
                            <span style="color:#ef4444; font-weight:600;">Detractors: <strong><?= $s['detractors'] ?></strong></span>
                        </div>
                    </div>
                    
                    <?php if($s['status'] === 'Active'): ?>
                    <form method="POST" action="controllers/save_survey.php" onsubmit="return confirm('Close this survey?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="close_survey">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button type="submit" class="cancel" style="width:100%; justify-content:center; background:rgba(239,68,68,0.1); color:#ef4444; border-color:rgba(239,68,68,0.3); font-weight:700;">
                            <i class="fas fa-lock" style="margin-right:6px;"></i> Close Survey
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if($isHR): ?>
<!-- Create Survey Modal -->
<div class="modal" id="surveyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:var(--modal-overlay); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:var(--bg-card); padding:32px; border-radius:16px; border:1px solid var(--border-card); width:100%; max-width:540px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-size:18px; font-weight:700; color:var(--text-heading);">🚀 Launch Pulse Survey</h2>
            <button type="button" class="close-modal" onclick="document.getElementById('surveyModal').style.display='none'">×</button>
        </div>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px; line-height:1.4;">Launching a new survey will automatically close any currently active survey across the organization.</p>
        
        <form method="POST" action="controllers/save_survey.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text-body);">Survey Question</label>
                <input type="text" name="question" required value="On a scale of 0-10, how likely are you to recommend working here?" style="width:100%;">
            </div>
            
            <div class="form-actions" style="margin-top:24px;">
                <button type="button" class="cancel" onclick="document.getElementById('surveyModal').style.display='none'">Cancel</button>
                <button type="submit" class="add-button">Launch Survey</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
