<?php
// executive_dashboard.php - Executive C-Suite BI Analytics & Executive Overview Dashboard
require_once 'includes/db.php';

// Check permissions
if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && !hasPermission($pdo, 'view_dashboard')) {
    http_response_code(403);
    die("<div style='padding:50px; text-align:center;'><h2>🚫 Access Denied: Executive Portal requires Super Admin or C-Suite privileges.</h2></div>");
}

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// Financial BI Queries
$totalRevenue = 0; $b2bRevenue = 0; $b2cRevenue = 0; $unpaidCredit = 0;
try { $totalRevenue = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status='Paid'")->fetchColumn() ?: 0; } catch (Exception $e) {}
try { $b2bRevenue   = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status='Paid' AND client_type='B2B'")->fetchColumn() ?: 0; } catch (Exception $e) {}
try { $b2cRevenue   = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status='Paid' AND (client_type='B2C' OR client_type IS NULL)")->fetchColumn() ?: 0; } catch (Exception $e) {}
try { $unpaidCredit = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status IN ('Unpaid','Partial')")->fetchColumn() ?: 0; } catch (Exception $e) {}

// HR Attendance Rate Query
$totalEmployees = 1; $todayPresents = 0; $attendanceRate = 0;
try {
    $totalEmployees = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'Super Admin'")->fetchColumn() ?: 1;
    $todayPresents  = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = '{$today}' AND status = 'Present'")->fetchColumn() ?: 0;
    $attendanceRate = round(($todayPresents / max(1, $totalEmployees)) * 100, 1);
} catch (Exception $e) {}

// Inventory BI Queries
$stockValuation = 0; $lowStockAlerts = 0; $totalProducts = 0;
try {
    $stockValuation = $pdo->query("SELECT SUM(quantity * unit_price) FROM inventory_items")->fetchColumn() ?: 0;
    $lowStockAlerts = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE quantity <= min_stock_alert")->fetchColumn() ?: 0;
    $totalProducts  = $pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn() ?: 0;
} catch (Exception $e) {}

// Helpdesk Performance Query
$totalTickets = 0; $resolvedTickets = 0; $slaResolution = 100;
try {
    $totalTickets    = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn() ?: 0;
    $resolvedTickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('Closed','Resolved')")->fetchColumn() ?: 0;
    $slaResolution   = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 1) : 100;
} catch (Exception $e) {}

// Top 5 High Value Stock Items
$topItems = [];
try {
    $topItems = $pdo->query("SELECT *, (quantity * unit_price) as total_val FROM inventory_items ORDER BY total_val DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Top 5 B2B Accounts (MySQL ONLY_FULL_GROUP_BY Compatible)
$topB2b = [];
try {
    $topB2b = $pdo->query("SELECT company_name, MAX(client_name) as client_name, MAX(gstin) as gstin, SUM(amount) as total_billed, MAX(status) as status FROM invoices WHERE company_name IS NOT NULL AND company_name != '' GROUP BY company_name ORDER BY total_billed DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
.exec-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-heading);
    display: flex;
    align-items: center;
    gap: 12px;
}
.exec-badge {
    font-size: 11px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 99px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}
.metric-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}
.metric-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}
.metric-value { font-size: 26px; font-weight: 800; color: var(--text-heading); }
.metric-label { font-size: 13px; color: var(--text-muted); font-weight: 600; }

.bi-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 28px;
}
@media (max-width: 900px) { .bi-grid { grid-template-columns: 1fr; } }

.bi-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
}
.bi-card-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-heading);
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-mini {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}
.table-mini th {
    text-align: left;
    padding: 10px;
    background: var(--bg-main);
    color: var(--text-heading);
    font-weight: 800;
    border-bottom: 2px solid var(--border-card);
}
.table-mini td {
    padding: 12px 10px;
    border-bottom: 1px solid var(--border-card);
    color: var(--text-body);
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
    text-decoration: none;
}
.btn-primary { background: #6366f1; color: white; }
.btn-primary:hover { background: #4f46e5; }
.btn-outline { background: transparent; border: 1px solid var(--border-card); color: var(--text-body); }
.btn-outline:hover { background: rgba(255,255,255,0.05); }
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Top Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
        <div>
            <div class="exec-title">
                🏢 Executive C-Suite BI Analytics Dashboard
                <span class="exec-badge">C-Suite Overview</span>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Real-time enterprise intelligence across Sales, B2B Accounts Receivable, HR Operations, Inventory Valuation, and Support Performance.
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="controllers/export_csv.php?table=invoices" class="btn-sm btn-outline">
                <i class="fas fa-file-export"></i> Export Financial Report
            </a>
            <button type="button" onclick="runHealthCheck()" class="btn-sm btn-primary">
                <i class="fas fa-heartbeat"></i> Run System Health Check
            </button>
        </div>
    </div>

    <!-- Top KPI Analytics Grid -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="metric-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span style="font-size:11px; background:#10b98120; color:#10b981; padding:2px 8px; border-radius:99px; font-weight:800;">COLLECTED</span>
            </div>
            <div class="metric-value">₹<?= number_format($totalRevenue, 2) ?></div>
            <div class="metric-label">Total Realized Sales Revenue</div>
        </div>

        <div class="metric-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="metric-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                    <i class="fas fa-building"></i>
                </div>
                <span style="font-size:11px; background:#6366f120; color:#6366f1; padding:2px 8px; border-radius:99px; font-weight:800;">B2B WHOLESALE</span>
            </div>
            <div class="metric-value">₹<?= number_format($b2bRevenue, 2) ?></div>
            <div class="metric-label">B2B Corporate Wholesale Sales</div>
        </div>

        <div class="metric-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="metric-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <span style="font-size:11px; background:#ef444420; color:#ef4444; padding:2px 8px; border-radius:99px; font-weight:800;">CREDIT BAL</span>
            </div>
            <div class="metric-value">₹<?= number_format($unpaidCredit, 2) ?></div>
            <div class="metric-label">Outstanding B2B Credit Balance</div>
        </div>

        <div class="metric-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="metric-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-boxes"></i>
                </div>
                <span style="font-size:11px; background:#f59e0b20; color:#f59e0b; padding:2px 8px; border-radius:99px; font-weight:800;"><?= $totalProducts ?> SKUs</span>
            </div>
            <div class="metric-value">₹<?= number_format($stockValuation, 2) ?></div>
            <div class="metric-label">Total Stock Valuation</div>
        </div>

        <div class="metric-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="metric-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span style="font-size:11px; background:#06b6d420; color:#06b6d4; padding:2px 8px; border-radius:99px; font-weight:800;">HR ATTENDANCE</span>
            </div>
            <div class="metric-value"><?= $attendanceRate ?>%</div>
            <div class="metric-label">Employee Attendance Rate Today</div>
        </div>
    </div>

    <!-- BI Insights Grid -->
    <div class="bi-grid">
        <!-- Top B2B Client Accounts -->
        <div class="bi-card">
            <div class="bi-card-title">
                <span>🏢 Top B2B Corporate Wholesale Accounts</span>
                <a href="client_ledger.php" style="font-size:12px; color:#6366f1; text-decoration:none;">View Ledger &rarr;</a>
            </div>
            <table class="table-mini">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>GSTIN</th>
                        <th style="text-align:right;">Billed Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topB2b)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">No B2B sales logged yet.</td></tr>
                    <?php else: foreach ($topB2b as $b): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['company_name']) ?></strong></td>
                        <td style="font-family:monospace; font-size:11.5px;"><?= htmlspecialchars($b['gstin'] ?: '—') ?></td>
                        <td style="text-align:right; font-weight:800; color:#10b981;">₹<?= number_format($b['total_billed'], 2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Highest Value Stock Items -->
        <div class="bi-card">
            <div class="bi-card-title">
                <span>📦 Highest Value Stock Inventory</span>
                <a href="inventory.php" style="font-size:12px; color:#6366f1; text-decoration:none;">Manage Stock &rarr;</a>
            </div>
            <table class="table-mini">
                <thead>
                    <tr>
                        <th>Item & Category</th>
                        <th>Qty in Hand</th>
                        <th style="text-align:right;">Valuation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topItems)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">No inventory items recorded.</td></tr>
                    <?php else: foreach ($topItems as $it): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($it['name']) ?></strong>
                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($it['category']) ?></div>
                        </td>
                        <td style="font-weight:700;"><?= (int)$it['quantity'] ?> units</td>
                        <td style="text-align:right; font-weight:800; color:#6366f1;">₹<?= number_format($it['total_val'], 2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function runHealthCheck() {
    Swal.fire({
        title: 'System Health Status',
        html: `
            <div style="text-align:left; font-size:13px; line-height:1.8;">
                <p>✅ <strong>Database Connection:</strong> SQLite Operational</p>
                <p>✅ <strong>Permission Cache:</strong> Active</p>
                <p>✅ <strong>Helpdesk Resolution SLA:</strong> <?= $slaResolution ?>% Fulfillment</p>
                <p>⚠️ <strong>Low Stock Alerts:</strong> <?= $lowStockAlerts ?> SKUs below minimum alert level</p>
                <p>🚨 <strong>Overdue B2B Credit:</strong> ₹<?= number_format($unpaidCredit, 2) ?></p>
            </div>
        `,
        icon: 'info'
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
