<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only admins can access
if (!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>Executive privileges required.</p></div>");
}

$total_headcount = 0;
$active_projects = 0;
$open_tickets = 0;
$pending_onboarding = 0;
$dept_labels = [];
$dept_counts = [];
$ticket_labels = [];
$ticket_counts = [];

try {
    // 1. Total Headcount
    $total_headcount = $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'Deactivated' AND role = 'Employee'")->fetchColumn() ?: 0;

    // 2. Active Projects
    $active_projects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Active'")->fetchColumn() ?: 0;

    // 3. Support Tickets (Open/In Progress)
    $open_tickets = $pdo->query("SELECT COUNT(*) FROM unified_tickets WHERE status != 'Closed'")->fetchColumn() ?: 0;

    // 4. Total Revenue
    try {
        $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM invoices WHERE status = 'Paid'")->fetchColumn() ?: 0;
        $currency = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'")->fetchColumn() ?: '₹';
    } catch (Exception $e) {
        $total_revenue = 0;
        $currency = '₹';
    }

    // Data for Chart: Headcount by Department
    $dept_stmt = $pdo->query("SELECT department, COUNT(*) as count FROM users WHERE status != 'Deactivated' GROUP BY department");
    $dept_data = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dept_data as $row) {
        $dept_labels[] = $row['department'] ?: 'Unassigned';
        $dept_counts[] = $row['count'];
    }

    // Data for Chart: Tickets by Priority
    $ticket_stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM unified_tickets WHERE status != 'Closed' GROUP BY priority");
    $ticket_data = $ticket_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ticket_data as $row) {
        $ticket_labels[] = $row['priority'] ?: 'Normal';
        $ticket_counts[] = $row['count'];
    }
} catch (Exception $e) {
    echo "<div style='padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.hud-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}
.hud-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.hud-card::after {
    content: '';
    position: absolute;
    top: 0; right: 0; width: 120px; height: 120px;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
    border-radius: 50%;
    transform: translate(30%, -30%);
    pointer-events: none;
}
.hud-card .title {
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.hud-card .value {
    color: var(--text-heading);
    font-size: 36px;
    font-weight: 900;
}
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
}
.chart-container {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}
</style>

<div class="content-section active">
    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h2 style="font-size: 28px; font-weight: 900; color: var(--text-heading); margin-bottom: 8px;">🌐 Global Command HUD</h2>
            <p style="color: var(--text-muted); font-size: 15px;">Real-time metrics and aggregated data across all enterprise modules.</p>
        </div>
        <button onclick="toggleTVMode()" style="background: #4f46e5; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
            📺 TV / Fullscreen Mode
        </button>
    </div>

    <!-- Top KPIs -->
    <div class="hud-grid">
        <div class="hud-card" style="border-top: 4px solid #6366f1;">
            <div class="title">Total Headcount</div>
            <div class="value"><?= number_format($total_headcount) ?></div>
        </div>
        <div class="hud-card" style="border-top: 4px solid #10b981;">
            <div class="title">Active Projects</div>
            <div class="value"><?= number_format($active_projects) ?></div>
        </div>
        <div class="hud-card" style="border-top: 4px solid #f59e0b;">
            <div class="title">Open Support Tickets</div>
            <div class="value"><?= number_format($open_tickets) ?></div>
        </div>
        <div class="hud-card" style="border-top: 4px solid #ef4444;">
            <div class="title">Total Revenue</div>
            <div class="value"><?= htmlspecialchars($currency) . number_format($total_revenue) ?></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 16px; color: var(--text-heading);">Headcount by Department</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
        <div class="chart-container">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 16px; color: var(--text-heading);">Open Tickets by Priority</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="ticketChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
const textColor = isDarkMode ? '#cbd5e1' : '#475569';
const gridColor = isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

// Department Headcount Chart
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($dept_labels) ?>,
        datasets: [{
            label: 'Employees',
            data: <?= json_encode($dept_counts) ?>,
            backgroundColor: '#6366f1',
            borderRadius: 6,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, color: textColor },
                grid: { color: gridColor }
            },
            x: {
                ticks: { color: textColor },
                grid: { display: false }
            }
        }
    }
});

// Tickets by Priority Chart
new Chart(document.getElementById('ticketChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($ticket_labels) ?>,
        datasets: [{
            data: <?= json_encode($ticket_counts) ?>,
            backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { color: textColor } }
        },
        cutout: '70%'
    }
});
</script>

<script>
let isTVMode = false;
let refreshInterval = null;

function toggleTVMode() {
    isTVMode = !isTVMode;
    const sidebar = document.querySelector('.sidebar');
    const appContainer = document.querySelector('.app-container');
    const header = document.querySelector('.header') || document.querySelector('header');
    const mainContent = document.querySelector('.main-content');
    const breadcrumbs = document.querySelector('.breadcrumbs');

    if (isTVMode) {
        if (sidebar) sidebar.style.display = 'none';
        if (header) header.style.display = 'none';
        if (breadcrumbs) breadcrumbs.style.display = 'none';
        if (appContainer) appContainer.style.display = 'block';
        if (mainContent) {
            mainContent.style.marginLeft = '0';
            mainContent.style.padding = '30px';
        }
        document.documentElement.requestFullscreen().catch(e => console.log(e));
        
        // Auto-refresh every 60 seconds
        refreshInterval = setInterval(() => {
            window.location.reload();
        }, 60000);
    } else {
        if (sidebar) sidebar.style.display = '';
        if (header) header.style.display = '';
        if (breadcrumbs) breadcrumbs.style.display = '';
        if (appContainer) appContainer.style.display = '';
        if (mainContent) {
            mainContent.style.marginLeft = '';
            mainContent.style.padding = '';
        }
        if (document.fullscreenElement) {
            document.exitFullscreen();
        }
        clearInterval(refreshInterval);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
