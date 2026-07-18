<?php
require_once 'includes/db.php';
requirePermission($pdo, 'view_users'); // Basic HR permission
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// HR Metrics Queries
try {
    $headcount = $pdo->query("SELECT COUNT(*) FROM users WHERE status='Active'")->fetchColumn() ?: 0;
} catch(Exception $e) { $headcount = 0; }

try {
    $leavesPending = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status='Pending'")->fetchColumn() ?: 0;
} catch(Exception $e) { $leavesPending = 0; }

try {
    $openJobs = $pdo->query("SELECT COUNT(*) FROM applicants WHERE status NOT IN ('Hired', 'Rejected')")->fetchColumn() ?: 0;
} catch(Exception $e) { $openJobs = 0; }

try {
    $today = date('Y-m-d');
    $presentToday = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date='$today'")->fetchColumn() ?: 0;
} catch(Exception $e) { $presentToday = 0; }

// Recent HR Activity
try {
    $recentLeaves = $pdo->query("SELECT l.*, u.name as user_name FROM leaves l JOIN users u ON l.user_id = u.login_id ORDER BY l.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $recentLeaves = []; }
?>

<div class="content-section active">
    <div class="section-header" style="margin-bottom:32px;">
        <h2>👔 Human Resources Dashboard</h2>
    </div>

    <!-- HR KPI Grid -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px; margin-bottom:40px;">
        <!-- Headcount -->
        <div class="glass-card hoverable" onclick="window.location.href='org_chart.php'" style="cursor:pointer; padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Total Headcount</div>
                <div style="width:32px; height:32px; border-radius:10px; background:rgba(79,70,229,0.1); color:#4f46e5; display:flex; align-items:center; justify-content:center;"><i class="fas fa-users"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:var(--text-heading); margin:12px 0;"><?= $headcount ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;"><span style="color:#10b981;">Active</span> employees</div>
        </div>

        <!-- Present Today -->
        <div class="glass-card hoverable" onclick="window.location.href='attendance.php'" style="cursor:pointer; padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Attendance Today</div>
                <div style="width:32px; height:32px; border-radius:10px; background:rgba(16,185,129,0.1); color:#10b981; display:flex; align-items:center; justify-content:center;"><i class="fas fa-clock"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:#10b981; margin:12px 0;"><?= $presentToday ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Employees logged in</div>
        </div>

        <!-- Leaves Pending -->
        <div class="glass-card hoverable" onclick="window.location.href='leaves.php'" style="cursor:pointer; padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Leave Requests</div>
                <div style="width:32px; height:32px; border-radius:10px; background:rgba(245,158,11,0.1); color:#f59e0b; display:flex; align-items:center; justify-content:center;"><i class="fas fa-umbrella-beach"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:#f59e0b; margin:12px 0;"><?= $leavesPending ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Awaiting approval</div>
        </div>

        <!-- Open Jobs -->
        <div class="glass-card hoverable" onclick="window.location.href='recruitment.php'" style="cursor:pointer; padding:24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; justify-content:space-between;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Open Applicants</div>
                <div style="width:32px; height:32px; border-radius:10px; background:rgba(239,68,68,0.1); color:#ef4444; display:flex; align-items:center; justify-content:center;"><i class="fas fa-user-tie"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:#ef4444; margin:12px 0;"><?= $openJobs ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Active in ATS</div>
        </div>
    </div>

    <!-- Layout for recent activity -->
    <div style="display:flex; flex-wrap:wrap; gap:24px;">
        <div class="glass-card" style="flex:1; min-width:300px; padding:24px;">
            <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-calendar-check" style="color:#10b981;"></i> Recent Leave Requests
            </h4>
            <?php if(empty($recentLeaves)): ?>
                <p style="color:var(--text-muted);">No recent leave requests.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach($recentLeaves as $leave): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; border-radius:8px; border:1px solid var(--border-card); background:rgba(0,0,0,0.02);">
                            <div>
                                <strong style="color:var(--text-heading); display:block;"><?= htmlspecialchars($leave['user_name']) ?></strong>
                                <span style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($leave['leave_type']) ?> (<?= $leave['days'] ?> days)</span>
                            </div>
                            <div>
                                <?php if($leave['status'] == 'Approved'): ?>
                                    <span style="background:rgba(16,185,129,0.1); color:#10b981; padding:4px 10px; border-radius:99px; font-size:11px; font-weight:700;">Approved</span>
                                <?php elseif($leave['status'] == 'Pending'): ?>
                                    <span style="background:rgba(245,158,11,0.1); color:#f59e0b; padding:4px 10px; border-radius:99px; font-size:11px; font-weight:700;">Pending</span>
                                <?php else: ?>
                                    <span style="background:rgba(239,68,68,0.1); color:#ef4444; padding:4px 10px; border-radius:99px; font-size:11px; font-weight:700;">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="glass-card" style="flex:1; min-width:300px; padding:24px;">
            <h4 style="margin-bottom:20px; color:var(--text-heading); font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-clipboard-list" style="color:#8b5cf6;"></i> Quick HR Actions
            </h4>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <button onclick="window.location.href='recruitment.php'" style="padding:16px; border:1px solid var(--border-card); border-radius:12px; background:var(--bg-card); cursor:pointer; text-align:center; transition:0.2s;" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="this.style.borderColor='var(--border-card)'">
                    <i class="fas fa-plus-circle" style="font-size:24px; color:#4f46e5; margin-bottom:8px;"></i><br>
                    <span style="font-weight:600; color:var(--text-heading);">New Hire</span>
                </button>
                <button onclick="window.location.href='leaves.php'" style="padding:16px; border:1px solid var(--border-card); border-radius:12px; background:var(--bg-card); cursor:pointer; text-align:center; transition:0.2s;" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--border-card)'">
                    <i class="fas fa-calendar-alt" style="font-size:24px; color:#10b981; margin-bottom:8px;"></i><br>
                    <span style="font-weight:600; color:var(--text-heading);">Review Leaves</span>
                </button>
                <button onclick="window.location.href='payroll.php'" style="padding:16px; border:1px solid var(--border-card); border-radius:12px; background:var(--bg-card); cursor:pointer; text-align:center; transition:0.2s;" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border-card)'">
                    <i class="fas fa-file-invoice-dollar" style="font-size:24px; color:#f59e0b; margin-bottom:8px;"></i><br>
                    <span style="font-weight:600; color:var(--text-heading);">Run Payroll</span>
                </button>
                <button onclick="window.location.href='performance_reviews.php'" style="padding:16px; border:1px solid var(--border-card); border-radius:12px; background:var(--bg-card); cursor:pointer; text-align:center; transition:0.2s;" onmouseover="this.style.borderColor='#ec4899'" onmouseout="this.style.borderColor='var(--border-card)'">
                    <i class="fas fa-star" style="font-size:24px; color:#ec4899; margin-bottom:8px;"></i><br>
                    <span style="font-weight:600; color:var(--text-heading);">Reviews</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
