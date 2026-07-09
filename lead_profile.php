<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_crm');

$leadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$leadId) {
    die("<div class='content-section active'><h2>Lead Not Found</h2><p>Invalid Lead ID provided.</p><a href='crm.php'>&larr; Back to CRM</a></div>");
}

$stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
$stmt->execute([$leadId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("<div class='content-section active'><h2>Lead Not Found</h2><p>Lead does not exist or was deleted.</p><a href='crm.php'>&larr; Back to CRM</a></div>");
}

$canEditLeads = hasPermission($pdo, 'edit_leads');

$customData = [];
if (!empty($lead['custom_data'])) {
    $customData = json_decode($lead['custom_data'], true);
}

// Fetch Activities
$activitiesStmt = $pdo->prepare("SELECT a.*, u.name as user_name FROM crm_activities a LEFT JOIN users u ON a.user_id = u.login_id WHERE a.lead_id = ? ORDER BY a.created_at DESC");
$activitiesStmt->execute([$leadId]);
$activities = $activitiesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.profile-grid { display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: start; }
.card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--border-card); }
.field-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 4px; }
.field-value { font-size: 14px; color: var(--text-heading); font-weight: 500; margin-bottom: 16px; word-break: break-all; }
.origin-badge { font-size: 11px; padding: 4px 8px; border-radius: 99px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
.origin-pabbly { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
.origin-manual { background: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe; }

.timeline { position: relative; padding-left: 20px; border-left: 2px solid #e5e7eb; margin-top: 20px; }
.timeline-item { position: relative; margin-bottom: 24px; }
.timeline-icon { position: absolute; left: -31px; top: 0; width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 3px solid white; box-shadow: 0 0 0 1px #e5e7eb; }
.timeline-content { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.timeline-header { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 4px; }
.timeline-meta { font-size: 11px; color: #9ca3af; margin-bottom: 8px; }
.timeline-body { font-size: 13px; color: #4b5563; line-height: 1.5; white-space: pre-wrap; }

/* Custom Attributes UI */
.custom-attr-list { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e5e7eb;}
.attr-row { display: flex; justify-content: space-between; font-size: 12px; background: #f3f4f6; padding: 8px 12px; border-radius: 6px; }
.attr-key { font-weight: 600; color: #4b5563; }
.attr-val { color: #6b7280; max-width: 60%; text-align: right; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 768px) {
    .profile-grid { grid-template-columns: 1fr; }
}
</style>

<div class="content-section active">
    
    <div style="margin-bottom: 20px;">
        <a href="crm.php" style="color:var(--primary-color); text-decoration:none; font-size:13px; font-weight:600;">&larr; Overview Pipeline</a>
    </div>

    <div class="profile-grid">
        <!-- Left Pane: Identity & Enriched Data -->
        <div class="card">
            <?php 
                $isPabbly = stripos($lead['source'], 'pabbly') !== false;
            ?>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, #10b981, #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; box-shadow: 0 4px 10px rgba(59,130,246,0.3);">
                    <?= strtoupper(substr($lead['lead_name'], 0, 1)) ?>
                </div>
                <div class="origin-badge <?= $isPabbly ? 'origin-pabbly' : 'origin-manual' ?>">
                    <?= $isPabbly ? '⚡ Pabbly Webhook' : '👤 Manual Entry' ?>
                </div>
            </div>
            
            <h2 style="font-size:20px; font-weight:800; color:#111827; margin-bottom:4px;"><?= htmlspecialchars($lead['lead_name']) ?></h2>
            <div style="font-size:14px; color:#6b7280; font-weight:500; margin-bottom:24px;">🏢 <?= htmlspecialchars($lead['company'] ?: 'Independent / Personal') ?></div>
            
            <div class="field-label">Email Address</div>
            <div class="field-value"><a href="mailto:<?= htmlspecialchars($lead['email']) ?>" style="color:#6366f1; text-decoration:none;"><?= htmlspecialchars($lead['email'] ?: '—') ?></a></div>
            
            <div class="field-label">Contact Number</div>
            <div class="field-value"><a href="tel:<?= htmlspecialchars($lead['phone']) ?>" style="color:#6366f1; text-decoration:none;"><?= htmlspecialchars($lead['phone'] ?: '—') ?></a></div>
            
            <div class="field-label">Deal Value</div>
            <div class="field-value" style="color:#059669; font-size:18px; font-weight:800;"><?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($lead['value'], 2) ?></div>
            
            <div class="field-label">Pipeline Stage</div>
            <div class="field-value">
                <span style="background:#e0e7ff; color:#4338ca; padding:4px 12px; border-radius:6px; font-weight:700; font-size:12px;"><?= htmlspecialchars($lead['stage']) ?></span>
            </div>

            <div class="field-label">Assigned Owner</div>
            <div class="field-value">👤 <?= htmlspecialchars($lead['owner_id']) ?></div>
            
            <?php if(!empty($customData)): ?>
            <!-- Dynamic Custom Attributes -->
            <div class="custom-attr-list">
                <h4 style="font-size:12px; color:#111827; margin-bottom:4px;">Enriched Attributes (From Integrations)</h4>
                <?php foreach($customData as $key =>$val): ?>
                    <div class="attr-row">
                        <span class="attr-key"><?= htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $key))) ?></span>
                        <span class="attr-val" title="<?= htmlspecialchars(is_array($val)?json_encode($val):$val) ?>"><?= htmlspecialchars(is_array($val)?'Array(...)':$val) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Pane: Communications & Timeline -->
        <div class="card" style="min-height: 500px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:#111827; font-size:18px;">Activity Timeline</h3>
            </div>

            <!-- Activity Logger -->
            <?php if($canEditLeads): ?>
            <div style="background:#f3f4f6; border-radius:12px; padding:16px; margin-bottom:30px;">
                <form method="POST" action="controllers/save_crm_activity.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="lead_id" value="<?= $leadId ?>">
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <select name="type" style="padding:10px; border-radius:8px; border:1px solid #d1d5db; height:40px;">
                            <option>📞 Log Call</option>
                            <option>📧 Log Email</option>
                            <option>🤝 Log Meeting</option>
                            <option>📝 internal Note</option>
                        </select>
                        <input type="text" name="note" placeholder="What happened..." style="flex:1; padding:10px; border-radius:8px; border:1px solid #d1d5db; height:40px;" required>
                        <button type="submit" style="background:#111827; color:white; border:none; border-radius:8px; padding:0 20px; font-weight:700; cursor:pointer;">Post</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="timeline">
                <?php if(empty($activities)): ?>
                <p style="color:#9ca3af; font-size:13px; font-style:italic;">No recorded activities.</p>
                <?php else: ?>
                    <?php foreach($activities as $act): 
                        $icon = '📌';
                        if(stripos($act['type'], 'call') !== false) $icon = '📞';
                        if(stripos($act['type'], 'email') !== false) $icon = '📧';
                        if(stripos($act['type'], 'meeting') !== false) $icon = '🤝';
                        if(stripos($act['type'], 'api') !== false || stripos($act['type'], 'webhook') !== false) $icon = '⚡';
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-icon"><?= $icon ?></div>
                        <div class="timeline-content">
                            <div class="timeline-header"><?= htmlspecialchars($act['type']) ?></div>
                            <div class="timeline-meta">by <?= htmlspecialchars($act['user_name'] ?? $act['user_id']) ?> on <?= date('D, M d, h:i A', strtotime($act['created_at'])) ?></div>
                            <div class="timeline-body"><?= htmlspecialchars($act['note']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
