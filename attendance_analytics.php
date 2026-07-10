<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_users');

$month = $_GET['month'] ?? date('Y-m');
$year  = substr($month, 0, 4);
$mon   = substr($month, 5, 2);

// All active users
$users = $pdo->query("SELECT login_id, name, department FROM users WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Days in month
$daysInMonth = date('t', mktime(0, 0, 0, (int)$mon, 1, (int)$year));

// Per-user stats
$stats = [];
foreach ($users as $u) {
    $uid = $u['login_id'];
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id=? AND DATE_FORMAT(date, '%Y-%m')=?");
    $stmt->execute([$uid, $month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $present = count(array_filter($rows, fn($r) => !empty($r['clock_in'])));
    $totalHours = 0;
    $lateCount  = 0;
    foreach ($rows as $r) {
        if ($r['clock_in'] && $r['clock_out']) {
            $hrs = (strtotime($r['clock_out']) - strtotime($r['clock_in'])) / 3600;
            $totalHours += $hrs;
        }
        // Late = clocked in after 9:15 AM
        if ($r['clock_in'] && date('H:i', strtotime($r['clock_in'])) > '09:15') $lateCount++;
    }
    $pct = $daysInMonth > 0 ? round(($present / $daysInMonth) * 100, 1) : 0;
    $avgHours = $present > 0 ? round($totalHours / $present, 1) : 0;
    $stats[] = array_merge($u, ['present'=>$present,'days'=>$daysInMonth,'pct'=>$pct,'total_hours'=>round($totalHours,1),'avg_hours'=>$avgHours,'late'=>$lateCount]);
}

// Sort by attendance %
usort($stats, fn($a,$b) =>$b['pct'] <=>$a['pct']);

// Company-wide averages
$totalPct = count($stats) > 0 ? round(array_sum(array_column($stats,'pct')) / count($stats), 1) : 0;
$totalLate = array_sum(array_column($stats,'late'));
?>
<div class="content-section active">
    <div class="section-header">
        <h2>📊 Attendance Analytics</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <input type="month" value="<?= $month ?>" onchange="window.location='attendance_analytics.php?month='+this.value" style="padding:8px 14px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text-body);font-size:14px;">
            <a href="controllers/export_csv.php?table=attendance&month=<?= $month ?>" class="view-button" style="text-decoration:none;padding:10px 18px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border-card);font-size:13px;font-weight:600;color:var(--text-body);">📥 Export</a>
        </div>
    </div>

    <!-- Company KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:28px;">
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Avg Attendance</div>
            <div style="font-size:32px;font-weight:800;color:#6366f1;"><?= $totalPct ?>%</div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Employees Tracked</div>
            <div style="font-size:32px;font-weight:800;color:#10b981;"><?= count($stats) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Late Arrivals</div>
            <div style="font-size:32px;font-weight:800;color:#f59e0b;"><?= $totalLate ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Working Days</div>
            <div style="font-size:32px;font-weight:800;color:#3b82f6;"><?= $daysInMonth ?></div>
        </div>
    </div>

    <!-- Chart + Table -->
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:28px;">
        <div style="background:var(--bg-card);border-radius:14px;padding:20px;border:1px solid var(--border-card);">
            <h4 style="margin-bottom:16px;color:var(--text-heading);font-size:15px;">Attendance Distribution</h4>
            <div style="position:relative;height:220px;"><canvas id="attChart"></canvas></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:20px;border:1px solid var(--border-card);">
            <h4 style="margin-bottom:16px;color:var(--text-heading);font-size:15px;">Top Attendance — <?= date('F Y', mktime(0,0,0,(int)$mon,1,(int)$year)) ?></h4>
            <?php foreach(array_slice($stats,0,8) as $s):
                $barColor = $s['pct'] >= 90 ? '#10b981' : ($s['pct'] >= 70 ? '#f59e0b' : '#dc2626');
            ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:110px;font-size:13px;font-weight:600;color:var(--text-heading);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($s['name']) ?></div>
                <div style="flex:1;background:#f3f4f6;border-radius:99px;height:10px;overflow:hidden;">
                    <div style="background:<?= $barColor ?>;height:100%;width:<?= $s['pct'] ?>%;border-radius:99px;transition:width .5s;"></div>
                </div>
                <div style="width:50px;text-align:right;font-size:13px;font-weight:700;color:<?= $barColor ?>;"><?= $s['pct'] ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Detail Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr><th>Employee</th><th>Dept</th><th>Days Present</th><th>Attendance %</th><th>Total Hours</th><th>Avg Hours/Day</th><th>Late Arrivals</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach($stats as $s):
                    $color = $s['pct'] >= 90 ? '#16a34a' : ($s['pct'] >= 70 ? '#d97706' : '#dc2626');
                    $bg    = $s['pct'] >= 90 ? '#dcfce7' : ($s['pct'] >= 70 ? '#fef3c7' : '#fee2e2');
                    $label = $s['pct'] >= 90 ? 'Excellent' : ($s['pct'] >= 70 ? 'Satisfactory' : 'Needs Review');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong><br><small><?= htmlspecialchars($s['login_id']) ?></small></td>
                    <td><?= htmlspecialchars($s['department'] ?? '—') ?></td>
                    <td><?= $s['present'] ?> / <?= $s['days'] ?></td>
                    <td><strong style="color:<?= $color ?>;"><?= $s['pct'] ?>%</strong></td>
                    <td><?= $s['total_hours'] ?>h</td>
                    <td><?= $s['avg_hours'] ?>h</td>
                    <td><?= $s['late'] > 0 ? "<span style='color:#f59e0b;font-weight:700;'>{$s['late']}</span>" : '—' ?></td>
                    <td><span style="background:<?= $bg ?>;color:<?= $color ?>;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;"><?= $label ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const stats = <?= json_encode(array_map(fn($s) => ['name'=>$s['name'],'pct'=>$s['pct']], $stats)) ?>;
const excellent = stats.filter(s=>s.pct>=90).length;
const satisfactory = stats.filter(s=>s.pct>=70&&s.pct<90).length;
const poor = stats.filter(s=>s.pct<70).length;
new Chart(document.getElementById('attChart'), {
    type: 'doughnut',
    data: {
        labels: ['Excellent (≥90%)', 'Satisfactory (70–89%)', 'Needs Review (<70%)'],
        datasets: [{ data: [excellent, satisfactory, poor], backgroundColor: ['#10b981','#f59e0b','#ef4444'], borderWidth: 0 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:12 } } } }
});
</script>
<?php require_once 'includes/footer.php'; ?>
