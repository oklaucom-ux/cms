<?php
global $pdo;

$cols = [
    'recipient_name' => 'VARCHAR(255)', 
    'recipient_email' => 'VARCHAR(255)', 
    'content_html' => 'TEXT', 
    'token' => 'VARCHAR(255)', 
    'signature_data' => 'TEXT', 
    'signed_at' => 'DATETIME'
];

foreach ($cols as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE contracts ADD COLUMN $col $def");
    } catch (Exception $e) {}
}

return [];
?>
