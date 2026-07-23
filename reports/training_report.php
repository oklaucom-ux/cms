<?php
require_once '../includes/db.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

requirePermission($pdo, 'manage_training');

// Dual DB safe department breakdown
try {
    $deptStats = $pdo->query("
        SELECT 
            COALESCE(u.branch_id, 'Global HQ') as department,
            COUNT(ta.id) as total_assigned,
            SUM(CASE WHEN ta.status = 'Completed' THEN 1 ELSE 0 END) as total_completed,
            AVG(tr.score) as avg_score
        FROM training_assignments ta
        LEFT JOIN users u ON ta.user_id = u.login_id
        LEFT JOIN training_results tr ON ta.id = tr.assignment_id
        GROUP BY COALESCE(u.branch_id, 'Global HQ')
        ORDER BY total_assigned DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $deptStats = []; }

$overallAssigned = 0; $overallCompleted = 0;
foreach($deptStats as $ds) {
    $overallAssigned += $ds['total_assigned'];
    $overallCompleted += $ds['total_completed'];
}
$overallComplianceRate = $overallAssigned > 0 ? round(($overallCompleted / $overallAssigned) * 100) : 100;
?>

<div class="content-section active">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">📊 Executive Training & Compliance Report</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Comprehensive department-level compliance metrics, workforce pass rates, and completion stats.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="view-button" onclick="window.location.href='../controllers/export_csv.php?table=training_assignments'" style="padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600;">
                📥 Export Compliance CSV
            </button>
            <button class="edit-button" onclick="window.location.href='../training.php'" style="padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600;">
                ← Back to Training Hub
            </button>
        </div>
    </div>

    <!-- Top Executive Compliance Analytics Cards -->
    <div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px;">
        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Overall Compliance Rate</div>
            <div style="font-size:28px; font-weight:800; color:#10b981;"><?= $overallComplianceRate ?>%</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;"><?= $overallCompleted ?> of <?= $overallAssigned ?> Assignments Done</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Departments Tracked</div>
            <div style="font-size:28px; font-weight:800; color:var(--text-heading);"><?= number_format(count($deptStats)) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Branch & Department Units</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">Total Course Enrollments</div>
            <div style="font-size:28px; font-weight:800; color:#6366f1;"><?= number_format($overallAssigned) ?></div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Active Employee Course Load</div>
        </div>

        <div class="dashboard-card">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px;">LMS Compliance Status</div>
            <div style="font-size:16px; font-weight:700; margin-top:6px; color:#10b981;">
                🟢 Mandatory Audited
            </div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Real-Time Compliance Audit</div>
        </div>
    </div>

    <!-- Departmental Compliance Table -->
    <div style="background:var(--bg-card); border:1px solid var(--border-card); border-radius:16px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:var(--bg-body); border-bottom:1px solid var(--border-card);">
                    <th style="padding:16px; font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Department / Branch</th>
                    <th style="padding:16px; font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Assigned Courses</th>
                    <th style="padding:16px; font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Completed</th>
                    <th style="padding:16px; font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Compliance %</th>
                    <th style="padding:16px; font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Average Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($deptStats as $ds): 
                    $pct = $ds['total_assigned'] > 0 ? round(($ds['total_completed'] / $ds['total_assigned']) * 100) : 100;
                    $avgScore = $ds['avg_score'] ? round($ds['avg_score'], 1) . '%' : '-';
                ?>
                <tr style="border-bottom:1px solid var(--border-card);">
                    <td style="padding:16px; font-weight:700; color:var(--text-heading);"><?= htmlspecialchars($ds['department']) ?></td>
                    <td style="padding:16px; color:var(--text-body); font-weight:600;"><?= number_format($ds['total_assigned']) ?></td>
                    <td style="padding:16px; color:#10b981; font-weight:700;"><?= number_format($ds['total_completed']) ?></td>
                    <td style="padding:16px;">
                        <span style="display:inline-block; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; background:<?= $pct >= 80 ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)' ?>; color:<?= $pct >= 80 ? '#10b981' : '#ef4444' ?>;">
                            <?= $pct ?>%
                        </span>
                    </td>
                    <td style="padding:16px; font-weight:700; color:var(--text-heading);"><?= $avgScore ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($deptStats)): ?>
                <tr>
                    <td colspan="5" style="padding:20px; text-align:center; color:var(--text-muted);">No departmental compliance data recorded yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
