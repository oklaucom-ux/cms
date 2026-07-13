<?php
return [

    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        login_id VARCHAR(255) UNIQUE NOT NULL,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        role TEXT NOT NULL,
        designation TEXT,
        department TEXT,
        status VARCHAR(255) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        role_id VARCHAR(255) UNIQUE NOT NULL,
        role_name TEXT NOT NULL,
        description TEXT,
        permissions VARCHAR(255) DEFAULT '[]'
    )",
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        login_id VARCHAR(255) NOT NULL,
        ip VARCHAR(255) NOT NULL,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS designations (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        designation_id VARCHAR(255) UNIQUE NOT NULL,
        designation_name TEXT NOT NULL,
        department TEXT
    )",
    "CREATE TABLE IF NOT EXISTS zones (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        zone_id VARCHAR(255) UNIQUE NOT NULL,
        zone_name TEXT NOT NULL,
        description TEXT,
        created_date TEXT
    )",
    "CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        location_id VARCHAR(255) UNIQUE NOT NULL,
        name TEXT NOT NULL,
        address TEXT,
        pin_code TEXT,
        zone TEXT,
        parent_location TEXT
    )",
    "CREATE TABLE IF NOT EXISTS policies (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        policy_id VARCHAR(255) UNIQUE NOT NULL,
        title TEXT NOT NULL,
        category TEXT,
        version TEXT,
        content TEXT,
        status VARCHAR(255) DEFAULT 'Active'
    )",
    "CREATE TABLE IF NOT EXISTS activities (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        activity_id VARCHAR(255) UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        included_members TEXT, -- stored as JSON string
        status TEXT,
        due_date TEXT
    )",
    "CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        task_id VARCHAR(255) UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        assigned_to TEXT, -- stored as JSON string
        due_date TEXT,
        priority TEXT,
        status VARCHAR(255) DEFAULT 'Pending',
        created_by TEXT
    )",
    "CREATE TABLE IF NOT EXISTS audit_trail (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_id TEXT,
        action TEXT,
        details TEXT
    )",
    "CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        sender_id TEXT NOT NULL,
        receiver_id TEXT NOT NULL,
        message TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(255) DEFAULT 'unread'
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
    "CREATE TABLE IF NOT EXISTS leaves (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        leave_type TEXT NOT NULL,
        reason TEXT,
        status VARCHAR(255) DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        date TEXT NOT NULL,
        clock_in DATETIME,
        clock_out DATETIME,
        status TEXT
    )",
    "CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        file_path TEXT NOT NULL,
        category TEXT,
        uploaded_by TEXT NOT NULL,
        visible_to_role VARCHAR(255) DEFAULT 'ALL',
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        invoice_id VARCHAR(255) UNIQUE NOT NULL,
        client_name TEXT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        issue_date TEXT NOT NULL,
        due_date TEXT NOT NULL,
        status VARCHAR(255) DEFAULT 'Unpaid'
    )",
    "CREATE TABLE IF NOT EXISTS training_courses (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        video_url TEXT NOT NULL,
        slides_url TEXT,
        quiz_json TEXT,
        passing_score INTEGER DEFAULT 0,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS training_assignments (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        course_id INTEGER,
        user_id TEXT,
        status VARCHAR(255) DEFAULT 'Pending',
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME
    )",
    "CREATE TABLE IF NOT EXISTS meetings (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        host_id TEXT NOT NULL,
        participants_list TEXT, -- stored as JSON string
        status VARCHAR(255) DEFAULT 'Scheduled',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS kpi_targets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        target_value DECIMAL(10, 2) NOT NULL,
        current_value DECIMAL(10, 2) DEFAULT 0,
        unit TEXT,
        deadline DATETIME,
        status VARCHAR(255) DEFAULT 'On Track',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS kpi_logs (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        kpi_id INTEGER NOT NULL,
        value_added DECIMAL(10, 2) NOT NULL,
        note TEXT,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        email TEXT NOT NULL,
        token TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS feedback (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        type TEXT NOT NULL,
        subject TEXT NOT NULL,
        details TEXT NOT NULL,
        is_anonymous INTEGER DEFAULT 0,
        submitted_by TEXT,
        status VARCHAR(255) DEFAULT 'Open',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTO_INCREMENT, 
        name TEXT NOT NULL, 
        client VARCHAR(255) DEFAULT 'Internal',
        client_id VARCHAR(255) DEFAULT NULL,
        budget DECIMAL(10,2) DEFAULT 0,
        deadline DATETIME,
        created_by VARCHAR(255),
        branch_id VARCHAR(255) DEFAULT 'Global HQ',
        ai_forecast VARCHAR(255) DEFAULT NULL,
        status VARCHAR(255) DEFAULT 'Planning', 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS knowledge_base (
        id INTEGER PRIMARY KEY AUTO_INCREMENT, 
        category TEXT NOT NULL, 
        title TEXT NOT NULL, 
        content_body TEXT NOT NULL, 
        is_public INTEGER DEFAULT 1, 
        tags VARCHAR(255) DEFAULT '', 
        created_by TEXT, 
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
,
    // Auth & Security
    "CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts (login_id, ip, attempted_at)",
    "CREATE INDEX IF NOT EXISTS idx_password_resets_lookup ON password_resets (email, token, expires_at)",
    // Audit Trail
    "CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_trail (user_id)",
    "CREATE INDEX IF NOT EXISTS idx_audit_timestamp ON audit_trail (timestamp DESC)",
    // Tasks
    "CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks (status)",
    "CREATE INDEX IF NOT EXISTS idx_tasks_assigned ON tasks (assigned_to(255))",
    "CREATE INDEX IF NOT EXISTS idx_tasks_due ON tasks (due_date)",
    "CREATE INDEX IF NOT EXISTS idx_tasks_project ON tasks (project_id)",
    "CREATE INDEX IF NOT EXISTS idx_tasks_created_by ON tasks (created_by(255))",
    // Messages & Chat
    "CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages (receiver_id(255), status)",
    "CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages (sender_id(255))",
    // Leaves & Attendance
    "CREATE INDEX IF NOT EXISTS idx_leaves_user ON leaves (user_id(255), status)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_user_date ON attendance (user_id(255), date)",
    // Forms
    "CREATE INDEX IF NOT EXISTS idx_form_submissions_form ON form_submissions (form_id, submitted_at)",
    "CREATE INDEX IF NOT EXISTS idx_form_assignments_user ON form_assignments (assigned_to(255))",
    // Training
    "CREATE INDEX IF NOT EXISTS idx_training_assign_user ON training_assignments (user_id(255), status)",
    // KPI
    "CREATE INDEX IF NOT EXISTS idx_kpi_targets_user ON kpi_targets (user_id(255))",
    "CREATE INDEX IF NOT EXISTS idx_kpi_logs_kpi ON kpi_logs (kpi_id)",
    // Feedback
    "CREATE INDEX IF NOT EXISTS idx_feedback_status ON feedback (status)",
    // Documents
    "CREATE INDEX IF NOT EXISTS idx_documents_role ON documents (visible_to_role)",
    // Invoices
    "CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices (status)",

    // From header.php (already moved)
    "CREATE TABLE IF NOT EXISTS unified_tickets (id INTEGER PRIMARY KEY AUTO_INCREMENT, source VARCHAR(255) NOT NULL, ticket_number VARCHAR(255), requester_id VARCHAR(255), requester_name VARCHAR(255), department VARCHAR(255), subject TEXT NOT NULL, description TEXT NOT NULL, priority VARCHAR(255) DEFAULT 'Medium', status VARCHAR(255) DEFAULT 'Open', assigned_agent_id VARCHAR(255), resolution_notes TEXT, is_anonymous INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "ALTER TABLE tasks ADD COLUMN project_id INTEGER",
    "ALTER TABLE tasks ADD COLUMN dependency_id INTEGER",
    "ALTER TABLE tasks ADD COLUMN is_milestone INTEGER DEFAULT 0",

    // From notifications_api.php
    "CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, title TEXT NOT NULL, body TEXT, link VARCHAR(255) DEFAULT '', is_read INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_theme.php
    "CREATE TABLE IF NOT EXISTS user_preferences (user_id TEXT PRIMARY KEY, theme VARCHAR(255) DEFAULT 'light', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From vault_api.php
    "CREATE TABLE IF NOT EXISTS vault_passwords (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, title TEXT NOT NULL, username TEXT, password_enc TEXT NOT NULL, url TEXT, notes TEXT, category TEXT DEFAULT 'General', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS vault_tasks (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, title TEXT NOT NULL, description TEXT, priority VARCHAR(255) DEFAULT 'Medium', status VARCHAR(255) DEFAULT 'Pending', due_date TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From webhook_api.php
    "CREATE TABLE IF NOT EXISTS webhooks (id INTEGER PRIMARY KEY AUTO_INCREMENT, event_name VARCHAR(255) NOT NULL, target_url TEXT NOT NULL, secret_key TEXT, is_active INTEGER DEFAULT 1, created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_contract.php
    "CREATE TABLE IF NOT EXISTS contracts (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT NOT NULL, recipient_name TEXT, recipient_email TEXT, content TEXT, status VARCHAR(255) DEFAULT 'Draft', signature_data TEXT, signed_at DATETIME, created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_crm_activity.php / get_crm_activities.php
    "CREATE TABLE IF NOT EXISTS crm_activities (id INTEGER PRIMARY KEY AUTO_INCREMENT, lead_id INTEGER NOT NULL, user_id TEXT NOT NULL, type TEXT NOT NULL, note TEXT, logged_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From intranet_api.php
    "CREATE TABLE IF NOT EXISTS intranet_posts (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, content TEXT NOT NULL, image_path TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS intranet_likes (id INTEGER PRIMARY KEY AUTO_INCREMENT, post_id INTEGER NOT NULL, user_id TEXT NOT NULL)",
    "CREATE TABLE IF NOT EXISTS intranet_comments (id INTEGER PRIMARY KEY AUTO_INCREMENT, post_id INTEGER NOT NULL, user_id TEXT NOT NULL, comment TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From interview_api.php
    "CREATE TABLE IF NOT EXISTS interview_templates (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT NOT NULL, description TEXT, created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS interview_questions (id INTEGER PRIMARY KEY AUTO_INCREMENT, template_id INTEGER NOT NULL, question TEXT NOT NULL, type VARCHAR(255) DEFAULT 'text', time_limit INTEGER DEFAULT 120, sort_order INTEGER DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS interview_sessions (id INTEGER PRIMARY KEY AUTO_INCREMENT, template_id INTEGER, candidate_name TEXT NOT NULL, status VARCHAR(255) DEFAULT 'Pending', token VARCHAR(255) UNIQUE, created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME)",
    "CREATE TABLE IF NOT EXISTS app_settings (id INTEGER PRIMARY KEY AUTO_INCREMENT, setting_key VARCHAR(255) UNIQUE NOT NULL, setting_value TEXT)",
    "CREATE TABLE IF NOT EXISTS interview_answers (id INTEGER PRIMARY KEY AUTO_INCREMENT, session_id INTEGER NOT NULL, question_id INTEGER NOT NULL, answer TEXT, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From office_api.php
    "CREATE TABLE IF NOT EXISTS office_files (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT NOT NULL, content TEXT, file_type VARCHAR(255) DEFAULT 'document', created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS office_folders (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT NOT NULL, parent_id INTEGER DEFAULT 0, created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From recruitment_api.php
    "CREATE TABLE IF NOT EXISTS applicants (id INTEGER PRIMARY KEY AUTO_INCREMENT, first_name TEXT, last_name TEXT, email TEXT, phone TEXT, position_applied TEXT, resume_path TEXT, status VARCHAR(255) DEFAULT 'New', source TEXT DEFAULT 'Direct', notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_kudos.php
    "CREATE TABLE IF NOT EXISTS kudos (id INTEGER PRIMARY KEY AUTO_INCREMENT, from_user TEXT NOT NULL, to_user TEXT NOT NULL, message TEXT, badge TEXT DEFAULT 'star', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_po.php
    "CREATE TABLE IF NOT EXISTS purchase_orders (id INTEGER PRIMARY KEY AUTO_INCREMENT, po_number VARCHAR(255) UNIQUE, vendor TEXT, description TEXT, amount DECIMAL(10,2), status VARCHAR(255) DEFAULT 'Pending', requested_by TEXT, approved_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_review.php
    "CREATE TABLE IF NOT EXISTS performance_reviews (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, reviewer_id TEXT NOT NULL, period TEXT, rating INTEGER, comments TEXT, status VARCHAR(255) DEFAULT 'Draft', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_room_booking.php
    "CREATE TABLE IF NOT EXISTS rooms (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT NOT NULL, capacity INTEGER DEFAULT 10, amenities TEXT, location TEXT, status VARCHAR(255) DEFAULT 'Active')",
    "CREATE TABLE IF NOT EXISTS room_bookings (id INTEGER PRIMARY KEY AUTO_INCREMENT, room_id INTEGER NOT NULL, booked_by TEXT NOT NULL, title TEXT, start_time DATETIME NOT NULL, end_time DATETIME NOT NULL, status VARCHAR(255) DEFAULT 'Confirmed', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_survey.php
    "CREATE TABLE IF NOT EXISTS pulse_surveys (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT NOT NULL, questions_json TEXT, status VARCHAR(255) DEFAULT 'Active', created_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS pulse_responses (id INTEGER PRIMARY KEY AUTO_INCREMENT, survey_id INTEGER NOT NULL, user_id TEXT NOT NULL, answers_json TEXT, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From save_timesheet.php
    "CREATE TABLE IF NOT EXISTS timesheets (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT NOT NULL, project_id INTEGER, task_id INTEGER, hours DECIMAL(5,2), date TEXT, description TEXT, status VARCHAR(255) DEFAULT 'Draft', approved_by TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // From complete_course.php
    "CREATE TABLE IF NOT EXISTS training_results (id INTEGER PRIMARY KEY AUTO_INCREMENT, assignment_id INTEGER, user_id TEXT, score DECIMAL(5,2), passed INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",

    // ALTER TABLE migrations from various controllers
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
    
    // Added missing schemas based on controller INSERTs
    "CREATE TABLE IF NOT EXISTS assets (id INTEGER PRIMARY KEY AUTO_INCREMENT, asset_tag VARCHAR(255) UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, assigned_to VARCHAR(255), status VARCHAR(255) DEFAULT 'Unassigned', condition VARCHAR(255) DEFAULT 'Good', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
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


];
