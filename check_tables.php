<?php
require 'includes/db.php';
$stmt = $pdo->query('SELECT name FROM sqlite_master WHERE type="table"');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
