<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Block external roles and unauthorized users
requirePermission($pdo, 'view_users');

require_once 'includes/sidebar.php';

$canCreateUsers = hasPermission($pdo, 'create_users');
$canEditUsers   = hasPermission($pdo, 'edit_users');
$canDeleteUsers = hasPermission($pdo, 'delete_users');
$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

// Auto Migrate
$pdo->exec("CREATE TABLE IF NOT EXISTS user_documents (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id TEXT, file_name TEXT, file_path TEXT, category TEXT, uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
try { $pdo->exec("ALTER TABLE users ADD COLUMN manager_id VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}

$all_users = $pdo->query("SELECT login_id, name, role FROM users")->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="content-section active">
    <?php if(!empty($_SESSION['flash_error'])): ?>
    <div style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; border-radius:10px; padding:14px 18px; margin-bottom:20px; font-weight:600; font-size:14px;"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>
    <div class="section-header">
        <h2>👥 User Management</h2>
        <div style="display:flex;gap:10px;">
            <?php if($canCreateUsers): ?>
            <button class="view-button" onclick="document.getElementById('csvImportModal').style.display='block'" style="background:var(--bg-card);border:1px solid var(--border-card);color:var(--text-body);padding:10px 18px;border-radius:10px;font-weight:600;cursor:pointer;">📥 Import CSV</button>
            <button class="add-button" onclick="openUserModal()">+ Add User</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- CSV Import Modal -->
    <div id="csvImportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:none;align-items:center;justify-content:center;">
        <div style="background:var(--bg-card);border-radius:20px;padding:36px;width:500px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.3);position:relative;">
            <button onclick="document.getElementById('csvImportModal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);">×</button>
            <h3 style="color:var(--text-heading);margin-bottom:8px;font-size:20px;font-weight:700;">📥 Bulk Import Users</h3>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;line-height:1.6;">Upload a CSV file with columns: <code style="background:var(--bg-body);padding:2px 6px;border-radius:4px;">login_id, name, email, role, department, password</code><br>Rows with existing login_id or email will be skipped. Default password is <code style="background:var(--bg-body);padding:2px 6px;border-radius:4px;">changeme123</code> if not specified.</p>
            <form method="POST" action="controllers/import_users_csv.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div style="border:2px dashed var(--input-border);border-radius:12px;padding:32px;text-align:center;margin-bottom:16px;cursor:pointer;" onclick="document.getElementById('csvFile').click()">
                    <div style="font-size:32px;margin-bottom:8px;">📂</div>
                    <div style="font-size:14px;color:var(--text-muted);" id="csvFileName">Click to select CSV file</div>
                    <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display:none" onchange="document.getElementById('csvFileName').textContent=this.files[0]?.name||'No file selected'">
                </div>
                <a href="#" onclick="downloadTemplate()" style="font-size:12px;color:#6366f1;text-decoration:none;display:block;margin-bottom:16px;">⬇ Download CSV template</a>
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="document.getElementById('csvImportModal').style.display='none'" style="flex:1;padding:12px;border-radius:10px;border:1px solid var(--border-card);background:transparent;color:var(--text-body);cursor:pointer;font-weight:600;">Cancel</button>
                    <button type="submit" style="flex:1;padding:12px;border-radius:10px;border:none;background:#6366f1;color:white;cursor:pointer;font-weight:700;">Import Users</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function downloadTemplate() {
        const csv = 'login_id,name,email,role,department,password\njohn.doe,John Doe,john@company.com,Employee,Engineering,mypassword123\njane.smith,Jane Smith,jane@company.com,Manager,HR,';
        const a = document.createElement('a');
        a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
        a.download = 'user_import_template.csv';
        a.click();
    }
    // Fix modal display on button click
    document.querySelector('[onclick*="csvImportModal"]')?.addEventListener('click', ()=>{ document.getElementById('csvImportModal').style.display='flex'; });
    </script>
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Login ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Department</th>
                    <th>Manager</th>
                    <th>Status</th>
                    <?php if($canEditUsers || $canDeleteUsers): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $userQuery = "SELECT u.*, m.name as manager_name 
                              FROM users u 
                              LEFT JOIN users m ON u.manager_id = m.login_id";
                if ($_SESSION['role'] !== 'Super Admin') {
                    $userQuery .= " WHERE u.role != 'Super Admin'";
                }
                foreach($pdo->query($userQuery) as $row): 
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['login_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['role'] ?? '') ?></td>
                    <td><span style="font-size:12px; font-weight:bold; background:#e0e7ff; color:#4338ca; padding:2px 8px; border-radius:12px;"><?= htmlspecialchars($row['branch_id'] ?? 'Global HQ') ?></span></td>
                    <td><?= htmlspecialchars($row['department'] ?? '') ?></td>
                    <td><span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($row['manager_name'] ?: 'None') ?></span></td>
                    <td><?= htmlspecialchars($row['status'] ?? '') ?></td>
                    <?php if($canEditUsers || $canDeleteUsers): ?>
                    <td class="action-buttons">
                        <button class="edit-button" style="background:#f3f4f6; color:#4b5563; border:none; margin-right:4px;" onclick='openHRFiles("<?= htmlspecialchars($row['login_id']) ?>", <?= json_encode($row['name']) ?>, "<?= htmlspecialchars($row['status']) ?>")'>📁 HR Files</button>
                        <?php if($canEditUsers): ?>
                        <button class="edit-button" onclick='editUser(<?= json_encode($row) ?>)'>Edit</button>
                        <?php endif; ?>
                        <?php if($canDeleteUsers): ?>
                        <form method="POST" action="controllers/delete_user.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="delete-button">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openUserModal(data = null) {
    let d = data;
    document.getElementById('modalTitle').textContent = d ? "Edit User" : "Add User";
    document.getElementById('modalForm').action = "controllers/save_user.php";
    
    let html = `<input type="hidden" name="id" value="${d ? d.id : ''}">`;
    html += `<div class="form-group"><label>Login ID</label><input type="text" name="login_id" required value="${d ? d.login_id : ''}"></div>`;
    html += `<div class="form-group"><label>Password</label><input type="password" name="password" ${d ? '' : 'required'} placeholder="${d ? 'Leave blank to keep unchanged' : 'Enter password'}"></div>`;
    html += `<div class="form-group"><label>Name</label><input type="text" name="name" required value="${d ? d.name : ''}"></div>`;
    html += `<div class="form-group"><label>Email Address</label><input type="email" name="email" value="${d ? d.email : ''}"></div>`;
    
    // Manager Selection for Hierarchy
    let mgrList = <?= json_encode($all_users) ?>;
    html += `<div class="form-group"><label>Direct Manager (Hierarchy)</label><select name="manager_id">`;
    html += `<option value="">-- No Manager (Top Level) --</option>`;
    mgrList.forEach(m => {
        if (!d || d.login_id != m.login_id) { // Don't allow self-management
            let selm = (d && d.manager_id == m.login_id) ? 'selected' : '';
            html += `<option value="${m.login_id}" ${selm}>${m.name} [${m.role}]</option>`;
        }
    });
    html += `</select></div>`;
    
    let canAssignSuper = <?= ($_SESSION['role'] === 'Super Admin') ? 'true' : 'false' ?>;
    html += `<div class="form-group"><label>Designation</label><select name="role">`;
    if (canAssignSuper || (d && d.role === 'Super Admin')) {
        html += `<option value="Super Admin" ${d && d.role=='Super Admin'?'selected':''}>Super Admin</option>`;
    }
    html += `<option value="Admin" ${d && d.role=='Admin'?'selected':''}>Admin</option><option value="Manager" ${d && d.role=='Manager'?'selected':''}>Manager</option><option value="Employee" ${d && d.role=='Employee'?'selected':''}>Employee</option></select></div>`;
    html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div class="form-group"><label>Branch / Subsidiary</label><input type="text" name="branch_id" required value="${d && d.branch_id ? d.branch_id : 'Global HQ'}"></div>`;
    html += `<div class="form-group"><label>Department</label><input type="text" name="department" value="${d ? d.department : ''}"></div></div>`;
    
    html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div class="form-group"><label>REST API Key</label><input type="text" readonly value="${d && d.api_key ? d.api_key : 'None'}" style="background:#f3f4f6;"></div>`;
    html += `<div class="form-group"><label>API Key Action</label><select name="generate_api_key"><option value="no">Do Nothing</option><option value="yes">Generate New Key</option><option value="revoke">Revoke Key</option></select></div></div>`;
    
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editUser(data) {
    openUserModal(data);
}

// HR Files Logics
function openHRFiles(userId, userName, userStatus) {
    document.getElementById('hrFilesTitle').textContent = `HR Files: ${userName}`;
    document.getElementById('hrFilesUserId').value = userId;
    
    // Fetch existing files
    fetch(`controllers/get_user_files.php?user_id=${encodeURIComponent(userId)}`)
    .then(r => r.json())
    .then(data => {
        let html = '';
        if (data.length === 0) {
            html = `<div style="padding:20px; text-align:center; color:#9ca3af; font-size:13px; background:#f9fafb; border-radius:8px;">No HR documents on file for this employee.</div>`;
        } else {
            html = `<table style="width:100%; text-align:left; border-collapse:collapse; font-size:13px;">
                <tr style="border-bottom:2px solid #e5e7eb;">
                    <th style="padding:8px; color:#6b7280;">Document Title</th>
                    <th style="padding:8px; color:#6b7280;">Date Uploaded</th>
                    <th style="padding:8px; color:#6b7280;">Actions</th>
                </tr>`;
            data.forEach(f => {
                const safeTitle = f.title.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                html += `<tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:10px 8px; font-weight:600; color:#374151;">${safeTitle}</td>
                    <td style="padding:10px 8px; color:#6b7280;">${f.uploaded_at.split(' ')[0]}</td>
                    <td style="padding:10px 8px;">
                        <a href="${f.file_path}" download class="view-button" style="text-decoration:none; padding:4px 8px; font-size:11px; margin-right:4px;">⬇️ View</a>
                        <form method="POST" action="controllers/delete_user_file.php" style="display:inline;" onsubmit="return confirm('Permanently delete this employee file?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="${f.id}">
                            <input type="hidden" name="user_id" value="${userId}">
                            <button type="submit" class="delete-button" style="padding:4px 8px; font-size:11px;">Trash</button>
                        </form>
                    </td>
                </tr>`;
            });
            html += `</table>`;
        }
        document.getElementById('hrFilesList').innerHTML = html;
        
        let actZone = document.getElementById('hrActivationZone');
        if (userStatus === 'Pending_Docs') {
            actZone.innerHTML = `<hr style="border:0; border-top:1px solid #e5e7eb; margin:20px 0;"><form method="POST" action="controllers/activate_user.php"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="user_id" value="${userId}"><button type="submit" style="width:100%; background:#10b981; color:white; border:none; padding:12px; border-radius:8px; font-weight:700; cursor:pointer; box-shadow:0 4px 6px rgba(16, 185, 129, 0.2);">✅ Documents Validated - Grant Full Access</button></form>`;
        } else {
            actZone.innerHTML = '';
        }
        
        document.getElementById('hrFilesModal').style.display = 'block';
    });
}
</script>

<!-- HR Files Modal -->
<div id="hrFilesModal" class="modal">
    <div class="modal-content" style="width: 600px;">
        <span class="close-button" onclick="document.getElementById('hrFilesModal').style.display='none'">&times;</span>
        <h2 id="hrFilesTitle" style="margin-bottom:20px;">HR Files</h2>
        
        <div id="hrFilesList" style="margin-bottom:24px; max-height:250px; overflow-y:auto;">
            <!-- Fetched dynamically -->
        </div>
        
        <!-- Upload Form -->
        <div style="background:var(--bg-body); padding:20px; border-radius:12px; border:1px solid var(--border-card);">
            <h3 style="font-size:14px; margin-bottom:12px; color:var(--text-heading);">➕ Secure Attachment Upload</h3>
            <form method="POST" action="controllers/upload_user_file.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="user_id" id="hrFilesUserId">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group" style="margin:0;">
                        <label>Document Title</label>
                        <input type="text" name="title" required placeholder="e.g. Identity Passport" style="background:var(--bg-card);">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>File (PDF, PNG, JPG)</label>
                        <input type="file" name="hr_file" required accept=".pdf,.png,.jpg,.jpeg" style="background:var(--bg-card); padding:8px;">
                    </div>
                </div>
                <div style="margin-top:16px; text-align:right;">
                    <button type="submit" class="submit" style="padding:8px 16px;">Encrypt & Upload</button>
                </div>
            </form>
        </div>
        
        <!-- Activation Zone -->
        <div id="hrActivationZone"></div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

