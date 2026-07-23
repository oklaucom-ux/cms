<?php
$files = glob('controllers/*.php');
foreach($files as $file) {
    if (in_array($file, ['controllers/login_action.php'])) continue;
    $content = file_get_contents($file);
    $hasPerm = (strpos($content, 'hasPermission') !== false || strpos($content, 'requirePermission') !== false || strpos($content, 'Super Admin') !== false || strpos($content, 'Admin') !== false || strpos($content, 'Client') !== false);
    if (!$hasPerm) {
        echo $file . " is missing RBAC checks\n";
    }
}
