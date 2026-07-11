<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_id = $_POST['form_id'];
    $user_id = $_SESSION['login_id'];
    
    // PHP parses inputs shaped like name="custom_data[Question]" into an associativive array
    $data = $_POST['custom_data'] ?? [];
    $data_json = json_encode($data);

    $stmt = $pdo->prepare("INSERT INTO form_submissions (form_id, user_id, data_json) VALUES (?, ?, ?)");
    $stmt->execute([$form_id, $user_id, $data_json]);
    
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$user_id, 'Submit Form', "Submitted data for Form ID {$form_id}"]);
    
    header("Location: ../forms.php");
}
?>
