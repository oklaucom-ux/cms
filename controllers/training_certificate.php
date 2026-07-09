<?php
session_start();
require_once '../includes/db.php';
$assignment_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT ta.*, c.title, c.description, u.name, u.login_id FROM training_assignments ta JOIN training_courses c ON ta.course_id=c.id JOIN users u ON ta.user_id=u.login_id WHERE ta.id=? AND ta.status='Completed'");
$stmt->execute([$assignment_id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cert) { die("Certificate not found or course not yet completed."); }
$company = $GLOBAL_SETTINGS['company_name'] ?? 'Company Management System';
$completedDate = $cert['completed_at'] ? date('F j, Y', strtotime($cert['completed_at'])) : date('F j, Y');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate — <?= htmlspecialchars($cert['title']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background:#f1f5f9; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; font-family:'Georgia',serif; padding:40px; }
.cert { width:800px; background:white; border:2px solid #4f46e5; border-radius:4px; padding:60px; text-align:center; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.15); }
.cert::before { content:''; position:absolute; inset:10px; border:1px solid #c7d2fe; border-radius:2px; pointer-events:none; }
.cert-logo { font-size:40px; margin-bottom:8px; }
.cert-company { font-size:14px; color:#6b7280; letter-spacing:.15em; text-transform:uppercase; font-family:'Arial',sans-serif; margin-bottom:40px; }
.cert-title { font-size:13px; color:#9ca3af; text-transform:uppercase; letter-spacing:.2em; font-family:'Arial',sans-serif; margin-bottom:16px; }
.cert-name { font-size:48px; color:#4f46e5; font-style:italic; margin-bottom:20px; font-weight:700; }
.cert-text { font-size:15px; color:#374151; line-height:1.8; margin-bottom:20px; }
.cert-course { font-size:24px; color:#111827; font-weight:700; margin-bottom:8px; }
.cert-date { font-size:13px; color:#9ca3af; margin-bottom:48px; font-family:'Arial',sans-serif; }
.cert-seal { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,#4f46e5,#7c3aed); display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:32px; }
.cert-footer { display:flex; justify-content:space-around; margin-top:40px; padding-top:20px; border-top:1px solid #e5e7eb; }
.cert-sig { text-align:center; }
.cert-sig-line { width:140px; border-top:1px solid #374151; margin:0 auto 6px; }
.cert-sig-label { font-size:11px; color:#9ca3af; font-family:'Arial',sans-serif; text-transform:uppercase; letter-spacing:.1em; }
@media print { body { background:white; padding:0; } .no-print { display:none; } }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:20px;display:flex;gap:12px;">
    <button onclick="window.print()" style="background:#4f46e5;color:white;border:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">🖨️ Print Certificate</button>
    <button onclick="window.history.back()" style="background:#f3f4f6;color:#374151;border:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">← Back</button>
</div>
<div class="cert">
    <div class="cert-logo">🎓</div>
    <div class="cert-company"><?= htmlspecialchars($company) ?></div>
    <div class="cert-title">Certificate of Completion</div>
    <div style="font-size:15px;color:#6b7280;margin-bottom:12px;">This is to certify that</div>
    <div class="cert-name"><?= htmlspecialchars($cert['name']) ?></div>
    <div class="cert-text">has successfully completed the training course</div>
    <div class="cert-course">«<?= htmlspecialchars($cert['title']) ?>»</div>
    <div class="cert-date">Completed on <?= $completedDate ?></div>
    <div class="cert-seal">✓</div>
    <div class="cert-footer">
        <div class="cert-sig"><div class="cert-sig-line"></div><div class="cert-sig-label">Training Administrator</div></div>
        <div style="text-align:center;font-size:11px;color:#d1d5db;font-family:monospace;">ID: <?= strtoupper(substr(md5($cert['id'].$cert['login_id']),0,12)) ?></div>
        <div class="cert-sig"><div class="cert-sig-line"></div><div class="cert-sig-label"><?= htmlspecialchars($company) ?></div></div>
    </div>
</div>
</body>
</html>
