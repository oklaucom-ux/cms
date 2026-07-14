<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'includes/db.php';
header('Content-Type: text/plain');

$name = 'Test Candidate';
$email = 'test@example.com';
$phone = '1234567890';
$role_applied = 'Developer';
$resume_path = null;

try {
    $stmt = $pdo->prepare("INSERT INTO applicants (name, email, phone, role_applied, resume_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $role_applied, $resume_path]);
    echo "Applicant insert successful! ID: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Applicant insert FAILED!\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

try {
    $cols = $pdo->query("PRAGMA table_info(applicants)")->fetchAll(PDO::FETCH_ASSOC);
    echo "Applicants columns (SQLite):\n";
    foreach($cols as $c) echo "- " . $c['name'] . "\n";
} catch(Exception $e) {}

try {
    $cols = $pdo->query("SHOW COLUMNS FROM applicants")->fetchAll(PDO::FETCH_ASSOC);
    echo "Applicants columns (MySQL):\n";
    foreach($cols as $c) echo "- " . $c['Field'] . "\n";
} catch(Exception $e) {}
