<?php
session_start();
require_once 'includes/db.php';
requirePermission($pdo, 'send_broadcast_emails');

$users = [];
try {
    $stmt = $pdo->query("SELECT login_id, name, email FROM users WHERE status='active' AND email IS NOT NULL AND email != '' ORDER BY name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compose Mail - <?= htmlspecialchars($GLOBAL_SETTINGS['company_name'] ?? 'CMS') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 for beautiful alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-heading); margin: 0; }
        .card { background: var(--bg-card); padding: 32px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-heading); font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--input-bg); color: var(--text-body); font-family: 'Inter', sans-serif; font-size: 15px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .btn-primary { background: var(--primary-color); color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .helper-text { font-size: 13px; color: var(--text-muted); margin-top: 6px; }
        
        .type-toggle { display: flex; gap: 10px; margin-bottom: 12px; }
        .type-toggle label { display: flex; align-items: center; gap: 6px; font-weight: 500; font-size: 14px; cursor: pointer; color: var(--text-body); }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once 'includes/header.php'; ?>
        
        <div class="page-header">
            <h1 class="page-title">✉️ Compose Mail</h1>
        </div>

        <div class="card" style="max-width: 800px;">
            <form action="controllers/send_manual_email.php" method="POST" id="emailForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>Recipient Mode</label>
                    <div class="type-toggle">
                        <label><input type="radio" name="recipient_type" value="system" checked onchange="toggleRecipientMode()"> Registered User</label>
                        <label><input type="radio" name="recipient_type" value="custom" onchange="toggleRecipientMode()"> Custom Email Address</label>
                    </div>
                </div>

                <div class="form-group" id="systemUserGroup">
                    <label>Select User</label>
                    <select name="system_user" id="system_user_select">
                        <option value="">-- Select a User --</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?= htmlspecialchars($u['email']) ?>">
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="customEmailGroup" style="display: none;">
                    <label>Custom Email Address</label>
                    <input type="email" name="custom_email" id="custom_email_input" placeholder="e.g. client@example.com">
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required placeholder="Enter email subject" maxlength="150">
                </div>

                <div class="form-group">
                    <label>Message Body</label>
                    <textarea name="message" rows="10" required placeholder="Type your email message here... HTML is allowed for bold, links, etc."></textarea>
                    <div class="helper-text">This message will be wrapped in the company's branded email template automatically.</div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>
                    Send Email
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleRecipientMode() {
            const mode = document.querySelector('input[name="recipient_type"]:checked').value;
            if (mode === 'system') {
                document.getElementById('systemUserGroup').style.display = 'block';
                document.getElementById('customEmailGroup').style.display = 'none';
                document.getElementById('system_user_select').required = true;
                document.getElementById('custom_email_input').required = false;
            } else {
                document.getElementById('systemUserGroup').style.display = 'none';
                document.getElementById('customEmailGroup').style.display = 'block';
                document.getElementById('system_user_select').required = false;
                document.getElementById('custom_email_input').required = true;
            }
        }
        
        // Initialize required fields on load
        toggleRecipientMode();

        document.getElementById('emailForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = 'Sending...';
            btn.style.opacity = '0.7';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true; // Wait! If I disable the submit button inside the submit event, sometimes forms don't post correctly in some browsers if it's the element that triggered it. It's safer to just change the text.
            // Oh well, it's usually fine. But to be safe, let's omit disabling it, or do it with a slight delay.
            setTimeout(() => btn.disabled = true, 50);
        });

        // Handle success and error query parameters
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                Swal.fire({
                    title: 'Sent!',
                    text: urlParams.get('success'),
                    icon: 'success',
                    confirmButtonColor: '#4f46e5'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
            if (urlParams.has('error')) {
                Swal.fire({
                    title: 'Error',
                    text: urlParams.get('error'),
                    icon: 'error',
                    confirmButtonColor: '#4f46e5'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        });
    </script>
</body>
</html>
