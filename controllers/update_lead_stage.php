<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'edit_leads');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['stage'])) {
    $id    = intval($_POST['id']);
    $stage = $_POST['stage'];

    $allowed = ['Prospect', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
    if (!in_array($stage, $allowed)) {
        http_response_code(400);
        die("Invalid stage.");
    }

    $stmt = $pdo->prepare("UPDATE crm_leads SET stage=?, last_contact=CURRENT_TIMESTAMP WHERE id=?");
    $stmt->execute([$stage, $id]);

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Move Lead', 'Lead ID {$id} moved to {$stage}')");

    echo json_encode(['success' => true]);
}
