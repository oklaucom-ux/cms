<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'access_calendar');
if (!isset($_SESSION['login_id'])) {
    echo json_encode([]);
    exit;
}

$events = [];

// Fetch Tasks
$tasks = $pdo->query("SELECT task_id, name, due_date, status FROM tasks")->fetchAll(PDO::FETCH_ASSOC);
foreach($tasks as $t) {
    if (!$t['due_date']) continue;
    $color = ($t['status'] == 'Completed') ? '#10b981' : '#ef4444';
    $events[] = [
        'id' => 'TASK_'.$t['task_id'],
        'title' => 'Task: ' . $t['name'],
        'start' =>$t['due_date'],
        'color' =>$color
    ];
}

// Fetch Activities
$activities = $pdo->query("SELECT activity_id, name, due_date FROM activities")->fetchAll(PDO::FETCH_ASSOC);
foreach($activities as $a) {
    if (!$a['due_date']) continue;
    $events[] = [
        'id' => 'ACT_'.$a['activity_id'],
        'title' => 'Activity: ' . $a['name'],
        'start' =>$a['due_date'],
        'color' => '#3b82f6'
    ];
}

// Fetch Leaves
$leaves = $pdo->query("SELECT id, user_id, leave_type, start_date, end_date, status FROM leaves WHERE status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);
foreach($leaves as $l) {
    $end = date('Y-m-d', strtotime($l['end_date'] . ' +1 day'));
    $events[] = [
        'id' => 'LEAVE_'.$l['id'],
        'title' => 'Leave: '.$l['user_id'] . ' ('.$l['leave_type'].')',
        'start' =>$l['start_date'],
        'end' =>$end,
        'color' => '#f59e0b'
    ];
}

// Fetch Meetings
$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin'])) ? 1 : 0;
$me = $_SESSION['login_id'];
$meetings = $pdo->query("SELECT * FROM meetings WHERE status != 'Canceled'")->fetchAll(PDO::FETCH_ASSOC);

foreach($meetings as $m) {
    $parts = json_decode($m['participants_list'], true) ?? [];
    if ($isAdmin || $m['host_id'] === $me || in_array('ALL', $parts) || in_array($me, $parts)) {
        $events[] = [
            'id' => 'MTG_'.$m['id'],
            'title' => 'Meeting: '.$m['title'],
            'start' =>$m['start_time'],
            'end' =>$m['end_time'],
            'color' => '#8b5cf6' // Purple for meetings
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($events);
?>
