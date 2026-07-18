<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// We need a helper to send chat messages
function sendSystemChat($pdo, $recipient_id, $message) {
    // Check if a system user exists, else send as the current user or a hardcoded system bot ID if you have one.
    // For now, we'll send it as the Receptionist (current user).
    $sender_id = $_SESSION['login_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$sender_id, $recipient_id, $message]);
    } catch (Exception $e) {}
    
    // Also notify if notifications table exists
    try {
        $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)");
        $nStmt->execute([$recipient_id, 'Reception Alert', $message, 'reception.php']);
    } catch(Exception $e) {}
}

if ($action === 'register_visitor') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    
    $visitor_name = $_POST['visitor_name'] ?? '';
    $company = $_POST['company'] ?? '';
    $host_id = $_POST['host_id'] ?? 0;
    $expected_arrival = $_POST['expected_arrival'] ?? null;
    $purpose = $_POST['purpose'] ?? '';
    $vehicle_reg = $_POST['vehicle_reg'] ?? '';
    $is_nda_signed = isset($_POST['is_nda_signed']) && $_POST['is_nda_signed'] === '1' ? 1 : 0;
    
    if (!$visitor_name || !$host_id) { echo json_encode(['status'=>'error', 'message'=>'Missing required fields']); exit; }
    
    $stmt = $pdo->prepare("INSERT INTO reception_visitors (visitor_name, company, host_id, expected_arrival, purpose, vehicle_reg, is_nda_signed) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$visitor_name, $company, $host_id, $expected_arrival, $purpose, $vehicle_reg, $is_nda_signed]);
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'register_walkin_visitor') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    
    $visitor_name = $_POST['visitor_name'] ?? '';
    $company = $_POST['company'] ?? '';
    $host_id = $_POST['host_id'] ?? 0;
    $purpose = $_POST['purpose'] ?? '';
    $vehicle_reg = $_POST['vehicle_reg'] ?? '';
    $is_nda_signed = isset($_POST['is_nda_signed']) && $_POST['is_nda_signed'] === '1' ? 1 : 0;
    
    if (!$visitor_name || !$host_id) { echo json_encode(['status'=>'error', 'message'=>'Missing required fields']); exit; }
    
    // Create visitor as checked_in immediately
    $stmt = $pdo->prepare("INSERT INTO reception_visitors (visitor_name, company, host_id, expected_arrival, status, checked_in_at, purpose, vehicle_reg, is_nda_signed) VALUES (?, ?, ?, CURRENT_TIMESTAMP, 'checked_in', CURRENT_TIMESTAMP, ?, ?, ?)");
    $stmt->execute([$visitor_name, $company, $host_id, $purpose, $vehicle_reg, $is_nda_signed]);
    $id = $pdo->lastInsertId();
    
    // Fetch visitor details for notification
    $vStmt = $pdo->prepare("SELECT v.*, u.id as host_id FROM reception_visitors v JOIN users u ON v.host_id = u.id WHERE v.id=?");
    $vStmt->execute([$id]);
    $visitor = $vStmt->fetch();
    
    if ($visitor) {
        $msg = "🛎️ Your walk-in visitor **{$visitor['visitor_name']}** " . ($visitor['company'] ? "from {$visitor['company']} " : "") . "has just arrived at the reception desk.";
        sendSystemChat($pdo, $visitor['host_id'], $msg);
    }
    
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'checkin_visitor') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE reception_visitors SET status='checked_in', checked_in_at=CURRENT_TIMESTAMP WHERE id=?");
    $stmt->execute([$id]);
    
    // Fetch visitor details for notification
    $vStmt = $pdo->prepare("SELECT v.*, u.id as host_id FROM reception_visitors v JOIN users u ON v.host_id = u.id WHERE v.id=?");
    $vStmt->execute([$id]);
    $visitor = $vStmt->fetch();
    
    if ($visitor) {
        $msg = "🛎️ Your visitor **{$visitor['visitor_name']}** " . ($visitor['company'] ? "from {$visitor['company']} " : "") . "has arrived at the reception desk.";
        sendSystemChat($pdo, $visitor['host_id'], $msg);
    }
    
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'checkout_visitor') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE reception_visitors SET status='checked_out', checked_out_at=CURRENT_TIMESTAMP WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'log_package') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    
    $recipient_id = $_POST['recipient_id'] ?? 0;
    $courier = $_POST['courier'] ?? '';
    $tracking = $_POST['tracking_number'] ?? '';
    $sender_name = $_POST['sender_name'] ?? '';
    $sender_company = $_POST['sender_company'] ?? '';
    $package_type = $_POST['package_type'] ?? 'Box';
    
    $stmt = $pdo->prepare("INSERT INTO reception_packages (recipient_id, courier, tracking_number, sender_name, sender_company, package_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$recipient_id, $courier, $tracking, $sender_name, $sender_company, $package_type]);
    
    $msg = "📦 You have a new package at the reception desk! (Courier: $courier" . ($tracking ? ", Tracking: $tracking" : "") . ")";
    sendSystemChat($pdo, $recipient_id, $msg);
    
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'pickup_package') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE reception_packages SET status='picked_up', picked_up_at=CURRENT_TIMESTAMP WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'checkout_asset') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    
    $asset_name = $_POST['asset_name'] ?? '';
    $asset_type = $_POST['asset_type'] ?? 'key';
    $assigned_to = $_POST['assigned_to'] ?? 0;
    $expected_return = $_POST['expected_return'] ?? null;
    $condition_out = $_POST['condition_out'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO reception_assets (asset_name, asset_type, assigned_to, expected_return, condition_out) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$asset_name, $asset_type, $assigned_to, $expected_return, $condition_out]);
    
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'return_asset') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    $id = $_POST['id'] ?? 0;
    $condition_in = $_POST['condition_in'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE reception_assets SET status='returned', returned_at=CURRENT_TIMESTAMP, condition_in=?, notes=? WHERE id=?");
    $stmt->execute([$condition_in, $notes, $id]);
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'take_message') {
    if (!hasPermission($pdo, 'manage_reception')) { echo json_encode(['status'=>'error', 'message'=>'Permission denied']); exit; }
    
    $recipient_id = $_POST['recipient_id'] ?? 0;
    $caller_name = $_POST['caller_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    
    $fullMsg = "📞 **Phone Message from $caller_name**\n";
    if ($phone) $fullMsg .= "Phone: $phone\n";
    $fullMsg .= "Message: $message";
    
    sendSystemChat($pdo, $recipient_id, $fullMsg);
    
    echo json_encode(['status' => 'success']);
    exit;
}

// Fetch endpoints for Dashboard
if ($action === 'get_visitors') {
    $filter = $_GET['filter'] ?? 'today';
    if ($filter === 'today') {
        global $use_mysql;
        $todayStr = (isset($use_mysql) && $use_mysql) ? "CURDATE()" : "date('now')";
        $stmt = $pdo->query("SELECT v.*, u.name as host_name FROM reception_visitors v JOIN users u ON v.host_id = u.id WHERE DATE(v.created_at) = $todayStr OR DATE(v.expected_arrival) = $todayStr OR DATE(v.checked_in_at) = $todayStr OR DATE(v.checked_out_at) = $todayStr ORDER BY v.created_at DESC LIMIT 100");
    } else {
        $stmt = $pdo->query("SELECT v.*, u.name as host_name FROM reception_visitors v JOIN users u ON v.host_id = u.id ORDER BY v.created_at DESC LIMIT 200");
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'get_packages') {
    $stmt = $pdo->query("SELECT p.*, u.name as recipient_name FROM reception_packages p JOIN users u ON p.recipient_id = u.id ORDER BY p.received_at DESC LIMIT 50");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'get_assets') {
    $stmt = $pdo->query("SELECT a.*, u.name as assignee_name FROM reception_assets a JOIN users u ON a.assigned_to = u.id ORDER BY a.checked_out_at DESC LIMIT 50");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'get_directory') {
    $stmt = $pdo->query("SELECT id, name, email, role, phone, designation, department, status FROM users WHERE status != 'Terminated' ORDER BY name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Attempt to grab live presence from chat_sessions or similar if available, else just use DB status
    // The CMS typically has `last_active` or similar. Let's just return users for now.
    echo json_encode($users);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
