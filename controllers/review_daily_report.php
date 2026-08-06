<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$isAdmin = in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'System Admin', 'Manager', 'HR Manager']);
if (!$isAdmin && !hasPermission($pdo, 'manage_daily_reports') && !hasPermission($pdo, 'view_users')) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = !empty($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $status = !empty($_POST['status']) ? trim($_POST['status']) : 'Reviewed';
    $feedback = trim($_POST['reviewer_feedback'] ?? '');

    if (!$reportId) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing report ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE daily_reports SET status = ?, reviewer_id = ?, reviewer_feedback = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $_SESSION['login_id'], $feedback, $reportId]);

        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Review Daily Report', "Reviewed report ID {$reportId} with status {$status}"]);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Report status and feedback saved successfully.']);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}
