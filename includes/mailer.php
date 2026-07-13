<?php
/**
 * Lightweight SMTP Mailer - Zero dependency (uses raw PHP sockets)
 * Usage: sendSystemEmail($to, $subject, $body_html, $replyTo = null)
 */
function sendSystemEmail(string $to, string $subject, string $body, string $replyTo = null): array {
    global $pdo;

    // Load SMTP config from DB
    $cfg = [];
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'") as $r) {
            $cfg[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {}

    $host     = $cfg['smtp_host'] ?? '';
    $port     = (int)($cfg['smtp_port'] ?? 587);
    $user     = $cfg['smtp_user'] ?? '';
    $pass     = $cfg['smtp_pass'] ?? '';
    $from     = $cfg['smtp_from'] ?? $user;

    if (empty($host) || empty($user) || empty($pass)) {
        return ['success' => false, 'error' => 'SMTP not configured. Please set up SMTP in System Settings.'];
    }

    // Build RFC-2822 compliant message
    $boundary = md5(uniqid('boundary_', true));
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$from}\r\n";
    if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "X-Mailer: CynoCMS/1.0\r\n";

    $plain = strip_tags($body);
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$body}\r\n\r\n";
    $message .= "--{$boundary}--";

    // SMTP Socket communication (STARTTLS on port 587)
    try {
        $errno = 0; $errstr = '';
        $sock = @fsockopen("tcp://{$host}", $port, $errno, $errstr, 15);
        if (!$sock) throw new Exception("Connection failed: {$errstr} ({$errno})");

        $smtpRead = function($sock) { $data = ''; while(!feof($sock)) { $data .= fgets($sock, 512); if(substr($data, 3, 1) === ' ') break; } return $data; };
        $smtpSend = function($sock, $cmd) use ($smtpRead) { fwrite($sock, $cmd . "\r\n"); return $smtpRead($sock); };

        $smtpRead($sock); // Read greeting
        $smtpSend($sock, "EHLO " . gethostname()); // Say hello

        // STARTTLS (port 587)
        if ($port == 587) {
            $smtpSend($sock, "STARTTLS");
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $smtpSend($sock, "EHLO " . gethostname());
        }

        // AUTH LOGIN
        $smtpSend($sock, "AUTH LOGIN");
        $smtpSend($sock, base64_encode($user));
        $resp = $smtpSend($sock, base64_encode($pass));
        if (!str_starts_with(trim($resp), '235')) {
            fclose($sock);
            throw new Exception("Authentication failed: " . trim($resp));
        }

        $smtpSend($sock, "MAIL FROM: <{$from}>");
        $smtpSend($sock, "RCPT TO: <{$to}>");
        $smtpSend($sock, "DATA");
        fwrite($sock, $headers . "\r\n" . $message . "\r\n.\r\n");
        $smtpRead($sock);
        $smtpSend($sock, "QUIT");
        fclose($sock);

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Branded HTML email wrapper
 */
function buildEmailTemplate(string $title, string $body, string $cta_text = '', string $cta_url = ''): string {
    global $GLOBAL_SETTINGS;
    $company = htmlspecialchars($GLOBAL_SETTINGS['company_name'] ?? 'Enterprise CMS');
    $btn = $cta_text ? "<p style='text-align:center;margin:28px 0;'><a href='{$cta_url}' style='background:#4f46e5;color:white;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;'>{$cta_text}</a></p>" : '';
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:580px;margin:40px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
    <div style="background:linear-gradient(135deg,#1e1b4b,#4f46e5);padding:28px 32px;">
        <h1 style="margin:0;color:white;font-size:22px;font-weight:800;">🏢 {$company}</h1>
        <p style="margin:6px 0 0;color:rgba(255,255,255,0.7);font-size:14px;">{$title}</p>
    </div>
    <div style="padding:32px;">{$body}{$btn}</div>
    <div style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;text-align:center;">
        This is an automated notification from {$company}. Please do not reply to this email.
    </div>
</div></body></html>
HTML;
}

/**
 * Retrieve user email by login_id from users or super_admins
 */
function getUserEmail($pdo, string $login_id): ?string {
    if (filter_var($login_id, FILTER_VALIDATE_EMAIL)) {
        return $login_id;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE login_id = ?");
        $stmt->execute([$login_id]);
        $email = $stmt->fetchColumn();
        if ($email) return $email;

        $stmt = $pdo->prepare("SELECT email FROM super_admins WHERE login_id = ?");
        $stmt->execute([$login_id]);
        $email = $stmt->fetchColumn();
        if ($email) return $email;

        return null;
    } catch (Exception $e) {
        return null;
    }
}
