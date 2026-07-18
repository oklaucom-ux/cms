<?php
session_start();
$_SESSION['login_id'] = 1;
require 'includes/db.php';
require 'controllers/reception_api.php';
sendSystemChat($pdo, 2, 'Test visitor notification');
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
