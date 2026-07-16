<?php
// migrations/017_pulse_surveys_strict_fix.php
global $pdo, $use_mysql;

// We only need to run ALTER TABLE MODIFY in MySQL. 
// SQLite doesn't support MODIFY directly, and the local SQLite DB doesn't have these columns anyway.
if (isset($use_mysql) && $use_mysql) {
    $queries = [
        "ALTER TABLE pulse_surveys MODIFY title TEXT NULL",
        "ALTER TABLE pulse_surveys MODIFY questions_json TEXT NULL",
        "ALTER TABLE pulse_responses MODIFY user_id TEXT NULL",
        "ALTER TABLE pulse_responses MODIFY answers_json TEXT NULL"
    ];

    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
        } catch (Exception $e) {
            // Ignore if column doesn't exist
        }
    }
}

return [];
