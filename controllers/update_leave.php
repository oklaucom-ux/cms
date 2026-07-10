<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
require_once '../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['status'])) {
    // RBAC check
    if (!hasPermission($pdo, 'approve_leaves') && !in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Manager') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

    $id = intval($_POST['id']);
    $status = in_array($_POST['status'], ['Approved', 'Rejected']) ? $_POST['status'] : 'Pending';

    $leave = $pdo->prepare("SELECT * FROM leaves WHERE id=?");
    $leave->execute([$id]);
    $leave = $leave->fetch(PDO::FETCH_ASSOC);

    if ($leave) {
        $year = date('Y', strtotime($leave['start_date']));
        $days = round((strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400) + 1;

        // Security Check: Is the current user an Admin, or the specific Manager of the user?
        $stmtOwner = $pdo->prepare("SELECT manager_id FROM users WHERE login_id = ?");
        $stmtOwner->execute([$leave['user_id']]);
        $owner = $stmtOwner->fetch(PDO::FETCH_ASSOC);

        $isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
        $isLineManager = ($_SESSION['role'] === 'Manager' && $owner && $owner['manager_id'] === $_SESSION['login_id']);

        if (!$isAdmin && !$isLineManager) {
            die("Unauthorized Access: You are not this employee's line manager.");
        }

        if ($status === 'Rejected') {
            $pdo->prepare("UPDATE leaves SET status='Rejected' WHERE id=?")->execute([$id]);
            createNotification($pdo, $leave['user_id'], "Leave Request Rejected", "Your {$leave['leave_type']} request ({$days} days) has been rejected.", 'leaves.php');
            $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Rejected Leave', 'Leave ID {$id} for {$leave['user_id']}')");
            setFlash('error', "Leave request rejected.");
        } else if ($status === 'Approved') {
            $pdo->prepare("UPDATE leaves SET status='Approved' WHERE id=?")->execute([$id]);
            // Deduct from leave balance
            $pdo->prepare("INSERT IGNORE INTO leave_balances (user_id, leave_type, year, entitlement, used) VALUES (?,?,?,12,0)")->execute([$leave['user_id'], $leave['leave_type'], $year]);
            $pdo->prepare("UPDATE leave_balances SET used = used + ? WHERE user_id=? AND leave_type=? AND year=?")->execute([$days, $leave['user_id'], $leave['leave_type'], $year]);

            createNotification($pdo, $leave['user_id'], "Leave Request Approved", "Your {$leave['leave_type']} request ({$days} days) has been approved by your Line Manager.", 'leaves.php');
            $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Approved Leave', 'Leave ID {$id} for {$leave['user_id']}')");
            setFlash('success', "Leave request approved successfully.");
        }
    }

    if ($isAjax) {
        echo json_encode(['status' => 'ok']);
    } else {
        header("Location: ../leaves.php");
    }
    exit();
}
