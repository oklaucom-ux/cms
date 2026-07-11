<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo '[]'; exit; }
$action = $_GET['action'] ?? 'list';
$me = $_SESSION['login_id'];

function getAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) {
        return "just now";
    }
    $minutes = round($diff / 60);
    if ($minutes < 60) {
        return $minutes . " min ago";
    }
    $hours = round($minutes / 60);
    if ($hours < 24) {
        return $hours . " hr ago";
    }
    $days = round($hours / 24);
    return $days . " days ago";
}

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$me]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$row) {
        $row['ago'] = getAgo($row['created_at']);
    }
    echo json_encode($results);
} elseif ($action === 'read') {
    $id = intval($_GET['id'] ?? 0);
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $me]);
    echo json_encode(['ok' => true]);
} elseif ($action === 'read_all') {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$me]);
    echo json_encode(['ok' => true]);
}

