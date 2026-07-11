<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Auto-migrate schema
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fetch_applicants') {
    $stmt = $pdo->query("SELECT * FROM applicants ORDER BY created_at DESC");
    echo json_encode(['status' => 'success', 'data' =>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'update_status') {
    if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Lacking HR permissions']);
        exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if (in_array($status, ['Applied', 'Screening', 'Interview', 'Offered', 'Rejected'])) {
        $pdo->prepare("UPDATE applicants SET status = ? WHERE id = ?")->execute([$status, $id]);
        
        // If Offered, we could auto-create a user record or push to onboarding queue (mocked here as an audit log)
        if ($status === 'Offered') {
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Applicant Offered']);
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    }
    exit;
}

if ($action === 'create_applicant') {
    if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Lacking HR permissions']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_applied = trim($_POST['role_applied'] ?? '');
    
    if (empty($name) || empty($email) || empty($role_applied)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }
    
    // Handle Resume Upload
    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $upload_dir = '../uploads/resumes/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_name = 'resume_' . time() . '_' . rand(1000, 9999) . '.pdf';
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_dir . $new_name)) {
                $resume_path = 'uploads/resumes/' . $new_name;
            }
        }
    }
    
    $pdo->prepare("INSERT INTO applicants (name, email, phone, role_applied, resume_path) VALUES (?, ?, ?, ?, ?)")
        ->execute([$name, $email, $phone, $role_applied, $resume_path]);
        
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'convert_applicant') {
    if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Lacking HR permissions']);
        exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id = ? AND status = 'Offered'");
    $stmt->execute([$id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        echo json_encode(['status' => 'error', 'message' => 'Applicant not found or not in Offered stage']);
        exit;
    }
    
    // Convert to employee
    $login_id = strtolower(str_replace(' ', '.', $app['name']));
    $base_login_id = $login_id;
    $counter = 1;
    // ensure unique login_id
    while ($pdo->query("SELECT COUNT(*) FROM users WHERE login_id = '$login_id'")->fetchColumn() > 0) {
        $login_id = $base_login_id . $counter;
        $counter++;
    }
    
    $hashed = password_hash('changeme123', PASSWORD_DEFAULT);
    
    $pdo->prepare("INSERT INTO users (login_id, password, name, email, role, department, status) VALUES (?, ?, ?, ?, 'Employee', ?, 'Pending_Docs')")
        ->execute([$login_id, $hashed, $app['name'], $app['email'], $app['role_applied']]);
        
    // Remove from ATS or mark as Hired
    $pdo->prepare("UPDATE applicants SET status = 'Hired' WHERE id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Applicant Converted']);
    
    echo json_encode(['status' => 'success', 'new_login_id' =>$login_id]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;
?>

