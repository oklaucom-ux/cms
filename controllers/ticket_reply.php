<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';
require_once '../includes/notifications.php';
require_once '../includes/webhook_helper.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_id = intval($_POST['ticket_id']);
    $message = $_POST['message'];
    $status = $_POST['status'] ?? 'Open';
    
    $is_client = ($_SESSION['role'] === 'Client') ? 1 : 0;
    
    // Validate ticket ownership if client
    $stmt = $pdo->prepare("SELECT * FROM unified_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) die("Ticket not found");
    if ($is_client && $ticket['requester_id'] !== $_SESSION['login_id']) die("Unauthorized");
    
    try {
        $pdo->beginTransaction();
        
        $replyStmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, is_client) VALUES (?, ?, ?, ?)");
        $replyStmt->execute([$ticket_id, $_SESSION['login_id'], $message, $is_client]);
        
        // Update ticket status
        $updateStmt = $pdo->prepare("UPDATE unified_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$status, $ticket_id]);
        
        $pdo->commit();
        
        // Notifications
        if ($is_client) {
            // Notify Admins
            $admins = $pdo->query("SELECT login_id, email FROM users WHERE role = 'Admin'")->fetchAll(PDO::FETCH_ASSOC);
            foreach($admins as $admin) {
                createNotification($pdo, $admin['login_id'], "New Client Reply", "Ticket {$ticket['ticket_number']} has a new reply from {$ticket['requester_name']}.", 'desk.php');
                if ($admin['email']) sendSystemEmail($admin['email'], "New Ticket Reply", "Ticket {$ticket['ticket_number']} has a new reply.");
            }
        } else {
            // Notify Client (if it was a client ticket)
            if ($ticket['source'] === 'Client_Support') {
                $clientEmail = getUserEmail($pdo, $ticket['requester_id']);
                if ($clientEmail) {
                    sendSystemEmail($clientEmail, "Support Desk Update", "Your ticket {$ticket['ticket_number']} has a new reply: <br><br> {$message}");
                }
            }
        }

        fireWebhook($pdo, 'ticket_replied', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket['ticket_number'],
            'reply' => $message,
            'replier_id' => $_SESSION['login_id']
        ]);
        
        // Redirect back to origin
        $ref = $_SERVER['HTTP_REFERER'] ?? '../desk.php';
        header("Location: " . $ref);
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>
