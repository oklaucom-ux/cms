<?php
require_once 'includes/db.php';
if (!hasPermission($pdo, 'view_reception')) {
    header("Location: dashboard.php");
    exit;
}

// Auto-migrate visitors table
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS visitors (
        id {$pkDef},
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        email VARCHAR(255),
        company VARCHAR(255),
        host_id VARCHAR(255),
        purpose VARCHAR(255),
        status VARCHAR(50) DEFAULT 'Checked In',
        badge_number VARCHAR(100),
        check_in_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        check_out_time DATETIME
    )");
} catch (Exception $e) {}

try {
    $allUsers = $pdo->query("SELECT id, name, login_id FROM users WHERE status != 'Terminated' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $allUsers = []; }

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
/* Premium Reception Styling */
:root {
    --primary-reception: #3b82f6;
    --primary-reception-dark: #2563eb;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.reception-header {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    padding: 30px;
    border-radius: 16px;
    color: white;
    margin: 20px;
    box-shadow: var(--card-shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reception-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.reception-header p {
    margin: 5px 0 0 0;
    color: #94a3b8;
    font-size: 15px;
}

.metric-card {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 20px;
}
.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--hover-shadow);
}
.metric-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}
.metric-content h3 {
    margin: 0;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
}
.metric-content .value {
    font-size: 36px;
    font-weight: 800;
    margin-top: 5px;
    line-height: 1;
}

.modern-tabs {
    display: flex;
    gap: 15px;
    margin: 0 20px 20px 20px;
    border-bottom: 2px solid rgba(0,0,0,0.05);
    padding-bottom: 0;
}
.modern-tab {
    padding: 12px 24px;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    margin-bottom: -2px;
}
.modern-tab:hover {
    color: var(--text-color);
}
.modern-tab.active {
    color: var(--primary-reception);
    border-bottom-color: var(--primary-reception);
}

.data-table-container {
    background: var(--bg-card);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    margin: 0 20px 20px 20px;
    overflow: hidden;
}
.data-table-header {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th, .data-table td {
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.data-table th {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(0,0,0,0.02);
}
.data-table tbody tr:hover {
    background: rgba(0,0,0,0.01);
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}
.status-expected { background: #f1f5f9; color: #475569; }
.status-checked-in { background: #dbeafe; color: #1d4ed8; }
.status-checked-out { background: #dcfce7; color: #15803d; }
.status-received { background: #fef3c7; color: #b45309; }
.status-picked-up { background: #dcfce7; color: #15803d; }
.status-returned { background: #dcfce7; color: #15803d; }

.modern-input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.modern-input:focus {
    outline: none;
    border-color: var(--primary-reception);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.action-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-blue { background: var(--primary-reception); color: white; }
.btn-blue:hover { background: var(--primary-reception-dark); }
.btn-green { background: #10b981; color: white; }
.btn-green:hover { background: #059669; }

.two-col-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group.full-width {
    grid-column: span 2;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--text-color);
    font-size: 13px;
}
</style>

<div class="main-content">
    
    <div class="reception-header">
        <div>
            <h2>🛎️ Reception & Front Office</h2>
            <p>Manage visitors, packages, assets, and switchboard communications</p>
        </div>
        <?php if(hasPermission($pdo, 'manage_reception')): ?>
        <div>
            <button onclick="openMessageModal()" class="action-btn" style="background: rgba(255,255,255,0.1); color:white; border: 1px solid rgba(255,255,255,0.2); font-size: 15px; padding: 10px 20px;">
                ✉️ Take Phone Message
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="modern-tabs">
        <div class="modern-tab active" onclick="switchTab('dashboard')" id="tab-dashboard">Dashboard</div>
        <div class="modern-tab" onclick="switchTab('visitors')" id="tab-visitors">Visitors Log</div>
        <div class="modern-tab" onclick="switchTab('packages')" id="tab-packages">Package Delivery</div>
        <div class="modern-tab" onclick="switchTab('assets')" id="tab-assets">Key & Asset Ledger</div>
        <div class="modern-tab" onclick="switchTab('directory')" id="tab-directory">Switchboard Directory</div>
    </div>

    <!-- DASHBOARD VIEW -->
    <div id="view-dashboard" class="tab-view">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px; padding: 0 20px 20px 20px;">
            <div class="metric-card">
                <div class="metric-icon" style="background:#eff6ff; color:#3b82f6;">👥</div>
                <div class="metric-content">
                    <h3>Expected Visitors</h3>
                    <div class="value" id="dash-expected-visitors" style="color:#1e293b;">0</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon" style="background:#fffbeb; color:#f59e0b;">📦</div>
                <div class="metric-content">
                    <h3>Pending Packages</h3>
                    <div class="value" id="dash-active-packages" style="color:#1e293b;">0</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon" style="background:#fef2f2; color:#ef4444;">🔑</div>
                <div class="metric-content">
                    <h3>Assets Checked Out</h3>
                    <div class="value" id="dash-active-assets" style="color:#1e293b;">0</div>
                </div>
            </div>
        </div>

        <div class="data-table-container" style="margin:0 20px;">
            <div class="data-table-header">
                <h3 style="margin:0; font-size:18px;">⚡ Recent Reception Activity</h3>
            </div>
            <div style="padding:20px;" id="activity-feed">
                <!-- Populated via JS -->
                <div style="text-align:center; color:var(--text-muted); padding:20px;">Loading activity...</div>
            </div>
        </div>
    </div>

    <!-- VISITORS VIEW -->
    <div id="view-visitors" class="tab-view" style="display:none;">
        <div class="data-table-container">
            <div class="data-table-header">
                <div style="display:flex; align-items:center; gap:15px;">
                    <h3 style="margin:0;">Visitor Log</h3>
                    <select id="visitorFilter" onchange="loadVisitors()" class="modern-input" style="width:150px; padding:6px;">
                        <option value="today">Today's Visitors</option>
                        <option value="all">All History</option>
                    </select>
                </div>
                <?php if(hasPermission($pdo, 'manage_reception')): ?>
                <div style="display:flex; gap:10px;">
                    <button onclick="openWalkinModal()" class="action-btn btn-green">🏃 Walk-in Check-in</button>
                    <button onclick="openVisitorModal()" class="action-btn btn-blue">📅 Pre-Register Visitor</button>
                </div>
                <?php endif; ?>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visitor Details</th>
                            <th>Host / Purpose</th>
                            <th>Vehicle / NDA</th>
                            <th>Status</th>
                            <th>Timing</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="visitors-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PACKAGES VIEW -->
    <div id="view-packages" class="tab-view" style="display:none;">
        <div class="data-table-container">
            <div class="data-table-header">
                <h3 style="margin:0;">Package & Delivery Log</h3>
                <?php if(hasPermission($pdo, 'manage_reception')): ?>
                <button onclick="openPackageModal()" class="action-btn btn-blue">📦 Log Incoming Package</button>
                <?php endif; ?>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Sender Details</th>
                            <th>Courier Info</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Received At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="packages-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ASSETS VIEW -->
    <div id="view-assets" class="tab-view" style="display:none;">
        <div class="data-table-container">
            <div class="data-table-header">
                <h3 style="margin:0;">Key & Asset Ledger</h3>
                <?php if(hasPermission($pdo, 'manage_reception')): ?>
                <button onclick="openAssetModal()" class="action-btn btn-blue">🔑 Checkout Asset</button>
                <?php endif; ?>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset Details</th>
                            <th>Assigned To</th>
                            <th>Condition (Out)</th>
                            <th>Status</th>
                            <th>Checkout/Return</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assets-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SWITCHBOARD VIEW -->
    <div id="view-directory" class="tab-view" style="display:none; padding: 0 20px;">
        <div style="margin-bottom:20px; max-width:600px;">
            <input type="text" id="dirSearch" placeholder="🔍 Search employee name, department, phone..." class="modern-input" style="padding:15px; font-size:16px;" oninput="renderDirectory()">
        </div>
        <div id="directory-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:20px;"></div>
    </div>

</div>

<!-- MODALS -->

<!-- Pre-Register Visitor Modal -->
<div id="visitorModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px);">
    <div style="width:600px; margin:50px auto; background:white; border-radius:16px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; font-size:20px; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">📅 Pre-Register Visitor</h3>
        <form id="visitorForm" onsubmit="event.preventDefault(); submitVisitor();">
            <div class="two-col-form">
                <div class="form-group">
                    <label>Visitor Name *</label>
                    <input type="text" id="v_name" required class="modern-input">
                </div>
                <div class="form-group">
                    <label>Company (Optional)</label>
                    <input type="text" id="v_company" class="modern-input">
                </div>
                <div class="form-group">
                    <label>Host Employee *</label>
                    <select id="v_host" required class="modern-input">
                        <option value="">Select Host...</option>
                        <?php foreach($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expected Arrival *</label>
                    <input type="datetime-local" id="v_arrival" required class="modern-input">
                </div>
                <div class="form-group full-width">
                    <label>Purpose of Visit</label>
                    <input type="text" id="v_purpose" class="modern-input" placeholder="e.g. Interview, Vendor Meeting">
                </div>
                <div class="form-group">
                    <label>Vehicle Reg Number (Parking)</label>
                    <input type="text" id="v_vehicle" class="modern-input" placeholder="e.g. DL 1C AB 1234">
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:25px;">
                    <input type="checkbox" id="v_nda" value="1" style="width:20px; height:20px;">
                    <label style="margin:0;">NDA / Policy Agreement Signed</label>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" onclick="document.getElementById('visitorModal').style.display='none'" class="action-btn" style="background:#f1f5f9; color:#475569;">Cancel</button>
                <button type="submit" class="action-btn btn-blue">Register Visitor</button>
            </div>
        </form>
    </div>
</div>

<!-- Walk-in Visitor Modal -->
<div id="walkinModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px);">
    <div style="width:600px; margin:50px auto; background:white; border-radius:16px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; font-size:20px; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">🏃 Log Walk-in Visitor</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Immediately checks in the visitor and notifies the host.</p>
        <form id="walkinForm" onsubmit="event.preventDefault(); submitWalkin();">
            <div class="two-col-form">
                <div class="form-group">
                    <label>Visitor Name *</label>
                    <input type="text" id="w_name" required class="modern-input">
                </div>
                <div class="form-group">
                    <label>Company (Optional)</label>
                    <input type="text" id="w_company" class="modern-input">
                </div>
                <div class="form-group">
                    <label>Host Employee *</label>
                    <select id="w_host" required class="modern-input">
                        <option value="">Select Host...</option>
                        <?php foreach($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Purpose of Visit</label>
                    <input type="text" id="w_purpose" class="modern-input">
                </div>
                <div class="form-group">
                    <label>Vehicle Reg Number</label>
                    <input type="text" id="w_vehicle" class="modern-input">
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:25px;">
                    <input type="checkbox" id="w_nda" value="1" style="width:20px; height:20px;">
                    <label style="margin:0;">NDA / Policy Agreement Signed</label>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" onclick="document.getElementById('walkinModal').style.display='none'" class="action-btn" style="background:#f1f5f9; color:#475569;">Cancel</button>
                <button type="submit" class="action-btn btn-green">Check In Now</button>
            </div>
        </form>
    </div>
</div>

<!-- Package Modal -->
<div id="packageModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px);">
    <div style="width:500px; margin:50px auto; background:white; border-radius:16px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; font-size:20px; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">📦 Log Incoming Package</h3>
        <form id="packageForm" onsubmit="event.preventDefault(); submitPackage();">
            <div class="form-group">
                <label>Recipient Employee *</label>
                <select id="p_recipient" required class="modern-input">
                    <option value="">Select Recipient...</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="two-col-form">
                <div class="form-group">
                    <label>Sender Name</label>
                    <input type="text" id="p_sender" class="modern-input">
                </div>
                <div class="form-group">
                    <label>Sender Company</label>
                    <input type="text" id="p_sender_company" class="modern-input">
                </div>
                <div class="form-group">
                    <label>Courier *</label>
                    <input type="text" id="p_courier" required class="modern-input" placeholder="FedEx, UPS, Amazon...">
                </div>
                <div class="form-group">
                    <label>Package Type</label>
                    <select id="p_type" class="modern-input">
                        <option value="Envelope">Envelope/Document</option>
                        <option value="Box" selected>Box/Parcel</option>
                        <option value="Tube">Tube</option>
                        <option value="Pallet">Pallet/Freight</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Tracking Number (Optional)</label>
                <input type="text" id="p_tracking" class="modern-input">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" onclick="document.getElementById('packageModal').style.display='none'" class="action-btn" style="background:#f1f5f9; color:#475569;">Cancel</button>
                <button type="submit" class="action-btn btn-blue">Log & Notify</button>
            </div>
        </form>
    </div>
</div>

<!-- Asset Modal -->
<div id="assetModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px);">
    <div style="width:500px; margin:50px auto; background:white; border-radius:16px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; font-size:20px; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">🔑 Checkout Asset / Key</h3>
        <form id="assetForm" onsubmit="event.preventDefault(); submitAsset();">
            <div class="form-group">
                <label>Asset Name *</label>
                <input type="text" id="a_name" required class="modern-input" placeholder="e.g. Master Key 3, Visitor Badge 12">
            </div>
            <div class="two-col-form">
                <div class="form-group">
                    <label>Asset Type</label>
                    <select id="a_type" class="modern-input">
                        <option value="key">Physical Key</option>
                        <option value="badge">Access Badge</option>
                        <option value="equipment">Equipment (Laptop/Projector)</option>
                        <option value="vehicle">Company Vehicle</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assigned To *</label>
                    <select id="a_assignee" required class="modern-input">
                        <option value="">Select Employee...</option>
                        <?php foreach($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Expected Return Date/Time</label>
                <input type="datetime-local" id="a_return" class="modern-input">
            </div>
            <div class="form-group">
                <label>Condition at Checkout (Notes)</label>
                <textarea id="a_condition_out" rows="2" class="modern-input" placeholder="Any damages or notes before handover?"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" onclick="document.getElementById('assetModal').style.display='none'" class="action-btn" style="background:#f1f5f9; color:#475569;">Cancel</button>
                <button type="submit" class="action-btn btn-blue">Checkout Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Return Asset Modal -->
<div id="returnAssetModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px);">
    <div style="width:400px; margin:50px auto; background:white; border-radius:16px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; font-size:20px; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">📥 Return Asset</h3>
        <form id="returnAssetForm" onsubmit="event.preventDefault(); submitReturnAsset();">
            <input type="hidden" id="ra_id">
            <div class="form-group">
                <label>Condition at Return</label>
                <textarea id="ra_condition_in" rows="2" class="modern-input" placeholder="Any damages or issues?"></textarea>
            </div>
            <div class="form-group">
                <label>Additional Notes</label>
                <textarea id="ra_notes" rows="2" class="modern-input" placeholder="Optional notes..."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" onclick="document.getElementById('returnAssetModal').style.display='none'" class="action-btn" style="background:#f1f5f9; color:#475569;">Cancel</button>
                <button type="submit" class="action-btn btn-green">Confirm Return</button>
            </div>
        </form>
    </div>
</div>

<!-- Message Modal -->
<div id="msgModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px);">
    <div style="width:400px; margin:50px auto; background:white; border-radius:16px; padding:30px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; font-size:20px; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">✉️ Take Phone Message</h3>
        <form id="msgForm" onsubmit="event.preventDefault(); submitMessage();">
            <div class="form-group">
                <label>For Employee *</label>
                <select id="m_recipient" required class="modern-input">
                    <option value="">Select Recipient...</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Caller Name *</label>
                <input type="text" id="m_caller" required class="modern-input">
            </div>
            <div class="form-group">
                <label>Caller Phone</label>
                <input type="text" id="m_phone" class="modern-input">
            </div>
            <div class="form-group">
                <label>Message *</label>
                <textarea id="m_message" required rows="4" class="modern-input"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" onclick="document.getElementById('msgModal').style.display='none'" class="action-btn" style="background:#f1f5f9; color:#475569;">Cancel</button>
                <button type="submit" class="action-btn btn-blue">Send to Chat</button>
            </div>
        </form>
    </div>
</div>

<script>
let state = { visitors: [], packages: [], assets: [], directory: [] };
let hasManagePerms = <?= hasPermission($pdo, 'manage_reception') ? 'true' : 'false' ?>;

function switchTab(tabId) {
    document.querySelectorAll('.modern-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-view').forEach(t => t.style.display = 'none');
    
    document.getElementById('tab-' + tabId).classList.add('active');
    document.getElementById('view-' + tabId).style.display = 'block';
    
    if (tabId === 'visitors') loadVisitors();
    if (tabId === 'packages') loadPackages();
    if (tabId === 'assets') loadAssets();
    if (tabId === 'directory') loadDirectory();
    if (tabId === 'dashboard') loadDashboard();
}

// Data Loaders
function loadVisitors() {
    let filter = document.getElementById('visitorFilter') ? document.getElementById('visitorFilter').value : 'today';
    fetch('controllers/reception_api.php?action=get_visitors&filter=' + filter).then(r=>r.json()).then(res => {
        state.visitors = res;
        renderVisitors();
    });
}
function loadPackages() {
    fetch('controllers/reception_api.php?action=get_packages').then(r=>r.json()).then(res => {
        state.packages = res;
        renderPackages();
    });
}
function loadAssets() {
    fetch('controllers/reception_api.php?action=get_assets').then(r=>r.json()).then(res => {
        state.assets = res;
        renderAssets();
    });
}
function loadDirectory() {
    if(state.directory.length === 0) {
        fetch('controllers/reception_api.php?action=get_directory').then(r=>r.json()).then(res => {
            state.directory = res;
            renderDirectory();
        });
    } else renderDirectory();
}
function loadDashboard() {
    let activityHTML = '';
    
    Promise.all([
        fetch('controllers/reception_api.php?action=get_visitors&filter=today').then(r=>r.json()),
        fetch('controllers/reception_api.php?action=get_packages').then(r=>r.json()),
        fetch('controllers/reception_api.php?action=get_assets').then(r=>r.json())
    ]).then(([visitors, packages, assets]) => {
        
        let expected = visitors.filter(v => v.status === 'expected').length;
        document.getElementById('dash-expected-visitors').textContent = expected;
        
        let activePkg = packages.filter(p => p.status === 'received').length;
        document.getElementById('dash-active-packages').textContent = activePkg;
        
        let activeAst = assets.filter(a => a.status === 'checked_out').length;
        document.getElementById('dash-active-assets').textContent = activeAst;
        
        // Build Activity Feed
        let activities = [];
        visitors.forEach(v => {
            if(v.checked_in_at) activities.push({ type: 'visitor_in', time: new Date(v.checked_in_at), text: `Visitor <b>${v.visitor_name}</b> checked in to see ${v.host_name}.` });
            if(v.checked_out_at) activities.push({ type: 'visitor_out', time: new Date(v.checked_out_at), text: `Visitor <b>${v.visitor_name}</b> checked out.` });
        });
        packages.forEach(p => {
            activities.push({ type: 'package_in', time: new Date(p.received_at), text: `Package received for <b>${p.recipient_name}</b> via ${p.courier}.` });
            if(p.picked_up_at) activities.push({ type: 'package_out', time: new Date(p.picked_up_at), text: `Package picked up by <b>${p.recipient_name}</b>.` });
        });
        assets.forEach(a => {
            activities.push({ type: 'asset_out', time: new Date(a.checked_out_at), text: `Asset <b>${a.asset_name}</b> checked out to ${a.assignee_name}.` });
            if(a.returned_at) activities.push({ type: 'asset_in', time: new Date(a.returned_at), text: `Asset <b>${a.asset_name}</b> returned by ${a.assignee_name}.` });
        });
        
        activities.sort((a,b) => b.time - a.time);
        
        if (activities.length === 0) {
            activityHTML = '<div style="text-align:center; color:var(--text-muted); padding:30px;">No recent activity to show.</div>';
        } else {
            activityHTML = '<div style="display:flex; flex-direction:column; gap:15px;">';
            activities.slice(0, 10).forEach(act => {
                let icon = '📌';
                if(act.type.includes('visitor')) icon = '👤';
                if(act.type.includes('package')) icon = '📦';
                if(act.type.includes('asset')) icon = '🔑';
                
                activityHTML += `<div style="display:flex; align-items:flex-start; gap:15px; padding:15px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--primary-reception);">
                    <div style="font-size:20px; background:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.05);">${icon}</div>
                    <div>
                        <div style="font-size:14px; color:var(--text-color);">${act.text}</div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">${act.time.toLocaleString()}</div>
                    </div>
                </div>`;
            });
            activityHTML += '</div>';
        }
        
        document.getElementById('activity-feed').innerHTML = activityHTML;
    });
}

// Renderers
function renderVisitors() {
    let html = '';
    if (state.visitors.length === 0) {
        html = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No visitors found.</td></tr>`;
    } else {
        state.visitors.forEach(v => {
            let statusClass = 'status-expected';
            if (v.status === 'checked_in') statusClass = 'status-checked-in';
            if (v.status === 'checked_out') statusClass = 'status-checked-out';
            
            let actions = '';
            if (hasManagePerms) {
                if (v.status === 'expected') actions = `<button onclick="updateVisitor(${v.id}, 'checkin')" class="action-btn btn-blue" style="padding:6px 12px;">Check-in</button>`;
                if (v.status === 'checked_in') actions = `<button onclick="updateVisitor(${v.id}, 'checkout')" class="action-btn" style="background:#f59e0b; color:white; padding:6px 12px;">Check-out</button>`;
            }
            
            let ndaBadge = v.is_nda_signed == 1 ? '<span style="color:#10b981; font-size:12px; font-weight:bold;">NDA Signed ✓</span>' : '';
            let vehicle = v.vehicle_reg ? `<div style="font-size:12px; color:var(--text-muted);">🚗 ${v.vehicle_reg}</div>` : '';
            
            html += `<tr>
                <td>
                    <div style="font-weight:600; color:var(--text-color);">${v.visitor_name}</div>
                    <div style="font-size:12px; color:var(--text-muted);">${v.company || 'Personal'}</div>
                </td>
                <td>
                    <div style="font-weight:500;">${v.host_name}</div>
                    <div style="font-size:12px; color:var(--text-muted);">${v.purpose || '-'}</div>
                </td>
                <td>
                    ${vehicle}
                    ${ndaBadge}
                </td>
                <td><span class="status-badge ${statusClass}">${v.status.replace('_', ' ')}</span></td>
                <td style="font-size:13px; color:var(--text-muted);">
                    <div>Expected: ${v.expected_arrival || '-'}</div>
                    ${v.checked_in_at ? `<div>In: ${v.checked_in_at}</div>` : ''}
                </td>
                <td>${actions}</td>
            </tr>`;
        });
    }
    document.getElementById('visitors-table-body').innerHTML = html;
}

function renderPackages() {
    let html = '';
    if (state.packages.length === 0) {
        html = `<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No packages found.</td></tr>`;
    } else {
        state.packages.forEach(p => {
            let statusClass = p.status === 'received' ? 'status-received' : 'status-picked-up';
            let actions = '';
            if (hasManagePerms && p.status === 'received') {
                actions = `<button onclick="updatePackage(${p.id}, 'pickup')" class="action-btn btn-green" style="padding:6px 12px;">Mark Picked Up</button>`;
            }
            html += `<tr>
                <td style="font-weight:600; color:var(--text-color);">${p.recipient_name}</td>
                <td>
                    <div style="font-weight:500;">${p.sender_name || 'Unknown'}</div>
                    <div style="font-size:12px; color:var(--text-muted);">${p.sender_company || '-'}</div>
                </td>
                <td>
                    <div style="font-weight:500;">${p.courier}</div>
                    <div style="font-size:12px; color:var(--text-muted);">${p.tracking_number || '-'}</div>
                </td>
                <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">${p.package_type || 'Box'}</span></td>
                <td><span class="status-badge ${statusClass}">${p.status.replace('_', ' ')}</span></td>
                <td style="font-size:13px; color:var(--text-muted);">${p.received_at}</td>
                <td>${actions}</td>
            </tr>`;
        });
    }
    document.getElementById('packages-table-body').innerHTML = html;
}

function renderAssets() {
    let html = '';
    if (state.assets.length === 0) {
        html = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No assets checked out.</td></tr>`;
    } else {
        state.assets.forEach(a => {
            let statusClass = a.status === 'checked_out' ? 'status-received' : 'status-returned';
            let actions = '';
            if (hasManagePerms && a.status === 'checked_out') {
                actions = `<button onclick="openReturnModal(${a.id})" class="action-btn btn-green" style="padding:6px 12px;">Return</button>`;
            }
            html += `<tr>
                <td>
                    <div style="font-weight:600; color:var(--text-color);">${a.asset_name}</div>
                    <div style="font-size:12px; color:var(--text-muted); text-transform:capitalize;">${a.asset_type}</div>
                </td>
                <td style="font-weight:500;">${a.assignee_name}</td>
                <td style="font-size:12px; color:var(--text-muted); max-width:200px;">${a.condition_out || '-'}</td>
                <td><span class="status-badge ${statusClass}">${a.status.replace('_', ' ')}</span></td>
                <td style="font-size:13px; color:var(--text-muted);">
                    <div>Out: ${a.checked_out_at}</div>
                    ${a.expected_return ? `<div>Exp: ${a.expected_return}</div>` : ''}
                </td>
                <td>${actions}</td>
            </tr>`;
        });
    }
    document.getElementById('assets-table-body').innerHTML = html;
}

function renderDirectory() {
    let q = document.getElementById('dirSearch').value.toLowerCase();
    let html = '';
    state.directory.forEach(u => {
        if (q && !u.name.toLowerCase().includes(q) && !(u.department && u.department.toLowerCase().includes(q)) && !(u.phone && u.phone.includes(q))) return;
        
        let dept = u.department || 'General';
        let desig = u.designation || u.role;
        html += `<div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:var(--card-shadow); display:flex; flex-direction:column; gap:10px; border-left:4px solid var(--primary-reception);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h4 style="margin:0; font-size:18px;">${u.name}</h4>
                    <div style="color:var(--text-muted); font-size:13px; font-weight:500;">${desig} &bull; ${dept}</div>
                </div>
            </div>
            <div style="font-size:14px; display:flex; flex-direction:column; gap:8px; margin-top:10px; padding-top:10px; border-top:1px solid rgba(0,0,0,0.05);">
                <div style="display:flex; align-items:center; gap:8px;">📧 <a href="mailto:${u.email}" style="color:var(--text-color); text-decoration:none;">${u.email}</a></div>
                <div style="display:flex; align-items:center; gap:8px;">📞 ${u.phone || '<span style="color:var(--text-muted);">Not provided</span>'}</div>
            </div>
            ${hasManagePerms ? `<button onclick="openMessageModal(${u.id})" class="action-btn" style="margin-top:15px; background:rgba(0,0,0,0.03); color:var(--text-color); width:100%;">✉️ Take Message</button>` : ''}
        </div>`;
    });
    if (html === '') html = '<div style="grid-column:1/-1; padding:30px; text-align:center; color:var(--text-muted);">No employees found matching your search.</div>';
    document.getElementById('directory-grid').innerHTML = html;
}

// Modals
function openVisitorModal() { document.getElementById('visitorForm').reset(); document.getElementById('visitorModal').style.display = 'block'; }
function openWalkinModal() { document.getElementById('walkinForm').reset(); document.getElementById('walkinModal').style.display = 'block'; }
function openPackageModal() { document.getElementById('packageForm').reset(); document.getElementById('packageModal').style.display='block'; }
function openAssetModal() { document.getElementById('assetForm').reset(); document.getElementById('assetModal').style.display='block'; }
function openReturnModal(id) {
    document.getElementById('returnAssetForm').reset();
    document.getElementById('ra_id').value = id;
    document.getElementById('returnAssetModal').style.display = 'block';
}
function openMessageModal(recipientId = '') { 
    document.getElementById('msgForm').reset();
    document.getElementById('m_recipient').value = recipientId;
    document.getElementById('msgModal').style.display='block'; 
}

// Submissions
function submitVisitor() {
    let fd = new FormData();
    fd.append('action', 'register_visitor');
    fd.append('visitor_name', document.getElementById('v_name').value);
    fd.append('company', document.getElementById('v_company').value);
    fd.append('host_id', document.getElementById('v_host').value);
    fd.append('expected_arrival', document.getElementById('v_arrival').value);
    fd.append('purpose', document.getElementById('v_purpose').value);
    fd.append('vehicle_reg', document.getElementById('v_vehicle').value);
    if(document.getElementById('v_nda').checked) fd.append('is_nda_signed', '1');
    
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') {
            document.getElementById('visitorModal').style.display='none';
            loadVisitors(); loadDashboard();
        } else alert(res.message);
    });
}

function submitWalkin() {
    let fd = new FormData();
    fd.append('action', 'register_walkin_visitor');
    fd.append('visitor_name', document.getElementById('w_name').value);
    fd.append('company', document.getElementById('w_company').value);
    fd.append('host_id', document.getElementById('w_host').value);
    fd.append('purpose', document.getElementById('w_purpose').value);
    fd.append('vehicle_reg', document.getElementById('w_vehicle').value);
    if(document.getElementById('w_nda').checked) fd.append('is_nda_signed', '1');
    
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') {
            document.getElementById('walkinModal').style.display='none';
            loadVisitors(); loadDashboard();
        } else alert(res.message);
    });
}

function submitPackage() {
    let fd = new FormData();
    fd.append('action', 'log_package');
    fd.append('recipient_id', document.getElementById('p_recipient').value);
    fd.append('courier', document.getElementById('p_courier').value);
    fd.append('tracking_number', document.getElementById('p_tracking').value);
    fd.append('sender_name', document.getElementById('p_sender').value);
    fd.append('sender_company', document.getElementById('p_sender_company').value);
    fd.append('package_type', document.getElementById('p_type').value);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { document.getElementById('packageModal').style.display='none'; loadPackages(); loadDashboard(); } else alert(res.message);
    });
}

function submitAsset() {
    let fd = new FormData();
    fd.append('action', 'checkout_asset');
    fd.append('asset_name', document.getElementById('a_name').value);
    fd.append('asset_type', document.getElementById('a_type').value);
    fd.append('assigned_to', document.getElementById('a_assignee').value);
    fd.append('expected_return', document.getElementById('a_return').value);
    fd.append('condition_out', document.getElementById('a_condition_out').value);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { document.getElementById('assetModal').style.display='none'; loadAssets(); loadDashboard(); } else alert(res.message);
    });
}

function submitReturnAsset() {
    let fd = new FormData();
    fd.append('action', 'return_asset');
    fd.append('id', document.getElementById('ra_id').value);
    fd.append('condition_in', document.getElementById('ra_condition_in').value);
    fd.append('notes', document.getElementById('ra_notes').value);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { document.getElementById('returnAssetModal').style.display='none'; loadAssets(); loadDashboard(); } else alert(res.message);
    });
}

function submitMessage() {
    let fd = new FormData();
    fd.append('action', 'take_message');
    fd.append('recipient_id', document.getElementById('m_recipient').value);
    fd.append('caller_name', document.getElementById('m_caller').value);
    fd.append('phone', document.getElementById('m_phone').value);
    fd.append('message', document.getElementById('m_message').value);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { document.getElementById('msgModal').style.display='none'; alert("Message sent to employee's chat."); } else alert(res.message);
    });
}

// Updaters
function updateVisitor(id, act) {
    let fd = new FormData();
    fd.append('action', act === 'checkin' ? 'checkin_visitor' : 'checkout_visitor');
    fd.append('id', id);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { loadVisitors(); loadDashboard(); } else alert(res.message);
    });
}

function updatePackage(id, act) {
    let fd = new FormData();
    fd.append('action', 'pickup_package');
    fd.append('id', id);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { loadPackages(); loadDashboard(); } else alert(res.message);
    });
}

// Init
loadDashboard();
</script>
<?php require_once 'includes/footer.php'; ?>
