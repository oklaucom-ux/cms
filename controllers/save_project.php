<?php
session_start();
require_once '../includes/db.php';

// Migrate branch_id if missing
try { $pdo->exec("ALTER TABLE projects ADD COLUMN branch_id TEXT DEFAULT 'Global HQ'"); } catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        requirePermission($pdo, 'edit_projects');
    } else {
        requirePermission($pdo, 'create_projects');
    }
    $name = $_POST['name'];
    $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $budget = floatval($_POST['budget']);
    $deadline = $_POST['deadline'];
    $status = $_POST['status'];

    // Backup client text for legacy display safely
    $clientText = 'Internal';
    if ($client_id) {
        $stmt_client = $pdo->prepare("SELECT name FROM users WHERE login_id = ?");
        $stmt_client->execute([$client_id]);
        $cName = $stmt_client->fetchColumn();
        if ($cName) $clientText = $cName;
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE projects SET name=?, client=?, client_id=?, budget=?, deadline=?, status=? WHERE id=?");
        $stmt->execute([$name, $clientText, $client_id, $budget, $deadline, $status, $id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, 'Update Project', ?)")->execute([$_SESSION['login_id'], "Updated Project $name"]);
    } else {
        // Fetch current user's branch
        $branchStmt = $pdo->prepare("SELECT branch_id FROM users WHERE login_id = ?");
        $branchStmt->execute([$_SESSION['login_id']]);
        $branch_id = $branchStmt->fetchColumn() ?: 'Global HQ';

        $stmt = $pdo->prepare("INSERT INTO projects (name, client, client_id, budget, deadline, status, created_by, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $clientText, $client_id, $budget, $deadline, $status, $_SESSION['login_id'], $branch_id]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, 'Create Project', ?)")->execute([$_SESSION['login_id'], "Spun up Project $name"]);
    }
    
    header("Location: ../projects.php");
    exit();
}
