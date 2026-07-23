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
    die("<div class='content-section active'><h2>Course Not Found</h2><p>Please select a valid course from the Training Hub.</p></div>");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passing_score = intval($_POST['passing_score'] ?? 70);
    $quiz_json_input = $_POST['quiz_json'] ?? '[]';
    
    // Validate JSON
    $decoded = json_decode($quiz_json_input, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $updateStmt = $pdo->prepare("UPDATE training_courses SET passing_score = ?, quiz_json = ? WHERE id = ?");
        $updateStmt->execute([$passing_score, $quiz_json_input, $course_id]);
        $message = "<div style='padding:12px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:20px; font-weight:600;'>✅ Quiz & Exam Settings updated successfully!</div>";
        $course['passing_score'] = $passing_score;
        $course['quiz_json'] = $quiz_json_input;
    } else {
        $message = "<div style='padding:12px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:20px; font-weight:600;'>❌ Invalid Quiz JSON structure. Please verify formatting.</div>";
    }
}

$existingQuestions = json_decode($course['quiz_json'] ?: '[]', true) ?: [];
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">📝 Quiz Builder: <?= htmlspecialchars($course['title']) ?></h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Create and manage interactive assessment questions, correct answer keys, and passing thresholds.</p>
        </div>
        <button class="edit-button" onclick="window.location.href='training.php'" style="padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600;">
            ← Back to Training Hub
        </button>
    </div>

    <?= $message ?>

    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:16px; padding:24px; max-width:800px; margin-bottom:28px;">
        <form method="POST">
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:13px; font-weight:700; color:var(--text-heading); margin-bottom:6px;">Passing Score Percentage (%)</label>
                <input type="number" min="0" max="100" name="passing_score" value="<?= htmlspecialchars($course['passing_score'] ?? 70) ?>" style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid var(--border-card); background:var(--bg-body); color:var(--text-heading); font-weight:600;" required>
            </div>

            <div style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <label style="font-size:13px; font-weight:700; color:var(--text-heading);">Exam Questions (JSON Format)</label>
                    <span style="font-size:12px; color:var(--text-muted);"><?= count($existingQuestions) ?> Questions Configured</span>
                </div>
                <textarea name="quiz_json" id="quiz_json" rows="12" style="width:100%; padding:14px; border-radius:8px; border:1px solid var(--border-card); background:var(--bg-body); color:var(--text-heading); font-family:monospace; font-size:13px;" required><?= htmlspecialchars($course['quiz_json'] ?: '[]') ?></textarea>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="add-button" style="padding:12px 24px;">
                    💾 Save Quiz Configuration
                </button>
                <button type="button" class="view-button" onclick="insertSampleQuiz()" style="padding:12px 20px;">
                    ⚡ Load Sample Question Schema
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function insertSampleQuiz() {
    const sample = [
        {
            "q": "What is the primary goal of our compliance policy?",
            "options": ["Maximum Profits", "Safety & Ethical Conduct", "Speed Over Quality", "None of the above"],
            "correct": 1
        },
        {
            "q": "Describe the proper protocol for reporting a security incident.",
            "is_essay": true
        }
    ];
    document.getElementById('quiz_json').value = JSON.stringify(sample, null, 2);
}
</script>

<?php require_once 'includes/footer.php'; ?>
