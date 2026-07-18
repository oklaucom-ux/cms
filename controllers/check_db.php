<?php
try {
    $pdo = new PDO('sqlite:../database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n";
    
    if (in_array('pulse_surveys', $tables)) {
        echo "pulse_surveys exists.\n";
    } else {
        echo "pulse_surveys DOES NOT EXIST!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
