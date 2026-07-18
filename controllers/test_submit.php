<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'submit_response';
$_POST['survey_id'] = 1;
$_POST['score'] = 5;
$_POST['comment'] = 'Test comment';

// Mock session and CSRF
session_start();
$_SESSION['login_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['csrf_token'] = 'fake_token';
$_POST['csrf_token'] = 'fake_token';

// Bypass CSRF referer check for tests if any
$_SERVER['HTTP_REFERER'] = 'http://localhost/pulse_surveys.php';
$_SERVER['PHP_SELF'] = '/controllers/save_survey.php';

try {
    ob_start();
    require_once 'save_survey.php';
    $output = ob_get_clean();
    echo "Output: " . $output;
} catch (Throwable $e) {
    echo "Error caught: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile();
}
