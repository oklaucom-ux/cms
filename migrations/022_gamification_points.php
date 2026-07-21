<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN cyno_points INTEGER DEFAULT 0");
    echo "Added cyno_points to users.<br>";
} catch (Exception $e) {
    // Ignore duplicate column errors
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS points_ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id VARCHAR(255) NOT NULL,
        points INTEGER NOT NULL,
        reason TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created points_ledger table.<br>";
} catch (Exception $e) {
    echo "Error creating points_ledger: " . $e->getMessage() . "<br>";
}
