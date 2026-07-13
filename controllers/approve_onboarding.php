<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_onboarding');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Fetch Application
    $stmt = $pdo->prepare("SELECT * FROM onboarding_applications WHERE id = ? AND status = 'Pending'");
    $stmt->execute([$id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($app) {
        // Generate a unique login_id
        $base_id = strtolower(substr($app['first_name'], 0, 1) . $app['last_name']);
        $base_id = preg_replace('/[^a-z0-9]/', '', $base_id);
        $login_id = $base_id . rand(10, 99);
        // Ensure uniqueness
        $chk = $pdo->prepare("SELECT id FROM users WHERE login_id = ?");
        $chk->execute([$login_id]);
        while ($chk->fetchColumn()) {
            $login_id = $base_id . rand(10, 999);
            $chk->execute([$login_id]);
        }
        // Cryptographically strong temp password
        $raw_password = bin2hex(random_bytes(5)); // 10 hex chars
        $hashed = password_hash($raw_password, PASSWORD_DEFAULT);
        
        // Default role for new hires
        $role = "Employee"; 
        $dept = "General";
        $name = $app['first_name'] . ' ' . $app['last_name'];

        // Automatically provision corporate Matrix account but strictly lock it down pending document upload
        $insert = $pdo->prepare("INSERT INTO users (login_id, password, name, email, role, department, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending_Docs')");
        $insert->execute([$login_id, $hashed, $name, $app['email'], $role, $dept]);

        // Mark as Hired
        $update = $pdo->prepare("UPDATE onboarding_applications SET status = 'Hired' WHERE id = ?");
        $update->execute([$id]);

        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Onboarding Approval', '']);

        // Email credentials to new hire
        require_once '../includes/mailer.php';
        if (!empty($app['email'])) {
            sendSystemEmail(
                $app['email'],
                'Welcome to ' . ($GLOBAL_SETTINGS['company_name'] ?? 'the Company') . ' — Your Account Details',
                "Dear {$name},<br><br>Your account has been approved and provisioned.<br><br>"
                . "<strong>Login ID:</strong> {$login_id}<br>"
                . "<strong>Temporary Password:</strong> {$raw_password}<br><br>"
                . "Please log in and upload your required HR documents to complete onboarding. You will be prompted to change your password on first login.<br><br>Best regards,<br>HR Team"
            );
        }
    }

    header("Location: ../onboarding.php");
}
