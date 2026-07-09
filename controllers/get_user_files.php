<?php
session_start();
require_once '../includes/db.php';

$user_id = $_GET['user_id'] ?? '';
$isSelf = ($user_id === $_SESSION['login_id']);

if (!$isSelf && !hasPermission($pdo, 'manage_users')) {
    echo json_encode([]);
    exit;
}

$user_id = $_GET['user_id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
