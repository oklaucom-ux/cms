<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['login_id'];
$todayDate = date('Y-m-d');

try {
    // 1. Fetch completed tasks today
    $completedStmt = $pdo->prepare("SELECT name, task_id, priority FROM tasks WHERE assigned_to = ? AND status = 'Completed' AND DATE(updated_at) = ?");
    $completedStmt->execute([$userId, $todayDate]);
    $completedTasks = $completedStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch in-progress tasks
    $wipStmt = $pdo->prepare("SELECT name, task_id, priority FROM tasks WHERE assigned_to = ? AND status = 'In Progress'");
    $wipStmt->execute([$userId]);
    $wipTasks = $wipStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch time logs today
    $timeLogs = [];
    try {
        $lStmt = $pdo->prepare("SELECT ttl.*, t.name as task_name FROM task_time_logs ttl LEFT JOIN tasks t ON ttl.task_id = t.id WHERE ttl.user_id = ? AND DATE(ttl.clock_in) = ?");
        $lStmt->execute([$userId, $todayDate]);
        $timeLogs = $lStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    // Check AI settings
    $GLOBAL_SETTINGS = [];
    try {
        foreach($pdo->query("SELECT setting_key, setting_value FROM settings") as $r) {
            $GLOBAL_SETTINGS[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {}

    $apiKey = $GLOBAL_SETTINGS['openai_api_key'] ?? '';
    $useLocal = ($GLOBAL_SETTINGS['use_local_ai'] ?? 'false') === 'true';

    $completedSummary = [];
    foreach ($completedTasks as $i => $t) {
        $completedSummary[] = ($i + 1) . ". Completed " . $t['name'] . " (" . $t['task_id'] . ")";
    }

    $wipSummary = [];
    foreach ($wipTasks as $i => $t) {
        $wipSummary[] = ($i + 1) . ". Ongoing: " . $t['name'] . " (" . $t['task_id'] . ")";
    }

    $totalTimeHours = 0;
    foreach ($timeLogs as $log) {
        if (!empty($log['clock_in']) && !empty($log['clock_out'])) {
            $diff = strtotime($log['clock_out']) - strtotime($log['clock_in']);
            $totalTimeHours += max(0, $diff / 3600);
        }
    }

    // Default structured response
    $tasksText = !empty($completedSummary) ? implode("\n", $completedSummary) : "1. Completed assigned deliverables and operational maintenance.";
    $wipText = !empty($wipSummary) ? implode("\n", $wipSummary) : "1. Continuing active workspace tasks.";
    $planText = "1. Follow up on active project deliverables.\n2. Review pending code/task reviews.";

    // If AI is configured, invoke OpenAI to generate polished EOD summary
    if (!empty($apiKey) && !$useLocal) {
        $prompt = "As an AI Work Assistant, format a professional End-of-Day (EOD) daily work report for an employee based on these activities:\n";
        $prompt .= "Completed Tasks: " . json_encode($completedTasks) . "\n";
        $prompt .= "Work In Progress: " . json_encode($wipTasks) . "\n";
        $prompt .= "Time Logged: " . number_format($totalTimeHours, 2) . " hours.\n";
        $prompt .= "Provide valid JSON with 3 string keys: 'tasks_completed', 'work_in_progress', 'plan_for_tomorrow'.";

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.5
        ]));
        
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);

        if (isset($data['choices'][0]['message']['content'])) {
            $aiDataStr = preg_replace('/```json|```/', '', $data['choices'][0]['message']['content']);
            $parsed = json_decode(trim($aiDataStr), true);
            if ($parsed && isset($parsed['tasks_completed'])) {
                $tasksText = $parsed['tasks_completed'];
                $wipText = $parsed['work_in_progress'] ?? $wipText;
                $planText = $parsed['plan_for_tomorrow'] ?? $planText;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'tasks_completed' => $tasksText,
        'work_in_progress' => $wipText,
        'plan_for_tomorrow' => $planText,
        'hours_worked' => $totalTimeHours > 0 ? round($totalTimeHours, 2) : 8.0
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error generating summary: ' . $e->getMessage()]);
}
