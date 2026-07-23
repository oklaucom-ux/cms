<div class="sidebar-overlay" onclick="document.querySelector('.app-container').classList.remove('sidebar-open');"></div>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Menu Search -->
        <div style="padding: 16px 16px 0 16px; margin-bottom: -8px;">
            <div style="position: relative;">
                <input type="text" id="menuSearch" placeholder="Search menu... (/)" 
                    style="box-sizing: border-box; width:100%; padding: 8px 12px 8px 32px !important; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-main); font-size: 13px; color: var(--text-body);">
                <span style="position: absolute; left: 10px; top: 9px; font-size: 14px; color: var(--text-muted); pointer-events: none;">🔍</span>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="sidebar-section" onclick="toggleSection('quick-access')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">Quick Access <i class="fas fa-chevron-down" id="icon-quick-access" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="quick-access" class="sidebar-group">

            <?php if(hasPermission($pdo, 'view_tasks')): ?>
            <div onclick="window.location.href='tasks.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : '' ?>">✅ Tasks</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_projects')): ?>
            <div onclick="window.location.href='projects.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : '' ?>">🚀 Projects</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_tasks')): ?>
            <div onclick="window.location.href='ops_kanban.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'ops_kanban.php' ? 'active' : '' ?>">📋 Todos</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_notes')): ?>
            <div onclick="window.location.href='notes.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'notes.php' ? 'active' : '' ?>">📝 Notes</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_documents')): ?>
            <div onclick="window.location.href='documents.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : '' ?>">📂 Drive</div>
            <?php endif; ?>
            <?php if(($GLOBAL_SETTINGS['module_communication'] ?? 'true') !== 'false'): ?>
            <?php if(hasPermission($pdo, 'access_chat')): ?>
            <div onclick="window.location.href='chat.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>">💬 Chat</div>
            <?php endif; ?>
            <?php endif; ?>
        <!-- Workspace -->
        
            
</div>
<div class="sidebar-section" onclick="toggleSection('my-workspace')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;"><?= __('My Workspace') ?> <i class="fas fa-chevron-down" id="icon-my-workspace" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="my-workspace" class="sidebar-group">

            <?php if(hasPermission($pdo, 'view_dashboard')): ?>
            <div onclick="window.location.href='dashboard.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">📊 <?= __('Dashboard') ?></div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_workspaces')): ?>
            <div onclick="window.location.href='workspaces.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'workspaces.php' ? 'active' : '' ?>">🏢 <?= __('Dedicated Workspaces') ?></div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_meetings')): ?>
            <div onclick="window.location.href='meetings.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'meetings.php' ? 'active' : '' ?>">📹 <?= __('Virtual Meetings') ?></div>
            <?php endif; ?>
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin', 'System Admin'])): ?>
            <div onclick="window.location.href='manual.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'manual.php' ? 'active' : '' ?>">📖 <?= __('User Manual') ?></div>
            <?php endif; ?>
            <?php if(($GLOBAL_SETTINGS['module_communication'] ?? 'true') !== 'false'): ?>
            <?php if(hasPermission($pdo, 'view_intranet')): ?>
            <div onclick="window.location.href='intranet.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'intranet.php' ? 'active' : '' ?>">📣 <?= __('Company Hub') ?></div>
            <?php endif; ?>
            <?php endif; ?>
            <div onclick="window.location.href='notifications.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">🔔 <?= __('Notifications') ?></div>
            <?php if(hasPermission($pdo, 'access_vault')): ?>
            <div onclick="window.location.href='vault.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'vault.php' ? 'active' : '' ?>">🔐 <?= __('Personal Vault') ?></div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_calendar')): ?>
            <div onclick="window.location.href='calendar.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : '' ?>">📆 <?= __('Shared Calendar') ?></div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_rewards') || hasPermission($pdo, 'manage_rewards')): ?>
            <div onclick="window.location.href='rewards.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'rewards.php' ? 'active' : '' ?>">🏆 Peer Rewards</div>
            <?php endif; ?>
        <!-- HR -->
        
            
