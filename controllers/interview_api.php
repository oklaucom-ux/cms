<?php
// controllers/interview_api.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Auto-migrate interview tables cleanly
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS interview_templates (
        id {$pkDef},
        title VARCHAR(255) NOT NULL,
        expected_keywords TEXT,
        created_by VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS interview_questions (
        id {$pkDef},
        template_id INT NOT NULL,
        question_text TEXT NOT NULL,
        time_limit_seconds INT DEFAULT 120
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS interview_sessions (
        id {$pkDef},
        template_id INT NOT NULL,
        candidate_name VARCHAR(255) NOT NULL,
        candidate_email VARCHAR(255),
        access_code VARCHAR(50) UNIQUE NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        score INT DEFAULT NULL,
        feedback TEXT,
        created_by VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$me = $_SESSION['login_id'] ?? 'Candidate';

// HR endpoints (Require Auth)
if ($action === 'create_template' && isset($_SESSION['role'])) {
    $title = trim($_POST['title']);
    $keywords = trim($_POST['expected_keywords']);
    $questions = json_decode($_POST['questions'] ?? '[]', true);
    
    $stmt = $pdo->prepare("INSERT INTO interview_templates (title, expected_keywords, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$title, $keywords, $me]);
    $tid = $pdo->lastInsertId();
    
    if (is_array($questions)) {
        foreach ($questions as $q) {
            if(!empty($q['text'])) {
                $pdo->prepare("INSERT INTO interview_questions (template_id, question_text, time_limit_seconds) VALUES (?, ?, ?)")
                    ->execute([$tid, $q['text'], $q['time']]);
            }
        }
    }
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'save_openai_key' && in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    $key = trim($_POST['api_key']);
    $check = $pdo->prepare("SELECT COUNT(*) FROM app_settings WHERE setting_key='openai_api_key'");
    $check->execute();
    if ($check->fetchColumn() > 0) {
        $pdo->prepare("UPDATE app_settings SET setting_value=? WHERE setting_key='openai_api_key'")->execute([$key]);
    } else {
        $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('openai_api_key', ?)")->execute([$key]);
    }
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'ai_generate_questions' && isset($_SESSION['role'])) {
    $kra = $_POST['kra'] ?? '';
    $skill = $_POST['skill_level'] ?? '';
    $cv = $_POST['cv_text'] ?? '';
    
    $apiKey = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='openai_api_key'")->fetchColumn();
    if (empty($apiKey)) { echo json_encode(['status'=>'error', 'message'=>'OpenAI API Key not configured.']); exit(); }
    
    $prompt = "You are an expert HR Recruiter. Generate 3 interview questions for a candidate. 
    Profile KRA: $kra. Skill Level: $skill. 
    Candidate CV Excerpt: $cv. 
    Return ONLY a raw JSON array of objects with 'text' (the question) and 'time' (suggested time in seconds, between 60 and 180). Example: [{\"text\":\"Explain OOP?\",\"time\":120}]";

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role'=>'user', 'content'=>$prompt]],
        'temperature' => 0.7
    ]));
    
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        $content = preg_replace('/```json|```/', '', $content);
        echo json_encode(['status'=>'success', 'questions'=>json_decode(trim($content))]);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Failed to connect to OpenAI']);
    }
    exit();
}

if ($action === 'list_templates' && isset($_SESSION['role'])) {
    $templates = $pdo->query("SELECT * FROM interview_templates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status'=>'success', 'data'=>$templates]);
    exit();
}

if ($action === 'list_sessions' && isset($_SESSION['role'])) {
    $sessions = $pdo->query("SELECT s.*, t.title as template_title FROM interview_sessions s LEFT JOIN interview_templates t ON s.template_id = t.id ORDER BY s.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status'=>'success', 'data'=>$sessions]);
    exit();
}

if ($action === 'generate_session' && isset($_SESSION['role'])) {
    $tid = intval($_POST['template_id']);
    $cName = trim($_POST['candidate_name']);
    $cEmail = trim($_POST['candidate_email'] ?? '');
    $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    
    $pdo->prepare("INSERT INTO interview_sessions (template_id, candidate_name, candidate_email, access_code, created_by) VALUES (?, ?, ?, ?, ?)")
        ->execute([$tid, $cName, $cEmail, $code, $me]);
    
    if (!empty($cEmail)) {
        require_once '../includes/mailer.php';
        $subject = "Invitation to Virtual Candidate Interview";
        $message = "Hello $cName,<br><br>You have been invited to complete a virtual interview.<br><strong>Access Code:</strong> <code>$code</code><br><br>Best regards,<br>HR Recruitment Team";
        sendSystemEmail($cEmail, $subject, $message);
    }
    
    echo json_encode(['status'=>'success', 'access_code'=>$code]);
    exit();
}

echo json_encode(['status'=>'error', 'message'=>'Invalid Action']);
exit();
