<?php
require 'includes/db.php';
$stmt = $pdo->query("PRAGMA table_info(reception_visitors)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("PRAGMA table_info(reception_packages)");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
