<?php
// controllers/save_visiting_card.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$uploadBase = __DIR__ . '/../uploads/';
$cardsDir   = $uploadBase . 'visiting_cards/';
$voiceDir   = $uploadBase . 'voice_notes/';

if (!is_dir($cardsDir)) @mkdir($cardsDir, 0755, true);
if (!is_dir($voiceDir)) @mkdir($voiceDir, 0755, true);

function saveBase64OrFile($fileKey, $base64Key, $targetDir, $prefix) {
    if (!empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
        $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . strtolower($ext);
        $target = $targetDir . $filename;
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)) {
            return 'uploads/' . basename($targetDir) . '/' . $filename;
        }
    }
    
    if (!empty($_POST[$base64Key])) {
        $data = $_POST[$base64Key];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) $type = 'png';
            $data = base64_decode($data);
            if ($data !== false) {
                $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $type;
                file_put_contents($targetDir . $filename, $data);
                return 'uploads/' . basename($targetDir) . '/' . $filename;
            }
        }
    }
    return null;
}

try {
    $contact_name = trim($_POST['contact_name'] ?? '');
    $job_title    = trim($_POST['job_title'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $website      = trim($_POST['website'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $category     = trim($_POST['category'] ?? 'Networking');
    $text_remarks = trim($_POST['text_remarks'] ?? '');
    $ocr_raw_text = trim($_POST['ocr_raw_text'] ?? '');
    $sync_crm     = isset($_POST['sync_crm_lead']) && $_POST['sync_crm_lead'] == '1';

    $latitude      = trim($_POST['latitude'] ?? '');
    $longitude     = trim($_POST['longitude'] ?? '');
    $location_name = trim($_POST['location_name'] ?? '');
    $follow_up_date= !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : date('Y-m-d', strtotime('+2 days'));
    $schedule_task = isset($_POST['schedule_followup_task']) && $_POST['schedule_followup_task'] == '1';

    if (empty($contact_name) && empty($company_name) && empty($email) && empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Please provide at least a name, company, email, or phone number for the card.']);
        exit();
    }

    $front_image  = saveBase64OrFile('front_image_file', 'front_image_b64', $cardsDir, 'front');
    $back_image   = saveBase64OrFile('back_image_file', 'back_image_b64', $cardsDir, 'back');
    $selfie_image = saveBase64OrFile('selfie_image_file', 'selfie_image_b64', $cardsDir, 'selfie');

    // Handle voice note blob upload
    $voice_note_path = null;
    if (!empty($_FILES['voice_note_file']['tmp_name']) && is_uploaded_file($_FILES['voice_note_file']['tmp_name'])) {
        $vExt = pathinfo($_FILES['voice_note_file']['name'], PATHINFO_EXTENSION) ?: 'webm';
        $vFilename = 'voice_' . time() . '_' . uniqid() . '.' . strtolower($vExt);
        if (move_uploaded_file($_FILES['voice_note_file']['tmp_name'], $voiceDir . $vFilename)) {
            $voice_note_path = 'uploads/voice_notes/' . $vFilename;
        }
    }

    $lead_id = null;
    if ($sync_crm) {
        // Create CRM lead
        $stmtLead = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, branch_id, follow_up_date, last_contact) VALUES (?, ?, ?, ?, 'Prospect', ?, 'Global HQ', ?, CURRENT_TIMESTAMP)");
        $leadName = $contact_name ?: ($company_name ?: 'Card Contact');
        $stmtLead->execute([$leadName, $company_name, $email, $phone, $_SESSION['login_id'], $follow_up_date]);
        $lead_id = $pdo->lastInsertId();
    }

    // Insert Visiting Card record
    $stmtCard = $pdo->prepare("INSERT INTO visiting_cards 
        (contact_name, job_title, company_name, email, phone, website, address, category, front_image, back_image, selfie_image, text_remarks, voice_note_path, ocr_raw_text, latitude, longitude, location_name, follow_up_date, lead_id, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    
    $stmtCard->execute([
        $contact_name, $job_title, $company_name, $email, $phone, $website, $address, $category,
        $front_image, $back_image, $selfie_image, $text_remarks, $voice_note_path, $ocr_raw_text,
        $latitude, $longitude, $location_name, $follow_up_date,
        $lead_id, $_SESSION['login_id']
    ]);

    $card_id = $pdo->lastInsertId();

    // Auto-create Follow-up Task in tasks table if checked
    $taskCreated = false;
    if ($schedule_task) {
        try {
            $taskTitle = "Follow up with " . ($contact_name ?: $company_name ?: 'Card Lead');
            $taskDesc  = "Contact: {$contact_name}\nCompany: {$company_name}\nEmail: {$email}\nPhone: {$phone}\nRemarks: {$text_remarks}";
            $stmtTask = $pdo->prepare("INSERT INTO tasks (title, description, due_date, status, assigned_to, created_by, created_at) VALUES (?, ?, ?, 'Pending', ?, ?, CURRENT_TIMESTAMP)");
            $stmtTask->execute([$taskTitle, $taskDesc, $follow_up_date, $_SESSION['login_id'], $_SESSION['login_id']]);
            $taskCreated = true;
        } catch (Exception $e) {}
    }

    // Check Auto Triggers for Email
    $autoEmailSent = false;
    $autoEmailError = null;
    if (!empty($email)) {
        $stmtTpl = $pdo->prepare("SELECT * FROM card_templates WHERE channel = 'email' AND is_auto_trigger = 1 ORDER BY id DESC LIMIT 1");
        $stmtTpl->execute();
        $autoTpl = $stmtTpl->fetch(PDO::FETCH_ASSOC);

        if ($autoTpl) {
            $replacements = [
                '{{name}}'       => $contact_name ?: 'Valued Contact',
                '{{company}}'    => $company_name ?: 'Your Company',
                '{{job_title}}'  => $job_title ?: 'Professional',
                '{{email}}'      => $email,
                '{{phone}}'      => $phone,
                '{{remarks}}'    => $text_remarks,
                '{{user_name}}'  => $_SESSION['login_name'] ?? $_SESSION['login_id']
            ];

            $subject = strtr($autoTpl['subject'] ?? 'Great connecting with you!', $replacements);
            $body = strtr($autoTpl['body'] ?? 'Hello {{name}}, it was a pleasure connecting with you.', $replacements);

            $res = sendSystemEmail($email, $subject, $body);
            if ($res['success']) {
                $autoEmailSent = true;
            } else {
                $autoEmailError = $res['error'] ?? 'SMTP Error';
            }
        }
    }

    echo json_encode([
        'success'         => true,
        'card_id'         => $card_id,
        'lead_id'         => $lead_id,
        'auto_email_sent' => $autoEmailSent,
        'auto_email_err'  => $autoEmailError,
        'message'         => 'Visiting card saved successfully!'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
