<?php
// controllers/send_push_notification.php - Real-Time Web Push Notification Dispatcher Engine
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

function sendWebPushNotification($pdo, $targetUserId, $title, $body, $url = 'dashboard.php') {
    try {
        // Ensure table exists
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

        // Fetch subscriptions for target user or 'all'
        if ($targetUserId === 'all') {
            $stmt = $pdo->query("SELECT * FROM push_subscriptions ORDER BY id DESC LIMIT 50");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([$targetUserId]);
        }
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subs)) {
            // Log fallback notification in internal database
            if ($targetUserId !== 'all') {
                createNotification($pdo, $targetUserId, $title, $body, $url);
            }
            return ['success' => true, 'delivered' => 0, 'message' => 'Internal notification created (No active browser push token)'];
        }

        // Internal notification record
        if ($targetUserId !== 'all') {
            createNotification($pdo, $targetUserId, $title, $body, $url);
        }

        $deliveredCount = 0;
        foreach ($subs as $sub) {
            $endpoint = $sub['endpoint'];
            // Send lightweight Web Push payload via cURL HTTP POST
            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'icon'  => '/assets/icons/icon-192x192.png',
                'url'   => $url
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'TTL: 86400'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 200 || $code == 201) {
                $deliveredCount++;
            }
        }

        return ['success' => true, 'delivered' => $deliveredCount, 'total_subs' => count($subs)];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
