<?php
require_once 'includes/db.php';

// Ensure only authorized users can access the vendor portal
requirePermission($pdo, 'manage_vendors');

$isAdmin = in_array($_SESSION['role'], ['Admin', 'Super Admin']);

// 1. Initialize Tables
$idColumn = isset($use_mysql) && $use_mysql ? 'id INT AUTO_INCREMENT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendors (
        $idColumn,
        company_name TEXT,
        contact_name TEXT,
        email TEXT,
        phone TEXT,
        status TEXT DEFAULT 'Active',
        tax_id TEXT,
        service_category TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_contracts (
        $idColumn,
        vendor_id INTEGER,
        contract_title TEXT,
        start_date DATE,
        end_date DATE,
        value DECIMAL(10,2),
        status TEXT DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Silently ignore or log error if DB is locked/cannot create table
    error_log("Vendor DB Init Error: " . $e->getMessage());
}

// Add missing columns if the table already existed (e.g. in MySQL)
try { $pdo->exec("ALTER TABLE vendors ADD COLUMN tax_id TEXT"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE vendors ADD COLUMN service_category TEXT"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE vendors ADD COLUMN contact_name TEXT"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE vendors ADD COLUMN phone TEXT"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE vendors ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Fetch Metrics & Data separately so one failure doesn't break everything
$totalVendors = $activeVendors = $expiringContracts = 0;
$vendors = [];
$contractsData = [];

try {
    $totalVendors = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    $activeVendors = $pdo->query("SELECT COUNT(*) FROM vendors WHERE status = 'Active'")->fetchColumn();
    $vendors = $pdo->query("SELECT * FROM vendors ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Vendor Fetch Error: " . $e->getMessage());
}

try {
    if (isset($use_mysql) && $use_mysql) {
        $expiringContracts = $pdo->query("SELECT COUNT(*) FROM vendor_contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'Active'")->fetchColumn();
    } else {
        $expiringContracts = $pdo->query("SELECT COUNT(*) FROM vendor_contracts WHERE end_date BETWEEN date('now') AND date('now', '+30 days') AND status = 'Active'")->fetchColumn();
    }
    $contractsData = $pdo->query("SELECT * FROM vendor_contracts")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Contract Fetch Error: " . $e->getMessage());
}

$contractsByVendor = [];
foreach ($contractsData as $c) {
    $contractsByVendor[$c['vendor_id']][] = $c;
}
?>

<div class="content-section active" style="padding-top:0;">
    <?php if(!empty($_SESSION['flash_error'])): ?>
    <div style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; border-radius:10px; padding:14px 18px; margin-bottom:20px; font-weight:600; font-size:14px;"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>
    <?php if(!empty($_SESSION['flash_success'])): ?>
    <div style="background:#d1fae5; color:#059669; border:1px solid #6ee7b7; border-radius:10px; padding:14px 18px; margin-bottom:20px; font-weight:600; font-size:14px;"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); endif; ?>

    <!-- Hero Header -->
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 0 0 24px 24px; padding: 40px; margin: -20px -20px 30px -20px; color: white; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 class="text-white" style="margin: 0 0 10px 0; font-size: 32px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff !important;"><i class="fas fa-handshake" style="margin-right:10px; color:#38bdf8;"></i> Vendor Portal</h1>
                <p class="text-light" style="margin: 0; font-size: 16px; color: #f8fafc !important;">Manage enterprise suppliers, track contracts, and monitor vendor performance.</p>
            </div>
            <div>
                <button onclick="openVendorModal()" style="background: linear-gradient(135deg, #38bdf8, #0ea5e9); color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);">
                    <i class="fas fa-plus"></i> Add New Vendor
                </button>
            </div>
        </div>
        <div style="position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(56,189,248,0.1); border-radius: 50%; filter: blur(40px);"></div>
    </div>

    <!-- Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 30px;">
        <div class="glass-card" style="padding: 24px; border-radius: 20px; background: var(--bg-card); border: 1px solid var(--border-card); display: flex; align-items: center; gap: 20px;">
            <div style="width: 60px; height: 60px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 14px; font-weight: 600; margin-bottom: 4px;">Total Vendors</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--text-heading);"><?= number_format($totalVendors) ?></div>
            </div>
        </div>
        
        <div class="glass-card" style="padding: 24px; border-radius: 20px; background: var(--bg-card); border: 1px solid var(--border-card); display: flex; align-items: center; gap: 20px;">
            <div style="width: 60px; height: 60px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 14px; font-weight: 600; margin-bottom: 4px;">Active Vendors</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--text-heading);"><?= number_format($activeVendors) ?></div>
            </div>
        </div>
        
        <div class="glass-card" style="padding: 24px; border-radius: 20px; background: var(--bg-card); border: 1px solid var(--border-card); display: flex; align-items: center; gap: 20px;">
            <div style="width: 60px; height: 60px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-file-contract"></i>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 14px; font-weight: 600; margin-bottom: 4px;">Expiring Contracts (30d)</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--text-heading);"><?= number_format($expiringContracts) ?></div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="glass-card" style="padding: 24px; border-radius: 20px; background: var(--bg-card); border: 1px solid var(--border-card);">
        <h2 style="margin: 0 0 20px 0; color: var(--text-heading); font-size: 20px; font-weight: 700;">Vendor Directory</h2>
        <div class="data-table">
            <table id="vendorsTable">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Category</th>
                        <th>Contact Person</th>
                        <th>Contact Info</th>
                        <th>Status</th>
                        <th>Contracts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($vendors as $v): 
                        $statusColor = '#6b7280';
                        $statusBg = '#f3f4f6';
                        if ($v['status'] === 'Active') { $statusColor = '#10b981'; $statusBg = '#d1fae5'; }
                        elseif ($v['status'] === 'Pending Review') { $statusColor = '#f59e0b'; $statusBg = '#fef3c7'; }
                        elseif ($v['status'] === 'Terminated') { $statusColor = '#ef4444'; $statusBg = '#fee2e2'; }
                        
                        $vc = $contractsByVendor[$v['id']] ?? [];
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($v['company_name']) ?></td>
                        <td><span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 600;"><?= htmlspecialchars($v['service_category'] ?: 'Uncategorized') ?></span></td>
                        <td><?= htmlspecialchars($v['contact_name']) ?></td>
                        <td>
                            <div style="font-size: 13px;"><i class="fas fa-envelope" style="color:var(--text-muted); width:16px;"></i> <a href="mailto:<?= htmlspecialchars($v['email']) ?>" style="color:var(--primary-color); text-decoration:none;"><?= htmlspecialchars($v['email']) ?></a></div>
                            <div style="font-size: 13px; margin-top: 4px;"><i class="fas fa-phone" style="color:var(--text-muted); width:16px;"></i> <?= htmlspecialchars($v['phone']) ?></div>
                        </td>
                        <td><span style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;"><?= htmlspecialchars($v['status']) ?></span></td>
                        <td>
                            <button onclick='openContractsModal(<?= json_encode($v) ?>, <?= json_encode($vc) ?>)' style="background: rgba(15, 23, 42, 0.05); border: 1px solid var(--border-card); color: var(--text-heading); padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                <i class="fas fa-file-signature"></i> <?= count($vc) ?> Contract(s)
                            </button>
                        </td>
                        <td>
                            <button onclick="openVendorModal(" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; margin-right: 4px;"><i class="fas fa-edit"></i></button>
                            <form method="POST" action="controllers/delete_vendor.php" style="display:inline;" onsubmit="return confirm('Delete this vendor? All associated contracts will also be deleted.');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button type="submit" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer;"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Vendor Modal -->
