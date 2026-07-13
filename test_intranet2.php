<?php
require_once 'includes/db.php';

$me = 'admin';
$_GET['limit'] = 50;
$limit = intval($_GET['limit'] ?? 50);

try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(u.name, sa.name, 'Unknown User') as author_name, 
               COALESCE(u.role, sa.role, 'Ghost') as author_role,
               (SELECT COUNT(*) FROM intranet_likes WHERE post_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM intranet_likes WHERE post_id = p.id AND user_id = ?) as liked_by_me,
               (SELECT COUNT(*) FROM intranet_comments WHERE post_id = p.id) as comments_count
        FROM intranet_posts p
        LEFT JOIN users u ON p.user_id = u.login_id
        LEFT JOIN super_admins sa ON p.user_id = sa.login_id
        ORDER BY p.id DESC LIMIT " . $limit . "
    ");
    $stmt->execute([$me]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($posts as &$p) {
        $cStmt = $pdo->prepare("SELECT c.*, COALESCE(u.name, sa.name, 'Unknown User') as author_name 
                                FROM intranet_comments c 
                                LEFT JOIN users u ON c.user_id = u.login_id 
                                LEFT JOIN super_admins sa ON c.user_id = sa.login_id
                                WHERE c.post_id = ? ORDER BY c.id ASC");
        $cStmt->execute([$p['id']]);
        $p['comments'] = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['status'=>'success', 'data'=>$posts], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
