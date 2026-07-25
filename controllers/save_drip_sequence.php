<?php
// controllers/save_drip_sequence.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$action = $_REQUEST['action'] ?? 'save_sequence';

try {
    if ($action === 'list') {
        $stmtSeq = $pdo->query("SELECT * FROM crm_drip_sequences ORDER BY id DESC");
        $sequences = $stmtSeq->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sequences as &$seq) {
            $stmtSteps = $pdo->prepare("SELECT * FROM crm_drip_steps WHERE sequence_id = ? ORDER BY step_number ASC");
            $stmtSteps->execute([$seq['id']]);
            $seq['steps'] = $stmtSteps->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'sequences' => $sequences]);
        exit();
    }

    if ($action === 'save_sequence') {
        $id            = (int)($_POST['id'] ?? 0);
        $title         = trim($_POST['title'] ?? '');
        $trigger_stage = trim($_POST['trigger_stage'] ?? 'Prospect');
        $steps         = $_POST['steps'] ?? [];

        if (is_string($steps)) {
            $steps = json_decode($steps, true) ?: [];
        }

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Sequence title is required.']);
            exit();
        }

        $pdo->beginTransaction();

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE crm_drip_sequences SET title = ?, trigger_stage = ? WHERE id = ?");
            $stmt->execute([$title, $trigger_stage, $id]);
            $sequence_id = $id;
            $pdo->prepare("DELETE FROM crm_drip_steps WHERE sequence_id = ?")->execute([$sequence_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO crm_drip_sequences (title, trigger_stage) VALUES (?, ?)");
            $stmt->execute([$title, $trigger_stage]);
            $sequence_id = $pdo->lastInsertId();
        }

        $stmtStep = $pdo->prepare("INSERT INTO crm_drip_steps (sequence_id, step_number, delay_days, channel, subject, body) VALUES (?, ?, ?, ?, ?, ?)");

        $num = 1;
        foreach ($steps as $st) {
            $stmtStep->execute([
                $sequence_id,
                $num++,
                (int)($st['delay_days'] ?? 1),
                $st['channel'] ?? 'email',
                $st['subject'] ?? 'Follow up',
                $st['body'] ?? 'Message content'
            ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Drip sequence saved successfully!']);
        exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM crm_drip_sequences WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM crm_drip_steps WHERE sequence_id = ?")->execute([$id]);
        }
        echo json_encode(['success' => true, 'message' => 'Drip sequence deleted.']);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Drip sequence error: ' . $e->getMessage()]);
}
