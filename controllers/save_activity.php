<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'create_activities');

// Auto-migrate extra columns
try { $pdo->exec("ALTER TABLE activities ADD COLUMN priority TEXT DEFAULT 'Normal'"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE activities ADD COLUMN progress INTEGER DEFAULT 0"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE activities ADD COLUMN created_by TEXT DEFAULT NULL"); } catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    $activity_id = trim($_POST['activity_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $included_members = trim($_POST['included_members'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $due_date = $_POST['due_date'] ?? null;
    $priority = trim($_POST['priority'] ?? 'Normal');
    $progress = max(0, min(100, (int)($_POST['progress'] ?? 0)));

    // Auto-generate activity ID if blank
    if (empty($activity_id)) {
        $activity_id = 'ACT-' . strtoupper(substr(uniqid(), -6));
    }

    // Auto-set progress to 100 if completed
    if ($status === 'Completed') $progress = 100;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE activities SET activity_id=?, name=?, description=?, included_members=?, status=?, due_date=?, priority=?, progress=? WHERE id=?");
        $stmt->execute([$activity_id, $name, $description, $included_members, $status, $due_date, $priority, $progress, $id]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Update Activity', 'Updated activity {$activity_id}')");
    } else {
        $stmt = $pdo->prepare("INSERT INTO activities (activity_id, name, description, included_members, status, due_date, priority, progress, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$activity_id, $name, $description, $included_members, $status, $due_date, $priority, $progress, $_SESSION['login_id']]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Create Activity', 'Created activity {$activity_id}')");
    }
    header("Location: ../activities.php");
}
?>
