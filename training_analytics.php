<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_training');

$course_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM training_courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Invalid course.");
}

$assignments = $pdo->prepare("
    SELECT ta.*, u.name as user_name, u.email, tr.score
    FROM training_assignments ta
    JOIN users u ON ta.user_id = u.login_id
    LEFT JOIN training_results tr ON ta.id = tr.assignment_id
    WHERE ta.course_id = ?
    ORDER BY ta.assigned_at DESC
");
$assignments->execute([$course_id]);
$enrollments = $assignments->fetchAll(PDO::FETCH_ASSOC);

$totalEnrolled = count($enrollments);
$completedCount = 0; $passedCount = 0; $totalScoreSum = 0; $scoredCount = 0;
$tierExcellent = 0; $tierGood = 0; $tierSatisfactory = 0; $tierFailed = 0;

foreach($enrollments as $e) {
    if($e['status'] === 'Completed') $completedCount++;
    if(isset($e['score']) && $e['score'] !== null) {
        $sc = floatval($e['score']);
        $totalScoreSum += $sc;
        $scoredCount++;
        if($sc >= ($course['passing_score'] ?? 70)) $passedCount++;

        if($sc >= 90) $tierExcellent++;
        elseif($sc >= 75) $tierGood++;
        elseif($sc >= 60) $tierSatisfactory++;
        else $tierFailed++;
    }
}
$avgScore = $scoredCount > 0 ? round($totalScoreSum / $scoredCount, 1) : 0;
$completionPct = $totalEnrolled > 0 ? round(($completedCount / $totalEnrolled) * 100) : 0;
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">📊 Course Analytics: <?= htmlspecialchars($course['title']) ?></h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Detailed employee enrollment progress, exam scores, and completion metrics.</p>
        </div>
        <button class="edit-button" onclick="window.location.href='training.php'" style="padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600;">
            ← Back to Training Hub
        </button>
    </div>

    <!-- Top Course Analytics Cards -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Total Enrolled</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format($totalEnrolled) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Assigned Employees</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Completion Rate</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= $completionPct ?>%</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;"><?= $completedCount ?> of <?= $totalEnrolled ?> Finished</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Average Exam Score</div>
            <div style="font-size:28px; font-weight:800; color:#6366f1;"><?= $avgScore ?>%</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Passing Score: <?= $course['passing_score'] ?? 70 ?>%</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Passed Exam Rate</div>
            <div style="font-size:28px; font-weight:800; color:#3b82f6;"><?= $scoredCount > 0 ? round(($passedCount / $scoredCount)*100) : 0 ?>%</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;"><?= $passedCount ?> Passed / <?= $scoredCount ?> Evaluated</div>
        </div>
    </div>

    <!-- Exam Performance Grade Tier Distribution -->
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:16px; padding:24px; margin-bottom:28px;">
        <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:700; color:var(--text-heading);">🎯 Workforce Exam Performance Tiers</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div style="background:rgba(16, 185, 129, 0.08); border:1px solid rgba(16, 185, 129, 0.2); border-radius:12px; padding:16px;">
                <div style="font-size:11px; font-weight:700; color:#10b981; text-transform:uppercase;">🌟 Excellent (90% - 100%)</div>
                <div style="font-size:24px; font-weight:800; color:#10b981; margin-top:4px;"><?= $tierExcellent ?> Staff</div>
            </div>

            <div style="background:rgba(59, 130, 246, 0.08); border:1px solid rgba(59, 130, 246, 0.2); border-radius:12px; padding:16px;">
                <div style="font-size:11px; font-weight:700; color:#3b82f6; text-transform:uppercase;">🟢 Good (75% - 89%)</div>
                <div style="font-size:24px; font-weight:800; color:#3b82f6; margin-top:4px;"><?= $tierGood ?> Staff</div>
            </div>

            <div style="background:rgba(245, 158, 11, 0.08); border:1px solid rgba(245, 158, 11, 0.2); border-radius:12px; padding:16px;">
                <div style="font-size:11px; font-weight:700; color:#f59e0b; text-transform:uppercase;">🟡 Satisfactory (60% - 74%)</div>
                <div style="font-size:24px; font-weight:800; color:#f59e0b; margin-top:4px;"><?= $tierSatisfactory ?> Staff</div>
            </div>

            <div style="background:rgba(239, 68, 68, 0.08); border:1px solid rgba(239, 68, 68, 0.2); border-radius:12px; padding:16px;">
                <div style="font-size:11px; font-weight:700; color:#ef4444; text-transform:uppercase;">🔴 Needs Retaking (< 60%)</div>
                <div style="font-size:24px; font-weight:800; color:#ef4444; margin-top:4px;"><?= $tierFailed ?> Staff</div>
            </div>
        </div>
    </div>

    <div style="background:white; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.05); overflow:hidden; border:1px solid #e5e7eb; margin-top: 20px;">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Employee</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Status</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Assigned At</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Completed At</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Score</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($enrollments as $e): ?>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:15px;">
                        <div style="font-weight:600; color:#111827;"><?= htmlspecialchars($e['user_name']) ?></div>
                        <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($e['user_id']) ?></div>
                    </td>
                    <td style="padding:15px;">
                        <span class="status-badge status-<?= strtolower(str_replace(' ','', $e['status'])) ?>"><?= htmlspecialchars($e['status']) ?></span>
                    </td>
                    <td style="padding:15px; color:#6b7280; font-size:13px;"><?= $e['assigned_at'] ? date('M d, Y H:i', strtotime($e['assigned_at'])) : '-' ?></td>
                    <td style="padding:15px; color:#6b7280; font-size:13px;"><?= $e['completed_at'] ? date('M d, Y H:i', strtotime($e['completed_at'])) : '-' ?></td>
                    <td style="padding:15px; font-weight:bold;">
                        <?= $e['score'] !== null ? $e['score'].'%' : '-' ?>
                    </td>
                    <td style="padding:15px;">
                        <?php if(!empty($e['user_answers'])): ?>
                        <button onclick='openAnalysisModal(<?= json_encode($e) ?>)' style="padding:6px 12px; background:#f59e0b; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600;">📊 Analysis</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($enrollments)): ?>
                <tr>
                    <td colspan="5" style="padding:20px; text-align:center; color:#6b7280;">No employees are currently enrolled in this course.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Evaluation/Grading Modal (Read Only) -->
