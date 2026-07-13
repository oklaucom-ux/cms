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
    $due_date      = $_POST['due_date'] ?? '';
    $priority      = $_POST['priority'] ?? 'Medium';
    $status        = $_POST['status'] ?? 'Pending';
    $project_id    = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $dependency_id = !empty($_POST['dependency_id']) ? intval($_POST['dependency_id']) : null;
    $is_milestone  = isset($_POST['is_milestone']) ? 1 : 0;
    // Generate task_id if new, otherwise reuse
    $task_id = !empty($_POST['task_id']) ? trim($_POST['task_id']) : 'TASK-' . strtoupper(substr(uniqid(), -6));
    
    if ($id) {
        $stmt = $pdo->prepare("UPDATE tasks SET task_id=?, name=?, description=?, assigned_to=?, due_date=?, priority=?, status=?, project_id=?, dependency_id=?, is_milestone=? WHERE id=?");
        $stmt->execute([$task_id, $name, $description, $assigned_to, $due_date, $priority, $status, $project_id, $dependency_id, $is_milestone, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Update Task', '']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO tasks (task_id, name, description, assigned_to, due_date, priority, status, project_id, dependency_id, is_milestone, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$task_id, $name, $description, $assigned_to, $due_date, $priority, $status, $project_id, $dependency_id, $is_milestone, $_SESSION['login_id']]);
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
