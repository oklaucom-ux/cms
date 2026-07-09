<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

requirePermission($pdo, 'manage_forms');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $frequency = $_POST['frequency'];
    $schema_json = $_POST['schema_json'];
    $assigned_users = $_POST['assigned_users'] ?? [];
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO dynamic_forms (title, frequency, schema_json, is_public) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $frequency, $schema_json, $is_public]);
    $form_id = $pdo->lastInsertId();

    if (in_array("ALL", $assigned_users)) {
        $all = $pdo->query("SELECT login_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        foreach($all as $u) {
            $pdo->prepare("INSERT INTO form_assignments (form_id, assigned_to) VALUES (?, ?)")->execute([$form_id, $u]);
            $email = getUserEmail($pdo, $u);
            if ($email) sendSystemEmail($email, "New Form Assigned: {$title}", "You have a new form waiting: <strong>{$title}</strong>.<br>Frequency requirement: {$frequency}");
        }
    } else {
        foreach($assigned_users as $u) {
            $pdo->prepare("INSERT INTO form_assignments (form_id, assigned_to) VALUES (?, ?)")->execute([$form_id, $u]);
            $email = getUserEmail($pdo, $u);
            if ($email) sendSystemEmail($email, "New Form Assigned: {$title}", "You have a new form waiting: <strong>{$title}</strong>.<br>Frequency requirement: {$frequency}");
        }
    }

    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Create Form', 'Created Form Prototype: {$title}')");
    header("Location: ../forms.php");
}
?>
