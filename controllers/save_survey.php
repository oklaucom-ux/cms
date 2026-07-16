<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Auto-migrate schema handled in migrations
    
    if ($action === 'create') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        // Close previous active surveys
        $pdo->exec("UPDATE pulse_surveys SET status = 'Closed' WHERE status = 'Active'");
        
        $question = $_POST['question'];
        $stmt = $pdo->prepare("INSERT INTO pulse_surveys (question, created_by) VALUES (?, ?)");
        $stmt->execute([$question, $_SESSION['login_id']]);
        
        header("Location: ../pulse_surveys.php?msg=SurveyLaunched");
        exit;
    }
    
    if ($action === 'close_survey') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE pulse_surveys SET status = 'Closed' WHERE id = ?")->execute([$id]);
        
        header("Location: ../pulse_surveys.php?msg=SurveyClosed");
        exit;
    }
    
    if ($action === 'submit_response') {
        // Prevent multiple submissions if desired by checking a cookie, 
        // but for anonymity, we'll allow it or rely on goodwill.
        // Let's set a simple cookie to deter double voting.
        $survey_id = intval($_POST['survey_id']);
        
        if (isset($_COOKIE['survey_voted_' . $survey_id])) {
            header("Location: ../pulse_surveys.php?error=AlreadyVoted");
            exit;
        }
        
        $score = intval($_POST['score']);
        $comment = $_POST['comment'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO pulse_responses (survey_id, score, comment) VALUES (?, ?, ?)");
        $stmt->execute([$survey_id, $score, $comment]);
        
        setcookie('survey_voted_' . $survey_id, '1', time() + (86400 * 30), "/"); // 30 days
        
        header("Location: ../pulse_surveys.php?msg=FeedbackSubmitted");
        exit;
    }
}
?>

