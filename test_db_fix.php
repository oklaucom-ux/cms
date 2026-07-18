<?php
require 'includes/db.php';
try {
    $autoIncrement = isset($use_mysql) && $use_mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS company_benefits_test (id INTEGER PRIMARY KEY $autoIncrement, title TEXT)");
    echo 'Success';
} catch(Exception $e) {
    echo $e->getMessage();
}
?>
