<?php
require_once 'includes/db.php';
requirePermission($pdo, 'view_hr_dashboard'); // Specific HR dashboard permission
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
    $attStmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = ? OR date(clock_in) = ?");
    $attStmt->execute([$today, $today]);
    $presentToday = $attStmt->fetchColumn() ?: 0;
} catch(Exception $e) { $presentToday = 0; }

// Recent HR Activity (Dual Join for Users & Super Admins)
try {
    $recentLeaves = $pdo->query("SELECT l.*, COALESCE(u.name, sa.name, l.user_id) as user_name FROM leaves l LEFT JOIN users u ON l.user_id = u.login_id LEFT JOIN super_admins sa ON l.user_id = sa.login_id ORDER BY l.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $recentLeaves = []; }
?>

<style>
    .glass-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 0 0 24px 24px;
        padding: 40px;
        margin: -20px -20px 30px -20px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        cursor: pointer;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .glass-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .action-btn {
        padding: 20px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-body);
        cursor: pointer;
        text-align: center;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.05);
    }
    .badge-modern {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 11px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
</style>

<div class="content-section active" style="padding-top:0;">

    <div class="glass-header">
        <div style="position: relative; z-index: 2;">
            <h1 class="text-white" style="margin: 0 0 10px 0; font-size: 32px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff !important;">
                <i class="fas fa-users-cog" style="margin-right:12px; color:#38bdf8;"></i> Human Resources Dashboard
            </h1>
            <p class="text-light" style="margin: 0; font-size: 16px; color: #cbd5e1 !important;">Manage headcount, attendance, leaves, and recruitment.</p>
        </div>
        <!-- Decorative background elements -->
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(56, 189, 248, 0.1); border-radius: 50%; filter: blur(30px); z-index: 1;"></div>
        <div style="position: absolute; bottom: -50px; left: 10%; width: 150px; height: 150px; background: rgba(139, 92, 246, 0.1); border-radius: 50%; filter: blur(30px); z-index: 1;"></div>
    </div>

    <!-- HR KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px;">
        <!-- Headcount -->
        <div class="stat-card" onclick="window.location.href='org_chart.php'" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Total Headcount</div>
                <div class="stat-icon" style="background:rgba(79,70,229,0.1); color:#4f46e5;"><i class="fas fa-users"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:var(--text-heading); margin:16px 0;"><?= $headcount ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;"><span style="color:#10b981;"><i class="fas fa-arrow-up me-1"></i>Active</span> employees</div>
        </div>

        <!-- Present Today -->
        <div class="stat-card" onclick="window.location.href='attendance.php'" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Attendance Today</div>
                <div class="stat-icon" style="background:rgba(16,185,129,0.1); color:#10b981;"><i class="fas fa-clock"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:#10b981; margin:16px 0;"><?= $presentToday ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Employees logged in</div>
        </div>

        <!-- Leaves Pending -->
        <div class="stat-card" onclick="window.location.href='leaves.php'" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Leave Requests</div>
                <div class="stat-icon" style="background:rgba(245,158,11,0.1); color:#f59e0b;"><i class="fas fa-umbrella-beach"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:#f59e0b; margin:16px 0;"><?= $leavesPending ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Awaiting approval</div>
        </div>

        <!-- Open Jobs -->
        <div class="stat-card" onclick="window.location.href='recruitment.php'" onmouseover="this.style.borderColor='#ef4444'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; font-weight:800; letter-spacing:1px;">Open Applicants</div>
                <div class="stat-icon" style="background:rgba(239,68,68,0.1); color:#ef4444;"><i class="fas fa-user-tie"></i></div>
            </div>
            <div style="font-size:36px; font-weight:900; color:#ef4444; margin:16px 0;"><?= $openJobs ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600;">Active in ATS</div>
        </div>
    </div>

    <!-- Layout for recent activity -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:30px;">
        
        <!-- Recent Leave Requests -->
        <div class="glass-panel">
            <h4 style="margin:0 0 20px 0; color:var(--text-heading); font-size:18px; font-weight:700; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-calendar-check text-success"></i> Recent Leave Requests
            </h4>
            
            <?php if(empty($recentLeaves)): ?>
                <div style="text-align:center; padding:30px 10px; color:var(--text-muted);">
                    <i class="fas fa-umbrella-beach fs-3 mb-2 d-block opacity-50"></i>
                    No recent leave requests.
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach($recentLeaves as $leave): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px; border-radius:12px; border:1px solid var(--border-color); background:var(--bg-body); transition: background 0.2s;" onmouseover="this.style.background='var(--bg-card)'" onmouseout="this.style.background='var(--bg-body)'">
                            <div>
                                <strong style="color:var(--text-heading); font-size:15px; display:block; margin-bottom:4px;"><?= htmlspecialchars($leave['user_name']) ?></strong>
                                <span style="font-size:13px; color:var(--text-muted);"><i class="fas fa-calendar-day me-1"></i><?= htmlspecialchars($leave['leave_type']) ?> (<?= $leave['days'] ?> days)</span>
                            </div>
                            <div>
                                <?php
                                    $bg = $leave['status'] == 'Approved' ? '#d1fae5' : ($leave['status'] == 'Pending' ? '#fef3c7' : '#fee2e2');
                                    $tc = $leave['status'] == 'Approved' ? '#065f46' : ($leave['status'] == 'Pending' ? '#92400e' : '#991b1b');
                                    $icon = $leave['status'] == 'Approved' ? 'fa-check-circle' : ($leave['status'] == 'Pending' ? 'fa-clock' : 'fa-times-circle');
                                ?>
                                <span class="badge-modern" style="background: <?= $bg ?>; color: <?= $tc ?>;">
                                    <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($leave['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick HR Actions -->
        <div class="glass-panel">
            <h4 style="margin:0 0 20px 0; color:var(--text-heading); font-size:18px; font-weight:700; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-bolt" style="color:#8b5cf6;"></i> Quick Actions
            </h4>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <button class="action-btn" onclick="window.location.href='recruitment.php'" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="this.style.borderColor='var(--border-color)'">
                    <i class="fas fa-user-plus" style="font-size:28px; color:#4f46e5;"></i>
                    <span style="font-weight:700; font-size:14px; color:var(--text-heading);">New Hire</span>
                </button>
                <button class="action-btn" onclick="window.location.href='leaves.php'" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--border-color)'">
                    <i class="fas fa-calendar-alt" style="font-size:28px; color:#10b981;"></i>
                    <span style="font-weight:700; font-size:14px; color:var(--text-heading);">Review Leaves</span>
                </button>
                <button class="action-btn" onclick="window.location.href='payroll.php'" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border-color)'">
                    <i class="fas fa-file-invoice-dollar" style="font-size:28px; color:#f59e0b;"></i>
                    <span style="font-weight:700; font-size:14px; color:var(--text-heading);">Run Payroll</span>
                </button>
                <button class="action-btn" onclick="window.location.href='performance_reviews.php'" onmouseover="this.style.borderColor='#ec4899'" onmouseout="this.style.borderColor='var(--border-color)'">
                    <i class="fas fa-star" style="font-size:28px; color:#ec4899;"></i>
                    <span style="font-weight:700; font-size:14px; color:var(--text-heading);">Reviews</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
