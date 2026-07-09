<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Auto-migrate schema just in case
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS timesheets (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            user_id TEXT NOT NULL,
            project_id INTEGER NOT NULL,
            entry_date DATE NOT NULL,
            hours REAL NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'Pending Approval',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    if ($action === 'log_hours') {
        $project_id = intval($_POST['project_id']);
        $date = $_POST['entry_date'];
        $hours = floatval($_POST['hours']);
        $desc = $_POST['description'];
        
        $stmt = $pdo->prepare("INSERT INTO timesheets (user_id, project_id, entry_date, hours, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['login_id'], $project_id, $date, $hours, $desc]);
        
        header("Location: ../timesheets.php?msg=HoursLogged");
        exit;
    }
    
    if ($action === 'approve' || $action === 'reject') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        
        $stmt = $pdo->prepare("UPDATE timesheets SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        if ($status === 'Approved') {
            // Log into audit trail
            $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Approved Timesheet', 'Approved timesheet ID {$id}')");
        }
        
        header("Location: ../timesheets.php?msg=StatusUpdated");
        exit;
    }
}
?>
