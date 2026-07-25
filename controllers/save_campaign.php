<?php
// controllers/save_campaign.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$action = $_REQUEST['action'] ?? 'save';

try {
    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $channel      = trim($_POST['channel'] ?? 'email');
        $target_stage = trim($_POST['target_stage'] ?? 'All');
        $variant_b_subject = trim($_POST['variant_b_subject'] ?? '');
        $variant_b_body    = trim($_POST['variant_b_body'] ?? '');

        if (empty($title) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Campaign title and message body are required.']);
            exit();
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE crm_campaigns SET title = ?, channel = ?, target_stage = ?, subject = ?, body = ?, variant_b_subject = ?, variant_b_body = ? WHERE id = ?");
            $stmt->execute([$title, $channel, $target_stage, $subject, $body, $variant_b_subject, $variant_b_body, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO crm_campaigns (title, channel, target_stage, subject, body, variant_b_subject, variant_b_body, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Draft', ?)");
            $stmt->execute([$title, $channel, $target_stage, $subject, $body, $variant_b_subject, $variant_b_body, $_SESSION['login_id']]);
        }

        echo json_encode(['success' => true, 'message' => 'Campaign saved successfully!']);
        exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM crm_campaigns WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM crm_campaign_logs WHERE campaign_id = ?")->execute([$id]);
        }
        echo json_encode(['success' => true, 'message' => 'Campaign deleted.']);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
