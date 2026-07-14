<?php
global $pdo;

$queries = [
    // Ensure applicants exists
    "CREATE TABLE IF NOT EXISTS applicants (
        id INTEGER PRIMARY KEY AUTO_INCREMENT, 
        first_name TEXT, 
        last_name TEXT, 
        email TEXT, 
        phone TEXT, 
        position_applied TEXT, 
        resume_path TEXT, 
        status VARCHAR(255) DEFAULT 'New', 
        source TEXT DEFAULT 'Direct', 
        notes TEXT, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Ensure assets exists WITH BACKTICKS around condition
    "CREATE TABLE IF NOT EXISTS assets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT, 
        asset_tag VARCHAR(255) UNIQUE, 
        name TEXT NOT NULL, 
        type TEXT NOT NULL, 
        assigned_to VARCHAR(255), 
        status VARCHAR(255) DEFAULT 'Unassigned', 
        `condition` VARCHAR(255) DEFAULT 'Good', 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Throwable $e) {}
}

$alterations = [
    // ── assets ──────────────────────────────────────────────────────────────
    "ALTER TABLE assets ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE assets ADD COLUMN `condition` VARCHAR(255) DEFAULT 'Good'",

    // ── applicants (ATS/Recruitment) ────────────────────────────────────────
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
