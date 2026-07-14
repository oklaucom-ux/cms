<?php
/**
 * Migration 005: Comprehensive schema reconciliation.
 *
 * The baseline migration (001) created many tables with column names that
 * differ from what the current application code actually uses. Because the
 * tables already exist, the inline CREATE TABLE IF NOT EXISTS statements in
 * each page are silently skipped and the queries then crash on missing columns.
 *
 * This migration adds every missing column to every affected table so the
 * live database matches the running code.
 */
global $pdo;

$alterations = [
    // ── timesheets ──────────────────────────────────────────────────────────
    // baseline: date, task_id, hours(DECIMAL), approved_by
    // code expects: entry_date, project_id, hours(REAL), description, status
    "ALTER TABLE timesheets ADD COLUMN entry_date DATE",
    "ALTER TABLE timesheets ADD COLUMN project_id INTEGER",

    // ── purchase_orders ─────────────────────────────────────────────────────
    // baseline: vendor, requested_by, approved_by
    // code expects: vendor_name, department, created_by
    "ALTER TABLE purchase_orders ADD COLUMN vendor_name TEXT",
    "ALTER TABLE purchase_orders ADD COLUMN department TEXT",
    "ALTER TABLE purchase_orders ADD COLUMN created_by TEXT",

    // ── kudos (rewards) ─────────────────────────────────────────────────────
    // baseline: from_user, to_user, message, badge
    // code expects: sender_id, receiver_id, points, message
    "ALTER TABLE kudos ADD COLUMN sender_id TEXT",
    "ALTER TABLE kudos ADD COLUMN receiver_id TEXT",
    "ALTER TABLE kudos ADD COLUMN points INTEGER DEFAULT 10",

    // ── performance_reviews ─────────────────────────────────────────────────
    // baseline: reviewer_id, period, rating, comments, status(Draft)
    // code expects: cycle_name, self_assessment_text, self_score,
    //               manager_id, manager_feedback, manager_score
    "ALTER TABLE performance_reviews ADD COLUMN cycle_name TEXT",
    "ALTER TABLE performance_reviews ADD COLUMN self_assessment_text TEXT",
    "ALTER TABLE performance_reviews ADD COLUMN self_score INTEGER",
    "ALTER TABLE performance_reviews ADD COLUMN manager_id TEXT",
    "ALTER TABLE performance_reviews ADD COLUMN manager_feedback TEXT",
    "ALTER TABLE performance_reviews ADD COLUMN manager_score INTEGER",

    // ── room_bookings ───────────────────────────────────────────────────────
    // baseline: booked_by   ->  code expects: user_id
    "ALTER TABLE room_bookings ADD COLUMN user_id TEXT",

    // ── projects ────────────────────────────────────────────────────────────
    // (some may already exist from migration 003 – duplicates are ignored)
    "ALTER TABLE projects ADD COLUMN client VARCHAR(255) DEFAULT 'Internal'",
    "ALTER TABLE projects ADD COLUMN client_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN budget DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN deadline DATETIME DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN created_by VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE projects ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE projects ADD COLUMN ai_forecast VARCHAR(255) DEFAULT NULL",

    // ── crm_leads ───────────────────────────────────────────────────────────
    // Ensure the table exists (was never in baseline)
    "CREATE TABLE IF NOT EXISTS crm_leads (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        lead_name VARCHAR(255) NOT NULL,
        company VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(255),
        value DECIMAL(10,2) DEFAULT 0,
        stage VARCHAR(255) DEFAULT 'Prospect',
        owner_id VARCHAR(255),
        assigned_to VARCHAR(255),
        source VARCHAR(255),
        custom_data TEXT,
        branch_id VARCHAR(255) DEFAULT 'Global HQ',
        follow_up_date DATE,
        last_contact DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(255) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── crm_activities ──────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS crm_activities (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        lead_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        type TEXT,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── api_keys ────────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        api_key VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── chat_channels & read state ──────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS chat_channels (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS channel_read_state (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        channel_name VARCHAR(255) NOT NULL,
        last_read_msg_id INTEGER DEFAULT 0
    )",

    // ── vendors ─────────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS vendors (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        company_name TEXT NOT NULL,
        contact_name TEXT,
        email TEXT,
        phone TEXT,
        payment_terms TEXT,
        scorecard_rating INTEGER DEFAULT 3,
        status VARCHAR(255) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── payroll tables ──────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS payroll_profiles (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) UNIQUE NOT NULL,
        base_salary REAL DEFAULT 0,
        tax_rate REAL DEFAULT 0.2,
        bank_account TEXT,
        currency VARCHAR(255) DEFAULT 'USD',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS payroll_runs (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        period TEXT NOT NULL,
        base_salary REAL,
        deductions REAL DEFAULT 0,
        bonuses REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        net_pay REAL,
        status VARCHAR(255) DEFAULT 'Draft',
        processed_by TEXT,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── ops kanban ──────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS ops_tasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        assigned_type VARCHAR(255) DEFAULT 'User',
        assigned_to TEXT,
        priority VARCHAR(255) DEFAULT 'Medium',
        status VARCHAR(255) DEFAULT 'Backlog',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS ops_subtasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        task_id INTEGER,
        title TEXT NOT NULL,
        is_completed INTEGER DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS ops_columns (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name TEXT NOT NULL,
        position INTEGER DEFAULT 0
    )",

    // ── webhooks ─────────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS webhooks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name TEXT NOT NULL,
        url TEXT NOT NULL,
        event_type TEXT NOT NULL,
        secret TEXT,
        is_active INTEGER DEFAULT 1,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── budgets ──────────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS budgets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        department VARCHAR(255) NOT NULL UNIQUE,
        allocated_amount REAL NOT NULL,
        year INTEGER NOT NULL
    )",

    // ── pulse_surveys & responses ────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS pulse_surveys (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        questions_json TEXT,
        status VARCHAR(255) DEFAULT 'Active',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS pulse_responses (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        survey_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        answers_json TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── audit_trail ──────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS audit_trail (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT,
        action TEXT,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── contracts ────────────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS contracts (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        party_name TEXT,
        type VARCHAR(255),
        value DECIMAL(10,2) DEFAULT 0,
        start_date DATE,
        end_date DATE,
        status VARCHAR(255) DEFAULT 'Draft',
        file_path TEXT,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    // ── assets ──────────────────────────────────────────────────────────────
    // baseline missing: branch_id (needed for branch-scoped queries)
    "ALTER TABLE assets ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE assets ADD COLUMN `condition` VARCHAR(255) DEFAULT 'Good'",

    // ── applicants (ATS/Recruitment) ────────────────────────────────────────
    // baseline: first_name, last_name, position_applied
    // code expects: name, email, phone, role_applied, status, resume_path
    "ALTER TABLE applicants ADD COLUMN name VARCHAR(255)",
    "ALTER TABLE applicants ADD COLUMN role_applied VARCHAR(255)",
    "ALTER TABLE applicants ADD COLUMN phone VARCHAR(255)",

    // ── expenses ────────────────────────────────────────────────────────────
    "ALTER TABLE expenses ADD COLUMN project_id INTEGER DEFAULT 0",
    "ALTER TABLE expenses ADD COLUMN amount REAL DEFAULT 0",
    "ALTER TABLE expenses ADD COLUMN user_id VARCHAR(255)",
    "ALTER TABLE expenses ADD COLUMN category VARCHAR(255)",
    "ALTER TABLE expenses ADD COLUMN description TEXT",
    "ALTER TABLE expenses ADD COLUMN receipt_url TEXT",
    "ALTER TABLE expenses ADD COLUMN status VARCHAR(255) DEFAULT 'Pending'",
    "ALTER TABLE expenses ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE expenses ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",

    // ── users ───────────────────────────────────────────────────────────────
    "ALTER TABLE users ADD COLUMN branch_id VARCHAR(255) DEFAULT 'Global HQ'",
    "ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN manager_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN api_key VARCHAR(255) DEFAULT NULL",

    // ── leaves ──────────────────────────────────────────────────────────────
    "ALTER TABLE leaves ADD COLUMN leave_type VARCHAR(255) DEFAULT 'Annual'",
    "ALTER TABLE leaves ADD COLUMN user_id VARCHAR(255)",

    // ── intranet_posts ──────────────────────────────────────────────────────
    "ALTER TABLE intranet_posts ADD COLUMN post_type VARCHAR(255) DEFAULT 'General'",

    // ── vault_tasks ─────────────────────────────────────────────────────────
    "ALTER TABLE vault_tasks ADD COLUMN reminder_minutes INTEGER DEFAULT 0",
    "ALTER TABLE vault_tasks ADD COLUMN reminder_sent INTEGER DEFAULT 0",

    // ── knowledge_base ──────────────────────────────────────────────────────
    "ALTER TABLE knowledge_base ADD COLUMN tags VARCHAR(255) DEFAULT ''",
    "ALTER TABLE knowledge_base ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP",

    // ── documents ───────────────────────────────────────────────────────────
    "ALTER TABLE documents ADD COLUMN version INTEGER DEFAULT 1",

    // ── training_assignments ────────────────────────────────────────────────
    "ALTER TABLE training_assignments ADD COLUMN user_answers TEXT",
    "ALTER TABLE training_assignments ADD COLUMN score INTEGER",

    // ── tasks ───────────────────────────────────────────────────────────────
    "ALTER TABLE tasks ADD COLUMN task_id VARCHAR(255)",
    "ALTER TABLE tasks ADD COLUMN name TEXT",
    "ALTER TABLE tasks ADD COLUMN description TEXT",
    "ALTER TABLE tasks ADD COLUMN assigned_to TEXT",
    "ALTER TABLE tasks ADD COLUMN due_date TEXT",
    "ALTER TABLE tasks ADD COLUMN priority TEXT",
    "ALTER TABLE tasks ADD COLUMN dependency_id INTEGER",
    "ALTER TABLE tasks ADD COLUMN is_milestone INTEGER DEFAULT 0",
    "ALTER TABLE tasks ADD COLUMN created_by TEXT",

    // ── missing tables ──────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS leave_types (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        annual_entitlement INTEGER DEFAULT 12,
        carry_over_max INTEGER DEFAULT 5
    )",
    "CREATE TABLE IF NOT EXISTS leave_balances (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        leave_type VARCHAR(255) NOT NULL,
        year INTEGER NOT NULL,
        entitlement INTEGER DEFAULT 0,
        used INTEGER DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS user_documents (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT,
        file_name TEXT,
        file_path TEXT,
        category TEXT,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS unified_tickets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        source VARCHAR(255) NOT NULL,
        ticket_number VARCHAR(255),
        requester_id VARCHAR(255),
        requester_name VARCHAR(255),
        department VARCHAR(255),
        subject TEXT NOT NULL,
        description TEXT NOT NULL,
        priority VARCHAR(255) DEFAULT 'Medium',
        status VARCHAR(255) DEFAULT 'Open',
        assigned_agent_id VARCHAR(255),
        resolution_notes TEXT,
        is_anonymous INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS onboarding_applications (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        email VARCHAR(255),
        position_applied VARCHAR(255),
        resume_link VARCHAR(255),
        status VARCHAR(255) DEFAULT 'Pending',
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS ticket_replies (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        ticket_id INTEGER,
        user_id TEXT,
        user_name TEXT,
        message TEXT,
        is_client INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS task_time_logs (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        task_id INTEGER,
        user_id TEXT,
        clock_in DATETIME,
        clock_out DATETIME,
        cost_incurred REAL DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS project_files (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        project_id INTEGER,
        uploader_id TEXT,
        file_name TEXT,
        file_path TEXT,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS training_modules (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        course_id INTEGER,
        title TEXT,
        content_type TEXT,
        content_url TEXT,
        sort_order INTEGER DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        title TEXT NOT NULL,
        body TEXT,
        link VARCHAR(255) DEFAULT '',
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS dynamic_forms (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT,
        frequency TEXT,
        schema_json TEXT,
        is_public INTEGER DEFAULT 0
    )",
    "CREATE TABLE IF NOT EXISTS form_assignments (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        form_id INTEGER,
        assigned_to TEXT
    )",
    "CREATE TABLE IF NOT EXISTS form_submissions (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        form_id INTEGER,
        user_id TEXT,
        data_json TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
];

foreach ($alterations as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // Silently skip duplicates and already-existing columns/tables
    }
}

return [];