<div class="modal premium-modal" id="vendorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="width: 600px; background: var(--bg-card); padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
        <h2 id="vendorModalTitle" style="margin: 0 0 24px 0; color: var(--text-heading); font-size: 22px; font-weight: 800;">Add Vendor</h2>
        <form method="POST" action="controllers/save_vendor.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="v_id" value="">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Company Name *</label>
                    <input type="text" name="company_name" id="v_company_name" required style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Service Category</label>
                    <input type="text" name="service_category" id="v_service_category" placeholder="e.g. IT Services, Janitorial" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Contact Name *</label>
                    <input type="text" name="contact_name" id="v_contact_name" required style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Tax ID / EIN</label>
                    <input type="text" name="tax_id" id="v_tax_id" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Email Address *</label>
                    <input type="email" name="email" id="v_email" required style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Phone Number</label>
                    <input type="text" name="phone" id="v_phone" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
            </div>
            
            <div style="margin-bottom: 24px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Status</label>
                <select name="status" id="v_status" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                    <option value="Active">Active</option>
                    <option value="Pending Review">Pending Review</option>
                    <option value="Terminated">Terminated</option>
                </select>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('vendorModal').style.display='none'" style="background: rgba(0,0,0,0.05); color: var(--text-heading); border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 700; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">Save Vendor</button>
            </div>
        </form>
    </div>
