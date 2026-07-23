<?php
require_once 'includes/db.php';
$internal_stmt = $pdo->prepare("
      SELECT login_id, name FROM users WHERE login_id != ? AND status = 'Active' AND role != 'Client'
      UNION 
      SELECT login_id, name FROM super_admins WHERE login_id != ? 
      ORDER BY name ASC
  ");
$internal_stmt->execute(['admin123', 'admin123']);
$internal_users = $internal_stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($internal_users);
