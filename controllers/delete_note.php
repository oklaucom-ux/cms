<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die("Security Error");
}

$id = $_POST['id'] ?? '';
if (empty($id)) {
    die("Invalid ID");
}

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
$loginId = $_SESSION['login_id'];

$chk = $pdo->prepare("SELECT created_by FROM notes WHERE id = ?");
$chk->execute([$id]);
$author = $chk->fetchColumn();

if ($isAdmin || $author === $loginId) {
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['flash_message'] = "Note deleted successfully.";
} else {
    die("Unauthorized to delete this note");
}

header("Location: ../notes.php");
exit;
