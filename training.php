<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_training');

$isAdmin = hasPermission($pdo, 'manage_training');

// Auto-Migrate schema
$pdo->exec("CREATE TABLE IF NOT EXISTS training_courses (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT, description TEXT, quiz_json TEXT, passing_score INTEGER DEFAULT 70)");
$pdo->exec("CREATE TABLE IF NOT EXISTS training_modules (id INTEGER PRIMARY KEY AUTO_INCREMENT, course_id INTEGER, title TEXT, content_type TEXT, content_url TEXT, sort_order INTEGER DEFAULT 0)");
$pdo->exec("CREATE TABLE IF NOT EXISTS training_assignments (id INTEGER PRIMARY KEY AUTO_INCREMENT, course_id INTEGER, user_id TEXT, status VARCHAR(255) DEFAULT 'Assigned', user_answers TEXT, score INTEGER, assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME)");
try { $pdo->exec("ALTER TABLE training_assignments ADD COLUMN user_answers TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE training_assignments ADD COLUMN score INTEGER"); } catch(Exception $e){}


if ($isAdmin) {
    $courses = $pdo->query("SELECT * FROM training_courses ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach($courses as &$c) {
        $c['modules'] = $pdo->query("SELECT * FROM training_modules WHERE course_id = {$c['id']} ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    $allUsers = $pdo->query("SELECT login_id, name FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch exams pending grading
    $pendingExams = $pdo->query("
        SELECT ta.id, ta.user_id, ta.user_answers, c.title, c.passing_score, u.name as user_name 
        FROM training_assignments ta 
        JOIN training_courses c ON ta.course_id = c.id 
        JOIN users u ON ta.user_id = u.login_id
        WHERE ta.status = 'Pending Grading'
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $my_stmt = $pdo->prepare("
        SELECT ta.id as assignment_id, ta.status, ta.expires_at, c.title, c.description, c.quiz_json, c.passing_score, ta.assigned_at 
        FROM training_assignments ta 
        JOIN training_courses c ON ta.course_id = c.id 
        WHERE ta.user_id = ? 
        ORDER BY ta.id DESC
    ");
    $my_stmt->execute([$_SESSION['login_id']]);
    $my_courses = $my_stmt->fetchAll(PDO::FETCH_ASSOC);
    // Load modules for each course so startCourse() has chapter data
    foreach ($my_courses as &$mc) {
        $modQ = $pdo->prepare("SELECT * FROM training_modules WHERE course_id = (SELECT course_id FROM training_assignments WHERE id = ?) ORDER BY sort_order ASC");
        $modQ->execute([$mc['assignment_id']]);
        $mc['modules'] = $modQ->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($mc);
}
?>
<div class="content-section active">
    <!-- ADMIN VIEW -->
    <?php if($isAdmin): ?>
    <div class="section-header">
        <h2>Corporate Training Hub (LMS)</h2>
        <button class="add-button" onclick="openCourseModal()">+ Add Course</button>
    </div>
    
    <div class="dashboard-grid">
        <?php foreach($courses as $c): ?>
            <?php
            // Calculate stats
            $assigned = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE course_id = {$c['id']}")->fetchColumn();
            $completed = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE course_id = {$c['id']} AND status = 'Completed'")->fetchColumn();
            $percent = $assigned > 0 ? round(($completed / $assigned) * 100) : 0;
            ?>
            <div class="dashboard-card" style=" min-height: 200px; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <h3 style="font-size: 1.2rem; color: #111827; margin-bottom: 8px;"><?= htmlspecialchars($c['title']) ?></h3>
                    <p style="font-size: 0.9rem; color: #6b7280; font-weight:normal; line-height:1.4;"><?= htmlspecialchars(substr($c['description'],0,100)).'...' ?></p>
                </div>
                <div>
                    <div style="font-size: 0.8rem; margin-top: 15px; color:#4f46e5; font-weight:600;">Completion: <?= $percent ?>% (<?= $completed ?>/<?= $assigned ?>)</div>
                    <div style="width: 100%; background: #e5e7eb; height: 6px; border-radius: 4px; margin-top: 5px; margin-bottom: 15px;">
                        <div style="width: <?= $percent ?>%; background: #10b981; height: 100%; border-radius: 4px;"></div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button onclick='openAssignModal(<?= $c['id'] ?>)' class="edit-button" style="flex:1;">Assign</button>
                        <button onclick='openCourseModal(<?= json_encode($c) ?>)' class="edit-button" style="background:#5a2d82; color:white;">Edit</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if(empty($courses)): ?>
            <p style="color:#6b7280; grid-column:span 4;">No training courses built yet.</p>
        <?php endif; ?>
    </div>

    <!-- PENDING GRADING SECTION -->
    <?php if(count($pendingExams) > 0): ?>
    <div class="section-header" style="margin-top:40px;">
        <h2>📝 Manager Evaluation Portal (Pending Exams)</h2>
    </div>
    <div style="background:white; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.05); overflow:hidden; border:1px solid #e5e7eb;">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Employee</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Course</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Action Required</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendingExams as $pe): ?>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:15px;">
                        <div style="font-weight:600; color:#111827;"><?= htmlspecialchars($pe['user_name']) ?></div>
                        <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($pe['user_id']) ?></div>
                    </td>
                    <td style="padding:15px; font-weight:500;"><?= htmlspecialchars($pe['title']) ?></td>
                    <td style="padding:15px;">
                        <button onclick='openGradeModal(<?= json_encode($pe) ?>)' class="edit-button" style="background:#f59e0b; color:white;">Evaluate Essays</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- STANDARD USER VIEW -->
    <?php else: ?>
    <div class="section-header">
        <h2>My Training Dashboard</h2>
    </div>
    
    <div class="dashboard-grid">
        <?php foreach($my_courses as $c): ?>
            <div class="dashboard-card" style="">
                <h3 style="font-size: 1.2rem; color: #111827; margin-bottom: 8px;"><?= htmlspecialchars($c['title']) ?></h3>
                <p style="font-size: 0.9rem; color: #6b7280; font-weight:normal; line-height:1.4; margin-bottom: 15px;"><?= htmlspecialchars(substr($c['description'],0,100)).'...' ?></p>
                
                <span class="status-badge status-<?= strtolower(str_replace(' ','', $c['status'])) ?>"><?= htmlspecialchars($c['status']) ?></span>
                
                <div style="margin-top: 20px;">
                    <button class="submit" style="width:100%; cursor:pointer;" onclick='startCourse(<?= json_encode($c) ?>)'>
                        <?= $c['status'] == 'Completed' ? 'Review Material' : '▶ Start Course' ?>
                    </button>
                    <?php if($c['status'] === 'Completed'): ?>
                    <a href="controllers/training_certificate.php?id=<?= $c['assignment_id'] ?>" target="_blank" style="display:block;text-align:center;padding:8px;margin-top:8px;background:#f0fdf4;color:#16a34a;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;border:1px solid #bbf7d0;">🏆 View Certificate</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if(empty($my_courses)): ?>
            <p style="color:#6b7280; grid-column:span 4;">You have no assigned training courses right now. Great job!</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Course Modal -->
<div id="courseModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close-modal" onclick="document.getElementById('courseModal').style.display='none'">&times;</span>
        <h2 id="modalTitle">Course Setup</h2>
        <form id="courseForm" method="POST" action="controllers/save_course.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="course_id">
            <div class="form-group">
                <label>Course Title</label>
                <input type="text" name="title" id="course_title" required>
            </div>
            <div class="form-group">
                <label>Course Description</label>
                <textarea name="description" id="course_desc" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Compliance Expiration</label>
                <select name="expiration_months" id="course_expiration">
                    <option value="0">Never Expires</option>
                    <option value="6">Expires in 6 Months</option>
                    <option value="12">Expires Annually (12 Months)</option>
                    <option value="24">Expires every 2 Years</option>
                </select>
            </div>
            
            <div id="courseModulesContainer" style="border:1px solid var(--border-card); padding:20px; border-radius:12px; background:var(--bg-card); margin-bottom:24px; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid var(--border-card); padding-bottom:12px; margin-bottom:16px;">
                    <h3 style="font-size:16px; margin:0; color:var(--text-heading); display:flex; align-items:center; gap:8px;">📚 Curriculum Modules</h3>
                    <button type="button" onclick="addModule()" style="padding:8px 16px; background:rgba(59,130,246,0.1); color:#3b82f6; border:1px dashed #3b82f6; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; transition:all 0.2s;" onmouseover="this.style.background='rgba(59,130,246,0.2)'" onmouseout="this.style.background='rgba(59,130,246,0.1)'">+ Add Chapter</button>
                </div>
                <div id="modulesList"></div>
                <textarea name="modules_json" id="course_modules" style="display:none;"></textarea>
            </div>
            <div class="form-group">
                <label>Passing Score (%)</label>
                <input type="number" name="passing_score" id="course_passing" value="80" min="0" max="100" style="font-size:16px; font-weight:bold;">
            </div>
            <div class="form-group">
                <label>Quiz Builder (Optional Exam)</label>
                <div id="quizBuilderContainer" style="border:1px solid var(--border-card); padding:20px; border-radius:12px; background:var(--bg-card); min-height:100px; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid var(--border-card); padding-bottom:12px; margin-bottom:16px;">
                        <h3 style="font-size:16px; margin:0; color:var(--text-heading); display:flex; align-items:center; gap:8px;">🧠 Assessment Questions</h3>
                        <button type="button" onclick="addQuizQuestion()" style="padding:8px 16px; background:rgba(16,185,129,0.1); color:#10b981; border:1px dashed #10b981; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; transition:all 0.2s;" onmouseover="this.style.background='rgba(16,185,129,0.2)'" onmouseout="this.style.background='rgba(16,185,129,0.1)'">+ Add Question</button>
                    </div>
                    <div id="quizQuestions"></div>
                    <textarea name="quiz_json" id="course_quiz" style="display:none;"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="submit">Save Course Framework</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Course Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="document.getElementById('assignModal').style.display='none'">&times;</span>
        <h2>Enroll Employees</h2>
        <form method="POST" action="controllers/assign_course.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="course_id" id="assign_course_id">
            <div class="form-group">
                <label>Select Target Employees</label>
                <select name="assigned_users[]" multiple required style="height: 150px;">
                    <option value="ALL" style="font-weight:bold; color:#4f46e5;">* Assign to ALL Users</option>
                    <?php if($isAdmin): foreach($allUsers as $u): ?>
                        <option value="<?= htmlspecialchars($u['login_id']) ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['login_id']) ?>)</option>
                    <?php endforeach; endif; ?>
                </select>
                <small style="color:#6b7280; display:block; margin-top:5px;">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="submit">Execute Batch Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- Evaluation/Grading Modal -->
<div id="gradeModal" class="modal">
    <div class="modal-content" style="max-width: 700px; padding:0; background:#f8fafc; overflow:hidden;">
        <div style="padding:20px; background:#1e293b; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:18px;">📝 Evaluate Exam: <span id="gradeCourseTitle"></span></h2>
            <span class="close-modal" style="color:white; cursor:pointer; font-size:24px;" onclick="document.getElementById('gradeModal').style.display='none'">&times;</span>
        </div>
        
        <div style="padding:20px; max-height:500px; overflow-y:auto;" id="gradeAnswersContainer">
            <!-- Answers injected here -->
        </div>

        <form method="POST" action="controllers/grade_course.php" style="padding:20px; background:white; border-top:1px solid #e2e8f0; margin:0; display:flex; flex-direction:column; gap:15px;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="assignment_id" id="grade_assignment_id">
            
            <div style="display:flex; justify-content:space-between; align-items:center; gap:15px;">
                <div style="flex:1;">
                    <label style="font-size:12px; font-weight:bold; color:#64748b; margin-bottom:4px; display:block;">Overall Score (Percentage)</label>
                    <input type="number" name="final_score" id="grade_final_score" required min="0" max="100" style="padding:8px; border-radius:6px; border:1px solid #cbd5e1; width:100px; font-weight:bold;">
                    <span style="font-size:12px; color:#64748b; margin-left:10px;">Required to Pass: <strong id="gradeReqScore"></strong>%</span>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="decision" value="Reject" style="padding:10px 20px; background:#ef4444; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">❌ Fail & Re-Assign</button>
                    <button type="submit" name="decision" value="Approve" style="padding:10px 20px; background:#10b981; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">✅ Certify Pass</button>
                </div>
            </div>

            <div>
                <label style="font-size:12px; font-weight:bold; color:#64748b; margin-bottom:4px; display:block;">Feedback Notes (Optional, sent to employee)</label>
                <textarea name="feedback_notes" rows="3" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; font-family:inherit;"></textarea>
            </div>
        </form>
    </div>
</div>

<!-- Video Player Modal -->
<div id="videoModal" class="modal">
    <div class="modal-content" style="max-width: 1100px; padding: 0; background: #0f172a; position:relative; display:flex; min-height:600px;">
        <span class="close-modal" style="position:absolute; top: 10px; right: 20px; color:white; z-index:99;" onclick="document.getElementById('videoModal').style.display='none'">&times;</span>
        
        <!-- Left Sidebar: Modules -->
        <div id="playerSidebar" style="width:250px; background:#1e293b; border-right:1px solid #334155; display:flex; flex-direction:column;">
            <div style="padding:20px; border-bottom:1px solid #334155;">
                <h3 id="videoTitle" style="color:white; margin:0; font-size:16px;">Course Title</h3>
            </div>
            <div id="playerModulesList" style="flex:1; overflow-y:auto; padding:10px;">
                <!-- Modules injected here -->
            </div>
            <div style="padding:20px; border-top:1px solid #334155;">
                <button id="takeExamBtn" style="display:none; width:100%; background:#4f46e5; color:white; padding:10px; border:none; border-radius:6px; font-weight:bold; cursor:pointer;" onclick="renderExam()">📝 Final Exam</button>
            </div>
        </div>

        <!-- Right Pane: Media -->
        <div style="flex:1; display:flex; flex-direction:column; background:#000;">
            <div style="padding: 10px 20px; display:flex; justify-content:flex-end;">
                <div id="mediaTabs" style="display:none; gap:10px;">
                    <button onclick="switchMedia('video')" id="tabVideo" style="padding:4px 10px; background:#4f46e5; color:white; border:none; border-radius:4px; font-size:12px; cursor:pointer;">Video</button>
                    <button onclick="switchMedia('slides')" id="tabSlides" style="padding:4px 10px; background:#374155; color:white; border:none; border-radius:4px; font-size:12px; cursor:pointer;">Slides</button>
                </div>
            </div>
            
            <div id="mediaContainer" style="flex:1; position:relative; min-height:400px;">
                <iframe id="videoFrame" style="position:absolute; top:0; left:0; width:100%; height:100%; border:none; background:#000;" allowfullscreen></iframe>
                <iframe id="slidesFrame" style="position:absolute; top:0; left:0; width:100%; height:100%; border:none; background:#fff; display:none;" allowfullscreen></iframe>
            </div>

            <div id="quizContainer" style="display:none; padding:30px; background:white; color:black; flex:1; overflow-y:auto; border-bottom-right-radius:8px;"></div>
            
            <div id="videoFooter" style="padding: 15px 20px; background: #1e293b; display:flex; justify-content:flex-end;">
                <form method="POST" action="controllers/complete_course.php" id="completeForm" style="margin:0; display:none;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="assignment_id" id="video_assignment_id">
                    <input type="hidden" name="user_score" id="computed_score">
                    <input type="hidden" name="user_answers" id="user_answers_json">
                    <button type="button" class="submit" id="btnScoreSubmit" onclick="this.parentElement.submit()" style="background:#10b981; font-weight:700; box-shadow:none;">✅ Mark Course as Completed</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let activeQuiz = null;

function openCourseModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Corporate Course" : "Add New Training Module";
    document.getElementById('course_id').value = data ? data.id : '';
    document.getElementById('course_title').value = data ? data.title : '';
    document.getElementById('course_desc').value = data ? data.description : '';
    document.getElementById('course_passing').value = data ? data.passing_score : '80';
    document.getElementById('course_expiration').value = data && data.expiration_months ? data.expiration_months : '0';
    
    // Module Builder Rendering
    document.getElementById('modulesList').innerHTML = '';
    if (data && data.modules && data.modules.length > 0) {
        data.modules.forEach(m => addModule(m));
    } else {
        addModule(); // one default
    }

    // Quiz Builder Rendering
    document.getElementById('quizQuestions').innerHTML = '';
    if (data && data.quiz_json && data.quiz_json !== '[]') {
        try {
            const qs = JSON.parse(data.quiz_json);
            qs.forEach(q => addQuizQuestion(q));
        } catch(e) {}
    }

    document.getElementById('courseModal').style.display='block';
}

function openGradeModal(data) {
    document.getElementById('gradeCourseTitle').textContent = data.title;
    document.getElementById('grade_assignment_id').value = data.id;
    document.getElementById('gradeReqScore').textContent = data.passing_score;
    
    let html = '';
    if(data.user_answers) {
        try {
            const answers = JSON.parse(data.user_answers);
            
            // Auto-calculate objective score out of total
            let preScore = 0;
            let objectiveCount = 0;
            
            answers.forEach((a, idx) => {
                html += `<div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">`;
                html += `<div style="font-weight:600; color:#1e293b; margin-bottom:10px;">Q${idx+1}: ${a.q}</div>`;
                
                if (a.is_essay) {
                    html += `<div style="background:#f1f5f9; padding:12px; border-radius:6px; font-family:monospace; font-size:13px; color:#334155; white-space:pre-wrap;">${a.answer || '<em>(No Answer Provided)</em>'}</div>`;
                    html += `<div style="margin-top:10px; text-align:right;"><span style="font-size:11px; background:#fef3c7; color:#d97706; padding:2px 6px; border-radius:4px; font-weight:bold;">Manual Grading Required</span></div>`;
                } else {
                    objectiveCount++;
                    // Basic display logic for objective: it passed passing_score constraint earlier or its an exact match logic if we saved correct ans.
                    // For now, since user_answers doesn't store the exact right/wrong state, we just display the raw input.
                    html += `<div style="background:#f0fdf4; color:#16a34a; padding:12px; border-radius:6px; font-size:13px; font-weight:500;">Selected Option Index: [${a.answer}]</div>`;
                }
                html += `</div>`;
            });
            
            // Just a default empty suggestion for score since we don't know manager's weight.
            document.getElementById('grade_final_score').value = '';
            
        } catch(e) {
            html = `<p style="color:red;">Error parsing JSON answers.</p>`;
        }
    } else {
        html = `<p>No answers recorded.</p>`;
    }
    
    document.getElementById('gradeAnswersContainer').innerHTML = html;
    document.getElementById('gradeModal').style.display = 'block';
}

// ── Module Builder ──────────────────────────────────────────────
function addModule(data = null) {
    const list = document.getElementById('modulesList');
    const idx = list.children.length + 1;
    let html = `
    <div class="module-block" style="background:var(--bg-body); padding:20px; border:1px solid var(--border-card); border-radius:12px; margin-bottom:15px; position:relative; color:var(--text-body); box-shadow:0 4px 6px rgba(0,0,0,0.02); transition:all 0.2s ease;">
        <button type="button" onclick="this.parentElement.remove()" style="position:absolute; top:12px; right:12px; background:rgba(239,68,68,0.1); border:none; color:#ef4444; width:28px; height:28px; border-radius:50%; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'" title="Delete Module">&times;</button>
        
        <div style="font-weight:800; font-size:13px; margin-bottom:12px; color:var(--text-heading); text-transform:uppercase; letter-spacing:0.05em;">Chapter / Module ${idx}</div>
        
        <input type="text" class="m-title" required placeholder="Enter module title..." value="${data ? data.chapter_title.replace(/"/g, '&quot;') : ''}" style="width:100%; padding:10px 14px; border-radius:8px; margin-bottom:15px; border:2px solid var(--input-border); background:var(--input-bg); color:var(--text-body); font-size:14px; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--input-border)'">
        
        <div style="display:flex; gap:15px; flex-wrap:wrap;">
            <div style="flex:1; min-width:200px;">
                <div style="font-size:11px; margin-bottom:6px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">🎥 Video Embed URL</div>
                <input type="text" class="m-video" value="${data && data.video_url ? data.video_url : ''}" placeholder="https://youtube.com/embed/..." style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body); font-size:13px;">
            </div>
            <div style="flex:1; min-width:200px;">
                <div style="font-size:11px; margin-bottom:6px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">📊 Slides Embed URL (Optional)</div>
                <input type="text" class="m-slides" value="${data && data.slides_url ? data.slides_url : ''}" placeholder="https://docs.google.com/..." style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body); font-size:13px;">
            </div>
        </div>
    </div>`;
    const div = document.createElement('div');
    div.innerHTML = html;
    list.appendChild(div.firstElementChild);
}

// ── Visual Quiz Builder ──────────────────────────────────────────────
function addQuizQuestion(data = null) {
    const list = document.getElementById('quizQuestions');
    const idx = list.children.length;
    let html = `
    <div class="quiz-q-block" style="background:var(--bg-body); padding:20px; border:1px solid var(--border-card); border-radius:12px; margin-bottom:15px; position:relative; color:var(--text-body); box-shadow:0 4px 6px rgba(0,0,0,0.02); transition:all 0.2s ease;">
        <button type="button" onclick="this.parentElement.remove()" style="position:absolute; top:12px; right:12px; background:rgba(239,68,68,0.1); border:none; color:#ef4444; width:28px; height:28px; border-radius:50%; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'" title="Delete Question">&times;</button>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-right:30px;">
            <label style="font-size:13px; font-weight:800; color:var(--text-heading); text-transform:uppercase; letter-spacing:0.05em;">Question ${idx + 1}</label>
            <label style="font-size:12px; cursor:pointer; color:var(--primary-color); font-weight:700; background:rgba(90,45,130,0.1); padding:4px 10px; border-radius:20px; display:flex; align-items:center; gap:6px;">
                <input type="checkbox" class="q-is-essay" ${data && data.is_essay ? 'checked' : ''} onchange="toggleEssayOpts(this)" style="margin:0;"> Open-Ended Essay
            </label>
        </div>
        
        <input type="text" class="q-text" placeholder="Enter your question here..." required value="${data ? data.q.replace(/"/g, '&quot;') : ''}" style="width:100%; padding:12px 16px; font-size:14px; border-radius:8px; margin-bottom:15px; border:2px solid var(--input-border); background:var(--input-bg); color:var(--text-body); transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--input-border)'">
        
        <div class="opt-list" style="${data && data.is_essay ? 'display:none;' : ''}">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Answer Options (Select Correct Answer)</label>
            <div class="opt-container">`;
    
    const opts = (data && data.opts) ? data.opts : ['',''];
    const ans = (data && data.ans !== undefined) ? data.ans : 0;
    
    opts.forEach((o, i) => {
        html += `<div style="display:flex; gap:10px; margin-bottom:8px; align-items:center; background:var(--bg-card); padding:8px 12px; border-radius:8px; border:1px solid var(--border-card);">
            <input type="radio" name="temp_ans_${idx}" value="${i}" ${ans === i ? 'checked' : ''} style="margin:0; width:18px; height:18px; accent-color:var(--primary-color); cursor:pointer;" title="Mark as Correct Answer">
            <input type="text" class="q-opt" placeholder="Option ${i+1}" value="${o.replace(/"/g, '&quot;')}" style="flex:1; min-width:0; padding:8px 12px; font-size:13px; border-radius:6px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body);">
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#9ca3af; cursor:pointer; font-size:16px; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:4px;" onmouseover="this.style.background='rgba(0,0,0,0.05)'; this.style.color='#ef4444';" onmouseout="this.style.background='none'; this.style.color='#9ca3af';" title="Remove Option">✕</button>
        </div>`;
    });
    
    html += `</div>
        <button type="button" class="btn-add-opt" onclick="addQuizOpt(this, ${idx})" style="font-size:12px; padding:6px 14px; border-radius:6px; border:1px dashed var(--primary-color); background:rgba(90,45,130,0.05); color:var(--primary-color); cursor:pointer; margin-top:8px; font-weight:600; transition:all 0.2s;" onmouseover="this.style.background='rgba(90,45,130,0.1)'" onmouseout="this.style.background='rgba(90,45,130,0.05)'" ${data && data.is_essay ? 'display:none;' : ''}>+ Add Option</button>
        </div>
    </div>`;
    
    const div = document.createElement('div');
    div.innerHTML = html;
    list.appendChild(div.firstElementChild);
}

function addQuizOpt(btn, qIdx) {
    const optContainer = btn.previousElementSibling;
    const optIdx = optContainer.children.length;
    const div = document.createElement('div');
    div.style.cssText = 'display:flex; gap:10px; margin-bottom:8px; align-items:center; background:var(--bg-card); padding:8px 12px; border-radius:8px; border:1px solid var(--border-card);';
    div.innerHTML = `
        <input type="radio" name="temp_ans_${qIdx}" value="${optIdx}" style="margin:0; width:18px; height:18px; accent-color:var(--primary-color); cursor:pointer;" title="Mark as Correct Answer">
        <input type="text" class="q-opt" required placeholder="Option ${optIdx+1}" style="flex:1; min-width:0; padding:8px 12px; font-size:13px; border-radius:6px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body);">
        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#9ca3af; cursor:pointer; font-size:16px; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:4px;" onmouseover="this.style.background='rgba(0,0,0,0.05)'; this.style.color='#ef4444';" onmouseout="this.style.background='none'; this.style.color='#9ca3af';" title="Remove Option">✕</button>
    `;
    optContainer.appendChild(div);
}

function toggleEssayOpts(checkbox) {
    const block = checkbox.closest('.quiz-q-block');
    const optList = block.querySelector('.opt-list');
    const addOptBtn = block.querySelector('.btn-add-opt');
    const qOpts = block.querySelectorAll('.q-opt');
    
    if (checkbox.checked) {
        optList.style.display = 'none';
        addOptBtn.style.display = 'none';
        qOpts.forEach(o => o.required = false);
    } else {
        optList.style.display = 'block';
        addOptBtn.style.display = 'inline-block';
        qOpts.forEach(o => o.required = true);
    }
}

// Hook into form submit to compile quiz JSON
document.getElementById('courseForm').addEventListener('submit', function(e) {
    const blocks = document.querySelectorAll('.quiz-q-block');
    const quizArr = [];
    let valid = true;
    blocks.forEach((b, idx) => {
        const qText = b.querySelector('.q-text').value;
        const isEssay = b.querySelector('.q-is-essay').checked;
        
        if (isEssay) {
            quizArr.push({ q: qText, is_essay: true });
        } else {
            const optInputs = b.querySelectorAll('.q-opt');
            const opts = [];
            optInputs.forEach(oi => opts.push(oi.value));
            const checked = b.querySelector(`input[type="radio"]:checked`);
            if (!checked) {
                alert('Please select a correct answer for question: ' + qText);
                valid = false;
                return;
            }
            const radioIndex = Array.from(b.querySelectorAll('input[type="radio"]')).indexOf(checked);
            quizArr.push({ q: qText, opts: opts, ans: radioIndex, is_essay: false });
        }
    });

    // Compile Modules
    const mods = document.querySelectorAll('.module-block');
    const modArr = [];
    mods.forEach((m, idx) => {
        modArr.push({
            chapter_title: m.querySelector('.m-title').value,
            video_url: m.querySelector('.m-video').value,
            slides_url: m.querySelector('.m-slides').value,
            sort_order: idx + 1
        });
    });
    
    if(modArr.length === 0) {
        alert('You must add at least 1 module.');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
        return;
    }
    
    document.getElementById('course_quiz').value = JSON.stringify(quizArr);
    document.getElementById('course_modules').value = JSON.stringify(modArr);
});

function openAssignModal(courseId) {
    document.getElementById('assign_course_id').value = courseId;
    document.getElementById('assignModal').style.display='block';
}

function startCourse(data) {
    document.getElementById('videoTitle').textContent = data.title;
    document.getElementById('video_assignment_id').value = data.assignment_id;
    
    // Render Sidebar Modules
    const modList = document.getElementById('playerModulesList');
    modList.innerHTML = '';
    
    let firstMod = null;
    let chapters = data.modules || [];
    
    // Handle legacy fallback if data has no modules but has video URL
    if (chapters.length === 0 && data.video_url) {
        chapters.push({ chapter_title: 'Main Content', video_url: data.video_url, slides_url: data.slides_url });
    }
    
    chapters.forEach((m, idx) => {
        if(idx === 0) firstMod = m;
        let btn = document.createElement('button');
        btn.textContent = `${idx + 1}. ${m.chapter_title}`;
        btn.style.cssText = "display:block; width:100%; text-align:left; padding:10px; margin-bottom:5px; background:transparent; border:none; color:#cbd5e1; font-size:13px; cursor:pointer; border-radius:4px;";
        btn.onmouseover = function() { this.style.background = '#334155'; };
        btn.onmouseout = function() { this.style.background = 'transparent'; };
        
        btn.onclick = function() {
            loadPlayerMedia(m);
            // highlight logic
            Array.from(modList.children).forEach(c => { c.style.fontWeight='normal'; c.style.color='#cbd5e1'; });
            this.style.fontWeight='bold';
            this.style.color='#fff';
        };
        modList.appendChild(btn);
    });

    if (firstMod) loadPlayerMedia(firstMod);
    if(modList.children[0]) {
        modList.children[0].style.fontWeight='bold';
        modList.children[0].style.color='#fff';
    }

    document.getElementById('quizContainer').style.display = 'none';
    document.getElementById('mediaContainer').style.display = 'block';
    document.getElementById('computed_score').value = '';
    document.getElementById('user_answers_json').value = '';
    
    activeQuiz = null;
    let hasQuiz = data.quiz_json && data.quiz_json.trim() !== '' && data.quiz_json !== '[]';
    
    document.getElementById('takeExamBtn').style.display = 'none';
    document.getElementById('completeForm').style.display = 'none';

    if(data.status !== 'Completed') {
        if(hasQuiz) {
            activeQuiz = JSON.parse(data.quiz_json);
            document.getElementById('takeExamBtn').style.display = 'block';
        } else {
            document.getElementById('completeForm').style.display = 'block';
        }
    }
    
    document.getElementById('videoModal').style.display='flex';
}

function loadPlayerMedia(m) {
    document.getElementById('videoFrame').src = m.video_url || '';
    if (m.slides_url && m.slides_url.trim() !== '') {
        document.getElementById('slidesFrame').src = m.slides_url;
        document.getElementById('mediaTabs').style.display = 'flex';
        switchMedia('video');
    } else {
        document.getElementById('slidesFrame').src = '';
        document.getElementById('mediaTabs').style.display = 'none';
        switchMedia('video');
    }
}

function switchMedia(type) {
    if(type === 'video') {
        document.getElementById('videoFrame').style.display = 'block';
        document.getElementById('slidesFrame').style.display = 'none';
        document.getElementById('tabVideo').style.background = '#4f46e5';
        document.getElementById('tabSlides').style.background = '#374151';
    } else {
        document.getElementById('videoFrame').style.display = 'none';
        document.getElementById('slidesFrame').style.display = 'block';
        document.getElementById('tabVideo').style.background = '#374151';
        document.getElementById('tabSlides').style.background = '#4f46e5';
    }
}

function renderExam() {
    document.getElementById('takeExamBtn').style.display = 'none';
    document.getElementById('mediaContainer').style.display = 'none';
    document.getElementById('videoFrame').src = ''; // Stop video
    document.getElementById('slidesFrame').src = ''; // Stop slides
    document.getElementById('quizContainer').style.display = 'block';
    
    let html = '<h3>Final Examination</h3><hr style="margin: 15px 0; border:0; border-top:1px solid #eee;">';
    activeQuiz.forEach((q, qIndex) => {
        html += `<div style="margin-bottom:20px;" class="exam-q-row" data-is-essay="${q.is_essay ? 'true' : 'false'}">
            <p style="font-weight:600; margin-bottom:10px;">${qIndex + 1}. ${q.q}</p>`;
        
        if (q.is_essay) {
            html += `<textarea name="q_${qIndex}" rows="4" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:10px; font-family:inherit;"></textarea>`;
        } else {
            q.opts.forEach((opt, oIndex) => {
                html += `<label style="display:block; margin-bottom:5px; cursor:pointer;">
                    <input type="radio" name="q_${qIndex}" value="${oIndex}">${opt}
                </label>`;
            });
        }
        html += `</div>`;
    });
    html += `<button type="button" class="submit" onclick="submitExam()" style="width:100%;">Submit Exam & Calculate</button>`;
    document.getElementById('quizContainer').innerHTML = html;
}

function submitExam() {
    let score = 0;
    let allAnswered = true;
    let hasEssay = false;
    let userAnswers = [];
    
    activeQuiz.forEach((q, qIndex) => {
        let block = document.querySelectorAll('.exam-q-row')[qIndex];
        let answerData = { q: q.q, is_essay: q.is_essay, answer: null };

        if (q.is_essay) {
            hasEssay = true;
            let val = block.querySelector(`textarea[name="q_${qIndex}"]`).value.trim();
            if(!val) allAnswered = false;
            answerData.answer = val;
        } else {
            let selected = block.querySelector(`input[name="q_${qIndex}"]:checked`);
            if(!selected) allAnswered = false;
            else {
                answerData.answer = parseInt(selected.value);
                if(answerData.answer === q.ans) score++;
            }
        }
        userAnswers.push(answerData);
    });
    
    if(!allAnswered) return alert("Please answer all questions before submitting.");
    
    // Only count objective questions in denominator (essays are manual)
    const objectiveQuestions = activeQuiz.filter(q => !q.is_essay);
    let percent = objectiveQuestions.length > 0 ? Math.round((score / objectiveQuestions.length) * 100) : 100;
    document.getElementById('computed_score').value = percent;
    document.getElementById('user_answers_json').value = JSON.stringify(userAnswers);
    
    if (hasEssay) {
        document.getElementById('quizContainer').innerHTML = `<div style="text-align:center; padding:30px;">
            <h2>Exam Submitted</h2>
            <p style="color:#f59e0b; font-weight:bold;">Pending Manager Grading</p>
            <p>Your essay portions have been securely transmitted to your manager for physical review.</p>
        </div>`;
        document.getElementById('btnScoreSubmit').textContent = "✅ File for Review";
    } else {
        document.getElementById('quizContainer').innerHTML = `<div style="text-align:center; padding:30px;">
            <h2>Exam Concluded</h2>
            <p>Your calculated score is: <strong>${percent}%</strong></p>
            <p>Your attempt will securely log when you click submit below.</p>
        </div>`;
    }
    document.getElementById('completeForm').style.display = 'block';
}

// Clear iframe src when closing video modal to stop playback
document.querySelectorAll('#videoModal .close-modal').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('videoFrame').src = '';
        document.getElementById('slidesFrame').src = '';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

