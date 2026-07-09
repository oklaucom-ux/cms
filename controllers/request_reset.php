<?php
// controllers/request_reset.php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (!$email) {
        header("Location: ../forgot_password.php?error=" . urlencode("Invalid Email Address"));
        exit;
    }

    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'Active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate Cryptographically Secure Token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $insert_stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->execute([$email, $token, $expires_at]);

            // Formulate reset link (dynamically generate absolute URI)
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $script_dir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
            
            $reset_link = $protocol . "://" . $host . $script_dir . "/reset_password.php?token=" . $token . "&email=" . urlencode($email);

            // Send Email payload via includes/mailer.php
            $subject = "Password Reset Request";
            $message = "Hello " . htmlspecialchars($user['name']) . ",<br><br>";
            $message .= "You recently requested to reset your password for your corporate account. ";
            $message .= "Click the secure link below to proceed:<br><br>";
            $message .= "<a href='{$reset_link}' style='display:inline-block; padding:10px 20px; background:#4f46e5; color:#fff; text-decoration:none; border-radius:5px;'>Reset My Password</a><br><br>";
            $message .= "This link will naturally expire in 1 hour.<br>";
            $message .= "If you did not request this reset, please ignore this email or contact your IT Supervisor.";
            
            sendSystemEmail($email, $subject, $message);
        }

        // Always redirect to success to prevent email enumeration targeting
        header("Location: ../forgot_password.php?status=success");
        exit;
    } catch (Exception $e) {
        header("Location: ../forgot_password.php?error=" . urlencode("A system error occurred. Please try again."));
        exit;
    }
} else {
    header("Location: ../forgot_password.php");
    exit;
}