</div>
<div class="sidebar-section" onclick="toggleSection('hr--people-ops')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">HR & People Ops <i class="fas fa-chevron-down" id="icon-hr--people-ops" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="hr--people-ops" class="sidebar-group">

            <?php if(hasPermission($pdo, 'view_users') || hasPermission($pdo, 'manage_users')): ?>
            <div onclick="window.location.href='hr_dashboard.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'hr_dashboard.php' ? 'active' : '' ?>">📊 HR Dashboard</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_users')): ?>
            <div onclick="window.location.href='org_chart.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'org_chart.php' ? 'active' : '' ?>">🏢 Org Chart</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_attendance')): ?>
            <div onclick="window.location.href='attendance.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : '' ?>">🕐 Time & Attendance</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_attendance') || hasPermission($pdo,'manage_users')): ?>
            <div onclick="window.location.href='attendance_analytics.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'attendance_analytics.php' ? 'active' : '' ?>">📊 Attendance Analytics</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_leaves')): ?>
            <div onclick="window.location.href='leaves.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'leaves.php' ? 'active' : '' ?>">🌴 HR Leaves & PTO</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo,'view_payroll')): ?>
            <div onclick="window.location.href='payroll.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'active' : '' ?>">💰 Payroll</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_recruitment')): ?>
            <div onclick="window.location.href='recruitment.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'recruitment.php' ? 'active' : '' ?>">🎯 Recruitment ATS</div>
            <div onclick="window.location.href='hr_interviews.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'hr_interviews.php' ? 'active' : '' ?>">🤖 Virtual HR Interviews</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_onboarding')): ?>
            <div onclick="window.location.href='onboarding.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'onboarding.php' ? 'active' : '' ?>">👔 HR Onboarding</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_reviews')): ?>
            <div onclick="window.location.href='performance_reviews.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'performance_reviews.php' ? 'active' : '' ?>">📈 Performance Reviews</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_surveys') || hasPermission($pdo, 'manage_surveys')): ?>
            <div onclick="window.location.href='pulse_surveys.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'pulse_surveys.php' ? 'active' : '' ?>">📊 Pulse Surveys</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_training') || hasPermission($pdo, 'manage_training')): ?>
            <div onclick="window.location.href='training.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'training.php' ? 'active' : '' ?>">🎓 Training Module</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_feedback')): ?>
            <div onclick="window.location.href='feedback.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : '' ?>">📬 Feedback & Complaints</div>
            <?php endif; ?>
        <!-- Commerce -->
        
            
</div>
<div class="sidebar-section" onclick="toggleSection('finance--commerce')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">Finance & Commerce <i class="fas fa-chevron-down" id="icon-finance--commerce" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="finance--commerce" class="sidebar-group">

            <?php if(($GLOBAL_SETTINGS['module_crm'] ?? 'true') !== 'false' && hasPermission($pdo, 'view_crm')): ?>
            <div onclick="window.location.href='crm.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'crm.php' ? 'active' : '' ?>">🎯 Sales CRM</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_procurement')): ?>
            <div onclick="window.location.href='vendor_crm.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'vendor_crm.php' ? 'active' : '' ?>">🤝 Vendor CRM</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_invoices')): ?>
            <div onclick="window.location.href='invoices.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>">🧾 Billing & Invoices</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_expenses')): ?>
            <div onclick="window.location.href='expenses.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : '' ?>">💸 Expense Engine</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_procurement')): ?>
            <div onclick="window.location.href='procurement.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'procurement.php' ? 'active' : '' ?>">🛒 Procurement & Budgets</div>
            <?php endif; ?>
        <!-- Projects -->
        
            
</div>
<div class="sidebar-section" onclick="toggleSection('project-management')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">Project Management <i class="fas fa-chevron-down" id="icon-project-management" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="project-management" class="sidebar-group">

            <?php if(hasPermission($pdo, 'view_projects')): ?>
            <?php if(hasPermission($pdo, 'view_projects')): ?>
            <div onclick="window.location.href='projects.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : '' ?>">📁 Core Projects</div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_tasks')): ?>
            <?php if(hasPermission($pdo, 'view_tasks')): ?>
            <div onclick="window.location.href='tasks.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : '' ?>">✅ Task Tracker</div>
            <?php endif; ?>
            <div onclick="window.location.href='kanban.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'kanban.php' ? 'active' : '' ?>">📋 Kanban Board</div>
            <div onclick="window.location.href='gantt.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'gantt.php' ? 'active' : '' ?>">📅 Gantt Charts</div>
            <div onclick="window.location.href='timesheets.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'timesheets.php' ? 'active' : '' ?>">⏱️ Project Timesheets</div>
            <?php endif; ?>
        <!-- Ops -->
        
            
