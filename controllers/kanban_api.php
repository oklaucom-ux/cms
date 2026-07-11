<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$me = $_SESSION['login_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Auto-Migrate schema just in case
if ($action === 'get_board') {
    $project_id = intval($_GET['project_id']);
    
    // Fetch all tasks for this project
    $stmt = $pdo->prepare("SELECT t.id, t.name as title, t.status, t.assigned_to, u.name as assignee_name 
                           FROM tasks t 
                           LEFT JOIN users u ON t.assigned_to = u.login_id 
                           WHERE t.project_id = ? AND t.status != 'Deleted'
                           ORDER BY t.id DESC");
    $stmt->execute([$project_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format tasks to ensure statuses align with Kanban columns
    foreach ($tasks as &$task) {
        if ($task['status'] === 'Pending') $task['status'] = 'Backlog';
        if (!in_array($task['status'], ['Backlog', 'In Progress', 'QA', 'Done'])) {
            $task['status'] = 'Backlog';
        }
    }
    
    echo json_encode(['status' => 'success', 'tasks' =>$tasks]);
    exit();
}

if ($action === 'update_status') {
    $task_id = intval($_POST['task_id']);
    $status = trim($_POST['status']);
    
    if (in_array($status, ['Backlog', 'In Progress', 'QA', 'Done'])) {
        $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$status, $task_id]);
        
        if (in_array(strtolower($status), ['done', 'completed'])) {
            fireWebhook($pdo, 'task_completed', [
                'task_id' =>$task_id,
                'status' =>$status,
                'user_id' =>$_SESSION['login_id']
            ]);
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    }
    exit();
}

if ($action === 'generate_forecast' && in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    $project_id = intval($_POST['project_id']);
    
    // Gather project metadata
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        echo json_encode(['status'=>'error', 'message'=>'Project not found']);
        exit();
    }
    
    $tStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE project_id = ? GROUP BY status");
    $tStmt->execute([$project_id]);
    $taskStats = json_encode($tStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Check OpenAI Key
    $apiKey = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='openai_api_key'")->fetchColumn();
    if (empty($apiKey)) {
        echo json_encode(['status'=>'error', 'message'=>'OpenAI API Key not configured in Virtual HR Settings.']);
        exit();
    }
    
    $prompt = "You are an Agile Scrum Master AI. Analyze this project's current status and task distribution. 
    Project Details: Name: {$project['name']}, Status: {$project['status']}, Deadline: {$project['deadline']}, Budget: {$project['budget']}.
    Task Statistics: $taskStats.
    Provide a brief JSON response containing: 
    1. 'risk_level' (Low, Medium, High).
    2. 'forecast' (1-2 sentences on whether they will miss the deadline).
    3. 'recommendation' (1 sentence advice).
    Return ONLY valid JSON.";

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role'=>'user', 'content'=>$prompt]],
        'temperature' => 0.5
    ]));
    
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        $aiData = preg_replace('/```json|```/', '', $data['choices'][0]['message']['content']);
        $pdo->prepare("UPDATE projects SET ai_forecast = ? WHERE id = ?")->execute([trim($aiData), $project_id]);
        echo json_encode(['status'=>'success', 'data'=>json_decode(trim($aiData), true)]);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Failed to connect to OpenAI API']);
    }
    exit();
}

