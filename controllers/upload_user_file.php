<?php
session_start();
require_once '../includes/db.php';

$user_id = $_POST['user_id'] ?? '';
$isSelf = ($user_id === $_SESSION['login_id']);

if (!$isSelf && !hasPermission($pdo, 'manage_users')) {
    die("Unauthorized.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['hr_file'])) {
    $user_id = $_POST['user_id'];
    $title = trim($_POST['title']);
    
    $upload_dir = '../uploads/hr_files/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $htaccess_path = $upload_dir . '.htaccess';
    if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, "php_flag engine off\n<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|py|cgi|exe|sh|bat)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\nOptions -ExecCGI");
    }
    
    $file_info = pathinfo($_FILES['hr_file']['name']);
    $ext = strtolower($file_info['extension']);
    
    // allow list
    if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'])) {
        $_SESSION['flash_error'] = "Invalid file type.";
        header("Location: ../users.php");
        exit;
    }
    
    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $user_id) . '_' . uniqid() . '.' . $ext;
    $target_path = $upload_dir . $safe_filename;
    
    if (move_uploaded_file($_FILES['hr_file']['tmp_name'], $target_path)) {
        $db_path = 'uploads/hr_files/' . $safe_filename;
        $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, title, file_path) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $title, $db_path]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Upload HR File', '']);
        $_SESSION['flash_success'] = "File uploaded successfully.";
        
        if ($isSelf) {
            require_once '../includes/notifications.php';
            // Alert all admins
            $admins = $pdo->query("SELECT login_id, role FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $u) {
                // If they are Admin role, they bypass hasPermission natively. OR check strictly:
                $canManage = false;
                if ($u['role'] === 'Admin') {
                    $canManage = true;
                } else {
                    $rStmt = $pdo->prepare("SELECT permissions FROM roles WHERE role_name=?");
                    $rStmt->execute([$u['role']]);
                    $rd = $rStmt->fetchColumn();
                    if ($rd && in_array('manage_users', json_decode($rd, true) ?: [])) {
                        $canManage = true;
                    }
                }
                if ($canManage) {
                    createNotification($pdo, $u['login_id'], 'New HR File Upload', "{$_SESSION['name']} uploaded '{$title}'", 'users.php');
                }
            }
        }
    } else {
        $_SESSION['flash_error'] = "File mechanism failure.";
    }
}
header("Location: ../" . ($isSelf ? "onboarding_portal.php" : "users.php"));
exit;
