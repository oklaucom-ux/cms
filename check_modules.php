<?php
$files = glob('*.php');
foreach($files as $file) {
    if (in_array($file, ['init_db.php', 'login.php', 'logout.php', 'client_portal.php'])) continue;
    $content = file_get_contents($file);
    if (strpos($content, 'require_once \'includes/header.php\';') !== false || strpos($content, 'require_once "includes/header.php";') !== false) {
        $hasPermission = (strpos($content, 'requirePermission') !== false || strpos($content, 'hasPermission') !== false || strpos($content, 'Super Admin') !== false);
        echo str_pad($file, 30) . ($hasPermission ? " [YES] Secured" : " [NO]  Not Secured") . "\n";
    }
}
