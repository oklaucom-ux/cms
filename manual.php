<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin', 'System Admin'])) {
    echo "<div class='content-section active'><div style='padding:40px;'><h2>Access Denied</h2><p>Only Administrators can view the User Manual.</p></div></div>";
    require_once 'includes/footer.php';
    exit;
}

$isAdmin = (in_array($_SESSION['role'], ['Admin', 'Super Admin']) || $_SESSION['role'] === 'Manager');
?>

<style>
.manual-container { display: flex; gap: 24px; }
.manual-toc { flex: 0 0 250px; position: sticky; top: 20px; align-self: start; background: var(--bg-card); border: 1px solid var(--border-card); border-radius: 12px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.manual-toc h4 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 12px; padding-left: 8px; }
.manual-toc a { display: block; padding: 8px; font-size: 13px; color: var(--text-body); text-decoration: none; border-radius: 6px; transition: 0.2s; }
.manual-toc a:hover { background: #e0e7ff; color: #4338ca; font-weight: 600; }
.manual-content { flex: 1; display: flex; flex-direction: column; gap: 24px; }

.manual-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.manual-card h3 {
    margin-top: 0;
    font-size: 20px;
    color: var(--text-heading);
    margin-bottom: 16px;
    border-bottom: 2px solid var(--border-card);
    padding-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.manual-card h4 {
    font-size: 16px;
    color: var(--text-heading);
    margin: 20px 0 10px 0;
}
.manual-card p, .manual-card ul {
    font-size: 14px;
    color: var(--text-body);
    line-height: 1.6;
    margin-bottom: 14px;
}
.manual-card ul { padding-left: 20px; }
.manual-card li { margin-bottom: 6px; }
.manual-card code {
    background: var(--input-bg);
    border: 1px solid var(--border-card);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 13px;
    color: #e11d48;
}
.manual-label {
    display: inline-block;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 700;
    background: #e0e7ff;
    color: #4338ca;
    vertical-align: middle;
}
.manual-tip {
    background: #f0fdf4;
    
    padding: 12px 16px;
    border-radius: 4px;
    margin: 16px 0;
    font-size: 13px;
    color: #166534;
}
[data-theme='dark'] .manual-tip { background: #064e3b; color: #a7f3d0; border-color: #059669; }
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>📖 Complete System User Manual</h2>
        <p style="color:var(--text-muted); font-size:14px;">A comprehensive guide to all modules and workflows within the Enterprise Management System.</p>
    </div>

    <div class="manual-container">
        <!-- Table of Contents Sidebar -->
        <div class="manual-toc">
            <h4>Quick Navigation</h4>
            <a href="#admin">⚙️ Administration</a>
            <a href="#crm">🎯 Enterprise CRM</a>
            <a href="#hr">👥 Human Resources</a>
            <a href="#ops">🛠️ Operations & Projects</a>
            <a href="#fin">💰 Finance & Billing</a>
            <a href="#team">💬 Team & Culture</a>
            <a href="#external">🤝 External Portals</a>
            <a href="#pwa">📱 Mobile App (PWA)</a>
        </div>

        <!-- Content Area -->
        <div class="manual-content">

            <!-- Administration -->
            <div id="admin" class="manual-card">
                <h3>⚙️ Administration & Core Config</h3>
                <p>The backbone of the system, managing who has access to what, and global configurations.</p>
                
                <h4>User Management & Org Chart</h4>
                <ul>
                    <li><strong>Users:</strong> Add employees, define their Branches/Locations, and assign them Roles. You can also assign who they <code>Report To</code>.</li>
                    <li><strong>Org Chart:</strong> The system automatically builds a visual company hierarchy based on the "Reports To" structure defined in User Management.</li>
                </ul>

                <h4>Roles & Full CRUD Granular Control</h4>
                <ul>
                    <li>Create custom roles (e.g., "Junior Sales", "HR Manager").</li>
                    <li>Assign hyper-specific permissions using the <strong>Granular Checkbox Matrix</strong>. The system supports <strong>Full CRUD (Create, Read, Update, Delete)</strong> capabilities across every single module (Finance, CRM, HR, Ops, etc).</li>
                    <li>Permissions automatically hide/show menus in the sidebar, secure API endpoints, and block unauthorized URL access instantly.</li>
                </ul>

                <h4>Global Module Toggles (Enable/Disable)</h4>
                <ul>
                    <li>In <strong>System Settings</strong>, administrators can globally Enable or Disable entire modules (e.g., Form Builder, Website Builder, CRM, Payroll).</li>
                    <li>Disabling a module completely hides its interface from the sidebar and securely blocks access system-wide, allowing you to tailor the CMS to your exact needs.</li>
                </ul>

                <h4>Locations, Zones, and Policies</h4>
                <ul>
                    <li><strong>Zones/Locations:</strong> Define global branches and their PIN Codes. This powers the Geo-Routing engine in the CRM.</li>
                    <li><strong>Policies:</strong> Upload employee handbooks and company policies that users must acknowledge.</li>
                </ul>
                
                <h4>Webhooks & Security Audit Trail</h4>
                <ul>
                    <li><strong>API Webhooks:</strong> Trigger real-time HTTP POST payloads to external systems (like Zapier, Make.com) when events occur (e.g., Lead Created, Invoice Paid).</li>
                    <li><strong>Audit Trail:</strong> A tamper-proof security log tracking every action (Login, Create, Update, Delete) taken by users across the system.</li>
                </ul>
                
                <h4>Advanced Reports</h4>
                <ul>
                    <li><strong>Zero-Latency Exports:</strong> Instantly generate client-side PDF and CSV reports for Employees, Attendance, CRM Pipelines, Projects, and Invoices without server overhead.</li>
                </ul>
                
                <div class="manual-tip">
                    <strong>Pro-Tip:</strong> Admins can use the <strong>Website Builder</strong> to visually construct the public-facing landing page, dragging and dropping Testimonials, Pricing, FAQs, and custom content blocks without writing code.
                </div>
            </div>

            <!-- Enterprise CRM -->
            <div id="crm" class="manual-card">
                <h3>🎯 Enterprise CRM & Lead Routing</h3>
                <p>A dynamic, Kanban-style Sales Pipeline with intelligent automation.</p>
                
                <h4>Adding & Managing Leads</h4>
                <ul>
                    <li><strong>Manual Entry:</strong> Click "+ Add Lead" to input a prospect. Leads are bound to specific pipeline stages (Prospect, Qualified, Proposal, Won, Lost).</li>
                    <li><strong>Drag & Drop:</strong> Visually move deals across stages. System automatically totals Pipeline value.</li>
                    <li><strong>Activity Timeline:</strong> Click "📋 Activities" on a card to log calls, emails, and internal notes.</li>
                </ul>

                <h4>🔄 Google Sheet Auto-Sync & Routing Engine</h4>
                <p>You can instantly pull patient/lead data from published Google Sheets via the <strong>Sync Google Sheet</strong> button.</p>
                <ul>
                    <li><strong>Dynamic Custom Data:</strong> Any columns in your Sheet (e.g., Doctor Name, Medical History) are automatically stored in the JSON bucket and displayed beautifully on the Lead's Profile page.</li>
                    <li><strong>Geo-Routing Logic:</strong> If your Sheet has a <code>PIN</code> or <code>Location</code> column AND a <code>User Type</code> column, the CRM instantly finds the matching local employee in your DB and assigns the lead to them!</li>
                </ul>

                <h4>Data Portability & Exports</h4>
                <ul>
                    <li><strong>Export CSV:</strong> Instantly download your pipeline. The system automatically unpacks dynamic/custom fields (like those from the Form Builder) into clean Excel columns.</li>
                    <li><strong>Export JSON:</strong> Download raw pipeline data for strict backups or integrations.</li>
                </ul>
            </div>

            <!-- Human Resources -->
            <div id="hr" class="manual-card">
                <h3>👥 Comprehensive Human Resources</h3>
                
                <h4>1. Recruitment & Virtual AI Interviews</h4>
                <ul>
                    <li><strong>ATS Board:</strong> Track candidates through Sourced, Interview, Offered, and Rejected stages.</li>
                    <li><strong>Virtual Interviews:</strong> Send candidates an interview link. They use their webcam/mic to answer AI-generated questions. The system monitors for cheating (tab-switching) and takes a photo ID snapshot. OpenAI transcripts the answer and scores it 1-10 automatically!</li>
                </ul>

                <h4>2. Employee Onboarding</h4>
                <ul>
                    <li>When a candidate is marked "Hired", they are sent an onboarding link to upload their tax forms and ID proofs. HR approves the documents, and they instantly become an active system User.</li>
                </ul>

                <h4>3. Attendance & Leave (PTO)</h4>
                <ul>
                    <li><strong>Geo-Punched Time Tracking:</strong> Employees click "Clock In". The system grabs their GPS coordinates and IP address for anti-fraud.</li>
                    <li><strong>Leaves:</strong> Multi-tier approval system. Employees request sick/vacation time; managers approve or deny.</li>
                </ul>

                <h4>4. Performance Reviews & Pulse Surveys</h4>
                <ul>
                    <li><strong>Reviews:</strong> 360-degree evaluation matrix. Managers score employees quarterly on specific criteria.</li>
                    <li><strong>Pulse Surveys:</strong> Send anonymous, quick company-wide polls to gauge team morale.</li>
                </ul>
                
                <h4>5. Employee Training</h4>
                <ul>
                    <li><strong>Training Hub:</strong> Upload courses and training materials for employees to complete. Track progress, course views, and training compliance.</li>
                </ul>
            </div>

            <!-- Operations & Projects -->
            <div id="ops" class="manual-card">
                <h3>🛠️ Operations & Project Hub</h3>
                
                <h4>Project Management Suite</h4>
                <ul>
                    <li><strong>Projects:</strong> Track global initiatives with client associations and budgets.</li>
                    <li><strong>Tasks & Kanban:</strong> Break projects down into actionable items. View as a list or a drag-and-drop Kanban board.</li>
                    <li><strong>Gantt Charts:</strong> Visual timeline of all tasks to identify bottlenecks.</li>
                    <li><strong>Timesheets:</strong> Log hours specifically against tasks for precise payroll/billing tracking.</li>
                </ul>

                <h4>Support & Form Engines</h4>
                <ul>
                    <li><strong>Omni-Channel Ticketing:</strong> A unified support matrix for Client Support, Internal IT/HR Helpdesk, and Feedback/Complaints. Admins can view and filter all organizational requests from a single 'Omni-Desk', while end-users retain a specialized, localized view. Automated Webhooks trigger on ticket creation and resolution to sync with external tools.</li>
                    <li><strong>Dynamic Form Builder (All Forms Included):</strong> Build customized data-collection forms with logic (Text, Dropdowns, Checkboxes). The system supports <strong>Public CRM Forms</strong> (responses pipe directly to Leads with custom JSON data), <strong>Internal HR Forms</strong> (surveys/requests), and <strong>Onboarding Forms</strong>. All dynamic form capabilities are natively integrated.</li>
                    <li><strong>KPI Engine:</strong> Assign numerical targets to employees. They update their progress, and a progress bar tracks their success metric in real time.</li>
                </ul>
                
                <h4>Knowledge Base & Visual Calendar</h4>
                <ul>
                    <li><strong>Knowledge Base:</strong> A centralized documentation hub for internal procedures, guides, and public FAQs. Supports categorization, tagging, and rich reading views.</li>
                    <li><strong>Visual Calendar:</strong> An enterprise visual scheduler to track global meetings. Instantly dispatch calendar invites to selected employees or company-wide.</li>
                </ul>

                <h4>IT Assets Management</h4>
                <ul>
                    <li>Track physical and digital assets (Laptops, Mobile Devices, Software Licenses) assigned to employees, including serial numbers, warranty dates, and deployment statuses.</li>
                </ul>
            </div>

            <!-- Finance -->
            <div id="fin" class="manual-card">
                <h3>💰 Finance, Payroll & Billing</h3>
                
                <h4>Invoicing</h4>
                <ul>
                    <li>Create professional invoices, download as PDF, and email directly to clients. Supports partial payments and multi-status tracking.</li>
                </ul>

                <h4>Expenses & Procurement</h4>
                <ul>
                    <li>Employees upload receipts for reimbursement. Multi-level managerial approval flow.</li>
                    <li><strong>Procurement:</strong> Manage company vendor budgets and asset purchases.</li>
                </ul>

                <h4>Payroll Engine</h4>
                <ul>
                    <li>Automatically calculate employee pay based on their fixed salary or hourly rates (pulled from Timesheets). Generates comprehensive payslips.</li>
                </ul>
            </div>

            <!-- Team & Culture -->
            <div id="team" class="manual-card">
                <h3>💬 Team & Culture</h3>
                <ul>
                    <li><strong>Team Chat:</strong> Real-time WebSocket/Polling based messaging between employees. Send direct messages instantly.</li>
                    <li><strong>Peer Rewards:</strong> Employees can send "Kudos" and badge awards to their peers to boost morale, complete with custom messages and animated GIFs.</li>
                    <li><strong>Room Booking:</strong> Visual calendar matrix to reserve conference rooms and assets globally.</li>
                </ul>
            </div>

            <!-- Portals -->
            <div id="external" class="manual-card">
                <h3>🤝 External Portals & Legal</h3>
                <ul>
                    <li><strong>Client Portal:</strong> Clients receive a dedicated login to view their specific Projects, download Invoices, and see Gantt chart progress.</li>
                    <li><strong>Vendor Portal:</strong> Vendors can log in to view active Purchase Orders (Procurement) and manage their active CRM status.</li>
                    <li><strong>Legal Contracts:</strong> Draft digital contracts and generate secure signing links. Counterparties draw their signature on mobile/desktop, and a PDF is instantly locked and saved.</li>
                </ul>
            </div>

            <!-- PWA -->
            <div id="pwa" class="manual-card">
                <h3>📱 Progressive Web App (Mobile)</h3>
                <p>This entire enterprise system is built as a PWA. It runs natively on iOS and Android without needing an App Store.</p>
                <ul>
                    <li><strong>Installation:</strong> Open the system in Chrome (Android) or Safari (iOS). Click the "Install App" button in the top right header, or use the browser's "Add to Home Screen" feature.</li>
                    <li><strong>Offline Capabilities:</strong> The built-in Service Worker caches critical assets, allowing the app to load instantly even on slow connections.</li>
                    <li>It runs in full-screen standalone mode, behaving exactly like a native application!</li>
                </ul>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
