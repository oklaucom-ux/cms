<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';

if (!hasPermission($pdo, 'manage_broadcasts') && !hasPermission($pdo, 'manage_users')) {
    die("Unauthorized access to Broadcast Engine.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type  = $_POST['target_type'] ?? 'ALL';
    $title = strip_tags(trim($_POST['title'] ?? ''));
    $body  = strip_tags(trim($_POST['body'] ?? ''));
    $link  = filter_var(trim($_POST['link'] ?? ''), FILTER_SANITIZE_URL);
    
    if (empty($title) || empty($body)) {
        $_SESSION['flash_error'] = "Missing Title or Body.";
        header("Location: ../notifications.php");
        exit;
    }

    try {
        if ($type === 'ALL') {
            notifyAll($pdo, $title, $body, $link);
            $_SESSION['flash_success'] = "Broadcast successfully dispatched to the entire company.";
        } 
        else if ($type === 'ROLE') {
            $role = $_POST['target_role'];
            $users = $pdo->prepare("SELECT login_id FROM users WHERE role=? AND status='Active'");
            $users->execute([$role]);
            $count = 0;
            foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                createNotification($pdo, $uid, $title, $body, $link);
                $count++;
            }
            $_SESSION['flash_success'] = "Broadcast dispatched to {$count} matched employees in role {$role}.";
        }
        else if ($type === 'USER') {
            $user = $_POST['target_user'];
            createNotification($pdo, $user, $title, $body, $link);
            $_SESSION['flash_success'] = "Direct push notification dispatched to {$user}.";
        }
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'System Broadcast']);
        
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Failed to send broadcast.";
    }
}
header("Location: ../notifications.php");
exit;
