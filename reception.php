<?php
require_once 'includes/db.php';
if (!hasPermission($pdo, 'view_reception')) {
    header("Location: dashboard.php");
    exit;
}

$allUsers = $pdo->query("SELECT id, name, login_id FROM users WHERE status != 'Terminated' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="app-header">
    <div class="app-header-left">
        <h2>🛎️ Reception Desk</h2>
        <div class="header-badge">Front Office Management</div>
    </div>
    <div class="app-header-right">
        <?php if(hasPermission($pdo, 'manage_reception')): ?>
        <button onclick="openMessageModal()" style="padding:8px 16px; background:var(--primary-color); color:white; border:none; border-radius:6px; cursor:pointer;">✉️ Take Phone Message</button>
        <?php endif; ?>
    </div>
</div>

<div class="tabs" style="margin:0 20px; border-bottom:2px solid var(--border-color); display:flex; gap:20px;">
    <div class="tab active" onclick="switchTab('dashboard')" id="tab-dashboard" style="padding:10px 5px; cursor:pointer; font-weight:bold; border-bottom:3px solid var(--primary-color);">Dashboard</div>
    <div class="tab" onclick="switchTab('visitors')" id="tab-visitors" style="padding:10px 5px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent; color:var(--text-muted);">Visitors</div>
    <div class="tab" onclick="switchTab('packages')" id="tab-packages" style="padding:10px 5px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent; color:var(--text-muted);">Packages</div>
    <div class="tab" onclick="switchTab('assets')" id="tab-assets" style="padding:10px 5px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent; color:var(--text-muted);">Key & Asset Log</div>
    <div class="tab" onclick="switchTab('directory')" id="tab-directory" style="padding:10px 5px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent; color:var(--text-muted);">Switchboard</div>
</div>

<div style="padding: 20px;">
    <!-- Dashboard Tab -->
    <div id="view-dashboard" class="tab-view">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
            <div class="stat-card" style="background:var(--bg-card); padding:20px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
                <h3 style="margin:0; color:var(--text-muted); font-size:14px;">Expected Visitors Today</h3>
                <div id="dash-expected-visitors" style="font-size:36px; font-weight:bold; color:var(--primary-color); margin-top:10px;">0</div>
            </div>
            <div class="stat-card" style="background:var(--bg-card); padding:20px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
                <h3 style="margin:0; color:var(--text-muted); font-size:14px;">Active Packages</h3>
                <div id="dash-active-packages" style="font-size:36px; font-weight:bold; color:#eab308; margin-top:10px;">0</div>
            </div>
            <div class="stat-card" style="background:var(--bg-card); padding:20px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
                <h3 style="margin:0; color:var(--text-muted); font-size:14px;">Checked-Out Assets</h3>
                <div id="dash-active-assets" style="font-size:36px; font-weight:bold; color:#ef4444; margin-top:10px;">0</div>
            </div>
        </div>
    </div>

    <!-- Visitors Tab -->
    <div id="view-visitors" class="tab-view" style="display:none;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3>Visitor Log</h3>
            <?php if(hasPermission($pdo, 'manage_reception')): ?>
            <button onclick="openVisitorModal()" class="btn-primary">Pre-Register Visitor</button>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Visitor Name</th>
                        <th>Company</th>
                        <th>Host Employee</th>
                        <th>Status</th>
                        <th>Expected Arrival</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="visitors-table-body"></tbody>
            </table>
        </div>
    </div>

    <!-- Packages Tab -->
    <div id="view-packages" class="tab-view" style="display:none;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3>Package Log</h3>
            <?php if(hasPermission($pdo, 'manage_reception')): ?>
            <button onclick="openPackageModal()" class="btn-primary">Log Incoming Package</button>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Courier</th>
                        <th>Tracking #</th>
                        <th>Status</th>
                        <th>Received At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="packages-table-body"></tbody>
            </table>
        </div>
    </div>

    <!-- Assets Tab -->
    <div id="view-assets" class="tab-view" style="display:none;">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3>Key & Asset Ledger</h3>
            <?php if(hasPermission($pdo, 'manage_reception')): ?>
            <button onclick="openAssetModal()" class="btn-primary">Checkout Asset</button>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset Name</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Checkout Time</th>
                        <th>Expected Return</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assets-table-body"></tbody>
            </table>
        </div>
    </div>

    <!-- Switchboard Tab -->
    <div id="view-directory" class="tab-view" style="display:none;">
        <div style="margin-bottom:20px; display:flex; gap:10px;">
            <input type="text" id="dirSearch" placeholder="Search employee name, department, phone..." style="flex:1; padding:10px; border-radius:8px; border:1px solid #ccc; font-size:16px;" oninput="renderDirectory()">
        </div>
        <div id="directory-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:20px;"></div>
    </div>
</div>

<!-- Modals -->

<!-- Visitor Modal -->
<div id="visitorModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:var(--bg-card); width:400px; margin:100px auto; padding:20px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3>Pre-Register Visitor</h3>
        <form id="visitorForm" onsubmit="event.preventDefault(); submitVisitor();">
            <div style="margin-bottom:15px;">
                <label>Visitor Name</label>
                <input type="text" id="v_name" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Company (Optional)</label>
                <input type="text" id="v_company" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Host Employee</label>
                <select id="v_host" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">Select Host...</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:15px;">
                <label>Expected Arrival</label>
                <input type="datetime-local" id="v_arrival" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('visitorModal').style.display='none'" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Register Visitor</button>
            </div>
        </form>
    </div>
</div>

<!-- Package Modal -->
<div id="packageModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:var(--bg-card); width:400px; margin:100px auto; padding:20px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3>Log Incoming Package</h3>
        <form id="packageForm" onsubmit="event.preventDefault(); submitPackage();">
            <div style="margin-bottom:15px;">
                <label>Recipient Employee</label>
                <select id="p_recipient" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">Select Recipient...</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:15px;">
                <label>Courier (FedEx, UPS, etc)</label>
                <input type="text" id="p_courier" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Tracking Number (Optional)</label>
                <input type="text" id="p_tracking" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('packageModal').style.display='none'" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Log & Notify</button>
            </div>
        </form>
    </div>
</div>

<!-- Asset Modal -->
<div id="assetModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:var(--bg-card); width:400px; margin:100px auto; padding:20px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3>Checkout Asset / Key</h3>
        <form id="assetForm" onsubmit="event.preventDefault(); submitAsset();">
            <div style="margin-bottom:15px;">
                <label>Asset Name (e.g. Master Key 3, Visitor Badge 12)</label>
                <input type="text" id="a_name" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Type</label>
                <select id="a_type" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <option value="key">Physical Key</option>
                    <option value="badge">Access Badge</option>
                    <option value="equipment">Equipment (Laptop/Projector)</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div style="margin-bottom:15px;">
                <label>Assigned To</label>
                <select id="a_assignee" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">Select Employee...</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:15px;">
                <label>Expected Return</label>
                <input type="datetime-local" id="a_return" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('assetModal').style.display='none'" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Checkout Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Message Modal -->
<div id="msgModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:var(--bg-card); width:400px; margin:100px auto; padding:20px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3>Take Phone Message</h3>
        <form id="msgForm" onsubmit="event.preventDefault(); submitMessage();">
            <div style="margin-bottom:15px;">
                <label>For Employee</label>
                <select id="m_recipient" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <option value="">Select Recipient...</option>
                    <?php foreach($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:15px;">
                <label>Caller Name</label>
                <input type="text" id="m_caller" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Caller Phone</label>
                <input type="text" id="m_phone" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Message</label>
                <textarea id="m_message" required rows="4" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('msgModal').style.display='none'" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Send Message to Chat</button>
            </div>
        </form>
    </div>
</div>

<script>
let state = { visitors: [], packages: [], assets: [], directory: [] };
let hasManagePerms = <?= hasPermission($pdo, 'manage_reception') ? 'true' : 'false' ?>;

function switchTab(tabId) {
    document.querySelectorAll('.tab').forEach(t => { t.style.borderBottom = '3px solid transparent'; t.style.color = 'var(--text-muted)'; });
    document.querySelectorAll('.tab-view').forEach(t => t.style.display = 'none');
    
    document.getElementById('tab-' + tabId).style.borderBottom = '3px solid var(--primary-color)';
    document.getElementById('tab-' + tabId).style.color = 'var(--text-color)';
    document.getElementById('view-' + tabId).style.display = 'block';
    
    if (tabId === 'visitors') loadVisitors();
    if (tabId === 'packages') loadPackages();
    if (tabId === 'assets') loadAssets();
    if (tabId === 'directory') loadDirectory();
    if (tabId === 'dashboard') loadDashboard();
}

// Data Loaders
function loadVisitors() {
    fetch('controllers/reception_api.php?action=get_visitors').then(r=>r.json()).then(res => {
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
    fetch('controllers/reception_api.php?action=get_visitors').then(r=>r.json()).then(res => {
        let expected = res.filter(v => v.status === 'expected').length;
        document.getElementById('dash-expected-visitors').textContent = expected;
    });
    fetch('controllers/reception_api.php?action=get_packages').then(r=>r.json()).then(res => {
        let active = res.filter(p => p.status === 'received').length;
        document.getElementById('dash-active-packages').textContent = active;
    });
    fetch('controllers/reception_api.php?action=get_assets').then(r=>r.json()).then(res => {
        let active = res.filter(a => a.status === 'checked_out').length;
        document.getElementById('dash-active-assets').textContent = active;
    });
}

// Renderers
function renderVisitors() {
    let html = '';
    state.visitors.forEach(v => {
        let statusColor = v.status === 'expected' ? 'var(--text-muted)' : (v.status === 'checked_in' ? 'var(--primary-color)' : 'var(--success-color)');
        let actions = '';
        if (hasManagePerms) {
            if (v.status === 'expected') actions = `<button onclick="updateVisitor(${v.id}, 'checkin')" style="background:var(--primary-color);color:white;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;">Check-in</button>`;
            if (v.status === 'checked_in') actions = `<button onclick="updateVisitor(${v.id}, 'checkout')" style="background:var(--success-color);color:white;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;">Check-out</button>`;
        }
        html += `<tr>
            <td style="font-weight:bold;">${v.visitor_name}</td>
            <td>${v.company || '-'}</td>
            <td>${v.host_name}</td>
            <td><span style="color:${statusColor}; font-weight:bold; text-transform:uppercase; font-size:12px;">${v.status.replace('_', ' ')}</span></td>
            <td>${v.expected_arrival || '-'}</td>
            <td>${actions}</td>
        </tr>`;
    });
    document.getElementById('visitors-table-body').innerHTML = html;
}

function renderPackages() {
    let html = '';
    state.packages.forEach(p => {
        let statusColor = p.status === 'received' ? '#eab308' : 'var(--success-color)';
        let actions = '';
        if (hasManagePerms && p.status === 'received') {
            actions = `<button onclick="updatePackage(${p.id}, 'pickup')" style="background:var(--success-color);color:white;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;">Mark Picked Up</button>`;
        }
        html += `<tr>
            <td style="font-weight:bold;">${p.recipient_name}</td>
            <td>${p.courier}</td>
            <td>${p.tracking_number || '-'}</td>
            <td><span style="color:${statusColor}; font-weight:bold; text-transform:uppercase; font-size:12px;">${p.status.replace('_', ' ')}</span></td>
            <td>${p.received_at}</td>
            <td>${actions}</td>
        </tr>`;
    });
    document.getElementById('packages-table-body').innerHTML = html;
}

function renderAssets() {
    let html = '';
    state.assets.forEach(a => {
        let statusColor = a.status === 'checked_out' ? '#ef4444' : 'var(--success-color)';
        let actions = '';
        if (hasManagePerms && a.status === 'checked_out') {
            actions = `<button onclick="updateAsset(${a.id}, 'return')" style="background:var(--success-color);color:white;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;">Mark Returned</button>`;
        }
        html += `<tr>
            <td style="font-weight:bold;">${a.asset_name}</td>
            <td style="text-transform:capitalize;">${a.asset_type}</td>
            <td>${a.assignee_name}</td>
            <td><span style="color:${statusColor}; font-weight:bold; text-transform:uppercase; font-size:12px;">${a.status.replace('_', ' ')}</span></td>
            <td>${a.checked_out_at}</td>
            <td>${a.expected_return || '-'}</td>
            <td>${actions}</td>
        </tr>`;
    });
    document.getElementById('assets-table-body').innerHTML = html;
}

function renderDirectory() {
    let q = document.getElementById('dirSearch').value.toLowerCase();
    let html = '';
    state.directory.forEach(u => {
        if (q && !u.name.toLowerCase().includes(q) && !(u.department && u.department.toLowerCase().includes(q)) && !(u.phone && u.phone.includes(q))) return;
        
        let dept = u.department || 'General';
        let desig = u.designation || u.role;
        html += `<div style="background:var(--bg-card); border-radius:12px; padding:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:10px; border-left:4px solid var(--primary-color);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h4 style="margin:0; font-size:18px;">${u.name}</h4>
                    <div style="color:var(--text-muted); font-size:13px;">${desig} &bull; ${dept}</div>
                </div>
            </div>
            <div style="font-size:14px; display:flex; flex-direction:column; gap:5px; margin-top:10px;">
                <div>📧 ${u.email}</div>
                <div>📞 ${u.phone || 'N/A'}</div>
            </div>
            ${hasManagePerms ? `<button onclick="openMessageModal(${u.id})" style="margin-top:10px; background:var(--bg-body); border:1px solid #ccc; padding:6px; border-radius:6px; cursor:pointer;">Take Message</button>` : ''}
        </div>`;
    });
    document.getElementById('directory-grid').innerHTML = html;
}

// Modals
function openVisitorModal() { document.getElementById('v_name').value=''; document.getElementById('v_company').value=''; document.getElementById('v_host').value=''; document.getElementById('v_arrival').value=''; document.getElementById('visitorModal').style.display='block'; }
function openPackageModal() { document.getElementById('p_recipient').value=''; document.getElementById('p_courier').value=''; document.getElementById('p_tracking').value=''; document.getElementById('packageModal').style.display='block'; }
function openAssetModal() { document.getElementById('a_name').value=''; document.getElementById('a_assignee').value=''; document.getElementById('a_return').value=''; document.getElementById('assetModal').style.display='block'; }
function openMessageModal(recipientId = '') { 
    document.getElementById('m_recipient').value = recipientId;
    document.getElementById('m_caller').value=''; document.getElementById('m_phone').value=''; document.getElementById('m_message').value=''; 
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
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { document.getElementById('visitorModal').style.display='none'; loadVisitors(); loadDashboard(); } else alert(res.message);
    });
}

function submitPackage() {
    let fd = new FormData();
    fd.append('action', 'log_package');
    fd.append('recipient_id', document.getElementById('p_recipient').value);
    fd.append('courier', document.getElementById('p_courier').value);
    fd.append('tracking_number', document.getElementById('p_tracking').value);
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
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { document.getElementById('assetModal').style.display='none'; loadAssets(); loadDashboard(); } else alert(res.message);
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

function updateAsset(id, act) {
    let fd = new FormData();
    fd.append('action', 'return_asset');
    fd.append('id', id);
    fetch('controllers/reception_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { loadAssets(); loadDashboard(); } else alert(res.message);
    });
}

// Init
loadDashboard();
</script>

<?php require_once 'includes/footer.php'; ?>
