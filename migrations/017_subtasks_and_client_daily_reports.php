<?php
// migrations/017_subtasks_and_client_daily_reports.php

try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";

    // 1. Create task_subtasks table for main tasks module
    $sql = "CREATE TABLE IF NOT EXISTS task_subtasks (
        id {$pkDef},
        task_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        is_completed TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // 2. Add client approval columns to daily_reports if missing
    try { $pdo->exec("ALTER TABLE daily_reports ADD COLUMN client_approved TINYINT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE daily_reports ADD COLUMN client_feedback TEXT"); } catch (Exception $e) {}

} catch (Exception $e) {
    error_log("Migration 017 Failed: " . $e->getMessage());
}
