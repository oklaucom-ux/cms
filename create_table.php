<?php
require_once 'includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id TEXT NOT NULL,
        employee_id TEXT NOT NULL,
        assigned_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(client_id, employee_id)
    )");
    echo "Table created.";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