<div id="gradeModal" class="modal">
    <div class="modal-content" style="max-width: 700px; padding:0; background:#f8fafc; overflow:hidden;">
        <div style="padding:20px; background:#1e293b; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:18px;">📝 Exam Analysis: <span id="gradeCourseTitle"></span></h2>
            <span class="close-modal" style="color:white; cursor:pointer; font-size:24px;" onclick="document.getElementById('gradeModal').style.display='none'">&times;</span>
        </div>
        <div style="padding:20px; max-height:500px; overflow-y:auto;" id="gradeAnswersContainer">
            <!-- Answers injected here -->
        </div>
        <div style="padding:20px; background:white; border-top:1px solid #e2e8f0; margin:0;">
            <div style="font-size:14px; font-weight:bold; color:#64748b;">Final Score: <span id="grade_final_score_display" style="color:#111827; font-size:18px;"></span>% (Required: <span id="gradeReqScore"></span>%)</div>
        </div>
    </div>
</div>

<script>
function openAnalysisModal(data) {
    document.getElementById('gradeCourseTitle').textContent = data.user_name + " - " + "background:white; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">`;
                html += `<div style="font-weight:600; color:#1e293b; margin-bottom:10px;">Q${idx+1}: ${a.q}</div>`;
                
                if (a.is_essay) {
                    html += `<div style="background:#f1f5f9; padding:12px; border-radius:6px; font-family:monospace; font-size:13px; color:#334155; white-space:pre-wrap;">${a.answer || '<em>(No Answer Provided)</em>'}</div>`;
                } else {
                    let isCorrect = a.is_correct;
                    let color = isCorrect ? '#16a34a' : '#dc2626';
                    let bgColor = isCorrect ? '#f0fdf4' : '#fef2f2';
                    let label = isCorrect ? 'Correct' : 'Incorrect';
                    
                    let displayAns = a.answer;
                    if(a.type === 'mcq' && Array.isArray(a.answer)) {
                        displayAns = a.answer.map(x => `Option ${x+1}`).join(', ');
                    } else if ((a.type === 'single' || !a.type) && typeof a.answer === 'number') {
                        displayAns = `Option ${a.answer+1}`;
                    }
                    
                    html += `<div style="background:${bgColor}; color:${color}; padding:12px; border-radius:6px; font-size:13px; font-weight:500;">User Answer: [${displayAns}]</div>`;
                    
                    if (!isCorrect && originalQ) {
                        let correctAns = originalQ.ans;
                        if(originalQ.type === 'mcq' && Array.isArray(correctAns)) {
                            correctAns = correctAns.map(x => `Option ${x+1}`).join(', ');
                        } else if ((originalQ.type === 'single' || !originalQ.type) && typeof correctAns === 'number') {
                            correctAns = `Option ${correctAns+1}`;
                        }
                        html += `<div style="margin-top:8px; font-size:12px; color:#475569;">Correct Answer was: <strong>${correctAns}</strong></div>`;
                    }
                    
                    html += `<div style="margin-top:10px; text-align:right;"><span style="font-size:11px; font-weight:bold; color:${color};">${label}</span></div>`;
                }
                html += `</div>`;
            });
            
        } catch(e) {
            html = `<p style="color:red;">Error parsing JSON answers.</p>`;
        }
    } else {
        html = `<p>No answers recorded.</p>`;
    }
    
    document.getElementById('gradeAnswersContainer').innerHTML = html;
    document.getElementById('gradeModal').style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == document.getElementById('gradeModal')) {
        document.getElementById('gradeModal').style.display = "none";
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
