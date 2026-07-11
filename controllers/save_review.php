<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Ensure table exists
    try {
// Add new columns to existing table if needed

} catch (Exception $e) {}

    if ($action === 'initiate_cycle') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $cycle = $_POST['cycle_name'];
        $user_id = $_POST['user_id'];
        $manager_id = $_POST['manager_id'];
        
        $pdo->prepare("INSERT INTO performance_reviews (user_id, cycle_name, manager_id) VALUES (?, ?, ?)")
            ->execute([$user_id, $cycle, $manager_id]);
            
        header("Location: ../performance_reviews.php?msg=CycleInitiated");
        exit;
    }
    
    if ($action === 'self_assessment') {
        $review_id = $_POST['review_id'];
        $text = $_POST['self_text'];
        $score = intval($_POST['self_score']);
        
        $stmt = $pdo->prepare("UPDATE performance_reviews SET self_assessment_text = ?, self_score = ?, status = 'Pending Manager' WHERE id = ? AND user_id = ?");
        $stmt->execute([$text, $score, $review_id, $_SESSION['login_id']]);
        
        header("Location: ../performance_reviews.php?msg=SelfAssessmentSubmitted");
        exit;
    }
    
    if ($action === 'manager_signoff') {
        $review_id = $_POST['review_id'];
        $feedback = $_POST['manager_text'];
        
        $s_tech = intval($_POST['score_tech']);
        $s_comm = intval($_POST['score_comm']);
        $s_lead = intval($_POST['score_lead']);
        $avg_score = round(($s_tech + $s_comm + $s_lead) / 3);
        
        $stmt = $pdo->prepare("UPDATE performance_reviews SET manager_feedback = ?, manager_score = ?, score_tech = ?, score_comm = ?, score_lead = ?, status = 'Completed' WHERE id = ? AND manager_id = ?");
        $stmt->execute([$feedback, $avg_score, $s_tech, $s_comm, $s_lead, $review_id, $_SESSION['login_id']]);
        
        header("Location: ../performance_reviews.php?msg=SignedOff");
        exit;
    }
}
?>

