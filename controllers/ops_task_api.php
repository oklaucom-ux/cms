<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list_columns') {
        $stmt = $pdo->query("SELECT * FROM ops_columns ORDER BY position ASC");
        echo json_encode(['success' => true, 'columns' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($action === 'add_column') {
        if (!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) throw new Exception("Permission denied");
        $name = $_POST['name'] ?? '';
        if (empty($name)) throw new Exception("Column name is required");
        // Get max position
        $pos = $pdo->query("SELECT COALESCE(MAX(position), 0) + 1 FROM ops_columns")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO ops_columns (name, position) VALUES (?, ?)");
        $stmt->execute([$name, $pos]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'list') {
        $stmt = $pdo->query("SELECT t.*, u.name as assignee_name FROM ops_tasks t LEFT JOIN users u ON t.assigned_to = u.login_id AND t.assigned_type = 'User' ORDER BY t.created_at DESC");
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subStmt = $pdo->query("SELECT * FROM ops_subtasks ORDER BY id ASC");
        $allSubs = $subStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group subtasks by task_id
        $subMap = [];
        foreach($allSubs as $s) {
            $subMap[$s['task_id']][] = $s;
        }
        
        foreach($tasks as &$t) {
            $t['subtasks'] = $subMap[$t['id']] ?? [];
        }
        
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        
    } elseif ($action === 'create') {
        $title = $_POST['title'] ?? '';
        $desc = $_POST['description'] ?? '';
        $priority = $_POST['priority'] ?? 'Medium';
        $assignedType = $_POST['assigned_type'] ?? 'User'; // User or Department
        $assignedTo = $_POST['assigned_to'] ?? '';
        
        if (empty($title)) throw new Exception("Title is required.");
        
        $stmt = $pdo->prepare("INSERT INTO ops_tasks (title, description, assigned_type, assigned_to, priority, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $assignedType, $assignedTo, $priority, $_SESSION['login_id']]);
        $taskId = $pdo->lastInsertId();
        
        if (!empty($_POST['subtasks'])) {
            $subs = json_decode($_POST['subtasks'], true);
            if (is_array($subs)) {
                $subQ = $pdo->prepare("INSERT INTO ops_subtasks (task_id, title) VALUES (?, ?)");
                foreach($subs as $st) {
                    if (trim($st)) $subQ->execute([$taskId, trim($st)]);
                }
            }
        }
        
        // ── Dispatch Notifications & Emails ──
        require_once '../includes/mailer.php';
        $notifQ = $pdo->prepare("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)");
        
        $targets = [];
        if ($assignedType === 'User') {
            $targets[] = $assignedTo;
        } else if ($assignedType === 'Department') {
            $uStmt = $pdo->prepare("SELECT login_id FROM users WHERE department = ? AND status='Active'");
            $uStmt->execute([$assignedTo]);
            $targets = $uStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        foreach($targets as $uid) {
            // Local Notification
            $notifQ->execute([$uid, "New Ops Task: " . $title, "You have been assigned to a new Ops task.", "ops_kanban.php"]);
            
            // Email
            $eStmt = $pdo->prepare("SELECT email FROM users WHERE login_id = ?");
            $eStmt->execute([$uid]);
            $userEmail = $eStmt->fetchColumn();
            if ($userEmail) {
                $html = buildEmailTemplate("New Operations Task Assigned", 
                    "You have a new task waiting for you on the Ops Kanban Board.<br><br><strong>Task:</strong> {$title}<br><strong>Priority:</strong> {$priority}<br><strong>Description:</strong> {$desc}",
                    "View Task Board",
                    "http://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/ops_kanban.php"
                );
                sendSystemEmail($userEmail, "New Ops Task: {$title}", $html);
            }
        }
        
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 'Backlog';
        $stmt = $pdo->prepare("UPDATE ops_tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'toggle_subtask') {
        $id = $_POST['subtask_id'] ?? 0;
        $completed = $_POST['is_completed'] === 'true' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE ops_subtasks SET is_completed = ? WHERE id = ?");
        $stmt->execute([$completed, $id]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM ops_tasks WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ops_subtasks WHERE task_id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
