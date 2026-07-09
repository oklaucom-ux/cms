<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) die(json_encode(['error' => 'Unauthorized']));

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo json_encode(['error' => 'Ticket not found']);
    exit;
}

// Optional security check for clients viewing their own tickets
if ($_SESSION['role'] === 'Client' && $ticket['client_id'] !== $_SESSION['login_id']) {
    echo json_encode(['error' => 'Unauthorized access to ticket']);
    exit;
}

$repliesStmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM ticket_replies r LEFT JOIN users u ON r.user_id = u.login_id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$repliesStmt->execute([$id]);
$replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ticket' =>$ticket,
    'replies' =>$replies
]);
?>
