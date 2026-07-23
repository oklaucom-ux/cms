<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';
require_once '../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $dept = $_POST['department'];
        $prio = $_POST['priority'] ?? 'Medium';
        $subj = $_POST['subject'];
        $desc = $_POST['description'];
        $uid = $_SESSION['login_id'];
        $uname = $_SESSION['name'] ?? $uid;
        
        $ticket_number = 'HD-' . date('ym') . '-' . rand(1000, 9999);
        
        $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, requester_name, department, subject, description, priority, status) VALUES ('IT_Helpdesk', ?, ?, ?, ?, ?, ?, ?, 'Open')");
        $stmt->execute([$ticket_number, $uid, $uname, $dept, $subj, $desc, $prio]);
        
        $ticket_id = $pdo->lastInsertId();
        
        fireWebhook($pdo, 'ticket_created', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'source' => 'IT_Helpdesk',
            'department' => $dept,
            'subject' => $subj,
            'requester' => $uname
        ]);
        
        // Email Notification to Requester
        $toEmail = getUserEmail($pdo, $uid);
        if ($toEmail) {
            $emailSubject = "Ticket Logged: [{$ticket_number}] {$subj}";
            $body = "<h3 style='color:#4f46e5;'>Helpdesk Ticket Confirmation</h3>
                    <p>Dear <strong>" . htmlspecialchars($uname) . "</strong>,</p>
                    <p>Your support ticket <strong>{$ticket_number}</strong> has been logged successfully.</p>
                    <ul>
                        <li><strong>Department:</strong> " . htmlspecialchars($dept) . "</li>
                        <li><strong>Priority:</strong> " . htmlspecialchars($prio) . "</li>
                        <li><strong>Subject:</strong> " . htmlspecialchars($subj) . "</li>
                    </ul>
                    <p>Our IT support team will review your ticket shortly.</p>";
            sendSystemEmail($toEmail, $emailSubject, $body);
        }
        
        header("Location: ../helpdesk.php?msg=TicketCreated");
        exit;
    }
    
    if ($action === 'update_status') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $assign_me = ($_POST['assign_me'] ?? '0') == '1';
        $notes = $_POST['resolution_notes'];
        
        // Fetch current ticket
        $tStmt = $pdo->prepare("SELECT * FROM unified_tickets WHERE id = ?");
        $tStmt->execute([$id]);
        $tData = $tStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assign_me) {
            $stmt = $pdo->prepare("UPDATE unified_tickets SET status = ?, assigned_agent_id = ?, resolution_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, $_SESSION['login_id'], $notes, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE unified_tickets SET status = ?, resolution_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, $notes, $id]);
        }
        
        if ($tData && !empty($tData['requester_id'])) {
            $toEmail = getUserEmail($pdo, $tData['requester_id']);
            if ($toEmail) {
                $emailSubject = "Ticket Updated: [{$tData['ticket_number']}] Status is now {$status}";
                $body = "<h3 style='color:#4f46e5;'>Ticket Status Update</h3>
                        <p>Ticket <strong>{$tData['ticket_number']}</strong> status has been updated to: <strong>{$status}</strong>.</p>
                        " . ($notes ? "<p><strong>Agent Notes:</strong> " . htmlspecialchars($notes) . "</p>" : "");
                sendSystemEmail($toEmail, $emailSubject, $body);
            }
        }
        
        header("Location: ../helpdesk.php?msg=TicketUpdated");
        exit;
    }
}
?>
