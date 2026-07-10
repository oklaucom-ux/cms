<?php
// controllers/interview_api.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$me = $_SESSION['login_id'] ?? 'Candidate';

// Migrations
$pdo->exec("CREATE TABLE IF NOT EXISTS interview_templates (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    title TEXT NOT NULL,
    expected_keywords TEXT,
    created_by TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS interview_questions (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    template_id INTEGER NOT NULL,
    question_text TEXT NOT NULL,
    time_limit_seconds INTEGER DEFAULT 120
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS interview_sessions (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    template_id INTEGER NOT NULL,
    candidate_name TEXT NOT NULL,
    candidate_email TEXT,
    access_code TEXT NOT NULL UNIQUE,
    status VARCHAR(255) DEFAULT 'Pending',
    total_score INTEGER DEFAULT 0,
    ai_analysis TEXT,
    id_photo_path TEXT,
    anti_cheat_flags INTEGER DEFAULT 0,
    created_by TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
try { $pdo->exec("ALTER TABLE interview_sessions ADD COLUMN candidate_email TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE interview_sessions ADD COLUMN ai_analysis TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE interview_sessions ADD COLUMN id_photo_path TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE interview_sessions ADD COLUMN anti_cheat_flags INTEGER DEFAULT 0"); } catch(Exception $e){}

$pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS interview_answers (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    session_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    candidate_answer TEXT,
    video_path TEXT,
    time_taken INTEGER DEFAULT 0,
    score INTEGER DEFAULT 0
)");
try { $pdo->exec("ALTER TABLE interview_answers ADD COLUMN video_path TEXT"); } catch(Exception $e){}

// HR endpoints (Require Auth)
if ($action === 'create_template' && isset($_SESSION['role'])) {
    $title = trim($_POST['title']);
    $keywords = trim($_POST['expected_keywords']);
    $questions = json_decode($_POST['questions'], true);
    
    $stmt = $pdo->prepare("INSERT INTO interview_templates (title, expected_keywords, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$title, $keywords, $me]);
    $tid = $pdo->lastInsertId();
    
    foreach ($questions as $q) {
        if(!empty($q['text'])) {
            $pdo->prepare("INSERT INTO interview_questions (template_id, question_text, time_limit_seconds) VALUES (?, ?, ?)")
                ->execute([$tid, $q['text'], $q['time']]);
        }
    }
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'save_openai_key' && in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    $key = trim($_POST['api_key']);
    $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('openai_api_key', ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value")->execute([$key]);
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
        $content = preg_replace('/```json|```/', '', $content); // Strip markdown blocks if any
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

if ($action === 'generate_session' && isset($_SESSION['role'])) {
    $tid = intval($_POST['template_id']);
    $cName = trim($_POST['candidate_name']);
    $cEmail = trim($_POST['candidate_email'] ?? '');
    $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8)); // 8-char code
    
    $pdo->prepare("INSERT INTO interview_sessions (template_id, candidate_name, candidate_email, access_code, created_by) VALUES (?, ?, ?, ?, ?)")
        ->execute([$tid, $cName, $cEmail, $code, $me]);
    
    // Trigger Email (Simulation or Basic Mail)
    if (!empty($cEmail)) {
        $subject = "Invitation to Virtual Interview";
        $message = "Hello $cName,\n\nYou have been invited to complete a virtual interview.\nAccess Code: $code\nLink: http://{$_SERVER['HTTP_HOST']}/cms/interview_portal.php\n\nBest regards,\nHR Team";
        $headers = "From: hr@enterprise.com";
        @mail($cEmail, $subject, $message, $headers);
    }
    
    echo json_encode(['status'=>'success', 'access_code'=>$code]);
    exit();
}

if ($action === 'list_sessions' && isset($_SESSION['role'])) {
    $sessions = $pdo->query("
        SELECT s.*, t.title as template_title 
        FROM interview_sessions s 
        JOIN interview_templates t ON s.template_id = t.id 
        ORDER BY s.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch answers for completed sessions
    foreach ($sessions as &$s) {
        if ($s['status'] === 'Completed') {
            $stmt = $pdo->prepare("
                SELECT a.*, q.question_text 
                FROM interview_answers a 
                JOIN interview_questions q ON a.question_id = q.id 
                WHERE a.session_id = ?
            ");
            $stmt->execute([$s['id']]);
            $s['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    echo json_encode(['status'=>'success', 'data'=>$sessions]);
    exit();
}

// Candidate Endpoints (Public - No session auth needed, uses access_code)
if ($action === 'start_session') {
    $code = strtoupper(trim($_POST['access_code'] ?? ''));
    $stmt = $pdo->prepare("SELECT * FROM interview_sessions WHERE access_code = ?");
    $stmt->execute([$code]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) { echo json_encode(['status'=>'error', 'message'=>'Invalid Access Code']); exit(); }
    if ($session['status'] === 'Completed') { echo json_encode(['status'=>'error', 'message'=>'Interview already completed.']); exit(); }
    
    // Fetch questions
    $stmt = $pdo->prepare("SELECT id, question_text, time_limit_seconds FROM interview_questions WHERE template_id = ? ORDER BY id ASC");
    $stmt->execute([$session['template_id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pdo->prepare("UPDATE interview_sessions SET status='In Progress' WHERE id=?")->execute([$session['id']]);
    
    echo json_encode(['status'=>'success', 'session'=>$session, 'questions'=>$questions]);
    exit();
}

if ($action === 'submit_answer') {
    $session_id = intval($_POST['session_id']);
    $question_id = intval($_POST['question_id']);
    $answer = trim($_POST['answer'] ?? '');
    $time_taken = intval($_POST['time_taken'] ?? 0);
    
    // Automated Scoring logic based on keywords
    $stmt = $pdo->prepare("SELECT expected_keywords FROM interview_templates t JOIN interview_sessions s ON s.template_id = t.id WHERE s.id = ?");
    $stmt->execute([$session_id]);
    $keywords = strtolower($stmt->fetchColumn() ?? '');
    
    $score = 0;
    if (!empty($keywords)) {
        $kw_array = array_map('trim', explode(',', $keywords));
        $ans_lower = strtolower($answer);
        $matches = 0;
        foreach($kw_array as $kw) {
            if (!empty($kw) && strpos($ans_lower, $kw) !== false) $matches++;
        }
        $score = count($kw_array) > 0 ? round(($matches / count($kw_array)) * 100) : 0;
    }
    
    $video_path = null;
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/interviews/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = "session_{$session_id}_q_{$question_id}_" . time() . ".webm";
        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
            $video_path = "uploads/interviews/" . $filename;
        }
    }
    
    $pdo->prepare("INSERT INTO interview_answers (session_id, question_id, candidate_answer, video_path, time_taken, score) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$session_id, $question_id, $answer, $video_path, $time_taken, $score]);
        
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'upload_id_photo') {
    $code = $_POST['access_code'];
    $image = $_POST['image']; // base64
    $image = str_replace('data:image/png;base64,', '', $image);
    $image = str_replace(' ', '+', $image);
    $data = base64_decode($image);
    
    $uploadDir = '../uploads/interviews/id_captures/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $file = $uploadDir . uniqid() . '.png';
    file_put_contents($file, $data);
    $path = str_replace('../', '', $file);
    
    $pdo->prepare("UPDATE interview_sessions SET id_photo_path=? WHERE access_code=?")->execute([$path, $code]);
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'flag_cheat') {
    $session_id = intval($_POST['session_id']);
    $pdo->prepare("UPDATE interview_sessions SET anti_cheat_flags = anti_cheat_flags + 1 WHERE id=?")->execute([$session_id]);
    exit();
}

if ($action === 'complete_session') {
    $session_id = intval($_POST['session_id']);
    
    // Calc total score average
    $stmt = $pdo->prepare("SELECT AVG(score) FROM interview_answers WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $avg = round($stmt->fetchColumn() ?: 0);
    
    // AI Transcript Analysis
    $apiKey = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='openai_api_key'")->fetchColumn();
    $ai_analysis = null;
    
    if (!empty($apiKey)) {
        $answers = $pdo->prepare("SELECT q.question_text, a.candidate_answer FROM interview_answers a JOIN interview_questions q ON a.question_id = q.id WHERE a.session_id = ?");
        $answers->execute([$session_id]);
        $transcript = "";
        foreach($answers->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $transcript .= "Q: {$a['question_text']}\nA: {$a['candidate_answer']}\n\n";
        }
        
        $prompt = "You are an expert HR Analyst. Review this interview transcript. 
        Analyze: 1. Sentiment/Confidence (Give a 1-sentence summary of their confidence level). 2. Communication Skills (Are they articulate?). 3. Overall Feedback (Should we hire them?).
        Return ONLY raw JSON in this format: {\"sentiment\":\"...\",\"communication\":\"...\",\"feedback\":\"...\"}
        Transcript: \n" . $transcript;

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
            $ai_analysis = preg_replace('/```json|```/', '', $data['choices'][0]['message']['content']);
        }
    }
    
    // Automated Phase 2 Scheduling Logic
    $status = 'Completed';
    if ($avg >= 80) {
        $status = 'Phase 2 Scheduled';
        $email = $pdo->query("SELECT candidate_email FROM interview_sessions WHERE id={$session_id}")->fetchColumn();
        if (!empty($email)) {
            $subject = "Congratulations! Phase 2 Interview Scheduled";
            $message = "You have passed the virtual screening! Please book your Phase 2 Interview with a manager using this Calendly link: https://calendly.com/enterprise-hr/phase2";
            @mail($email, $subject, $message, "From: hr@enterprise.com");
        }
    }
    
    $pdo->prepare("UPDATE interview_sessions SET status=?, total_score=?, ai_analysis=? WHERE id=?")->execute([$status, $avg, $ai_analysis, $session_id]);
    
    // Send Notification to HR
    $creator = $pdo->query("SELECT created_by FROM interview_sessions WHERE id={$session_id}")->fetchColumn();
    if($creator) {
        require_once '../includes/notifications.php';
        createNotification($pdo, $creator, 'Interview Completed', 'A candidate finished their interview (Score: '.$avg.'%).', 'hr_interviews.php');
    }
    
    echo json_encode(['status'=>'success']);
    exit();
}

