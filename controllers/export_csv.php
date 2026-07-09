<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized");

$table = $_GET['table'] ?? '';
$allowed_tables = ['invoices', 'attendance', 'leaves', 'tasks', 'users', 'payroll_runs', 'assets', 'kpi_targets', 'expenses'];
$isAdmin = hasPermission($pdo, 'view_reports') || (in_array($_SESSION['role'], ['Admin', 'Super Admin'])) || ($_SESSION['role'] === 'Manager');

if (!in_array($table, $allowed_tables)) {
    die("Invalid export request.");
}

$me = $_SESSION['login_id'];

// Granular table-level authorization checks
if ($table === 'users'        && !$isAdmin) die("Unauthorized export.");
if ($table === 'payroll_runs' && !hasPermission($pdo, 'manage_payroll')) die("Unauthorized export.");
if ($table === 'assets'       && !hasPermission($pdo, 'view_assets')) die("Unauthorized export.");
if ($table === 'expenses'     && !hasPermission($pdo, 'view_expenses')) die("Unauthorized export.");
if ($table === 'invoices'     && !$isAdmin && $_SESSION['role'] !== 'Finance') die("Unauthorized export.");

// Build specific query based on permissions
if ($isAdmin) {
    switch ($table) {
        case 'leaves':
            $stmt = $pdo->query("SELECT l.*, u.name AS employee_name, u.department FROM leaves l JOIN users u ON l.user_id = u.login_id ORDER BY l.created_at DESC");
            break;
        case 'payroll_runs':
            $stmt = $pdo->query("SELECT pr.*, u.name, u.department FROM payroll_runs pr JOIN users u ON pr.user_id = u.login_id ORDER BY pr.period DESC, u.name");
            break;
        case 'assets':
            $stmt = $pdo->query("SELECT a.*, u.name AS assigned_to_name FROM assets a LEFT JOIN users u ON a.assigned_to = u.login_id ORDER BY a.name");
            break;
        case 'kpi_targets':
            $stmt = $pdo->query("SELECT k.*, u.name AS employee_name, u.department FROM kpi_targets k JOIN users u ON k.user_id = u.login_id ORDER BY k.deadline");
            break;
        case 'expenses':
            $stmt = $pdo->query("SELECT e.*, p.name AS project_name FROM expenses e LEFT JOIN projects p ON e.project_id = p.id ORDER BY e.created_at DESC");
            break;
        default:
            $stmt = $pdo->query("SELECT * FROM $table");
    }
} else {
    // Non-admin: restrict to own data only
    if ($table === 'attendance' || $table === 'leaves') {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$me]);
    } elseif ($table === 'tasks') {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to LIKE ?");
        $stmt->execute(["%$me%"]);
    } elseif ($table === 'kpi_targets') {
        $stmt = $pdo->prepare("SELECT * FROM kpi_targets WHERE user_id = ? ORDER BY deadline");
        $stmt->execute([$me]);
    } else {
        die("Export not permitted for your role.");
    }
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output as CSV stream
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $table . '_export_' . date('Ymd_His') . '.csv');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

if (count($rows) > 0) {
    fputcsv($output, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['No data available for export']);
}

fclose($output);
exit();
?>
