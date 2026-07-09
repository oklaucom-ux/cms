<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_settings');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
    $db_file = __DIR__ . '/../database.sqlite';
    $temp_file = $_FILES['backup_file']['tmp_name'];
    
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK && pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION) === 'sqlite') {
        // Disconnect PDO explicitly to release locks on Windows
        $pdo = null; 
        
        if (move_uploaded_file($temp_file, $db_file)) {
            // Reconnect via a fresh PDO instance since db.php checks require_once
            try {
                $pdo = new PDO("sqlite:" . $db_file);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec("PRAGMA foreign_keys = ON;");
                $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Database Restore', 'Restored system database from backup file')");
            } catch (Exception $e) {}
            
            header("Location: ../settings.php?success=Database Restored Successfully");
            exit;
        } else {
            die("Failed to replace database file.");
        }
    } else {
        die("Invalid file format. Please upload a .sqlite file.");
    }
}
header("Location: ../settings.php");
?>
