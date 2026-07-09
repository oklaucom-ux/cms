<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_website');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['blocks'])) {
    
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Token Validation Failed");
    }

    $blocks = $_POST['blocks'];
    
    // Sanitize and ensure boolean values are correct
    $cleanBlocks = [];
    foreach ($blocks as $block) {
        $cleanBlocks[] = [
            'id' => trim($block['id'] ?? ''),
            'type' => trim($block['type'] ?? ''),
            'visible' => isset($block['visible']) && $block['visible'] == '1' ? true : false,
            'title' => trim($block['title'] ?? ''),
            'subtitle' => trim($block['subtitle'] ?? ''),
            'button_text' => trim($block['button_text'] ?? ''),
            'button_url' => trim($block['button_url'] ?? ''),
            'content' => trim($block['content'] ?? '')
        ];
    }

    $jsonStr = json_encode($cleanBlocks);

    // Save to settings
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES ('public_website_blocks', ?)");
    $stmt->execute([$jsonStr]);

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'System Update', 'Updated Public Website Layout')");
    
    header("Location: ../website_builder.php?success=Website%20layout%20saved");
}
