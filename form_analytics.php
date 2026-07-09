<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_settings');

$forms = $pdo->query("SELECT * FROM dynamic_forms ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$selected_form_id = intval($_GET['form_id'] ?? ($forms[0]['id'] ?? 0));

$selectedForm = null;
$submissions  = [];
$analytics    = [];

if ($selected_form_id) {
    foreach ($forms as $f) { if ($f['id'] == $selected_form_id) { $selectedForm = $f; break; } }

    $subs = $pdo->prepare("SELECT * FROM form_submissions WHERE form_id=? ORDER BY submitted_at DESC");
    $subs->execute([$selected_form_id]);
    $submissions = $subs->fetchAll(PDO::FETCH_ASSOC);

    // Parse schema for field analysis
    if ($selectedForm) {
        $schema = json_decode($selectedForm['schema_json'] ?? '[]', true) ?: [];
        foreach ($schema as $field) {
            if (!in_array($field['type'] ?? '', ['dropdown','radio','checkbox'])) continue;
            $counts = [];
            foreach ($submissions as $sub) {
                $data = json_decode($sub['data_json'] ?? '{}', true) ?: [];
                $val = $data[$field['label']] ?? null;
                if ($val !== null) {
                    if (is_array($val)) { foreach ($val as $v) $counts[$v] = ($counts[$v] ?? 0) + 1; }
                    else { $counts[$val] = ($counts[$val] ?? 0) + 1; }
                }
            }
            if (!empty($counts)) $analytics[] = ['field' =>$field['label'], 'type' =>$field['type'], 'counts' =>$counts];
        }
    }
}

// Submission trend — last 14 days
$trend = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE form_id=? AND date(submitted_at)=?");
    $stmt->execute([$selected_form_id, $date]);
    $trend[] = ['date' => date('M d', strtotime($date)), 'count' => (int)$stmt->fetchColumn()];
}
?>
<div class="content-section active">
    <div class="section-header">
        <h2>📊 Form Response Analytics</h2>
        <?php if(!empty($forms)): ?>
        <select onchange="window.location='form_analytics.php?form_id='+this.value" style="padding:10px 16px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text-body);font-size:14px;font-weight:600;">
            <?php foreach($forms as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $f['id']==$selected_form_id?'selected':'' ?>><?= htmlspecialchars($f['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <?php if(!$selectedForm): ?>
        <div style="text-align:center;padding:60px;color:var(--text-muted);">No forms available yet. Create a form first.</div>
    <?php else: ?>

    <!-- KPI strip -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:28px;">
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Total Responses</div>
            <div style="font-size:32px;font-weight:800;color:#6366f1;"><?= count($submissions) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Last 7 Days</div>
            <div style="font-size:32px;font-weight:800;color:#10b981;"><?= array_sum(array_column(array_slice($trend,-7),'count')) ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Frequency</div>
            <div style="font-size:20px;font-weight:800;color:#f59e0b;margin-top:6px;"><?= htmlspecialchars($selectedForm['frequency'] ?? '—') ?></div>
        </div>
        <div style="background:var(--bg-card);border-radius:14px;padding:18px;border:1px solid var(--border-card);">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Unique Respondents</div>
            <div style="font-size:32px;font-weight:800;color:#3b82f6;"><?= count(array_unique(array_column($submissions,'user_id'))) ?></div>
        </div>
    </div>

    <!-- Trend chart -->
    <div style="background:var(--bg-card);border-radius:16px;padding:24px;border:1px solid var(--border-card);margin-bottom:24px;">
        <h4 style="color:var(--text-heading);margin-bottom:16px;font-size:15px;font-weight:700;">📈 Submission Trend — Last 14 Days</h4>
        <div style="position:relative;height:200px;"><canvas id="trendChart"></canvas></div>
    </div>

    <!-- Field analytics -->
    <?php if(!empty($analytics)): ?>
    <h3 style="color:var(--text-heading);font-size:16px;font-weight:700;margin-bottom:16px;">Question Breakdown</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px;margin-bottom:24px;">
        <?php foreach($analytics as $idx =>$a):
            $total = array_sum($a['counts']);
            arsort($a['counts']);
        ?>
        <div style="background:var(--bg-card);border-radius:14px;padding:20px;border:1px solid var(--border-card);">
            <h4 style="color:var(--text-heading);font-size:14px;font-weight:700;margin-bottom:14px;"><?= htmlspecialchars($a['field']) ?></h4>
            <?php foreach($a['counts'] as $opt =>$cnt):
                $pct = $total > 0 ? round(($cnt/$total)*100) : 0;
                $colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6'];
                $color = $colors[$idx % count($colors)];
            ?>
            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span style="color:var(--text-body);"><?= htmlspecialchars($opt) ?></span>
                    <span style="font-weight:700;color:var(--text-heading);"><?= $cnt ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:#f3f4f6;border-radius:99px;height:8px;overflow:hidden;">
                    <div style="background:<?= $color ?>;height:100%;width:<?= $pct ?>%;border-radius:99px;transition:width .5s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="position:relative;height:120px;margin-top:12px;"><canvas id="pie_<?= $idx ?>"></canvas></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recent submissions table -->
    <div class="data-table">
        <table>
            <thead><tr><th>Respondent</th><th>Submitted At</th><th>Response Preview</th></tr></thead>
            <tbody>
                <?php foreach(array_slice($submissions,0,20) as $s):
                    $data = json_decode($s['data_json'] ?? '{}', true) ?: [];
                    $preview = implode(' · ', array_map(fn($k,$v) => "{$k}: ".(is_array($v)?implode(',',$v):$v), array_keys(array_slice($data,0,3)), array_values(array_slice($data,0,3))));
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['user_id'] ?? 'Anonymous') ?></strong></td>
                    <td><?= date('M d, Y H:i', strtotime($s['submitted_at'])) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($preview) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($submissions)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:32px;">No responses yet for this form.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendData = <?= json_encode($trend) ?>;
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: trendData.map(d=>d.date),
        datasets: [{ label:'Submissions', data: trendData.map(d=>d.count), backgroundColor:'rgba(99,102,241,0.7)', borderRadius:6 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});
<?php foreach($analytics as $idx =>$a): ?>
new Chart(document.getElementById('pie_<?= $idx ?>'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($a['counts'])) ?>,
        datasets: [{ data: <?= json_encode(array_values($a['counts'])) ?>, backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6'], borderWidth:0 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ font:{size:10}, padding:8 } } } }
});
<?php endforeach; ?>
</script>
<?php require_once 'includes/footer.php'; ?>
