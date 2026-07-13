<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
require_once '../includes/notifications.php';
requirePermission($pdo, 'manage_payroll');

$action = $_POST['action'] ?? '';

if ($action === 'generate') {
    $period = $_POST['period'];
    $users  = $pdo->query("SELECT login_id FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_COLUMN);
    $count  = 0;
    foreach ($users as $uid) {
        $exists = $pdo->prepare("SELECT id FROM payroll_runs WHERE user_id=? AND period=?");
        $exists->execute([$uid, $period]);
        if ($exists->fetchColumn()) continue;
        $profile = $pdo->prepare("SELECT * FROM payroll_profiles WHERE user_id=?");
        $profile->execute([$uid]);
        $p = $profile->fetch(PDO::FETCH_ASSOC);
        if (!$p) continue;
        $base     = $p['base_salary'];
        $tax      = round($base * $p['tax_rate'], 2);
        
        // Expense reimbursements
        $expStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id=? AND status='Approved' AND datetime(created_at) >= datetime(?||'-01 00:00:00') AND datetime(created_at) < datetime(?||'-01 00:00:00', '+1 month')");
        $expStmt->execute([$uid, $period, $period]);
        $total_expenses = floatval($expStmt->fetchColumn());

        $net      = round($base - $tax + $total_expenses, 2);

        $pdo->prepare("INSERT INTO payroll_runs (user_id, period, base_salary, deductions, bonuses, tax_amount, net_pay, status, processed_by) VALUES (?,?,?,0,?,?,?,'Draft',?)")
            ->execute([$uid, $period, $base, $total_expenses, $tax, $net, $_SESSION['login_id']]);
        $count++;
    }
    setFlash('success', "Generated payroll for {$count} employee(s).");
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Generate Payroll', '']);
    header("Location: ../payroll.php?period={$period}"); exit();
}

if ($action === 'edit_run') {
    $id         = intval($_POST['id']);
    $bonuses    = floatval($_POST['bonuses']);
    $deductions = floatval($_POST['deductions']);
    $status     = $_POST['status'];
    $run = $pdo->prepare("SELECT * FROM payroll_runs pr JOIN payroll_profiles pp ON pr.user_id=pp.user_id WHERE pr.id=?");
    $run->execute([$id]);
    $run = $run->fetch(PDO::FETCH_ASSOC);
    if ($run) {
        $tax = round(($run['base_salary'] + $bonuses) * $run['tax_rate'], 2);
        $net = round($run['base_salary'] + $bonuses - $deductions - $tax, 2);
        $pdo->prepare("UPDATE payroll_runs SET bonuses=?,deductions=?,tax_amount=?,net_pay=?,status=? WHERE id=?")
            ->execute([$bonuses, $deductions, $tax, $net, $status, $id]);
        // Notify if marked Paid
        if ($status === 'Paid') {
            createNotification($pdo, $run['user_id'], '💰 Salary Paid!', "Your salary of \${$net} for {$run['period']} has been processed.", 'payroll.php');
        }
    }
    $period = $run['period'] ?? date('Y-m');
    setFlash('success', 'Payroll entry updated.');
    header("Location: ../payroll.php?period={$period}"); exit();
}

if ($action === 'edit_profile') {
    $uid    = $_POST['user_id'];
    $salary = floatval($_POST['base_salary']);
    $tax    = floatval($_POST['tax_rate']);
    $bank   = $_POST['bank_account'] ?? '';
    $pdo->prepare("INSERT INTO payroll_profiles (user_id,base_salary,tax_rate,bank_account) VALUES (?,?,?,?) ON CONFLICT(user_id) DO UPDATE SET base_salary=excluded.base_salary, tax_rate=excluded.tax_rate, bank_account=excluded.bank_account")
        ->execute([$uid, $salary, $tax, $bank]);
    setFlash('success', "Salary profile updated for {$uid}.");
    header("Location: ../payroll.php"); exit();
}

if ($action === 'process_all') {
    $period = $_POST['period'];
    $pdo->prepare("UPDATE payroll_runs SET status='Processed' WHERE period=? AND status='Draft'")->execute([$period]);
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Process Payroll', '']);
    setFlash('success', "All draft entries marked as Processed.");
    header("Location: ../payroll.php?period={$period}"); exit();
}

header("Location: ../payroll.php");
