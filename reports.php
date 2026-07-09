<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Broadest permission check - must at least be an employee
if (!isset($_SESSION['user_id'])) die("Unauthorized");
?>

<!-- Load jsPDF and AutoTable for robust Client-Side PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<style>
.report-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.report-card { background: var(--bg-card); border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--border-card); text-align: center; }
.report-icon { font-size: 32px; margin-bottom: 12px; }
.report-title { font-size: 18px; font-weight: 700; color: var(--text-heading); margin-bottom: 8px; }
.report-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; min-height: 40px; }
.report-actions { display: flex; gap: 10px; justify-content: center; }
.btn-csv { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-pdf { background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-csv:hover { background: #059669; }
.btn-pdf:hover { background: #dc2626; }
.loading-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: bold; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>📊 Advanced Reporting Engine</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-top:5px;">Zero-latency, browser-rendered exports. Uses 0% server CPU.</p>
    </div>

    <div class="report-grid">
        
        <?php if(hasPermission($pdo, 'view_users')): ?>
        <div class="report-card">
            <div class="report-icon">👥</div>
            <div class="report-title">Employee Roster</div>
            <div class="report-desc">Full index of corporate staff including designations and statuses.</div>
            <div class="report-actions">
                <button class="btn-csv" onclick="generateReport('users', 'csv', 'Employee_Roster')">📄 CSV</button>
                <button class="btn-pdf" onclick="generateReport('users', 'pdf', 'Employee_Roster')">📕 PDF</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if(hasPermission($pdo, 'view_attendance')): ?>
        <div class="report-card">
            <div class="report-icon">🕐</div>
            <div class="report-title">Attendance Logs</div>
            <div class="report-desc">Detailed check-in and check-out logs for all active employees.</div>
            <div class="report-actions">
                <button class="btn-csv" onclick="generateReport('attendance', 'csv', 'Attendance_Log')">📄 CSV</button>
                <button class="btn-pdf" onclick="generateReport('attendance', 'pdf', 'Attendance_Log')">📕 PDF</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if(hasPermission($pdo, 'view_crm')): ?>
        <div class="report-card">
            <div class="report-icon">🎯</div>
            <div class="report-title">Sales CRM Funnel</div>
            <div class="report-desc">All leads, pipeline stages, financial values, and conversion statuses.</div>
            <div class="report-actions">
                <button class="btn-csv" onclick="generateReport('crm', 'csv', 'CRM_Pipeline')">📄 CSV</button>
                <button class="btn-pdf" onclick="generateReport('crm', 'pdf', 'CRM_Pipeline')">📕 PDF</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if(hasPermission($pdo, 'view_projects')): ?>
        <div class="report-card">
            <div class="report-icon">🚀</div>
            <div class="report-title">Project Masterlist</div>
            <div class="report-desc">Portfolio of all current and past projects, clients, and budgets.</div>
            <div class="report-actions">
                <button class="btn-csv" onclick="generateReport('projects', 'csv', 'Project_Masterlist')">📄 CSV</button>
                <button class="btn-pdf" onclick="generateReport('projects', 'pdf', 'Project_Masterlist')">📕 PDF</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if(hasPermission($pdo, 'view_invoices')): ?>
        <div class="report-card">
            <div class="report-icon">💰</div>
            <div class="report-title">Financial Invoices</div>
            <div class="report-desc">Billing history, paid vs unpaid ledgers, and invoice totals.</div>
            <div class="report-actions">
                <button class="btn-csv" onclick="generateReport('invoices', 'csv', 'Invoices_Ledger')">📄 CSV</button>
                <button class="btn-pdf" onclick="generateReport('invoices', 'pdf', 'Invoices_Ledger')">📕 PDF</button>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
    Hold on, crunching data and generating your report... ⏳
</div>

<script>
async function generateReport(type, format, filename) {
    document.getElementById('loadingOverlay').style.display = 'flex';
    
    try {
        // Fetch raw JSON payload from our new high-speed API
        const response = await fetch(`controllers/report_api.php?type=${type}`);
        const result = await response.json();
        
        if (result.error) {
            alert("Error: " + result.error);
            document.getElementById('loadingOverlay').style.display = 'none';
            return;
        }

        const columns = result.columns;
        const data = result.data;
        const finalFilename = `${filename}_${new Date().toISOString().split('T')[0]}`;

        if (format === 'csv') {
            generateCSV(columns, data, finalFilename);
        } else if (format === 'pdf') {
            generatePDF(columns, data, finalFilename);
        }

    } catch (e) {
        alert("Failed to generate report: " + e.message);
    }
    
    document.getElementById('loadingOverlay').style.display = 'none';
}

function generateCSV(columns, data, filename) {
    // Escape rules for Excel compatibility
    let csv = columns.map(c => `"${c}"`).join(',') + '\n';
    
    data.forEach(row => {
        csv += row.map(v => {
            const val = v === null ? '' : v.toString();
            return `"${val.replace(/"/g, '""')}"`;
        }).join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.click();
}

function generatePDF(columns, data, filename) {
    // Initialize jsPDF
    window.jspdf = window.jspdf || {};
    const { jsPDF } = window.jspdf;
    
    // Landscape orientation works better for multi-column robust data
    const doc = new jsPDF('landscape');
    
    doc.setFontSize(18);
    doc.text(filename.replace(/_/g, ' '), 14, 20);
    
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(`Generated on: ${new Date().toLocaleString()} by System User`, 14, 28);

    doc.autoTable({
        startY: 35,
        head: [columns],
        body: data,
        theme: 'grid',
        headStyles: { fillColor: [79, 70, 229] }, // Brand primary color
        styles: { fontSize: 8 },
        alternateRowStyles: { fillColor: [249, 250, 251] }
    });

    doc.save(filename + '.pdf');
}
</script>

<?php require_once 'includes/footer.php'; ?>
