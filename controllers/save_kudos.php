<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-migrate schema
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
