<?php
require 'includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vault_tasks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status VARCHAR(255) DEFAULT 'Pending',
        due_date DATETIME,
        reminder_minutes INTEGER DEFAULT 0,
        reminder_sent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
