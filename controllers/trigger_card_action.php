<?php
// controllers/trigger_card_action.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$card_id     = (int)($_POST['card_id'] ?? 0);
$template_id = (int)($_POST['template_id'] ?? 0);
$channel     = trim($_POST['channel'] ?? 'email');

if ($card_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Card ID is required.']);
    exit();
}

try {
    $stmtCard = $pdo->prepare("SELECT * FROM visiting_cards WHERE id = ?");
    $stmtCard->execute([$card_id]);
    $card = $stmtCard->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        echo json_encode(['success' => false, 'error' => 'Card not found.']);
        exit();
    }

    $template = null;
    if ($template_id > 0) {
        $stmtTpl = $pdo->prepare("SELECT * FROM card_templates WHERE id = ?");
        $stmtTpl->execute([$template_id]);
        $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);
    }

    $replacements = [
        '{{name}}'       => $card['contact_name'] ?: 'Valued Contact',
        '{{company}}'    => $card['company_name'] ?: 'Your Company',
        '{{job_title}}'  => $card['job_title'] ?: 'Professional',
        '{{email}}'      => $card['email'] ?: '',
        '{{phone}}'      => $card['phone'] ?: '',
        '{{remarks}}'    => $card['text_remarks'] ?: '',
        '{{user_name}}'  => $_SESSION['login_name'] ?? $_SESSION['login_id']
    ];

    if ($channel === 'email') {
        $to = trim($_POST['custom_email'] ?? $card['email']);
        if (empty($to)) {
            echo json_encode(['success' => false, 'error' => 'No email address available for this contact.']);
            exit();
        }

        $subject = $_POST['custom_subject'] ?? ($template['subject'] ?? 'Great connecting with you!');
        $body    = $_POST['custom_body'] ?? ($template['body'] ?? 'Hello {{name}}, it was nice connecting with you.');

        $subject = strtr($subject, $replacements);
        $body    = strtr($body, $replacements);

        $res = sendSystemEmail($to, $subject, $body);

        if ($res['success']) {
            echo json_encode(['success' => true, 'message' => "Email sent successfully to {$to}!"]);
        } else {
            echo json_encode(['success' => false, 'error' => $res['error'] ?? 'Failed to send email. Check SMTP configuration.']);
        }
        exit();
    }

    if ($channel === 'whatsapp') {
        $phone = trim($_POST['custom_phone'] ?? $card['phone']);
        if (empty($phone)) {
            echo json_encode(['success' => false, 'error' => 'No phone number available for this contact.']);
            exit();
        }

        // Clean phone number for WhatsApp link
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        
        $body = $_POST['custom_body'] ?? ($template['body'] ?? 'Hello {{name}}, it was great connecting with you today!');
        $body = strtr($body, $replacements);

        $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode(strip_tags($body));

        echo json_encode([
            'success'   => true,
            'wa_url'    => $waUrl,
            'phone'     => $cleanPhone,
            'message'   => "WhatsApp link generated."
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid channel specified.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
