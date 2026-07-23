<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_settings');
require_once '../includes/flash.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$me = $_SESSION['login_id'];

// Generate a random secure key
$newKey = bin2hex(random_bytes(32)); // 64 char hex string

try {
    // Check if user already has an API key
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE user_id = ?");
    $stmt->execute([$me]);
    
    if ($stmt->fetch()) {
        // Update
        $pdo->prepare("UPDATE api_keys SET api_key = ? WHERE user_id = ?")->execute([$newKey, $me]);
    } else {
        // Insert
        $pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (?, ?)")->execute([$me, $newKey]);
    }
    
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$me, 'Security Update', 'Regenerated Webhook API Token.']);
    setFlash("API Key generated successfully. Use this token to authenticate Webhooks.");
    
} catch (Exception $e) {
    setFlash("Error generating key: " . $e->getMessage(), 'error');
}

// Redirect back to CRM where the user triggered it
header("Location: ../crm.php");
exit;
?>
