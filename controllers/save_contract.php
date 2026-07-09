<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Auto-migrate schema
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS contracts (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            title TEXT NOT NULL,
            recipient_name TEXT NOT NULL,
            recipient_email TEXT NOT NULL,
            content_html TEXT NOT NULL,
            signature_data TEXT,
            status TEXT DEFAULT 'Draft',
            token TEXT UNIQUE,
            created_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            signed_at DATETIME
        )");
    } catch (Exception $e) {}

    if ($action === 'create') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $title = $_POST['title'];
        $name = $_POST['recipient_name'];
        $email = $_POST['recipient_email'];
        $content = $_POST['content_html'];
        $token = bin2hex(random_bytes(16));
        
        $stmt = $pdo->prepare("INSERT INTO contracts (title, recipient_name, recipient_email, content_html, token, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $name, $email, $content, $token, $_SESSION['login_id']]);
        
        header("Location: ../contracts.php?msg=ContractCreated");
        exit;
    }
    
    if ($action === 'mark_sent') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE contracts SET status = 'Sent' WHERE id = ?")->execute([$id]);
        header("Location: ../contracts.php?msg=MarkedSent");
        exit;
    }
    
    if ($action === 'delete') {
        if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) die("Unauthorized");
        
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM contracts WHERE id = ?")->execute([$id]);
        header("Location: ../contracts.php?msg=Deleted");
        exit;
    }
    
    if ($action === 'sign') {
        // Public action
        $token = $_POST['token'] ?? '';
        $signature = $_POST['signature_data'] ?? '';
        
        if (empty($token) || empty($signature)) {
            die("Invalid data");
        }
        
        $stmt = $pdo->prepare("UPDATE contracts SET signature_data = ?, status = 'Signed', signed_at = CURRENT_TIMESTAMP WHERE token = ? AND status != 'Signed'");
        $stmt->execute([$signature, $token]);
        
        // Audit log
        $c = $pdo->prepare("SELECT id, title, recipient_name FROM contracts WHERE token = ?");
        $c->execute([$token]);
        $contractInfo = $c->fetch(PDO::FETCH_ASSOC);
        if ($contractInfo) {
            $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('System', 'Contract Signed', 'Contract {$contractInfo['title']} was signed by {$contractInfo['recipient_name']}')");
        }
        
        header("Location: ../sign_contract.php?token=" . urlencode($token) . "&success=1");
        exit;
    }
}
?>