</div>

<!-- Contracts Modal -->
<div class="modal premium-modal" id="contractsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="width: 700px; background: var(--bg-card); padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
            <div>
                <h2 id="contractsModalTitle" style="margin: 0 0 8px 0; color: var(--text-heading); font-size: 22px; font-weight: 800;">Vendor Contracts</h2>
                <p style="margin: 0; color: var(--text-muted); font-size: 14px;">Manage service agreements and tracking.</p>
            </div>
            <button onclick="document.getElementById('contractsModal').style.display='none'" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        
        <div id="contractsList" style="margin-bottom: 30px;">
            <!-- Contracts injected here via JS -->
        </div>
        
        <hr style="border:0; border-top: 1px solid var(--border-card); margin-bottom: 24px;">
        
        <h3 style="margin: 0 0 16px 0; color: var(--text-heading); font-size: 18px; font-weight: 700;">+ Add New Contract</h3>
        <form method="POST" action="controllers/save_contract.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="vendor_id" id="c_vendor_id" value="">
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Contract Title *</label>
                <input type="text" name="contract_title" required placeholder="e.g. Master Service Agreement 2026" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Start Date</label>
                    <input type="date" name="start_date" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none; color-scheme: dark;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">End Date</label>
                    <input type="date" name="end_date" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none; color-scheme: dark;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Contract Value ($)</label>
                    <input type="number" step="0.01" name="value" placeholder="10000.00" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Status</label>
                    <select name="status" style="width:100%; padding:10px 14px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                        <option value="Active">Active</option>
                        <option value="Expired">Expired</option>
                        <option value="Pending">Pending Signature</option>
                    </select>
                </div>
            </div>
            
            <div style="text-align: right;">
                <button type="submit" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 700; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">Add Contract</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVendorModal(v = null) {
    document.getElementById('vendorModalTitle').textContent = v ? 'Edit Vendor' : 'Add New Vendor';
    document.getElementById('v_id').value = v ? v.id : '';
    document.getElementById('v_company_name').value = v ? v.company_name : '';
    document.getElementById('v_contact_name').value = v ? v.contact_name : '';
    document.getElementById('v_email').value = v ? v.email : '';
    document.getElementById('v_phone').value = v ? v.phone : '';
    document.getElementById('v_tax_id').value = v ? v.tax_id : '';
    document.getElementById('v_service_category').value = v ? v.service_category : '';
    document.getElementById('v_status').value = v ? v.status : 'Active';
    
    document.getElementById('vendorModal').style.display = 'flex';
}

function openContractsModal(vendor, contracts) {
    document.getElementById('contractsModalTitle').textContent = `Contracts: ${vendor.company_name}`;
    document.getElementById('c_vendor_id').value = vendor.id;
    
    let html = '';
    if (contracts.length === 0) {
        html = `<div style="text-align:center; padding:30px; background:rgba(0,0,0,0.02); border-radius:12px; color:var(--text-muted);">No contracts found for this vendor.</div>`;
    } else {
        contracts.forEach(c => {
            let sc = c.status === 'Active' ? '#10b981' : (c.status === 'Expired' ? '#ef4444' : '#f59e0b');
            html += `
            <div style="background:var(--bg-body); border:1px solid var(--border-card); padding:16px; border-radius:12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:700; color:var(--text-heading); font-size:15px; margin-bottom:4px;">${c.contract_title}</div>
                    <div style="font-size:13px; color:var(--text-muted);">Valid: ${c.start_date || 'N/A'} to ${c.end_date || 'N/A'} &bull; Value: $${parseFloat(c.value).toLocaleString()}</div>
                </div>
                <div>
                    <span style="background:rgba(0,0,0,0.05); color:${sc}; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700;">${c.status}</span>
                </div>
            </div>`;
        });
    }
    document.getElementById('contractsList').innerHTML = html;
    
    document.getElementById('contractsModal').style.display = 'flex';
}
</script>

<?php require_once 'includes/footer.php'; ?>
