<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']));
$me = $_SESSION['login_id'];

if ($isAdmin) {
    // ── Consolidated Admin Metrics (single query replaces 10 individual COUNTs) ──
    $globalCounts = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM users) as users_count,
            (SELECT COUNT(*) FROM zones) as zones_count,
            (SELECT COUNT(*) FROM locations) as locations_count,
            (SELECT COUNT(*) FROM tasks) as tasks_total,
            (SELECT COUNT(*) FROM activities) as activities_count,
            (SELECT COUNT(*) FROM tasks WHERE status='Pending') as tasks_pending,
            (SELECT COUNT(*) FROM tasks WHERE status='In Progress') as tasks_in_progress,
            (SELECT COUNT(*) FROM tasks WHERE status='Completed') as tasks_completed,
            (SELECT COUNT(*) FROM leaves WHERE status='Pending') as pending_leaves
    ")->fetch(PDO::FETCH_ASSOC);

    $usersCount      = $globalCounts['users_count'];
    $zonesCount      = $globalCounts['zones_count'];
    $locationsCount  = $globalCounts['locations_count'];
    $tasksCount      = $globalCounts['tasks_total'];
    $activitiesCount = $globalCounts['activities_count'];
    $tasksPending    = $globalCounts['tasks_pending'];
    $tasksInProgress = $globalCounts['tasks_in_progress'];
    $tasksCompleted  = $globalCounts['tasks_completed'];
    $pendingLeaves   = $globalCounts['pending_leaves'];

    try {
        $openFeedback = $pdo->query("SELECT COUNT(*) FROM unified_tickets WHERE status='Open' AND source='Feedback'")->fetchColumn();
    } catch (Exception $e) { $openFeedback = 0; }

    // ── Phase 18 KPIs (consolidated into 1 query) ──
    try {
        $p18 = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM projects WHERE status='Active') as active_projects,
                (SELECT COALESCE(SUM(budget),0) FROM projects WHERE status='Active') as total_budget,
                (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status='Approved') as total_spent,
                (SELECT COUNT(*) FROM expenses WHERE status='Pending') as pending_expenses,
                (SELECT COUNT(*) FROM assets WHERE status='Assigned') as assigned_assets,
                (SELECT COUNT(*) FROM assets) as total_assets,
                (SELECT COALESCE(SUM(value),0) FROM crm_leads WHERE stage NOT IN ('Won','Lost')) as pipeline_value,
                (SELECT COALESCE(SUM(value),0) FROM crm_leads WHERE stage='Won') as won_value,
                (SELECT COUNT(*) FROM crm_leads WHERE stage NOT IN ('Won','Lost')) as open_leads
        ")->fetch(PDO::FETCH_ASSOC);

        $p18_activeProjects  = $p18['active_projects'];
        $p18_totalBudget     = $p18['total_budget'];
        $p18_totalSpent      = $p18['total_spent'];
        $p18_pendingExpenses = $p18['pending_expenses'];
        $p18_assignedAssets  = $p18['assigned_assets'];
        $p18_totalAssets     = $p18['total_assets'];
        $p18_pipelineValue   = $p18['pipeline_value'];
        $p18_wonValue        = $p18['won_value'];
        $p18_openLeads       = $p18['open_leads'];
        $p18_burnRate        = $p18_totalBudget > 0 ? round(($p18_totalSpent / $p18_totalBudget) * 100, 1) : 0;
        $p18_hasTables       = true;
    } catch (Exception $e) { $p18_hasTables = false; }


    // Phase 19 Business & HR KPIs
    try {
        // Fallback for amount field in invoices (sometimes it's amount, sometimes total_amount based on schema)
        $p19_unpaidInvoices = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status != 'Paid'")->fetchColumn() ?: 0;
    } catch (Exception $e) {
        try {
            $p19_unpaidInvoices = $pdo->query("SELECT SUM(total_amount) FROM invoices WHERE status != 'Paid'")->fetchColumn() ?: 0;
        } catch (Exception $e2) {
            $p19_unpaidInvoices = 0;
        }
    }
    
    try { $p19_activeContracts = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status='Active'")->fetchColumn() ?: 0; } catch (Exception $e) { $p19_activeContracts = 0; }
    try { $p19_openJobs = $pdo->query("SELECT COUNT(*) FROM applicants WHERE status NOT IN ('Hired', 'Rejected')")->fetchColumn() ?: 0; } catch (Exception $e) { $p19_openJobs = 0; }
    try { $p19_upcomingBookings = $pdo->query("SELECT COUNT(*) FROM room_bookings WHERE start_time >= datetime('now')")->fetchColumn() ?: 0; } catch (Exception $e) { $p19_upcomingBookings = 0; }

    // Fetch Global Audit Trail
    $recentActivity = $pdo->query("SELECT a.*, u.name as user_name FROM audit_trail a LEFT JOIN users u ON a.user_id = u.login_id ORDER BY a.timestamp DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    // ── CHART DATA: Revenue by Month (last 6 months) ──
    $revenueLabels = []; $revenueData = [];
    try {
        $revenueRows = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%b %Y') as mon,
                   SUM(total_amount) as total
            FROM invoices
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($revenueRows as $r) { $revenueLabels[] = $r['mon']; $revenueData[] = (float)$r['total']; }
    } catch (Exception $e) {}

    // ── CHART DATA: Pipeline by Stage ──
    $pipelineLabels = []; $pipelineData = []; $pipelineColors = [];
    $stageColors = ['Prospect'=>'#6366f1','Qualified'=>'#3b82f6','Proposal'=>'#f59e0b','Won'=>'#10b981','Lost'=>'#ef4444'];
    try {
        $pipelineRows = $pdo->query("SELECT stage, COUNT(*) as cnt FROM crm_leads GROUP BY stage")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pipelineRows as $r) {
            $pipelineLabels[] = $r['stage'];
            $pipelineData[]   = (int)$r['cnt'];
            $pipelineColors[] = $stageColors[$r['stage']] ?? '#9ca3af';
        }
    } catch (Exception $e) {}

    // ── CHART DATA: Omni-Channel Tickets ──
    $ticketLabels = ['Open', 'In Progress', 'Resolved']; 
    $ticketData = [0, 0, 0];
    try {
        $ticketRows = $pdo->query("SELECT status, COUNT(*) as cnt FROM unified_tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ticketRows as $r) {
            if ($r['status'] == 'Open') $ticketData[0] = (int)$r['cnt'];
            if ($r['status'] == 'In Progress') $ticketData[1] = (int)$r['cnt'];
            if ($r['status'] == 'Resolved' || $r['status'] == 'Closed') $ticketData[2] += (int)$r['cnt'];
        }
    } catch (Exception $e) {}

    // ── CHART DATA: Activity Bar Chart (Last 7 Days) ──
    $activityLabels = [];
    $activityData = [];
    try {
        $activityRows = $pdo->query("
            SELECT date(timestamp) as act_date, COUNT(*) as cnt 
            FROM audit_trail 
            WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
            GROUP BY date(timestamp) 
            ORDER BY date(timestamp) ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill missing days
        $activityMap = [];
        foreach($activityRows as $r) { $activityMap[$r['act_date']] = (int)$r['cnt']; }
        for ($i=6; $i>=0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $activityLabels[] = date('M d', strtotime("-$i days"));
            $activityData[] = $activityMap[$d] ?? 0;
        }
    } catch (Exception $e) {}
} else {
    // Standard User Personalized Metrics
    $myTasksCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to LIKE ?");
    $myTasksCount->execute(["%$me%"]);
    $myTotal = $myTasksCount->fetchColumn();

    $myPendingStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to LIKE ? AND status='Pending'");
    $myPendingStmt->execute(["%$me%"]);
    $myPending = $myPendingStmt->fetchColumn();

    $myCompletedStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to LIKE ? AND status='Completed'");
    $myCompletedStmt->execute(["%$me%"]);
    $myCompleted = $myCompletedStmt->fetchColumn();

    $myFormsCount = $pdo->prepare("SELECT COUNT(*) FROM form_assignments WHERE assigned_to = ?");
    $myFormsCount->execute([$me]);
    $myForms = $myFormsCount->fetchColumn();
    
    // Check specific table if it exists to prevent errors if user hasn't visited messages yet
    try {
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND status='unread'");
        $unreadStmt->execute([$me]);
        $unreadChats = $unreadStmt->fetchColumn();
    } catch(PDOException $e) { $unreadChats = 0; }

    // Fetch Personal Audit Trail
    $recentActivity = $pdo->prepare("SELECT a.*, u.name as user_name FROM audit_trail a LEFT JOIN users u ON a.user_id = u.login_id WHERE a.user_id = ? ORDER BY a.timestamp DESC LIMIT 5");
    $recentActivity->execute([$me]);
    $recentActivity = $recentActivity->fetchAll(PDO::FETCH_ASSOC);
}

// Global enhancement (for everyone): Detect Urgent / Overdue Tasks
try {
    // Basic fallback for MySQL compatibility without SQLite date() function
    $stmtUrgent = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to LIKE ? AND status != 'Done' AND status != 'Deleted' AND due_date <= '" . date('Y-m-d H:i:s', strtotime('+1 day')) . "' ORDER BY due_date ASC");
    $stmtUrgent->execute(["%$me%"]);
    $urgentTasks = $stmtUrgent->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $urgentTasks = []; }

// Fetch Company Hub Announcements
$announcements = [];
try {
    $stmtAnn = $pdo->query("SELECT p.*, COALESCE(u.name, sa.name, 'Unknown User') as author_name FROM intranet_posts p LEFT JOIN users u ON p.user_id = u.login_id LEFT JOIN super_admins sa ON p.user_id = sa.login_id WHERE p.post_type = 'Announcement' ORDER BY p.id DESC LIMIT 3");
    if($stmtAnn) $announcements = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="content-section active" id="printableDashboard">
    
    <!-- COMPANY HUB ANNOUNCEMENTS -->
    <?php if(!empty($announcements)): ?>
    <div style="background: linear-gradient(to right, #1e3a8a, #3b82f6); border-radius: 12px; padding: 20px; margin-bottom: 25px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" data-html2canvas-ignore="true">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h3 style="margin:0; font-size: 18px; display:flex; align-items:center; gap:8px;">📣 Official Announcements</h3>
            <button onclick="window.location.href='intranet.php'" style="background:rgba(255,255,255,0.2); color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:bold; font-size:12px;">View All in Hub</button>
        </div>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach($announcements as $ann): ?>
            <div style="background: rgba(255,255,255,0.1); padding: 12px 15px; border-radius: 8px; font-size: 14px;">
                <strong style="color: #fef08a; margin-right: 5px;"><?= htmlspecialchars($ann['author_name']) ?>:</strong> 
                <span><?= htmlspecialchars($ann['content']) ?></span>
                <div style="font-size: 11px; opacity: 0.7; margin-top: 5px;"><?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- URGENT TASKS NOTIFICATION (All Users) -->
    <?php if(!empty($urgentTasks)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;  padding: 20px; border-radius: 8px; margin-bottom: 25px;" data-html2canvas-ignore="true">
        <h3 style="color: #b91c1c; margin-bottom: 10px; display:flex; align-items:center; gap:10px;">⚠️ Urgent Action Required</h3>
        <p style="color: #991b1b; font-size: 14px; margin-bottom: 15px;">You have <?= count($urgentTasks) ?> task(s) currently overdue or due within 24 hours.</p>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach($urgentTasks as $ut): ?>
            <div style="display:flex; justify-content:space-between; background:white; padding:10px 15px; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.05); font-size:13px;">
                <strong style="color:#111827;"><?= htmlspecialchars($ut['name']) ?></strong>
                <span style="color:#dc2626; font-weight:bold;">Due: <?= htmlspecialchars($ut['due_date']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ADMIN DASHBOARD -->
    <?php if($isAdmin): ?>
        <div class="section-header" data-html2canvas-ignore="true">
            <h2>Enterprise Overview (Admin)</h2>
            <button class="add-button" onclick="generatePDF()">📥 Export Report to PDF</button>
        </div>
        
        <div class="dashboard-grid">
            <div class="metric-card-split glass-card hoverable">
                <div class="metric-header" style="background:transparent;">Total Registered Users</div>
                <div class="metric-body bg-gradient-green">
                    <h3><?= $usersCount ?></h3>
                </div>
            </div>
            <div class="metric-card-split glass-card hoverable">
                <div class="metric-header" style="background:transparent;">Active Zones</div>
                <div class="metric-body bg-gradient-blue">
                    <h3><?= $zonesCount ?></h3>
                </div>
            </div>
            <div class="metric-card-split glass-card hoverable">
                <div class="metric-header" style="background:transparent;">Pending Complaints/Feedback</div>
                <div class="metric-body bg-gradient-purple">
                    <h3><?= $openFeedback ?></h3>
                </div>
            </div>
            <div class="metric-card-split glass-card hoverable">
                <div class="metric-header" style="background:transparent;">Leave Requests Pending</div>
                <div class="metric-body bg-gradient-orange">
                    <h3><?= $pendingLeaves ?></h3>
                </div>
            </div>
        </div>

        <!-- PHASE 18 ENTERPRISE METRICS -->
        <?php if(!empty($p18_hasTables)): ?>
        <h3 style="color:var(--text-heading);font-size:18px;font-weight:700;margin:32px 0 16px;letter-spacing:-0.5px;">🚀 Enterprise Operations — Live</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Active Projects</div>
                <div class="premium-gradient-text" style="font-size:34px;font-weight:800;margin-top:4px;display:inline-block;"><?= $p18_activeProjects ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Budget: $<?= number_format($p18_totalBudget) ?></div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Budget Burn Rate</div>
                <div style="font-size:34px;font-weight:800;color:<?= $p18_burnRate >= 90 ? '#dc2626' : ($p18_burnRate >= 70 ? '#f59e0b' : '#10b981') ?>;margin-top:4px;"><?= $p18_burnRate ?>%</div>
                <div style="background:#f3f4f6;border-radius:99px;height:5px;margin-top:10px;overflow:hidden;"><div style="background:<?= $p18_burnRate >= 90 ? '#dc2626' : ($p18_burnRate >= 70 ? '#f59e0b' : '#10b981') ?>;height:100%;width:<?= min($p18_burnRate,100) ?>%;border-radius:99px;transition:width 1s ease-in-out;"></div></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:6px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p18_totalSpent) ?> spent</div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Total Unpaid Invoices</div>
                <div style="font-size:34px;font-weight:800;color:#ef4444;margin-top:4px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p19_unpaidInvoices) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Awaiting Collection</div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">CRM Pipeline Value</div>
                <div style="font-size:34px;font-weight:800;color:#f59e0b;margin-top:4px;">$<?= number_format($p18_pipelineValue) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $p18_openLeads ?> open leads</div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Closed/Won Value</div>
                <div style="font-size:34px;font-weight:800;color:#10b981;margin-top:4px;">$<?= number_format($p18_wonValue) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Total successful</div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Pending Expenses</div>
                <div style="font-size:34px;font-weight:800;color:#eab308;margin-top:4px;"><?= $p18_pendingExpenses ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Awaiting approval</div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">IT Assets</div>
                <div style="font-size:34px;font-weight:800;color:#3b82f6;margin-top:4px;"><?= $p18_assignedAssets ?><span style="font-size:16px;color:var(--text-muted);font-weight:500;"> / <?= $p18_totalAssets ?></span></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Assigned / Total</div>
            </div>
            <div class="glass-card hoverable" style="padding:20px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">CRM Pipeline</div>
                <div style="font-size:28px;font-weight:800;color:#10b981;margin-top:4px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p18_pipelineValue) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $p18_openLeads ?> open · Won: $<?= number_format($p18_wonValue) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PHASE 19 BUSINESS & HR METRICS -->
        <h3 style="color:var(--text-heading);font-size:18px;font-weight:700;margin:32px 0 16px;letter-spacing:-0.5px;">💼 Business & HR Overview</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
            <div style="background:var(--bg-card);border-radius:16px;padding:20px;border:1px solid var(--border-card);box-shadow:var(--shadow-soft);">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Outstanding Invoices</div>
                <div style="font-size:28px;font-weight:800;color:#ef4444;margin-top:4px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p19_unpaidInvoices) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Awaiting Payment</div>
            </div>
            <div style="background:var(--bg-card);border-radius:16px;padding:20px;border:1px solid var(--border-card);box-shadow:var(--shadow-soft);">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Active Contracts</div>
                <div style="font-size:34px;font-weight:800;color:#14b8a6;margin-top:4px;"><?= $p19_activeContracts ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Legal module</div>
            </div>
            <div style="background:var(--bg-card);border-radius:16px;padding:20px;border:1px solid var(--border-card);box-shadow:var(--shadow-soft);">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Open Job Requisitions</div>
                <div style="font-size:34px;font-weight:800;color:#8b5cf6;margin-top:4px;"><?= $p19_openJobs ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Recruitment pipeline</div>
            </div>
            <div style="background:var(--bg-card);border-radius:16px;padding:20px;border:1px solid var(--border-card);box-shadow:var(--shadow-soft);">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.07em;">Upcoming Bookings</div>
                <div style="font-size:34px;font-weight:800;color:#f97316;margin-top:4px;"><?= $p19_upcomingBookings ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Meeting Rooms</div>
            </div>
        </div>

        <!-- CHARTS ROW: Revenue + Pipeline + Tickets -->
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:24px; margin-bottom:24px;">
            <!-- Revenue Line Chart -->
            <div style="background:var(--bg-card); padding:24px; border-radius:16px; box-shadow:var(--shadow-soft); border:1px solid var(--border-card);">
                <h4 style="margin:0 0 20px; color:var(--text-heading); font-size:1.1rem;">📈 Invoice Revenue — 6 Months</h4>
                <div style="height:250px;"><canvas id="revenueChart"></canvas></div>
            </div>
            <!-- Pipeline Doughnut -->
            <div style="background:var(--bg-card); padding:24px; border-radius:16px; box-shadow:var(--shadow-soft); border:1px solid var(--border-card);">
                <h4 style="margin:0 0 20px; color:var(--text-heading); font-size:1.1rem;">🎯 CRM Pipeline</h4>
                <div style="height:250px; display:flex; justify-content:center;"><canvas id="pipelineChart"></canvas></div>
            </div>
            <!-- Omni-Channel Tickets Doughnut -->
            <div style="background:var(--bg-card); padding:24px; border-radius:16px; box-shadow:var(--shadow-soft); border:1px solid var(--border-card);">
                <h4 style="margin:0 0 20px; color:var(--text-heading); font-size:1.1rem;">🎫 Omni-Channel Tickets</h4>
                <div style="height:250px; display:flex; justify-content:center;"><canvas id="ticketChart"></canvas></div>
            </div>
        </div>

        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 24px;">
            <!-- Live Activity Feed -->
            <div style="flex: 2; min-width: 350px; background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid var(--border-card);">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-bolt" style="color:#f59e0b; margin-right:8px;"></i> Live Enterprise Activity Feed</h4>
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <?php foreach($recentActivity as $act): ?>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:12px; border-bottom:1px solid var(--border-card);">
                            <div>
                                <strong style="color:var(--text-heading); font-size:0.95rem; display:block;"><?= htmlspecialchars($act['action']) ?></strong>
                                <span style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($act['details']) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-size:0.8rem; font-weight:600; background:rgba(99, 102, 241, 0.1); color:#4f46e5; padding:4px 8px; border-radius:12px;"><?= htmlspecialchars($act['user_name'] ?? $act['user_id']) ?></span>
                                <span style="display:block; font-size:0.75rem; color:var(--text-muted); margin-top:4px;"><?= date('M d, h:i A', strtotime($act['timestamp'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($recentActivity)): ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">No recent activities logged securely in the matrix.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Global Task Matrix Chart -->
            <div style="flex: 1; min-width: 300px; background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid var(--border-card);">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-chart-pie" style="color:#4f46e5; margin-right:8px;"></i> Global Task Load</h4>
                <div style="height: 250px; display: flex; justify-content: center;">
                    <canvas id="taskChart"></canvas>
                </div>
            </div>

            <!-- Activity Bar Chart -->
            <div style="flex: 1; min-width: 300px; background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid var(--border-card);">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-chart-bar" style="color:#10b981; margin-right:8px;"></i> Enterprise Activity (7 Days)</h4>
                <div style="height: 250px; display: flex; justify-content: center;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

        <script>
        new Chart(document.getElementById('taskChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [<?= $tasksPending ?: 0 ?>, <?= $tasksInProgress ?: 0 ?>, <?= $tasksCompleted ?: 0 ?>],
                    backgroundColor: ['#ef4444', '#3b82f6', '#10b981'],
                    hoverOffset: 4
                }]
            },
        options: { responsive: true, maintainAspectRatio: false }
        });

        // Activity Bar Chart
        const actCtx = document.getElementById('activityChart').getContext('2d');
        const actGradient = actCtx.createLinearGradient(0, 0, 0, 400);
        actGradient.addColorStop(0, '#8fa8d3'); // Blue gradient
        actGradient.addColorStop(1, '#7a98cc');

        new Chart(actCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($activityLabels) ?>,
                datasets: [{
                    label: 'System Activity',
                    data: <?= json_encode($activityData) ?>,
                    backgroundColor: actGradient,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { display: false }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });

        // Revenue Line Chart
        const revCtx = document.getElementById('revenueChart').getContext('2d');
        const revGradient = revCtx.createLinearGradient(0, 0, 0, 400);
        revGradient.addColorStop(0, 'rgba(105, 210, 178, 0.5)'); // Green gradient start
        revGradient.addColorStop(1, 'rgba(105, 210, 178, 0.0)');

        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($revenueLabels ?: ['No Data']) ?>,
                datasets: [{
                    label: 'Revenue (<?= htmlspecialchars($GLOBAL_SETTINGS['currency'] ?? '₹') ?>)',
                    data: <?= json_encode($revenueData ?: [0]) ?>,
                    borderColor: '#4dbca2',
                    backgroundColor: revGradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4dbca2',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { display: false }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });

        // Pipeline Doughnut Chart
        new Chart(document.getElementById('pipelineChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($pipelineLabels ?: ['No Leads']) ?>,
                datasets: [{
                    data: <?= json_encode($pipelineData ?: [1]) ?>,
                    backgroundColor: <?= json_encode($pipelineColors ?: ['#e5e7eb']) ?>,
                    hoverOffset: 6,
                    borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } } } }
        });

        // Omni-Channel Ticket Chart
        new Chart(document.getElementById('ticketChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($ticketLabels) ?>,
                datasets: [{
                    data: <?= json_encode($ticketData) ?>,
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                    hoverOffset: 6,
                    borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } } } }
        });
        </script>

    <!-- STANDARD USER DASHBOARD -->
    <?php else: ?>
        <div class="section-header" data-html2canvas-ignore="true">
            <h2>Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>!</h2>
            <button class="add-button" onclick="generatePDF()">📥 Export Personal Report</button>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-card" style="" onclick="window.location.href='tasks.php'">
                <h3><?= $myTotal ?></h3>
                <p>My Total Assigned Tasks</p>
            </div>
            <div class="dashboard-card" style="" onclick="window.location.href='tasks.php'">
                <h3><?= $myPending ?></h3>
                <p>Tasks Pending Action</p>
            </div>
            <div class="dashboard-card" style="" onclick="window.location.href='forms.php'">
                <h3><?= $myForms ?></h3>
                <p>Forms Allocated To Me</p>
            </div>
            <div class="dashboard-card" style="" onclick="window.location.href='chat.php'">
                <h3><?= $unreadChats ?></h3>
                <p>Unread Messages</p>
            </div>
        </div>

        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom:24px;">
            <div style="flex: 2; min-width: 350px; background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid var(--border-card);">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-history" style="color:#6366f1; margin-right:8px;"></i> My Recent Activity</h4>
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <?php foreach($recentActivity as $act): ?>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:12px; border-bottom:1px solid var(--border-card);">
                            <div>
                                <strong style="color:var(--text-heading); font-size:0.95rem; display:block;"><?= htmlspecialchars($act['action']) ?></strong>
                                <span style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($act['details']) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span style="display:block; font-size:0.75rem; color:var(--text-muted); margin-top:4px;"><?= date('M d, Y', strtotime($act['timestamp'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($recentActivity)): ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">No recent activities logged in your profile.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="flex: 1; min-width: 300px; background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid var(--border-card);">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-chart-pie" style="color:#10b981; margin-right:8px;"></i> My Task Completion</h4>
                <div style="height: 250px; display: flex; justify-content: center;">
                    <canvas id="myTaskChart"></canvas>
                </div>
            </div>
            
            <div style="flex: 1; min-width: 300px; background: var(--bg-card); padding: 24px; border-radius: 16px; box-shadow: var(--shadow-soft); border: 1px solid var(--border-card);">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-bolt" style="color:#f59e0b; margin-right:8px;"></i> Quick Actions</h4>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <a href="attendance.php" style="text-decoration:none; padding:16px; background:var(--bg-body); border-radius:8px; border:1px solid var(--border-card); color:var(--text-body); font-weight:600; display:flex; justify-content:space-between; transition:all 0.2s;">
                        <span>🕐 Clock In / Time Tracker</span>
                        <span>&rarr;</span>
                    </a>
                    <a href="forms.php" style="text-decoration:none; padding:16px; background:var(--bg-body); border-radius:8px; border:1px solid var(--border-card); color:var(--text-body); font-weight:600; display:flex; justify-content:space-between; transition:all 0.2s;">
                        <span>📝 Submit Reports & Forms</span>
                        <span>&rarr;</span>
                    </a>
                    <a href="chat.php" style="text-decoration:none; padding:16px; background:var(--bg-body); border-radius:8px; border:1px solid var(--border-card); color:var(--text-body); font-weight:600; display:flex; justify-content:space-between; transition:all 0.2s;">
                        <span>💬 Enterprise Messaging</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>
        </div>

        <script>
        const ctxTasks = document.getElementById('myTaskChart').getContext('2d');
        new Chart(ctxTasks, {
            type: 'doughnut',
            data: {
                labels: ['Needs Action (Pending)', 'Completed Tasks'],
                datasets: [{
                    data: [<?= $myPending ?: 0 ?>, <?= $myCompleted ?: 0 ?>],
                    backgroundColor: ['#ef4444', '#10b981'],
                    hoverOffset: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
        </script>
    <?php endif; ?>
</div>

<script>
function generatePDF() {
    const element = document.getElementById('printableDashboard');
    // Temporarily enforce light mode styling for better PDF printing contrast
    const wasDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if(wasDark) document.documentElement.removeAttribute('data-theme');
    
    html2pdf().set({
        margin:       0.5,
        filename:     'Enterprise_Report_' + new Date().toISOString().split('T')[0] + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
    }).from(element).save().then(() => {
        // Restore dark mode if it was active
        if(wasDark) document.documentElement.setAttribute('data-theme', 'dark');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>


