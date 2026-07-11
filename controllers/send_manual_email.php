<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';
requirePermission($pdo, 'send_broadcast_emails');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../send_email.php");
    exit;
}

$recipient_type = $_POST['recipient_type'] ?? 'system';
$system_user = $_POST['system_user'] ?? '';
$custom_email = trim($_POST['custom_email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($subject) || empty($message)) {
    header("Location: ../send_email.php?error=" . urlencode("Subject and Message body are required."));
    exit;
}

$to = '';
if ($recipient_type === 'system') {
    if (empty($system_user)) {
        header("Location: ../send_email.php?error=" . urlencode("Please select a registered user."));
        exit;
    }
    $to = $system_user;
} else {
    if (empty($custom_email) || !filter_var($custom_email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../send_email.php?error=" . urlencode("Please provide a valid custom email address."));
        exit;
    }
    $to = $custom_email;
}

// Build the HTML email
$html = buildEmailTemplate($subject, nl2br(htmlspecialchars_decode($message)));

// Send the email
$result = sendSystemEmail($to, $subject, $html);

if ($result['success']) {
    header("Location: ../send_email.php?success=" . urlencode("Email successfully sent to {$to}."));
} else {
    header("Location: ../send_email.php?error=" . urlencode("Failed to send email: " . $result['error']));
}
exit;
