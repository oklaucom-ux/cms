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
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --border-card: rgba(255, 255, 255, 0.1);
            --text-heading: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --accent-primary: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.5);
        }

        body { 
            font-family: 'Inter', sans-serif;
            background: var(--bg-body); 
            color: var(--text-body); 
            min-height:100vh; 
            margin: 0;
            display:flex; 
            align-items:center; 
            justify-content:center; 
            position: relative;
            overflow-x: hidden;
        }

        /* Abstract Background */
        body::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: 
                radial-gradient(circle at 50% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 30%),
                radial-gradient(circle at 20% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
            z-index: 0;
            pointer-events: none;
        }

        .sandbox { 
            position: relative;
            z-index: 10;
            background: var(--bg-card); 
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-card); 
            border-radius: 24px; 
            padding: 40px; 
            width: 100%; 
            max-width: 650px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(255,255,255,0.02); 
        }

        .sandbox h1 { 
            color: var(--text-heading); 
            margin: 0 0 10px 0; 
            font-size:28px; 
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .sandbox p { 
            color: var(--text-muted); 
            margin: 0 0 30px 0; 
            font-size:15px; 
            line-height:1.6; 
        }

        .upload-zone { 
            border: 2px dashed rgba(59, 130, 246, 0.3); 
            background: rgba(0,0,0,0.2);
            padding: 30px; 
            border-radius: 16px; 
            text-align: center; 
            margin-bottom: 30px; 
            transition: all 0.3s;
        }
        
        .upload-zone:hover {
            border-color: var(--accent-primary);
            background: rgba(59, 130, 246, 0.05);
        }

        .upload-zone h3 {
            margin-top:0; 
            margin-bottom:15px; 
            font-size:16px; 
            font-weight: 700;
            color: var(--text-heading);
        }

        .input-dark {
            width: 100%; 
            padding: 14px; 
            margin-bottom: 16px; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.1); 
            background: rgba(0,0,0,0.3); 
            color: var(--text-heading);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s;
        }

        .input-dark:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #6366f1); 
            color: white; 
            border: none; 
            padding: 14px 24px; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            transition: all 0.3s;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }

        .logout { 
            position: fixed; 
            top: 30px; 
            right: 30px; 
            color: #f87171; 
            font-weight: 600; 
            text-decoration: none; 
            padding: 10px 20px;
            border-radius: 99px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            transition: all 0.2s;
            z-index: 50;
        }
        
        .logout:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .flash-msg {
            padding: 14px 20px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            font-size: 14px; 
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .flash-error {
            background: rgba(239, 68, 68, 0.1); 
            color: #f87171; 
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .flash-success {
            background: rgba(16, 185, 129, 0.1); 
            color: #34d399; 
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .archive-box {
            background: rgba(0,0,0,0.2); 
            border-radius: 16px; 
            padding: 24px; 
            border: 1px solid var(--border-card);
        }
    </style>
</head>
<body>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt me-2"></i> Secure Logout</a>
    
    <div class="sandbox">
        <h1 style="display:flex; align-items:center; gap:12px;">
            <div style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                <i class="fas fa-lock"></i>
            </div>
            Action Required
        </h1>
        <p>Welcome to the Corporate Intranet, <strong style="color:white;"><?= htmlspecialchars($_SESSION['name']) ?></strong>.<br><br>
        Your account has been provisioned, but network access is currently restricted. Human Resources requires you to upload your mandatory onboarding documents before your account can be fully activated.</p>
        
        <?php if(isset($_SESSION['flash_error'])): ?>
            <div class="flash-msg flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['flash_success'])): ?>
            <div class="flash-msg flash-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <div class="upload-zone">
            <h3><i class="fas fa-cloud-upload-alt me-2" style="color: var(--accent-primary);"></i> Secure Document Uploader</h3>
            <form action="controllers/upload_user_file.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($me) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                
                <input type="text" name="title" required placeholder="Document Name (e.g. Passport, NDA)" class="input-dark">
                
                <div style="position: relative; margin-bottom: 20px;">
                    <input type="file" name="hr_file" id="hr_file" required accept=".pdf,.doc,.docx,.jpg,.png" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;">
                    <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 14px; color: var(--text-muted); font-size: 14px; display: flex; justify-content: center; align-items: center; gap: 10px; pointer-events: none;">
                        <i class="fas fa-file-alt"></i> <span id="file-chosen">Choose a file... (PDF, DOCX, JPG)</span>
                    </div>
                </div>

                <button type="submit" class="btn-primary"><i class="fas fa-shield-alt me-2"></i> Transmit Secure File</button>
            </form>
        </div>

        <div class="archive-box">
            <h4 style="margin:0 0 16px 0; font-size:14px; font-weight: 700; color:var(--text-heading); display:flex; justify-content:space-between; align-items:center;">
                Uploaded Archive
                <i class="fas fa-folder-open text-muted" style="color:var(--text-muted);"></i>
            </h4>
            <div id="fileList" style="font-size:14px; color:var(--text-muted); display:flex; flex-direction:column; gap:12px;">
                <div style="text-align:center; padding: 10px;"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
        
        <p style="margin-top:30px; text-align:center; font-size:13px; font-weight:600; color:#34d399; background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 8px;">
            <i class="fas fa-info-circle me-1"></i> Once all required files are uploaded, HR will review them and remotely unlock your access.
        </p>
    </div>

    <script>
        // Update file name display
        document.getElementById('hr_file').addEventListener('change', function(){
            document.getElementById('file-chosen').textContent = this.files[0] ? this.files[0].name : "Choose a file... (PDF, DOCX, JPG)";
        });

        // Load files
        fetch('controllers/get_user_files.php?user_id=<?= urlencode($me) ?>')
        .then(r=>r.json())
        .then(files => {
            let html = '';
            if(!files.length) {
                html = '<div style="text-align:center; font-style:italic; padding:10px;">No documents uploaded yet.</div>';
            } else {
                files.forEach(f => {
                    const safeTitle = f.title.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    html += `<div style="display:flex; justify-content:space-between; align-items:center; background: rgba(255,255,255,0.03); padding:12px 16px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                        <strong style="color:var(--text-heading);"><i class="fas fa-file-pdf me-2" style="color:#ef4444; margin-right:8px;"></i> ${safeTitle}</strong>
                        <span style="font-size:11px; font-weight: 700; background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 4px 10px; border-radius: 20px;">Pending HR Review</span>
                    </div>`;
                });
            }
            document.getElementById('fileList').innerHTML = html;
        });
    </script>
</body>
</html>
