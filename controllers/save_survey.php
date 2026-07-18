<?php
file_put_contents(__DIR__ . '/save_survey_debug.log', "Hit save_survey at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
require_once '../includes/db.php';
file_put_contents(__DIR__ . '/save_survey_debug.log', "DB included successfully\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    file_put_contents(__DIR__ . '/save_survey_debug.log', "Action: $action\n", FILE_APPEND);
    
    // Auto-migrate schema handled in migrations, but we add a fallback here just in case
    try {
        $pdo->exec("ALTER TABLE pulse_responses ADD COLUMN score INTEGER");
        $pdo->exec("ALTER TABLE pulse_responses ADD COLUMN comment TEXT");
    } catch (Exception $e) {
        // Ignore if already exists
    }
    
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
        try {
            $survey_id = intval($_POST['survey_id']);
            
            if (isset($_COOKIE['survey_voted_' . $survey_id])) {
                header("Location: ../pulse_surveys.php?error=AlreadyVoted");
                exit;
            }
            
            $score = intval($_POST['score']);
            $comment = $_POST['comment'] ?? '';
            
            $stmt = $pdo->prepare("INSERT INTO pulse_responses (survey_id, user_id, score, comment) VALUES (?, 'anonymous', ?, ?)");
            $stmt->execute([$survey_id, $score, $comment]);
            
            @setcookie('survey_voted_' . $survey_id, '1', time() + (86400 * 30), "/"); // 30 days
            
            header("Location: ../pulse_surveys.php?msg=FeedbackSubmitted");
            exit;
        } catch (\Throwable $e) {
            die("Debug Error: " . $e->getMessage() . " on line " . $e->getLine());
        }
    }
}
?>

