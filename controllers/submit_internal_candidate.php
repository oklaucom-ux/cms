<?php
session_start();
require_once '../includes/db.php';

if (!hasPermission($pdo, 'manage_onboarding')) {
    die("Unauthorized.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $position = trim($_POST['position']);
    
    // Resume link inside enterprise apps is optional for internal hires
    $resume_link = trim($_POST['resume_link'] ?? '');
    
    $stmt = $pdo->prepare("INSERT INTO onboarding_applications (first_name, last_name, email, position_applied, resume_link, status) VALUES (?,?,?,?,?,'Pending')");
    $stmt->execute([$first_name, $last_name, $email, $position, $resume_link]);
    
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Added Internal Candidate', '']);
    
    $_SESSION['flash_success'] = "Candidate staged into the pipeline successfully.";
}

header("Location: ../onboarding.php");
exit();
