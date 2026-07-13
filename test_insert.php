<?php
require_once 'includes/db.php';
$me = 'admin';
$content = 'Test post content';
$type = 'General';

try {
    $stmt = $pdo->prepare("INSERT INTO intranet_posts (user_id, content, post_type) VALUES (?, ?, ?)");
    $stmt->execute([$me, $content, $type]);
    echo "Inserted post id: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Insert Error: " . $e->getMessage() . "\n";
}
