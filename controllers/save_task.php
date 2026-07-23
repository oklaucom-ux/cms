<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        requirePermission($pdo, 'edit_tasks');
    } else {
        requirePermission($pdo, 'create_tasks');
    }
    $name          = trim($_POST['name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $assigned_to   = $_POST['assigned_to'] ?? '';
    $due_date      = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority      = $_POST['priority'] ?? 'Medium';
    $status        = $_POST['status'] ?? 'Pending';
    $project_id    = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $dependency_id = !empty($_POST['dependency_id']) ? intval($_POST['dependency_id']) : null;
    $is_milestone  = isset($_POST['is_milestone']) ? 1 : 0;

    // Resolve workspace_id from POST, Session, or Project
    $workspace_id = !empty($_POST['workspace_id']) ? intval($_POST['workspace_id']) : ($_SESSION['active_workspace_id'] ?? null);
    if (empty($workspace_id) && $project_id > 0) {
        $pWsStmt = $pdo->prepare("SELECT workspace_id FROM projects WHERE id = ?");
        $pWsStmt->execute([$project_id]);
        $workspace_id = $pWsStmt->fetchColumn() ?: null;
    }

    // Generate task_id if new, otherwise reuse
    $task_id = !empty($_POST['task_id']) ? trim($_POST['task_id']) : 'TASK-' . strtoupper(substr(uniqid(), -6));
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE tasks SET task_id=?, name=?, description=?, assigned_to=?, due_date=?, priority=?, status=?, project_id=?, dependency_id=?, is_milestone=?, workspace_id=? WHERE id=?");
        $stmt->execute([$task_id, $name, $description, $assigned_to, $due_date, $priority, $status, $project_id, $dependency_id, $is_milestone, $workspace_id, $id]);
        
        // AUTOMATED WORKFLOW: Gamification rewards
        if (in_array(strtolower($status), ['done', 'completed']) && !empty($due_date) && !empty($assigned_to)) {
            if (strtotime(date('Y-m-d')) <= strtotime($due_date)) {
                // To avoid multiple awards for the same task, check if points were already awarded for this task_id.
                $awarded = $pdo->prepare("SELECT COUNT(*) FROM points_ledger WHERE user_id = ? AND reason LIKE ?");
                $awarded->execute([$assigned_to, "%Completed task '$task_id'%"]);
                if ($awarded->fetchColumn() == 0) {
                    $pdo->prepare("UPDATE users SET cyno_points = cyno_points + 50 WHERE login_id = ?")->execute([$assigned_to]);
                    $pdo->prepare("INSERT INTO points_ledger (user_id, points, reason) VALUES (?, 50, ?)")->execute([$assigned_to, "Completed task '$task_id' on time"]);
                }
            }
        }

        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Update Task', '']);
        
        if (!empty($assigned_to)) {
            $email = getUserEmail($pdo, $assigned_to);
            if ($email) {
                sendSystemEmail($email, "Task Update: {$name} [{$status}]", "<h3 style='color:#4f46e5;'>Task Updated</h3><p>The task <strong>" . htmlspecialchars($name) . "</strong> assigned to you has been updated.</p><ul><li><strong>Status:</strong> " . htmlspecialchars($status) . "</li><li><strong>Priority:</strong> " . htmlspecialchars($priority) . "</li><li><strong>Due Date:</strong> " . htmlspecialchars($due_date ?: 'N/A') . "</li></ul>");
            }
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO tasks (task_id, name, description, assigned_to, due_date, priority, status, project_id, dependency_id, is_milestone, created_by, workspace_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$task_id, $name, $description, $assigned_to, $due_date, $priority, $status, $project_id, $dependency_id, $is_milestone, $_SESSION['login_id'], $workspace_id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Create Task', '']);
        
        $email = getUserEmail($pdo, $assigned_to);
        if ($email) {
            sendSystemEmail($email, "New Task Assigned: {$name}", "You have been assigned a new task: <strong>{$name}</strong>.<br>Due date: {$due_date}<br>Priority: {$priority}");
        }
    }
    header("Location: ../tasks.php");
    exit();
}
?>
