


<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open');"></div>
<div class="app-container">
    <div class="sidebar">
        <!-- New Avatar Profile Section -->
        <section style="padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 10px;">
            <article style="width: 80px; height: 80px; background: #ffffff; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 700; color: var(--primary-color); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <?= isset($_SESSION['name']) ? strtoupper(substr($_SESSION['name'],0,1)) : 'U' ?>
            </article>
            <p style="color: white; font-weight: 700; font-size: 16px; letter-spacing: 1px; text-transform: uppercase; margin: 0;">
                <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin-top: 4px; margin-bottom: 0; text-transform: uppercase;">
                <?= htmlspecialchars($_SESSION['role'] ?? 'Role') ?>
            </p>
        </section>
        
                <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-workspace', this)">My Workspace <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-workspace">
            <?php if(hasPermission($pdo, 'view_dashboard')): ?>
            <div onclick="window.location.href='dashboard.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">📊 Dashboard</div>
            <?php endif; ?>
            <div onclick="window.location.href='manual.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'manual.php' ? 'active' : '' ?>">📖 User Manual</div>
            <?php if(($GLOBAL_SETTINGS['module_communication'] ?? 'true') !== 'false'): ?>
            <div onclick="window.location.href='chat.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>">💬 Messages</div>
            <div onclick="window.location.href='intranet.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'intranet.php' ? 'active' : '' ?>">📣 Company Hub</div>
            <?php endif; ?>
            <div onclick="window.location.href='notifications.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                🔔 Notifications
                <?php
                require_once __DIR__.'/notifications.php';
                $nc = getUnreadCountDirect($pdo, $_SESSION['login_id'] ?? '');
                if($nc > 0) echo "<span style='background:#ef4444;color:white;border-radius:99px;font-size:10px;font-weight:700;padding:1px 6px;margin-left:auto;'>{$nc}</span>";
                ?>
            </div>
            <div onclick="window.location.href='vault.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'vault.php' ? 'active' : '' ?>">🔐 Personal Vault</div>
            <?php if(hasPermission($pdo, 'view_calendar')): ?>
            <div onclick="window.location.href='calendar.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : '' ?>">📆 Visual Calendar</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_rewards') || hasPermission($pdo, 'manage_rewards')): ?>
            <div onclick="window.location.href='rewards.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'rewards.php' ? 'active' : '' ?>">🏆 Peer Rewards</div>
            <?php endif; ?>
        </div>

        <?php if(($GLOBAL_SETTINGS['module_hr'] ?? 'true') !== 'false'): ?>
        <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-hr', this)">HR & People Ops <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-hr">
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
        </div>
        <?php endif; ?>

        <?php if(($GLOBAL_SETTINGS['module_finance'] ?? 'true') !== 'false' || (($GLOBAL_SETTINGS['module_crm'] ?? 'true') !== 'false' && hasPermission($pdo, 'view_crm'))): ?>
        <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-commerce', this)">Finance & Commerce <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-commerce">
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
        </div>
        <?php endif; ?>

        <?php if($_SESSION['role'] !== 'Client' && $_SESSION['role'] !== 'Vendor' && ($GLOBAL_SETTINGS['module_projects'] ?? 'true') !== 'false'): ?>
        <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-projects', this)">Project Management <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-projects">
            <?php if(hasPermission($pdo, 'view_projects')): ?>
            <div onclick="window.location.href='projects.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : '' ?>">📁 Core Projects</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_tasks')): ?>
            <div onclick="window.location.href='tasks.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : '' ?>">✅ Task Tracker</div>
            <div onclick="window.location.href='kanban.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'kanban.php' ? 'active' : '' ?>">📋 Kanban Board</div>
            <div onclick="window.location.href='gantt.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'gantt.php' ? 'active' : '' ?>">📅 Gantt Charts</div>
            <div onclick="window.location.href='timesheets.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'timesheets.php' ? 'active' : '' ?>">⏱️ Project Timesheets</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-ops', this)">Operations & Support <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-ops">
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
            <div onclick="window.location.href='ops_kanban.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'ops_kanban.php' ? 'active' : '' ?>">🎯 Ops Task Board</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'manage_support')): ?>
            <div onclick="window.location.href='omni_desk.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'omni_desk.php' ? 'active' : '' ?>">🆘 Omni-Channel Desk</div>
            <div onclick="window.location.href='kb.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'kb.php' ? 'active' : '' ?>">📚 Knowledge Base</div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'view_documents')): ?>
            <div onclick="window.location.href='documents.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : '' ?>">📂 Documents Drive</div>
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
        </div>

        <?php if(hasPermission($pdo, 'view_users') || hasPermission($pdo, 'manage_roles') || hasPermission($pdo, 'manage_settings') || hasPermission($pdo, 'manage_contracts') || hasPermission($pdo, 'send_broadcast_emails')): ?>
        <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-admin', this)">System Administration <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-admin">
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
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <div onclick="window.location.href='activities.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'activities.php' ? 'active' : '' ?>">⚡ All Activities</div>
            <div onclick="window.open('cron_tasks.php?key=Admin123!SecureCronKey', '_blank')" style="color:var(--danger-color); font-weight:bold;">⚡ Force CRON Tick</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Client' || $_SESSION['role'] === 'Vendor'): ?>
        <div class="sidebar-section collapsed" onclick="toggleSidebarGroup('grp-portals', this)">External Portals <span class="toggle-icon">▼</span></div>
        <div class="sidebar-group collapsed-group" id="grp-portals">
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Client'): ?>
            <div onclick="window.location.href='client_portal.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'client_portal.php' ? 'active' : '' ?>">🤝 Client Portal</div>
            <?php endif; ?>
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Vendor'): ?>
            <div onclick="window.location.href='vendor_portal.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'vendor_portal.php' ? 'active' : '' ?>">🚚 Vendor Portal</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
function toggleSidebarGroup(groupId, headerEl) {
    const group = document.getElementById(groupId);
    group.classList.toggle('collapsed-group');
    headerEl.classList.toggle('collapsed');
    localStorage.setItem(groupId, group.classList.contains('collapsed-group') ? 'collapsed' : 'expanded');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sidebar-group').forEach(group => {
        const state = localStorage.getItem(group.id);
        const header = group.previousElementSibling;
        
        // If it's explicitly saved as collapsed, keep it collapsed.
        // Otherwise, default to EXPANDED so users can see all integrated links.
        if (state === 'collapsed' && !group.querySelector('.active')) {
            // keep it collapsed (already collapsed in CSS)
        } else {
            group.classList.remove('collapsed-group');
            header.classList.remove('collapsed');
        }
    });
});
</script>
