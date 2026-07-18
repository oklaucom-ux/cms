<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT id, name FROM users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " - " . $row['name'] . "\n";
}
