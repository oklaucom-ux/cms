<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto-migrate schema
require_once 'migrations/016_create_daily_reports_schema.php';

$userId = $_SESSION['login_id'] ?? '';
$userRole = $_SESSION['role'] ?? 'Employee';
$isAdminOrManager = in_array($userRole, ['Admin', 'Super Admin', 'System Admin', 'Manager', 'HR Manager']) || hasPermission($pdo, 'manage_daily_reports');

// Fetch today's submission for current user if exists
$todayDate = date('Y-m-d');
$todayReport = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE user_id = ? AND report_date = ? LIMIT 1");
    $stmt->execute([$userId, $todayDate]);
    $todayReport = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch user's completed tasks today for quick auto-fill helper
$completedTasksToday = [];
try {
    $tStmt = $pdo->prepare("SELECT name, task_id FROM tasks WHERE assigned_to = ? AND status = 'Completed' AND DATE(updated_at) = ?");
    $tStmt->execute([$userId, $todayDate]);
    $completedTasksToday = $tStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch User's Recent Submissions (Last 30 Days)
$myReports = [];
try {
    $mStmt = $pdo->prepare("SELECT * FROM daily_reports WHERE user_id = ? ORDER BY report_date DESC LIMIT 30");
    $mStmt->execute([$userId]);
    $myReports = $mStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch All Users for Filter (Manager View)
$allUsers = [];
if ($isAdminOrManager) {
    try {
        $allUsers = $pdo->query("SELECT login_id, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Analytics Metrics
$countSubmittedToday = 0;
$totalHoursToday = 0.0;
$pendingReviewsCount = 0;
$activeUsersCount = 1;

try {
    $countSubmittedToday = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM daily_reports WHERE report_date = '$todayDate'")->fetchColumn();
    $totalHoursToday = (float)$pdo->query("SELECT SUM(hours_worked) FROM daily_reports WHERE report_date = '$todayDate'")->fetchColumn();
    $pendingReviewsCount = (int)$pdo->query("SELECT COUNT(*) FROM daily_reports WHERE status = 'Submitted'")->fetchColumn();
    $activeUsersCount = max(1, (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());
} catch (Exception $e) {}

$complianceRate = min(100, round(($countSubmittedToday / $activeUsersCount) * 100));
?>

<!-- Load jsPDF and AutoTable for PDF exports -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<style>
.dwr-container { padding: 4px; }
.dwr-hud { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.hud-card { background: var(--bg-card); border-radius: 12px; padding: 20px; border: 1px solid var(--border-card); box-shadow: var(--shadow-soft); display: flex; flex-direction: column; gap: 6px; }
.hud-val { font-size: 28px; font-weight: 800; color: var(--text-heading); }
.hud-lbl { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

.dwr-tabs { display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--border-card); padding-bottom: 8px; }
.dwr-tab-btn { background: none; border: none; font-weight: 700; font-size: 15px; color: var(--text-muted); padding: 8px 16px; cursor: pointer; border-radius: 8px; transition: all 0.2s; }
.dwr-tab-btn.active { color: var(--primary-color); background: rgba(79, 70, 229, 0.1); }

.dwr-card { background: var(--bg-card); border-radius: 12px; padding: 24px; border: 1px solid var(--border-card); box-shadow: var(--shadow-soft); margin-bottom: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-heading); margin-bottom: 6px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; box-sizing: border-box; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-main); color: var(--text-body); font-family: inherit; font-size: 14px; }
.form-group textarea { min-height: 90px; resize: vertical; }

.btn-primary { background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
.btn-primary:hover { background: #4338ca; }
.btn-secondary { background: var(--bg-main); color: var(--text-body); border: 1px solid var(--border-card); padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-secondary:hover { background: var(--border-card); }

.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
.status-Submitted { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
.status-Reviewed { background: rgba(16, 185, 129, 0.15); color: #059669; }
.status-NeedsRevision { background: rgba(245, 158, 11, 0.15); color: #d97706; }

.table-responsive { overflow-x: auto; }
.custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
.custom-table th { background: var(--table-header); color: var(--text-heading); font-weight: 700; padding: 12px 16px; border-bottom: 2px solid var(--border-card); }
.custom-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-card); color: var(--text-body); vertical-align: top; }

/* Modal overlay */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
.modal-content { background: var(--bg-card); width: 90%; max-width: 650px; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); border: 1px solid var(--border-card); max-height: 90vh; overflow-y: auto; }
</style>

<div class="content-section active dwr-container">
    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0; font-size:22px; font-weight:700; color:var(--text-heading);">📝 Daily Work Reporting Module</h2>
            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:13px;">Submit daily work progress, log hours, track blockers, and manage team EOD reports.</p>
        </div>
        <div>
            <button class="btn-secondary" onclick="exportReports('csv')">📄 Export CSV</button>
            <button class="btn-secondary" onclick="exportReports('pdf')">📕 Export PDF</button>
        </div>
    </div>

    <!-- Analytics HUD -->
    <div class="dwr-hud">
        <div class="hud-card">
            <div class="hud-lbl">Submissions Today</div>
            <div class="hud-val"><?= $countSubmittedToday ?></div>
            <div style="font-size:12px; color:var(--text-muted);">Active Employees Reporting</div>
        </div>
        <div class="hud-card">
            <div class="hud-lbl">Compliance Rate</div>
            <div class="hud-val" style="color:#10b981;"><?= $complianceRate ?>%</div>
            <div style="font-size:12px; color:var(--text-muted);">Overall Daily Velocity</div>
        </div>
        <div class="hud-card">
            <div class="hud-lbl">Total Hours Logged Today</div>
            <div class="hud-val" style="color:#3b82f6;"><?= number_format($totalHoursToday, 1) ?> hrs</div>
            <div style="font-size:12px; color:var(--text-muted);">Productive Time Captured</div>
        </div>
        <div class="hud-card">
            <div class="hud-lbl">Pending Manager Reviews</div>
            <div class="hud-val" style="color:#f59e0b;"><?= $pendingReviewsCount ?></div>
            <div style="font-size:12px; color:var(--text-muted);">Awaiting EOD Review</div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="dwr-tabs">
        <button class="dwr-tab-btn active" id="tabSubmitBtn" onclick="switchTab('submitTab')">✍️ Submit / Edit My Report</button>
        <button class="dwr-tab-btn" id="tabHistoryBtn" onclick="switchTab('historyTab')">📋 My History</button>
        <?php if ($isAdminOrManager): ?>
        <button class="dwr-tab-btn" id="tabTeamBtn" onclick="switchTab('teamTab')">👥 Team Review Board</button>
        <?php endif; ?>
    </div>

    <!-- TAB 1: Submit / Edit My Daily Report -->
    <div id="submitTab" class="tab-content" style="display:block;">
        <div class="dwr-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-heading);">
                    <?= $todayReport ? '✏️ Edit Today\'s Work Report ('.htmlspecialchars($todayDate).')' : '📝 Submit Today\'s Work Report ('.htmlspecialchars($todayDate).')' ?>
                    <span style="font-size:12px; font-weight:bold; background:rgba(16,185,129,0.1); color:#059669; padding:4px 10px; border-radius:20px; margin-left:8px;">⏱️ Auto-syncs to Timesheets</span>
                </h3>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn-primary" style="font-size:12px; background:linear-gradient(135deg, #6366f1 0%, #a855f7 100%);" onclick="generateAiEodSummary()">🤖 AI Auto-Summarize My Day</button>
                    <?php if (!empty($completedTasksToday)): ?>
                    <button type="button" class="btn-secondary" style="font-size:12px;" onclick="autoFillTasks()">⚡ Insert Today's Tasks (<?= count($completedTasksToday) ?>)</button>
                    <?php endif; ?>
                    <button type="button" class="btn-secondary" style="font-size:12px;" onclick="window.location.href='timesheets.php'">⏱️ View Timesheets</button>
                </div>
            </div>

            <form id="dwrForm" action="controllers/save_daily_report.php" method="POST">
                <input type="hidden" name="id" value="<?= $todayReport['id'] ?? '' ?>">
                
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label>Reporting Date *</label>
                            <input type="date" name="report_date" required value="<?= $todayReport['report_date'] ?? $todayDate ?>" max="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label>Key Tasks Completed Today *</label>
                            <textarea id="tasks_completed" name="tasks_completed" required placeholder="1. Implemented features X and Y&#10;2. Resolved bug #104&#10;3. Reviewed pull requests..."><?= htmlspecialchars($todayReport['tasks_completed'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Work In Progress (WIP)</label>
                            <textarea name="work_in_progress" placeholder="Tasks currently ongoing or queued..."><?= htmlspecialchars($todayReport['work_in_progress'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div>
                        <div class="form-group">
                            <label>Hours Logged Today</label>
                            <input type="number" step="0.25" min="0.5" max="24" name="hours_worked" value="<?= $todayReport['hours_worked'] ?? '8.00' ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Blockers / Challenges (If any)</label>
                            <textarea name="blockers" placeholder="Specify any impediments, pending approvals, or dependencies..."><?= htmlspecialchars($todayReport['blockers'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Plan for Tomorrow</label>
                            <textarea name="plan_for_tomorrow" placeholder="Key goals and deliverables planned for tomorrow..."><?= htmlspecialchars($todayReport['plan_for_tomorrow'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div style="margin-top:16px; text-align:right;">
                    <button type="submit" class="btn-primary">
                        💾 Submit Daily Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: My Submission History -->
    <div id="historyTab" class="tab-content" style="display:none;">
        <div class="dwr-card">
            <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:700; color:var(--text-heading);">My Daily Report Submissions</h3>
            
            <div class="table-responsive">
                <table class="custom-table" id="myHistoryTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tasks Completed</th>
                            <th>Work In Progress</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Reviewer Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($myReports)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:24px;">No daily reports submitted yet.</td></tr>
                        <?php else: foreach ($myReports as $rep): ?>
                        <tr>
                            <td style="font-weight:700; white-space:nowrap;"><?= htmlspecialchars($rep['report_date']) ?></td>
                            <td><?= nl2br(htmlspecialchars($rep['tasks_completed'])) ?></td>
                            <td><?= nl2br(htmlspecialchars($rep['work_in_progress'] ?? '-')) ?></td>
                            <td><strong><?= number_format($rep['hours_worked'], 1) ?> hrs</strong></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($rep['status']) ?>"><?= htmlspecialchars($rep['status']) ?></span>
                            </td>
                            <td style="font-style:italic; color:var(--text-muted);">
                                <?= !empty($rep['reviewer_feedback']) ? htmlspecialchars($rep['reviewer_feedback']) : 'None' ?>
                            </td>
                            <td>
                                <button class="btn-secondary" style="font-size:12px; padding:4px 8px;" onclick='populateEdit(<?= json_encode($rep) ?>)'>✏️ Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Team Review Board (Admin/Manager) -->
    <?php if ($isAdminOrManager): ?>
    <div id="teamTab" class="tab-content" style="display:none;">
        <div class="dwr-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-heading);">Team EOD Submissions & Review Engine</h3>
                
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="date" id="filterDate" value="<?= $todayDate ?>" onchange="loadTeamReports()" style="padding:6px 12px; border-radius:6px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);">
                    <select id="filterUser" onchange="loadTeamReports()" style="padding:6px 12px; border-radius:6px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);">
                        <option value="">All Employees</option>
                        <?php foreach($allUsers as $u): ?>
                        <option value="<?= htmlspecialchars($u['login_id']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterStatus" onchange="loadTeamReports()" style="padding:6px 12px; border-radius:6px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);">
                        <option value="">All Statuses</option>
                        <option value="Submitted">Submitted (Pending)</option>
                        <option value="Reviewed">Reviewed</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="teamReportsTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Tasks Completed</th>
                            <th>Blockers</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Manager Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="teamTableBody">
                        <tr><td colspan="8" style="text-align:center; padding:24px;">Loading team submissions...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-content">
        <h3 style="margin:0 0 16px 0; color:var(--text-heading);">Grade & Review Daily Report</h3>
        <form id="reviewForm" onsubmit="submitReview(event)">
            <input type="hidden" id="rev_report_id" name="report_id">
            
            <div class="form-group">
                <label>Review Status</label>
                <select name="status" id="rev_status">
                    <option value="Reviewed">Reviewed (Approved)</option>
                    <option value="NeedsRevision">Needs Revision</option>
                </select>
            </div>

            <div class="form-group">
                <label>Manager Feedback / Comments</label>
                <textarea name="reviewer_feedback" id="rev_feedback" placeholder="Add feedback or notes for employee..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn-secondary" onclick="closeReviewModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Review</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.dwr-tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabId).style.display = 'block';
    if(tabId === 'submitTab') document.getElementById('tabSubmitBtn').classList.add('active');
    if(tabId === 'historyTab') document.getElementById('tabHistoryBtn').classList.add('active');
    if(tabId === 'teamTab') {
        document.getElementById('tabTeamBtn').classList.add('active');
        loadTeamReports();
    }
}

function autoFillTasks() {
    const tasks = <?= json_encode($completedTasksToday) ?>;
    if (!tasks || tasks.length === 0) return;
    
    let text = "Tasks completed today:\n";
    tasks.forEach((t, i) => {
        text += `${i+1}. ${t.name} (${t.task_id})\n`;
    });
    
    const elem = document.getElementById('tasks_completed');
    elem.value = elem.value ? (elem.value + "\n" + text) : text;
}

function generateAiEodSummary() {
    const btn = event.target;
    const origText = btn.innerHTML;
    btn.innerHTML = '🤖 Analyzing tasks... ⏳';
    btn.disabled = true;

    fetch('controllers/ai_summarize_eod.php', { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = origText;
            btn.disabled = false;
            if(res.status === 'success') {
                if(res.tasks_completed) document.querySelector('#dwrForm textarea[name="tasks_completed"]').value = res.tasks_completed;
                if(res.work_in_progress) document.querySelector('#dwrForm textarea[name="work_in_progress"]').value = res.work_in_progress;
                if(res.plan_for_tomorrow) document.querySelector('#dwrForm textarea[name="plan_for_tomorrow"]').value = res.plan_for_tomorrow;
                if(res.hours_worked) document.querySelector('#dwrForm input[name="hours_worked"]').value = res.hours_worked;
            } else {
                alert(res.message || 'Error generating AI summary.');
            }
        })
        .catch(err => {
            btn.innerHTML = origText;
            btn.disabled = false;
            alert('Failed to connect to AI Summarizer.');
        });
}

function populateEdit(report) {
    switchTab('submitTab');
    document.querySelector('#dwrForm input[name="id"]').value = report.id;
    document.querySelector('#dwrForm input[name="report_date"]').value = report.report_date;
    document.querySelector('#dwrForm textarea[name="tasks_completed"]').value = report.tasks_completed;
    document.querySelector('#dwrForm textarea[name="work_in_progress"]').value = report.work_in_progress || '';
    document.querySelector('#dwrForm textarea[name="blockers"]').value = report.blockers || '';
    document.querySelector('#dwrForm textarea[name="plan_for_tomorrow"]').value = report.plan_for_tomorrow || '';
    document.querySelector('#dwrForm input[name="hours_worked"]').value = report.hours_worked;
}

function loadTeamReports() {
    const tbody = document.getElementById('teamTableBody');
    if(!tbody) return;
    
    const date = document.getElementById('filterDate').value;
    const user = document.getElementById('filterUser').value;
    const status = document.getElementById('filterStatus').value;

    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">Fetching records... ⏳</td></tr>';

    let url = `api/get_daily_reports.php?date=${encodeURIComponent(date)}&user=${encodeURIComponent(user)}&status=${encodeURIComponent(status)}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if(!data.reports || data.reports.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--text-muted);">No daily reports match the filter.</td></tr>';
                return;
            }
            let html = '';
            data.reports.forEach(r => {
                html += `<tr>
                    <td style="font-weight:700;">${r.user_name || r.user_id}</td>
                    <td>${r.report_date}</td>
                    <td>${escapeHtml(r.tasks_completed)}</td>
                    <td style="color:${r.blockers ? '#ef4444' : 'inherit'};">${escapeHtml(r.blockers || '-')}</td>
                    <td><strong>${parseFloat(r.hours_worked).toFixed(1)} hrs</strong></td>
                    <td><span class="status-badge status-${r.status}">${r.status}</span></td>
                    <td style="font-style:italic; font-size:12px;">${escapeHtml(r.reviewer_feedback || '-')}</td>
                    <td><button class="btn-secondary" style="font-size:12px; padding:4px 8px;" onclick='openReviewModal(${JSON.stringify(r)})'>⭐ Grade</button></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#ef4444;">Failed to load reports.</td></tr>';
        });
}

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\n/g, "<br>");
}

function openReviewModal(rep) {
    document.getElementById('rev_report_id').value = rep.id;
    document.getElementById('rev_status').value = rep.status === 'NeedsRevision' ? 'NeedsRevision' : 'Reviewed';
    document.getElementById('rev_feedback').value = rep.reviewer_feedback || '';
    document.getElementById('reviewModal').style.display = 'flex';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function submitReview(e) {
    e.preventDefault();
    const form = document.getElementById('reviewForm');
    const formData = new FormData(form);

    fetch('controllers/review_daily_report.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            closeReviewModal();
            loadTeamReports();
        } else {
            alert(res.message || 'Error saving review');
        }
    });
}

function exportReports(format) {
    const table = document.querySelector('.tab-content:not([style*="display: none"]) table');
    if(!table) return alert('No table visible to export');

    if (format === 'csv') {
        let csv = [];
        const rows = table.querySelectorAll('tr');
        for (let row of rows) {
            let cols = row.querySelectorAll('td, th');
            let rowData = [];
            for (let col of cols) {
                let text = col.innerText.replace(/"/g, '""').replace(/\n/g, ' ');
                rowData.push('"' + text + '"');
            }
            csv.push(rowData.join(','));
        }
        let blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        let url = window.URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = `Daily_Reports_Export_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
    } else if (format === 'pdf') {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.text("Daily Work Reports Summary", 14, 15);
        doc.autoTable({ html: table });
        doc.save(`Daily_Reports_Export_${new Date().toISOString().slice(0,10)}.pdf`);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
