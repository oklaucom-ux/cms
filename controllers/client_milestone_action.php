<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';
require_once '../includes/notifications.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Client') {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = intval($_POST['task_id']);
    $action = $_POST['action']; // "Approve" or "Reject"
    
    // Validate that task belongs to a project the client owns
    $stmt = $pdo->prepare("SELECT t.id, t.name, t.assigned_to, p.name as proj_name 
                           FROM tasks t 
                           JOIN projects p ON t.project_id = p.id 
                           WHERE t.id = ? AND (p.client_id = ? OR p.client = ?)");
    $stmt->execute([$task_id, $_SESSION['login_id'], $_SESSION['name']]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        die("Invalid Task or Unauthorized.");
    }
    
    if ($action === 'Approve') {
        $pdo->prepare("UPDATE tasks SET status='Completed' WHERE id=?")->execute([$task_id]);
        $msg = "Client (".$_SESSION['name'].") has Approved milestone: '{$task['name']}' in {$task['proj_name']}.";
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Approve Milestone', '']);
    } else {
        $pdo->prepare("UPDATE tasks SET status='In Progress' WHERE id=?")->execute([$task_id]);
        $msg = "Client (".$_SESSION['name'].") has Rejected milestone: '{$task['name']}' in {$task['proj_name']}. Requires revisions.";
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Reject Milestone', '']);
    }
    
    // Notify the assignee
    if (!empty($task['assigned_to'])) {
        createNotification($pdo, $task['assigned_to'], "Milestone $action", $msg, 'tasks.php');
        $email = getUserEmail($pdo, $task['assigned_to']);
        if ($email) {
            sendSystemEmail($email, "Milestone Update: " . $action, $msg);
        }
    }
    
    header("Location: ../client_portal.php?success=" . urlencode("Milestone $action processed successfully."));
    exit();
}
?>
