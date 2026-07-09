<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_assets');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($asset) {
        // TERMINATION BLOCKER: Cannot retire/delete an actively assigned asset
        if (!empty($asset['assigned_to']) && $asset['status'] === 'Assigned') {
            $_SESSION['flash_error'] = "⚠️ Cannot retire asset [{$asset['asset_tag']}] — it is currently assigned to {$asset['assigned_to']}. Unassign it first.";
            header("Location: ../assets.php");
            exit();
        }

        // Soft retire
        $pdo->prepare("UPDATE assets SET status = 'Retired' WHERE id = ?")->execute([$id]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Retire Asset', 'Retired asset {$asset['asset_tag']}')");
    }

    header("Location: ../assets.php");
    exit();
}
