<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode([]);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $query . '%';
$results = [];

// 1. Search Tasks
$tStmt = $pdo->prepare("SELECT id, name, status, priority FROM tasks WHERE status != 'Deleted' AND (name LIKE ? OR description LIKE ?) LIMIT 5");
$tStmt->execute([$like, $like]);
foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $results[] = [
        'type' => 'Task',
        'title' => $t['name'],
        'subtitle' => 'Status: ' . $t['status'] . ' | Priority: ' . $t['priority'],
        'url' => 'tasks.php',
        'icon' => 'fa-tasks',
        'badge' => '#3b82f6'
    ];
}

// 2. Search Projects
$pStmt = $pdo->prepare("SELECT id, name, status FROM projects WHERE name LIKE ? OR description LIKE ? LIMIT 5");
$pStmt->execute([$like, $like]);
foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $results[] = [
        'type' => 'Project',
        'title' => $p['name'],
        'subtitle' => 'Status: ' . $p['status'],
        'url' => 'projects.php',
        'icon' => 'fa-project-diagram',
        'badge' => '#8b5cf6'
    ];
}

// 3. Search Notes
$nStmt = $pdo->prepare("SELECT id, title FROM notes WHERE title LIKE ? OR content LIKE ? LIMIT 5");
$nStmt->execute([$like, $like]);
foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
    $results[] = [
        'type' => 'Note',
        'title' => $n['title'],
        'subtitle' => 'Personal / Team Note',
        'url' => 'notes.php',
        'icon' => 'fa-sticky-note',
        'badge' => '#f59e0b'
    ];
}

// 4. Search Drive Documents
$dStmt = $pdo->prepare("SELECT id, title, category FROM documents WHERE title LIKE ? OR category LIKE ? LIMIT 5");
$dStmt->execute([$like, $like]);
foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
    $results[] = [
        'type' => 'Document',
        'title' => $d['title'],
        'subtitle' => 'Category: ' . $d['category'],
        'url' => 'documents.php',
        'icon' => 'fa-file-alt',
        'badge' => '#10b981'
    ];
}

// 5. Search Virtual Meetings
$mStmt = $pdo->prepare("SELECT id, title, scheduled_time FROM meetings WHERE title LIKE ? OR description LIKE ? LIMIT 5");
$mStmt->execute([$like, $like]);
foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $results[] = [
        'type' => 'Meeting',
        'title' => $m['title'],
        'subtitle' => 'Scheduled Virtual Meeting',
        'url' => 'meetings.php',
        'icon' => 'fa-video',
        'badge' => '#ef4444'
    ];
}

echo json_encode($results);
exit;
