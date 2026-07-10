<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendors (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        company_name TEXT NOT NULL,
        contact_name TEXT,
        email TEXT,
        phone TEXT,
        payment_terms TEXT,
        scorecard_rating INTEGER DEFAULT 3,
        status VARCHAR(255) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

if (!hasPermission($pdo, 'view_invoices') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>Procurement privileges required.</p></div>");
}

$vendors = $pdo->query("SELECT * FROM vendors ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Count active vs inactive
$activeCount = 0;
foreach($vendors as $v) if($v['status'] === 'Active') $activeCount++;
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🤝 Vendor CRM & Scorecards</h2>
        <button class="add-button" onclick="document.getElementById('vendorModal').style.display='flex'">+ Add Vendor</button>
    </div>

    <!-- Quick Stats -->
    <div style="display:flex; gap:20px; margin-bottom:20px;">
        <div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; flex:1; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
            <div style="font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase;">Total Vendors</div>
            <div style="font-size:28px; font-weight:900; color:#1e293b;"><?= count($vendors) ?></div>
        </div>
        <div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; flex:1; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
            <div style="font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase;">Active Suppliers</div>
            <div style="font-size:28px; font-weight:900; color:#10b981;"><?= $activeCount ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;">
        <?php foreach($vendors as $v): ?>
        <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
            <div style="padding:20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h3 style="margin:0 0 5px 0; color:#1e293b; font-size:18px;"><?= htmlspecialchars($v['company_name']) ?></h3>
                    <div style="font-size:12px; color:#64748b;">👤 <?= htmlspecialchars($v['contact_name'] ?: 'No Contact') ?></div>
                </div>
                <span style="padding:4px 8px; border-radius:12px; font-size:11px; font-weight:bold; <?= $v['status']=='Active' ? 'background:#d1fae5; color:#065f46;' : 'background:#fee2e2; color:#991b1b;' ?>">
                    <?= $v['status'] ?>
                </span>
            </div>
            <div style="padding:20px; background:#f8fafc; font-size:13px; color:#475569;">
                <div style="margin-bottom:8px;"><strong>Email:</strong> <?= htmlspecialchars($v['email'] ?: 'N/A') ?></div>
                <div style="margin-bottom:8px;"><strong>Phone:</strong> <?= htmlspecialchars($v['phone'] ?: 'N/A') ?></div>
                <div style="margin-bottom:8px;"><strong>Terms:</strong> <?= htmlspecialchars($v['payment_terms'] ?: 'N/A') ?></div>
                
                <!-- Scorecard Rating -->
                <div style="margin-top:15px; border-top:1px solid #e2e8f0; padding-top:15px; display:flex; align-items:center; justify-content:space-between;">
                    <strong>Performance Score:</strong>
                    <div style="color:#f59e0b; font-size:16px;">
                        <?= str_repeat('★', $v['scorecard_rating']) . str_repeat('☆', 5 - $v['scorecard_rating']) ?>
                    </div>
                </div>
            </div>
            <div style="padding:10px 20px; border-top:1px solid #f1f5f9; display:flex; gap:10px; background:white;">
                <button onclick='editVendor(<?= json_encode($v) ?>)' style="background:#f1f5f9; color:#475569; border:none; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:bold; cursor:pointer; flex:1;">Edit</button>
                <form method="POST" action="controllers/save_vendor.php" style="margin:0; flex:1;" onsubmit="return confirm('Change status?')">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <button type="submit" style="background:#f1f5f9; color:#475569; border:none; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:bold; cursor:pointer; width:100%;">Toggle Status</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($vendors)) echo "<p style='color:#64748b; padding:20px;'>No vendors found. Add your first supplier.</p>"; ?>
    </div>
</div>

<!-- Vendor Modal -->
<div class="modal" id="vendorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;" id="modalTitle">Add Vendor</h2>
        <form method="POST" action="controllers/save_vendor.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="v_id">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Company Name</label>
            <input type="text" name="company_name" id="v_company" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Contact Name</label>
                    <input type="text" name="contact_name" id="v_contact" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Email</label>
                    <input type="email" name="email" id="v_email" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Phone</label>
                    <input type="text" name="phone" id="v_phone" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Payment Terms</label>
                    <select name="payment_terms" id="v_terms" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="Net 30">Net 30</option>
                        <option value="Net 60">Net 60</option>
                        <option value="Due on Receipt">Due on Receipt</option>
                    </select>
                </div>
            </div>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Scorecard Rating (1-5)</label>
            <input type="number" min="1" max="5" name="scorecard_rating" id="v_score" value="3" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;">
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('vendorModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Save Vendor</button>
            </div>
        </form>
    </div>
</div>

<script>
function editVendor(v) {
    document.getElementById('modalTitle').innerText = 'Edit Vendor';
    document.getElementById('v_id').value = v.id;
    document.getElementById('v_company').value = v.company_name;
    document.getElementById('v_contact').value = v.contact_name;
    document.getElementById('v_email').value = v.email;
    document.getElementById('v_phone').value = v.phone;
    document.getElementById('v_terms').value = v.payment_terms;
    document.getElementById('v_score').value = v.scorecard_rating;
    document.getElementById('vendorModal').style.display = 'flex';
}
</script>

<?php require_once 'includes/footer.php'; ?>

