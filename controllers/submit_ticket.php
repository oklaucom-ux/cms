<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';
require_once '../includes/webhook_helper.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Client') {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $priority = $_POST['priority'] ?? 'Medium';
    $subject = $_POST['subject'] ?? 'No Subject';
    $message = $_POST['message'] ?? '';
    
    $client_id = $_SESSION['login_id'];
    $client_name = $_SESSION['name'];
    $ticket_number = 'TKT-' . date('ym') . '-' . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, requester_name, subject, description, priority, status) VALUES ('Client_Support', ?, ?, ?, ?, ?, ?, 'Open')");
        $stmt->execute([$ticket_number, $client_id, $client_name, $subject, $message, $priority]);
        $ticket_id = $pdo->lastInsertId();

        $replyStmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, is_client) VALUES (?, ?, ?, 1)");
        $replyStmt->execute([$ticket_id, $client_id, $message]);

        $pdo->commit();
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$client_id, 'Submit Ticket', "Created support ticket {$ticket_number}"]);

        fireWebhook($pdo, 'ticket_created', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'source' => 'Client_Support',
            'subject' => $subject,
            'requester' => $client_name
        ]);

        header("Location: ../client_portal.php?success=Ticket Submitted Successfully");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error submitting ticket: " . $e->getMessage());
    }
}
?>
