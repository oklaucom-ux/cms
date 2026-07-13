<?php
require_once 'includes/db.php';
$me = 'admin'; // Assume testing with admin
$limit = 50;

echo "Database MySQL: " . ($use_mysql ? "Yes" : "No") . "\n";

try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(u.name, sa.name, 'Unknown User') as author_name, 
               COALESCE(u.role, sa.role, 'Ghost') as author_role
        FROM intranet_posts p
        LEFT JOIN users u ON p.user_id = u.login_id
        LEFT JOIN super_admins sa ON p.user_id = sa.login_id
        ORDER BY p.id DESC LIMIT " . $limit . "
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Posts count: " . count($posts) . "\n";
    print_r($posts);
    
    // Also fetch super admins
    $sa = $pdo->query("SELECT * FROM super_admins")->fetchAll();
    echo "\nSuper admins count: " . count($sa) . "\n";
    print_r($sa);
    
    // And users
    $u = $pdo->query("SELECT login_id, role FROM users")->fetchAll();
    echo "\nUsers count: " . count($u) . "\n";
    print_r($u);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
