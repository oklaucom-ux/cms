<?php
// includes/notifications.php — In-app notification system

function createNotification($pdo, $user_id, $title, $body, $link = '') {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            user_id TEXT NOT NULL,
            title TEXT NOT NULL,
            body TEXT,
            link TEXT DEFAULT '',
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)");
        $stmt->execute([$user_id, $title, $body, $link]);
    } catch (Exception $e) { /* silently fail — never break main flow */ }
}

function notifyAll($pdo, $title, $body, $link = '', $excludeId = null) {
    try {
        $users = $pdo->query("SELECT login_id FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($users as $uid) {
            if ($uid !== $excludeId) createNotification($pdo, $uid, $title, $body, $link);
        }
    } catch (Exception $e) {}
}

function getUnreadCount($pdo, $user_id) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, title TEXT NOT NULL, body TEXT, link TEXT DEFAULT '', is_read INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        return $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$user_id]) 
            ? $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$user_id]) && false 
            : 0;
    } catch (Exception $e) { return 0; }
}

function getUnreadCountDirect($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}
