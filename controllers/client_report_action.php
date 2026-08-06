<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Client') {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_id = intval($_POST['report_id']);
    $approved = isset($_POST['approved']) && $_POST['approved'] == 1 ? 1 : 0;
    $feedback = trim($_POST['client_feedback'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE daily_reports SET client_approved = ?, client_feedback = ? WHERE id = ?");
        $stmt->execute([$approved, $feedback, $report_id]);

        $rStmt = $pdo->prepare("SELECT user_id, report_date FROM daily_reports WHERE id = ?");
        $rStmt->execute([$report_id]);
        $rep = $rStmt->fetch(PDO::FETCH_ASSOC);

        if ($rep && !empty($rep['user_id'])) {
            $msg = "Client (" . ($_SESSION['name'] ?? 'Client') . ") " . ($approved ? "Approved" : "Commented on") . " your daily report for {$rep['report_date']}.";
            createNotification($pdo, $rep['user_id'], 'Client Daily Report Sign-off', $msg, 'daily_reports.php');
        }

        header("Location: ../client_portal.php?success=" . urlencode("Daily Work Report sign-off updated."));
        exit();
    } catch (Exception $e) {
        die("Error saving client sign-off: " . $e->getMessage());
    }
}
