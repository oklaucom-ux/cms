<?php
session_start();
require_once 'includes/db.php';

// Force logout if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$me = $_SESSION['login_id'];
$status = $_SESSION['status'];

// If somehow their status got upgraded, dump them out of the sandbox
if ($status !== 'Pending_Docs') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - Onboarding</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: var(--bg-body); color: var(--text-body); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .sandbox { background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; padding: 40px; width: 100%; max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .sandbox h1 { color: var(--text-heading); margin-bottom: 8px; font-size:24px; }
        .sandbox p { color: var(--text-muted); margin-bottom: 24px; font-size:14px; line-height:1.5; }
        .upload-zone { border: 2px dashed var(--border-card); padding: 24px; border-radius: 8px; text-align: center; margin-bottom: 24px; background:var(--bg-body); }
        .logout { position: fixed; top: 24px; right: 24px; color: #ef4444; font-weight:600; text-decoration:none; }
    </style>
</head>
<body>
    <a href="logout.php" class="logout">Logout</a>
    <div class="sandbox">
        <h1 style="display:flex; align-items:center; gap:10px;">🔒 Action Required</h1>
        <p>Welcome to the Enterprise Matrix, <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>.<br>
        Your account has been provisioned, but access to the corporate network is currently locked. Human Resources requires you to upload specific compulsory documents before your account can be fully activated.</p>
        
        <?php if(isset($_SESSION['flash_error'])): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:12px; border-radius:6px; margin-bottom:16px; font-size:13px; font-weight:600;"><?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['flash_success'])): ?>
            <div style="background:#dcfce7; color:#15803d; padding:12px; border-radius:6px; margin-bottom:16px; font-size:13px; font-weight:600;"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>

        <div class="upload-zone">
            <h3 style="margin-top:0; margin-bottom:8px; font-size:15px; color:var(--text-heading);">Secure Document Uploader</h3>
            <form action="controllers/upload_user_file.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($me) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="text" name="title" required placeholder="Document Name (e.g. Passport, NDA)" style="width:100%; padding:10px; margin-bottom:12px; border-radius:6px; border:1px solid var(--border-card); background:var(--bg-card); color:var(--text-body);">
                <input type="file" name="hr_file" required style="width:100%; margin-bottom:16px; font-size:13px; color:var(--text-muted);" accept=".pdf,.doc,.docx,.jpg,.png">
                <button type="submit" style="background:#6366f1; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:600; cursor:pointer;" class="border-shadow">Upload Secure File</button>
            </form>
        </div>

        <div style="background:var(--bg-body); border-radius:8px; padding:16px; border:1px solid var(--border-card);">
            <h4 style="margin:0 0 12px 0; font-size:13px; color:var(--text-heading);">Your Uploaded Archive</h4>
            <div id="fileList" style="font-size:13px; color:var(--text-muted); display:flex; flex-direction:column; gap:8px;">
                Loading...
            </div>
        </div>
        
        <p style="margin-top:24px; text-align:center; font-size:12px; font-weight:600; color:#10b981;">
            Once all required files are uploaded, HR will review them and remotely unlock your access.
        </p>
    </div>

    <script>
        fetch('controllers/get_user_files.php?user_id=<?= urlencode($me) ?>')
        .then(r=>r.json())
        .then(files => {
            let html = '';
            if(!files.length) {
                html = '<em>No documents uploaded yet.</em>';
            } else {
                files.forEach(f => {
                    const safeTitle = f.title.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    html += `<div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:8px 12px; border-radius:4px; border:1px solid var(--border-card);">
                        <strong>${safeTitle}</strong>
                        <span style="font-size:11px;">Pending HR Review</span>
                    </div>`;
                });
            }
            document.getElementById('fileList').innerHTML = html;
        });
    </script>
</body>
</html>
