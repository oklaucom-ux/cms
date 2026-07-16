<?php
// migrations/012_fix_training_courses_video_url.php
global $pdo;

// video_url and slides_url are now per-module (training_modules table),
// not per-course, so make them nullable on the course table.
$queries = [
    "ALTER TABLE training_courses MODIFY COLUMN video_url TEXT DEFAULT NULL",
    "ALTER TABLE training_courses MODIFY COLUMN slides_url TEXT DEFAULT NULL",
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Exception $e) {
        // Ignore if column doesn't exist
    }
}

return [];
