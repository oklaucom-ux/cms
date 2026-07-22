<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Only admins can access
if (!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>Executive privileges required.</p></div>");
}

// 1. Total Headcount
$total_headcount = $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'Deactivated' AND role = 'Employee'")->fetchColumn() ?: 0;

// 2. Active Projects
$active_projects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Active'")->fetchColumn() ?: 0;

// 3. Support Tickets (Open/In Progress)
$open_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Closed'")->fetchColumn() ?: 0;

// 4. Pending Onboarding
$pending_onboarding = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Pending_Docs'")->fetchColumn() ?: 0;

// Data for Chart: Headcount by Department
$dept_stmt = $pdo->query("SELECT department, COUNT(*) as count FROM users WHERE status != 'Deactivated' GROUP BY department");
$dept_data = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
$dept_labels = [];
$dept_counts = [];
foreach ($dept_data as $row) {
    $dept_labels[] = $row['department'] ?: 'Unassigned';
    $dept_counts[] = $row['count'];
}

// Data for Chart: Tickets by Priority
$ticket_stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM support_tickets WHERE status != 'Closed' GROUP BY priority");
$ticket_data = $ticket_stmt->fetchAll(PDO::FETCH_ASSOC);
$ticket_labels = [];
$ticket_counts = [];
foreach ($ticket_data as $row) {
    $ticket_labels[] = $row['priority'] ?: 'Normal';
    $ticket_counts[] = $row['count'];
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
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 28px; font-weight: 900; color: var(--text-heading); margin-bottom: 8px;">🌐 Global Command HUD</h2>
        <p style="color: var(--text-muted); font-size: 15px;">Real-time metrics and aggregated data across all enterprise modules.</p>
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
            <div class="title">Pending Onboarding</div>
            <div class="value"><?= number_format($pending_onboarding) ?></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 16px; color: var(--text-heading);">Headcount by Department</h3>
            <canvas id="deptChart" height="250"></canvas>
        </div>
        <div class="chart-container">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 16px; color: var(--text-heading);">Open Tickets by Priority</h3>
            <canvas id="ticketChart" height="250"></canvas>
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

<?php require_once 'includes/footer.php'; ?>
