<?php
// api_v1/lead_webhook.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    $data = $_POST;
}

if (empty($data)) {
    echo json_encode(['success' => false, 'error' => 'Empty payload received.']);
    exit();
}

try {
    $name    = trim($data['name'] ?? $data['lead_name'] ?? $data['full_name'] ?? $data['contact_name'] ?? '');
    $company = trim($data['company'] ?? $data['company_name'] ?? $data['organization'] ?? '');
    $email   = trim($data['email'] ?? $data['email_address'] ?? '');
    $phone   = trim($data['phone'] ?? $data['mobile'] ?? $data['telephone'] ?? '');
    $value   = floatval($data['value'] ?? $data['amount'] ?? 0);
    $stage   = trim($data['stage'] ?? 'Prospect');

    if (empty($name) && empty($email) && empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Required contact fields (name, email, or phone) missing.']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, value, stage, owner_id, branch_id, last_contact) VALUES (?, ?, ?, ?, ?, ?, 'Webhook', 'Global HQ', CURRENT_TIMESTAMP)");
    $stmt->execute([
        $name ?: ($company ?: 'Webhook Lead'),
        $company,
        $email,
        $phone,
        $value,
        $stage
    ]);

    $leadId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'message' => 'Lead successfully ingested into CynoCMS CRM.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Webhook processing error: ' . $e->getMessage()]);
}
