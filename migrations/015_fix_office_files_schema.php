<?php
// migrations/015_fix_office_files_schema.php
global $pdo;

// 1. Ensure office_folders exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS office_folders (
        id INTEGER PRIMARY KEY AUTO_INCREMENT, 
        name TEXT NOT NULL, 
        parent_id INTEGER DEFAULT 0, 
        created_by TEXT, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // SQLite fallback for AUTOINCREMENT
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS office_folders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            name TEXT NOT NULL, 
            parent_id INTEGER DEFAULT 0, 
            created_by TEXT, 
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e2) {}
}

// 2. Ensure office_files has the necessary columns (handling both MySQL and SQLite)
$cols = [
    'file_name' => 'VARCHAR(255)',
    'json_data' => 'LONGTEXT',
    'visibility' => "VARCHAR(255) DEFAULT 'Private'",
    'shared_with' => 'TEXT',
    'folder_id' => 'INTEGER DEFAULT 0',
    'locked_by' => 'VARCHAR(255)',
    'approval_status' => "VARCHAR(255) DEFAULT 'Draft'",
    'approved_by' => 'VARCHAR(255)'
];

foreach ($cols as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE office_files ADD COLUMN $col $def");
    } catch (Exception $e) {}
}

return [];
?>
