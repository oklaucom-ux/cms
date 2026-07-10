<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_contracts');

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contracts (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title TEXT NOT NULL,
        recipient_name TEXT NOT NULL,
        recipient_email TEXT NOT NULL,
        content_html TEXT NOT NULL,
        signature_data TEXT,
        status VARCHAR(255) DEFAULT 'Draft',
        token VARCHAR(255) UNIQUE,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        signed_at DATETIME
    )");
} catch (Exception $e) {}

if (!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>HR or Admin privileges required for Contracts.</p></div>");
}

$contracts = $pdo->query("SELECT * FROM contracts ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Base URL for signing links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📜 Legal Contracts & e-Sign</h2>
        <button class="add-button" onclick="document.getElementById('contractModal').style.display='flex'">+ Create Contract</button>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Recipient</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Signed At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($contracts as $c): 
                    $signLink = $baseUrl . "/sign_contract.php?token=" . urlencode($c['token']);
                ?>
                <tr>
                    <td style="font-weight:bold; color:var(--text-heading);"><?= htmlspecialchars($c['title']) ?></td>
                    <td><?= htmlspecialchars($c['recipient_name']) ?></td>
                    <td><?= htmlspecialchars($c['recipient_email']) ?></td>
                    <td>
                        <span style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; 
                            background: <?= $c['status']=='Signed' ? '#d1fae5; color:#065f46;' : ($c['status']=='Sent' ? '#dbeafe; color:#1e40af;' : '#f3f4f6; color:#475569;') ?>">
                            <?= $c['status'] ?>
                        </span>
                    </td>
                    <td style="font-size:12px; color:#64748b;"><?= substr($c['created_at'], 0, 10) ?></td>
                    <td style="font-size:12px; color:#64748b;"><?= $c['signed_at'] ? substr($c['signed_at'], 0, 16) : '-' ?></td>
                    <td>
                        <?php if($c['status'] === 'Signed'): ?>
                            <a href="<?= $signLink ?>" target="_blank" style="background:#10b981; color:white; padding:4px 8px; border-radius:4px; text-decoration:none; font-size:11px;">View PDF/Signed</a>
                        <?php else: ?>
                            <button onclick="copyToClipboard('<?= $signLink ?>')" style="background:#f1f5f9; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">Copy Link</button>
                            <form method="POST" action="controllers/save_contract.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="mark_sent">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" style="background:#3b82f6; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">Mark Sent</button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" action="controllers/save_contract.php" style="display:inline;" onsubmit="return confirm('Delete this contract?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" style="background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($contracts)) echo "<tr><td colspan='7' style='text-align:center;'>No contracts generated yet.</td></tr>"; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Contract Modal -->
<div class="modal" id="contractModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:600px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Generate New Contract</h2>
        <form method="POST" action="controllers/save_contract.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Contract Title</label>
            <input type="text" name="title" required placeholder="e.g. Non-Disclosure Agreement (NDA)" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Recipient Name</label>
                    <input type="text" name="recipient_name" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Recipient Email</label>
                    <input type="email" name="recipient_email" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Contract Content (HTML/Text)</label>
            <textarea name="content_html" required rows="10" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px; font-family:monospace;" placeholder="Enter legal terms here..."></textarea>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('contractModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Generate & Save Draft</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Signing Link Copied to Clipboard!');
    }, function(err) {
        alert('Could not copy text: ', err);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>

