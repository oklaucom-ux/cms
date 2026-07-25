<?php
// controllers/import_crm_leads.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$source = $_POST['source'] ?? 'csv';
$ownerId = $_SESSION['login_id'];

try {
    $importedCount = 0;

    // SOURCE 1: CSV FILE UPLOAD
    if ($source === 'csv') {
        if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            echo json_encode(['success' => false, 'error' => 'Please select a valid CSV file to upload.']);
            exit();
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            echo json_encode(['success' => false, 'error' => 'Could not open CSV file.']);
            exit();
        }

        $header = fgetcsv($handle);
        if (!$header) {
            echo json_encode(['success' => false, 'error' => 'Empty CSV file.']);
            exit();
        }

        // Map column indexes
        $map = [
            'name'    => -1,
            'company' => -1,
            'email'   => -1,
            'phone'   => -1,
            'value'   => -1,
            'stage'   => -1
        ];

        foreach ($header as $idx => $col) {
            $colLower = strtolower(trim($col));
            if (in_array($colLower, ['name', 'lead_name', 'full_name', 'contact_name'])) $map['name'] = $idx;
            elseif (in_array($colLower, ['company', 'company_name', 'organization'])) $map['company'] = $idx;
            elseif (in_array($colLower, ['email', 'email_address'])) $map['email'] = $idx;
            elseif (in_array($colLower, ['phone', 'mobile', 'telephone', 'phone_number'])) $map['phone'] = $idx;
            elseif (in_array($colLower, ['value', 'amount', 'lead_value', 'deal_size'])) $map['value'] = $idx;
            elseif (in_array($colLower, ['stage', 'status'])) $map['stage'] = $idx;
        }

        // Fallbacks if header mapping not found
        if ($map['name'] === -1) $map['name'] = 0;
        if ($map['company'] === -1) $map['company'] = 1;
        if ($map['email'] === -1) $map['email'] = 2;
        if ($map['phone'] === -1) $map['phone'] = 3;

        $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, value, stage, owner_id, branch_id, last_contact) VALUES (?, ?, ?, ?, ?, ?, ?, 'Global HQ', CURRENT_TIMESTAMP)");

        while (($row = fgetcsv($handle)) !== false) {
            $name    = trim($row[$map['name']] ?? '');
            $company = trim($row[$map['company']] ?? '');
            $email   = trim($row[$map['email']] ?? '');
            $phone   = trim($row[$map['phone']] ?? '');
            $value   = floatval($row[$map['value']] ?? 0);
            $stage   = trim($row[$map['stage']] ?? 'Prospect');

            if (empty($name) && empty($company) && empty($email) && empty($phone)) continue;

            $stmt->execute([
                $name ?: ($company ?: 'Imported Lead'),
                $company,
                $email,
                $phone,
                $value,
                $stage ?: 'Prospect',
                $ownerId
            ]);
            $importedCount++;
        }
        fclose($handle);
    }

    // SOURCE 2: CMS DYNAMIC FORM SUBMISSIONS
    elseif ($source === 'dynamic_form') {
        $formId = (int)($_POST['form_id'] ?? 0);
        if ($formId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Please select a Dynamic Form.']);
            exit();
        }

        $stmtSubs = $pdo->prepare("SELECT response_json FROM form_submissions WHERE form_id = ? ORDER BY id DESC");
        $stmtSubs->execute([$formId]);
        $subs = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

        $stmtIns = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, branch_id, last_contact) VALUES (?, ?, ?, ?, 'Prospect', ?, 'Global HQ', CURRENT_TIMESTAMP)");

        foreach ($subs as $s) {
            $resp = json_decode($s['response_json'], true);
            if (!is_array($resp)) continue;

            $name = ''; $email = ''; $phone = ''; $company = '';

            foreach ($resp as $key => $val) {
                if (is_array($val)) $val = implode(', ', $val);
                $k = strtolower($key);
                if (str_contains($k, 'name')) $name = $val;
                elseif (str_contains($k, 'email')) $email = $val;
                elseif (str_contains($k, 'phone') || str_contains($k, 'mobile')) $phone = $val;
                elseif (str_contains($k, 'company') || str_contains($k, 'org')) $company = $val;
            }

            if (empty($name) && empty($email) && empty($phone)) continue;

            $stmtIns->execute([
                $name ?: 'Form Inquiry',
                $company,
                $email,
                $phone,
                $ownerId
            ]);
            $importedCount++;
        }
    }

    // SOURCE 3: DRIVE DOCUMENTS / FILES
    elseif ($source === 'drive') {
        $docId = (int)($_POST['document_id'] ?? 0);
        if ($docId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Please select a Drive Document file.']);
            exit();
        }

        $stmtDoc = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmtDoc->execute([$docId]);
        $filePath = $stmtDoc->fetchColumn();

        if (!$filePath || !file_exists(__DIR__ . '/../' . $filePath)) {
            echo json_encode(['success' => false, 'error' => 'Document file not found on server drive.']);
            exit();
        }

        $fullPath = __DIR__ . '/../' . $filePath;
        $content = file_get_contents($fullPath);

        // Check if JSON or CSV
        $json = json_decode($content, true);
        $stmtIns = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, branch_id, last_contact) VALUES (?, ?, ?, ?, 'Prospect', ?, 'Global HQ', CURRENT_TIMESTAMP)");

        if (is_array($json)) {
            foreach ($json as $item) {
                $name    = $item['name'] ?? $item['lead_name'] ?? '';
                $company = $item['company'] ?? '';
                $email   = $item['email'] ?? '';
                $phone   = $item['phone'] ?? '';
                if (empty($name) && empty($email)) continue;
                $stmtIns->execute([$name, $company, $email, $phone, $ownerId]);
                $importedCount++;
            }
        } else {
            // Parse CSV content string
            $lines = explode("\n", $content);
            foreach ($lines as $idx => $line) {
                if ($idx === 0) continue;
                $cols = str_getcsv($line);
                if (count($cols) < 2) continue;
                $stmtIns->execute([
                    $cols[0] ?? 'Drive Lead',
                    $cols[1] ?? '',
                    $cols[2] ?? '',
                    $cols[3] ?? '',
                    $ownerId
                ]);
                $importedCount++;
            }
        }
    }

    // SOURCE 4: RECEPTION / VISITOR DESK LOG
    elseif ($source === 'reception') {
        $stmtRec = $pdo->query("SELECT visitor_name, company, phone, email FROM reception_log ORDER BY id DESC LIMIT 200");
        $visitors = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

        $stmtIns = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, branch_id, last_contact) VALUES (?, ?, ?, ?, 'Prospect', ?, 'Global HQ', CURRENT_TIMESTAMP)");

        foreach ($visitors as $v) {
            if (empty($v['visitor_name']) && empty($v['phone'])) continue;
            $stmtIns->execute([
                $v['visitor_name'] ?: 'Visitor Lead',
                $v['company'] ?: '',
                $v['email'] ?: '',
                $v['phone'] ?: '',
                $ownerId
            ]);
            $importedCount++;
        }
    }

    echo json_encode([
        'success'        => true,
        'imported_count' => $importedCount,
        'message'        => "Successfully imported {$importedCount} leads into CRM."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Import error: ' . $e->getMessage()]);
}
