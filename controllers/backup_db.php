<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_settings');

$isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
$filename = "cyno_cms_backup_" . date('Y-m-d_H-i-s');

if ($isMysql) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '.sql"');
    
    echo "-- Cyno CMS Database Backup Dump\n";
    echo "-- Generated At: " . date('Y-m-d H:i:s') . "\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "-- Table structure for `$table` --\n";
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $keys = array_keys($row);
            $vals = array_values($row);
            $escapedVals = array_map(function($v) use ($pdo) {
                if ($v === null) return "NULL";
                return $pdo->quote($v);
            }, $vals);
            
            echo "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedVals) . ");\n";
        }
        echo "\n";
    }
    exit;
} else {
    // SQLite File Export
    $dbPath = __DIR__ . '/../cms.sqlite';
    if (!file_exists($dbPath)) {
        $dbPath = __DIR__ . '/../database.sqlite';
    }
    
    if (file_exists($dbPath)) {
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="' . $filename . '.sqlite"');
        header('Content-Length: ' . filesize($dbPath));
        readfile($dbPath);
        exit;
    } else {
        die("SQLite database file not found.");
    }
}
