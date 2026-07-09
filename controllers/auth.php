<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
require_once '../includes/notifications.php';

// ── Ensure login_attempts table ───────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    login_id TEXT NOT NULL,
    ip TEXT NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loginId = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // ── RATE LIMITING: max 5 attempts per login_id per 15 minutes ─────────────
    $windowStart = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE login_id=? AND ip=? AND attempted_at > ?");
    $stmt->execute([$loginId, $ip, $windowStart]);
    $attempts = (int)$stmt->fetchColumn();

    if ($attempts >= 5) {
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$loginId}', 'Rate Limited', 'Too many login attempts from IP {$ip}')");
        header("Location: ../login.php?error=" . urlencode("Too many login attempts. Please wait 15 minutes."));
        exit();
    }

    // Log this attempt
    $pdo->prepare("INSERT INTO login_attempts (login_id, ip) VALUES (?,?)")->execute([$loginId, $ip]);

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login_id = ?");
        $stmt->execute([$loginId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'Terminated') {
                $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$user['login_id']}', 'Failed Login', 'Terminated account attempted login.')");
                header("Location: ../login.php?error=" . urlencode("Account Terminated. Contact HR."));
                exit();
            }

            // ── Successful login — regenerate session ─────────────────────────
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['login_id']      = $user['login_id'];
            $_SESSION['name']          = $user['name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['status']        = $user['status'];
            $_SESSION['last_activity'] = time();

            // Clear attempts on success
            $pdo->prepare("DELETE FROM login_attempts WHERE login_id=?")->execute([$loginId]);

            $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$user['login_id']}', 'Login', 'User logged in successfully.')");
            createNotification($pdo, $user['login_id'], 'Welcome back, ' . $user['name'] . '!', 'You logged in successfully.', 'dashboard.php');

            header("Location: ../dashboard.php");
            exit();
        } else {
            header("Location: ../login.php?error=" . urlencode("Invalid credentials. " . (5 - $attempts - 1) . " attempt(s) remaining."));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../login.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}
header("Location: ../login.php");
