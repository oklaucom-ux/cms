<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'view_expenses');

$isAdmin = hasPermission($pdo, 'approve_expenses') || (in_array($_SESSION['role'], ['Admin', 'Super Admin']));

// Fetch all expenses with project context
// Auto-Migrate schema

// Fetch based on role/branch
if ($isAdmin) {
    $expenses = $pdo->query("
        SELECT e.*, p.name AS project_name, p.budget AS project_budget
        FROM expenses e
        LEFT JOIN projects p ON e.project_id = p.id
        ORDER BY e.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $myBranch = $pdo->query("SELECT branch_id FROM users WHERE login_id = '{$_SESSION['login_id']}'")->fetchColumn() ?: 'Global HQ';
    $stmt = $pdo->prepare("
        SELECT e.*, p.name AS project_name, p.budget AS project_budget
        FROM expenses e
        LEFT JOIN projects p ON e.project_id = p.id
        WHERE e.user_id = ? OR e.branch_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$_SESSION['login_id'], $myBranch]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch projects for dropdown
$projects = $pdo->query("SELECT id, name, budget FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Burn Rate Summary per Project
$burnSummary = $pdo->query("
    SELECT p.id, p.name, p.budget,
           COALESCE(SUM(CASE WHEN e.status='Approved' THEN e.amount ELSE 0 END), 0) AS approved_spend,
           COALESCE(SUM(CASE WHEN e.status='Pending'  THEN e.amount ELSE 0 END), 0) AS pending_spend,
           COUNT(e.id) AS total_requests
    FROM projects p
    LEFT JOIN expenses e ON e.project_id = p.id
    GROUP BY p.id
")->fetchAll(PDO::FETCH_ASSOC);

// Overall totals
$totals = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status='Approved' THEN amount ELSE 0 END), 0) AS total_approved,
        COALESCE(SUM(CASE WHEN status='Pending'  THEN amount ELSE 0 END), 0) AS total_pending,
        COALESCE(SUM(CASE WHEN status='Rejected' THEN amount ELSE 0 END), 0) AS total_rejected,
        COUNT(*) AS total_count
    FROM expenses
")->fetch(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>💸 Corporate Expense Engine</h2>
        <button class="add-button" onclick="openExpenseModal()">+ Log Expense</button>
    </div>

    <!-- KPI Strip -->
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:16px; margin-bottom:28px;">
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.05); ">
            <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:700;">Total Approved</div>
            <div style="font-size:26px; font-weight:800; color:#10b981; margin-top:4px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totals['total_approved'], 2) ?></div>
        </div>
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.05); ">
            <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:700;">Pending Review</div>
            <div style="font-size:26px; font-weight:800; color:#f59e0b; margin-top:4px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totals['total_pending'], 2) ?></div>
        </div>
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.05); ">
            <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:700;">Total Rejected</div>
            <div style="font-size:26px; font-weight:800; color:#dc2626; margin-top:4px;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totals['total_rejected'], 2) ?></div>
        </div>
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.05); ">
            <div style="font-size:11px; color:#6b7280; text-transform:uppercase; font-weight:700;">Total Requests</div>
            <div style="font-size:26px; font-weight:800; color:#6366f1; margin-top:4px;"><?= $totals['total_count'] ?></div>
        </div>
    </div>

    <!-- Budget Burn Rate by Project -->
    <?php if (!empty($burnSummary)): ?>
    <h3 style="color:#374151; margin-bottom:16px; font-size:16px;">📊 Budget Burn Rate by Project</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr)); gap:16px; margin-bottom:32px;">
        <?php foreach($burnSummary as $b):
            $pct = $b['budget'] > 0 ? min(($b['approved_spend'] / $b['budget']) * 100, 100) : 0;
            $barColor = $pct >= 90 ? '#dc2626' : ($pct >= 70 ? '#f59e0b' : '#10b981');
            $overBudget = $b['approved_spend'] > $b['budget'];
        ?>
        <div style="background:white; border-radius:12px; padding:20px; box-shadow:0 4px 6px rgba(0,0,0,0.05); ">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="color:#111827; font-size:14px;"><?= htmlspecialchars($b['name']) ?></strong>
                <?php if($overBudget): ?>
                <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px;">OVER BUDGET</span>
                <?php endif; ?>
            </div>
            <div style="background:#f3f4f6; border-radius:99px; height:10px; margin-bottom:10px; overflow:hidden;">
                <div style="background:<?= $barColor ?>; height:100%; width:<?= round($pct, 1) ?>%; transition:width 0.5s; border-radius:99px;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#6b7280;">
                <span>Spent: <strong style="color:<?= $barColor ?>;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($b['approved_spend'], 2) ?></strong></span>
                <span>Budget: <strong><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($b['budget'], 2) ?></strong></span>
                <span><?= round($pct, 1) ?>%</span>
            </div>
            <?php if($b['pending_spend'] > 0): ?>
            <div style="font-size:12px; color:#f59e0b; margin-top:6px;">⏳ +$<?= number_format($b['pending_spend'],2) ?> pending approval</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Expenses Table -->
    <h3 style="color:#374151; margin-bottom:16px; font-size:16px;">📋 Expense Ledger</h3>
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Submitted By</th>
                    <th>Category</th>
                    <th>Project</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <?php if($isAdmin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($expenses as $e): 
                    $statusColor = $e['status'] === 'Approved' ? '#10b981' : ($e['status'] === 'Rejected' ? '#dc2626' : '#f59e0b');
                ?>
                <tr>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($e['created_at']))) ?></td>
                    <td><?= htmlspecialchars($e['user_id']) ?></td>
                    <td><?= htmlspecialchars($e['category']) ?></td>
                    <td><?= htmlspecialchars($e['project_name'] ?? 'General') ?></td>
                    <td><?= htmlspecialchars($e['description']) ?></td>
                    <td style="font-weight:700;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($e['amount'], 2) ?></td>
                    <td><span style="background:<?= $statusColor ?>22; color:<?= $statusColor ?>; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:700;"><?= htmlspecialchars($e['status']) ?></span></td>
                    <?php if($isAdmin): ?>
                    <td class="action-buttons">
                        <?php if($e['status'] === 'Pending'): ?>
                        <form method="POST" action="controllers/approve_expense.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="edit-button" style="background:#10b981; color:white; border:none;">✓ Approve</button>
                        </form>
                        <form method="POST" action="controllers/approve_expense.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="delete-button">✗ Reject</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="controllers/delete_expense.php" style="display:inline;" onsubmit="return confirm('Delete this expense record?')">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="delete-button">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($expenses)): ?>
                <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:40px;">No expense records found. Log your first expense above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generic Modal -->
