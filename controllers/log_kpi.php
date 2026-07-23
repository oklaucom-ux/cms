<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'access_kpi');
require_once '../includes/flash.php';
require_once '../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kpi_id   = intval($_POST['kpi_id']);
    $value    = floatval($_POST['value_added']);
    $note     = $_POST['note'] ?? '';

    // Log the entry
    $pdo->prepare("INSERT INTO kpi_logs (kpi_id, value_added, note) VALUES (?,?,?)")->execute([$kpi_id, $value, $note]);

    // Update current_value
    $pdo->prepare("UPDATE kpi_targets SET current_value = current_value + ? WHERE id = ?")->execute([$value, $kpi_id]);

    // Auto-compute status
    $kpi = $pdo->prepare("SELECT * FROM kpi_targets WHERE id=?");
    $kpi->execute([$kpi_id]);
    $kpi = $kpi->fetch(PDO::FETCH_ASSOC);

    if ($kpi) {
        $pct = $kpi['target_value'] > 0 ? ($kpi['current_value'] / $kpi['target_value']) * 100 : 0;
        $daysLeft = $kpi['deadline'] ? max(0, (strtotime($kpi['deadline']) - time()) / 86400) : 999;

        $newStatus = 'On Track';
        if ($pct >= 100) {
            $newStatus = 'Achieved';
        } elseif ($daysLeft < 7 && $pct < 80) {
            $newStatus = 'At Risk';
        } elseif ($daysLeft < 14 && $pct < 60) {
            $newStatus = 'At Risk';
        } elseif ($daysLeft < 30 && $pct < 40) {
            $newStatus = 'At Risk';
        }

        $pdo->prepare("UPDATE kpi_targets SET status=? WHERE id=?")->execute([$newStatus, $kpi_id]);

        // Notify if At Risk
        if ($newStatus === 'At Risk') {
            createNotification($pdo, $kpi['user_id'], '⚡ KPI At Risk', "Your KPI \"{$kpi['title']}\" is at risk — {$pct}% complete with {$daysLeft} days left.", 'kpi.php');
        } elseif ($newStatus === 'Achieved') {
            createNotification($pdo, $kpi['user_id'], '🎉 KPI Achieved!', "Congratulations! You hit your target for \"{$kpi['title']}\".", 'kpi.php');
        }
    }

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$_SESSION['login_id'], 'Log KPI', '']);
    echo json_encode(["status" => "success", "message" => "Progress logged! Status auto-updated."]);
    exit();
}
