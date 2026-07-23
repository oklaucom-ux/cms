<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_crm');


$canCreateLeads = hasPermission($pdo, 'create_leads');
$canEditLeads   = hasPermission($pdo, 'edit_leads');
$canConvert     = hasPermission($pdo, 'convert_leads');
$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Manager');

// Fetch API Key
$myApiKey = '';
if ($isAdmin) {
    $stmtKey = $pdo->prepare("SELECT api_key FROM api_keys WHERE user_id = ?");
    $stmtKey->execute([$_SESSION['login_id']]);
    $myApiKey = $stmtKey->fetchColumn();
}

// Auto-Migrate schema gracefully
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_leads (
        id {$pkDef},
        lead_name VARCHAR(255) NOT NULL,
        company VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        value DECIMAL(12,2) DEFAULT 0,
        stage VARCHAR(50) DEFAULT 'Prospect',
        owner_id VARCHAR(255),
        branch_id VARCHAR(255) DEFAULT 'Global HQ',
        follow_up_date DATE,
        last_contact DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Fetch leads based on role/branch
if ($isAdmin) {
    $leads = $pdo->query("SELECT * FROM crm_leads ORDER BY last_contact DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Managers/Users only see leads in their branch
    $branchStmt = $pdo->prepare("SELECT branch_id FROM users WHERE login_id = ?");
    $branchStmt->execute([$_SESSION['login_id']]);
    $myBranch = $branchStmt->fetchColumn() ?: 'Global HQ';
    
    $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE branch_id = ? ORDER BY last_contact DESC");
    $stmt->execute([$myBranch]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all users for assignments
$allUsers = $pdo->query("SELECT login_id, name FROM users")->fetchAll(PDO::FETCH_ASSOC);

// Pipeline stages
$stages = ['Prospect', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];

// Build board
$board = array_fill_keys($stages, []);
foreach ($leads as $l) {
    $s = $l['stage'];
    if (isset($board[$s])) $board[$s][] = $l;
    else $board['Prospect'][] = $l;
}

// Pipeline value stats
$pipelineStats = $pdo->query("
    SELECT 
        SUM(CASE WHEN stage NOT IN ('Won','Lost') THEN value ELSE 0 END) AS pipeline_value,
        SUM(CASE WHEN stage='Won' THEN value ELSE 0 END) AS won_value,
        COUNT(CASE WHEN stage NOT IN ('Won','Lost') THEN 1 END) AS active_count,
        COUNT(CASE WHEN stage='Won' THEN 1 END) AS won_count
    FROM crm_leads
")->fetch(PDO::FETCH_ASSOC);

$stageColors = [
    'Prospect'    => '#6366f1',
    'Qualified'   => '#3b82f6',
    'Proposal'    => '#f59e0b',
    'Negotiation' => '#ef4444',
    'Won'         => '#10b981',
    'Lost'        => '#9ca3af',
];
?>
<style>
.crm-board { display:flex; gap:20px; overflow-x:auto; padding-bottom:24px; min-height:calc(100vh - 260px); scroll-behavior: smooth; }
.crm-col { flex:0 0 280px; background: rgba(15,23,42,0.4); border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:16px; display:flex; flex-direction:column; gap:14px; backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); box-shadow:0 4px 20px rgba(0,0,0,0.15); transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.crm-col-header { font-weight:800; font-size:15px; color:var(--text-heading); display:flex; justify-content:space-between; align-items:center; padding-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.06); }
.crm-badge { padding:4px 12px; border-radius:99px; font-size:12px; font-weight:800; color:white; box-shadow:0 2px 8px rgba(0,0,0,0.2); }
.crm-card { background: var(--bg-card); border:1px solid var(--border-card); border-left-width:4px; border-radius:14px; padding:18px; box-shadow:0 4px 12px rgba(0,0,0,0.06); cursor:pointer; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.crm-card:hover { transform:translateY(-6px) scale(1.02); box-shadow:0 16px 32px rgba(0,0,0,0.12); z-index:10; border-color: rgba(236,72,153,0.3); }
.crm-card.dragging { opacity:0.5; transform:scale(1.05) rotate(2deg); box-shadow:0 24px 48px rgba(236,72,153,0.2); }
.crm-col.drag-over { background: rgba(79,70,229,0.08); border:2px dashed rgba(236,72,153,0.5); transform:scale(1.01); box-shadow: 0 0 30px rgba(236,72,153,0.1); }
.crm-card-name { font-weight:800; font-size:15px; color:var(--text-heading); margin-bottom:4px; }
.crm-card-company { font-size:13px; color:var(--text-muted); margin-bottom:10px; font-weight:600; }
.crm-card-value { font-size:22px; font-weight:900; background:linear-gradient(135deg, #10b981, #059669); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.crm-card-meta { font-size:11px; color:var(--text-muted); margin-top:10px; font-weight:500; border-top:1px solid var(--border-card); padding-top:10px; line-height:1.6; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2 style="background: linear-gradient(135deg, #4f46e5, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 24px; font-weight: 900; letter-spacing: -0.5px;">🎯 Sales Pipeline CRM</h2>
        <div style="display:flex; gap:12px;">
            <?php if($isAdmin): ?>
            <button class="premium-btn" style="background:linear-gradient(135deg, #10b981, #059669);" onclick="document.getElementById('syncSheetModal').style.display='block'">🔄 Sync Google Sheet</button>
            <button class="premium-btn" style="background:linear-gradient(135deg, #4b5563, #374151);" onclick="document.getElementById('apiIntegrationModal').style.display='block'">🔌 API Settings</button>
            <a href="controllers/export_leads.php?format=csv" class="premium-btn" style="background:linear-gradient(135deg, #0ea5e9, #0284c7); text-decoration:none;">📥 Export CSV</a>
            <a href="controllers/export_leads.php?format=json" class="premium-btn" style="background:linear-gradient(135deg, #8b5cf6, #7c3aed); text-decoration:none;">📦 Export JSON</a>
            <?php endif; ?>
            <?php if($canCreateLeads): ?>
            <button class="premium-btn" onclick="openLeadModal()">+ Add Lead</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pipeline KPIs -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:16px; margin-bottom:32px;">
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Active Pipeline</div>
            <div style="font-size:26px; font-weight:900; background:linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-top:6px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($pipelineStats['pipeline_value'] ?? 0, 0) ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600; margin-top:4px;"><?= $pipelineStats['active_count'] ?> open deals</div>
        </div>
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Closed Won</div>
            <div style="font-size:26px; font-weight:900; background:linear-gradient(135deg, #10b981, #059669); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-top:6px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($pipelineStats['won_value'] ?? 0, 0) ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600; margin-top:4px;"><?= $pipelineStats['won_count'] ?> deals closed</div>
        </div>
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Total Leads</div>
            <div style="font-size:26px; font-weight:900; color:#f59e0b; margin-top:6px;"><?= count($leads) ?></div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600; margin-top:4px;">in pipeline</div>
        </div>
        <?php
        $winRate = count($leads) > 0 ? round(($pipelineStats['won_count'] / count($leads)) * 100, 1) : 0;
        ?>
        <div class="glass-card" style="padding:20px; border-radius:16px;">
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:800;">Win Rate</div>
            <div style="font-size:26px; font-weight:900; background:linear-gradient(135deg, #ec4899, #f43f5e); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-top:6px;"><?= $winRate ?>%</div>
            <div style="font-size:13px; color:var(--text-muted); font-weight:600; margin-top:4px;">of all leads</div>
        </div>
    </div>

    <!-- Kanban CRM Board -->
    <div class="crm-board">
        <?php foreach($stages as $stage):
            $color = $stageColors[$stage];
            $colLeads = $board[$stage];
            $stageValue = array_sum(array_column($colLeads, 'value'));
        ?>
        <div class="crm-col" data-stage="<?= $stage ?>">
            <div class="crm-col-header">
                <span><?= $stage ?></span>
                <span class="crm-badge" style="background:<?= $color ?>;"><?= count($colLeads) ?></span>
            </div>
            <?php if($stageValue > 0): ?>
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-align:center; background: var(--bg-card); border:1px solid var(--border-card); border-radius:8px; padding:6px 8px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($stageValue, 0) ?></div>
            <?php endif; ?>
            <div class="crm-dropzone" style="flex:1; display:flex; flex-direction:column; gap:10px;">
                <?php foreach($colLeads as $lead): ?>
                <div class="crm-card" 
                     draggable="true" 
                     data-id="<?= $lead['id'] ?>"
                     style="border-left-color:<?= $color ?>;"
                     onclick="window.location.href='lead_profile.php?id=<?= $lead['id'] ?>'">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div class="crm-card-name"><?= htmlspecialchars($lead['lead_name']) ?></div>
                        <?php if(stripos($lead['source'] ?? '', 'pabbly') !== false): ?>
                            <span title="Automated Lead via Pabbly Connect" style="font-size:10px; background:#fee2e2; color:#dc2626; padding:2px 4px; border-radius:4px; margin-left:4px;">⚡</span>
                        <?php endif; ?>
                    </div>
                    <div class="crm-card-company">🏢 <?= htmlspecialchars($lead['company'] ?: 'Independent') ?></div>
                    <div class="crm-card-value"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($lead['value'], 0) ?></div>
                    <div style="margin-top:8px; display:flex; gap:6px;">
                        <button onclick="event.stopPropagation();openActivity(<?= $lead['id'] ?>,'<?= addslashes(htmlspecialchars($lead['lead_name'])) ?>')" style="flex:1;font-size:11px;padding:3px 10px;border-radius:99px;border:1px solid var(--border-card);background:transparent;color:var(--text-muted);cursor:pointer;">📋 Activities</button>
                        <?php if($canEditLeads): ?>
                        <button onclick="event.stopPropagation();editLead(<?= htmlspecialchars(json_encode($lead)) ?>)" style="flex:1;font-size:11px;padding:3px 10px;border-radius:99px;border:1px solid var(--border-card);background:transparent;color:var(--text-muted);cursor:pointer;">✏️ Edit</button>
                        <?php endif; ?>
                        <?php if($lead['stage'] !== 'Lost' && $lead['stage'] !== 'Won' && $canConvert): ?>
                        <form method="POST" action="controllers/convert_lead.php" style="margin:0;" onsubmit="this.querySelector('button').innerHTML='⏳...'; this.querySelector('button').style.opacity='0.7';">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                            <button type="submit" onclick="event.stopPropagation();" style="flex:1;font-size:11px;padding:3px 10px;border-radius:99px;border:1px solid #10b981;background:#10b981;color:white;cursor:pointer;transition:all 0.2s;">🚀 Convert</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="crm-card-meta">
                        📧 <?= htmlspecialchars($lead['email'] ?: '—') ?><br>
                        👤 Owner: <?= htmlspecialchars($lead['owner_id']) ?><br>
                        🕐 <?= htmlspecialchars(date('d M', strtotime($lead['last_contact']))) ?>
                        <?php if($lead['follow_up_date']): ?><br>📅 Follow Up: <span style="<?= strtotime($lead['follow_up_date'])<time() ? 'color:#ef4444;font-weight:bold;':'' ?>"><?= htmlspecialchars($lead['follow_up_date']) ?></span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Generic Modal -->
<div id="genericModal" class="modal premium-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:500px; box-shadow:0 10px 40px rgba(0,0,0,0.15); position:relative;">
        <span class="close-button" onclick="closeModal()" style="position:absolute; top:24px; right:24px; cursor:pointer; font-size:24px; color:var(--text-muted);">&times;</span>
        <h2 id="modalTitle" style="margin-top:0; font-size:22px; font-weight:800; margin-bottom:24px;">New Lead</h2>
        <form id="modalForm" method="POST" action="controllers/save_lead.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div id="modalFields"></div>
            <div class="form-actions" style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="cancel" onclick="closeModal()" style="background:#f1f5f9; border:none; padding:12px 24px; border-radius:99px; cursor:pointer; font-weight:700; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Cancel</button>
                <button type="submit" class="submit premium-btn">Save Lead</button>
            </div>
        </form>
    </div>
</div>

<script>
const stages = <?= json_encode($stages) ?>;

function openLeadModal(d = null) {
    document.getElementById('modalTitle').textContent = d ? 'Edit Lead' : 'Add New Lead';

    let html = `<input type="hidden" name="id" value="${d ? d.id : ''}">`;
    html += `<div class="form-group"><label>Lead Name</label><input type="text" name="lead_name" required value="${d ? d.lead_name : ''}"></div>`;
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div class="form-group"><label>Company</label><input type="text" name="company" value="${d ? d.company : ''}"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="${d ? d.email : ''}"></div>
    </div>`;
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div class="form-group"><label>Deal Value</label><div style="position:relative;"><span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-weight:bold;">₹</span><input type="number" step="0.01" name="value" style="padding-left:26px;" value="${d ? d.value : '0'}"></div></div>
        <div class="form-group"><label>Follow Up Date</label><input type="date" name="follow_up_date" value="${d && d.follow_up_date ? d.follow_up_date : ''}"></div>
    </div>`;
    
    let uList = <?= json_encode($allUsers) ?>;
    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div class="form-group"><label>Pipeline Stage</label><select name="stage">`;
    stages.forEach(s => {
        html += `<option value="${s}" ${d&&d.stage===s?'selected':''}>${s}</option>`;
    });
    html += `</select></div>
        <div class="form-group"><label>Lead Owner</label><select name="owner_id" required>`;
    uList.forEach(u => {
        let selu = (d && d.owner_id == u.login_id) || (!d && u.login_id == '<?= $_SESSION['login_id'] ?>') ? 'selected' : '';
        html += `<option value="${u.login_id}" ${selu}>${u.name} (${u.login_id})</option>`;
    });
    html += `</select></div>
    </div>`;

    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function editLead(d) { openLeadModal(d); }
function closeModal() { document.getElementById('genericModal').style.display = 'none'; }

// Drag & Drop
let draggedCard = null;

document.addEventListener('DOMContentLoaded', () => {
    attachDragListeners();

    document.querySelectorAll('.crm-col').forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => {
            e.preventDefault();
                col.classList.remove('drag-over');
                <?php if($canEditLeads): ?>
                if (draggedCard) {
                    col.querySelector('.crm-dropzone').appendChild(draggedCard);
                    const newStage = col.dataset.stage;
                    const leadId   = draggedCard.dataset.id;

                    let fd = new FormData();
                    fd.append('id', leadId);
                    fd.append('stage', newStage);
                    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                    fetch('controllers/update_lead_stage.php', { method:'POST', body: fd });
                }
                <?php endif; ?>
            });
    });
});

function attachDragListeners() {
    document.querySelectorAll('.crm-card').forEach(card => {
        card.addEventListener('dragstart', () => {
            draggedCard = card;
            setTimeout(() => card.classList.add('dragging'), 0);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            draggedCard = null;
        });
    });
}
</script>


<div id="activityPanel" style="display:none;position:fixed;top:0;right:0;width:440px;height:100vh;background: rgba(15,23,42,0.95);border-left:1px solid rgba(236,72,153,0.15);z-index:1000;overflow-y:auto;box-shadow:-12px 0 40px rgba(0,0,0,.4);transition:transform .3s; backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);">
    <div style="padding:24px 28px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;align-items:center;background: linear-gradient(135deg, rgba(79,70,229,0.1), rgba(236,72,153,0.05));">
        <h3 style="color:#f8fafc;font-size:20px;font-weight:800;" id="activityLeadName">Lead Activity</h3>
        <button onclick="closeActivity()" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);width:34px;height:34px;border-radius:50%;font-size:20px;line-height:1;cursor:pointer;color:rgba(255,255,255,0.6);display:flex;align-items:center;justify-content:center;transition:all 0.3s;" onmouseover="this.style.background='rgba(236,72,153,0.1)'; this.style.borderColor='rgba(236,72,153,0.3)'; this.style.color='#ec4899';" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.6)';">×</button>
    </div>
    <div style="padding:16px 24px;">
        <form method="POST" action="controllers/save_crm_activity.php" id="activityForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="lead_id" id="activityLeadId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <select name="type" style="padding:9px 12px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text-body);">
                    <option>📞 Call</option><option>📧 Email</option><option>🤝 Meeting</option>
                    <option>📝 Note</option><option>💬 Demo</option><option>📄 Proposal Sent</option>
                </select>
                <button type="submit" style="background:linear-gradient(135deg, #4f46e5, #ec4899);color:white;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-size:13px;box-shadow:0 4px 12px rgba(236,72,153,0.3);transition:all 0.3s;">+ Log Activity</button>
            </div>
            <textarea name="note" placeholder="Activity notes..." style="width:100%;padding:10px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text-body);font-size:13px;resize:vertical;min-height:80px;"></textarea>
        </form>
        <div id="activityList" style="margin-top:16px;"></div>
    </div>
</div>
<div id="activityOverlay" onclick="closeActivity()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:999;"></div>

<script>
function openActivity(leadId, leadName) {
    document.getElementById('activityLeadId').value = leadId;
    document.getElementById('activityLeadName').textContent = leadName;
    document.getElementById('activityPanel').style.display = 'block';
    document.getElementById('activityOverlay').style.display = 'block';
    fetch('controllers/get_crm_activities.php?lead_id='+leadId)
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('activityList');
            if (!data.length) { list.innerHTML='<p style="color:var(--text-muted);font-size:13px;">No activities yet.</p>'; return; }
            list.innerHTML = data.map(a=>`
            list.innerHTML = data.map(a=>`
                <div style="padding:14px 18px;margin-bottom:12px;background:rgba(255,255,255,0.9);border:1px solid rgba(0,0,0,0.05);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                    <div style="font-size:14px;font-weight:800;color:var(--text-heading);">${a.type}</div>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:4px;line-height:1.5;">${a.note||''}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:8px;font-weight:600;display:flex;justify-content:space-between;border-top:1px solid rgba(0,0,0,0.04);padding-top:8px;">
                        <span>👤 ${a.user_id}</span>
                        <span>🕐 ${a.ago}</span>
                    </div>
                </div>`).join('');
        });
}
function closeActivity() {
    document.getElementById('activityPanel').style.display = 'none';
    document.getElementById('activityOverlay').style.display = 'none';
}
</script>

<!-- API Integrations Modal -->
<div id="apiIntegrationModal" class="modal premium-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:550px; box-shadow:0 10px 40px rgba(0,0,0,0.15); position:relative;">
        <span class="close-button" onclick="document.getElementById('apiIntegrationModal').style.display='none'" style="position:absolute; top:24px; right:24px; cursor:pointer; font-size:24px; color:var(--text-muted);">&times;</span>
        <h2 style="display:flex;align-items:center;gap:12px; margin-top:0; font-size:22px; font-weight:800;"><span style="font-size:28px;">🔌</span> Enterprise Pabbly Integration</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px; line-height:1.5;">Connect external forms seamlessly using Pabbly Connect Webhooks.</p>
        
        <div style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb; margin-bottom:24px;">
            <div style="font-size:12px; font-weight:800; color:#6b7280; text-transform:uppercase; margin-bottom:8px;">Your Endpoint URL</div>
            <code style="display:block; background:#111827; color:#10b981; padding:12px 16px; border-radius:8px; font-size:13px; word-break:break-all; font-weight:600;">
                https://<?= $_SERVER['HTTP_HOST'] ?? 'yourdomain.com' ?>/api/pabbly_webhook.php
            </code>
        </div>

        <div style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb; margin-bottom:24px;">
            <div style="font-size:12px; font-weight:800; color:#6b7280; text-transform:uppercase; margin-bottom:8px;">Your API Token (Bearer)</div>
            <div style="display:flex; gap:12px; align-items:center;">
                <input type="text" readonly value="<?= htmlspecialchars($myApiKey ?: 'No Key Generated') ?>" style="flex:1; padding:12px 16px; border-radius:8px; border:1px solid #d1d5db; background:#fff; font-family:monospace; font-size:14px; color:var(--text-heading); font-weight:600;">
                <form method="POST" action="controllers/generate_api_key.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="premium-btn">Regenerate</button>
                </form>
            </div>
        </div>
        
        <div style="font-size:13px; color:#4b5563; margin-top:24px; text-align:left; line-height: 1.6;">
            <strong>Setup Instructions:</strong><br>
            1. In Pabbly Connect, create an "API / Custom Request" Action.<br>
            2. Set Method to <strong>POST</strong> and Endpoint URL as above.<br>
            3. In Headers, add: <code style="background:#e5e7eb; padding:2px 6px; border-radius:4px;">Authorization: Bearer YOUR_API_TOKEN</code><br>
            4. Send any generic or custom fields; the CRM will map natively or stash in <code style="background:#e5e7eb; padding:2px 6px; border-radius:4px;">custom_data</code> JSON!
        </div>
    </div>
</div>

<!-- Google Sheet Sync Modal -->
<div id="syncSheetModal" class="modal premium-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:550px; box-shadow:0 10px 40px rgba(0,0,0,0.15); position:relative;">
        <span class="close-button" onclick="document.getElementById('syncSheetModal').style.display='none'" style="position:absolute; top:24px; right:24px; cursor:pointer; font-size:24px; color:var(--text-muted);">&times;</span>
        <h2 style="display:flex;align-items:center;gap:12px; margin-top:0; font-size:22px; font-weight:800;"><span style="font-size:28px;">🔄</span> Sync Google Sheets</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px; line-height:1.5;">Instantly import leads/patients from a published Google Sheet CSV.</p>
        
        <form method="POST" action="controllers/sync_google_sheet.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group" style="margin-bottom:24px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Published CSV URL</label>
                <input type="url" name="csv_url" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" required style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            </div>

            <div style="font-size:13px; color:#4b5563; margin-top:20px; text-align:left; line-height: 1.6; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0;">
                <strong style="color:var(--text-heading);">How to get this link:</strong><br>
                1. Open your Google Sheet.<br>
                2. Click <strong>File > Share > Publish to web</strong>.<br>
                3. Choose the specific tab and select <strong>Comma-separated values (.csv)</strong>.<br>
                4. Click Publish and paste the generated link here.<br><br>
                <strong style="color:var(--text-heading);">Auto-Routing Engine:</strong><br>
                If your Sheet has columns named <strong style="color:var(--primary-color);">PIN</strong>, <strong style="color:var(--primary-color);">Location</strong>, or <strong style="color:var(--primary-color);">User Type</strong>, the CRM will instantly assign the Lead to the exact matching employee in that zone!
            </div>

            <div class="form-actions" style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="cancel" onclick="document.getElementById('syncSheetModal').style.display='none'" style="background:#f1f5f9; border:none; padding:12px 24px; border-radius:99px; cursor:pointer; font-weight:700; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Cancel</button>
                <button type="submit" class="submit premium-btn" onclick="this.innerHTML='⏳ Syncing...'; this.style.opacity='0.7';">Sync Now</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

