<?php
// controllers/send_payslip_email.php - Dispatches Payslip Email to Employee
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
$month   = $_POST['month'] ?? $_GET['month'] ?? date('F Y');

if (empty($user_id)) {
    echo json_encode(['success' => false, 'error' => 'Employee user_id required']);
    exit();
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_profiles (
        user_id VARCHAR(255) PRIMARY KEY,
        basic_salary DECIMAL(12,2) DEFAULT 25000,
        hra DECIMAL(12,2) DEFAULT 10000,
        allowances DECIMAL(12,2) DEFAULT 5000,
        deductions DECIMAL(12,2) DEFAULT 5000,
        net_salary DECIMAL(12,2) DEFAULT 35000
    )");
    try { $pdo->exec("ALTER TABLE payroll_profiles ADD COLUMN net_salary DECIMAL(12,2) DEFAULT 35000"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE payroll_profiles ADD COLUMN basic_salary DECIMAL(12,2) DEFAULT 25000"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE payroll_profiles ADD COLUMN hra DECIMAL(12,2) DEFAULT 10000"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE payroll_profiles ADD COLUMN allowances DECIMAL(12,2) DEFAULT 5000"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE payroll_profiles ADD COLUMN deductions DECIMAL(12,2) DEFAULT 5000"); } catch (Exception $e) {}

    $stmt = $pdo->prepare("SELECT u.*, p.net_salary FROM users u LEFT JOIN payroll_profiles p ON u.login_id = p.user_id WHERE u.login_id = ?");
    $stmt->execute([$user_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        echo json_encode(['success' => false, 'error' => 'Employee record not found']);
        exit();
    }

    if (empty($emp['email'])) {
        echo json_encode(['success' => false, 'error' => "Employee {$emp['name']} has no email address on profile"]);
        exit();
    }

    $netSalary = $emp['net_salary'] ? number_format($emp['net_salary'], 2) : '35,000.00';
    $subject = "Salary Payslip Statement for {$month} - {$emp['name']}";
    $payslipUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/controllers/generate_payslip.php?user_id=" . urlencode($user_id) . "&month=" . urlencode($month);

    $htmlBody = "
    <div style='font-family:sans-serif; max-width:600px; margin:0 auto; padding:24px; border:1px solid #e2e8f0; border-radius:12px;'>
        <h2 style='color:#4f46e5;'>🏢 Salary Statement for {$month}</h2>
        <p>Dear <strong>{$emp['name']}</strong>,</p>
        <p>Your monthly salary statement for <strong>{$month}</strong> has been processed.</p>
        <div style='background:#f1f5f9; padding:16px; border-radius:8px; margin:20px 0;'>
            <strong>Net Amount Paid:</strong> <span style='font-size:18px; color:#10b981; font-weight:bold;'>₹{$netSalary}</span><br>
            <strong>Employee ID:</strong> {$emp['login_id']}<br>
            <strong>Role:</strong> {$emp['role']}
        </div>
        <p>You can view and print your complete detailed payslip using the link below:</p>
        <p><a href='{$payslipUrl}' style='display:inline-block; padding:12px 24px; background:#4f46e5; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>📄 View Complete Payslip</a></p>
        <hr style='border:none; border-top:1px solid #e2e8f0; margin-top:24px;'>
        <p style='font-size:11px; color:#94a3b8;'>This is an automated system notification from your HR & Payroll portal.</p>
    </div>
    ";

    $sent = sendSystemEmail($emp['email'], $subject, $htmlBody);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => "Payslip emailed successfully to {$emp['email']}"]);
    } else {
        echo json_encode(['success' => false, 'error' => "Failed to send email to {$emp['email']}"]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
