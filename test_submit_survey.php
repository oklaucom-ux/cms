<?php
// fake POST request to save_survey.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'submit_response';
$_POST['survey_id'] = 1;
$_POST['score'] = 5;
$_POST['comment'] = 'Test comment';

// Mock session
session_start();
$_SESSION['login_id'] = 1;
$_SESSION['role'] = 'Admin';

// Include the script and catch errors
try {
    ob_start();
    require_once 'controllers/save_survey.php';
    $output = ob_get_clean();
    echo "Output: " . $output;
} catch (Throwable $e) {
    echo "Error caught: " . $e->getMessage() . " on line " . $e->getLine();
}
