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
        if (isset($pdo)) {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'") as $r) {
                $cfg[$r['setting_key']] = $r['setting_value'];
            }
        }
    } catch (Exception $e) {}

    // Support ENV variable fallbacks for Docker/Dokploy deployments
    $host     = $cfg['smtp_host'] ?: getenv('SMTP_HOST');
    $port     = (int)($cfg['smtp_port'] ?: getenv('SMTP_PORT') ?: 587);
    $user     = $cfg['smtp_user'] ?: getenv('SMTP_USER');
    $pass     = $cfg['smtp_pass'] ?: getenv('SMTP_PASS');
    $from     = $cfg['smtp_from'] ?: getenv('SMTP_FROM') ?: $user;

    if (empty($host) || empty($user) || empty($pass)) {
        return ['success' => false, 'error' => 'SMTP not configured. Please set up SMTP in System Settings or via Environment Variables.'];
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

    // SMTP Socket communication
    try {
        $errno = 0; $errstr = '';
        $scheme = ($port == 465) ? "ssl://" : "tcp://";
        
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $sock = @stream_socket_client("{$scheme}{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) throw new Exception("Connection failed: {$errstr} ({$errno})");

        $smtpRead = function($sock) { 
            $data = ''; 
            while(!feof($sock)) { 
                $line = fgets($sock, 512); 
                $data .= $line; 
                if(isset($line[3]) && $line[3] === ' ') break; 
            } 
            return $data; 
        };
        $smtpSend = function($sock, $cmd) use ($smtpRead) { 
            fwrite($sock, $cmd . "\r\n"); 
            return $smtpRead($sock); 
        };

        $resp = $smtpRead($sock); // Read greeting
        if (!str_starts_with(trim($resp), '220')) throw new Exception("SMTP Greeting failed: " . trim($resp));

        $smtpSend($sock, "EHLO " . (gethostname() ?: 'localhost')); // Say hello

        // STARTTLS (port 587)
        if ($port == 587) {
            $resp = $smtpSend($sock, "STARTTLS");
            if (str_starts_with(trim($resp), '220')) {
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("STARTTLS crypto enablement failed.");
                }
                $smtpSend($sock, "EHLO " . (gethostname() ?: 'localhost'));
            }
        }

        // AUTH LOGIN
        $resp = $smtpSend($sock, "AUTH LOGIN");
        if (!str_starts_with(trim($resp), '334')) throw new Exception("AUTH LOGIN failed: " . trim($resp));
        
        $smtpSend($sock, base64_encode($user));
        $resp = $smtpSend($sock, base64_encode($pass));
        if (!str_starts_with(trim($resp), '235')) {
            fclose($sock);
            throw new Exception("Authentication failed: " . trim($resp));
        }

        $resp = $smtpSend($sock, "MAIL FROM: <{$from}>");
        if (!str_starts_with(trim($resp), '250')) throw new Exception("MAIL FROM failed: " . trim($resp));
        
        $resp = $smtpSend($sock, "RCPT TO: <{$to}>");
        if (!str_starts_with(trim($resp), '250') && !str_starts_with(trim($resp), '251')) throw new Exception("RCPT TO failed: " . trim($resp));
        
        $resp = $smtpSend($sock, "DATA");
        if (!str_starts_with(trim($resp), '354')) throw new Exception("DATA failed: " . trim($resp));
        
        fwrite($sock, $headers . "\r\n" . $message . "\r\n.\r\n");
        $resp = $smtpRead($sock);
        if (!str_starts_with(trim($resp), '250')) throw new Exception("Message rejection: " . trim($resp));
        
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
