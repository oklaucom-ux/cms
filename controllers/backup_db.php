<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_settings');

$db_file = __DIR__ . '/../database.sqlite';

if (isset($use_mysql) && $use_mysql) {
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Database Backup', 'MySQL Backup']);
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Dump MySQL Database using PDO
    echo "-- CMS Database Backup (" . date('Y-m-d H:i:s') . ")\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo "-- Table structure for `$table`\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $create['Create Table'] . ";\n\n";
        
        echo "-- Dumping data for `$table`\n";
        $rows = $pdo->query("SELECT * FROM `$table`");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $vals = array_map(function($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote($val);
            }, array_values($row));
            echo "INSERT INTO `$table` VALUES(" . implode(', ', $vals) . ");\n";
        }
        echo "\n\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
} elseif (file_exists($db_file)) {
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Database Backup', 'SQLite Backup']);
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/x-sqlite3');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sqlite"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($db_file));
    readfile($db_file);
    exit;
} else {
    die("Database file not found or backup not supported for this configuration.");
}
?>
