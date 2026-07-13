<?php
require 'includes/db.php';
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS test_tbl_mysql (id INTEGER PRIMARY KEY AUTO_INCREMENT)');
    echo 'SUCCESS';
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
