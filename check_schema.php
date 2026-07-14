<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once 'includes/db.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$schema = [];
foreach ($tables as $table) {
    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $schema[$table] = $columns;
}

echo json_encode($schema, JSON_PRETTY_PRINT);
