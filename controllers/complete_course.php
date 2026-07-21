<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized access.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = intval($_POST['assignment_id']);
    $user_score = isset($_POST['user_score']) ? intval($_POST['user_score']) : null;
    $user_answers = $_POST['user_answers'] ?? null;
    
    // Validate Quiz constraints
    $courseQuery = $pdo->prepare("SELECT c.id as course_id, c.title, c.passing_score, c.quiz_json, c.expiration_months FROM training_assignments ta JOIN training_courses c ON ta.course_id = c.id WHERE ta.id = ?");
    $courseQuery->execute([$assignment_id]);
    $course = $courseQuery->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header("Location: ../training.php?error=Invalid course");
        exit;
    }

    $hasEssay = false;
    if ($user_answers) {
        $answersData = json_decode($user_answers, true);
        if (is_array($answersData)) {
            foreach ($answersData as $ans) {
                if (!empty($ans['is_essay'])) {
                    $hasEssay = true;
                    break;
                }
            }
        }
    }

    // Initial check to log the attempt regardless of pass/fail (If no essay, check score immediately)
    $passed = 1;
    if (!$hasEssay && !empty($course['quiz_json']) && $course['quiz_json'] !== '[]') {
        if ($user_score === null || $user_score < $course['passing_score']) {
            $passed = 0;
        }
$pdo->prepare("INSERT INTO training_results (assignment_id, user_id, score, passed) VALUES (?, ?, ?, ?)")
            ->execute([$assignment_id, $_SESSION['login_id'], $user_score, $passed]);
    }
    
<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized access.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = intval($_POST['assignment_id']);
    $user_score = isset($_POST['user_score']) ? intval($_POST['user_score']) : null;
    $user_answers = $_POST['user_answers'] ?? null;
    
    // Validate Quiz constraints
    $courseQuery = $pdo->prepare("SELECT c.id as course_id, c.title, c.passing_score, c.quiz_json, c.expiration_months FROM training_assignments ta JOIN training_courses c ON ta.course_id = c.id WHERE ta.id = ?");
    $courseQuery->execute([$assignment_id]);
    $course = $courseQuery->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header("Location: ../training.php?error=Invalid course");
        exit;
    }

    $hasEssay = false;
    if ($user_answers) {
        $answersData = json_decode($user_answers, true);
        if (is_array($answersData)) {
            foreach ($answersData as $ans) {
                if (!empty($ans['is_essay'])) {
                    $hasEssay = true;
                    break;
                }
            }
        }
    }

    // Initial check to log the attempt regardless of pass/fail (If no essay, check score immediately)
    $passed = 1;
    if (!$hasEssay && !empty($course['quiz_json']) && $course['quiz_json'] !== '[]') {
        if ($user_score === null || $user_score < $course['passing_score']) {
            $passed = 0;
        }
$pdo->prepare("INSERT INTO training_results (assignment_id, user_id, score, passed) VALUES (?, ?, ?, ?)")
            ->execute([$assignment_id, $_SESSION['login_id'], $user_score, $passed]);
    }
    
    if ($passed === 0) {
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Failed Exam', "Failed course {$course['title']} Exam with score: {$user_score}% (Required: {$course['passing_score']}%)"]);
        header("Location: ../training.php?error=" . urlencode("Exam Failed. Score: {$user_score}%. Required: {$course['passing_score']}%. Please try again later."));
        exit;
    }
    
    if ($hasEssay) {
        // Pending Grading workflow
        $stmt = $pdo->prepare("UPDATE training_assignments SET status='Pending Grading', user_answers=? WHERE id=? AND user_id=?");
        $stmt->execute([$user_answers, $assignment_id, $_SESSION['login_id']]);
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Submit Exam', "Submitted exam for {$course['title']} (Pending Manager Review)"]);
        
        header("Location: ../training.php?success=" . urlencode("Exam submitted to your Manager for manual grading."));
    } else {
        // Automatically Completed
        $expiresAt = null;
        if (intval($course['expiration_months']) > 0) {
            $months = intval($course['expiration_months']);
            $expiresAt = date('Y-m-d', strtotime("+$months months"));
        }

        $stmt = $pdo->prepare("UPDATE training_assignments SET status='Completed', completed_at=CURRENT_TIMESTAMP, expires_at=?, user_answers=? WHERE id=? AND user_id=?");
        $stmt->execute([$expiresAt, $user_answers, $assignment_id, $_SESSION['login_id']]);
        
        $extra_log = $user_score !== null ? " with score {$user_score}%" : "";
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Complete Course', "Completed training course: {$course['title']}{$extra_log}"]);
        
        // Send email to the candidate
        require_once '../includes/mailer.php';
        $email = getUserEmail($pdo, $_SESSION['login_id']);
        if ($email) {
            $msg = "Congratulations! You have completed the training course: <strong>{$course['title']}</strong>.<br>";
            if ($user_score !== null) {
                $msg .= "Your final score is: <strong>{$user_score}%</strong>.<br>";
            }
            $msg .= "You can view your detailed exam analysis in the Training module on your employee panel.";
            sendSystemEmail($email, "Training Completed: {$course['title']}", $msg);
        }
        
        header("Location: ../training.php?success=1");
    }
}
?>
