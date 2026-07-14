<?php
require 'includes/db.php';
header('Content-Type: text/plain');

try {
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users columns:\n";
    foreach($cols as $c) echo "- " . $c['name'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
