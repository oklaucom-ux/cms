<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['login_id'];
$todayDate = date('Y-m-d');

try {
    // 1. Fetch completed tasks
    $completedTasks = [];
    try {
        $completedStmt = $pdo->prepare("SELECT name, task_id, priority FROM tasks WHERE assigned_to = ? AND (status = 'Completed' OR status = 'Done')");
        $completedStmt->execute([$userId]);
        $completedTasks = $completedStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

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

    // Check AI settings across settings and app_settings
    $GLOBAL_SETTINGS = [];
    try {
        foreach($pdo->query("SELECT setting_key, setting_value FROM settings") as $r) {
            $GLOBAL_SETTINGS[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Exception $e) {}
    try {
        foreach($pdo->query("SELECT setting_key, setting_value FROM app_settings") as $r) {
            if (empty($GLOBAL_SETTINGS[$r['setting_key']])) {
                $GLOBAL_SETTINGS[$r['setting_key']] = $r['setting_value'];
            }
        }
    } catch (Exception $e) {}

    $apiKey = $GLOBAL_SETTINGS['openai_api_key'] ?? '';
    $useLocal = ($GLOBAL_SETTINGS['use_local_ai'] ?? 'false') === 'true';
    $localUrl = trim($GLOBAL_SETTINGS['local_ai_url'] ?? '');
    if (empty($localUrl)) {
        $localUrl = 'http://192.168.71.2:8081';
    }

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

    // Route request to active local AI server or OpenAI API
    $isUsingOpenAi = !empty($apiKey) && !$useLocal;
    $apiUrl = $isUsingOpenAi ? 'https://api.openai.com/v1/chat/completions' : (rtrim($localUrl, '/') . '/v1/chat/completions');
    $authHeader = $isUsingOpenAi ? ("Authorization: Bearer " . $apiKey) : "Authorization: Bearer local";

    $prompt = "As an AI Work Assistant, format a professional End-of-Day (EOD) daily work report for an employee based on these activities:\n";
    $prompt .= "Completed Tasks: " . json_encode($completedTasks) . "\n";
    $prompt .= "Work In Progress: " . json_encode($wipTasks) . "\n";
    $prompt .= "Time Logged: " . number_format($totalTimeHours, 2) . " hours.\n";
    $prompt .= "Provide valid JSON with 3 string keys: 'tasks_completed', 'work_in_progress', 'plan_for_tomorrow'.";

    $aiPayload = json_encode([
        'model' => $isUsingOpenAi ? 'gpt-4o-mini' : 'Qwen/Qwen2.5-0.5B-Instruct-GGUF',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.5
    ]);

    $res = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", $authHeader]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aiPayload);
        
        $res = curl_exec($ch);
        curl_close($ch);
    }

    if (!$res) {
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" . $authHeader . "\r\n",
                'content' => $aiPayload,
                'timeout' => 30
            ]
        ];
        $context = stream_context_create($opts);
        $res = @file_get_contents($apiUrl, false, $context);
    }

    if ($res) {
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
