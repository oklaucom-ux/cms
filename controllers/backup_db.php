<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_settings');

$db_file = __DIR__ . '/../database.sqlite';
if (file_exists($db_file)) {
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Database Backup', '']);
    
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
    die("Database file not found.");
}
?>
