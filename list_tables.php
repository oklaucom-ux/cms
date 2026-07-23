<?php
require 'includes/db.php';
foreach($pdo->query("PRAGMA table_info(custom_statuses)") as $row) {
    echo $row['name'] . "\n";
}
echo "----\n";
foreach($pdo->query("PRAGMA table_info(notes)") as $row) {
    echo $row['name'] . "\n";
}
