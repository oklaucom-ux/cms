<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'edit_leads');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = intval($_POST['lead_id']);
    $type    = $_POST['type'];
    $note    = $_POST['note'] ?? '';
    $pdo->prepare("INSERT INTO crm_activities (lead_id, user_id, type, note) VALUES (?,?,?,?)")
        ->execute([$lead_id, $_SESSION['login_id'], $type, $note]);
    // Update last_contact
    $pdo->prepare("UPDATE crm_leads SET last_contact=CURRENT_TIMESTAMP WHERE id=?")->execute([$lead_id]);
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'CRM Activity', '']);
    header("Location: ../crm.php?lead_id={$lead_id}");
    exit();
}
