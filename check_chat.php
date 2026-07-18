<?php
require 'includes/db.php';
try {
    $pdo->query('SELECT 1 FROM chat_messages');
    echo 'EXISTS';
} catch (Exception $e) {
    echo 'MISSING: ' . $e->getMessage();
}
