<?php
// crm_campaigns.php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_crm');

// Fetch analytics
$totalCampaigns = $pdo->query("SELECT COUNT(*) FROM crm_campaigns")->fetchColumn() ?: 0;
$activeCampaigns= $pdo->query("SELECT COUNT(*) FROM crm_campaigns WHERE status = 'Active'")->fetchColumn() ?: 0;
$totalDispatched = $pdo->query("SELECT SUM(sent_count) FROM crm_campaigns")->fetchColumn() ?: 0;
$totalLeads = $pdo->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn() ?: 0;

// Fetch campaigns
$campaigns = $pdo->query("SELECT * FROM crm_campaigns ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent logs
$logs = $pdo->query("SELECT l.*, c.title as campaign_title FROM crm_campaign_logs l LEFT JOIN crm_campaigns c ON l.campaign_id = c.id ORDER BY l.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// CRM stages
$stages = ['Prospect', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
?>

<style>
.cmp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.cmp-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-heading);
    display: flex;
    align-items: center;
    gap: 12px;
}
.cmp-badge {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
    background: linear-gradient(135deg, #ec4899, #8b5cf6);
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 18px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
    flex-shrink: 0;
}
.stat-num { font-size: 24px; font-weight: 800; color: var(--text-heading); }
.stat-label { font-size: 13px; color: var(--text-muted); font-weight: 600; }

.cmp-layout {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 24px;
}
@media (max-width: 1024px) {
    .cmp-layout { grid-template-columns: 1fr; }
}

.cmp-box {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}

.cmp-input, .cmp-select, .cmp-textarea {
    width: 100%;
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid var(--border-card);
    background: var(--bg-main);
    color: var(--text-body);
    font-size: 13.5px;
    outline: none;
    transition: border 0.3s;
    box-sizing: border-box;
}
.cmp-input:focus, .cmp-select:focus, .cmp-textarea:focus {
    border-color: #ec4899;
    box-shadow: 0 0 0 3px rgba(236,72,153,0.15);
}

.btn-sm {
    padding: 8px 14px;
    font-size: 12.5px;
    font-weight: 700;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary { background: #ec4899; color: white; }
.btn-primary:hover { background: #db2777; }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-outline { background: transparent; border: 1px solid var(--border-card); color: var(--text-body); }
.btn-outline:hover { background: rgba(255,255,255,0.05); }
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Header -->
    <div class="cmp-header">
        <div>
            <div class="cmp-title">
                📢 Sales Marketing Campaigns
                <span class="cmp-badge">Email & WhatsApp Outreach</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Create targeted sales campaigns, segment lead audiences by stage, and execute multi-channel outreach.
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="crm.php" class="btn-sm btn-outline" style="padding: 10px 18px;">
                <i class="fas fa-arrow-left"></i> Back to Sales CRM
            </a>
        </div>
    </div>

    <!-- Analytics Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ec4899, #d946ef);">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalCampaigns ?></div>
                <div class="stat-label">Total Campaigns</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="stat-num"><?= $activeCampaigns ?></div>
                <div class="stat-label">Active Campaigns</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalDispatched ?></div>
                <div class="stat-label">Messages Dispatched</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div class="stat-num"><?= $totalLeads ?></div>
                <div class="stat-label">Target Lead Pool</div>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="cmp-layout">
        <!-- Campaign Builder Form -->
        <div class="cmp-box">
            <div style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:16px;">
                <i class="fas fa-pen-fancy" style="color:#ec4899;"></i> Design Campaign
            </div>

            <form id="campaignForm" onsubmit="submitCampaignForm(event)">
                <input type="hidden" id="cmp_id" name="id" value="0" />

                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div>
                        <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Campaign Title *</label>
                        <input type="text" id="cmp_title" name="title" class="cmp-input" placeholder="e.g. Q3 Prospect Conversion Campaign" required />
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Channel *</label>
                            <select id="cmp_channel" name="channel" class="cmp-select" onchange="toggleCmpSubject()">
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Target Lead Stage</label>
                            <select id="cmp_stage" name="target_stage" class="cmp-select">
                                <option value="All">All Lead Stages</option>
                                <?php foreach($stages as $st): ?>
                                <option value="<?= $st ?>"><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="cmpSubjectGroup">
                        <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Email Subject</label>
                        <input type="text" id="cmp_subject" name="subject" class="cmp-input" placeholder="Exclusive update for {{company}}" />
                    </div>

                    <div>
                        <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Campaign Message Body (Variant A) *</label>
                        <textarea id="cmp_body" name="body" class="cmp-textarea" rows="5" placeholder="Hi {{name}},&#10;&#10;We are reaching out to discuss how we can help {{company}} grow..." required></textarea>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
                            Dynamic Tags: <code>{{name}}</code>, <code>{{company}}</code>, <code>{{email}}</code>, <code>{{phone}}</code>, <code>{{user_name}}</code>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                        <input type="checkbox" id="enable_ab_testing" onchange="document.getElementById('ab_variant_box').style.display = this.checked ? 'block' : 'none';" style="width:16px; height:16px; accent-color:#ec4899;" />
                        <label for="enable_ab_testing" style="font-size:13px; font-weight:700; color:var(--text-heading); cursor:pointer;">
                            Enable 50/50 A/B Variant Testing (Test 2 Subjects/Messages)
                        </label>
                    </div>

                    <div id="ab_variant_box" style="display:none; background:rgba(236,72,153,0.05); border:1px dashed rgba(236,72,153,0.4); border-radius:14px; padding:16px; margin-top:4px;">
                        <div style="font-weight:800; font-size:13px; color:#ec4899; margin-bottom:10px;">🧪 Variant B Message (50% Audience Split)</div>
                        <div id="cmpSubjectGroupB" style="margin-bottom:10px;">
                            <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Variant B Subject Line</label>
                            <input type="text" id="cmp_variant_b_subject" name="variant_b_subject" class="cmp-input" placeholder="Alternative subject line for {{name}}" />
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Variant B Message Content</label>
                            <textarea id="cmp_variant_b_body" name="variant_b_body" class="cmp-textarea" rows="4" placeholder="Alternative message body variation for A/B testing..."></textarea>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button type="submit" class="btn-sm btn-primary" style="flex:1; padding:12px;">
                            <i class="fas fa-save"></i> Save Campaign Draft
                        </button>
                        <button type="button" onclick="resetCampaignForm()" class="btn-sm btn-outline">Cancel</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Campaign List & Execution -->
        <div class="cmp-box">
            <div style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:16px;">
                <i class="fas fa-rocket" style="color:#10b981;"></i> Campaign Center (<?= count($campaigns) ?>)
            </div>

            <div style="display:flex; flex-direction:column; gap:14px; max-height:480px; overflow-y:auto;">
                <?php foreach($campaigns as $c): ?>
                <div style="background:var(--bg-main); border:1px solid var(--border-card); border-radius:14px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div style="font-weight:800; font-size:15px; color:var(--text-heading);">
                            <?= htmlspecialchars($c['title']) ?>
                        </div>
                        <span style="font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; background:<?= $c['status'] === 'Active' ? '#10b981' : '#64748b' ?>; color:white;">
                            <?= strtoupper(htmlspecialchars($c['status'])) ?>
                        </span>
                    </div>

                    <div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;">
                        Channel: <strong><?= strtoupper(htmlspecialchars($c['channel'])) ?></strong> • Target Stage: <strong><?= htmlspecialchars($c['target_stage']) ?></strong> • Dispatched: <strong><?= $c['sent_count'] ?></strong>
                    </div>

                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" onclick="executeCampaign(<?= $c['id'] ?>)" class="btn-sm btn-success" title="Dispatch Outreach Now">
                            <i class="fas fa-paper-plane"></i> Run Outreach
                        </button>
                        <button type="button" onclick="editCampaign(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn-sm btn-outline">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" onclick="deleteCampaign(<?= $c['id'] ?>)" class="btn-sm btn-outline" style="color:#ef4444; border-color:rgba(239,68,68,0.3);">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCmpSubject() {
    const ch = document.getElementById('cmp_channel').value;
    document.getElementById('cmpSubjectGroup').style.display = (ch === 'email') ? 'block' : 'none';
}

async function submitCampaignForm(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('campaignForm'));
    formData.append('action', 'save');

    try {
        const resp = await fetch('controllers/save_campaign.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Saved!', res.message, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection error.', 'error');
    }
}

function editCampaign(c) {
    document.getElementById('cmp_id').value = c.id;
    document.getElementById('cmp_title').value = c.title;
    document.getElementById('cmp_channel').value = c.channel;
    document.getElementById('cmp_stage').value = c.target_stage;
    document.getElementById('cmp_subject').value = c.subject || '';
    document.getElementById('cmp_body').value = c.body;
    toggleCmpSubject();
}

function resetCampaignForm() {
    document.getElementById('campaignForm').reset();
    document.getElementById('cmp_id').value = 0;
    toggleCmpSubject();
}

async function deleteCampaign(id) {
    if (!confirm('Delete this campaign?')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    const resp = await fetch('controllers/save_campaign.php', { method: 'POST', body: formData });
    const res = await resp.json();
    if (res.success) window.location.reload();
}

async function executeCampaign(id) {
    if (!confirm('Dispatch campaign outreach to all matching target leads now?')) return;

    Swal.fire({ title: 'Executing Sales Campaign...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const formData = new FormData();
        formData.append('campaign_id', id);
        const resp = await fetch('controllers/execute_campaign.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Campaign Executed!', res.message, 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Execution Failed', res.error, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Server connection error.', 'error');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
