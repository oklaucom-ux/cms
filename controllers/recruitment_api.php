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
    
    if (in_array($status, ['Applied', 'Screening', 'Interview', 'Offered', 'Hired', 'Rejected'])) {
        $pdo->prepare("UPDATE applicants SET status = ? WHERE id = ?")->execute([$status, $id]);
        
        // Auto-provision into Onboarding Pipeline when Offered or Hired
        if (in_array($status, ['Offered', 'Hired'])) {
            $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$id]);
            $cand = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cand && !empty($cand['email'])) {
                $parts = explode(' ', trim($cand['name']), 2);
                $firstName = $parts[0] ?? '';
                $lastName = $parts[1] ?? '';
                $chk = $pdo->prepare("SELECT id FROM onboarding_applications WHERE email = ?");
                $chk->execute([$cand['email']]);
                if (!$chk->fetchColumn()) {
                    $ins = $pdo->prepare("INSERT INTO onboarding_applications (first_name, last_name, email, position_applied, resume_link, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                    $ins->execute([$firstName, $lastName, $cand['email'], $cand['role_applied'], $cand['resume_path'] ?? '']);
                }
            }
            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Applicant ' . $status, 'Auto-provisioned into onboarding']);
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
    
    try {
        $pdo->prepare("INSERT INTO applicants (name, email, phone, role_applied, resume_path, status) VALUES (?, ?, ?, ?, ?, 'Applied')")
            ->execute([$name, $email, $phone, $role_applied, $resume_path]);
            
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Insert Error: ' . $e->getMessage()]);
    }
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
        
    // AUTOMATED WORKFLOW: Assign initial training course
    $course = $pdo->query("SELECT id FROM training_courses LIMIT 1")->fetchColumn();
    if ($course) {
        $pdo->prepare("INSERT INTO training_assignments (user_id, course_id, status, start_date) VALUES (?, ?, 'In Progress', CURRENT_TIMESTAMP)")
            ->execute([$login_id, $course]);
    }

    // AUTOMATED WORKFLOW: Create IT Setup Kanban Task
    $col = $pdo->query("SELECT id FROM ops_columns ORDER BY position ASC LIMIT 1")->fetchColumn() ?: 1;
    $desc = "Please provision a laptop and standard IT bundle for new hire: " . $app['name'] . " (" . $login_id . ").";
    $pdo->prepare("INSERT INTO ops_tasks (column_id, title, description, priority, status) VALUES (?, ?, ?, 'High', 'Open')")
        ->execute([$col, "IT Setup for New Hire: " . $app['name'], $desc]);

    // Remove from ATS or mark as Hired
    $pdo->prepare("UPDATE applicants SET status = 'Hired' WHERE id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Applicant Converted', 'Automated workflows triggered.']);
    
    echo json_encode(['status' => 'success', 'new_login_id' =>$login_id]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;
?>

