<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
$me = $_SESSION['login_id'];

// Ensure chat_files column exists

// Create dynamic channels table
try {
// Seed default channels if empty
    $count = $pdo->query("SELECT COUNT(*) FROM chat_channels")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO chat_channels (name, description) VALUES ('#General', 'Company-wide Broadcast'), ('#Sales', 'Lead & Client Discussion'), ('#Engineering', 'Tech & Development')");
    }
} catch(Exception $e){}

// GET: fetch messages
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') == 'fetch') {
    $partner = $_GET['partner'] ?? '';
    if (empty($partner)) { echo json_encode([]); exit; }
    
    if (strpos($partner, '#') === 0) {
        $stmt = $pdo->prepare("SELECT m.*, u.name AS sender_name FROM messages m LEFT JOIN users u ON m.sender_id = u.login_id WHERE m.receiver_id = ? ORDER BY m.timestamp ASC");
        $stmt->execute([$partner]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY timestamp ASC");
        $stmt->execute([$me, $partner, $partner, $me]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE messages SET status='read' WHERE sender_id=? AND receiver_id=? AND status='unread'")->execute([$partner, $me]);
    }
    echo json_encode($messages);
    exit;
}

// GET: fetch unread counts
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') == 'unread_counts') {
    $stmt = $pdo->prepare("SELECT sender_id, COUNT(*) as unread_count FROM messages WHERE receiver_id=? AND status='unread' GROUP BY sender_id");
    $stmt->execute([$me]);
    $dm_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['dms' => $dm_counts]);
    exit;
}

// POST: send text message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'send') {
    $receiver = $_POST['receiver'] ?? '';
    $message  = trim($_POST['message'] ?? '');
    if (!empty($receiver) && !empty($message)) {
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)")->execute([$me, $receiver, $message]);
        // Notify recipient only for direct messages
        if (strpos($receiver, '#') !== 0) {
            createNotification($pdo, $receiver, '💬 New message from ' . $_SESSION['name'], substr($message, 0, 80), 'chat.php');
        }
        echo json_encode(['status'=>'success']);
    } else { echo json_encode(['status'=>'error','message'=>'Empty']); }
    exit;
}

// POST: upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'upload') {
    $receiver = $_POST['receiver'] ?? '';
    if (empty($receiver) || empty($_FILES['chat_file'])) { echo json_encode(['status'=>'error','message'=>'Missing']); exit; }

    $file     = $_FILES['chat_file'];
    $maxSize  = 10 * 1024 * 1024; // 10MB
    $allowed  = ['jpg','jpeg','png','gif','pdf','txt','doc','docx','xlsx','csv','zip'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['size'] > $maxSize) { echo json_encode(['status'=>'error','message'=>'File too large (max 10MB)']); exit; }
    if (!in_array($ext, $allowed)) { echo json_encode(['status'=>'error','message'=>'File type not allowed']); exit; }

    $uploadDir = __DIR__ . '/../assets/chat_uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $safeName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $dest     = $uploadDir . $safeName;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $fileUrl  = 'assets/chat_uploads/' . $safeName;
        $isImage  = in_array($ext, ['jpg','jpeg','png','gif']);
        $msgText  = $isImage ? '[image]' : '[file: ' . htmlspecialchars($file['name']) . ']';

        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, file_path, file_name, file_type) VALUES (?,?,?,?,?,?)")
            ->execute([$me, $receiver, $msgText, $fileUrl, $file['name'], $isImage ? 'image' : 'file']);

        if (strpos($receiver, '#') !== 0) {
            createNotification($pdo, $receiver, '📎 ' . $_SESSION['name'] . ' sent a file', htmlspecialchars($file['name']), 'chat.php');
        }
        echo json_encode(['status'=>'success','file_url'=>$fileUrl,'file_name'=>$file['name'],'is_image'=>$isImage]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Upload failed']);
    }
    exit;
}

// POST: create channel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'create_channel') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    
    if (empty($name)) { echo json_encode(['status'=>'error','message'=>'Name required']); exit; }
    if (strpos($name, '#') !== 0) $name = '#' . $name;
    
    try {
        $pdo->prepare("INSERT INTO chat_channels (name, description) VALUES (?, ?)")->execute([$name, $desc]);
        echo json_encode(['status'=>'success', 'name' =>$name, 'description' =>$desc]);
    } catch(PDOException $e) {
        echo json_encode(['status'=>'error', 'message'=>'Channel may already exist']);
    }
    exit;
}

// POST: edit channel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'edit_channel') {
    if (!hasPermission($pdo, 'manage_settings') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $newName = trim($_POST['name'] ?? '');
    $newDesc = trim($_POST['description'] ?? '');
    
    if ($id <= 0 || empty($newName)) { echo json_encode(['status'=>'error','message'=>'Invalid input']); exit; }
    if (strpos($newName, '#') !== 0) $newName = '#' . $newName;

    try {
        $pdo->beginTransaction();
        
        // Get old name
        $oldName = $pdo->query("SELECT name FROM chat_channels WHERE id=$id")->fetchColumn();
        
        if ($oldName) {
            // Update channel
            $stmt = $pdo->prepare("UPDATE chat_channels SET name=?, description=? WHERE id=?");
            $stmt->execute([$newName, $newDesc, $id]);
            
            // If name changed, update all existing messages pointing to the old name
            if ($oldName !== $newName) {
                $stmtMsg = $pdo->prepare("UPDATE messages SET receiver_id=? WHERE receiver_id=?");
                $stmtMsg->execute([$newName, $oldName]);
            }
        }
        $pdo->commit();
        echo json_encode(['status'=>'success']);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status'=>'error', 'message'=>'Update failed. Name might already exist.']);
    }
    exit;
}

// POST: delete channel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'delete_channel') {
    if (!hasPermission($pdo, 'manage_settings') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid ID']); exit; }
    
    try {
        $pdo->beginTransaction();
        
        $oldName = $pdo->query("SELECT name FROM chat_channels WHERE id=$id")->fetchColumn();
        if ($oldName) {
            $pdo->prepare("DELETE FROM chat_channels WHERE id=?")->execute([$id]);
            // Purge related messages
            $pdo->prepare("DELETE FROM messages WHERE receiver_id=?")->execute([$oldName]);
        }
        
        $pdo->commit();
        echo json_encode(['status'=>'success']);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status'=>'error', 'message'=>'Deletion failed.']);
    }
    exit;
}


