<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '' || ($email === '' && $phone === '')) {
        header("Location: ../index.php?error=missing_fields");
        exit();
    }

    // Attempt to find a default owner (first admin)
    $owner = $pdo->query("SELECT login_id FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if (!$owner) {
        $owner = 'System'; // Fallback
    }

    $customData = json_encode([
        'phone' => $phone,
        'notes' => $notes,
        'source' => 'Public Website - Request Call'
    ]);

    // Insert into crm_leads table
    try {
        $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, value, stage, owner_id, custom_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $company,
            $email,
            0,
            'Prospect', // Initial stage
            $owner,
            $customData
        ]);
        
        // Optionally create a notification for the admin
        if ($owner !== 'System') {
            require_once '../includes/notifications.php';
            createNotification($pdo, $owner, "New Lead", "New call request from $name ($company)", "crm.php");
        }

        header("Location: ../index.php?applied=call_requested");
        exit();

    } catch (PDOException $e) {
        // Fallback or log error
        header("Location: ../index.php?error=db_error");
        exit();
    }
}
