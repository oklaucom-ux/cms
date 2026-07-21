<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_training');

$isAdmin = hasPermission($pdo, 'manage_training');

// Auto-Migrate schema


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
        SELECT ta.id as assignment_id, ta.status, ta.expires_at, ta.completed_modules, c.title, c.description, c.quiz_json, c.passing_score, ta.assigned_at, ta.user_answers, tr.score as final_score 
        FROM training_assignments ta 
        JOIN training_courses c ON ta.course_id = c.id 
        LEFT JOIN training_results tr ON ta.id = tr.assignment_id
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
    
    $avail_stmt = $pdo->prepare("
        SELECT * FROM training_courses 
        WHERE allow_self_enroll = 1 
        AND id NOT IN (SELECT course_id FROM training_assignments WHERE user_id = ?)
        ORDER BY id DESC
    ");
    $avail_stmt->execute([$_SESSION['login_id']]);
    $available_courses = $avail_stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <button onclick='window.location.href="training_analytics.php?id=<?= $c['id'] ?>"' class="edit-button" style="background:#0284c7; color:white;">Analytics</button>
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
    
    <div style="margin-bottom: 20px;">
        <button id="btnMyCourses" onclick="switchTrainingTab('myCourses')" style="padding:10px 20px; background:#4f46e5; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">My Courses</button>
        <button id="btnCatalog" onclick="switchTrainingTab('catalog')" style="padding:10px 20px; background:#e5e7eb; color:#374151; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Course Catalog</button>
    </div>
    
    <div id="myCoursesView" class="dashboard-grid">
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
                    <?php if(!empty($c['user_answers'])): ?>
                    <button class="submit" style="width:100%; cursor:pointer; margin-top:8px; background:#f59e0b;" onclick='openGradeModal(<?= json_encode($c) ?>, true)'>📊 View Analysis</button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if(empty($my_courses)): ?>
            <p style="color:#6b7280; grid-column:span 4;">You have no assigned training courses right now. Great job!</p>
        <?php endif; ?>
    </div>
    
    <div id="catalogView" class="dashboard-grid" style="display:none;">
        <?php foreach($available_courses as $ac): ?>
            <div class="dashboard-card" style="">
                <div style="font-size:11px; background:#e0e7ff; color:#4f46e5; padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:8px; font-weight:bold;"><?= htmlspecialchars($ac['category'] ?? 'General') ?></div>
                <h3 style="font-size: 1.2rem; color: #111827; margin-bottom: 8px;"><?= htmlspecialchars($ac['title']) ?></h3>
                <p style="font-size: 0.9rem; color: #6b7280; font-weight:normal; line-height:1.4; margin-bottom: 15px;"><?= htmlspecialchars(substr($ac['description'],0,100)).'...' ?></p>
                <form method="POST" action="controllers/self_enroll.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="course_id" value="<?= $ac['id'] ?>">
                    <button type="submit" class="submit" style="width:100%; cursor:pointer;">📥 Enroll Now</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if(empty($available_courses)): ?>
            <p style="color:#6b7280; grid-column:span 4;">No new courses available in the catalog right now.</p>
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
                <label>Category</label>
                <select name="category" id="course_category">
                    <option value="General">General</option>
                    <option value="Technical">Technical</option>
                    <option value="Compliance">Compliance</option>
                    <option value="Soft Skills">Soft Skills</option>
                    <option value="Onboarding">Onboarding</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="allow_self_enroll" id="course_self_enroll" value="1" style="width:20px; height:20px;">
                <label style="margin:0;">Allow Self-Enrollment (Visible in Course Catalog)</label>
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
            <div class="form-group">
                <label>Due Date (Optional)</label>
                <input type="datetime-local" name="due_date" id="assign_due_date">
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

        <form id="gradeActionDiv" method="POST" action="controllers/grade_course.php" style="padding:20px; background:white; border-top:1px solid #e2e8f0; margin:0; display:flex; flex-direction:column; gap:15px;">
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
            
            <div id="videoFooter" style="padding: 15px 20px; background: #1e293b; display:flex; justify-content:flex-end; gap: 10px;">
                <button id="btnMarkWatched" onclick="markModuleWatched()" style="display:none; padding:10px 20px; background:#f59e0b; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Mark Chapter as Watched</button>
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
let currentCourseData = null;
let currentModuleData = null;
let completedModules = [];

function openCourseModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Corporate Course" : "Add New Training Module";
    document.getElementById('course_id').value = data ? data.id : '';
    document.getElementById('course_title').value = data ? data.title : '';
    document.getElementById('course_desc').value = data ? data.description : '';
    document.getElementById('course_passing').value = data ? data.passing_score : '80';
    document.getElementById('course_expiration').value = data && data.expiration_months ? data.expiration_months : '0';
    document.getElementById('course_category').value = data && data.category ? data.category : 'General';
    document.getElementById('course_self_enroll').checked = data && data.allow_self_enroll == 1;
    
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

function openGradeModal(data, isReadOnly = false) {
    document.getElementById('gradeCourseTitle').textContent = data.title + (isReadOnly ? " (Results Analysis)" : "");
    document.getElementById('grade_assignment_id').value = data.id || data.assignment_id;
    document.getElementById('gradeReqScore').textContent = data.passing_score;
    
    if (isReadOnly) {
        document.getElementById('gradeActionDiv').style.display = 'none';
    } else {
        document.getElementById('gradeActionDiv').style.display = 'block';
    }
    
    let html = '';
    if(data.user_answers) {
        try {
            const answers = JSON.parse(data.user_answers);
            
            // Re-fetch the original quiz JSON from data.quiz_json to compare answers if needed
            let originalQuiz = null;
            if(data.quiz_json) {
                try { originalQuiz = JSON.parse(data.quiz_json); } catch(e){}
            }
            
            answers.forEach((a, idx) => {
                let originalQ = originalQuiz ? originalQuiz[idx] : null;
                html += `<div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">`;
                html += `<div style="font-weight:600; color:#1e293b; margin-bottom:10px;">Q${idx+1}: ${a.q}</div>`;
                
                if (a.is_essay) {
                    html += `<div style="background:#f1f5f9; padding:12px; border-radius:6px; font-family:monospace; font-size:13px; color:#334155; white-space:pre-wrap;">${a.answer || '<em>(No Answer Provided)</em>'}</div>`;
                    html += `<div style="margin-top:10px; text-align:right;"><span style="font-size:11px; background:#fef3c7; color:#d97706; padding:2px 6px; border-radius:4px; font-weight:bold;">Manual Grading Required</span></div>`;
                } else {
                    let isCorrect = a.is_correct; // We will save this in submitExam
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
                    
                    // Show correct answer if incorrect
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
            
            document.getElementById('grade_final_score').value = data.final_score !== null ? data.final_score : '';
            
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
    
    // Determine type (backward compatibility for old data)
    let type = 'single';
    if (data) {
        if (data.type) {
            type = data.type;
        } else if (data.is_essay) {
            type = 'essay';
        }
    }
    
    let html = `
    <div class="quiz-q-block" data-qindex="${idx}" style="background:var(--bg-body); padding:20px; border:1px solid var(--border-card); border-radius:12px; margin-bottom:15px; position:relative; color:var(--text-body); box-shadow:0 4px 6px rgba(0,0,0,0.02); transition:all 0.2s ease;">
        <button type="button" onclick="this.parentElement.remove()" style="position:absolute; top:12px; right:12px; background:rgba(239,68,68,0.1); border:none; color:#ef4444; width:28px; height:28px; border-radius:50%; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'" title="Delete Question">&times;</button>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-right:30px;">
            <label style="font-size:13px; font-weight:800; color:var(--text-heading); text-transform:uppercase; letter-spacing:0.05em;">Question ${idx + 1}</label>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:11px; font-weight:bold; color:var(--text-muted); text-transform:uppercase;">Question Type:</span>
                <select class="q-type" onchange="changeQuestionType(this)" style="padding:6px 12px; border-radius:6px; border:1px solid var(--input-border); background:var(--bg-card); color:var(--text-body); font-size:12px; font-weight:600; cursor:pointer;">
                    <option value="single" ${type === 'single' ? 'selected' : ''}>Single Choice (Radio)</option>
                    <option value="mcq" ${type === 'mcq' ? 'selected' : ''}>Multiple Choice (Checkboxes)</option>
                    <option value="fill_blank" ${type === 'fill_blank' ? 'selected' : ''}>Fill in the Blank</option>
                    <option value="essay" ${type === 'essay' ? 'selected' : ''}>Open-Ended Essay</option>
                </select>
            </div>
        </div>
        
        <input type="text" class="q-text" placeholder="Enter your question here..." required value="${data ? data.q.replace(/"/g, '&quot;') : ''}" style="width:100%; padding:12px 16px; font-size:14px; border-radius:8px; margin-bottom:15px; border:2px solid var(--input-border); background:var(--input-bg); color:var(--text-body); transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--input-border)'">
        
        <div class="opt-list" style="${(type === 'essay' || type === 'fill_blank') ? 'display:none;' : ''}">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Answer Options (Select Correct Answer(s))</label>
            <div class="opt-container">`;
    
    if (type === 'single' || type === 'mcq') {
        const opts = (data && data.opts) ? data.opts : ['',''];
        const ans = (data && data.ans !== undefined) ? data.ans : (type === 'single' ? 0 : []);
        
        opts.forEach((o, i) => {
            const inputType = type === 'mcq' ? 'checkbox' : 'radio';
            const isChecked = (type === 'mcq' && Array.isArray(ans)) ? ans.includes(i) : (ans === i);
            
            html += `<div style="display:flex; gap:10px; margin-bottom:8px; align-items:center; background:var(--bg-card); padding:8px 12px; border-radius:8px; border:1px solid var(--border-card);">
                <input type="${inputType}" name="temp_ans_${idx}${type === 'mcq' ? '[]' : ''}" value="${i}" ${isChecked ? 'checked' : ''} style="margin:0; width:18px; height:18px; accent-color:var(--primary-color); cursor:pointer;" title="Mark as Correct Answer">
                <input type="text" class="q-opt" placeholder="Option ${i+1}" value="${o.replace(/"/g, '&quot;')}" style="flex:1; min-width:0; padding:8px 12px; font-size:13px; border-radius:6px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body);">
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#9ca3af; cursor:pointer; font-size:16px; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:4px;" onmouseover="this.style.background='rgba(0,0,0,0.05)'; this.style.color='#ef4444';" onmouseout="this.style.background='none'; this.style.color='#9ca3af';" title="Remove Option">✕</button>
            </div>`;
        });
    }
    
    html += `</div>
        <button type="button" class="btn-add-opt" onclick="addQuizOpt(this)" style="font-size:12px; padding:6px 14px; border-radius:6px; border:1px dashed var(--primary-color); background:rgba(90,45,130,0.05); color:var(--primary-color); cursor:pointer; margin-top:8px; font-weight:600; transition:all 0.2s;" onmouseover="this.style.background='rgba(90,45,130,0.1)'" onmouseout="this.style.background='rgba(90,45,130,0.05)'" ${(type === 'essay' || type === 'fill_blank') ? 'style="display:none;"' : ''}>+ Add Option</button>
        </div>
        
        <div class="fill-blank-ans" style="${type === 'fill_blank' ? 'display:block;' : 'display:none;'}">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">Correct Answer (Exact Text Match)</label>
            <input type="text" class="q-blank-ans" placeholder="e.g. Sales" value="${(type === 'fill_blank' && data && data.ans) ? data.ans.replace(/"/g, '&quot;') : ''}" style="width:100%; padding:10px 14px; font-size:13px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body);">
        </div>
    </div>`;
    
    const div = document.createElement('div');
    div.innerHTML = html;
    list.appendChild(div.firstElementChild);
}

function addQuizOpt(btn) {
    const block = btn.closest('.quiz-q-block');
    const qIdx = block.getAttribute('data-qindex');
    const type = block.querySelector('.q-type').value;
    const inputType = type === 'mcq' ? 'checkbox' : 'radio';
    
    const optContainer = btn.previousElementSibling;
    const optIdx = optContainer.children.length;
    
    const div = document.createElement('div');
    div.style.cssText = 'display:flex; gap:10px; margin-bottom:8px; align-items:center; background:var(--bg-card); padding:8px 12px; border-radius:8px; border:1px solid var(--border-card);';
    div.innerHTML = `
        <input type="${inputType}" name="temp_ans_${qIdx}${type === 'mcq' ? '[]' : ''}" value="${optIdx}" style="margin:0; width:18px; height:18px; accent-color:var(--primary-color); cursor:pointer;" title="Mark as Correct Answer">
        <input type="text" class="q-opt" required placeholder="Option ${optIdx+1}" style="flex:1; min-width:0; padding:8px 12px; font-size:13px; border-radius:6px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body);">
        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#9ca3af; cursor:pointer; font-size:16px; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:4px;" onmouseover="this.style.background='rgba(0,0,0,0.05)'; this.style.color='#ef4444';" onmouseout="this.style.background='none'; this.style.color='#9ca3af';" title="Remove Option">✕</button>
    `;
    optContainer.appendChild(div);
}

function changeQuestionType(selectElement) {
    const block = selectElement.closest('.quiz-q-block');
    const type = selectElement.value;
    const optList = block.querySelector('.opt-list');
    const fillBlankAns = block.querySelector('.fill-blank-ans');
    const addOptBtn = block.querySelector('.btn-add-opt');
    const qOpts = block.querySelectorAll('.q-opt');
    const qIdx = block.getAttribute('data-qindex');
    
    if (type === 'essay') {
        optList.style.display = 'none';
        fillBlankAns.style.display = 'none';
        addOptBtn.style.display = 'none';
        qOpts.forEach(o => o.required = false);
    } else if (type === 'fill_blank') {
        optList.style.display = 'none';
        fillBlankAns.style.display = 'block';
        addOptBtn.style.display = 'none';
        qOpts.forEach(o => o.required = false);
    } else {
        optList.style.display = 'block';
        fillBlankAns.style.display = 'none';
        addOptBtn.style.display = 'inline-block';
        qOpts.forEach(o => o.required = true);
        
        // Switch inputs between radio and checkbox
        const inputs = block.querySelectorAll('.opt-container input[type="radio"], .opt-container input[type="checkbox"]');
        const inputType = type === 'mcq' ? 'checkbox' : 'radio';
        const inputName = type === 'mcq' ? `temp_ans_${qIdx}[]` : `temp_ans_${qIdx}`;
        
        inputs.forEach(input => {
            input.type = inputType;
            input.name = inputName;
        });
    }
}

// Hook into form submit to compile quiz JSON
document.getElementById('courseForm').addEventListener('submit', function(e) {
    const blocks = document.querySelectorAll('.quiz-q-block');
    const quizArr = [];
    let valid = true;
    blocks.forEach((b) => {
        const qText = b.querySelector('.q-text').value;
        const qType = b.querySelector('.q-type').value;
        
        if (qType === 'essay') {
            quizArr.push({ type: 'essay', q: qText, is_essay: true });
        } else if (qType === 'fill_blank') {
            const blankAns = b.querySelector('.q-blank-ans').value.trim();
            if (!blankAns) {
                alert('Please provide a correct answer for the Fill in the Blank question: ' + qText);
                valid = false;
                return;
            }
            quizArr.push({ type: 'fill_blank', q: qText, ans: blankAns, is_essay: false });
        } else if (qType === 'mcq') {
            const optInputs = b.querySelectorAll('.q-opt');
            const opts = [];
            optInputs.forEach(oi => opts.push(oi.value));
            
            const checked = b.querySelectorAll(`input[type="checkbox"]:checked`);
            if (checked.length === 0) {
                alert('Please select at least one correct answer for Multiple Choice question: ' + qText);
                valid = false;
                return;
            }
            const ansArray = [];
            checked.forEach(c => ansArray.push(parseInt(c.value)));
            
            quizArr.push({ type: 'mcq', q: qText, opts: opts, ans: ansArray, is_essay: false });
        } else {
            // single
            const optInputs = b.querySelectorAll('.q-opt');
            const opts = [];
            optInputs.forEach(oi => opts.push(oi.value));
            
            const checked = b.querySelector(`input[type="radio"]:checked`);
            if (!checked) {
                alert('Please select a correct answer for question: ' + qText);
                valid = false;
                return;
            }
            const radioIndex = parseInt(checked.value);
            quizArr.push({ type: 'single', q: qText, opts: opts, ans: radioIndex, is_essay: false });
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
    currentCourseData = data;
    try {
        completedModules = JSON.parse(data.completed_modules || '[]');
    } catch(e) {
        completedModules = [];
    }

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
        if (completedModules.includes(m.id)) {
            btn.innerHTML = `✅ ${idx + 1}. ${m.chapter_title}`;
        }
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
    checkAllCompleted();
    document.getElementById('videoModal').style.display='flex';
}

function checkAllCompleted() {
    let hasQuiz = currentCourseData.quiz_json && currentCourseData.quiz_json.trim() !== '' && currentCourseData.quiz_json !== '[]';
    
    document.getElementById('takeExamBtn').style.display = 'none';
    document.getElementById('completeForm').style.display = 'none';

    let allDone = true;
    let chapters = currentCourseData.modules || [];
    chapters.forEach(c => {
        if(!completedModules.includes(c.id)) allDone = false;
    });

    if(currentCourseData.status !== 'Completed') {
        if(allDone) {
            if(hasQuiz) {
                activeQuiz = JSON.parse(currentCourseData.quiz_json);
                document.getElementById('takeExamBtn').style.display = 'block';
            } else {
                document.getElementById('completeForm').style.display = 'block';
            }
        }
    }
}

function loadPlayerMedia(m) {
    currentModuleData = m;
    
    if(!completedModules.includes(m.id) && currentCourseData.status !== 'Completed') {
        document.getElementById('btnMarkWatched').style.display = 'block';
    } else {
        document.getElementById('btnMarkWatched').style.display = 'none';
    }

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

function markModuleWatched() {
    if(!currentModuleData) return;
    
    const formData = new FormData();
    formData.append('assignment_id', currentCourseData.assignment_id);
    formData.append('module_id', currentModuleData.id);
    
    fetch('controllers/update_module_progress.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(res => {
        if(res.success) {
            completedModules.push(currentModuleData.id);
            document.getElementById('btnMarkWatched').style.display = 'none';
            // update sidebar text
            const btns = document.getElementById('playerModulesList').querySelectorAll('button');
            btns.forEach(btn => {
                if(btn.textContent.includes(currentModuleData.chapter_title)) {
                    btn.innerHTML = `✅ ` + btn.textContent;
                }
            });
            checkAllCompleted();
        }
    });
}

function renderExam() {
    document.getElementById('takeExamBtn').style.display = 'none';
    document.getElementById('mediaContainer').style.display = 'none';
    document.getElementById('videoFrame').src = ''; // Stop video
    document.getElementById('slidesFrame').src = ''; // Stop slides
    document.getElementById('quizContainer').style.display = 'block';
    
    let html = '<h3>Final Examination</h3><hr style="margin: 15px 0; border:0; border-top:1px solid #eee;">';
    activeQuiz.forEach((q, qIndex) => {
        let type = q.type || (q.is_essay ? 'essay' : 'single');
        html += `<div style="margin-bottom:20px;" class="exam-q-row" data-type="${type}" data-is-essay="${q.is_essay ? 'true' : 'false'}">
            <p style="font-weight:600; margin-bottom:10px;">${qIndex + 1}. ${q.q}</p>`;
        
        if (type === 'essay') {
            html += `<textarea name="q_${qIndex}" rows="4" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:10px; font-family:inherit;"></textarea>`;
        } else if (type === 'fill_blank') {
            html += `<input type="text" name="q_${qIndex}" placeholder="Type your answer..." style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:10px; font-family:inherit;">`;
        } else if (type === 'mcq') {
            q.opts.forEach((opt, oIndex) => {
                html += `<label style="display:block; margin-bottom:5px; cursor:pointer;">
                    <input type="checkbox" name="q_${qIndex}[]" value="${oIndex}">${opt}
                </label>`;
            });
        } else { // single
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
        let type = q.type || (q.is_essay ? 'essay' : 'single');
        let answerData = { q: q.q, is_essay: q.is_essay, type: type, answer: null, is_correct: false };

        if (type === 'essay') {
            hasEssay = true;
            let val = block.querySelector(`textarea[name="q_${qIndex}"]`).value.trim();
            if(!val) allAnswered = false;
            answerData.answer = val;
        } else if (type === 'fill_blank') {
            let val = block.querySelector(`input[name="q_${qIndex}"]`).value.trim();
            if(!val) allAnswered = false;
            answerData.answer = val;
            
            if (val.toLowerCase() === String(q.ans).trim().toLowerCase()) {
                answerData.is_correct = true;
                score++;
            }
        } else if (type === 'mcq') {
            let selected = block.querySelectorAll(`input[name="q_${qIndex}[]"]:checked`);
            if(selected.length === 0) allAnswered = false;
            else {
                let ansArray = [];
                selected.forEach(s => ansArray.push(parseInt(s.value)));
                answerData.answer = ansArray;
                
                // Compare arrays (ignore order)
                let correctArray = Array.isArray(q.ans) ? q.ans : [q.ans];
                let isMatch = ansArray.length === correctArray.length && ansArray.every(val => correctArray.includes(val));
                if (isMatch) {
                    answerData.is_correct = true;
                    score++;
                }
            }
        } else {
            // single
            let selected = block.querySelector(`input[name="q_${qIndex}"]:checked`);
            if(!selected) allAnswered = false;
            else {
                answerData.answer = parseInt(selected.value);
                if(answerData.answer === q.ans) {
                    answerData.is_correct = true;
                    score++;
                }
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

function switchTrainingTab(tab) {
    if(tab === 'myCourses') {
        document.getElementById('myCoursesView').style.display = 'grid';
        document.getElementById('catalogView').style.display = 'none';
        document.getElementById('btnMyCourses').style.background = '#4f46e5';
        document.getElementById('btnMyCourses').style.color = 'white';
        document.getElementById('btnCatalog').style.background = '#e5e7eb';
        document.getElementById('btnCatalog').style.color = '#374151';
    } else {
        document.getElementById('myCoursesView').style.display = 'none';
        document.getElementById('catalogView').style.display = 'grid';
        document.getElementById('btnMyCourses').style.background = '#e5e7eb';
        document.getElementById('btnMyCourses').style.color = '#374151';
        document.getElementById('btnCatalog').style.background = '#4f46e5';
        document.getElementById('btnCatalog').style.color = 'white';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

