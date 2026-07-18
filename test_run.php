<?php
// Fake an HTTP request to save_survey.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'create';
$_POST['question'] = 'Are you happy?';

// Mock session and CSRF
session_start();
$_SESSION['role'] = 'Admin';
$_SESSION['login_id'] = 1;
$_SESSION['csrf_token'] = 'test';
$_POST['csrf_token'] = 'test';

// Change dir so relative paths in save_survey.php work
chdir(__DIR__ . '/controllers');

// Include the target file
try {
    require 'save_survey.php';
} catch (Throwable $t) {
    echo "CAUGHT EXCEPTION: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString();
}
