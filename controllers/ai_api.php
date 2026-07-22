<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['login_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
        exit;
    }

    $query = strtolower(trim($_POST['query'] ?? ''));
    $myId = $_SESSION['login_id'];

    if (empty($query)) {
        echo json_encode(['status' => 'error', 'message' => 'Empty query']);
        exit;
    }

    // Fetch Global Settings to check for API Key
    $GLOBAL_SETTINGS = [];
    foreach($pdo->query("SELECT setting_key, setting_value FROM settings") as $r) {
        $GLOBAL_SETTINGS[$r['setting_key']] = $r['setting_value'];
    }
    $apiKey = $GLOBAL_SETTINGS['openai_api_key'] ?? '';
    $useLocal = ($GLOBAL_SETTINGS['use_local_ai'] ?? 'false') === 'true';

    // IF Local AI is enabled or OpenAI API Key is provided, use the Smart AI
    if ($useLocal || !empty($apiKey)) {
        
        // Gather context for the AI
        $context = "You are an internal corporate AI assistant for the Cyno Management System. Your job is to provide helpful, concise, and professional answers.\n";
        $context .= "The company is: " . ($GLOBAL_SETTINGS['company_name'] ?? 'Cyno') . "\n";
        
        // Get user details
        $stmt = $pdo->prepare("SELECT name, role, department, cyno_points FROM users WHERE login_id = ?");
        $stmt->execute([$myId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $context .= "The user talking to you is {$user['name']}, role: {$user['role']}, department: {$user['department']}. They have {$user['cyno_points']} points.\n";
        }

        // Get their tasks
        $stmt = $pdo->prepare("SELECT title, status FROM tasks WHERE assigned_to = ? AND status != 'Done' LIMIT 5");
        $stmt->execute([$myId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($tasks) {
            $context .= "Their pending tasks are: ";
            foreach($tasks as $t) $context .= "{$t['title']} ({$t['status']}), ";
            $context .= "\n";
        }

        // OpenAI API Call
        $data = [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => $context],
                ["role" => "user", "content" => $_POST['query']] // Original cased query
            ],
            "temperature" => 0.7,
            "max_tokens" => 300
        ];

        // Routing logic
        $apiUrl = "https://api.openai.com/v1/chat/completions";
        $authHeader = "Authorization: Bearer " . $apiKey;
        
        if ($useLocal) {
            $baseUrl = rtrim($GLOBAL_SETTINGS['local_ai_url'] ?? 'http://127.0.0.1:8080', '/');
            $apiUrl = $baseUrl . "/v1/chat/completions";
            $authHeader = "Authorization: Bearer local"; // local engine doesn't strict check, but good practice
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                $authHeader
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $response = false;
            $httpCode = 0;
        }

        if ($httpCode == 200 && $response) {
            $resObj = json_decode($response, true);
            $reply = $resObj['choices'][0]['message']['content'] ?? "I'm sorry, I couldn't formulate a response.";
            echo json_encode(['status' => 'success', 'reply' => $reply]);
            exit;
        } else {
            // Fallback to simulated AI if API fails
            $reply = "*(OpenAI API Error - Falling back to offline mode)*\n\n";
        }
    } else {
        $reply = "";
    }

    // ---------------------------------------------------------
    // OFFLINE / SIMULATED AI FALLBACK
    // ---------------------------------------------------------
    if (empty($reply)) {
        $reply = "I'm sorry, I couldn't understand that query. Try asking about your tasks, projects, points, finding colleagues, or company policies.";
    }

    function extractNameAfter($str, $keyword) {
        if (($pos = strpos($str, $keyword)) !== false) {
            $name = trim(substr($str, $pos + strlen($keyword)));
            $name = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
            return explode(' ', $name)[0];
        }
        return '';
    }

    try {
        if (preg_match('/\b(hello|hi|hey|greetings)\b/i', $query)) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE login_id = ?");
            $stmt->execute([$myId]);
            $userName = $stmt->fetchColumn() ?: "there";
            $reply .= "Hello, **{$userName}**! How can I assist you today? You can ask me about your tasks, tickets, projects, or search for colleagues.";
        } 
        elseif (preg_match('/\b(my tasks|pending tasks|what do i have to do)\b/i', $query)) {
            $stmt = $pdo->prepare("SELECT title, status, due_date FROM tasks WHERE assigned_to = ? AND status != 'Done' ORDER BY due_date ASC LIMIT 5");
            $stmt->execute([$myId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($tasks) {
                $reply .= "Here are your pending tasks:\n";
                foreach ($tasks as $t) {
                    $due = $t['due_date'] ? " (Due: {$t['due_date']})" : "";
                    $reply .= "- **" . htmlspecialchars($t['title']) . "** [{$t['status']}]{$due}\n";
                }
            } else {
                $reply .= "Awesome! You have no pending tasks assigned to you right now.";
            }
        }
        elseif (preg_match('/\b(who is|find|contact|email for)\b/i', $query)) {
            $nameToSearch = extractNameAfter($query, 'who is') ?: extractNameAfter($query, 'find') ?: extractNameAfter($query, 'email for') ?: extractNameAfter($query, 'contact');
            if (strlen($nameToSearch) > 2) {
                $stmt = $pdo->prepare("SELECT name, email, department, role FROM users WHERE name LIKE ? LIMIT 3");
                $stmt->execute(["%{$nameToSearch}%"]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($users) {
                    $reply .= "Here is what I found for '**{$nameToSearch}**':\n";
                    foreach ($users as $u) {
                        $reply .= "- **{$u['name']}** ({$u['role']}) - Dept: {$u['department']} | ✉️ {$u['email']}\n";
                    }
                } else {
                    $reply .= "I couldn't find anyone matching '{$nameToSearch}' in the directory.";
                }
            } else {
                $reply .= "Please provide a specific name to search for (e.g., 'who is John').";
            }
        }
        elseif (preg_match('/\b(my points|cyno points|how many points|rewards)\b/i', $query)) {
            $stmt = $pdo->prepare("SELECT cyno_points FROM users WHERE login_id = ?");
            $stmt->execute([$myId]);
            $points = $stmt->fetchColumn() ?: 0;
            $reply .= "You currently have **{$points} Cyno Points**! 🏅\nYou can earn more by completing tasks early or scoring 100% on training quizzes.";
        }
        elseif (preg_match('/\b(active projects|my projects|what projects)\b/i', $query)) {
            $stmt = $pdo->prepare("SELECT name, status, progress FROM projects WHERE status IN ('Active', 'Planning') LIMIT 5");
            $stmt->execute();
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($projects) {
                $reply .= "Here are some of the currently active projects:\n";
                foreach ($projects as $p) {
                    $prog = $p['progress'] ? $p['progress'].'%' : '0%';
                    $reply .= "- **" . htmlspecialchars($p['name']) . "** [{$p['status']}] - {$prog} completed\n";
                }
            } else {
                $reply .= "There are no active projects right now.";
            }
        }
        elseif (preg_match('/\b(my tickets|open tickets|helpdesk)\b/i', $query)) {
            $stmt = $pdo->prepare("SELECT subject, status, priority FROM support_tickets WHERE created_by = ? AND status != 'Closed' LIMIT 5");
            $stmt->execute([$myId]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($tickets) {
                $reply .= "Here are your open support tickets:\n";
                foreach ($tickets as $t) {
                    $reply .= "- **" . htmlspecialchars($t['subject']) . "** (Priority: {$t['priority']}, Status: {$t['status']})\n";
                }
            } else {
                $reply .= "You have no open IT support tickets. Everything looks good!";
            }
        }
        elseif (preg_match('/\b(policy|policies|rules|handbook)\b/i', $query)) {
            $stmt = $pdo->query("SELECT title FROM policies ORDER BY created_at DESC LIMIT 3");
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($policies) {
                $reply .= "Here are the latest company policies:\n";
                foreach ($policies as $p) {
                    $reply .= "- " . htmlspecialchars($p['title']) . "\n";
                }
                $reply .= "\nPlease check the **Policies** section in the sidebar for full documents.";
            } else {
                $reply .= "There are no documented policies available right now.";
            }
        }
        elseif (preg_match('/\b(headcount|how many employees|total staff)\b/i', $query)) {
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $reply .= "We currently have **{$count} registered employees** across the matrix! 🌐";
        }
        elseif (preg_match('/\b(training|course|learn)\b/i', $query)) {
            $stmt = $pdo->query("SELECT title FROM training_courses WHERE status = 'Published' LIMIT 3");
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($courses) {
                $reply .= "We have these training courses available:\n";
                foreach ($courses as $c) {
                    $reply .= "- " . htmlspecialchars($c['title']) . "\n";
                }
                $reply .= "\nHead over to the **Training Hub** to enroll!";
            } else {
                $reply .= "There are no published training courses right now.";
            }
        }
        elseif (preg_match('/\b(leave|sick|pto|vacation)\b/i', $query)) {
            $reply .= "To request time off, please navigate to the **Leaves** portal from your sidebar. If you are sick, ensure you notify your direct manager via Chat or Omni-Desk.";
        }

    } catch (Exception $e) {
        $reply .= "\n*(I ran into a small database error while fetching that data, but I'm still online!)*";
    }

    usleep(600000); // 0.6 seconds

    echo json_encode([
        'status' => 'success',
        'reply' => trim($reply)
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'PHP Error: ' . $e->getMessage()]);
}
