<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$theme = ($_POST['theme'] ?? '') === 'dark' ? 'dark' : 'light';
$pdo->exec("CREATE TABLE IF NOT EXISTS user_preferences (user_id TEXT PRIMARY KEY, theme TEXT DEFAULT 'light', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->prepare("INSERT INTO user_preferences (user_id, theme) VALUES (?,?) ON CONFLICT(user_id) DO UPDATE SET theme=excluded.theme, updated_at=CURRENT_TIMESTAMP")
    ->execute([$_SESSION['login_id'], $theme]);

// Fix: Immediately sync the PHP session variable so the new page load uses the correct theme
$_SESSION['preferred_theme'] = $theme;

echo json_encode(['ok'=>true,'theme'=>$theme]);
