<?php
// migrations/010_schema_fixes_mysql.php
global $pdo;

$queries = [
    // 1. Employee Pulse Surveys
    "ALTER TABLE pulse_surveys ADD COLUMN question TEXT",
    "ALTER TABLE pulse_responses ADD COLUMN score INTEGER",
    "ALTER TABLE pulse_responses ADD COLUMN comment TEXT",
    
    // 2. Training LMS
    "ALTER TABLE training_courses ADD COLUMN expiration_months INTEGER",
    
    // 3. Room Booking
    "ALTER TABLE room_bookings ADD COLUMN user_id TEXT",
    
    // 4. Virtual HR Templates
    "ALTER TABLE interview_templates ADD COLUMN expected_keywords TEXT",
    "ALTER TABLE interview_questions ADD COLUMN question_text TEXT",
    "ALTER TABLE interview_questions ADD COLUMN time_limit_seconds INTEGER",
    "ALTER TABLE interview_sessions ADD COLUMN access_code VARCHAR(255)",
    "ALTER TABLE interview_sessions ADD COLUMN total_score INTEGER",
    "ALTER TABLE interview_sessions ADD COLUMN candidate_email TEXT",
    "ALTER TABLE interview_sessions ADD COLUMN ai_analysis TEXT",
    "ALTER TABLE interview_sessions ADD COLUMN id_photo_path TEXT",
    "ALTER TABLE interview_sessions ADD COLUMN anti_cheat_flags INTEGER DEFAULT 0",
    "ALTER TABLE interview_answers ADD COLUMN candidate_answer TEXT",
    "ALTER TABLE interview_answers ADD COLUMN time_taken INTEGER",
    "ALTER TABLE interview_answers ADD COLUMN score INTEGER",
    "ALTER TABLE interview_answers ADD COLUMN video_path TEXT"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Exception $e) {
        // Ignore column already exists errors
    }
}

return [];
