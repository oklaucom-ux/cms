<?php
/**
 * Automated Database Backup Script
 * Usage via cPanel Cron: php /home/yourusername/public_html/cron_backup.php token=YOUR_SECRET_TOKEN
 * Usage via Web: https://yourdomain.com/cron_backup.php?token=YOUR_SECRET_TOKEN
 */

// Define a secure token (change this to a strong random string in production)
define('CRON_TOKEN', 'cyno_secure_backup_2026');

// Check token
$provided_token = $_GET['token'] ?? null;
if (php_sapi_name() === 'cli') {
    // Check CLI args
    foreach ($argv as $arg) {
        if (strpos($arg, 'token=') === 0) {
            $provided_token = substr($arg, 6);
        }
    }
}

if ($provided_token !== CRON_TOKEN) {
    http_response_code(403);
    die("Unauthorized.\n");
}

$dbFile = __DIR__ . '/database.sqlite';
$backupDir = __DIR__ . '/backups';

if (!file_exists($dbFile)) {
    die("Database not found.\n");
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    // Secure the backup directory from web access
    file_put_contents($backupDir . '/.htaccess', "Order allow,deny\nDeny from all\n");
}

$timestamp = date('Y-m-d_H-i-s');
$backupZip = $backupDir . '/backup_' . $timestamp . '.zip';

$zipCreated = false;
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($backupZip, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($dbFile, 'database.sqlite');
        $zip->close();
        echo "Backup created successfully (Zipped): " . basename($backupZip) . "\n";
        $zipCreated = true;
    }
}

if (!$zipCreated) {
    // Fallback to direct copy if ZipArchive is missing or failed
    $backupCopy = $backupDir . '/backup_' . $timestamp . '.sqlite';
    if (copy($dbFile, $backupCopy)) {
        echo "Backup created successfully (Direct Copy): " . basename($backupCopy) . "\n";
    } else {
        die("Failed to create backup copy.\n");
    }
}

// Cleanup backups older than 7 days
$files = glob($backupDir . '/backup_*.*');
$now = time();
$deleted = 0;

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 7 * 24 * 60 * 60) {
            unlink($file);
            $deleted++;
        }
    }
}

if ($deleted > 0) {
    echo "Cleaned up $deleted old backup(s).\n";
}
