<?php
// includes/webhook_helper.php

function fireWebhook($pdo, $event_name, $payload_array) {
    // Create the table if it doesn't exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS webhooks (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            event_name TEXT NOT NULL,
            payload_url TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch(Exception $e) {}

    // Find active webhooks for this event
    $stmt = $pdo->prepare("SELECT payload_url FROM webhooks WHERE event_name = ? AND is_active = 1");
    $stmt->execute([$event_name]);
    $urls = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($urls)) return false;

    $json_payload = json_encode([
        'event' =>$event_name,
        'timestamp' => date('c'),
        'data' =>$payload_array
    ]);

    // Send asynchronously in a non-blocking way if possible, or simple cURL
    // For PHP on Windows/Linux, we'll use standard cURL with a short timeout to not block UI
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_payload),
            'User-Agent: Enterprise-CMS-Webhook/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second max timeout to prevent blocking
        curl_exec($ch);
        curl_close($ch);
    }
    
    return true;
}
?>
