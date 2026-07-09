<?php
// init_db.php
$is_production = true;
if ($is_production && php_sapi_name() !== 'cli') die("Script disabled in production.");
require_once __DIR__ . '/includes/db.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        login_id TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        role TEXT NOT NULL,
        designation TEXT,
        department TEXT,
        status TEXT DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        role_id TEXT UNIQUE NOT NULL,
        role_name TEXT NOT NULL,
        description TEXT,
        permissions TEXT DEFAULT '[]'
    )",
    "CREATE TABLE IF NOT EXISTS designations (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        designation_id TEXT UNIQUE NOT NULL,
        designation_name TEXT NOT NULL,
        department TEXT
    )",
    "CREATE TABLE IF NOT EXISTS zones (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        zone_id TEXT UNIQUE NOT NULL,
        zone_name TEXT NOT NULL,
        description TEXT,
        created_date TEXT
    )",
    "CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        location_id TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        address TEXT,
        pin_code TEXT,
        zone TEXT,
        parent_location TEXT
    )",
    "CREATE TABLE IF NOT EXISTS policies (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        policy_id TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        category TEXT,
        version TEXT,
        content TEXT,
        status TEXT DEFAULT 'Active'
    )",
    "CREATE TABLE IF NOT EXISTS activities (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        activity_id TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        included_members TEXT, -- stored as JSON string
        status TEXT,
        due_date TEXT
    )",
    "CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        task_id TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        assigned_to TEXT, -- stored as JSON string
        due_date TEXT,
        priority TEXT,
        status TEXT DEFAULT 'Pending',
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
        status TEXT DEFAULT 'unread'
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
        status TEXT DEFAULT 'Pending',
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
        visible_to_role TEXT DEFAULT 'ALL',
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        invoice_id TEXT UNIQUE NOT NULL,
        client_name TEXT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        issue_date TEXT NOT NULL,
        due_date TEXT NOT NULL,
        status TEXT DEFAULT 'Unpaid'
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
        status TEXT DEFAULT 'Pending',
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
        status TEXT DEFAULT 'Scheduled',
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
        status TEXT DEFAULT 'On Track',
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
        status TEXT DEFAULT 'Open',
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (PDOException $e) {
        die("Error creating table: " . $e->getMessage());
    }
}

// Seed Initial Data
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        // Hash passwords with BCRYPT
        $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
        $emp_pass = password_hash('password123', PASSWORD_BCRYPT);
        
        $insert_users = "INSERT INTO users (login_id, password, name, email, role, designation, department, status) VALUES 
            ('admin', '$admin_pass', 'System Admin', 'admin@example.com', 'Super Admin', 'Administrator', 'IT', 'Active'),
            ('john.doe', '$emp_pass', 'John Doe', 'john.doe@example.com', 'Employee', 'Software Engineer', 'Engineering', 'Active'),
            ('jane.smith', '$emp_pass', 'Jane Smith', 'jane.smith@example.com', 'Manager', 'Project Manager', 'Operations', 'Active')";
        $pdo->exec($insert_users);
        
        $insert_roles = "INSERT INTO roles (role_id, role_name, description) VALUES
            ('R000', 'Super Admin', 'God mode system access.'),
            ('R001', 'Admin', 'Full system access.'),
            ('R002', 'Manager', 'Manages teams and projects.'),
            ('R003', 'Employee', 'Standard user access.')";
        $pdo->exec($insert_roles);

        $insert_zones = "INSERT INTO zones (zone_id, zone_name, description, created_date) VALUES
            ('ZN001', 'North Zone', 'Covers all northern regions.', '2025-01-15'),
            ('ZN002', 'South Zone', 'Covers all southern regions.', '2025-02-20')";
        $pdo->exec($insert_zones);

        print("Database initialized and mock data inserted successfully!\n");
    } else {
        print("Database already initialized schema.\n");
    }
} catch (PDOException $e) {
    die("Error seeding data: " . $e->getMessage());
}

?>
