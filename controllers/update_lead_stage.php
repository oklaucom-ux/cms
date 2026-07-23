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

    // Cross-module automation: Automatically generate a Project when Lead is Won
    if ($stage === 'Won') {
        $leadStmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
        $leadStmt->execute([$id]);
        $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);
        if ($lead) {
            $projectName = "Deal - " . ($lead['company'] ?: $lead['lead_name']);
            // Check if project already created for this deal
            $chk = $pdo->prepare("SELECT id FROM projects WHERE name = ?");
            $chk->execute([$projectName]);
            if (!$chk->fetchColumn()) {
                $wsId = $_SESSION['active_workspace_id'] ?? null;
                $budget = floatval($lead['value'] ?? 0);
                $insProj = $pdo->prepare("INSERT INTO projects (name, client, budget, deadline, status, created_by, workspace_id) VALUES (?, ?, ?, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'Planning', ?, ?)");
                try {
                    $insProj->execute([$projectName, $lead['lead_name'], $budget, $_SESSION['login_id'], $wsId]);
                } catch (Exception $e) {
                    // Fallback for SQLite date syntax
                    $insProj = $pdo->prepare("INSERT INTO projects (name, client, budget, deadline, status, created_by, workspace_id) VALUES (?, ?, ?, date('now', '+30 days'), 'Planning', ?, ?)");
                    $insProj->execute([$projectName, $lead['lead_name'], $budget, $_SESSION['login_id'], $wsId]);
                }
            }
        }
    }

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Move Lead', '']);

    echo json_encode(['success' => true]);
}
