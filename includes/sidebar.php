<div class="sidebar-overlay" onclick="document.querySelector('.app-container').classList.remove('sidebar-open');"></div>
<div class="app-container">
    <!-- Double Sidebar Rail -->
    <div class="sidebar-rail">
        <div class="rail-avatar">
            <?= isset($_SESSION['name']) ? strtoupper(substr($_SESSION['name'],0,1)) : 'U' ?>
        </div>
        
        <div class="rail-menu">
            <button class="rail-btn active" data-target="panel-quick" title="<?= __('Quick Access') ?>">⚡</button>
            <button class="rail-btn" data-target="panel-workspace" title="<?= __('My Workspace') ?>">💼</button>
            <?php if(($GLOBAL_SETTINGS['module_hr'] ?? 'true') !== 'false'): ?>
            <button class="rail-btn" data-target="panel-hr" title="<?= __('Human Capital') ?>">👥</button>
            <?php endif; ?>
            <?php if(($GLOBAL_SETTINGS['module_finance'] ?? 'true') !== 'false' || (($GLOBAL_SETTINGS['module_crm'] ?? 'true') !== 'false' && hasPermission($pdo, 'view_crm'))): ?>
            <button class="rail-btn" data-target="panel-commerce" title="<?= __('Financial Operations') ?>">💰</button>
            <?php endif; ?>
            <?php if($_SESSION['role'] !== 'Client' && $_SESSION['role'] !== 'Vendor' && ($GLOBAL_SETTINGS['module_projects'] ?? 'true') !== 'false'): ?>
            <button class="rail-btn" data-target="panel-projects" title="<?= __('Project & Task Management') ?>">🚀</button>
            <?php endif; ?>
            <button class="rail-btn" data-target="panel-ops" title="<?= __('Operations') ?>">🛠️</button>
            <?php if(hasPermission($pdo, 'view_users') || hasPermission($pdo, 'manage_roles') || hasPermission($pdo, 'manage_settings') || hasPermission($pdo, 'manage_contracts') || hasPermission($pdo, 'send_broadcast_emails')): ?>
            <button class="rail-btn" data-target="panel-admin" title="<?= __('System Administration') ?>">⚙️</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Context Panels -->
    <div class="sidebar">
        <!-- Sidebar Menu Search -->
        <div style="padding: 16px 16px 0 16px; margin-bottom: -8px;">
            <div style="position: relative;">
                <input type="text" id="menuSearch" placeholder="Search menu... (/)" 
                    style="width:100%; padding: 8px 12px 8px 32px; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-main); font-size: 13px; color: var(--text-body);">
                <span style="position: absolute; left: 10px; top: 9px; font-size: 14px; color: var(--text-muted);">🔍</span>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="sidebar-context-panel active" id="panel-quick">
            <div class="sidebar-section">Quick Access</div>
            <div onclick="window.location.href='tasks.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : '' ?>">✅ Tasks</div>
            <div onclick="window.location.href='projects.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : '' ?>">🚀 Projects</div>
            <div onclick="window.location.href='ops_kanban.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'ops_kanban.php' ? 'active' : '' ?>">📋 Todos</div>
            <div onclick="window.location.href='notes.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'notes.php' ? 'active' : '' ?>">📝 Notes</div>
            <div onclick="window.location.href='documents.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : '' ?>">📂 Drive</div>
            <?php if(($GLOBAL_SETTINGS['module_communication'] ?? 'true') !== 'false'): ?>
            <div onclick="window.location.href='chat.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>">💬 Chat</div>
            <?php endif; ?>
        </div>

        <!-- Workspace -->
        <div class="sidebar-context-panel" id="panel-workspace" style="display:none;">
            <div class="sidebar-section"><?= __('My Workspace') ?></div>
            <?php if(hasPermission($pdo, 'view_dashboard')): ?>
            <div onclick="window.location.href='dashboard.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">📊 <?= __('Dashboard') ?></div>
            <?php endif; ?>
            <div onclick="window.location.href='workspaces.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'workspaces.php' ? 'active' : '' ?>">🏢 <?= __('Dedicated Workspaces') ?></div>
            <div onclick="window.location.href='meetings.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'meetings.php' ? 'active' : '' ?>">📹 <?= __('Virtual Meetings') ?></div>
            <div onclick="window.location.href='manual.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'manual.php' ? 'active' : '' ?>">📖 <?= __('User Manual') ?></div>
            <?php if(($GLOBAL_SETTINGS['module_communication'] ?? 'true') !== 'false'): ?>
            <div onclick="window.location.href='intranet.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'intranet.php' ? 'active' : '' ?>">📣 <?= __('Company Hub') ?></div>
            <?php endif; ?>
            <div onclick="window.location.href='notifications.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">🔔 <?= __('Notifications') ?></div>
            <div onclick="window.location.href='vault.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'vault.php' ? 'active' : '' ?>">🔐 <?= __('Personal Vault') ?></div>
            <?php if(hasPermission($pdo, 'view_calendar')): ?>
            <div onclick="window.location.href='calendar.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : '' ?>">📆 <?= __('Shared Calendar') ?></div>
            <?php endif; ?>
            <?php if(hasPermission($pdo, 'access_rewards') || hasPermission($pdo, 'manage_rewards')): ?>
            <div onclick="window.location.href='rewards.php'" class="<?= basename($_SERVER['PHP_SELF']) == 'rewards.php' ? 'active' : '' ?>">🏆 Peer Rewards</div>
            <?php endif; ?>
        </div>

        <!-- HR -->
        <div class="sidebar-context-panel" id="panel-hr" style="display:none;">
            <div class="sidebar-section">HR & People Ops</div>
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
        </div>

        <!-- Commerce -->
        <div class="sidebar-context-panel" id="panel-commerce" style="display:none;">
            <div class="sidebar-section">Finance & Commerce</div>
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

        <!-- Projects -->
        <div class="sidebar-context-panel" id="panel-projects" style="display:none;">
            <div class="sidebar-section">Project Management</div>
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

        <!-- Ops -->
        <div class="sidebar-context-panel" id="panel-ops" style="display:none;">
            <div class="sidebar-section">Operations & Support</div>
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

        <!-- Admin -->
        <div class="sidebar-context-panel" id="panel-admin" style="display:none;">
            <div class="sidebar-section">System Administration</div>
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
    const btns = document.querySelectorAll('.rail-btn');
    const panels = document.querySelectorAll('.sidebar-context-panel');

    // Auto-select panel if a child is active
    let activeFound = false;
    panels.forEach(p => {
        if (p.querySelector('.active')) {
            p.style.display = 'block';
            document.querySelector(`.rail-btn[data-target="${p.id}"]`)?.classList.add('active');
            activeFound = true;
        } else {
            p.style.display = 'none';
        }
    });

    if (!activeFound) {
        document.getElementById('panel-quick').style.display = 'block';
        document.querySelector('.rail-btn[data-target="panel-quick"]').classList.add('active');
    }

    btns.forEach(b => {
        b.addEventListener('click', () => {
            btns.forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            panels.forEach(p => p.style.display = 'none');
            document.getElementById(b.dataset.target).style.display = 'block';
            localStorage.setItem('activeSidebarPanel', b.dataset.target);
            // clear search when switching panels
            document.getElementById('menuSearch').value = '';
            document.getElementById('menuSearch').dispatchEvent(new Event('input'));
        });
    });

    // Menu Search Logic
    const menuSearch = document.getElementById('menuSearch');
    const allMenuLinks = document.querySelectorAll('.sidebar-context-panel div:not(.sidebar-section)');
    const allSections = document.querySelectorAll('.sidebar-section');
    
    menuSearch.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        
        if (query.trim() === '') {
            // Reset to normal panel view
            panels.forEach(p => {
                const isActive = document.querySelector(`.rail-btn[data-target="${p.id}"]`).classList.contains('active');
                p.style.display = isActive ? 'block' : 'none';
                
                // Show all links in this panel
                p.querySelectorAll('div:not(.sidebar-section)').forEach(link => {
                    link.style.display = 'block';
                    link.innerHTML = link.dataset.originalText || link.innerHTML;
                });
                
                // Show section titles
                p.querySelectorAll('.sidebar-section').forEach(sec => sec.style.display = 'block');
            });
            return;
        }
        
        // Search mode: show all panels, hide section titles, filter links
        panels.forEach(p => {
            p.style.display = 'block';
            p.querySelectorAll('.sidebar-section').forEach(sec => sec.style.display = 'none');
            
            let hasVisibleLinks = false;
            p.querySelectorAll('div:not(.sidebar-section)').forEach(link => {
                if (!link.dataset.originalText) {
                    link.dataset.originalText = link.innerHTML;
                }
                
                const text = link.textContent.toLowerCase();
                if (text.includes(query)) {
                    link.style.display = 'block';
                    hasVisibleLinks = true;
                    // Highlight match
                    const regex = new RegExp(`(${query})`, 'gi');
                    link.innerHTML = link.dataset.originalText.replace(/(<([^>]+)>)/gi, "").replace(regex, '<mark style="background:#fef08a; color:#854d0e; border-radius:2px; padding:0 2px;">$1</mark>');
                } else {
                    link.style.display = 'none';
                }
            });
            
            // Hide panel if no links match
            if (!hasVisibleLinks) {
                p.style.display = 'none';
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
});
</script>
