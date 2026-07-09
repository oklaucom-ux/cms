<?php
// controllers/update_feedback.php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'Open';

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE feedback SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Log action in audit trail
        $audit_stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)");
        $audit_stmt->execute([$_SESSION['login_id'], 'UPDATE_FEEDBACK', "Changed feedback ID #$id status to '$status'"]);
    }
    header("Location: ../feedback.php");
    exit;
} else {
    header("Location: ../feedback.php");
    exit;
}
