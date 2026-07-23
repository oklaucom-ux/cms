<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die("Security Error");
}

$id = $_POST['id'] ?? '';
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$projectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
$color = $_POST['color'] ?? '#ffffff';
$isPinned = !empty($_POST['is_pinned']) ? 1 : 0;
$createdBy = $_SESSION['login_id'];

$workspaceId = $_SESSION['active_workspace_id'] ?? null;

if (empty($content)) {
    die("Content is required");
}

if (empty($id)) {
    $stmt = $pdo->prepare("INSERT INTO notes (title, content, project_id, color, is_pinned, created_by, workspace_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $content, $projectId, $color, $isPinned, $createdBy, $workspaceId]);
    $_SESSION['flash_message'] = "Note created successfully.";
} else {
    // Only author or admin can edit
    $isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
    $chk = $pdo->prepare("SELECT created_by FROM notes WHERE id = ?");
    $chk->execute([$id]);
    $author = $chk->fetchColumn();

    if ($isAdmin || $author === $createdBy) {
        $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, project_id = ?, color = ?, is_pinned = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$title, $content, $projectId, $color, $isPinned, $id]);
        $_SESSION['flash_message'] = "Note updated successfully.";
    } else {
        die("Unauthorized to edit this note");
    }
}

header("Location: ../notes.php");
exit;
