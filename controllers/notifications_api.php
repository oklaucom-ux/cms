<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo '[]'; exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, title TEXT NOT NULL, body TEXT, link TEXT DEFAULT '', is_read INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

$action = $_GET['action'] ?? 'list';
$me = $_SESSION['login_id'];

if ($action === 'list') {
    $rows = $pdo->prepare("SELECT *, CASE
        WHEN (julianday('now') - julianday(created_at)) < 1/24.0 THEN round((julianday('now') - julianday(created_at))*24*60)||' min ago'
        WHEN (julianday('now') - julianday(created_at)) < 1 THEN round((julianday('now') - julianday(created_at))*24)||' hr ago'
        ELSE round(julianday('now') - julianday(created_at))||' days ago' END as ago
        FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
    $rows->execute([$me]);
    echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
} elseif ($action === 'read') {
    $id = intval($_GET['id'] ?? 0);
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $me]);
    echo json_encode(['ok' => true]);
} elseif ($action === 'read_all') {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$me]);
    echo json_encode(['ok' => true]);
}
