<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only users with onboarding manager permissions should access
requirePermission($pdo, 'manage_onboarding');

// Fetch applications
$apps = $pdo->query("SELECT * FROM onboarding_applications ORDER BY applied_at DESC")->fetchAll(PDO::FETCH_ASSOC);

function timeAgo($datetime) {
    if (!$datetime) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 86400) return 'Today';
    return floor($diff / 86400) . ' days ago';
}
?>
<style>
.kanban-board-wrapper {
    display: flex;
    gap: 24px;
    min-height: calc(100vh - 200px);
    overflow-x: auto;
    padding: 10px 0 20px 0;
}
.kanban-col {
    flex: 1;
    min-width: 320px;
    background: rgba(243, 244, 246, 0.6);
    border-radius: 16px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    border: 1px solid #e5e7eb;
}
[data-theme="dark"] .kanban-col {
    background: rgba(30, 41, 59, 0.6);
    border-color: #334155;
}

.kanban-col-header {
    font-weight: 800;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #374151;
    padding-bottom: 12px;
    border-bottom: 3px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
[data-theme="dark"] .kanban-col-header { color: #cbd5e1; border-color: #475569; }

.k-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
    border: 1px solid #e5e7eb;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.k-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.04);
}
[data-theme="dark"] .k-card { background: #0f172a; border-color: #334155; }

.k-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #6366f1;
}
.k-card.status-Hired::before { background: #10b981; }
.k-card.status-Rejected::before { background: #ef4444; }

.k-title { font-weight: 800; color: #111827; margin-bottom: 4px; font-size: 16px; }
[data-theme="dark"] .k-title { color: #f8fafc; }

.k-desc { font-size: 13px; color: #6b7280; margin-bottom: 16px; line-height: 1.5; }
[data-theme="dark"] .k-desc { color: #94a3b8; }

.k-meta { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }

.btn-small { 
    background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 6px 12px; border-radius: 6px; 
    font-weight: 600; cursor: pointer; text-decoration: none; display:inline-block; font-size:12px; transition: all 0.2s;
}
.btn-small:hover { background: #e5e7eb; }
[data-theme="dark"] .btn-small { background: #1e293b; color: #cbd5e1; border-color: #475569; }

.btn-primary { background: #6366f1; color: white; border: none; }
.btn-primary:hover { background: #4f46e5; }

.btn-success { background: #10b981; color: white; border: none; }
.btn-success:hover { background: #059669; }

.btn-danger { background: #ef4444; color: white; border: none; }
.btn-danger:hover { background: #dc2626; }

.kanban-badge { background: #e5e7eb; color: #374151; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; }
</style>

<div class="content-section active">
    <!-- Header Area -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; background:var(--bg-card); padding:20px 24px; border-radius:16px; border:1px solid var(--border-card); box-shadow:0 4px 6px rgba(0,0,0,0.02);">
        <div>
            <h2 style="margin:0; font-size:24px; font-weight:800; color:var(--text-heading);">🚀 Applicant Protocol</h2>
            <p style="margin:4px 0 0 0; font-size:14px; color:var(--text-muted);">Manage the active candidate pipeline and provision corporate accounts.</p>
        </div>
        <button onclick="document.getElementById('internalCandidateModal').style.display='flex'" style="background:var(--primary-color); color:white; border:none; padding:12px 20px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px; box-shadow:0 4px 12px rgba(99,102,241,0.3); transition:transform 0.2s;">➕ Quick Internal Hire</button>
    </div>

    <!-- Kanban Engine -->
    <div class="kanban-board-wrapper">
        
        <!-- Column 1: New Applications -->
        <div class="kanban-col">
            <div class="kanban-col-header" style="border-bottom-color:#6366f1;">
                <span style="color:#4f46e5;">Pending Review</span>
                <?php $pCount = count(array_filter($apps, fn($a) =>$a['status'] === 'Pending')); ?>
                <span class="kanban-badge" style="background:#e0e7ff; color:#4f46e5;"><?= $pCount ?></span>
            </div>
            
            <div style="flex:1; display:flex; flex-direction:column; gap:16px;">
                <?php foreach($apps as $a): if($a['status'] !== 'Pending') continue; ?>
                <div class="k-card">
                    <div class="k-meta">⏳ Applied <?= timeAgo($a['applied_at']) ?></div>
                    <div class="k-title"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    <div class="k-desc">
                        <strong>Role:</strong> <?= htmlspecialchars($a['position_applied']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($a['email']) ?>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <a href="<?= htmlspecialchars($a['resume_link']) ?>" target="_blank" class="btn-small btn-primary">📄 Resume</a>
                        <form method="POST" action="controllers/approve_onboarding.php" style="margin:0;" onsubmit="return confirm('Hire this applicant? This instantly provisions an enterprise account.')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn-small btn-success">✅ Hire</button>
                        </form>
                        <form method="POST" action="controllers/reject_onboarding.php" style="margin:0;" onsubmit="return confirm('Reject this applicant?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn-small btn-danger">❌ Pass</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if($pCount === 0): ?>
                    <div style="text-align:center; padding:30px 10px; color:var(--text-muted); font-size:13px; border:2px dashed var(--border-card); border-radius:12px;">Inbox zero! No new candidates.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 2: Provisioned & Hired -->
        <div class="kanban-col" style="background:rgba(220,252,231,0.3); border-color:#bbf7d0;">
            <div class="kanban-col-header" style="border-bottom-color:#10b981;">
                <span style="color:#059669;">Hired & Provisioned</span>
                <?php $hCount = count(array_filter($apps, fn($a) =>$a['status'] === 'Hired')); ?>
                <span class="kanban-badge" style="background:#d1fae5; color:#059669;"><?= $hCount ?></span>
            </div>
            
            <div style="flex:1; display:flex; flex-direction:column; gap:16px;">
                <?php foreach($apps as $a): if($a['status'] !== 'Hired') continue; ?>
                <div class="k-card status-Hired">
                    <div class="k-meta" style="color:#059669;">🎉 Active Employee</div>
                    <div class="k-title"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    <div class="k-desc">
                        <strong>Role:</strong> <?= htmlspecialchars($a['position_applied']) ?><br>
                        <strong>System ID:</strong> <?= strtolower($a['first_name'].'.'.$a['last_name']) ?>
                    </div>
                    <div style="font-size:12px; font-weight:700; color:#10b981; background:#ecfdf5; padding:6px 12px; border-radius:6px; display:inline-block;">Account Online & Synced</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Column 3: Rejected -->
        <div class="kanban-col" style="background:rgba(254,226,226,0.3); border-color:#fecaca;">
            <div class="kanban-col-header" style="border-bottom-color:#ef4444;">
                <span style="color:#dc2626;">Passed Review</span>
                <?php $rCount = count(array_filter($apps, fn($a) =>$a['status'] === 'Rejected')); ?>
                <span class="kanban-badge" style="background:#fee2e2; color:#dc2626;"><?= $rCount ?></span>
            </div>
            
            <div style="flex:1; display:flex; flex-direction:column; gap:16px;">
                <?php foreach($apps as $a): if($a['status'] !== 'Rejected') continue; ?>
                <div class="k-card status-Rejected" style="opacity:0.75;">
                    <div class="k-meta">❌ Rejected</div>
                    <div class="k-title"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    <div class="k-desc" style="margin-bottom:0;">
                        <strong>Applied For:</strong> <?= htmlspecialchars($a['position_applied']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<!-- Internal Candidate Modal -->
<div id="internalCandidateModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div style="background:var(--bg-card); padding:36px; border-radius:16px; width:450px; max-width:90vw; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); border:1px solid var(--border-card);">
        <button onclick="document.getElementById('internalCandidateModal').style.display='none'" style="position:absolute; top:16px; right:20px; background:none; border:none; font-size:28px; cursor:pointer; color:var(--text-muted); transition:color 0.2s;">&times;</button>
        <h3 style="margin-top:0; margin-bottom:8px; color:var(--text-heading); font-size:20px; font-weight:800;">Internal Candidate Intake</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:24px;">Bypass public portals to instantly spawn an employee pipeline card.</p>
        
        <form method="POST" action="controllers/submit_internal_candidate.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">First Name</label>
                    <input type="text" name="first_name" required style="width:100%; padding:12px; border-radius:8px; background:var(--bg-body); border:1px solid var(--input-border); color:var(--text-body); outline:none; transition:border 0.2s;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Last Name</label>
                    <input type="text" name="last_name" required style="width:100%; padding:12px; border-radius:8px; background:var(--bg-body); border:1px solid var(--input-border); color:var(--text-body); outline:none; transition:border 0.2s;">
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Email Address</label>
                <input type="email" name="email" required style="width:100%; padding:12px; border-radius:8px; background:var(--bg-body); border:1px solid var(--input-border); color:var(--text-body); outline:none; transition:border 0.2s;">
            </div>
            <div style="margin-bottom:24px;">
                <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:6px; display:block;">Assigned Role / Position</label>
                <input type="text" name="position" required placeholder="e.g. Senior Software Engineer" style="width:100%; padding:12px; border-radius:8px; background:var(--bg-body); border:1px solid var(--input-border); color:var(--text-body); outline:none; transition:border 0.2s;">
            </div>
            <button type="submit" style="width:100%; background:var(--primary-color); color:white; border:none; padding:14px; border-radius:10px; font-weight:800; font-size:15px; cursor:pointer; box-shadow:0 4px 12px rgba(99,102,241,0.25); transition:transform 0.2s;">Deploy safely to pipeline</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
