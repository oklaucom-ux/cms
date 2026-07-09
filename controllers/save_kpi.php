<?php
// controllers/save_kpi.php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if (!hasPermission($pdo, 'manage_kpi')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden: You do not have permission to assign KPIs"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $target_value = floatval($_POST['target_value'] ?? 0);
    $unit = $_POST['unit'] ?? '';
    $deadline = $_POST['deadline'] ?? '';

    if (empty($user_id) || empty($title) || $target_value <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing required fields or invalid target"]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO kpi_targets (user_id, title, description, target_value, unit, deadline, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'On Track')");
        $stmt->execute([$user_id, $title, $description, $target_value, $unit, $deadline]);

        $audit_stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)");
        $audit_stmt->execute([$_SESSION['login_id'], 'CREATE_KPI', "Created KPI '$title' for user $user_id"]);

        echo json_encode(["status" => "success", "message" => "KPI assigned successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
