<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die("Security Error");
}

$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';

if (!$type || !$id) die("Invalid request.");

$table = '';
$nameField = '';
$redirect = '';
$permission = '';

switch($type) {
    case 'project':
        $table = 'projects';
        $nameField = 'name';
        $redirect = '../projects.php';
        $permission = 'create_projects';
        break;
    case 'task':
        $table = 'tasks';
        $nameField = 'title';
        $redirect = '../tasks.php';
        $permission = 'create_tasks';
        break;
    case 'contract':
        $table = 'contracts';
        $nameField = 'title';
        $redirect = '../contracts.php';
        $permission = 'manage_crm';
        break;
    case 'payslip':
        $table = 'payroll_runs';
        $nameField = '';
        $redirect = '../payroll.php';
        $permission = 'manage_payroll';
        break;
}

requirePermission($pdo, $permission);

$stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    unset($row['id']);
    
    if ($nameField && isset($row[$nameField])) {
        $row[$nameField] .= ' (Copy)';
    }

    if (isset($row['created_at'])) {
        $row['created_at'] = date('Y-m-d H:i:s');
    }
    
    if ($type === 'payslip' && isset($row['status'])) {
        $row['status'] = 'Draft'; // Reset duplicated payslip to draft
    }

    $cols = array_keys($row);
    $placeholders = array_fill(0, count($cols), '?');
    
    $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute(array_values($row));
}

header("Location: " . $redirect);
exit;
