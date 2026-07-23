<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die("Invalid CSRF token");
    }

    $lang = $_POST['lang'] ?? 'en';
    if (in_array($lang, ['en', 'hi'])) {
        $_SESSION['preferred_lang'] = $lang;

        if (isset($_SESSION['login_id'])) {
            try {
                // Ensure column exists or use user_preferences table
                $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$_SESSION['login_id']]);
                if ($stmt->fetch()) {
                    $pdo->prepare("UPDATE user_preferences SET language = ? WHERE user_id = ?")->execute([$lang, $_SESSION['login_id']]);
                } else {
                    $pdo->prepare("INSERT INTO user_preferences (user_id, language) VALUES (?, ?)")->execute([$_SESSION['login_id'], $lang]);
                }
            } catch (Exception $e) {}
        }
    }
}
