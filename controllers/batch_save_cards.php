<?php
// controllers/batch_save_cards.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$uploadBase = __DIR__ . '/../uploads/';
$cardsDir   = $uploadBase . 'visiting_cards/';
if (!is_dir($cardsDir)) @mkdir($cardsDir, 0755, true);

$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (empty($data['cards']) || !is_array($data['cards'])) {
    echo json_encode(['success' => false, 'error' => 'No card data array provided in batch request.']);
    exit();
}

$savedCount = 0;
$createdLeads = 0;

try {
    $pdo->beginTransaction();

    foreach ($data['cards'] as $c) {
        $contact_name = trim($c['contact_name'] ?? '');
        $job_title    = trim($c['job_title'] ?? '');
        $company_name = trim($c['company_name'] ?? '');
        $email        = trim($c['email'] ?? '');
        $phone        = trim($c['phone'] ?? '');
        $website      = trim($c['website'] ?? '');
        $address      = trim($c['address'] ?? '');
        $category     = trim($c['category'] ?? 'Batch Import');
        $text_remarks = trim($c['text_remarks'] ?? 'Imported via Batch Scanner');
        $front_b64    = $c['front_image_b64'] ?? null;
        $sync_crm     = !empty($c['sync_crm']);

        if (empty($contact_name) && empty($company_name) && empty($email) && empty($phone)) {
            continue;
        }

        $front_image = null;
        if (!empty($front_b64) && preg_match('/^data:image\/(\w+);base64,/', $front_b64, $type)) {
            $b64Data = substr($front_b64, strpos($front_b64, ',') + 1);
            $ext = strtolower($type[1]);
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) $ext = 'png';
            $decoded = base64_decode($b64Data);
            if ($decoded !== false) {
                $filename = 'front_batch_' . time() . '_' . uniqid() . '.' . $ext;
                file_put_contents($cardsDir . $filename, $decoded);
                $front_image = 'uploads/visiting_cards/' . $filename;
            }
        }

        $lead_id = null;
        if ($sync_crm) {
            $stmtLead = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, branch_id, last_contact) VALUES (?, ?, ?, ?, 'Prospect', ?, 'Global HQ', CURRENT_TIMESTAMP)");
            $leadName = $contact_name ?: ($company_name ?: 'Batch Contact');
            $stmtLead->execute([$leadName, $company_name, $email, $phone, $_SESSION['login_id']]);
            $lead_id = $pdo->lastInsertId();
            $createdLeads++;
        }

        $stmtCard = $pdo->prepare("INSERT INTO visiting_cards 
            (contact_name, job_title, company_name, email, phone, website, address, category, front_image, text_remarks, lead_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        
        $stmtCard->execute([
            $contact_name, $job_title, $company_name, $email, $phone, $website, $address, $category,
            $front_image, $text_remarks, $lead_id, $_SESSION['login_id']
        ]);

        $savedCount++;
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'saved_count'   => $savedCount,
        'created_leads' => $createdLeads,
        'message'       => "Successfully processed and saved {$savedCount} cards."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Batch processing error: ' . $e->getMessage()]);
}