</div>
<div class="sidebar-section" onclick="toggleSection('operations--support')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">Operations & Support <i class="fas fa-chevron-down" id="icon-operations--support" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="operations--support" class="sidebar-group">

            <?php if(($GLOBAL_SETTINGS['module_assets'] ?? 'true') !== 'false' && hasPermission($pdo, 'view_assets')): ?>
            <div onclick="window.location.href='assets.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'assets.php' ? 'active' : '' ?>">🖥️ IT Assets</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_rooms') || hasPermission($pdo, 'manage_rooms')): ?>
            <div onclick="window.location.href='room_booking.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'room_booking.php' ? 'active' : '' ?>">📅 Room Booking</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_reception') || hasPermission($pdo, 'manage_reception')): ?>
            <div onclick="window.location.href='reception.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'reception.php' ? 'active' : '' ?>">🛎️ Reception Desk</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_forms') || hasPermission($pdo, 'manage_forms')): ?>
            <div onclick="window.location.href='forms.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'forms.php' ? 'active' : '' ?>">📝 Dynamic Forms</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_forms') || hasPermission($pdo,'manage_settings')): ?>
            <div onclick="window.location.href='form_analytics.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'form_analytics.php' ? 'active' : '' ?>">📊 Form Analytics</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_helpdesk') || hasPermission($pdo, 'manage_support')): ?>
            <div onclick="window.location.href='helpdesk.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'helpdesk.php' ? 'active' : '' ?>">🎫 IT & HR Helpdesk</div>
            <?php if(hasPermission($pdo, 'view_tasks')): ?>
            <div onclick="window.location.href='ops_kanban.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'ops_kanban.php' ? 'active' : '' ?>">🎯 Ops Task Board</div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_support')): ?>
            <div onclick="window.location.href='omni_desk.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'omni_desk.php' ? 'active' : '' ?>">🆘 Omni-Channel Desk</div>
            <div onclick="window.location.href='kb.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'kb.php' ? 'active' : '' ?>">📚 Knowledge Base</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_documents')): ?>
            <?php if(hasPermission($pdo, 'view_documents')): ?>
            <div onclick="window.location.href='documents.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : '' ?>">📂 Documents Drive</div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_office')): ?>
            <div onclick="window.location.href='office.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'office.php' ? 'active' : '' ?>">🛠️ Office Suite</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_kpi') || hasPermission($pdo, 'manage_kpi')): ?>
            <div onclick="window.location.href='kpi.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'kpi.php' ? 'active' : '' ?>">📈 KPI & Targets</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_reports')): ?>
            <div onclick="window.location.href='reports.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">📊 Advanced Reports</div>
            <?php endif; ?>
        <!-- Admin -->
        
            
