<?php
// api/whatsapp_webhook.php - Inbound WhatsApp Webhook & Lead Capture Engine
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Verification for Meta / Twilio / Custom Webhook Verification Challenges
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubVerifyToken = $_GET['hub_verify_token'] ?? $_GET['verify_token'] ?? '';
    $hubChallenge   = $_GET['hub_challenge'] ?? $_GET['challenge'] ?? '';
    $expectedToken  = $GLOBAL_SETTINGS['whatsapp_verify_token'] ?? 'CynoCrmWhatsAppToken2026';

    if ($hubVerifyToken === $expectedToken) {
        echo $hubChallenge;
        exit;
    }
    http_response_code(403);
    echo json_encode(['error' => 'Invalid Verification Token']);
    exit;
}

// Inbound POST Webhook Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON Payload']);
        exit;
    }

    $leadName = $data['name'] ?? $data['from_name'] ?? $data['contacts'][0]['profile']['name'] ?? 'WhatsApp Lead';
    $phone    = $data['phone'] ?? $data['from'] ?? $data['messages'][0]['from'] ?? '';
    $email    = $data['email'] ?? '';
    $message  = $data['message'] ?? $data['text'] ?? $data['messages'][0]['text']['body'] ?? '';
    $company  = $data['company'] ?? 'WhatsApp Contact';

    if (empty($phone) && empty($email)) {
        http_response_code(422);
        echo json_encode(['error' => 'Missing Phone or Email identifier']);
        exit;
    }

    // Auto-Migrate schema gracefully
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS crm_leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lead_name VARCHAR(255) NOT NULL,
            company VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            value DECIMAL(12,2) DEFAULT 0,
            stage VARCHAR(50) DEFAULT 'Prospect',
            owner_id VARCHAR(255),
            branch_id VARCHAR(255) DEFAULT 'Global HQ',
            follow_up_date DATE,
            last_contact DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    // Deduplication check: match phone or email
    $existing = null;
    if (!empty($phone)) {
        $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE phone = ? OR phone LIKE ?");
        $stmt->execute([$phone, "%$phone%"]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$existing && !empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($existing) {
        // Update existing lead
        $stmtUpdate = $pdo->prepare("UPDATE crm_leads SET last_contact = CURRENT_TIMESTAMP, lead_name = COALESCE(NULLIF(?, ''), lead_name) WHERE id = ?");
        $stmtUpdate->execute([$leadName, $existing['id']]);
        
        // Log activity
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS crm_activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                user_id VARCHAR(255),
                type VARCHAR(100),
                note TEXT,
                logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $stmtAct = $pdo->prepare("INSERT INTO crm_activities (lead_id, user_id, type, note) VALUES (?, 'WhatsApp API', '📱 WhatsApp Message', ?)");
            $stmtAct->execute([$existing['id'], $message ?: 'Inbound WhatsApp interaction recorded']);
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'action' => 'updated', 'lead_id' => $existing['id']]);
    } else {
        // Insert new lead
        $stmtInsert = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, branch_id) VALUES (?, ?, ?, ?, 'Prospect', 'Admin', 'Global HQ')");
        $stmtInsert->execute([$leadName, $company, $email, $phone]);
        $newId = $pdo->lastInsertId();

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS crm_activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                user_id VARCHAR(255),
                type VARCHAR(100),
                note TEXT,
                logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $stmtAct = $pdo->prepare("INSERT INTO crm_activities (lead_id, user_id, type, note) VALUES (?, 'WhatsApp API', '📱 WhatsApp Lead Captured', ?)");
            $stmtAct->execute([$newId, $message ?: 'New Lead captured via WhatsApp API']);
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'action' => 'created', 'lead_id' => $newId]);
    }
    exit;
}
