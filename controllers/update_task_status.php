<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';

if (!hasPermission($pdo, 'edit_tasks')) die(json_encode(["error" => "Unauthorized"]));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = $_POST['task_id'] ?? null;
    $id = $_POST['id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if (($task_id || $id) && $new_status) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? OR (task_id IS NOT NULL AND task_id != '' AND task_id = ?)");
        $stmt->execute([$new_status, $id ?: 0, $task_id ?: '']);
        
        // Log it silently
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Move Task', '']);
        
        if (in_array(strtolower($new_status), ['done', 'completed'])) {
            // AUTOMATED WORKFLOW: Gamification rewards
            $taskStmt = $pdo->prepare("SELECT due_date, assigned_to, task_id, id FROM tasks WHERE id = ? OR (task_id IS NOT NULL AND task_id != '' AND task_id = ?)");
            $taskStmt->execute([$id ?: 0, $task_id ?: '']);
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
            if ($task && !empty($task['due_date']) && !empty($task['assigned_to'])) {
                if (strtotime(date('Y-m-d')) <= strtotime($task['due_date'])) {
                    $tRef = $task['task_id'] ?: $task['id'];
                    $awarded = $pdo->prepare("SELECT COUNT(*) FROM points_ledger WHERE user_id = ? AND reason LIKE ?");
                    $awarded->execute([$task['assigned_to'], "%Completed task '$tRef'%"]);
                    if ($awarded->fetchColumn() == 0) {
                        $pdo->prepare("UPDATE users SET cyno_points = cyno_points + 50 WHERE login_id = ?")->execute([$task['assigned_to']]);
                        $pdo->prepare("INSERT INTO points_ledger (user_id, points, reason) VALUES (?, 50, ?)")->execute([$task['assigned_to'], "Completed task '$tRef' on time"]);
                    }
                }
            }

            fireWebhook($pdo, 'task_completed', [
                'task_id' => $task_id ?: $id,
                'status' => $new_status,
                'user_id' => $_SESSION['login_id']
            ]);
        }

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Missing data"]);
    }
}
?>
