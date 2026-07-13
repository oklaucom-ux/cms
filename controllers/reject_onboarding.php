<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';
requirePermission($pdo, 'manage_onboarding');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    $app = $pdo->prepare("SELECT * FROM onboarding_applications WHERE id = ?");
    $app->execute([$id]);
    $data = $app->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $pdo->prepare("UPDATE onboarding_applications SET status = 'Rejected' WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Reject Candidate', '']);
    }
    
    header("Location: ../onboarding.php");
    exit;
}
?>
