<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS time_punches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id VARCHAR(255) NOT NULL,
        punch_type VARCHAR(50) NOT NULL,
        latitude REAL,
        longitude REAL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created time_punches table.<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