<div id="genericModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Log Expense</h2>
        <form id="modalForm" method="POST" action="controllers/save_expense.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div id="modalFields"></div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="submit">Submit Expense</button>
            </div>
        </form>
    </div>
</div>

<script>
const projects = <?= json_encode($projects) ?>;

function openExpenseModal() {
    document.getElementById('modalTitle').textContent = 'Log New Expense';
    document.getElementById('modalForm').action = 'controllers/save_expense.php';

    let html = '';
    html += `<div class="form-group"><label>Category</label><select name="category" required>
                <option>Travel</option>
                <option>Software / SaaS</option>
                <option>Hardware</option>
                <option>Marketing</option>
                <option>Office Supplies</option>
                <option>Meals & Entertainment</option>
                <option>Training</option>
                <option>Other</option>
             </select></div>`;

    html += `<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" name="amount" required placeholder="0.00"></div>
        <div class="form-group"><label>Link to Project</label><select name="project_id">
            <option value="0">General (No Project)</option>`;
    projects.forEach(p => {
        html += `<option value="${p.id}">${p.name}</option>`;
    });
    html += `</select></div></div>`;

    html += `<div class="form-group"><label>Description</label><textarea name="description" required placeholder="Briefly describe this expense..."></textarea></div>`;
    html += `<div class="form-group"><label>Receipt URL (optional)</label><input type="text" name="receipt_url" placeholder="https://..."></div>`;

    document.getElementById('modalFields').innerHTML = html;
    document.getElementById('genericModal').style.display = 'block';
}

function closeModal() { document.getElementById('genericModal').style.display = 'none'; }
</script>

<?php require_once 'includes/footer.php'; ?>

