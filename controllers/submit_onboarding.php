<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];
    $position = $_POST['position'];
    $link = $_POST['resume_link'];

    $stmt = $pdo->prepare("INSERT INTO onboarding_applications (first_name, last_name, email, position_applied, resume_link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$first, $last, $email, $position, $link]);

    // Send them back to the index page with a success message
    header("Location: ../index.php?applied=true");
    exit();
}
