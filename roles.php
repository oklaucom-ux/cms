<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'manage_roles');

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
?>
<div class="content-section active">
    <!-- Roles Top Section -->
    <div class="section-header">
        <h2>Roles Management</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openRoleModal()">Add Role</button>
        <?php endif; ?>
    </div>
    <div class="data-table" style="margin-bottom: 40px;">
        <table>
            <thead>
                <tr>
                    <th>Role ID</th>
                    <th>Role Name</th>
                    <th>Description</th>
                    <th>Permissions</th>
                    <?php if($isAdmin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pdo->query("SELECT * FROM roles") as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['role_id']) ?></td>
                    <td><?= htmlspecialchars($row['role_name']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <td>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; max-width:450px;">
                        <?php 
                            $perms = json_decode($row['permissions'] ?? '[]', true);
                            if(is_array($perms)) {
                                foreach($perms as $p) {
                                    $label = ucwords(str_replace('_', ' ', $p));
                                    echo "<span style='background:#e0e7ff; color:#4f46e5; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:600;'>{$label}</span>";
                                }
                            }
                        ?>
                        </div>
                    </td>
                    <?php if($isAdmin): ?>
                    <td class="action-buttons">
                        <button class="edit-button" onclick='editRole(<?= json_encode($row) ?>)'>Edit</button>
                        <form method="POST" action="controllers/delete_role.php" style="display:inline;" onsubmit="return confirm('Delete this role?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="delete-button">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Designations Top Section -->
    <div class="section-header">
        <h2>Designations Management</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openDesignationModal()">Add Designation</button>
        <?php endif; ?>
    </div>
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Designation ID</th>
                    <th>Designation Name</th>
                    <th>Department</th>
                    <?php if($isAdmin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pdo->query("SELECT * FROM designations") as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['designation_id']) ?></td>
                    <td><?= htmlspecialchars($row['designation_name']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <?php if($isAdmin): ?>
                    <td class="action-buttons">
                        <button class="edit-button" onclick='editDesignation(<?= json_encode($row) ?>)'>Edit</button>
                        <form method="POST" action="controllers/delete_designation.php" style="display:inline;" onsubmit="return confirm('Delete this designation?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="delete-button">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openRoleModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Role" : "Add Role";
    document.getElementById('modalForm').action = "controllers/save_role.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div class="form-group"><label>Role ID</label><input type="text" name="role_id" required value="${data ? data.role_id : ''}"></div>`;
    html += `<div class="form-group"><label>Role Name</label><input type="text" name="role_name" required value="${data ? data.role_name : ''}"></div>`;
    html += `<div class="form-group"><label>Description</label><textarea name="description" required>${data ? data.description : ''}</textarea></div>`;
    
    // Checkbox mapping for RBAC
    let p = data && data.permissions ? (typeof data.permissions==='string'?JSON.parse(data.permissions):data.permissions) : [];
    const permGroups = {
        'Core Modules': ['view_dashboard'],
        'User Administration': ['view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users'],
        'Roles & Security': ['manage_roles', 'manage_settings', 'view_audit_trail', 'manage_broadcasts'],
        'Human Resources': ['view_onboarding', 'manage_onboarding', 'view_attendance', 'manage_attendance', 'view_leaves', 'approve_leaves', 'view_payroll', 'manage_payroll', 'manage_recruitment', 'manage_reviews', 'access_surveys', 'manage_surveys'],
        'Sales & CRM': ['view_crm', 'create_leads', 'edit_leads', 'delete_leads', 'convert_leads', 'export_leads'],
        'Finance — Assets': ['view_assets', 'create_assets', 'edit_assets', 'delete_assets'],
        'Finance — Expenses': ['view_expenses', 'create_expenses', 'approve_expenses', 'delete_expenses'],
        'Finance — Invoices': ['view_invoices', 'create_invoices', 'edit_invoices', 'delete_invoices'],
        'Finance — Procurement': ['manage_procurement'],
        'Projects & Tasks': ['view_projects', 'create_projects', 'edit_projects', 'delete_projects', 'view_tasks', 'create_tasks', 'edit_tasks', 'delete_tasks'],
        'Activities & Calendar': ['view_activities', 'create_activities', 'edit_activities', 'delete_activities', 'view_calendar', 'create_meetings', 'view_reports'],
        'Documents & Office': ['view_documents', 'upload_documents', 'delete_documents', 'access_office'],
        'Communication': ['access_chat', 'moderate_chat', 'access_helpdesk', 'manage_support'],
        'Forms & KPI': ['access_forms', 'manage_forms', 'access_kpi', 'manage_kpi'],
        'Training & Feedback': ['access_training', 'manage_training', 'manage_feedback'],
        'Contracts & Legal': ['view_contracts', 'create_contracts', 'edit_contracts', 'delete_contracts'],
        'Locations & Zones': ['view_locations', 'create_locations', 'edit_locations', 'delete_locations', 'view_zones', 'create_zones', 'edit_zones', 'delete_zones'],
        'Policies & KB': ['view_policies', 'create_policies', 'edit_policies', 'delete_policies', 'view_kb', 'manage_kb'],
        'Workspace & Culture': ['access_rooms', 'manage_rooms', 'access_rewards', 'manage_rewards', 'manage_website', 'view_reception', 'manage_reception']
    };

    const permLabels = {
        view_dashboard: 'View Dashboard',
        view_users: 'View User List', create_users: 'Create New User', edit_users: 'Edit User Profile', delete_users: 'Terminate/Delete User', manage_users: 'Full User Management',
        manage_roles: 'Configure Roles & Perms', manage_settings: 'System Global Settings', view_audit_trail: 'View Security Audit Logs', manage_broadcasts: 'Send Company-Wide Alerts',
        view_onboarding: 'View Applicant Pipeline', manage_onboarding: 'Approve/Reject Applicants',
        view_attendance: 'View Attendance Records', manage_attendance: 'Edit Attendance Entries',
        view_leaves: 'View Team Leave Requests', approve_leaves: 'Approve/Reject Leaves',
        view_payroll: 'View Salary Overview', manage_payroll: 'Process Payroll & Payslips',
        manage_recruitment: 'AI Recruitment & Interviews', manage_reviews: 'Performance Reviews',
        access_surveys: 'Participate in Surveys', manage_surveys: 'Create & Manage Surveys',
        view_crm: 'Access CRM Sales Hub', create_leads: 'Register New Leads', edit_leads: 'Update Lead Info/Stage', delete_leads: 'Purge Lead Records', convert_leads: 'Convert Lead → Project', export_leads: 'Export CRM Data (CSV/JSON)',
        view_assets: 'View Asset Inventory', create_assets: 'Register New Asset', edit_assets: 'Update/Assign Asset', delete_assets: 'Decommission Asset',
        view_expenses: 'View Expense History', create_expenses: 'File Expense Claim', approve_expenses: 'Approve/Reject Expenses', delete_expenses: 'Delete Expense Record',
        view_invoices: 'View Invoice List', create_invoices: 'Generate New Invoice', edit_invoices: 'Modify Invoice Details', delete_invoices: 'Cancel/Void Invoice',
        manage_procurement: 'Manage Procurement & Vendors',
        view_projects: 'View Project Portfolio', create_projects: 'Create New Project', edit_projects: 'Edit Project Details', delete_projects: 'Archive/Delete Project',
        view_tasks: 'View Task Board', create_tasks: 'Create & Assign Tasks', edit_tasks: 'Modify Task Details', delete_tasks: 'Remove Task Records',
        view_activities: 'View Activity Feed', create_activities: 'Log New Activity', edit_activities: 'Edit Activity Entry', delete_activities: 'Delete Activity Record',
        view_calendar: 'View Corporate Calendar', create_meetings: 'Schedule Meetings', view_reports: 'Access Advanced Reports',
        view_documents: 'Browse File Drive', upload_documents: 'Upload Documents', delete_documents: 'Delete Drive Files', access_office: 'Use Office Suite',
        access_chat: 'Open Team Chat', moderate_chat: 'Moderate Chat (Purge)', access_helpdesk: 'Submit Support Tickets', manage_support: 'Manage Service Desk',
        access_forms: 'Submit Dynamic Forms', manage_forms: 'Build & Edit Forms', access_kpi: 'View My KPIs', manage_kpi: 'Set KPI Targets',
        access_training: 'Access Training LMS', manage_training: 'Manage LMS Courses', manage_feedback: 'Manage Feedback/Surveys',
        view_contracts: 'View Legal Contracts', create_contracts: 'Draft New Contracts', edit_contracts: 'Edit Contract Status', delete_contracts: 'Delete Contracts',
        view_locations: 'View Branch Locations', create_locations: 'Add New Location', edit_locations: 'Edit Location', delete_locations: 'Remove Location',
        view_zones: 'View Geo Zones', create_zones: 'Create Zone', edit_zones: 'Edit Zone', delete_zones: 'Remove Zone',
        view_policies: 'View Company Policies', create_policies: 'Create Policy', edit_policies: 'Edit Policy', delete_policies: 'Remove Policy',
        view_kb: 'View Knowledge Base', manage_kb: 'Manage KB Articles',
        access_rooms: 'Book Meeting Rooms', manage_rooms: 'Manage Room Inventory', access_rewards: 'Send Peer Kudos', manage_rewards: 'Moderate Peer Rewards', manage_website: 'Website Builder Access',
        view_reception: 'View Reception Desk', manage_reception: 'Manage Visitors & Packages'
    };
    
    html += `<div class="form-group"><label>Granular Access Configuration</label><div style="display:flex; flex-direction:column; gap:16px;">`;
    
    for (const [groupName, perms] of Object.entries(permGroups)) {
        html += `<div style="background:var(--bg-body); border:1px solid var(--border-card); border-radius:8px; padding:16px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">`;
        html += `<h4 style="margin:0 0 12px 0; color:var(--text-heading); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; border-bottom:2px solid var(--border-card); padding-bottom:8px;">📁 ${groupName}</h4>`;
        html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; color:var(--text-body);">`;
        
        perms.forEach(perm => {
            let isChecked = p.includes(perm) ? 'checked' : '';
            html += `<label style="font-weight:normal; font-size:13px; display:flex; align-items:center; gap:8px; margin:0; cursor:pointer;">
                <input type="checkbox" name="permissions[]" value="${perm}" ${isChecked}>${permLabels[perm] || perm.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}
            </label>`;
        });
        
        html += `</div></div>`;
    }
    
    html += `</div></div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editRole(data) { openRoleModal(data); }

function openDesignationModal(data = null) {
    document.getElementById('modalTitle').textContent = data ? "Edit Designation" : "Add Designation";
    document.getElementById('modalForm').action = "controllers/save_designation.php";
    
    let html = `<input type="hidden" name="id" value="${data ? data.id : ''}">`;
    html += `<div class="form-group"><label>Designation ID</label><input type="text" name="designation_id" required value="${data ? data.designation_id : ''}"></div>`;
    html += `<div class="form-group"><label>Designation Name</label><input type="text" name="designation_name" required value="${data ? data.designation_name : ''}"></div>`;
    html += `<div class="form-group"><label>Department</label><input type="text" name="department" required value="${data ? data.department : ''}"></div>`;
    
    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editDesignation(data) { openDesignationModal(data); }
</script>

<?php require_once 'includes/footer.php'; ?>
