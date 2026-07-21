<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';

if (!hasPermission($pdo, 'manage_training')) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = intval($_POST['assignment_id']);
    $final_score = intval($_POST['final_score']);
    $decision = $_POST['decision'] ?? '';

    $courseQuery = $pdo->prepare("SELECT c.id as course_id, c.title, c.passing_score, c.expiration_months, ta.user_id FROM training_assignments ta JOIN training_courses c ON ta.course_id = c.id WHERE ta.id = ?");
    $courseQuery->execute([$assignment_id]);
    $data = $courseQuery->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Invalid assignment");
    }

    $manager = $_SESSION['login_id'];
    $feedback = trim($_POST['feedback_notes'] ?? '');

    if ($decision === 'Reject') {
        // Evaluate as Failed
        $pdo->prepare("UPDATE training_assignments SET status='Assigned', user_answers=NULL WHERE id=?")
            ->execute([$assignment_id]);
            
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$manager, 'Grade Essay', "Rejected essay submission for {$data['user_id']} on course {$data['title']}. Re-assigned."]);
        
        $notifMsg = "Your exam for '{$data['title']}' was evaluated and requires re-submission.";
        if ($feedback) {
            $notifMsg .= " Manager Feedback: " . htmlspecialchars($feedback);
        }
        createNotification($pdo, $data['user_id'], '❌ Exam Evaluation Failed', $notifMsg, 'training.php');
        
        // Send email
        require_once '../includes/mailer.php';
        $email = getUserEmail($pdo, $data['user_id']);
        if ($email) {
            $mailMsg = "Your training exam for <strong>{$data['title']}</strong> was evaluated and requires re-submission.<br>";
            if ($feedback) $mailMsg .= "<strong>Manager Feedback:</strong><br>" . nl2br(htmlspecialchars($feedback)) . "<br><br>";
            $mailMsg .= "Please log into the portal to review the feedback and try again.";
            sendSystemEmail($email, "Training Exam Evaluated - Action Required", $mailMsg);
        }
        
        header("Location: ../training.php?success=Rejected and Re-assigned");
    } else {
        // Approve & Certify
        $expiresAt = null;
        if (intval($data['expiration_months']) > 0) {
            $months = intval($data['expiration_months']);
            $expiresAt = date('Y-m-d', strtotime("+$months months"));
        }

        $pdo->prepare("UPDATE training_assignments SET status='Completed', completed_at=CURRENT_TIMESTAMP, expires_at=? WHERE id=?")
            ->execute([$expiresAt, $assignment_id]);
            
        // Check if training_results exists and update/insert
        try {
            $stmt = $pdo->prepare("UPDATE training_results SET score=?, passed=1 WHERE assignment_id=?");
            $stmt->execute([$final_score, $assignment_id]);
            if ($stmt->rowCount() == 0) {
                // If it wasn't inserted by the engine initially (e.g. pure essay)
                $pdo->prepare("INSERT INTO training_results (assignment_id, user_id, score, passed) VALUES (?, ?, ?, 1)")
                    ->execute([$assignment_id, $data['user_id'], $final_score]);
            }
        } catch(Exception $e) {}

        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$manager, 'Grade Essay', "Approved essay for {$data['user_id']} on course {$data['title']} with manual score {$final_score}%"]);
        
        // Send email
        require_once '../includes/mailer.php';
        $email = getUserEmail($pdo, $data['user_id']);
        if ($email) {
            $mailMsg = "Congratulations! Your training exam for <strong>{$data['title']}</strong> has been graded and you have passed.<br>";
            $mailMsg .= "Your final score is: <strong>{$final_score}%</strong>.<br>";
            if ($feedback) $mailMsg .= "<strong>Manager Feedback:</strong><br>" . nl2br(htmlspecialchars($feedback)) . "<br><br>";
            $mailMsg .= "You can view your detailed exam analysis in the Training module on your employee panel.";
            sendSystemEmail($email, "Training Completed: {$data['title']}", $mailMsg);
        }
        
        header("Location: ../training.php?success=Certified");
    }
}
?>
