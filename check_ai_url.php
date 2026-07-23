<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='local_ai_url'");
echo "URL: " . $stmt->fetchColumn() . "\n";
