<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
requirePermission($pdo, 'access_office');
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$me = $_SESSION['login_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Auto-Migrate schema
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS office_folders (
        id {$pkDef},
        name VARCHAR(255) NOT NULL,
        parent_id INT DEFAULT 0,
        created_by VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS office_files (
        id {$pkDef},
        folder_id INT DEFAULT 0,
        file_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        json_data LONGTEXT,
        visibility VARCHAR(50) DEFAULT 'Private',
        shared_with TEXT,
        locked_by VARCHAR(255) DEFAULT NULL,
        approval_status VARCHAR(50) DEFAULT 'Draft',
        approved_by VARCHAR(255) DEFAULT NULL,
        created_by VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    if ($isMysql) {
        try { $pdo->exec("ALTER TABLE office_files MODIFY COLUMN title TEXT NULL"); } catch (Exception $e) {}
    }
} catch (Exception $e) {}

if ($action === 'list') {
    $folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : 0;
    
    // Fetch Folders
    $stmtF = $pdo->prepare("SELECT * FROM office_folders WHERE (parent_id = ? OR (parent_id IS NULL AND ? = 0)) AND (created_by = ? OR id IN (SELECT folder_id FROM office_files WHERE visibility='Public' OR created_by=?))");
    $stmtF->execute([$folder_id, $folder_id, $me, $me]);
    $folders = $stmtF->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch Files
    $stmt = $pdo->prepare("SELECT id, file_type, file_name, created_by, visibility, updated_at, shared_with, locked_by, approval_status, approved_by FROM office_files WHERE folder_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$folder_id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $files = [];
    foreach($all as $f) {
        $sharedList = json_decode($f['shared_with'], true) ?? [];
        if (!is_array($sharedList)) $sharedList = [];
        
        // Show if owner, public, shared with me, admin, OR if I am the manager of the creator and it's pending approval
        $isMyTeam = false;
        if ($f['approval_status'] === 'Pending') {
            $mgrStmt = $pdo->prepare("SELECT manager_id FROM users WHERE login_id=?");
            $mgrStmt->execute([$f['created_by']]);
            if ($mgrStmt->fetchColumn() === $me) $isMyTeam = true;
        }

        if ($f['created_by'] === $me || $f['visibility'] === 'Public' || ($f['visibility'] === 'Shared' && in_array($me, $sharedList)) || in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $isMyTeam) {
            $files[] = $f;
        }
    }
    echo json_encode(['status'=>'success', 'data'=>['folders'=>$folders, 'files'=>$files]]);
    exit();
}

if ($action === 'create_folder') {
    $name = trim($_POST['name'] ?? 'New Folder');
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $pdo->prepare("INSERT INTO office_folders (name, parent_id, created_by) VALUES (?, ?, ?)")->execute([$name, $parent_id, $me]);
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'rename_folder') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $stmt = $pdo->prepare("SELECT created_by FROM office_folders WHERE id = ?");
    $stmt->execute([$id]);
    $owner = $stmt->fetchColumn();
    if ($owner !== $me && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status'=>'error', 'message'=>'Permission denied']);
        exit();
    }
    $pdo->prepare("UPDATE office_folders SET name = ? WHERE id = ?")->execute([$name, $id]);
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'delete_folder') {
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("SELECT created_by FROM office_folders WHERE id = ?");
    $stmt->execute([$id]);
    $owner = $stmt->fetchColumn();
    if ($owner !== $me && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status'=>'error', 'message'=>'Permission denied']);
        exit();
    }
    // Orphan files to root directory
    $pdo->prepare("UPDATE office_files SET folder_id = 0 WHERE folder_id = ?")->execute([$id]);
    // Orphan sub-folders to root directory
    $pdo->prepare("UPDATE office_folders SET parent_id = 0 WHERE parent_id = ?")->execute([$id]);
    // Delete the target folder
    $pdo->prepare("DELETE FROM office_folders WHERE id = ?")->execute([$id]);
    
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'unlock') {
    $id = intval($_POST['id']);
    $pdo->prepare("UPDATE office_files SET locked_by = NULL WHERE id = ? AND locked_by = ?")->execute([$id, $me]);
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'load') {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM office_files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        echo json_encode(['status'=>'error', 'message'=>'Not found']);
        exit();
    }

    $sharedList = json_decode($file['shared_with'], true) ?? [];
    if (!is_array($sharedList)) $sharedList = [];

    if (!($file['created_by'] === $me || $file['visibility'] === 'Public' || ($file['visibility'] === 'Shared' && in_array($me, $sharedList)) || in_array($_SESSION['role'], ['Admin', 'Super Admin']))) {
        echo json_encode(['status'=>'error', 'message'=>'Access Denied']);
        exit();
    }

    // Lock the file if not locked
    if (!$file['locked_by']) {
        $pdo->prepare("UPDATE office_files SET locked_by = ? WHERE id = ?")->execute([$me, $id]);
        $file['locked_by'] = $me;
    }
    $file['is_readonly'] = ($file['locked_by'] !== $me);

    echo json_encode(['status'=>'success', 'data'=>$file]);
    exit();
}

if ($action === 'save') {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        echo json_encode(['status'=>'error', 'message'=>'CSRF Token Mismatch. Please refresh the page and try again.']);
        exit();
    }

    $id = $_POST['id'] ?? null;
    $file_type = $_POST['file_type'] ?? 'Word';
    $file_name = $_POST['file_name'] ?? 'Untitled';
    $json_data = $_POST['json_data'] ?? '';
    $visibility = $_POST['visibility'] ?? 'Private';
    $folder_id = intval($_POST['folder_id'] ?? 0);

    $shared_raw = $_POST['shared_with'] ?? '[]';
    $shared_decoded = json_decode($shared_raw, true);
    if (is_array($shared_decoded)) {
        $shared_with = json_encode($shared_decoded);
    } else {
        $shared_with = json_encode(array_values(array_filter(array_map('trim', explode(',', $shared_raw)))));
    }

    if ($id) {
        // Enforce ownership for editing
        $stmt = $pdo->prepare("SELECT created_by, locked_by, approval_status FROM office_files WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['status'=>'error', 'message'=>'File not found']);
            exit();
        }
        if ($row['created_by'] !== $me && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
            echo json_encode(['status'=>'error', 'message'=>'Only owner can overwrite']);
            exit();
        }
        if ($row['locked_by'] && $row['locked_by'] !== $me) {
            echo json_encode(['status'=>'error', 'message'=>'File is currently locked by another user.']);
            exit();
        }

        if ($row['approval_status'] === 'Pending' && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
            echo json_encode(['status'=>'error', 'message'=>'File is pending approval and locked for editing.']);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE office_files SET title=?, file_name=?, json_data=?, visibility=?, shared_with=?, folder_id=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->execute([$file_name, $file_name, $json_data, $visibility, $shared_with, $folder_id, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO office_files (title, file_type, file_name, json_data, created_by, visibility, shared_with, folder_id, locked_by, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')");
        $stmt->execute([$file_name, $file_type, $file_name, $json_data, $me, $visibility, $shared_with, $folder_id, $me]);
        $id = $pdo->lastInsertId();
    }
    
    echo json_encode(['status'=>'success', 'id'=>$id]);
    exit();
}

if ($action === 'delete') {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($submittedToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        echo json_encode(['status'=>'error', 'message'=>'CSRF Token Mismatch. Please refresh the page and try again.']);
        exit();
    }
    $id = $_POST['id'];
    $stmt = $pdo->prepare("SELECT created_by FROM office_files WHERE id = ?");
    $stmt->execute([$id]);
    $owner = $stmt->fetchColumn();
    
    if ($owner !== $me && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status'=>'error', 'message'=>'Only owner can delete']);
        exit();
    }

    $pdo->prepare("DELETE FROM office_files WHERE id = ?")->execute([$id]);
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'submit_approval') {
    $id = intval($_POST['id']);
    // Find manager
    require_once __DIR__ . '/../includes/notifications.php';
    $mgrStmt = $pdo->prepare("SELECT manager_id FROM users WHERE login_id=?");
    $mgrStmt->execute([$me]);
    $managerId = $mgrStmt->fetchColumn();

    if (!$managerId) {
        echo json_encode(['status'=>'error', 'message'=>'No manager assigned in HR. Cannot submit for approval.']);
        exit();
    }

    $pdo->prepare("UPDATE office_files SET approval_status='Pending', locked_by=NULL WHERE id=?")->execute([$id]);
    
    // Notify manager
    $docName = $pdo->query("SELECT file_name FROM office_files WHERE id={$id}")->fetchColumn();
    createNotification($pdo, $managerId, 'Document Approval Required', "{$me} submitted '{$docName}' for your review.", 'office.php');
    
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'process_approval') {
    $id = intval($_POST['id']);
    $status = $_POST['status']; // 'Approved' or 'Rejected'
    
    $stmt = $pdo->prepare("SELECT created_by, file_name FROM office_files WHERE id=?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("UPDATE office_files SET approval_status=?, approved_by=? WHERE id=?")->execute([$status, $me, $id]);
    
    require_once '../includes/notifications.php';
    createNotification($pdo, $doc['created_by'], "Document {$status}", "Your document '{$doc['file_name']}' was {$status} by {$me}.", 'office.php');

    echo json_encode(['status'=>'success']);
    exit();
}
