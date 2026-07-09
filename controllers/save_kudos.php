<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-migrate schema
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS kudos (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            sender_id TEXT NOT NULL,
            receiver_id TEXT NOT NULL,
            points INTEGER NOT NULL,
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    $receiver = $_POST['receiver_id'];
    $points = intval($_POST['points']);
    $message = $_POST['message'];
    
    // Prevent self-kudos
    if ($receiver === $_SESSION['login_id']) {
        header("Location: ../rewards.php?error=SelfKudosNotAllowed");
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO kudos (sender_id, receiver_id, points, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['login_id'], $receiver, $points, $message]);
    
    header("Location: ../rewards.php?msg=KudosSent");
    exit;
}
?>
