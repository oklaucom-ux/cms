<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['login_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Verify CSRF
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . (str_contains($_SERVER['HTTP_REFERER'], '?') ? '&' : '?') . "profile_error=" . urlencode("Security CSRF violation."));
        exit();
    }

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || strlen($password) < 8) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . (str_contains($_SERVER['HTTP_REFERER'], '?') ? '&' : '?') . "profile_error=" . urlencode("Password must be at least 8 characters long."));
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . (str_contains($_SERVER['HTTP_REFERER'], '?') ? '&' : '?') . "profile_error=" . urlencode("Passwords do not match."));
        exit();
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE login_id = ?");
    $stmt->execute([$hashed, $_SESSION['login_id']]);

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Change Password']);
    
    header("Location: " . $_SERVER['HTTP_REFERER'] . (str_contains($_SERVER['HTTP_REFERER'], '?') ? '&' : '?') . "profile_success=1");
    exit();
}
header("Location: ../dashboard.php");
exit();
