<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'manage_training');

try {
    // Auto-migrate notifications table
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(255),
        is_read TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$course_id = intval($_GET['course_id'] ?? 0);
$where = "WHERE ta.status = 'Assigned'";
$params = [];

if ($course_id > 0) {
    $where .= " AND ta.course_id = ?";
    $params[] = $course_id;
}

$pendingAssignments = $pdo->prepare("
    SELECT ta.id, ta.user_id, ta.course_id, c.title as course_title 
    FROM training_assignments ta 
    JOIN training_courses c ON ta.course_id = c.id 
    {$where}
");
$pendingAssignments->execute($params);
$list = $pendingAssignments->fetchAll(PDO::FETCH_ASSOC);

$remindersSent = 0;
$notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, 'training.php')");

foreach ($list as $item) {
    $title = "📢 Mandatory Training Reminder: " . $item['course_title'];
    $msg = "You have an incomplete training assignment for '" . $item['course_title'] . "'. Please log in to complete your course modules.";
    $notifStmt->execute([$item['user_id'], $title, $msg]);
    $remindersSent++;
}

header("Location: ../training.php?msg=" . urlencode("Sent {$remindersSent} compliance training reminders successfully!"));
exit;
