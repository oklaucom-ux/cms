<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$me = $_SESSION['login_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Master Encryption Key (Derived from server secret + user ID for isolation)
$ENCRYPTION_KEY = hash('sha256', "CYNO_ENTERPRISE_SECRET_KEY_" . $me);
$ENCRYPTION_METHOD = "AES-256-CBC";

function encryptPassword($string, $key, $method) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($string, $method, $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptPassword($string, $key, $method) {
    list($encrypted_data, $iv) = explode('::', base64_decode($string), 2);
    return openssl_decrypt($encrypted_data, $method, $key, 0, $iv);
}

// Auto-Migrate Vault Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vault_passwords (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        website TEXT NOT NULL,
        username TEXT NOT NULL,
        encrypted_password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(Exception $e){}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vault_tasks (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status VARCHAR(255) DEFAULT 'Pending',
        due_date DATETIME,
        reminder_minutes INTEGER DEFAULT 0,
        reminder_sent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(Exception $e){}

// ----- PASSWORD VAULT -----
if ($action === 'list_passwords') {
    $stmt = $pdo->prepare("SELECT id, website, username, encrypted_password FROM vault_passwords WHERE user_id = ? ORDER BY website ASC");
    $stmt->execute([$me]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row) {
        $row['password'] = decryptPassword($row['encrypted_password'], $ENCRYPTION_KEY, $ENCRYPTION_METHOD);
        unset($row['encrypted_password']); // Don't send encrypted payload to frontend
    }
    echo json_encode(['status'=>'success', 'data'=>$rows]);
    exit();
}

if ($action === 'save_password') {
    $website = trim($_POST['website']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $enc = encryptPassword($password, $ENCRYPTION_KEY, $ENCRYPTION_METHOD);

    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE vault_passwords SET website=?, username=?, encrypted_password=? WHERE id=? AND user_id=?");
        $stmt->execute([$website, $username, $enc, $id, $me]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vault_passwords (user_id, website, username, encrypted_password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$me, $website, $username, $enc]);
    }
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'delete_password') {
    $id = intval($_POST['id']);
    $pdo->prepare("DELETE FROM vault_passwords WHERE id=? AND user_id=?")->execute([$id, $me]);
    echo json_encode(['status'=>'success']);
    exit();
}

// ----- PERSONAL TASKS -----
if ($action === 'list_tasks') {
    $stmt = $pdo->prepare("SELECT * FROM vault_tasks WHERE user_id = ? ORDER BY status DESC, due_date ASC");
    $stmt->execute([$me]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status'=>'success', 'data'=>$rows]);
    exit();
}

if ($action === 'save_task') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $due_date = empty($_POST['due_date']) ? null : $_POST['due_date'];
    $rem_mins = intval($_POST['reminder_minutes']);

    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE vault_tasks SET title=?, description=?, due_date=?, reminder_minutes=?, reminder_sent=0 WHERE id=? AND user_id=?");
        $stmt->execute([$title, $desc, $due_date, $rem_mins, $id, $me]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vault_tasks (user_id, title, description, due_date, reminder_minutes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$me, $title, $desc, $due_date, $rem_mins]);
    }
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'delete_task') {
    $id = intval($_POST['id']);
    $pdo->prepare("DELETE FROM vault_tasks WHERE id=? AND user_id=?")->execute([$id, $me]);
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'complete_task') {
    $id = intval($_POST['id']);
    $pdo->prepare("UPDATE vault_tasks SET status='Completed' WHERE id=? AND user_id=?")->execute([$id, $me]);
    echo json_encode(['status'=>'success']);
    exit();
}

// ----- REMINDER DAEMON -----
if ($action === 'check_reminders') {
    // Check for tasks where due_date - reminder_minutes is <= NOW() and reminder_sent = 0
    $stmt = $pdo->prepare("SELECT id, title, due_date FROM vault_tasks WHERE user_id = ? AND status = 'Pending' AND reminder_sent = 0 AND due_date IS NOT NULL");
    $stmt->execute([$me]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notified = 0;
    foreach($tasks as $t) {
        $dueTime = strtotime($t['due_date']);
        $now = time();
        // If due in less than reminder_minutes, trigger notification
        // Note: For simplicity, we just trigger if now is past (dueTime - reminderWindow)
        // Wait, the schema holds 'reminder_minutes'. Let's fetch it too.
    }
    
    // Better Query:
    $stmt = $pdo->prepare("SELECT id, title, due_date, reminder_minutes FROM vault_tasks WHERE user_id = ? AND status = 'Pending' AND reminder_sent = 0 AND due_date IS NOT NULL");
    $stmt->execute([$me]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $dueTime = strtotime($t['due_date']);
        $remTime = $dueTime - ($t['reminder_minutes'] * 60);
        if (time() >= $remTime) {
            // Trigger System Notification
            createNotification($pdo, $me, 'Vault Reminder', 'Your personal task "' . $t['title'] . '" is due soon!', 'vault.php');
            // Mark as sent
            $pdo->prepare("UPDATE vault_tasks SET reminder_sent=1 WHERE id=?")->execute([$t['id']]);
            $notified++;
        }
    }
    
    echo json_encode(['status'=>'success', 'reminders_sent' =>$notified]);
    exit();
}

