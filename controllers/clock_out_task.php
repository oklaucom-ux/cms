<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'view_tasks');

if (!isset($_SESSION['login_id'])) die("Unauthorized");

$task_id = intval($_POST['task_id']);
$user_id = $_SESSION['login_id'];

// Find open log
$stmt = $pdo->prepare("SELECT id, clock_in, rate_snapshot FROM task_time_logs WHERE task_id = ? AND user_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
$stmt->execute([$task_id, $user_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if ($log) {
    // Current time
    $clock_out = date('Y-m-d H:i:s');
    $clock_in = $log['clock_in'];
    
    // SQLite Julian Day difference (hours)
    $hoursQuery = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, ?) / 3600.0");
    $hoursQuery->execute([$clock_out, $clock_in]);
    $total_hours = $hoursQuery->fetchColumn();
    
    if ($total_hours < 0) $total_hours = 0;

    $cost = $total_hours * $log['rate_snapshot'];

    $upd = $pdo->prepare("UPDATE task_time_logs SET clock_out = ?, total_hours = ?, cost_incurred = ? WHERE id = ?");
    $upd->execute([$clock_out, $total_hours, $cost, $log['id']]);
    
    header("Location: ../tasks.php?success=Clocked Out");
} else {
    header("Location: ../tasks.php?error=No open session found");
}
?>
