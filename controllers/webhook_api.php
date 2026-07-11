<?php
session_start();
require_once '../includes/db.php';

if(!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    http_response_code(403);
    die("Unauthorized");
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Ensure table exists
if($action === 'create') {
        $event = trim($_POST['event_name'] ?? '');
        $url = trim($_POST['payload_url'] ?? '');
        
        if($event && filter_var($url, FILTER_VALIDATE_URL)) {
            $stmt = $pdo->prepare("INSERT INTO webhooks (event_name, payload_url) VALUES (?, ?)");
            $stmt->execute([$event, $url]);
        }
        header("Location: ../webhooks.php?msg=Webhook+Added");
        exit;
    }
    
    if($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if($id) {
            $stmt = $pdo->prepare("DELETE FROM webhooks WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: ../webhooks.php?msg=Webhook+Deleted");
        exit;
    }
}
?>
