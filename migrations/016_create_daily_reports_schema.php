<?php
// migrations/016_create_daily_reports_schema.php

try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";

    $sql = "CREATE TABLE IF NOT EXISTS daily_reports (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        report_date DATE NOT NULL,
        tasks_completed TEXT,
        work_in_progress TEXT,
        blockers TEXT,
        plan_for_tomorrow TEXT,
        hours_worked DECIMAL(4,2) DEFAULT 8.00,
        status VARCHAR(50) DEFAULT 'Submitted',
        reviewer_id VARCHAR(255),
        reviewer_feedback TEXT,
        reviewed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
} catch (Exception $e) {
    error_log("Migration 016 Failed: " . $e->getMessage());
}