</div>
<div class="sidebar-section" onclick="toggleSection('system-administration')" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">System Administration <i class="fas fa-chevron-down" id="icon-system-administration" style="font-size:10px; transition: transform 0.3s;"></i></div>
<div id="system-administration" class="sidebar-group">

            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <div onclick="window.location.href='executive_hud.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'executive_hud.php' ? 'active' : '' ?>">🌐 Global Command HUD</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_users')): ?>
            <div onclick="window.location.href='users.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">👥 User Management</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_roles')): ?>
            <div onclick="window.location.href='roles.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : '' ?>">🔒 Roles & Config</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_audit_trail')): ?>
            <div onclick="window.location.href='audit_trail.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'audit_trail.php' ? 'active' : '' ?>">📝 Audit Trail</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_contracts')): ?>
            <div onclick="window.location.href='contracts.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'contracts.php' ? 'active' : '' ?>">📜 Legal Contracts</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_settings')): ?>
            <div onclick="window.location.href='policies.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'policies.php' ? 'active' : '' ?>">📋 Policy Management</div>
            <div onclick="window.location.href='zones.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'zones.php' ? 'active' : '' ?>">🌍 Zone Management</div>
            <div onclick="window.location.href='locations.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : '' ?>">📍 Location Management</div>
            <?php if(($GLOBAL_SETTINGS['module_website'] ?? 'true') == 'true'): ?>
            <div onclick="window.location.href='website_builder.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'website_builder.php' ? 'active' : '' ?>">🌐 Website Builder</div>
            <?php endif; ?>
            <div onclick="window.location.href='webhooks.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'webhooks.php' ? 'active' : '' ?>">🔗 API Webhooks</div>
            <div onclick="window.location.href='settings.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">⚙️ System Settings</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_forms') && ($GLOBAL_SETTINGS['module_forms'] ?? 'true') == 'true'): ?>
            <div onclick="window.location.href='form_builder.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'form_builder.php' ? 'active' : '' ?>">📝 Form Builder</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'send_broadcast_emails')): ?>
            <div onclick="window.location.href='send_email.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'send_email.php' ? 'active' : '' ?>">✉️ Compose Mail</div>
            <?php endif; ?>
            
</div>
</div>

    <div class="main-content">
        <?php 
        $currentPage = basename($_SERVER['PHP_SELF']);
        if($currentPage !== 'dashboard.php'): 
            $pageTitle = ucwords(str_replace(['.php', '_'], ['', ' '], $currentPage));
        ?>
        <div class="breadcrumbs" style="margin-bottom: 20px; font-size: 13px; color: var(--text-muted);">
            <a href="dashboard.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">Home</a> 
            <span style="margin: 0 8px;">/</span> 
            <span style="color: var(--text-heading); font-weight: 600;"><?= $pageTitle ?></span>
        </div>
        <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // Menu Search Logic
    const menuSearch = document.getElementById('menuSearch');
    const allMenuLinks = document.querySelectorAll('.sidebar-group > div');
    const allSections = document.querySelectorAll('.sidebar-section');
    const allGroups = document.querySelectorAll('.sidebar-group');
    
    menuSearch.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        
        if (query.trim() === '') {
            allMenuLinks.forEach(link => {
                link.style.display = '';
                if(link.dataset.originalText) {
                    link.innerHTML = link.dataset.originalText;
                }
            });
            allSections.forEach(sec => sec.style.display = 'flex');
            allGroups.forEach(group => group.style.display = 'block');
            return;
        }
        
        allSections.forEach(sec => sec.style.display = 'none');
        allGroups.forEach(group => group.style.display = 'block');
        
        allMenuLinks.forEach(link => {
            if (!link.dataset.originalText) {
                link.dataset.originalText = link.innerHTML;
            }
            
            const text = link.textContent.toLowerCase();
            if (text.includes(query)) {
                link.style.display = '';
                const regex = new RegExp((), 'gi');
                link.innerHTML = link.dataset.originalText.replace(/(<([^>]+)>)/gi, "").replace(regex, '<mark style="background:#fef08a; color:#854d0e; border-radius:2px; padding:0 2px;"></mark>');
                
                // Show the parent section header
                const parentGroup = link.closest('.sidebar-group');
                if (parentGroup) {
                    const sectionHeader = parentGroup.previousElementSibling;
                    if (sectionHeader && sectionHeader.classList.contains('sidebar-section')) {
                        sectionHeader.style.display = 'flex';
                    }
                }
            } else {
                link.style.display = 'none';
            }
        });
    });

    // Keyboard shortcut '/'
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== menuSearch && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault();
            menuSearch.focus();
        }
    });

    // Expand group that contains active link on load
    document.querySelectorAll('.sidebar-group').forEach(group => {
        if (!group.querySelector('.active')) {
            // Optional: collapse non-active groups by default? 
            // group.style.display = 'none';
            // group.previousElementSibling.querySelector('i').style.transform = 'rotate(-90deg)';
        }
    });
});

function toggleSection(id) {
    const group = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    if (group.style.display === 'none') {
        group.style.display = 'block';
        icon.style.transform = 'rotate(0deg)';
    } else {
        group.style.display = 'none';
        icon.style.transform = 'rotate(-90deg)';
    }
}

</script>

