<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode([]);
    exit;
}

$me = $_SESSION['login_id'];

// Get Admins and Super Admins
$stmt = $pdo->prepare("
    SELECT login_id, name, role FROM users WHERE role IN ('Admin', 'Super Admin', 'System Admin') AND status = 'Active'
    UNION
    SELECT login_id, name, 'Super Admin' as role FROM super_admins
");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get explicitly assigned employees
$stmt2 = $pdo->prepare("
    SELECT u.login_id, u.name, u.role 
    FROM users u 
    JOIN client_assignments ca ON u.login_id = ca.employee_id 
    WHERE ca.client_id = ? AND u.status = 'Active'
");
$stmt2->execute([$me]);
$assigned = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Merge and remove duplicates
$all = array_merge($admins, $assigned);
$unique = [];
$seen = [];
foreach($all as $u) {
    if(!isset($seen[$u['login_id']])) {
        $seen[$u['login_id']] = true;
        $unique[] = $u;
    }
}

// Sort alphabetically
usort($unique, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

echo json_encode($unique);
