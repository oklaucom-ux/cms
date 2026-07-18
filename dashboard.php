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
    try { 
        $now_str = (isset($use_mysql) && $use_mysql) ? 'NOW()' : "datetime('now')";
        $p19_upcomingBookings = $pdo->query("SELECT COUNT(*) FROM room_bookings WHERE start_time >= $now_str")->fetchColumn() ?: 0; 
    } catch (Exception $e) { $p19_upcomingBookings = 0; }

    // Fetch Global Audit Trail
    $recentActivity = $pdo->query("SELECT a.*, u.name as user_name FROM audit_trail a LEFT JOIN users u ON a.user_id = u.login_id ORDER BY a.timestamp DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    // ── CHART DATA: Revenue by Month (last 6 months) ──
    $revenueLabels = []; $revenueData = [];
    try {
        global $use_mysql;
        if (isset($use_mysql) && $use_mysql) {
            $revenueRows = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as mon,
                       SUM(total_amount) as total
                FROM invoices
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
                LIMIT 6
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $revenueRows = $pdo->query("
                SELECT strftime('%Y-%m', created_at) as raw_mon,
                       SUM(total_amount) as total
                FROM invoices
                WHERE created_at >= date('now', '-6 month')
                GROUP BY strftime('%Y-%m', created_at)
                ORDER BY strftime('%Y-%m', created_at) ASC
                LIMIT 6
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach($revenueRows as &$row) {
                // Convert YYYY-MM to abbreviated month string
                $d = DateTime::createFromFormat('Y-m', $row['raw_mon']);
                $row['mon'] = $d ? $d->format('M Y') : $row['raw_mon'];
            }
        }
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
        global $use_mysql;
        if (isset($use_mysql) && $use_mysql) {
            $activityRows = $pdo->query("
                SELECT date(timestamp) as act_date, COUNT(*) as cnt 
                FROM audit_trail 
                WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                GROUP BY date(timestamp) 
                ORDER BY date(timestamp) ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $activityRows = $pdo->query("
                SELECT date(timestamp) as act_date, COUNT(*) as cnt 
                FROM audit_trail 
                WHERE timestamp >= date('now', '-6 day') 
                GROUP BY date(timestamp) 
                ORDER BY date(timestamp) ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
        
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
    <div class="glass-card hoverable" style="padding: 24px; margin-bottom: 25px;" data-html2canvas-ignore="true">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h3 style="margin:0; font-size: 18px; color:var(--text-heading); display:flex; align-items:center; gap:8px;">📣 Official Announcements</h3>
            <button class="premium-btn" onclick="window.location.href='intranet.php'" style="padding:6px 12px; font-size:12px;">View All in Hub</button>
        </div>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach($announcements as $ann): ?>
            <div style="background: rgba(59, 130, 246, 0.05); padding: 12px 15px; border-radius: 8px; font-size: 14px; border:1px solid rgba(59,130,246,0.1);">
                <strong style="color: #3b82f6; margin-right: 5px;"><?= htmlspecialchars($ann['author_name']) ?>:</strong> 
                <span style="color:var(--text-muted);"><?= htmlspecialchars($ann['content']) ?></span>
                <div style="font-size: 11px; color:#9ca3af; margin-top: 5px; font-weight:600;"><?= date('M d, Y h:i A', strtotime($ann['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- URGENT TASKS NOTIFICATION (All Users) -->
    <?php if(!empty($urgentTasks)): ?>
    <div class="glass-card hoverable" style="padding: 20px; border-left: 4px solid #ef4444; margin-bottom: 25px; background: linear-gradient(145deg, rgba(239, 68, 68, 0.05), rgba(255, 255, 255, 0));" data-html2canvas-ignore="true">
        <h3 style="color: #ef4444; margin-bottom: 10px; display:flex; align-items:center; gap:10px;">⚠️ Urgent Action Required</h3>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 15px; font-weight:500;">You have <?= count($urgentTasks) ?> task(s) currently overdue or due within 24 hours.</p>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach($urgentTasks as $ut): ?>
            <div style="display:flex; justify-content:space-between; background:var(--bg-card); padding:10px 15px; border-radius:8px; box-shadow:var(--shadow-soft); font-size:13px; border:1px solid var(--border-card);">
                <strong style="color:var(--text-heading);"><?= htmlspecialchars($ut['name']) ?></strong>
                <span style="color:#ef4444; font-weight:bold; background:rgba(239, 68, 68, 0.1); padding:2px 8px; border-radius:99px;">Due: <?= htmlspecialchars($ut['due_date']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ADMIN DASHBOARD -->
    <?php if($isAdmin): ?>
        <div class="section-header" data-html2canvas-ignore="true">
            <h2>Enterprise Overview</h2>
            <button class="premium-btn" onclick="generatePDF()">
                <i class="fas fa-file-pdf"></i> Export Report
            </button>
        </div>
        
        <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:24px; margin-bottom:32px;">
            <div class="glass-card hoverable" style="flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #4f46e5, #818cf8); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(79,70,229,0.3);">
                    <i class="fas fa-users" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Registered Users</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $usersCount ?></div>
                </div>
            </div>
            
            <div class="glass-card hoverable" style="flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #3b82f6, #60a5fa); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(59,130,246,0.3);">
                    <i class="fas fa-map-marked-alt" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Active Zones</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $zonesCount ?></div>
                </div>
            </div>

            <div class="glass-card hoverable" style="flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #8b5cf6, #c084fc); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(139,92,246,0.3);">
                    <i class="fas fa-comment-dots" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Pending Feedback</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $openFeedback ?></div>
                </div>
            </div>

            <div class="glass-card hoverable" style="flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #f97316, #fb923c); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(249,115,22,0.3);">
                    <i class="fas fa-calendar-times" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Leaves Pending</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $pendingLeaves ?></div>
                </div>
            </div>
        </div>

        <!-- PHASE 18 ENTERPRISE METRICS -->
        <?php if(!empty($p18_hasTables)): ?>
        <h3 style="color:var(--text-heading); font-size:18px; font-weight:800; margin:40px 0 20px; letter-spacing:-0.5px; display:flex; align-items:center; gap:10px;">
            <div style="width:10px; height:24px; background:linear-gradient(135deg, #f43f5e, #fb923c); border-radius:4px;"></div>
            Enterprise Operations — Live
        </h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px; margin-bottom:40px;">
            
            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Active Projects</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(16,185,129,0.1); color:#10b981; display:flex; align-items:center; justify-content:center;"><i class="fas fa-project-diagram"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:var(--text-heading); margin:12px 0;"><?= $p18_activeProjects ?></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;"><span style="color:#10b981;">Budget:</span> <?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p18_totalBudget) ?></div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Budget Burn Rate</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(245,158,11,0.1); color:#f59e0b; display:flex; align-items:center; justify-content:center;"><i class="fas fa-fire"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:<?= $p18_burnRate >= 90 ? '#dc2626' : ($p18_burnRate >= 70 ? '#f59e0b' : '#10b981') ?>; margin:12px 0;"><?= $p18_burnRate ?>%</div>
                <div style="background:var(--border-card); border-radius:99px; height:6px; margin-bottom:12px; overflow:hidden;"><div style="background:<?= $p18_burnRate >= 90 ? '#dc2626' : ($p18_burnRate >= 70 ? '#f59e0b' : '#10b981') ?>; height:100%; width:<?= min($p18_burnRate,100) ?>%; border-radius:99px; transition:width 1s ease-in-out;"></div></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p18_totalSpent) ?> spent</div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Total Unpaid Invoices</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(239,68,68,0.1); color:#ef4444; display:flex; align-items:center; justify-content:center;"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:#ef4444; margin:12px 0;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p19_unpaidInvoices) ?></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Awaiting Collection</div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">CRM Pipeline Value</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(99,102,241,0.1); color:#6366f1; display:flex; align-items:center; justify-content:center;"><i class="fas fa-bullseye"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:#6366f1; margin:12px 0;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p18_pipelineValue) ?></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;"><span style="color:var(--text-heading);"><?= $p18_openLeads ?></span> open leads</div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Closed / Won Value</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(16,185,129,0.1); color:#10b981; display:flex; align-items:center; justify-content:center;"><i class="fas fa-handshake"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:#10b981; margin:12px 0;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p18_wonValue) ?></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Total successful conversions</div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Pending Expenses</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(234,179,8,0.1); color:#eab308; display:flex; align-items:center; justify-content:center;"><i class="fas fa-receipt"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:#eab308; margin:12px 0;"><?= $p18_pendingExpenses ?></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Awaiting approval</div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">IT Assets</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(59,130,246,0.1); color:#3b82f6; display:flex; align-items:center; justify-content:center;"><i class="fas fa-laptop"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:#3b82f6; margin:12px 0;"><?= $p18_assignedAssets ?> <span style="font-size:20px; color:var(--text-muted); font-weight:600;">/ <?= $p18_totalAssets ?></span></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Assigned vs Total</div>
            </div>

            <div class="glass-card hoverable" style="padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Active Contracts</div>
                    <div style="width:32px; height:32px; border-radius:10px; background:rgba(20,184,166,0.1); color:#14b8a6; display:flex; align-items:center; justify-content:center;"><i class="fas fa-file-signature"></i></div>
                </div>
                <div style="font-size:36px; font-weight:900; color:#14b8a6; margin:12px 0;"><?= $p19_activeContracts ?></div>
                <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Legal / Vault module</div>
            </div>

        </div>
        <?php endif; ?>

        <!-- CHARTS ROW: Revenue + Pipeline + Tickets -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px; margin-bottom:24px;">
            <!-- Revenue Line Chart -->
            <div class="glass-card" style="padding:24px;">
                <h4 style="margin:0 0 20px; color:var(--text-heading); font-size:1.1rem; display:flex; align-items:center; gap:8px;">📈 Invoice Revenue (6m)</h4>
                <div style="height:250px;"><canvas id="revenueChart"></canvas></div>
            </div>
            <!-- Pipeline Doughnut -->
            <div class="glass-card" style="padding:24px;">
                <h4 style="margin:0 0 20px; color:var(--text-heading); font-size:1.1rem; display:flex; align-items:center; gap:8px;">🎯 CRM Pipeline</h4>
                <div style="height:250px; display:flex; justify-content:center;"><canvas id="pipelineChart"></canvas></div>
            </div>
            <!-- Omni-Channel Tickets Doughnut -->
            <div class="glass-card" style="padding:24px;">
                <h4 style="margin:0 0 20px; color:var(--text-heading); font-size:1.1rem; display:flex; align-items:center; gap:8px;">🎫 Omni-Channel Tickets</h4>
                <div style="height:250px; display:flex; justify-content:center;"><canvas id="ticketChart"></canvas></div>
            </div>
        </div>

        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 24px;">
            <!-- Live Activity Feed -->
            <div class="glass-card" style="flex: 2; min-width: 350px; padding: 24px;">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-bolt" style="color:#f59e0b; margin-right:8px;"></i> Live Enterprise Activity Feed</h4>
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <?php foreach($recentActivity as $act): ?>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:12px; border-radius:8px; background:rgba(0,0,0,0.02); border:1px solid var(--border-card);">
                            <div>
                                <strong style="color:var(--text-heading); font-size:0.95rem; display:block; margin-bottom:4px;"><?= htmlspecialchars($act['action']) ?></strong>
                                <span style="color:var(--text-muted); font-size:0.85rem; line-height:1.4; display:block;"><?= htmlspecialchars($act['details']) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-size:0.75rem; font-weight:700; background:rgba(99, 102, 241, 0.1); color:#4f46e5; padding:4px 10px; border-radius:99px;"><?= htmlspecialchars($act['user_name'] ?? $act['user_id']) ?></span>
                                <span style="display:block; font-size:0.75rem; color:var(--text-muted); margin-top:8px; font-weight:600;"><?= date('M d, h:i A', strtotime($act['timestamp'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($recentActivity)): ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">No recent activities logged securely in the matrix.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Global Task Matrix Chart -->
            <div class="glass-card" style="flex: 1; min-width: 300px; padding: 24px;">
                <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem;"><i class="fas fa-chart-pie" style="color:#4f46e5; margin-right:8px;"></i> Global Task Load</h4>
                <div style="height: 250px; display: flex; justify-content: center;">
                    <canvas id="taskChart"></canvas>
                </div>
            </div>

            <!-- Activity Bar Chart -->
            <div class="glass-card" style="flex: 1; min-width: 300px; padding: 24px;">
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
                    label: 'Revenue (<?= htmlspecialchars($GLOBAL_SETTINGS['currency'] ?? 'Γé╣') ?>)',
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
            <button class="add-button" onclick="generatePDF()">≡ƒôÑ Export Personal Report</button>
        </div>
        
        <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:24px; margin-bottom:32px;">
            <div class="glass-card hoverable" onclick="window.location.href='tasks.php'" style="cursor:pointer; flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #4f46e5, #818cf8); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(79,70,229,0.3);">
                    <i class="fas fa-tasks" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">My Total Tasks</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $myTotal ?></div>
                </div>
            </div>
            
            <div class="glass-card hoverable" onclick="window.location.href='tasks.php'" style="cursor:pointer; flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #f59e0b, #fbbf24); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(245,158,11,0.3);">
                    <i class="fas fa-clock" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Pending Action</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $myPending ?></div>
                </div>
            </div>

            <div class="glass-card hoverable" onclick="window.location.href='forms.php'" style="cursor:pointer; flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #10b981, #34d399); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(16,185,129,0.3);">
                    <i class="fas fa-file-alt" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Forms Allocated</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $myForms ?></div>
                </div>
            </div>

            <div class="glass-card hoverable" onclick="window.location.href='chat.php'" style="cursor:pointer; flex:1; min-width:240px; position:relative; overflow:hidden; padding:24px; border:1px solid var(--border-card); box-shadow:0 8px 32px rgba(0,0,0,0.04); backdrop-filter:blur(10px); display:flex; align-items:center; gap:20px; border-radius:20px;">
                <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg, #ef4444, #f87171); display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(239,68,68,0.3);">
                    <i class="fas fa-envelope" style="font-size:24px; color:white;"></i>
                </div>
                <div>
                    <div style="color:var(--text-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; margin-bottom:6px;">Unread Messages</div>
                    <div style="font-size:36px; font-weight:900; color:var(--text-heading); line-height:1; letter-spacing:-1px;"><?= $unreadChats ?></div>
                </div>
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
                        <span>≡ƒòÉ Clock In / Time Tracker</span>
                        <span>&rarr;</span>
                    </a>
                    <a href="forms.php" style="text-decoration:none; padding:16px; background:var(--bg-body); border-radius:8px; border:1px solid var(--border-card); color:var(--text-body); font-weight:600; display:flex; justify-content:space-between; transition:all 0.2s;">
                        <span>≡ƒô¥ Submit Reports & Forms</span>
                        <span>&rarr;</span>
                    </a>
                    <a href="chat.php" style="text-decoration:none; padding:16px; background:var(--bg-body); border-radius:8px; border:1px solid var(--border-card); color:var(--text-body); font-weight:600; display:flex; justify-content:space-between; transition:all 0.2s;">
                        <span>≡ƒÆ¼ Enterprise Messaging</span>
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


