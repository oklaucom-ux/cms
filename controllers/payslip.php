<?php
session_start();
require_once '../includes/db.php';
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT pr.*, u.name, u.email, u.department, u.designation, pp.bank_account, pp.tax_rate FROM payroll_runs pr JOIN users u ON pr.user_id=u.login_id JOIN payroll_profiles pp ON pr.user_id=pp.user_id WHERE pr.id=?");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) die("Payslip not found.");
// Only admin or the employee themselves
if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['login_id'] !== $r['user_id']) die("Unauthorized.");
$company = $GLOBAL_SETTINGS['company_name'] ?? 'Company';
$gross = $r['base_salary'] + $r['bonuses'];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payslip</title>
<style>*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}body{background:#f1f5f9;padding:40px}.slip{max-width:700px;margin:0 auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}.slip-head{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:white;padding:32px 40px;display:flex;justify-content:space-between}.slip-head h1{font-size:24px;font-weight:800}.slip-body{padding:32px 40px}.row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:14px}.row.total{font-weight:800;font-size:16px;border-top:2px solid #4f46e5;border-bottom:none;padding-top:16px;color:#4f46e5}.label{color:#6b7280}.val{font-weight:700;color:#111827}.section-title{font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:#9ca3af;font-weight:700;margin:20px 0 10px}.slip-foot{text-align:center;padding:20px;font-size:11px;color:#9ca3af;background:#f9fafb;border-top:1px solid #f3f4f6}@media print{body{background:white;padding:0}.no-print{display:none}}</style></head>
<body>
<div class="no-print" style="text-align:center;margin-bottom:20px"><button onclick="window.print()" style="background:#4f46e5;color:white;border:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">🖨️ Print Payslip</button>&nbsp;<button onclick="window.history.back()" style="background:#f3f4f6;color:#374151;border:none;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">← Back</button></div>
<div class="slip">
  <div class="slip-head">
    <div><h1><?= htmlspecialchars($company) ?></h1><div style="opacity:.8;font-size:13px;margin-top:4px">PAYSLIP</div></div>
    <div style="text-align:right;font-size:13px;opacity:.9"><div style="font-weight:700;font-size:16px"><?= date('F Y', strtotime($r['period'].'-01')) ?></div><div>Ref: PAY-<?= str_pad($r['id'],5,'0',STR_PAD_LEFT) ?></div></div>
  </div>
  <div class="slip-body">
    <div class="section-title">Employee Details</div>
    <div class="row"><span class="label">Name</span><span class="val"><?= htmlspecialchars($r['name']) ?></span></div>
    <div class="row"><span class="label">Employee ID</span><span class="val"><?= htmlspecialchars($r['user_id']) ?></span></div>
    <div class="row"><span class="label">Department</span><span class="val"><?= htmlspecialchars($r['department'] ?? '—') ?></span></div>
    <div class="row"><span class="label">Designation</span><span class="val"><?= htmlspecialchars($r['designation'] ?? '—') ?></span></div>
    <?php if($r['bank_account']): ?><div class="row"><span class="label">Bank Account</span><span class="val">****<?= substr($r['bank_account'],-4) ?></span></div><?php endif; ?>
    <div class="section-title">Earnings</div>
    <div class="row"><span class="label">Base Salary</span><span class="val"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($r['base_salary'],2) ?></span></div>
    <?php if($r['bonuses']>0): ?><div class="row"><span class="label">Bonus / Incentive</span><span class="val" style="color:#10b981">+$<?= number_format($r['bonuses'],2) ?></span></div><?php endif; ?>
    <div class="row"><span class="label"><strong>Gross Earnings</strong></span><span class="val"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($gross,2) ?></span></div>
    <div class="section-title">Deductions</div>
    <?php if($r['deductions']>0): ?><div class="row"><span class="label">Other Deductions</span><span class="val" style="color:#ef4444">-$<?= number_format($r['deductions'],2) ?></span></div><?php endif; ?>
    <div class="row"><span class="label">Income Tax (<?= round($r['tax_rate']*100,1) ?>%)</span><span class="val" style="color:#dc2626">-$<?= number_format($r['tax_amount'],2) ?></span></div>
    <div class="row total"><span>NET PAY</span><span><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($r['net_pay'],2) ?></span></div>
    <div style="margin-top:28px;display:flex;justify-content:space-between">
      <div style="text-align:center"><div style="width:140px;border-top:1px solid #374151;margin-bottom:6px"></div><div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em">Employer Signature</div></div>
      <div style="text-align:center"><div style="width:140px;border-top:1px solid #374151;margin-bottom:6px"></div><div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em">Employee Signature</div></div>
    </div>
  </div>
  <div class="slip-foot">Generated by <?= htmlspecialchars($company) ?> · <?= date('F j, Y') ?> · This is a system-generated payslip. No signature required if digitally processed.</div>
</div></body></html>
