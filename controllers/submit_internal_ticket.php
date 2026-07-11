<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';

if (!hasPermission($pdo, 'manage_support')) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $priority = $_POST['priority'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $client_id = $_POST['client_id'];
    
    // Fetch user name
    $stmt = $pdo->prepare("SELECT name FROM users WHERE login_id = ?");
    $stmt->execute([$client_id]);
    $client_name = $stmt->fetchColumn() ?: 'Unknown';

    $ticket_number = 'TKT-' . date('ym') . '-' . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, requester_name, subject, description, priority, status) VALUES ('Client_Support', ?, ?, ?, ?, ?, ?, 'Open')");
        $stmt->execute([$ticket_number, $client_id, $client_name, $subject, $message, $priority]);
        $ticket_id = $pdo->lastInsertId();

        $replyStmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_name, message, is_client) VALUES (?, ?, ?, ?, 0)");
        $replyStmt->execute([$ticket_id, $_SESSION['login_id'], $_SESSION['name'], $message]);

        $pdo->commit();
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Submit Internal Ticket']);

        fireWebhook($pdo, 'ticket_created', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'source' => 'Client_Support',
            'subject' => $subject,
            'requester' => $client_name,
            'created_by' => $_SESSION['name']
        ]);

        header("Location: ../desk.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error submitting ticket: " . $e->getMessage());
    }
}
?>
