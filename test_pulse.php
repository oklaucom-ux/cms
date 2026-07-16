<?php
require 'includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pulse_surveys (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        question TEXT NOT NULL,
        status VARCHAR(255) DEFAULT 'Active',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table ok.\n";
} catch (Exception $e) {
    echo "Table error: " . $e->getMessage() . "\n";
}
try {
    $stmt = $pdo->prepare("INSERT INTO pulse_surveys (question, created_by) VALUES (?, ?)");
    $stmt->execute(['Test', 'admin']);
    echo "Insert ok.\n";
} catch (Exception $e) {
    echo "Insert error: " . $e->getMessage() . "\n";
}
