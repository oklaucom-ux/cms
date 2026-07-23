<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/permissions.php';
requirePermission($pdo, 'view_tasks');

if (!isset($_SESSION['login_id'])) die("Unauthorized");

$task_id = intval($_POST['task_id']);
$user_id = $_SESSION['login_id'];

// Prevent multiple loose clock-ins
$activeCheck = $pdo->prepare("SELECT id, task_id FROM task_time_logs WHERE user_id = ? AND clock_out IS NULL");
$activeCheck->execute([$user_id]);
$active = $activeCheck->fetch();

if ($active) {
    die("You are already clocked into task ID {$active['task_id']}. Please clock out there first.");
}

// Get user rate
$rate = $pdo->query("SELECT hourly_rate FROM users WHERE login_id = '{$user_id}'")->fetchColumn();
if (!$rate) $rate = 0;

$stmt = $pdo->prepare("INSERT INTO task_time_logs (task_id, user_id, clock_in, rate_snapshot) VALUES (?, ?, CURRENT_TIMESTAMP, ?)");
$stmt->execute([$task_id, $user_id, $rate]);

header("Location: ../tasks.php?success=Clocked In");
?>
