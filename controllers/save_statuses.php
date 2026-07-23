<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die("Security Error");
}

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    http_response_code(403);
    die("Unauthorized");
}

$statusIds = $_POST['status_id'] ?? [];
$modules = $_POST['module'] ?? [];
$statusNames = $_POST['status_name'] ?? [];
$colors = $_POST['color'] ?? [];
$sortOrders = $_POST['sort_order'] ?? [];

$keptIds = [];

for ($i = 0; $i < count($statusIds); $i++) {
    $id = $statusIds[$i];
    $mod = $modules[$i];
    $name = trim($statusNames[$i]);
    $color = $colors[$i];
    $sort = (int)($sortOrders[$i] ?? 0);
    
    if (empty($name)) continue;

    if ($id === 'new') {
        $stmt = $pdo->prepare("INSERT INTO custom_statuses (module, status_name, color, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$mod, $name, $color, $sort]);
        $keptIds[] = $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare("UPDATE custom_statuses SET status_name = ?, color = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$name, $color, $sort, $id]);
        $keptIds[] = $id;
    }
}

// Delete statuses that were removed from the UI
if (!empty($keptIds)) {
    $placeholders = str_repeat('?,', count($keptIds) - 1) . '?';
    $deleteStmt = $pdo->prepare("DELETE FROM custom_statuses WHERE id NOT IN ($placeholders)");
    $deleteStmt->execute($keptIds);
} else {
    // If all were deleted, just clear the table
    $pdo->exec("DELETE FROM custom_statuses");
}

$_SESSION['flash_message'] = "Custom Statuses successfully updated.";
header("Location: ../settings.php");
exit;
