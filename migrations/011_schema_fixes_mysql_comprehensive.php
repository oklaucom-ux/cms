<?php
// migrations/011_schema_fixes_mysql_comprehensive.php
global $pdo;

$queries = [
    // Ensure all missing schemas from 001_baseline are created/altered
    "CREATE TABLE IF NOT EXISTS pulse_surveys (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT NOT NULL, questions_json TEXT, status VARCHAR(255) DEFAULT 'Active', created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS pulse_responses (id INTEGER PRIMARY KEY AUTO_INCREMENT, survey_id INTEGER NOT NULL, user_id TEXT NOT NULL, answers_json TEXT, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS timesheets (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, project_id INTEGER, task_id INTEGER, hours DECIMAL(5,2), date TEXT, description TEXT, status VARCHAR(255) DEFAULT 'Draft', approved_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS training_results (id INTEGER PRIMARY KEY AUTO_INCREMENT, assignment_id INTEGER, user_id TEXT, score DECIMAL(5,2), passed INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    
    "ALTER TABLE interview_sessions ADD COLUMN candidate_email TEXT",
    "ALTER TABLE interview_sessions ADD COLUMN ai_analysis TEXT",
    "ALTER TABLE interview_sessions ADD COLUMN id_photo_path TEXT",
    "ALTER TABLE interview_sessions ADD COLUMN anti_cheat_flags INTEGER DEFAULT 0",
    "ALTER TABLE interview_answers ADD COLUMN video_path TEXT",
    
    "ALTER TABLE office_files ADD COLUMN folder_id INTEGER DEFAULT 0",
    "ALTER TABLE office_files ADD COLUMN locked_by VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE office_files ADD COLUMN approval_status VARCHAR(255) DEFAULT 'Draft'",
    "ALTER TABLE office_files ADD COLUMN approved_by VARCHAR(255) DEFAULT NULL",
    
    "ALTER TABLE performance_reviews ADD COLUMN score_tech INTEGER DEFAULT 0",
    "ALTER TABLE performance_reviews ADD COLUMN score_comm INTEGER DEFAULT 0",
    "ALTER TABLE performance_reviews ADD COLUMN score_lead INTEGER DEFAULT 0",
    
    "ALTER TABLE users ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE users ADD COLUMN api_key VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN manager_id VARCHAR(255) DEFAULT NULL",
    
    "ALTER TABLE projects ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE projects ADD COLUMN ai_forecast VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN client VARCHAR(255) DEFAULT 'Internal'",
    "ALTER TABLE projects ADD COLUMN client_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN budget DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN deadline DATETIME",
    "ALTER TABLE projects ADD COLUMN created_by VARCHAR(255)",
    
    "ALTER TABLE vault_tasks ADD COLUMN reminder_minutes INTEGER DEFAULT 0",
    "ALTER TABLE vault_tasks ADD COLUMN reminder_sent INTEGER DEFAULT 0",
    
    "ALTER TABLE intranet_posts ADD COLUMN post_type VARCHAR(255) DEFAULT 'General'",
    
    "ALTER TABLE applicants ADD COLUMN name VARCHAR(255)",
    "ALTER TABLE applicants ADD COLUMN role_applied VARCHAR(255)",
    
    "ALTER TABLE activities ADD COLUMN priority VARCHAR(255) DEFAULT 'Normal'",
    "ALTER TABLE activities ADD COLUMN progress INTEGER DEFAULT 0",
    "ALTER TABLE activities ADD COLUMN created_by VARCHAR(255) DEFAULT NULL",
    
    "ALTER TABLE documents ADD COLUMN version INTEGER DEFAULT 1",
    "ALTER TABLE documents ADD COLUMN parent_doc_id INTEGER DEFAULT NULL",
    
    "ALTER TABLE messages ADD COLUMN file_path VARCHAR(255) DEFAULT NULL",

    "CREATE TABLE IF NOT EXISTS assets (id INTEGER PRIMARY KEY AUTO_INCREMENT, asset_tag VARCHAR(255) UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, assigned_to VARCHAR(255), status VARCHAR(255) DEFAULT 'Unassigned', `condition` VARCHAR(255) DEFAULT 'Good', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS expenses (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id VARCHAR(255) NOT NULL, amount DECIMAL(10,2) NOT NULL, category VARCHAR(255), description TEXT, receipt_path TEXT, status VARCHAR(255) DEFAULT 'Pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS payroll_runs (id INTEGER PRIMARY KEY AUTO_INCREMENT, cycle_name VARCHAR(255) NOT NULL, status VARCHAR(255) DEFAULT 'Draft', processed_by VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS payroll_profiles (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id VARCHAR(255) UNIQUE NOT NULL, base_salary DECIMAL(10,2) DEFAULT 0, tax_rate DECIMAL(5,2) DEFAULT 0, bank_account VARCHAR(255))",
    "CREATE TABLE IF NOT EXISTS vendors (id INTEGER PRIMARY KEY AUTO_INCREMENT, vendor_name VARCHAR(255) NOT NULL, contact_person VARCHAR(255), email VARCHAR(255), service_type VARCHAR(255), status VARCHAR(255) DEFAULT 'Active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS ticket_replies (id INTEGER PRIMARY KEY AUTO_INCREMENT, ticket_id INTEGER NOT NULL, user_id VARCHAR(255) NOT NULL, message TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS leave_balances (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id VARCHAR(255) NOT NULL, leave_type VARCHAR(255) NOT NULL, allocated INTEGER DEFAULT 0, used INTEGER DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS project_files (id INTEGER PRIMARY KEY AUTO_INCREMENT, project_id INTEGER NOT NULL, file_path TEXT NOT NULL, uploaded_by VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS user_documents (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id VARCHAR(255) NOT NULL, title VARCHAR(255), file_path TEXT NOT NULL, uploaded_by VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS rate_limits (id INTEGER PRIMARY KEY AUTO_INCREMENT, ip_address VARCHAR(255) NOT NULL, endpoint VARCHAR(255) NOT NULL, hits INTEGER DEFAULT 1, hit_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS training_modules (id INTEGER PRIMARY KEY AUTO_INCREMENT, course_id INTEGER NOT NULL, chapter_title VARCHAR(255) NOT NULL, video_url TEXT, slides_url TEXT, sort_order INTEGER DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS ops_columns (id INTEGER PRIMARY KEY AUTO_INCREMENT, board_id INTEGER DEFAULT 1, name VARCHAR(255) NOT NULL, position INTEGER DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS ops_tasks (id INTEGER PRIMARY KEY AUTO_INCREMENT, column_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, description TEXT, assigned_to VARCHAR(255), priority VARCHAR(255) DEFAULT 'Medium', status VARCHAR(255) DEFAULT 'Open', created_by VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS ops_subtasks (id INTEGER PRIMARY KEY AUTO_INCREMENT, task_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, is_completed INTEGER DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS onboarding_applications (id INTEGER PRIMARY KEY AUTO_INCREMENT, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255) NOT NULL, position_applied VARCHAR(255), resume_link TEXT, status VARCHAR(255) DEFAULT 'Pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    
    // Office missing table
    "CREATE TABLE IF NOT EXISTS office_folders (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT NOT NULL, parent_id INTEGER DEFAULT 0, created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Exception $e) {
        // Ignore duplicate column errors and missing table errors during ALTER
    }
}

return [];
