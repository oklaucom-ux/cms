<?php
// controllers/process_reset.php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: ../login.php?error=" . urlencode("Security token expired. Please try again."));
        exit;
    }

    $token = $_POST['token'] ?? '';
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $redirect_base = "../reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);

    if (empty($token) || empty($email)) {
        header("Location: ../login.php?error=" . urlencode("Invalid access mapping."));
        exit;
    }

    if (strlen($password) < 8) {
        header("Location: " . $redirect_base . "&error=" . urlencode("Password must be at least 8 characters natively."));
        exit;
    }

    if ($password !== $confirm) {
        header("Location: " . $redirect_base . "&error=" . urlencode("Passwords do not precisely match."));
        exit;
    }

    try {
        $pdo->beginTransaction();

        $current_time = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND token = ? AND expires_at > ?");
        $stmt->execute([$email, $token, $current_time]);
        $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset_record) {
            $pdo->rollBack();
            header("Location: ../login.php?error=" . urlencode("Invalid or completely expired reset token boundary."));
            exit;
        }

        // Hash new encrypted password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND status = 'Active'");
        $update_stmt->execute([$hashed_password, $email]);

        // Consume / truncate Token to avoid replay security attacks
        $delete_stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $delete_stmt->execute([$email]);

        // Intercept user login_id for Audit Logging natively
        $user_stmt = $pdo->prepare("SELECT login_id FROM users WHERE email = ?");
        $user_stmt->execute([$email]);
        $user_login_id = $user_stmt->fetchColumn();

        if ($user_login_id) {
            $audit_stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)");
            $audit_stmt->execute([$user_login_id, 'PASSWORD_RESET', 'Self-service password recovery protocol fully completed']);
        }

        $pdo->commit();
        
        header("Location: ../login.php?success=" . urlencode("Password securely reset. You can now login."));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: " . $redirect_base . "&error=" . urlencode("System architecture error processing request."));
        exit;
    }
} else {
    header("Location: ../login.php");
    exit;
}
