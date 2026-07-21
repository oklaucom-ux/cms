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
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📊 Analytics: <?= htmlspecialchars($course['title']) ?></h2>
        <button class="edit-button" onclick="window.location.href='training.php'">Back to Training Hub</button>
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
    document.getElementById('gradeCourseTitle').textContent = data.user_name + " - " + <?= json_encode($course['title']) ?>;
    document.getElementById('gradeReqScore').textContent = <?= json_encode($course['passing_score']) ?>;
    document.getElementById('grade_final_score_display').textContent = data.score !== null ? data.score : 'N/A';
    
    let html = '';
    if(data.user_answers) {
        try {
            const answers = JSON.parse(data.user_answers);
            let originalQuiz = null;
            let quizJson = <?= json_encode($course['quiz_json']) ?>;
            if(quizJson) {
                try { originalQuiz = JSON.parse(quizJson); } catch(e){}
            }
            
            answers.forEach((a, idx) => {
                let originalQ = originalQuiz ? originalQuiz[idx] : null;
                html += `<div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">`;
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
