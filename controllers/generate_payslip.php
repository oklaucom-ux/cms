<?php
// controllers/generate_payslip.php - Printable Employee Salary Slip Generator
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    die("Unauthorized Access");
}

$user_id = $_GET['user_id'] ?? $_SESSION['login_id'];
$month   = $_GET['month'] ?? date('F Y');

// Fetch user info
$stmtUser = $pdo->prepare("SELECT u.*, p.net_salary, p.basic_salary, p.hra, p.allowances, p.deductions FROM users u LEFT JOIN payroll_profiles p ON u.login_id = p.user_id WHERE u.login_id = ?");
$stmtUser->execute([$user_id]);
$employee = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee profile not found.");
}

$companyName = $GLOBAL_SETTINGS['company_name'] ?? 'Cyno Pharmaceuticals Ltd.';
$companyLogo = $GLOBAL_SETTINGS['company_logo'] ?? '';
$basic       = floatval($employee['basic_salary'] ?? 35000);
$hra         = floatval($employee['hra'] ?? 15000);
$allowances  = floatval($employee['allowances'] ?? 8000);
$gross       = $basic + $hra + $allowances;
$deductions  = floatval($employee['deductions'] ?? 3500);
$netSalary   = $employee['net_salary'] ? floatval($employee['net_salary']) : ($gross - $deductions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Payslip - <?= htmlspecialchars($employee['name']) ?> (<?= htmlspecialchars($month) ?>)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px 20px; }
        .payslip-card { max-width: 750px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #6366f1; padding-bottom: 20px; margin-bottom: 24px; }
        .company-title { font-size: 22px; font-weight: 800; color: #4f46e5; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .info-box { background: #f1f5f9; padding: 16px; border-radius: 10px; font-size: 13.5px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13.5px; }
        th { background: #4f46e5; color: #ffffff; padding: 10px 14px; text-align: left; }
        td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .amount { text-align: right; font-weight: 700; }
        .total-row { background: #e0e7ff; font-weight: 800; }
        .net-pay { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 18px; border-radius: 12px; text-align: center; margin-top: 20px; font-size: 20px; font-weight: 800; }
        .no-print { margin-top: 24px; text-align: center; }
        .btn-print { background: #4f46e5; color: white; border: none; padding: 12px 28px; border-radius: 99px; font-weight: 700; cursor: pointer; font-size: 14px; box-shadow: 0 4px 12px rgba(79,70,229,0.3); }
        @media print { .no-print { display: none; } body { background: white; padding: 0; } .payslip-card { box-shadow: none; border: none; } }
    </style>
</head>
<body>
    <div class="payslip-card">
        <div class="header">
            <div>
                <div class="company-title">🏢 <?= htmlspecialchars($companyName) ?></div>
                <div style="font-size: 13px; color: #64748b; margin-top: 4px;">Official Employee Salary Statement</div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 800; font-size: 16px; color: #4f46e5;">PAYSLIP</div>
                <div style="font-size: 13px; color: #64748b;"><?= htmlspecialchars($month) ?></div>
            </div>
        </div>

        <div class="grid-2">
            <div class="info-box">
                <strong>Employee Details:</strong><br>
                👤 <strong>Name:</strong> <?= htmlspecialchars($employee['name']) ?><br>
                🆔 <strong>Employee ID:</strong> <?= htmlspecialchars($employee['login_id']) ?><br>
                💼 <strong>Role:</strong> <?= htmlspecialchars($employee['role']) ?><br>
                🏢 <strong>Branch:</strong> <?= htmlspecialchars($employee['branch_id'] ?? 'Global HQ') ?>
            </div>
            <div class="info-box">
                <strong>Payment Metadata:</strong><br>
                📅 <strong>Pay Period:</strong> <?= htmlspecialchars($month) ?><br>
                🏦 <strong>Bank Account:</strong> ************<?= substr($employee['login_id'], -4) ?><br>
                📧 <strong>Email:</strong> <?= htmlspecialchars($employee['email'] ?: 'N/A') ?><br>
                🟢 <strong>Status:</strong> Processed & Paid
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Earnings</th>
                    <th class="amount">Amount (₹)</th>
                    <th>Deductions</th>
                    <th class="amount">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Salary</td>
                    <td class="amount">₹<?= number_format($basic, 2) ?></td>
                    <td>Provident Fund (PF)</td>
                    <td class="amount">₹<?= number_format($deductions * 0.5, 2) ?></td>
                </tr>
                <tr>
                    <td>House Rent Allowance (HRA)</td>
                    <td class="amount">₹<?= number_format($hra, 2) ?></td>
                    <td>Professional Tax & ESI</td>
                    <td class="amount">₹<?= number_format($deductions * 0.5, 2) ?></td>
                </tr>
                <tr>
                    <td>Special Allowances</td>
                    <td class="amount">₹<?= number_format($allowances, 2) ?></td>
                    <td>-</td>
                    <td class="amount">₹0.00</td>
                </tr>
                <tr class="total-row">
                    <td>Gross Earnings</td>
                    <td class="amount">₹<?= number_format($gross, 2) ?></td>
                    <td>Total Deductions</td>
                    <td class="amount">₹<?= number_format($deductions, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="net-pay">
            NET SALARY PAYABLE: ₹<?= number_format($netSalary, 2) ?>
        </div>

        <div style="margin-top: 32px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 16px;">
            This is a computer-generated document. No signature is required.
        </div>

        <div class="no-print">
            <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
        </div>
    </div>
</body>
</html>
