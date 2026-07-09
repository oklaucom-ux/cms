<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
require_once '../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id    = $_SESSION['login_id'];
    $type       = $_POST['type'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $reason     = $_POST['reason'];
    $year       = date('Y');
    $days       = round((strtotime($end_date) - strtotime($start_date)) / 86400) + 1;

    // Check balance
    try {
        $bal = $pdo->prepare("SELECT entitlement, used FROM leave_balances WHERE user_id=? AND leave_type=? AND year=?");
        $bal->execute([$user_id, $type, $year]);
        $b = $bal->fetch(PDO::FETCH_ASSOC);
        if ($b && ($b['entitlement'] - $b['used']) < $days) {
            setFlash('error', "Insufficient leave balance. You have " . ($b['entitlement'] - $b['used']) . " day(s) remaining for {$type}.");
            header("Location: ../leaves.php");
            exit();
        }
    } catch (Exception $e) {}

    // Get user's manager
    $userStmt = $pdo->prepare("SELECT manager_id FROM users WHERE login_id=?");
    $userStmt->execute([$user_id]);
    $u = $userStmt->fetch(PDO::FETCH_ASSOC);
    $manager_id = $u ? $u['manager_id'] : null;

    $man_status = $manager_id ? 'Pending Manager' : 'Pending HR';

    $stmt = $pdo->prepare("INSERT INTO leaves (user_id, start_date, end_date, leave_type, reason, status, manager_status) VALUES (?,?,?,?,?,'Pending',?)");
    $stmt->execute([$user_id, $start_date, $end_date, $type, $reason, $man_status]);

    // Notify manager or admins
    if ($manager_id) {
        createNotification($pdo, $manager_id, "New Leave Request", "{$_SESSION['name']} requested {$days} day(s) of {$type}. Awaiting your approval.", 'leaves.php');
    } else {
        $admins = $pdo->query("SELECT login_id FROM users WHERE role='Admin' OR role='Manager'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $admin) {
            createNotification($pdo, $admin, "New Leave Request", "{$_SESSION['name']} requested {$days} day(s) of {$type}.", 'leaves.php');
        }
    }

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$user_id}', 'Leave Request', 'Requested {$type} from {$start_date} to {$end_date}')");
    setFlash('success', "Leave request submitted! {$days} day(s) pending approval.");
    header("Location: ../leaves.php");
    exit();
}
