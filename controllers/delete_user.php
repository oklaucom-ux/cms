<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'delete_users');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Fetch user to check against self-deletion and role
    $stmt = $pdo->prepare("SELECT login_id, name, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($target) {
        if ($target['role'] === 'Super Admin' && $_SESSION['role'] !== 'Super Admin') {
            $_SESSION['flash_error'] = "⚠️ You do not have permission to terminate a Super Admin.";
            header("Location: ../users.php");
            exit();
        }
        if ($target['role'] === 'Admin' && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
            $_SESSION['flash_error'] = "⚠️ You do not have permission to terminate an Admin.";
            header("Location: ../users.php");
            exit();
        }

        if ($target['login_id'] !== $_SESSION['login_id']) {

        // ── PHASE 18: ASSET TERMINATION BLOCKER ──────────────────────────
        // Prevent terminating a user who still has IT assets assigned.
        try {
            $assetCheck = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE assigned_to = ? AND status = 'Assigned'");
            $assetCheck->execute([$target['login_id']]);
            $assetCount = $assetCheck->fetchColumn();

            if ($assetCount > 0) {
                $_SESSION['flash_error'] = "⚠️ Cannot terminate {$target['name']} — they have {$assetCount} active IT asset(s) still assigned. Please recover assets via 🖥️ IT Asset Tracking first.";
                header("Location: ../users.php");
                exit();
            }
        } catch (Exception $e) {
            // assets table not yet migrated — proceed safely
        }
        // ─────────────────────────────────────────────────────────────────

        $update = $pdo->prepare("UPDATE users SET status = 'Terminated' WHERE id = ?");
        $update->execute([$id]);

        // ── PURGE HR FILES (GDPR Compliance & Storage Optimization) ──────
        try {
            $docs = $pdo->prepare("SELECT id, file_path FROM user_documents WHERE user_id = ?");
            $docs->execute([$target['login_id']]);
            foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $p = '../' . $d['file_path'];
                if (file_exists($p)) { unlink($p); }
            }
            $pdo->prepare("DELETE FROM user_documents WHERE user_id = ?")->execute([$target['login_id']]);
        } catch (Exception $e) {}

        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Soft Delete', 'Terminated user {$target['name']} and purged localized HR documents.')");
        }
    }

    header("Location: ../users.php");
    exit();
}
?>
