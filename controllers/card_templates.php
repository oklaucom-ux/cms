<?php
// controllers/card_templates.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$action = $_REQUEST['action'] ?? 'list';

try {
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM card_templates ORDER BY id DESC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'templates' => $templates]);
        exit();
    }

    if ($action === 'save') {
        $id              = (int)($_POST['id'] ?? 0);
        $name            = trim($_POST['name'] ?? '');
        $channel         = trim($_POST['channel'] ?? 'email');
        $subject         = trim($_POST['subject'] ?? '');
        $body            = trim($_POST['body'] ?? '');
        $is_auto_trigger = isset($_POST['is_auto_trigger']) && $_POST['is_auto_trigger'] == '1' ? 1 : 0;

        if (empty($name) || empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Template name and content body are required.']);
            exit();
        }

        // If setting auto trigger, clear auto trigger for same channel
        if ($is_auto_trigger === 1) {
            $stmtClear = $pdo->prepare("UPDATE card_templates SET is_auto_trigger = 0 WHERE channel = ?");
            $stmtClear->execute([$channel]);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE card_templates SET name = ?, channel = ?, subject = ?, body = ?, is_auto_trigger = ? WHERE id = ?");
            $stmt->execute([$name, $channel, $subject, $body, $is_auto_trigger, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO card_templates (name, channel, subject, body, is_auto_trigger) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $channel, $subject, $body, $is_auto_trigger]);
        }

        echo json_encode(['success' => true, 'message' => 'Template saved successfully!']);
        exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM card_templates WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true, 'message' => 'Template deleted.']);
        exit();
    }

    if ($action === 'toggle_auto') {
        $id      = (int)($_POST['id'] ?? 0);
        $channel = trim($_POST['channel'] ?? 'email');
        $enable  = isset($_POST['enable']) && $_POST['enable'] == '1' ? 1 : 0;

        if ($enable === 1) {
            $pdo->prepare("UPDATE card_templates SET is_auto_trigger = 0 WHERE channel = ?")->execute([$channel]);
            $pdo->prepare("UPDATE card_templates SET is_auto_trigger = 1 WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE card_templates SET is_auto_trigger = 0 WHERE id = ?")->execute([$id]);
        }

        echo json_encode(['success' => true, 'message' => 'Auto-trigger updated.']);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
