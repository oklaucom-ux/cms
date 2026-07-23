<?php
require_once '../includes/db.php';
require_once '../includes/permissions.php';
requirePermission($pdo, 'view_meetings');
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    // Basic CSRF check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }

    $title = trim($_POST['title'] ?? 'Untitled Meeting');
    $scheduled_time = $_POST['scheduled_time'] ?? date('Y-m-d\TH:i');
    $room_name = trim($_POST['room_name'] ?? '');

    if (empty($room_name)) {
        // Auto-generate room name
        $room_name = 'cms-' . bin2hex(random_bytes(8));
    }

    // Format scheduled time for DB (DATETIME)
    $scheduled_datetime = date('Y-m-d H:i:s', strtotime($scheduled_time));

    try {
        $stmt = $pdo->prepare("INSERT INTO meetings (title, room_name, host_id, scheduled_time, status) VALUES (?, ?, ?, ?, 'Scheduled')");
        $stmt->execute([$title, $room_name, $_SESSION['login_id'], $scheduled_datetime]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
