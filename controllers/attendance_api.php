<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $me = $_SESSION['login_id'];
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $lat = $_POST['latitude'] ?? null;
    $lng = $_POST['longitude'] ?? null;

    if ($action === 'clock_in') {
        // Prevent duplicate clock-in for same day
        $exists = $pdo->prepare("SELECT id FROM attendance WHERE user_id=? AND date=?");
        $exists->execute([$me, $today]);
        if ($exists->fetchColumn()) {
            header("Location: ../attendance.php?error=" . urlencode("You have already clocked in today."));
            exit();
        }
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in, status, ip_address, latitude, longitude) VALUES (?, ?, ?, 'Present', ?, ?, ?)");
        $stmt->execute([$me, $today, $now, $ip, $lat, $lng]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$me, 'Clock In', "Clocked in at $now from IP $ip"]);
    } 
    elseif ($action === 'clock_out') {
        // Technically they might move, but we will just update the clock_out for the day. If needed we could append new coords.
        $stmt = $pdo->prepare("UPDATE attendance SET clock_out = ?, ip_address = ?, latitude = ?, longitude = ? WHERE user_id = ? AND date = ?");
        $stmt->execute([$now, $ip, $lat, $lng, $me, $today]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$me, 'Clock Out', "Clocked out at $now from IP $ip"]);
    }

    header("Location: ../attendance.php");
}
?>
