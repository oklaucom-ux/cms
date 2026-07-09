<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/webhook_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        requirePermission($pdo, 'edit_invoices');
    } else {
        requirePermission($pdo, 'create_invoices');
    }
    $invoice_id = $_POST['invoice_id'];
    $client_name = $_POST['client_name'];
    $amount = $_POST['amount'];
    $issue_date = $_POST['issue_date'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];
    
    if ($id) {
        $old_status = $pdo->query("SELECT status FROM invoices WHERE id=" . intval($id))->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE invoices SET invoice_id=?, client_name=?, amount=?, issue_date=?, due_date=?, status=? WHERE id=?");
        $stmt->execute([$invoice_id, $client_name, $amount, $issue_date, $due_date, $status, $id]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Update Invoice', 'Updated invoice {$invoice_id}')");
        
        if ($old_status !== 'Paid' && $status === 'Paid') {
            fireWebhook($pdo, 'invoice_paid', [
                'invoice_id' => $invoice_id,
                'amount' => $amount,
                'client' => $client_name
            ]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO invoices (invoice_id, client_name, amount, issue_date, due_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_id, $client_name, $amount, $issue_date, $due_date, $status]);
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Create Invoice', 'Created invoice {$invoice_id}')");
        
        if ($status === 'Paid') {
            fireWebhook($pdo, 'invoice_paid', [
                'invoice_id' => $invoice_id,
                'amount' => $amount,
                'client' => $client_name
            ]);
        }
    }
    header("Location: ../invoices.php");
}
?>
