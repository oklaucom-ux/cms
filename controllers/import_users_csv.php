<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
requirePermission($pdo, 'manage_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (!$file) { setFlash('error','No file uploaded.'); header("Location: ../users.php"); exit(); }

    $handle = fopen($file, 'r');
    $headers = fgetcsv($handle); // skip header row
    $headers = array_map('strtolower', array_map('trim', $headers));

    $imported = 0; $skipped = 0; $errors = [];
    $required = ['login_id','name','email','role'];

    // Validate headers
    foreach ($required as $r) {
        if (!in_array($r, $headers)) {
            setFlash('error', "CSV missing required column: {$r}. Required: login_id, name, email, role, [department, password]");
            header("Location: ../users.php"); exit();
        }
    }

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < count($required)) { $skipped++; continue; }
        $data = array_combine($headers, array_pad($row, count($headers), ''));
        $login_id   = trim($data['login_id'] ?? '');
        $name       = trim($data['name'] ?? '');
        $email      = trim($data['email'] ?? '');
        $role       = trim($data['role'] ?? 'Employee');
        $dept       = trim($data['department'] ?? '');
        $password   = trim($data['password'] ?? '');

        if (!$login_id || !$name || !$email) { $skipped++; continue; }

        // Check existing
        $exists = $pdo->prepare("SELECT id FROM users WHERE login_id=? OR email=?");
        $exists->execute([$login_id, $email]);
        if ($exists->fetch()) { $skipped++; $errors[] = "Skipped {$login_id} — already exists."; continue; }

        $hashed = password_hash($password ?: 'changeme123', PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO users (login_id, password, name, email, role, department, status) VALUES (?,?,?,?,?,?,'Active')")
                ->execute([$login_id, $hashed, $name, $email, $role, $dept]);
            $imported++;
        } catch (Exception $e) { $skipped++; $errors[] = "Error on {$login_id}: ".$e->getMessage(); }
    }
    fclose($handle);

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute(['{$_SESSION[', 'login_id']}'', 'Bulk Import']);
    $msg = "Imported {$imported} user(s). Skipped: {$skipped}.";
    if (!empty($errors)) $msg .= ' Errors: ' . implode('; ', array_slice($errors, 0, 3));
    setFlash($imported > 0 ? 'success' : 'warning', $msg);
    header("Location: ../users.php");
    exit();
}
header("Location: ../users.php");
