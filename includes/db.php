<?php
// includes/db.php
// Production Hardening: Log errors to file, never display to users
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
ini_set('log_errors', 1);
ini_set('error_log', $logDir . '/php_errors.log');

function getEnvVar($key) {
    return getenv($key) ?: ($_SERVER[$key] ?? $_ENV[$key] ?? '');
}

$db_url = getEnvVar('DATABASE_URL');
$db_host = getEnvVar('DB_HOST');

// Determine if we are using MySQL or SQLite
$use_mysql = false;
$mysql_dsn = '';
$mysql_user = '';
$mysql_pass = '';

if (!empty($db_url) && str_starts_with($db_url, 'mysql://')) {
    if (preg_match('/^mysql:\/\/([^:]+):(.*)@([^:\/]+)(?::(\d+))?\/(.+)$/', $db_url, $matches)) {
        $mysql_user = urldecode($matches[1]);
        $mysql_pass = urldecode($matches[2]);
        $host       = urldecode($matches[3]);
        $port       = $matches[4] ?: 3306;
        $dbname     = urldecode($matches[5]);
    } else {
        $parsed = parse_url($db_url);
        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? 3306;
        $dbname = ltrim($parsed['path'] ?? '', '/');
        $mysql_user = $parsed['user'] ?? '';
        $mysql_pass = $parsed['pass'] ?? '';
    }
    
    // Fallback if DATABASE_URL was constructed with empty credentials
    if (empty($mysql_pass)) {
        $mysql_pass = getEnvVar('DB_PASSWORD') ?: getEnvVar('DB_PASS') ?: getEnvVar('MYSQL_PASSWORD') ?: getEnvVar('MYSQL_ROOT_PASSWORD') ?: '';
    }
    if (empty($mysql_user)) {
        $mysql_user = getEnvVar('DB_USERNAME') ?: getEnvVar('DB_USER') ?: getEnvVar('MYSQL_USER') ?: 'root';
    }
    if (empty($dbname)) {
        $dbname = getEnvVar('DB_DATABASE') ?: getEnvVar('DB_NAME') ?: getEnvVar('MYSQL_DATABASE') ?: 'cms';
    }

    $mysql_dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $use_mysql = true;
} elseif (!empty($db_host)) {
    $host = $db_host;
    $port = getEnvVar('DB_PORT') ?: 3306;
    $dbname = getEnvVar('DB_NAME') ?: getEnvVar('DB_DATABASE') ?: 'cms';
    $mysql_user = getEnvVar('DB_USER') ?: getEnvVar('DB_USERNAME') ?: 'root';
    $mysql_pass = getEnvVar('DB_PASS') ?: getEnvVar('DB_PASSWORD') ?: '';
    $mysql_dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $use_mysql = true;
} else {
    // Fallback to local SQLite for development
    $db_file = __DIR__ . '/../database.sqlite';
}
// ── Secure Session Configuration ──────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,             // Session cookie (expires when browser closes)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,      // Only send over HTTPS in production
        'httponly'  => true,          // Prevent JavaScript access (XSS protection)
        'samesite' => 'Lax',         // CSRF protection for cross-site requests
    ]);
    session_start();
}

