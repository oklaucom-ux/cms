<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$isAdminOrManager = in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'System Admin', 'Manager', 'HR Manager']) || hasPermission($pdo, 'manage_daily_reports');

if (!$isAdminOrManager) {
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit();
}

$date = !empty($_GET['date']) ? trim($_GET['date']) : '';
$user = !empty($_GET['user']) ? trim($_GET['user']) : '';
$status = !empty($_GET['status']) ? trim($_GET['status']) : '';

$where = [];
$params = [];

if (!empty($date)) {
    $where[] = "dr.report_date = ?";
    $params[] = $date;
}

if (!empty($user)) {
    $where[] = "dr.user_id = ?";
    $params[] = $user;
}

if (!empty($status)) {
    $where[] = "dr.status = ?";
    $params[] = $status;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    $sql = "SELECT dr.*, u.name as user_name 
            FROM daily_reports dr 
            LEFT JOIN users u ON dr.user_id = u.login_id 
            {$whereClause} 
            ORDER BY dr.report_date DESC, dr.created_at DESC 
            LIMIT 200";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'reports' => $reports]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
