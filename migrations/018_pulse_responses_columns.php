<?php
// migrations/018_pulse_responses_columns.php
global $pdo, $use_mysql;

if (isset($use_mysql) && $use_mysql) {
    $queries = [
        "ALTER TABLE pulse_responses ADD COLUMN score INTEGER NULL",
        "ALTER TABLE pulse_responses ADD COLUMN comment TEXT NULL",
        "ALTER TABLE pulse_responses ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
        } catch (Exception $e) {
            // Ignore if columns already exist
        }
    }
} else {
    // For SQLite, if the columns don't exist
    $queries = [
        "ALTER TABLE pulse_responses ADD COLUMN score INTEGER",
        "ALTER TABLE pulse_responses ADD COLUMN comment TEXT",
        "ALTER TABLE pulse_responses ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];
    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
        } catch (Exception $e) {
            // Ignore
        }
    }
}

return [];
