<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'create') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Workspace name required']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO workspaces (name, description, owner_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $_SESSION['login_id']]);
        
        $workspace_id = $pdo->lastInsertId();

        // Add creator as member
        $memberStmt = $pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, 'Owner')");
        $memberStmt->execute([$workspace_id, $_SESSION['login_id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'workspace_id' => $workspace_id]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($action === 'switch') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }

    $workspace_id = (int)($_POST['workspace_id'] ?? 0);

    if ($workspace_id === 0) {
        unset($_SESSION['active_workspace_id']);
        unset($_SESSION['active_workspace_name']);
        echo json_encode(['success' => true]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM workspaces WHERE id = ?");
        $stmt->execute([$workspace_id]);
        $ws = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ws) {
            $_SESSION['active_workspace_id'] = $ws['id'];
            $_SESSION['active_workspace_name'] = $ws['name'];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Workspace not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
