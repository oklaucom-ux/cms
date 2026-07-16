<?php
$query = "
ALTER TABLE contracts 
ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(255),
ADD COLUMN IF NOT EXISTS content_html TEXT,
ADD COLUMN IF NOT EXISTS token VARCHAR(255),
ADD COLUMN IF NOT EXISTS signature_data TEXT,
ADD COLUMN IF NOT EXISTS signed_at DATETIME;
";

try {
    $pdo->exec($query);
} catch (Exception $e) {
    // MySQL 5.7 fallback (doesn't support ADD COLUMN IF NOT EXISTS natively)
    $cols = ['recipient_name' => 'VARCHAR(255)', 'recipient_email' => 'VARCHAR(255)', 'content_html' => 'TEXT', 'token' => 'VARCHAR(255)', 'signature_data' => 'TEXT', 'signed_at' => 'DATETIME'];
    foreach ($cols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE contracts ADD COLUMN $col $def");
        } catch (Exception $e) {}
    }
}
?>
