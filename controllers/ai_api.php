<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Basic CSRF check if we use it globally
$query = strtolower(trim($_POST['query'] ?? ''));

if (empty($query)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty query']);
    exit;
}

// Simulated AI Logic
$reply = "I'm sorry, I'm just a simulated AI assistant for now and couldn't find an answer to that. Please check with HR or your manager!";

// Mock Intents
if (strpos($query, 'hello') !== false || strpos($query, 'hi') !== false) {
    $reply = "Hello! How can I help you today with HR, operations, or onboarding?";
} 
elseif (strpos($query, 'policy') !== false || strpos($query, 'policies') !== false) {
    // Try to fetch latest policies
    try {
        $stmt = $pdo->query("SELECT title FROM policies ORDER BY created_at DESC LIMIT 3");
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($policies) {
            $reply = "Here are some of our recent policies:\n";
            foreach ($policies as $p) {
                $reply .= "- " . htmlspecialchars($p['title']) . "\n";
            }
            $reply .= "\nYou can view more details in the Policies portal.";
        } else {
            $reply = "We don't have any policies listed yet.";
        }
    } catch (Exception $e) {
        $reply = "I found the policies portal, but I can't read the latest ones right now.";
    }
}
elseif (strpos($query, 'leave') !== false || strpos($query, 'sick') !== false || strpos($query, 'pto') !== false) {
    $reply = "To request leave or PTO, you can usually check the **Timesheets** or **HR** portal. If you are sick, please notify your manager immediately.";
}
elseif (strpos($query, 'training') !== false || strpos($query, 'course') !== false) {
    try {
        $stmt = $pdo->query("SELECT title FROM training_courses WHERE status = 'Published' LIMIT 3");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($courses) {
            $reply = "We have several published training courses available:\n";
            foreach ($courses as $c) {
                $reply .= "- " . htmlspecialchars($c['title']) . "\n";
            }
            $reply .= "\nHead over to the Training Hub to enroll!";
        } else {
            $reply = "There are no published training courses right now.";
        }
    } catch (Exception $e) {
        $reply = "Check the Training Hub for available courses.";
    }
}
elseif (strpos($query, 'onboarding') !== false) {
    $reply = "If you are a new hire, make sure to check your **Onboarding Portal** to complete your checklist and view assigned training modules.";
}
elseif (strpos($query, 'points') !== false || strpos($query, 'reward') !== false) {
    $reply = "You can earn **Cyno Points** by completing tasks early or scoring 100% on training quizzes. Redeem them in the Rewards portal!";
}

// Simulate network delay for effect
usleep(800000); // 0.8 seconds

echo json_encode([
    'status' => 'success',
    'reply' => $reply
]);
