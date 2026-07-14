<?php
global $pdo;

// 1. Create the table properly.
// We change `source TEXT DEFAULT 'Direct'` to `source VARCHAR(255) DEFAULT 'Direct'`
// because MySQL does not allow default values on TEXT columns!
$create_query = "CREATE TABLE IF NOT EXISTS applicants (
    id INTEGER PRIMARY KEY AUTO_INCREMENT, 
    first_name TEXT, 
    last_name TEXT, 
    email TEXT, 
    phone TEXT, 
    position_applied TEXT, 
    resume_path TEXT, 
    status VARCHAR(255) DEFAULT 'New', 
    source VARCHAR(255) DEFAULT 'Direct', 
    notes TEXT, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

// Convert AUTO_INCREMENT to AUTOINCREMENT for SQLite
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') {
    $create_query = str_ireplace('AUTO_INCREMENT', 'AUTOINCREMENT', $create_query);
}

try {
    $pdo->exec($create_query);
} catch (Throwable $e) {
    // If this fails, we want to know, but we'll try to continue
    error_log("Failed to create applicants table in 008: " . $e->getMessage());
}

$alterations = [
    "ALTER TABLE applicants ADD COLUMN name VARCHAR(255)",
    "ALTER TABLE applicants ADD COLUMN role_applied VARCHAR(255)",
    "ALTER TABLE applicants ADD COLUMN phone VARCHAR(255)"
];

foreach ($alterations as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        // Silently skip duplicates
    }
}

return [];
