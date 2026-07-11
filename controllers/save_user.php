<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';

// Migrate branch_id if missing
// Migrate api_key if missing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    
    // Granular Check
    if ($id) {
        requirePermission($pdo, 'edit_users');
    } else {
        requirePermission($pdo, 'create_users');
    }
    $login_id   = trim($_POST['login_id'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $role       = trim($_POST['role'] ?? 'Employee');
    $branch_id  = !empty($_POST['branch_id'])  ? trim($_POST['branch_id'])  : 'Global HQ';
    $department = !empty($_POST['department']) ? trim($_POST['department']) : null;
    $manager_id = !empty($_POST['manager_id']) ? trim($_POST['manager_id']) : null;

    // Validate required fields
    if (empty($login_id) || empty($name) || empty($email)) {
        $_SESSION['flash_error'] = "Login ID, Name, and Email are required.";
        header("Location: ../users.php");
        exit();
    }
    
    // Super Admin Protection
    if ($role === 'Super Admin' && $_SESSION['role'] !== 'Super Admin') {
        $_SESSION['flash_error'] = "⚠️ Only a Super Admin can assign the Super Admin role.";
        header("Location: ../users.php");
        exit();
    }

    if ($id) {
        $stmtCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtCheck->execute([$id]);
        $targetRole = $stmtCheck->fetchColumn();
        if ($targetRole === 'Super Admin' && $_SESSION['role'] !== 'Super Admin') {
            $_SESSION['flash_error'] = "⚠️ You do not have permission to modify a Super Admin account.";
            header("Location: ../users.php");
            exit();
        }
        if ($targetRole === 'Admin' && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
            $_SESSION['flash_error'] = "⚠️ You do not have permission to modify an Admin account.";
            header("Location: ../users.php");
            exit();
        }
    }
    
    if ($id) { // Update
        if (!empty($_POST['generate_api_key']) && $_POST['generate_api_key'] === 'yes') {
            $new_api_key = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE users SET api_key=? WHERE id=?")->execute([$new_api_key, $id]);
        } elseif (isset($_POST['generate_api_key']) && $_POST['generate_api_key'] === 'revoke') {
            $pdo->prepare("UPDATE users SET api_key=NULL WHERE id=?")->execute([$id]);
        }

        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET login_id=?, password=?, name=?, email=?, role=?, branch_id=?, department=?, manager_id=? WHERE id=?");
            $stmt->execute([$login_id, $pass, $name, $email, $role, $branch_id, $department, $manager_id, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET login_id=?, name=?, email=?, role=?, branch_id=?, department=?, manager_id=? WHERE id=?");
            $stmt->execute([$login_id, $name, $email, $role, $branch_id, $department, $manager_id, $id]);
        }
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Update User']);
    } else { // Create
        $api_key = (!empty($_POST['generate_api_key']) && $_POST['generate_api_key'] === 'yes') ? bin2hex(random_bytes(16)) : null;
        $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (login_id, password, name, email, role, branch_id, department, manager_id, api_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$login_id, $pass, $name, $email, $role, $branch_id, $department, $manager_id, $api_key]);
        $new_user_id = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Create User']);

        // Fire webhook
        fireWebhook($pdo, 'user_created', [
            'id' => $new_user_id,
            'login_id' => $login_id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'branch_id' => $branch_id,
            'department' => $department
        ]);
    }
    header("Location: ../users.php");
    exit();
}
?>

