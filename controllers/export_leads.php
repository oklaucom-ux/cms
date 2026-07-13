<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'export_leads');

// Fetch leads
$isAdmin = in_array($_SESSION['role'], ['Admin', 'Super Admin']);
if ($isAdmin) {
    $leads = $pdo->query("SELECT * FROM crm_leads ORDER BY last_contact DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $myBranch = $pdo->query("SELECT branch_id FROM users WHERE login_id = '{$_SESSION['login_id']}'")->fetchColumn() ?: 'Global HQ';
    $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE branch_id = ? ORDER BY last_contact DESC");
    $stmt->execute([$myBranch]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$format = $_GET['format'] ?? 'csv';
$filename = "crm_leads_export_" . date('Y_m_d_His');

$pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Data Export', '']);

if ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    echo json_encode($leads, JSON_PRETTY_PRINT);
    exit();
}

// Default to CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
$output = fopen('php://output', 'w');

// Flatten custom_data JSON into headers if present
$allKeys = ['id', 'lead_name', 'company', 'email', 'value', 'stage', 'owner_id', 'branch_id', 'last_contact', 'follow_up_date', 'source'];
$customKeys = [];
foreach ($leads as $l) {
    if ($l['custom_data']) {
        $cd = json_decode($l['custom_data'], true);
        if (is_array($cd)) {
            foreach (array_keys($cd) as $k) {
                if (!in_array($k, $customKeys)) $customKeys[] = $k;
            }
        }
    }
}

// Write Header
$headers = array_merge($allKeys, $customKeys);
fputcsv($output, $headers);

// Write Data
foreach ($leads as $l) {
    $row = [];
    foreach ($allKeys as $k) {
        $row[] = $l[$k] ?? '';
    }
    
    $cd = json_decode($l['custom_data'] ?? '{}', true) ?: [];
    foreach ($customKeys as $k) {
        $row[] = $cd[$k] ?? '';
    }
    
    fputcsv($output, $row);
}
fclose($output);
exit();
