<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
requirePermission($pdo, 'convert_leads');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lead_id = intval($_POST['lead_id']);

    $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id=?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lead) {
        $project_name = $lead['company'] ? "{$lead['company']} Implementation" : "{$lead['lead_name']} Project";
        $budget = $lead['value'];
        $client = $lead['company'] ?: $lead['lead_name'];

        $pdo->beginTransaction();
        try {
            // Mark Lead as Won (Wait, what if they convert from Prospect? We assume conversion = Won).
            $pdo->prepare("UPDATE crm_leads SET stage='Won' WHERE id=?")->execute([$lead_id]);

            // Create Project
            $stmtProj = $pdo->prepare("INSERT INTO projects (name, description, client, budget, status) VALUES (?, ?, ?, ?, 'Planning')");
            $stmtProj->execute([
                $project_name, 
                "Project converted from CRM Lead ID: {$lead['id']}. Contact Email: {$lead['email']}.", 
                $client, 
                $budget
            ]);
            
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Convert Lead', '']);
            $pdo->commit();
            setFlash('success', 'Lead converted to Project successfully!');
        } catch(Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Error converting lead to project.');
        }
    } else {
        setFlash('error', 'Lead not found.');
    }
    header("Location: ../crm.php");
    exit();
}
?>
