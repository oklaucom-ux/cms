<?php
// controllers/merge_crm_leads.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$action = $_REQUEST['action'] ?? 'find_duplicates';

try {
    // Action 1: Find Duplicates by Email or Phone
    if ($action === 'find_duplicates') {
        $stmtEmail = $pdo->query("
            SELECT email, COUNT(*) as cnt 
            FROM crm_leads 
            WHERE email IS NOT NULL AND email != '' 
            GROUP BY email HAVING cnt > 1
        ");
        $dupEmails = $stmtEmail->fetchAll(PDO::FETCH_ASSOC);

        $duplicates = [];
        foreach ($dupEmails as $d) {
            $stmt = $pdo->prepare("SELECT id, lead_name, company, email, phone, stage, value FROM crm_leads WHERE email = ?");
            $stmt->execute([$d['email']]);
            $duplicates[] = [
                'type' => 'email',
                'key'  => $d['email'],
                'leads'=> $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        }

        echo json_encode(['success' => true, 'duplicates' => $duplicates]);
        exit();
    }

    // Action 2: Merge Duplicates into Primary Lead
    if ($action === 'merge') {
        $primary_id   = (int)($_POST['primary_id'] ?? 0);
        $duplicate_ids= $_POST['duplicate_ids'] ?? [];

        if (is_string($duplicate_ids)) {
            $duplicate_ids = json_decode($duplicate_ids, true) ?: [];
        }

        if ($primary_id <= 0 || empty($duplicate_ids)) {
            echo json_encode(['success' => false, 'error' => 'Primary lead ID and duplicate lead IDs are required.']);
            exit();
        }

        // Fetch primary lead
        $stmtP = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
        $stmtP->execute([$primary_id]);
        $primary = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$primary) {
            echo json_encode(['success' => false, 'error' => 'Primary lead not found.']);
            exit();
        }

        $pdo->beginTransaction();

        $mergedValue = floatval($primary['value']);

        foreach ($duplicate_ids as $dupId) {
            $dupId = (int)$dupId;
            if ($dupId === $primary_id || $dupId <= 0) continue;

            $stmtD = $pdo->prepare("SELECT value FROM crm_leads WHERE id = ?");
            $stmtD->execute([$dupId]);
            $dupVal = $stmtD->fetchColumn();

            $mergedValue += floatval($dupVal);

            // Re-assign visiting cards linked to duplicate lead
            $pdo->prepare("UPDATE visiting_cards SET lead_id = ? WHERE lead_id = ?")->execute([$primary_id, $dupId]);

            // Delete duplicate lead record
            $pdo->prepare("DELETE FROM crm_leads WHERE id = ?")->execute([$dupId]);
        }

        // Update primary lead total value
        $pdo->prepare("UPDATE crm_leads SET value = ? WHERE id = ?")->execute([$mergedValue, $primary_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Duplicate leads successfully merged into primary record.']);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Merge error: ' . $e->getMessage()]);
}
