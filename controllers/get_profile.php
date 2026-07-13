<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
    $stmt = $pdo->prepare("SELECT login_id, name, email, role, 'Executive' as department, 'Global HQ' as branch_id FROM super_admins WHERE login_id = ?");
} else {
    $stmt = $pdo->prepare("SELECT login_id, name, email, role, department, branch_id FROM users WHERE login_id = ?");
}
$stmt->execute([$_SESSION['login_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode($user);
} else {
    echo json_encode(['error' => 'User not found']);
}
exit();
