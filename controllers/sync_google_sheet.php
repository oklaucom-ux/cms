<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';
requirePermission($pdo, 'create_leads');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csv_url = $_POST['csv_url'] ?? '';
    if (empty($csv_url) || !filter_var($csv_url, FILTER_VALIDATE_URL)) {
        header("Location: ../crm.php?error=" . urlencode("Invalid Google Sheets CSV URL."));
        exit();
    }

    // Try to fetch the CSV
    $csv_data = @file_get_contents($csv_url);
    if ($csv_data === false) {
        header("Location: ../crm.php?error=" . urlencode("Failed to fetch data. Ensure the Google Sheet is published to the web as CSV."));
        exit();
    }

    $lines = explode("\n", trim($csv_data));
    if (count($lines) < 2) {
        header("Location: ../crm.php?error=" . urlencode("CSV appears empty or has no data rows."));
        exit();
    }

    $headers = str_getcsv(array_shift($lines));
    // Clean headers
    $headers = array_map('trim', $headers);
    
    // Look for standard headers (case-insensitive)
    $headerMap = [];
    foreach ($headers as $i => $h) {
        $h_lower = strtolower($h);
        if ($h_lower === 'lead name' || $h_lower === 'name') $headerMap['lead_name'] = $i;
        elseif ($h_lower === 'company') $headerMap['company'] = $i;
        elseif ($h_lower === 'email') $headerMap['email'] = $i;
        elseif ($h_lower === 'value' || $h_lower === 'amount') $headerMap['value'] = $i;
        elseif ($h_lower === 'stage') $headerMap['stage'] = $i;
        elseif (in_array($h_lower, ['pin', 'pin code', 'pincode', 'zip'])) $headerMap['pin'] = $i;
        elseif (in_array($h_lower, ['location', 'city'])) $headerMap['location'] = $i;
        elseif (in_array($h_lower, ['user type', 'usertype', 'role', 'designation'])) $headerMap['user_type'] = $i;
        else $headerMap['custom'][$h] = $i;
    }

    if (!isset($headerMap['lead_name'])) {
        header("Location: ../crm.php?error=" . urlencode("CSV must contain at least a 'Lead Name' or 'Name' column."));
        exit();
    }

    $importedCount = 0;
    
    // Pre-fetch locations mapping
    // $locationsMap[pin_code] = branch_id (location name)
    // $locationsMapByName[name] = branch_id
    $locs = $pdo->query("SELECT name, pin_code FROM locations")->fetchAll(PDO::FETCH_ASSOC);
    $pinMap = [];
    $nameMap = [];
    foreach ($locs as $l) {
        if (!empty($l['pin_code'])) $pinMap[$l['pin_code']] = $l['name'];
        if (!empty($l['name'])) $nameMap[strtolower($l['name'])] = $l['name'];
    }

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $row = str_getcsv($line);
        if (count($row) !== count($headers)) continue; // Malformed row

        $lead_name = $row[$headerMap['lead_name']];
        if (empty($lead_name)) continue;

        $company = isset($headerMap['company']) ? $row[$headerMap['company']] : '';
        $email = isset($headerMap['email']) ? $row[$headerMap['email']] : '';
        $value = isset($headerMap['value']) ? floatval($row[$headerMap['value']]) : 0;
        $stage = isset($headerMap['stage']) ? $row[$headerMap['stage']] : 'Prospect';
        
        $pin = isset($headerMap['pin']) ? trim($row[$headerMap['pin']]) : '';
        $loc = isset($headerMap['location']) ? trim($row[$headerMap['location']]) : '';
        $user_type = isset($headerMap['user_type']) ? trim($row[$headerMap['user_type']]) : '';

        // Extract Custom JSON
        $custom_data = [];
        if (isset($headerMap['custom'])) {
            foreach ($headerMap['custom'] as $colName => $colIndex) {
                $custom_data[$colName] = $row[$colIndex];
            }
            // If they provided PIN, Location, or User Type but we still want to save it to custom data so it's visible on the profile
            if ($pin) $custom_data['PIN Code'] = $pin;
            if ($loc) $custom_data['Location'] = $loc;
            if ($user_type) $custom_data['User Type'] = $user_type;
        }
        $custom_json = empty($custom_data) ? null : json_encode($custom_data);

        // INTELLIGENT AUTO-ROUTING ENGINE
        $owner_id = $_SESSION['login_id']; // Default to uploader
        $branch_id = 'Global HQ'; // Default

        $matchedBranch = null;
        if ($pin && isset($pinMap[$pin])) {
            $matchedBranch = $pinMap[$pin];
        } elseif ($loc && isset($nameMap[strtolower($loc)])) {
            $matchedBranch = $nameMap[strtolower($loc)];
        }

        if ($matchedBranch) {
            $branch_id = $matchedBranch;
            // Now find an appropriate user in that branch
            // Query matches branch AND (optionally) user_type
            if ($user_type) {
                $stmt = $pdo->prepare("SELECT login_id FROM users WHERE status='Active' AND branch_id=? AND (role=? OR designation=?) LIMIT 1");
                $stmt->execute([$branch_id, $user_type, $user_type]);
            } else {
                // If no user type provided, just find anyone in that branch with lead access
                $stmt = $pdo->prepare("SELECT login_id FROM users WHERE status='Active' AND branch_id=? LIMIT 1");
                $stmt->execute([$branch_id]);
            }
            
            $assignedUser = $stmt->fetchColumn();
            if ($assignedUser) {
                $owner_id = $assignedUser;
            } else {
                // Fallback: If they provided user_type but no match in branch, try just branch
                if ($user_type) {
                    $stmt = $pdo->prepare("SELECT login_id FROM users WHERE status='Active' AND branch_id=? LIMIT 1");
                    $stmt->execute([$branch_id]);
                    $fallbackUser = $stmt->fetchColumn();
                    if ($fallbackUser) $owner_id = $fallbackUser;
                }
            }
        }

        // Insert Lead
        $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, value, stage, owner_id, branch_id, custom_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$lead_name, $company, $email, $value, $stage, $owner_id, $branch_id, $custom_json]);
        
        fireWebhook($pdo, 'lead_created', [
            'lead_name' => $lead_name,
            'company' => $company,
            'email' => $email,
            'value' => $value,
            'owner_id' => $owner_id,
            'branch_id' => $branch_id
        ]);
        $importedCount++;
    }

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Sync Leads']);

    header("Location: ../crm.php?msg=" . urlencode("Successfully synced {$importedCount} leads from Google Sheets."));
    exit();
}
