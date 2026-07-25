<?php
// controllers/get_campaign_logs.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$campaign_id = (int)($_GET['campaign_id'] ?? 0);

if ($campaign_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid Campaign ID is required.']);
    exit();
}

try {
    $stmtCmp = $pdo->prepare("SELECT id, title, channel, target_stage, status, sent_count FROM crm_campaigns WHERE id = ?");
    $stmtCmp->execute([$campaign_id]);
    $campaign = $stmtCmp->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo json_encode(['success' => false, 'error' => 'Campaign not found.']);
        exit();
    }

    $stmtLogs = $pdo->prepare("SELECT l.*, c.lead_name FROM crm_campaign_logs l LEFT JOIN crm_leads c ON l.lead_id = c.id WHERE l.campaign_id = ? ORDER BY l.id DESC LIMIT 200");
    $stmtLogs->execute([$campaign_id]);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'campaign' => $campaign,
        'logs' => $logs
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
