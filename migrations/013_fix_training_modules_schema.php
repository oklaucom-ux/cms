<?php
// migrations/013_fix_training_modules_schema.php
global $pdo;

// training_modules was originally created in 002_scattered_schemas with an old structure.
// We need to add the new columns used by the modern LMS redesign.
$queries = [
    "ALTER TABLE training_modules ADD COLUMN chapter_title VARCHAR(255)",
    "ALTER TABLE training_modules ADD COLUMN video_url TEXT",
    "ALTER TABLE training_modules ADD COLUMN slides_url TEXT"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Exception $e) {}
}

return [];
