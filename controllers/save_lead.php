<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        requirePermission($pdo, 'edit_leads');
    } else {
        requirePermission($pdo, 'create_leads');
    }

    $lead_name      = $_POST['lead_name'];
    $company        = $_POST['company'];
    $email          = $_POST['email'];
    $value          = floatval($_POST['value']);
    $stage          = $_POST['stage'];
    $owner_id       = $_POST['owner_id'];
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE crm_leads SET lead_name=?, company=?, email=?, value=?, stage=?, owner_id=?, follow_up_date=?, last_contact=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->execute([$lead_name, $company, $email, $value, $stage, $owner_id, $follow_up_date, $id]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Update Lead', 'Updated CRM lead: {$lead_name}')");
    } else {
        $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, value, stage, owner_id, follow_up_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$lead_name, $company, $email, $value, $stage, $owner_id, $follow_up_date]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Add Lead', 'Added CRM lead: {$lead_name}')");
        
        fireWebhook($pdo, 'lead_created', [
            'lead_name' =>$lead_name,
            'company' =>$company,
            'email' =>$email,
            'value' =>$value,
            'owner_id' =>$owner_id
        ]);
    }

    header("Location: ../crm.php");
    exit();
}
