<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formId = $_POST['form_id'] ?? '';
    $formName = $_POST['form_name'] ?? 'Dynamic Form Submission';
    $customFields = $_POST['custom_fields'] ?? [];

    if (!$formId) die("Invalid Form.");

    // Convert any array data (like checkboxes) to strings BEFORE extraction
    foreach ($customFields as $key => $val) {
        if (is_array($val)) {
            $customFields[$key] = implode(', ', $val);
        }
    }

    // Extract core CRM fields if they exist, otherwise use fallbacks
    // The builder uses 'lead_name', 'email', 'company' for canonical mappings
    $leadName = $customFields['lead_name'] ?? $customFields['name'] ?? 'Unknown Lead';
    $email = $customFields['email'] ?? null;
    $company = $customFields['company'] ?? 'Dynamic Form: ' . $formName;

    // Add metadata
    $customFields['_source_form'] = $formName;
    $customFields['_submitted_at'] = date('Y-m-d H:i:s');
    
    $customDataJson = json_encode($customFields);

    // Assign to the first Admin if possible
    $ownerId = 'admin';
    $stmtOwner = $pdo->query("SELECT login_id FROM users WHERE role = 'Admin' OR role = 'Manager' LIMIT 1");
    if ($admin = $stmtOwner->fetchColumn()) {
        $ownerId = $admin;
    }

    // Insert into crm_leads
    $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, stage, owner_id, branch_id, custom_data, value) VALUES (?, ?, ?, 'Prospect', ?, 'Global HQ', ?, 0)");
    $stmt->execute([
        trim($leadName),
        trim($company),
        $email ? trim($email) : null,
        $ownerId,
        $customDataJson
    ]);

    $leadId = $pdo->lastInsertId();

    // Create an activity log
    $pdo->prepare("INSERT INTO crm_activities (lead_id, type, note, user_id) VALUES (?, 'System', ?, 'system')")
        ->execute([$leadId, "Lead captured via Public Form: " . $formName]);

    // Send an automated notification to the owner
    $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)")
        ->execute([
            $ownerId, 
            "New Form Submission", 
            "A new prospect ($leadName) submitted the $formName.", 
            "lead_profile.php?id=$leadId"
        ]);

    header("Location: ../public_form.php?id=" . urlencode($formId) . "&success=1");
    exit();
}
