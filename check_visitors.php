<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM reception_visitors ORDER BY id DESC LIMIT 2");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
