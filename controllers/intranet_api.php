<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';
header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$me = $_SESSION['login_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Migrations
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS intranet_posts (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        post_type VARCHAR(255) DEFAULT 'General',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("ALTER TABLE intranet_posts ADD COLUMN post_type VARCHAR(255) DEFAULT 'General'");
} catch(Exception $e){}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS intranet_likes (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        post_id INTEGER NOT NULL,
        user_id VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(Exception $e){}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS intranet_comments (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        post_id INTEGER NOT NULL,
        user_id VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(Exception $e){}

if ($action === 'list') {
    $limit = intval($_GET['limit'] ?? 50);
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as author_name, u.role as author_role,
               (SELECT COUNT(*) FROM intranet_likes WHERE post_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM intranet_likes WHERE post_id = p.id AND user_id = ?) as liked_by_me,
               (SELECT COUNT(*) FROM intranet_comments WHERE post_id = p.id) as comments_count
        FROM intranet_posts p
        JOIN users u ON p.user_id = u.login_id
        ORDER BY p.id DESC LIMIT ?
    ");
    $stmt->execute([$me, $limit]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If it's a specific feed request or we need comments, we can fetch them separately.
    // For simplicity, we'll fetch comments for each post (not scalable for thousands, but fine for SQLite intranet)
    foreach ($posts as &$p) {
        $cStmt = $pdo->prepare("SELECT c.*, u.name as author_name FROM intranet_comments c JOIN users u ON c.user_id = u.login_id WHERE c.post_id = ? ORDER BY c.id ASC");
        $cStmt->execute([$p['id']]);
        $p['comments'] = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['status'=>'success', 'data'=>$posts]);
    exit();
}

if ($action === 'post') {
    $content = trim($_POST['content']);
    $type = $_POST['post_type'] ?? 'General';
    if(empty($content)) { echo json_encode(['status'=>'error', 'message'=>'Content cannot be empty']); exit(); }
    
    // Only Admin/HR should post 'Announcement'
    if ($type === 'Announcement' && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
        $type = 'General';
    }
    
    $stmt = $pdo->prepare("INSERT INTO intranet_posts (user_id, content, post_type) VALUES (?, ?, ?)");
    $stmt->execute([$me, $content, $type]);
    $postId = $pdo->lastInsertId();
    
    if ($type === 'Announcement') {
        notifyAll($pdo, '📣 New Company Announcement', 'An official announcement was posted on the Company Hub.', 'intranet.php', $me);
    }
    
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'like') {
    $post_id = intval($_POST['post_id']);
    // Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM intranet_likes WHERE post_id=? AND user_id=?");
    $stmt->execute([$post_id, $me]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM intranet_likes WHERE post_id=? AND user_id=?")->execute([$post_id, $me]);
    } else {
        $pdo->prepare("INSERT INTO intranet_likes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $me]);
    }
    echo json_encode(['status'=>'success']);
    exit();
}

if ($action === 'comment') {
    $post_id = intval($_POST['post_id']);
    $content = trim($_POST['content']);
    if(!empty($content)) {
        $pdo->prepare("INSERT INTO intranet_comments (post_id, user_id, content) VALUES (?, ?, ?)")->execute([$post_id, $me, $content]);
        
        // Notify Post Author
        $author = $pdo->query("SELECT user_id FROM intranet_posts WHERE id={$post_id}")->fetchColumn();
        if ($author && $author !== $me) {
            createNotification($pdo, $author, 'New Comment', 'Someone commented on your post.', 'intranet.php');
        }
    }
    echo json_encode(['status'=>'success']);
    exit();
}

