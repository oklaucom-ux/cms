<?php
require_once 'includes/db.php';
require_once 'includes/webhook_helper.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Client') {
    die("<div class='content-section active'><h2>Unauthorized</h2></div>");
}

$invoice_id = $_GET['invoice_id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("<div class='content-section active'><h2>Invoice Not Found</h2></div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate Payment Processing
    $pdo->prepare("UPDATE invoices SET status = 'Paid' WHERE invoice_id = ?")->execute([$invoice_id]);
    
    // Fire Webhook
    fireWebhook($pdo, 'invoice_paid', [
        'invoice_id' =>$invoice_id,
        'amount' =>$invoice['amount'],
        'client' =>$invoice['client_name']
    ]);

    echo "<script>alert('Payment Successful!'); window.location.href='client_portal.php';</script>";
    exit;
}
?>

<div class="content-section active" style="display:flex; justify-content:center; align-items:center; min-height:80vh;">
    <div style="background:white; padding:40px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1); max-width:400px; width:100%; text-align:center;">
        <div style="font-size:48px; margin-bottom:20px;">💳</div>
        <h2 style="margin:0 0 10px 0;">Secure Checkout</h2>
        <p style="color:#64748b; margin-bottom:30px;">You are paying Invoice <strong><?= htmlspecialchars($invoice['invoice_id']) ?></strong></p>
        
        <div style="font-size:36px; font-weight:bold; color:#10b981; margin-bottom:30px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($invoice['amount'], 2) ?>
        </div>
        
        <form method="POST" style="text-align:left;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label style="display:block; font-size:12px; font-weight:bold; color:#475569; margin-bottom:5px;">Cardholder Name</label>
            <input type="text" value="<?= htmlspecialchars($_SESSION['name']) ?>" required style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;" readonly>
            
            <label style="display:block; font-size:12px; font-weight:bold; color:#475569; margin-bottom:5px;">Card Details (Simulated)</label>
            <input type="text" value="•••• •••• •••• 4242" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px; font-family:monospace;" readonly>
            
            <button type="submit" style="width:100%; background:#4f46e5; color:white; border:none; padding:15px; border-radius:6px; font-size:16px; font-weight:bold; cursor:pointer;">Pay $<?= number_format($invoice['amount'], 2) ?></button>
            <a href="client_portal.php" style="display:block; text-align:center; color:#64748b; margin-top:15px; text-decoration:none; font-size:14px;">Cancel & Return</a>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
