<?php
session_start();
require_once '../includes/db.php';

if (!hasPermission($pdo, 'manage_training')) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $title = $_POST['title'];
    $description = $_POST['description'];
    
    $passing_score = intval($_POST['passing_score'] ?? 0);
    $expiration_months = intval($_POST['expiration_months'] ?? 0);
    $category = trim($_POST['category'] ?? 'General');
    $allow_self_enroll = isset($_POST['allow_self_enroll']) ? 1 : 0;
    $quiz_json = $_POST['quiz_json'] ?? null;
    $modules_json = $_POST['modules_json'] ?? '[]';
    $created_by = $_SESSION['login_id'];

    try {
        $pdo->beginTransaction();

        if ($id) {
            $stmt = $pdo->prepare("UPDATE training_courses SET title=?, description=?, category=?, allow_self_enroll=?, quiz_json=?, passing_score=?, expiration_months=? WHERE id=?");
            $stmt->execute([$title, $description, $category, $allow_self_enroll, $quiz_json, $passing_score, $expiration_months, $id]);
            $course_id = $id;
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$created_by, 'Update Course', "Updated training course {$title}"]);
            
            // Delete old modules
            $pdo->prepare("DELETE FROM training_modules WHERE course_id=?")->execute([$course_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO training_courses (title, description, category, allow_self_enroll, quiz_json, passing_score, expiration_months, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category, $allow_self_enroll, $quiz_json, $passing_score, $expiration_months, $created_by]);
            $course_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$created_by, 'Create Course', "Created training course {$title}"]);
        }

        // Insert new modules
        $modules = json_decode($modules_json, true);
        if (is_array($modules)) {
            $modStmt = $pdo->prepare("INSERT INTO training_modules (course_id, chapter_title, video_url, slides_url, sort_order) VALUES (?, ?, ?, ?, ?)");
            foreach ($modules as $m) {
                $modStmt->execute([
                    $course_id,
                    $m['chapter_title'],
                    trim($m['video_url'] ?? ''),
                    trim($m['slides_url'] ?? ''),
                    $m['sort_order'] ?? 1
                ]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error saving course: " . $e->getMessage());
    }
    
    header("Location: ../training.php");
}
?>
