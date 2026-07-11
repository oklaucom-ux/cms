<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_settings');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Key value pairs 
    $updates = [
        'company_name' =>$_POST['company_name'],
        'company_email' =>$_POST['company_email'],
        'currency' =>$_POST['currency'],
        'timezone' =>$_POST['timezone'],
        'enable_public_website' => $_POST['enable_public_website'] ?? 'false',
        'module_crm' => $_POST['module_crm'] ?? 'true',
        'module_projects' => $_POST['module_projects'] ?? 'true',
        'module_finance' => $_POST['module_finance'] ?? 'true',
        'module_hr' => $_POST['module_hr'] ?? 'true',
        'module_communication' => $_POST['module_communication'] ?? 'true',
        'module_assets' => $_POST['module_assets'] ?? 'true',
        'module_support' => $_POST['module_support'] ?? 'true',
        'module_workspace' => $_POST['module_workspace'] ?? 'true',
        'module_forms' => $_POST['module_forms'] ?? 'true',
        'module_website' => $_POST['module_website'] ?? 'true',
        'footer_text' => $_POST['footer_text'] ?? '',
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? '',
        'smtp_user' => $_POST['smtp_user'] ?? '',
        'smtp_pass' => $_POST['smtp_pass'] ?? '',
        'smtp_from' => $_POST['smtp_from'] ?? ''
    ];

    $footer_links = [];
    if (!empty($_POST['footer_link_names']) && is_array($_POST['footer_link_names'])) {
        foreach ($_POST['footer_link_names'] as $i => $name) {
            $url = $_POST['footer_link_urls'][$i] ?? '#';
            if (trim($name) !== '') {
                $footer_links[] = ['name' => trim($name), 'url' => trim($url)];
            }
        }
    }
    $updates['footer_links'] = json_encode($footer_links);

    foreach($updates as $k =>$v) {
        $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$k, $v]);
    }

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'System Update', 'Settings updated']);
    
    header("Location: ../settings.php");
}
?>
