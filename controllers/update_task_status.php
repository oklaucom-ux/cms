<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';

if (!hasPermission($pdo, 'edit_tasks')) die(json_encode(["error" => "Unauthorized"]));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = $_POST['task_id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if ($task_id && $new_status) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE task_id = ?");
        $stmt->execute([$new_status, $task_id]);
        
        // Log it silently
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Move Task']);
        
        if (in_array(strtolower($new_status), ['done', 'completed'])) {
            fireWebhook($pdo, 'task_completed', [
                'task_id' =>$task_id,
                'status' =>$new_status,
                'user_id' =>$_SESSION['login_id']
            ]);
        }

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Missing data"]);
    }
}
?>
