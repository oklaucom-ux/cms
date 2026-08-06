<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subtaskId = !empty($_POST['subtask_id']) ? intval($_POST['subtask_id']) : 0;
    $isCompleted = isset($_POST['is_completed']) && ($_POST['is_completed'] == 1 || $_POST['is_completed'] === 'true') ? 1 : 0;

    if (!$subtaskId) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid subtask ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE task_subtasks SET is_completed = ? WHERE id = ?");
        $stmt->execute([$isCompleted, $subtaskId]);

        // Get parent task details to return updated counts
        $parentStmt = $pdo->prepare("SELECT task_id FROM task_subtasks WHERE id = ?");
        $parentStmt->execute([$subtaskId]);
        $taskId = $parentStmt->fetchColumn();

        $countStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM task_subtasks WHERE task_id = ?");
        $countStmt->execute([$taskId]);
        $stats = $countStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'task_id' => $taskId,
            'completed' => (int)($stats['completed'] ?? 0),
            'total' => (int)($stats['total'] ?? 0)
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}
