<?php
// migrations/020_enhance_training.php

$queries = [
    "ALTER TABLE training_courses ADD COLUMN category VARCHAR(255) DEFAULT 'General'",
    "ALTER TABLE training_courses ADD COLUMN allow_self_enroll INTEGER DEFAULT 0",
    "ALTER TABLE training_assignments ADD COLUMN due_date DATETIME",
    "ALTER TABLE training_assignments ADD COLUMN completed_modules TEXT"
];

return $queries;

