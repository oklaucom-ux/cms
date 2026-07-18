<?php
require 'includes/db.php';
try {
    $stmt = $pdo->query('PRAGMA table_info(notifications)');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo 'MISSING';
}
