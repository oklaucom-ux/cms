<?php
// controllers/execute_campaign.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$campaign_id = (int)($_POST['campaign_id'] ?? 0);

if ($campaign_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Campaign ID is required.']);
    exit();
}

try {
    $stmtC = $pdo->prepare("SELECT * FROM crm_campaigns WHERE id = ?");
    $stmtC->execute([$campaign_id]);
    $campaign = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo json_encode(['success' => false, 'error' => 'Campaign not found.']);
        exit();
    }

    // Fetch Target Leads based on stage
    $stage = $campaign['target_stage'];
    if (empty($stage) || $stage === 'All') {
        $stmtLeads = $pdo->query("SELECT * FROM crm_leads ORDER BY id DESC");
    } else {
        $stmtLeads = $pdo->prepare("SELECT * FROM crm_leads WHERE stage = ? ORDER BY id DESC");
        $stmtLeads->execute([$stage]);
    }
    $leads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        echo json_encode(['success' => false, 'error' => 'No target leads found for this stage audience.']);
        exit();
    }

    $sentCount = 0;
    $failedCount = 0;
    $counter = 0;

    $stmtLog = $pdo->prepare("INSERT INTO crm_campaign_logs (campaign_id, lead_id, recipient, status, sent_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");

    $hasVariantB = !empty($campaign['variant_b_body']);

    foreach ($leads as $l) {
        $counter++;
        $useVariantB = ($hasVariantB && ($counter % 2 === 0));

        $replacements = [
            '{{name}}'      => $l['lead_name'] ?: 'Valued Lead',
            '{{company}}'   => $l['company'] ?: 'Your Company',
            '{{email}}'     => $l['email'] ?: '',
            '{{phone}}'     => $l['phone'] ?: '',
            '{{user_name}}' => $_SESSION['login_name'] ?? $_SESSION['login_id']
        ];

        if ($campaign['channel'] === 'email') {
            if (empty($l['email'])) continue;

            $rawSubject = $useVariantB ? ($campaign['variant_b_subject'] ?: $campaign['subject']) : $campaign['subject'];
            $rawBody    = $useVariantB ? $campaign['variant_b_body'] : $campaign['body'];

            $subject = strtr($rawSubject ?: 'Special Offer', $replacements);
            $body    = strtr($rawBody, $replacements);

            $res = sendSystemEmail($l['email'], $subject, $body);

            $statusText = 'Sent ' . ($useVariantB ? '(Variant B)' : '(Variant A)');

            if ($res['success']) {
                $stmtLog->execute([$campaign_id, $l['id'], $l['email'], $statusText]);
                $sentCount++;
            } else {
                $stmtLog->execute([$campaign_id, $l['id'], $l['email'], 'Failed: ' . ($res['error'] ?? 'SMTP Error')]);
                $failedCount++;
            }
        } elseif ($campaign['channel'] === 'whatsapp') {
            if (empty($l['phone'])) continue;
            $statusText = 'Queued WhatsApp ' . ($useVariantB ? '(Variant B)' : '(Variant A)');
            $stmtLog->execute([$campaign_id, $l['id'], $l['phone'], $statusText]);
            $sentCount++;
        }
    }

    // Update campaign status
    $stmtUpd = $pdo->prepare("UPDATE crm_campaigns SET status = 'Active', sent_count = sent_count + ? WHERE id = ?");
    $stmtUpd->execute([$sentCount, $campaign_id]);

    echo json_encode([
        'success'      => true,
        'sent_count'   => $sentCount,
        'failed_count' => $failedCount,
        'message'      => "Campaign executed! Dispatched to {$sentCount} target leads."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Campaign execution error: ' . $e->getMessage()]);
}
