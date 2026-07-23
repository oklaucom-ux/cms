<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name = 'users';");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($tables as $table) {
    echo $table['sql'] . "\n\n";
}
