<?php
// kpi.php
session_start();
if (!isset($_SESSION['login_id'])) {
    header("Location: index.php");
    exit;
}

$page_title = "KPI & Targets";
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$isAdmin = hasPermission($pdo, 'manage_kpi');
$user_id = $_SESSION['login_id'];

// Fetch Users for Admin assignment
$users = [];
if ($isAdmin) {
    $stmt = $pdo->query("SELECT login_id, name, role FROM users WHERE status = 'Active'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch KPIs
$kpis = [];
if ($isAdmin) {
    // Admin sees all KPIs with User Info
    $stmt = $pdo->query("
        SELECT k.*, u.name as user_name 
        FROM kpi_targets k
        JOIN users u ON k.user_id = u.login_id
        ORDER BY k.deadline ASC
    ");
    $kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Employee sees only their own KPIs
    $stmt = $pdo->prepare("SELECT * FROM kpi_targets WHERE user_id = ? ORDER BY deadline ASC");
    $stmt->execute([$user_id]);
    $kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="header-action">
        <h2>KPI & Targets</h2>
        <div style="display:flex;gap:10px;">
            <a href="controllers/export_csv.php?table=kpi_targets" class="view-button" style="text-decoration:none;font-size:13px;font-weight:600;">📥 Export CSV</a>
            <?php if($isAdmin): ?>
            <button class="btn btn-primary" onclick="openAssignKpiModal()">
                <i class="fas fa-bullseye"></i> Assign New KPI
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="kpi-grid">
        <?php foreach ($kpis as $kpi): 
            $progress = 0;
            if ($kpi['target_value'] > 0) {
                $progress = ($kpi['current_value'] / $kpi['target_value']) * 100;
                if ($progress > 100) $progress = 100;
            }
            
            $status_class = 'status-track';
            if ($kpi['status'] === 'Achieved') $status_class = 'status-achieved';
            elseif ($kpi['status'] === 'At Risk') $status_class = 'status-risk';
        ?>
        <div class="card kpi-card" data-kpi-id="<?php echo $kpi['id']; ?>">
            <div class="kpi-card-header">
                <h3><?php echo htmlspecialchars($kpi['title']); ?></h3>
                <span class="badge <?php echo $status_class; ?>"><?php echo $kpi['status']; ?></span>
            </div>
            <?php if($isAdmin): ?>
                <div class="kpi-assignee"><i class="fas fa-user"></i> <?php echo htmlspecialchars($kpi['user_name'] ?? ''); ?></div>
            <?php endif; ?>
            <div class="kpi-desc"><?php echo htmlspecialchars($kpi['description']); ?></div>
            
            <div class="kpi-metric">
                <span class="current"><?php echo number_format($kpi['current_value'], 2); ?></span>
                <span class="divider">/</span>
                <span class="target"><?php echo number_format($kpi['target_value'], 2); ?></span>
                <span class="unit"><?php echo htmlspecialchars($kpi['unit']); ?></span>
            </div>

            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
            </div>
            
            <div class="kpi-footer">
                <div class="deadline">
                    <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($kpi['deadline'])); ?>
                </div>
                <?php if ($kpi['status'] !== 'Achieved'): ?>
                    <button class="btn btn-sm btn-outline log-progress-btn" onclick="openLogProgressModal(<?php echo $kpi['id']; ?>)">
                        <i class="fas fa-plus"></i> Log
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($kpis)): ?>
            <div class="no-data">No KPIs assigned yet.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign KPI Modal -->
<?php if($isAdmin): ?>
<div class="modal" id="assignKpiModal">
    <div class="modal-content">
        <span class="close" onclick="closeAssignKpiModal()">&times;</span>
        <h2>Assign Target</h2>
        <form id="assignKpiForm" method="POST">
            <div class="form-group">
                <label>Assign To</label>
                <select name="user_id" required>
                    <option value="">Select Employee...</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?php echo htmlspecialchars($u['login_id']); ?>">
                            <?php echo htmlspecialchars($u['name'] . ' (' . $u['role'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required placeholder="e.g. Q4 Sales Goals">
            </div>
            <div class="form-group">
                <label>Description (Optional)</label>
                <textarea name="description" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Target Value</label>
                    <input type="number" step="0.01" name="target_value" required>
                </div>
                <div class="form-group">
                    <label>Unit (e.g. USD, Deals, Hours)</label>
                    <input type="text" name="unit" required>
                </div>
            </div>
            <div class="form-group">
                <label>Deadline</label>
                <input type="date" name="deadline" required>
            </div>
            <button type="submit" class="btn btn-primary">Assign KPI</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Log Progress Modal -->
<div class="modal" id="logProgressModal">
    <div class="modal-content">
        <span class="close" onclick="closeLogProgressModal()">&times;</span>
        <h2>Log Progress</h2>
        <form id="logProgressForm" method="POST">
            <input type="hidden" name="kpi_id" id="log_kpi_id">
            <div class="form-group">
                <label>Value Added (Numeric)</label>
                <input type="number" step="0.01" name="value_added" required placeholder="e.g. 50">
            </div>
            <div class="form-group">
                <label>Note (Optional)</label>
                <textarea name="note" rows="2" placeholder="e.g. Closed deal with Acme Corp"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Log Progress</button>
        </form>
    </div>
</div>

<style>
/* KPI Specific Styles */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.kpi-card {
    display: flex;
    flex-direction: column;
}
.kpi-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.kpi-card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-color);
}
.kpi-assignee, .kpi-desc {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}
.kpi-metric {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
    font-family: inherit;
}
.kpi-metric .current {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
}
.kpi-metric .divider {
    font-size: 1.2rem;
    color: var(--border-color);
}
.kpi-metric .target {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-muted);
}
.kpi-metric .unit {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-left: 0.25rem;
}
.progress-bar-container {
    height: 8px;
    background: var(--bg-hover);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 1rem;
}
.progress-bar {
    height: 100%;
    background: var(--primary-color);
    transition: width 0.5s ease-in-out;
}
.status-achieved { background: rgba(16, 185, 129, 0.1); color: #10B981; }
.status-risk { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
.status-track { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }

.kpi-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}
.deadline {
    font-size: 0.85rem;
    color: var(--text-muted);
}
.form-row {
    display: flex;
    gap: 1rem;
}
.form-row .form-group {
    flex: 1;
}
</style>

<script>
function openAssignKpiModal() {
    if(document.getElementById('assignKpiModal')) {
        document.getElementById('assignKpiModal').style.display = 'block';
    }
}
function closeAssignKpiModal() {
    if(document.getElementById('assignKpiModal')) {
        document.getElementById('assignKpiModal').style.display = 'none';
    }
}

function openLogProgressModal(id) {
    document.getElementById('log_kpi_id').value = id;
    document.getElementById('logProgressModal').style.display = 'block';
}
function closeLogProgressModal() {
    document.getElementById('logProgressModal').style.display = 'none';
}

if(document.getElementById('assignKpiForm')) {
    document.getElementById('assignKpiForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        try {
            const response = await fetch('controllers/save_kpi.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
                submitBtn.disabled = false;
            }
        } catch (error) {
            alert('Error assigning KPI');
            submitBtn.disabled = false;
        }
    });
}

if(document.getElementById('logProgressForm')) {
    document.getElementById('logProgressForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        try {
            const response = await fetch('controllers/log_kpi.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
                submitBtn.disabled = false;
            }
        } catch (error) {
            alert('Error logging progress');
            submitBtn.disabled = false;
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