// ── SESSION TIMEOUT: 30 min inactivity ────────────────────────────────────────
$timeout = 30 * 60;
if (isset($_SESSION['login_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        $redir = (strpos($_SERVER['PHP_SELF'], '/controllers/') !== false) ? '../login.php' : 'login.php';
        session_unset();
        session_destroy();
        session_start();
        header("Location: {$redir}?error=" . urlencode("Session expired. Please log in again."));
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// ── CSRF Token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Universal POST CSRF Defense ───────────────────────────────────────────────
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $whitelist = ['submit_public_form.php','process_reset.php','submit_onboarding.php','notifications_api.php', 'save_contract.php', 'interview_api.php', 'webhook_api.php'];
    $current_script = basename($_SERVER['PHP_SELF']);
    if (!in_array($current_script, $whitelist)) {
        $submitted_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
            http_response_code(403);
            die("SECURITY VIOLATION: Invalid or missing CSRF token. Request Blocked.");
        }
    }
}

try {
    if ($use_mysql) {
        $pdo = new PDO($mysql_dsn, $mysql_user, $mysql_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        $pdo = new PDO("sqlite:" . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(255) PRIMARY KEY, setting_value TEXT)");
    $GLOBAL_SETTINGS = [];
    foreach($pdo->query("SELECT * FROM settings") as $r) {
        $GLOBAL_SETTINGS[$r['setting_key']] = $r['setting_value'];
    }

    // Auto-migrate new features
    if ($use_mysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_statuses (id INT AUTO_INCREMENT PRIMARY KEY, module VARCHAR(50), status_name VARCHAR(255), color VARCHAR(50) DEFAULT '#4f46e5', sort_order INT DEFAULT 0)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS notes (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), content TEXT, project_id INT, color VARCHAR(50) DEFAULT '#ffffff', is_pinned INT DEFAULT 0, created_by VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME)");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS meetings (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), room_name VARCHAR(255), host_id VARCHAR(255), scheduled_time DATETIME, status VARCHAR(50) DEFAULT 'Scheduled', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS workspaces (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), description TEXT, owner_id VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS workspace_members (workspace_id INT, user_id VARCHAR(255), role VARCHAR(50) DEFAULT 'Member', PRIMARY KEY(workspace_id, user_id))");
    } else {
        // Fix initial mis-aligned schema if present
        try {
            $cols = $pdo->query("PRAGMA table_info(custom_statuses)")->fetchAll(PDO::FETCH_ASSOC);
            $hasModule = false;
            foreach ($cols as $c) { if ($c['name'] === 'module') $hasModule = true; }
            if (!$hasModule) {
                $pdo->exec("DROP TABLE custom_statuses");
                $pdo->exec("DROP TABLE notes");
            }
        } catch (Exception $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS custom_statuses (id INTEGER PRIMARY KEY AUTOINCREMENT, module VARCHAR(50), status_name VARCHAR(255), color VARCHAR(50) DEFAULT '#4f46e5', sort_order INTEGER DEFAULT 0)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS notes (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), content TEXT, project_id INTEGER, color VARCHAR(50) DEFAULT '#ffffff', is_pinned INTEGER DEFAULT 0, created_by VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS meetings (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), room_name VARCHAR(255), host_id VARCHAR(255), scheduled_time DATETIME, status VARCHAR(50) DEFAULT 'Scheduled', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS workspaces (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), description TEXT, owner_id VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS workspace_members (workspace_id INTEGER, user_id VARCHAR(255), role VARCHAR(50) DEFAULT 'Member', PRIMARY KEY(workspace_id, user_id))");
    }
    
    // Auto-migrate workspace_id to projects, tasks, notes, documents
    try {
        if ($use_mysql) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN workspace_id INT DEFAULT NULL");
            $pdo->exec("ALTER TABLE tasks ADD COLUMN workspace_id INT DEFAULT NULL");
            $pdo->exec("ALTER TABLE notes ADD COLUMN workspace_id INT DEFAULT NULL");
            $pdo->exec("ALTER TABLE documents ADD COLUMN workspace_id INT DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE projects ADD COLUMN workspace_id INTEGER DEFAULT NULL");
            $pdo->exec("ALTER TABLE tasks ADD COLUMN workspace_id INTEGER DEFAULT NULL");
            $pdo->exec("ALTER TABLE notes ADD COLUMN workspace_id INTEGER DEFAULT NULL");
            $pdo->exec("ALTER TABLE documents ADD COLUMN workspace_id INTEGER DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // Auto-migrate roles permissions column to handle large JSON strings safely
    try {
        if ($use_mysql) {
            $pdo->exec("ALTER TABLE roles MODIFY COLUMN permissions LONGTEXT");
        }
    } catch (Exception $e) {}

} catch (PDOException $e) {
    http_response_code(500);
    $padding = str_repeat(" ", 512); // Prevent Chrome from replacing small 500 error pages
    die("<div style='font-family:sans-serif;text-align:center;margin-top:100px;'><h2>⚠️ System Temporarily Unavailable</h2><p>Our database is currently undergoing maintenance or experiencing a connection issue. Please try again in a few minutes.</p></div><!-- DB ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n<!-- PADDING FOR BROWSER: $padding -->");
}

// ── Load user theme preference from DB ───────────────────────────────────────
if (isset($_SESSION['login_id']) && empty($_SESSION['theme_loaded'])) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_preferences (user_id VARCHAR(255) PRIMARY KEY, theme TEXT, language VARCHAR(10) DEFAULT 'en', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        // Try adding language column if it doesn't exist
        try {
            if ($use_mysql) {
                $pdo->exec("ALTER TABLE user_preferences ADD COLUMN language VARCHAR(10) DEFAULT 'en'");
            } else {
                $pdo->exec("ALTER TABLE user_preferences ADD COLUMN language VARCHAR(10) DEFAULT 'en'");
            }
        } catch(Exception $e) {}

        $themeStmt = $pdo->prepare("SELECT theme, language FROM user_preferences WHERE user_id=?");
        $themeStmt->execute([$_SESSION['login_id']]);
        $prefs = $themeStmt->fetch(PDO::FETCH_ASSOC);
        if ($prefs) { 
            if ($prefs['theme']) $_SESSION['preferred_theme'] = $prefs['theme']; 
            if ($prefs['language']) $_SESSION['preferred_lang'] = $prefs['language']; 
        }
        $_SESSION['theme_loaded'] = true;
    } catch (Exception $e) {}
}

// ── RBAC ──────────────────────────────────────────────────────────────────────
function hasPermission($pdo, $permission_key) {
    if (empty($_SESSION['role'])) return false;
    if (in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Super Admin') return true;
    
    static $cache = null;
    if ($cache === null) {
        $stmt = $pdo->prepare("SELECT permissions FROM roles WHERE role_name = ?");
        $stmt->execute([$_SESSION['role']]);
        $roleData = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache = $roleData ? json_decode($roleData['permissions'], true) : [];
        if (!is_array($cache)) $cache = [];
    }

    // Direct match
    if (in_array($permission_key, $cache)) return true;

    // ── HIERARCHICAL RESOLUTION ──
    // If checking for 'create_users', 'edit_users', etc., 'manage_users' grants it automatically.
    $parts = explode('_', $permission_key);
    if (count($parts) >= 2) {
        $action = $parts[0]; // e.g. view, create, edit, delete
        $module = implode('_', array_slice($parts, 1)); // e.g. users, crm, assets
        
        // 'manage_module' grants all specific actions for that module
        if (in_array('manage_' . $module, $cache)) return true;
        
        // 'create_', 'edit_', or 'delete_' usually implies 'view_' rights for that module
        if ($action === 'view') {
            if (in_array('create_' . $module, $cache) || 
                in_array('edit_' . $module, $cache) || 
                in_array('delete_' . $module, $cache)) return true;
        }
    }

    return false;
}

function requirePermission($pdo, $permission_key) {
    if (!hasPermission($pdo, $permission_key)) {
        http_response_code(403);
        die("Unauthorized Action: Lacking strict {$permission_key} permissions.");
    }
}

require_once __DIR__ . '/lang.php';
