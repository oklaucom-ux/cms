<?php
$content = file_get_contents('controllers/chat_api.php');
$content .= <<<'EOF'

// POST: assign employee to client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'assign_employee') {
    if (!in_array($_SESSION['role'], ['Admin', 'Super Admin', 'System Admin'])) {
        echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
    }
    $client_id = $_POST['client_id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    
    if (empty($client_id) || empty($employee_id)) {
        echo json_encode(['status'=>'error','message'=>'Missing client or employee ID']); exit;
    }
    
    try {
        $pdo->prepare("INSERT OR IGNORE INTO client_assignments (client_id, employee_id, assigned_by) VALUES (?, ?, ?)")
            ->execute([$client_id, $employee_id, $me]);
        echo json_encode(['status'=>'success']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>'Database error']);
    }
    exit;
}
EOF;
file_put_contents('controllers/chat_api.php', $content);
echo "done";
