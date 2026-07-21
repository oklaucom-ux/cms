<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("ALTER TABLE training_courses ADD COLUMN category VARCHAR(255) DEFAULT 'General'");
    echo "Added category to training_courses\n";
} catch (Exception $e) { echo "category already exists or error: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE training_courses ADD COLUMN allow_self_enroll INTEGER DEFAULT 0");
    echo "Added allow_self_enroll to training_courses\n";
} catch (Exception $e) { echo "allow_self_enroll already exists or error: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE training_assignments ADD COLUMN due_date DATETIME");
    echo "Added due_date to training_assignments\n";
} catch (Exception $e) { echo "due_date already exists or error: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE training_assignments ADD COLUMN completed_modules TEXT");
    echo "Added completed_modules to training_assignments\n";
} catch (Exception $e) { echo "completed_modules already exists or error: " . $e->getMessage() . "\n"; }

echo "Migration 020 completed.\n";
