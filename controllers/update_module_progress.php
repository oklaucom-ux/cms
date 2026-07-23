<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/permissions.php';
requirePermission($pdo, 'access_training');

if (!isset($_SESSION['login_id'])) die("Unauthorized access.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = intval($_POST['assignment_id']);
    $module_id = intval($_POST['module_id']);
    $user_id = $_SESSION['login_id'];
    
    // Validate assignment
    $stmt = $pdo->prepare("SELECT id, completed_modules FROM training_assignments WHERE id = ? AND user_id = ?");
    $stmt->execute([$assignment_id, $user_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        $completed = json_decode($assignment['completed_modules'], true);
        if (!is_array($completed)) $completed = [];
        
        if (!in_array($module_id, $completed)) {
            $completed[] = $module_id;
            $pdo->prepare("UPDATE training_assignments SET completed_modules = ? WHERE id = ?")
                ->execute([json_encode($completed), $assignment_id]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid assignment']);
    }
}
?>
