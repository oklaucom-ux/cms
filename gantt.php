<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_projects');

$projects = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Enrich with tasks
foreach ($projects as &$p) {
    $tasks = $pdo->prepare("SELECT * FROM tasks WHERE project_id=? AND status != 'Deleted' ORDER BY due_date ASC");
    $tasks->execute([$p['id']]);
    $p['tasks'] = $tasks->fetchAll(PDO::FETCH_ASSOC);
    $expenses = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE project_id=? AND status='Approved'");
    $expenses->execute([$p['id']]);
    $p['spent'] = $expenses->fetchColumn() ?: 0;
}
unset($p);

// Compute global date range
$allDates = [];
foreach ($projects as $p) {
    if ($p['created_at']) $allDates[] = strtotime($p['created_at']);
    if ($p['deadline'])   $allDates[] = strtotime($p['deadline']);
    foreach ($p['tasks'] as $t) {
        if ($t['due_date']) $allDates[] = strtotime($t['due_date']);
    }
}
$minDate = $allDates ? min($allDates) : time();
$maxDate = $allDates ? max($allDates) : strtotime('+90 days');
$totalDays = max(1, ceil(($maxDate - $minDate) / 86400)) + 14;
?>
<style>
.gantt-wrap { overflow-x:auto; }
.gantt-table { min-width:900px; border-collapse:collapse; width:100%; }
.gantt-table th { background:var(--bg-card); color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:.07em; padding:10px 14px; border-bottom:1px solid var(--border-card); font-weight:700; }
.gantt-table td { padding:8px 14px; border-bottom:1px solid var(--border-card); vertical-align:middle; }
.gantt-bar-cell { position:relative; height:38px; }
.gantt-bar { position:absolute; height:22px; top:8px; border-radius:6px; display:flex; align-items:center; padding:0 8px; font-size:11px; font-weight:700; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:4px; transition:opacity .15s; }
.gantt-bar:hover { opacity:.85; }
.gantt-today { position:absolute; top:0; bottom:0; width:2px; background:#ef4444; z-index:5; }
.gantt-today::before { content:'Today'; position:absolute; top:-18px; left:-16px; font-size:10px; color:#ef4444; font-weight:700; white-space:nowrap; }
.status-pill { padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
.legend { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
.leg { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
.leg-dot { width:12px; height:12px; border-radius:3px; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>📅 Project Gantt Chart</h2>
        <button class="view-button" onclick="window.location.href='projects.php'" style="text-decoration:none;">← Back to Projects</button>
    </div>

    <div class="legend">
        <div class="leg"><div class="leg-dot" style="background:#6366f1"></div>Planning</div>
        <div class="leg"><div class="leg-dot" style="background:#10b981"></div>Active</div>
        <div class="leg"><div class="leg-dot" style="background:#f59e0b"></div>On Hold</div>
        <div class="leg"><div class="leg-dot" style="background:#9ca3af"></div>Completed</div>
        <div class="leg"><div class="leg-dot" style="background:#3b82f6;opacity:.5"></div>Task</div>
        <div class="leg"><div class="leg-dot" style="background:#ef4444;width:2px;border-radius:0"></div>Today</div>
    </div>

    <?php if(empty($projects)): ?>
        <div style="text-align:center;padding:60px;color:var(--text-muted);">No projects yet. <a href="projects.php" style="color:#6366f1;">Create one →</a></div>
    <?php else: ?>
    <div class="gantt-wrap" style="position:relative;">
        <svg id="gantt-svg" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:10;"></svg>
        <table class="gantt-table" id="ganttTable">
            <thead>
                <tr>
                    <th style="width:180px;">Project / Task</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:80px;">Budget</th>
                    <th>
                        <!-- Month headers -->
                        <div style="position:relative;height:20px;">
                            <?php
                            $cur = $minDate;
                            while ($cur < $maxDate + 86400*14) {
                                $pct = (($cur - $minDate) / ($totalDays * 86400)) * 100;
                                echo "<span style='position:absolute;left:{$pct}%;font-size:10px;color:var(--text-muted);white-space:nowrap;'>" . date('M d', $cur) . "</span>";
                                $cur = strtotime('+14 days', $cur);
                            }
                            ?>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php
            $statusColors = ['Planning'=>'#6366f1','Active'=>'#10b981','On Hold'=>'#f59e0b','Completed'=>'#9ca3af'];
            $todayPct = ((time() - $minDate) / ($totalDays * 86400)) * 100;

            foreach ($projects as $p):
                $pStart = strtotime($p['created_at']);
                $pEnd   = $p['deadline'] ? strtotime($p['deadline']) : strtotime('+30 days', $pStart);
                $left   = max(0, (($pStart - $minDate) / ($totalDays * 86400)) * 100);
                $width  = min(100 - $left, (($pEnd - $pStart) / ($totalDays * 86400)) * 100);
                $barColor = $statusColors[$p['status']] ?? '#6366f1';
                $burnPct = $p['budget'] > 0 ? round(($p['spent'] / $p['budget']) * 100) : 0;
            ?>
                <!-- Project Row -->
                <tr style="background:var(--bg-card);">
                    <td>
                        <strong style="color:var(--text-heading);font-size:14px;"><?= htmlspecialchars($p['name']) ?></strong>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($p['client_id'] ?: 'Internal') ?></div>
                    </td>
                    <td><span class="status-pill" style="background:<?= $barColor ?>22;color:<?= $barColor ?>;"><?= htmlspecialchars($p['status']) ?></span></td>
                    <td>
                        <div style="font-size:12px;font-weight:700;color:<?= $burnPct>=90?'#dc2626':($burnPct>=70?'#f59e0b':'#111827') ?>;"><?= $burnPct ?>%</div>
                        <div style="font-size:10px;color:var(--text-muted);"><?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($p['spent']) ?> / <?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($p['budget']) ?></div>
                    </td>
                    <td>
                        <div class="gantt-bar-cell">
                            <!-- Today line -->
                            <?php if($todayPct >= 0 && $todayPct <= 100): ?><div class="gantt-today" style="left:<?= round($todayPct,1) ?>%"></div><?php endif; ?>
                            <div class="gantt-bar" style="left:<?= round($left,1) ?>%;width:<?= round($width,1) ?>%;background:<?= $barColor ?>;" title="<?= htmlspecialchars($p['name']) ?> — <?= date('M d', $pStart) ?> to <?= date('M d', $pEnd) ?>">
                                <?= htmlspecialchars(substr($p['name'], 0, 24)) ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <!-- Task Rows -->
                <?php foreach($p['tasks'] as $t):
                    $tEnd  = $t['due_date'] ? strtotime($t['due_date']) : time();
                    $tStart= strtotime($t['created_at'] ?? $p['created_at']);
                    $tLeft = max(0, (($tStart - $minDate) / ($totalDays * 86400)) * 100);
                    $tWidth= min(100-$tLeft, max(0.5, (($tEnd-$tStart) / ($totalDays * 86400)) * 100));
                    $tColor= $t['status']==='Done'||$t['status']==='Completed' ? '#10b981' : ($t['priority']==='High'?'#ef4444':'#3b82f6');
                ?>
                <tr>
                    <td style="padding-left:28px;">
                        <span style="font-size:13px;color:var(--text-body);">└ <?= htmlspecialchars($t['name']) ?></span>
                    </td>
                    <td><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($t['priority'] ?? '') ?></span></td>
                    <td><span style="font-size:11px;color:var(--text-muted);"><?= $t['due_date'] ? date('M d', strtotime($t['due_date'])) : '—' ?></span></td>
                    <td>
                        <div class="gantt-bar-cell">
                            <div id="taskBar_<?= $t['id'] ?>" data-dep="<?= $t['dependency_id'] ?>" class="gantt-bar" style="left:<?= round($tLeft,1) ?>%;width:<?= round($tWidth,1) ?>%;background:<?= $tColor ?>;opacity:.7;height:14px;top:12px;" title="<?= htmlspecialchars($t['name']) ?>"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    window.addEventListener('load', () => {
        const svg = document.getElementById('gantt-svg');
        const container = document.querySelector('.gantt-wrap');
        const tasks = document.querySelectorAll('[id^="taskBar_"]');
        
        let pathDefs = '';
        tasks.forEach(t => {
            const depId = t.dataset.dep;
            if (depId && depId != "0") {
                const parent = document.getElementById('taskBar_' + depId);
                if (parent) {
                    const rContainer = container.getBoundingClientRect();
                    const rT = t.getBoundingClientRect();
                    const rP = parent.getBoundingClientRect();

                    // Parent right edge coords
                    const px = (rP.right - rContainer.left);
                    const py = (rP.top - rContainer.top) + (rP.height/2);

                    // Child left edge coords
                    const cx = (rT.left - rContainer.left);
                    const cy = (rT.top - rContainer.top) + (rT.height/2);

                    // Draw a step line (arrow)
                    pathDefs += `<path d="M ${px} ${py} L ${px+10} ${py} L ${px+10} ${cy} L ${cx-5} ${cy}" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="4,2"/>`;
                    pathDefs += `<polygon points="${cx-5},${cy-3} ${cx},${cy} ${cx-5},${cy+3}" fill="#ef4444"/>`;
                }
            }
        });
        if(svg) svg.innerHTML = pathDefs;
    });
    </script>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
