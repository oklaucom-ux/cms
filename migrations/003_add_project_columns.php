<?php
global $pdo;

$queries = [
    "ALTER TABLE projects ADD COLUMN client VARCHAR(255) DEFAULT 'Internal'",
    "ALTER TABLE projects ADD COLUMN client_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN budget DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN deadline DATETIME DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN created_by VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE projects ADD COLUMN ai_forecast VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN status VARCHAR(255) DEFAULT 'Planning'"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Exception $e) {
        // ignore errors like duplicate column
    }
}

return [];
