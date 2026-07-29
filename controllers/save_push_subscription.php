<?php
// controllers/save_push_subscription.php - PWA Web Push Subscription Registrar
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$userId = $_SESSION['login_id'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['endpoint'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid Push Subscription Payload']);
    exit();
}

try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh TEXT,
        auth TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $endpoint = $data['endpoint'];
    $p256dh   = $data['keys']['p256dh'] ?? '';
    $auth     = $data['keys']['auth'] ?? '';

    // Check existing
    $stmtCheck = $pdo->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
    $stmtCheck->execute([$userId, $endpoint]);

    if (!$stmtCheck->fetchColumn()) {
        $stmtIns = $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$userId, $endpoint, $p256dh, $auth]);
    }

    echo json_encode(['success' => true, 'message' => 'Web Push notification subscription registered']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
