<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/permissions.php';
requirePermission($pdo, 'access_training');
require_once '../includes/mailer.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized access.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = intval($_POST['course_id']);
    $user_id = $_SESSION['login_id'];
    
    // Validate course allows self enroll
    $courseStmt = $pdo->prepare("SELECT title, allow_self_enroll FROM training_courses WHERE id = ?");
    $courseStmt->execute([$course_id]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course || empty($course['allow_self_enroll'])) {
        header("Location: ../training.php?error=" . urlencode("Course not available for self-enrollment."));
        exit;
    }
    
    // Check if already enrolled
    $exists = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE course_id = {$course_id} AND user_id = '{$user_id}'")->fetchColumn();
    if ($exists == 0) {
        $pdo->prepare("INSERT INTO training_assignments (course_id, user_id, status) VALUES (?, ?, 'Assigned')")->execute([$course_id, $user_id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$user_id, 'Self-Enroll', "Enrolled in course {$course['title']}"]);
        
        header("Location: ../training.php?success=" . urlencode("Successfully enrolled in {$course['title']}"));
    } else {
        header("Location: ../training.php?error=" . urlencode("Already enrolled."));
    }
}
?>
