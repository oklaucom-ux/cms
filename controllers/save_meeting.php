<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_time = $_POST['start_time']; 
    $end_time = $_POST['end_time'];
    $host_id = $_SESSION['login_id'];
    $participants = $_POST['participants'] ?? [];
    
    $participants_json = json_encode($participants);
    
    $stmt = $pdo->prepare("INSERT INTO meetings (title, description, start_time, end_time, host_id, participants_list) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $start_time, $end_time, $host_id, $participants_json]);
    
    // Notify Participants
    $st = date('M d, Y h:i A', strtotime($start_time));
    $et = date('h:i A', strtotime($end_time));
    
    $all = [];
    if (in_array('ALL', $participants)) {
        $all = $pdo->query("SELECT login_id FROM users WHERE login_id != '{$host_id}'")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $all = $participants;
    }
    
    foreach($all as $u) {
        $email = getUserEmail($pdo, $u);
        if ($email) {
            sendSystemEmail($email, "Meeting Invitation: {$title}", "You have been invited to a meeting hosted by {$host_id}.<br><strong>{$title}</strong><br>Time: {$st} - {$et}<br>Details: {$description}");
        }
    }
    
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$host_id}', 'Schedule Meeting', 'Scheduled meeting {$title}')");
    header("Location: ../calendar.php");
}
?>
