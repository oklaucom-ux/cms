<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

requirePermission($pdo, 'manage_training');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = $_POST['course_id'];
    $assigned_users = $_POST['assigned_users'] ?? [];
    
    // Fetch course details via prepared statement
    $courseStmt = $pdo->prepare("SELECT title FROM training_courses WHERE id = ?");
    $courseStmt->execute([intval($course_id)]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    $title = $course ? $course['title'] : 'Unknown Course';

    if (in_array("ALL", $assigned_users)) {
        $all = $pdo->query("SELECT login_id FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_COLUMN);
        foreach($all as $u) {
            $exists = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE course_id = {$course_id} AND user_id = '{$u}'")->fetchColumn();
            if ($exists == 0) {
                $pdo->prepare("INSERT INTO training_assignments (course_id, user_id) VALUES (?, ?)")->execute([$course_id, $u]);
                $email = getUserEmail($pdo, $u);
                if ($email) sendSystemEmail($email, "New Training Assigned: {$title}", "You have been enrolled in a new training course: <strong>{$title}</strong>.<br>Please complete it at your earliest convenience.");
            }
        }
    } else {
        foreach($assigned_users as $u) {
            $exists = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE course_id = {$course_id} AND user_id = '{$u}'")->fetchColumn();
            if ($exists == 0) {
                $pdo->prepare("INSERT INTO training_assignments (course_id, user_id) VALUES (?, ?)")->execute([$course_id, $u]);
                $email = getUserEmail($pdo, $u);
                if ($email) sendSystemEmail($email, "New Training Assigned: {$title}", "You have been enrolled in a new training course: <strong>{$title}</strong>.<br>Please complete it at your earliest convenience.");
            }
        }
    }

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Assign Course']);
    header("Location: ../training.php");
}
?>
