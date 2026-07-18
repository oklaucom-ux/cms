<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Client') {
    die("<div class='content-section active'><h2>Unauthorized Access</h2><p>This portal is exclusively for Clients.</p></div>");
}

$clientName = $_SESSION['name'];
try {
} catch(Exception $e){}
// Fetch their projects
$stmtProjects = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? OR client = ? ORDER BY created_at DESC");
$stmtProjects->execute([$_SESSION['login_id'], $clientName]);
$projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending milestone sign-offs
$stmtMilestones = $pdo->prepare("SELECT t.id, t.name, t.description, p.name as project_name 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.id 
    WHERE (p.client_id = ? OR p.client = ?) AND t.is_milestone = 1 AND t.status = 'Awaiting Approval'");
$stmtMilestones->execute([$_SESSION['login_id'], $clientName]);
$milestones = $stmtMilestones->fetchAll(PDO::FETCH_ASSOC);

// Fetch their invoices
$stmtInvoices = $pdo->prepare("SELECT * FROM invoices WHERE client_name = ? ORDER BY issue_date DESC");
$stmtInvoices->execute([$clientName]);
$invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

$totalProjects = count($projects);
$totalInvoicesActive = count(array_filter($invoices, fn($i) =>$i['status'] === 'Unpaid'));
$totalUnpaidAmount = array_sum(array_column(array_filter($invoices, fn($i) =>$i['status'] === 'Unpaid'), 'amount'));

// Fetch support tickets
$stmtTickets = $pdo->prepare("SELECT * FROM unified_tickets WHERE requester_id = ? AND source = 'Client_Support' ORDER BY created_at DESC");
$stmtTickets->execute([$_SESSION['login_id']]);
$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

// Fetch active KB articles
$kbArticles = $pdo->query("SELECT * FROM knowledge_base WHERE is_public = 1 ORDER BY category ASC, title ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalTickets = count($tickets);
$openTickets = count(array_filter($tickets, fn($t) =>$t['status'] !== 'Closed'));
?>

<style>
    .glass-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 0 0 24px 24px;
        padding: 40px;
        margin: -20px -20px 30px -20px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }
    .stat-content h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 800;
        color: var(--text-color);
        line-height: 1.2;
    }
    .stat-content p {
        margin: 4px 0 0;
        font-size: 14px;
        color: var(--text-muted);
        font-weight: 500;
    }
    .modern-table-container {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }
    .modern-table {
        width: 100%;
        border-collapse: collapse;
    }
    .modern-table th {
        background: rgba(0,0,0,0.02);
        padding: 16px 20px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-color);
    }
    .modern-table td {
        padding: 16px 20px;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-color);
        font-size: 14px;
    }
    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }
    .modern-table tbody tr:hover {
        background: rgba(0,0,0,0.01);
    }
    .badge-modern {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-gradient {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
        color: white;
    }
</style>

<div class="content-section active" style="padding-top:0;">

    <div class="glass-header">
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 class="text-white" style="margin: 0 0 10px 0; font-size: 32px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff !important;">
                    <i class="fas fa-building" style="margin-right:12px; color:#38bdf8;"></i> Client Portal: <?= htmlspecialchars($clientName) ?>
                </h1>
                <p class="text-light" style="margin: 0; font-size: 16px; color: #cbd5e1 !important;">Overview of your enterprise projects, billing, and support.</p>
            </div>
            <div>
                <button onclick="document.getElementById('ticketModal').style.display='block'" class="btn-gradient">
                    <i class="fas fa-headset"></i> Create Support Ticket
                </button>
            </div>
        </div>
        <!-- Decorative background elements -->
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(56, 189, 248, 0.1); border-radius: 50%; filter: blur(30px); z-index: 1;"></div>
        <div style="position: absolute; bottom: -50px; left: 10%; width: 150px; height: 150px; background: rgba(139, 92, 246, 0.1); border-radius: 50%; filter: blur(30px); z-index: 1;"></div>
    </div>

    <!-- Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 40px;">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-content">
                <h3><?= $totalProjects ?></h3>
                <p>Active Projects</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-content">
                <h3><?= $totalInvoicesActive ?></h3>
                <p>Open Invoices</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <h3><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($totalUnpaidAmount, 2) ?></h3>
                <p>Unpaid Balance</p>
            </div>
        </div>
    </div>

    <!-- MILESTONE SIGN-OFF REQUESTS -->
    <?php if(count($milestones) > 0): ?>
    <div style="background: linear-gradient(to right, #fffbeb, #fef3c7); border-left: 4px solid #f59e0b; border-radius: 12px; padding: 24px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <h3 style="margin: 0 0 8px 0; color: #b45309; display: flex; align-items: center; gap: 10px; font-size: 20px;">
            <i class="fas fa-exclamation-circle"></i> Action Required: Milestone Sign-offs
        </h3>
        <p style="color: #92400e; font-size: 15px; margin-bottom: 24px;">The following project milestones have been completed by the team and require your formal review.</p>
        
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach($milestones as $m): ?>
            <div style="background: white; border: 1px solid #fde68a; padding: 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                <div>
                    <div style="font-size: 12px; font-weight: 700; color: #d97706; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                        <i class="fas fa-folder me-1"></i> <?= htmlspecialchars($m['project_name']) ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 800; color: #1f2937; margin-bottom: 6px;">
                        <?= htmlspecialchars($m['name']) ?>
                    </div>
                    <div style="font-size: 14px; color: #4b5563; max-width: 600px; line-height: 1.5;">
                        <?= htmlspecialchars($m['description']) ?>
                    </div>
                </div>
                <div style="display: flex; gap: 12px;">
                    <form method="POST" action="controllers/client_milestone_action.php" style="margin:0; display: flex; gap: 12px;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="task_id" value="<?= $m['id'] ?>">
                        <button type="submit" name="action" value="Reject" style="background: white; border: 1px solid #ef4444; color: #ef4444; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='white'" onclick="return confirm('Send back for revisions?');">
                            <i class="fas fa-times me-1"></i> Request Revisions
                        </button>
                        <button type="submit" name="action" value="Approve" style="background: #10b981; border: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; box-shadow: 0 2px 4px rgba(16,185,129,0.2);" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'" onclick="return confirm('Certify this milestone as fully completed?');">
                            <i class="fas fa-check me-1"></i> Approve Milestone
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h3 style="margin: 0; color: var(--text-heading); font-size: 22px; font-weight: 700;"><i class="fas fa-project-diagram me-2 text-primary"></i> My Projects</h3>
    </div>
    
    <div class="modern-table-container">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>Deadline</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($projects as $p): ?>
                <tr>
                    <td style="font-weight: 600;"><?= htmlspecialchars($p['name']) ?></td>
                    <td>
                        <?php
                            $bg = $p['status'] == 'Active' ? '#d1fae5' : ($p['status'] == 'Completed' ? '#e0e7ff' : '#fef3c7');
                            $tc = $p['status'] == 'Active' ? '#065f46' : ($p['status'] == 'Completed' ? '#3730a3' : '#92400e');
                            $icon = $p['status'] == 'Active' ? 'fa-spinner fa-pulse' : ($p['status'] == 'Completed' ? 'fa-check-circle' : 'fa-clock');
                        ?>
                        <span class="badge-modern" style="background: <?= $bg ?>; color: <?= $tc ?>;">
                            <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($p['status']) ?>
                        </span>
                    </td>
                    <td style="font-weight: 500;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($p['budget'], 2) ?></td>
                    <td style="color: var(--text-muted);"><i class="far fa-calendar-alt me-1"></i> <?= htmlspecialchars($p['deadline'] ?: 'No date') ?></td>
                    <td>
                        <a href="client_gantt.php?id=<?= $p['id'] ?>" style="background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid #cbd5e1; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#1e293b';" onmouseout="this.style.background='#f1f5f9'; this.style.color='#475569';">
                            <i class="fas fa-chart-gantt me-1"></i> View Timeline
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($projects)): ?>
                <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);"><i class="fas fa-folder-open fs-2 mb-2 d-block opacity-50"></i> No active projects associated with your account.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 40px; margin-bottom: 20px;">
        <h3 style="margin: 0; color: var(--text-heading); font-size: 22px; font-weight: 700;"><i class="fas fa-file-invoice-dollar me-2 text-success"></i> My Invoices</h3>
    </div>
    
    <div class="modern-table-container">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Amount</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($invoices as $inv): ?>
                <tr>
                    <td style="font-family: monospace; font-weight: bold; color: var(--text-muted);">#<?= htmlspecialchars($inv['invoice_id']) ?></td>
                    <td style="font-weight: 700;"><?= ($GLOBAL_SETTINGS['currency'] ?? '₹') ?><?= number_format($inv['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($inv['issue_date']) ?></td>
                    <td>
                        <?php 
                            $isOverdue = (strtotime($inv['due_date']) < time() && $inv['status'] !== 'Paid');
                            $dueColor = $isOverdue ? '#dc2626' : 'inherit';
                        ?>
                        <span style="color: <?= $dueColor ?>; <?= $isOverdue ? 'font-weight:bold;' : '' ?>"><i class="far fa-calendar-times me-1"></i> <?= htmlspecialchars($inv['due_date']) ?></span>
                    </td>
                    <td>
                        <?php
                            $bg = $inv['status'] == 'Paid' ? '#d1fae5' : ($inv['status'] == 'Overdue' ? '#fee2e2' : '#fef3c7');
                            $tc = $inv['status'] == 'Paid' ? '#065f46' : ($inv['status'] == 'Overdue' ? '#991b1b' : '#92400e');
                            $icon = $inv['status'] == 'Paid' ? 'fa-check-circle' : ($inv['status'] == 'Overdue' ? 'fa-exclamation-circle' : 'fa-clock');
                        ?>
                        <span class="badge-modern" style="background: <?= $bg ?>; color: <?= $tc ?>;">
                            <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($inv['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if($inv['status'] !== 'Paid'): ?>
                            <a href="client_checkout.php?invoice_id=<?= urlencode($inv['invoice_id']) ?>" class="btn-gradient" style="padding: 6px 14px; font-size: 13px;">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                        <?php else: ?>
                            <span style="color: #10b981; font-size: 13px; font-weight: 700; background: rgba(16,185,129,0.1); padding: 6px 12px; border-radius: 6px;">
                                <i class="fas fa-receipt me-1"></i> Receipt
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($invoices)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--text-muted);"><i class="fas fa-file-invoice fs-2 mb-2 d-block opacity-50"></i> No invoice history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Grid for Service Desk & FAQ -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 40px; margin-bottom: 40px; align-items: start;">
        
        <!-- Ticket List -->
        <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h3 style="margin: 0; color: var(--text-heading); font-size: 22px; font-weight: 700;"><i class="fas fa-headset me-2" style="color: #4f46e5;"></i> Service Desk</h3>
            </div>
            <div class="modern-table-container" style="margin-bottom: 0;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tickets as $t): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: bold; color: var(--text-muted);">#<?= htmlspecialchars($t['ticket_number']) ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-color);"><?= htmlspecialchars($t['subject']) ?></div>
                                <div style="font-size: 12px; margin-top: 4px;">
                                    <span style="font-weight: 700; color: <?= $t['priority']=='Critical' ? '#dc2626' : ($t['priority']=='High' ? '#ea580c' : '#64748b') ?>;">
                                        <i class="fas fa-flag me-1"></i> <?= htmlspecialchars($t['priority']) ?> Priority
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $bg = $t['status'] == 'Closed' ? '#f1f5f9' : '#dbeafe';
                                    $tc = $t['status'] == 'Closed' ? '#475569' : '#1e40af';
                                    $icon = $t['status'] == 'Closed' ? 'fa-lock' : 'fa-envelope-open-text';
                                ?>
                                <span class="badge-modern" style="background: <?= $bg ?>; color: <?= $tc ?>;">
                                    <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($t['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button style="background: white; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; color: #475569; transition: all 0.2s;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#94a3b8';" onmouseout="this.style.background='white'; this.style.borderColor='#cbd5e1';" onclick="openTicket(<?= $t['id'] ?>)">
                                    <i class="fas fa-eye me-1"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($tickets)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--text-muted);"><i class="fas fa-ticket-alt fs-2 mb-2 d-block opacity-50"></i> You have no active support tickets.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Knowledge Base Quick View -->
        <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h3 style="margin: 0; color: var(--text-heading); font-size: 22px; font-weight: 700;"><i class="fas fa-book me-2 text-warning"></i> Knowledge Base</h3>
            </div>
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php 
                    $lastCat = '';
                    foreach ($kbArticles as $kb): 
                        if($lastCat !== $kb['category']): 
                            echo "<div style='font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-top:15px; margin-bottom:10px;'>".htmlspecialchars($kb['category'])."</div>";
                            $lastCat = $kb['category'];
                        endif;
                    ?>
                    <details style="margin-bottom: 12px; background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                        <summary style="font-size: 14px; font-weight: 600; color: var(--text-color); cursor: pointer; list-style: none; outline: none; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
                            <?= htmlspecialchars($kb['title']) ?>
                            <i class="fas fa-chevron-down text-muted" style="font-size: 10px;"></i>
                        </summary>
                        <div style="font-size: 14px; color: var(--text-muted); padding: 16px; border-top: 1px solid var(--border-color); background: var(--bg-body); line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($kb['content_body']) ?></div>
                    </details>
                    <?php endforeach; ?>
                    <?php if(empty($kbArticles)): ?>
                    <div style="text-align: center; padding: 30px 10px; color: var(--text-muted);">
                        <i class="fas fa-book-open fs-3 mb-2 d-block opacity-50"></i>
                        No articles published yet.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Support Ticket Modal -->
<div id="ticketModal" class="modal">
    <div class="modal-content" style="max-width: 500px; border-radius: 16px; overflow: hidden; padding: 0;">
        <div style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; padding: 24px; position: relative;">
            <span class="close-modal" style="color: white; opacity: 0.8; top: 20px; right: 20px;" onclick="document.getElementById('ticketModal').style.display='none'">&times;</span>
            <h2 style="margin: 0 0 8px 0; font-size: 24px;"><i class="fas fa-ticket-alt me-2"></i> Submit Support Ticket</h2>
            <p style="margin: 0; font-size: 14px; opacity: 0.9;">Our SLA guarantees a response to Critical issues within 2 hours.</p>
        </div>
        <div style="padding: 24px;">
            <form method="POST" action="controllers/submit_ticket.php" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="form-group mb-3">
                    <label style="font-weight: 600; color: var(--text-heading);">Priority Level</label>
                    <select name="priority" required class="form-control" style="border-radius: 8px;">
                        <option value="Low">Low - General Question</option>
                        <option value="Medium" selected>Medium - Issue or Bug</option>
                        <option value="High">High - Workflow Blocked</option>
                        <option value="Critical">Critical - Production Down</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label style="font-weight: 600; color: var(--text-heading);">Subject</label>
                    <input type="text" name="subject" required class="form-control" style="border-radius: 8px;" placeholder="Brief summary of the issue">
                </div>
                <div class="form-group mb-4">
                    <label style="font-weight: 600; color: var(--text-heading);">Description of Issue</label>
                    <textarea name="message" rows="5" required class="form-control" style="border-radius: 8px; resize: vertical;" placeholder="Please provide specific steps to reproduce the issue..."></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;" onclick="document.getElementById('ticketModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-gradient" style="border-radius: 8px;"><i class="fas fa-paper-plane me-2"></i> Submit to Helpdesk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ticket View/Reply Modal (Clients) -->
<div id="ticketViewModal" class="modal">
    <div class="modal-content" style="max-width: 800px; padding:0; display:flex; flex-direction:column; height:80vh; border-radius: 16px; overflow: hidden;">
        <div style="padding:24px; background: #1e293b; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:20px; font-weight: 600;" id="tvTitle">Loading...</h2>
            <span class="close-modal" style="color:white; opacity: 0.8; position: static; cursor:pointer;" onclick="document.getElementById('ticketViewModal').style.display='none'">&times;</span>
        </div>
        
        <div id="tvReplies" style="flex:1; padding:24px; overflow-y:auto; display:flex; flex-direction:column; gap:20px; background: #f8fafc;">
            <div style="text-align:center; padding:50px; color: #64748b;">
                <i class="fas fa-spinner fa-spin fs-2 mb-3"></i><br>Loading thread...
            </div>
        </div>
        
        <div style="padding:24px; background:white; border-top:1px solid #e2e8f0;">
            <form method="POST" action="controllers/ticket_reply.php" style="margin:0; display:flex; gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="ticket_id" id="tvTicketId">
                <input type="hidden" name="status" value="Open">
                <input type="text" name="message" required placeholder="Type your reply here..." style="flex:1; border-radius:12px; border:1px solid #cbd5e1; padding:12px 16px; font-size: 15px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#cbd5e1'">
                <button type="submit" class="btn-gradient" style="border-radius: 12px; padding: 12px 24px;"><i class="fas fa-paper-plane me-2"></i> Send</button>
            </form>
        </div>
    </div>
</div>

<script>
function openTicket(id) {
    document.getElementById('ticketViewModal').style.display = 'block';
    document.getElementById('tvTicketId').value = id;
    
    fetch('controllers/get_ticket_thread.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('tvTitle').textContent = '#' + data.ticket.ticket_number + ' - ' + data.ticket.subject;
            
            let html = '';
            data.replies.forEach(r => {
                let isClient = (r.is_client == 1);
                let align = isClient ? 'flex-end' : 'flex-start';
                let bg = isClient ? '#4f46e5' : 'white';
                let color = isClient ? 'white' : '#1e293b';
                let border = isClient ? 'none' : '1px solid #e2e8f0';
                let shadow = isClient ? '0 4px 6px rgba(79, 70, 229, 0.2)' : '0 2px 4px rgba(0,0,0,0.02)';
                let Name = isClient ? 'You' : (r.user_name || 'Support Agent');
                let NameColor = isClient ? '#4f46e5' : '#64748b';
                let NameWeight = isClient ? '700' : '600';

                html += `<div style="align-self:${align}; max-width:75%; display:flex; flex-direction:column; gap:6px;">
                    <div style="font-size:12px; color:${NameColor}; font-weight:${NameWeight}; margin:0 4px; text-align:${isClient?'right':'left'}">
                        ${Name} <span style="color:#94a3b8; font-weight:400; margin-left:8px;">${r.created_at}</span>
                    </div>
                    <div style="background:${bg}; color:${color}; padding:14px 18px; border-radius:16px; border:${border}; box-shadow:${shadow}; font-size:15px; line-height:1.5; white-space:pre-wrap;">${r.message}</div>
                </div>`;
            });
            document.getElementById('tvReplies').innerHTML = html;
            const container = document.getElementById('tvReplies');
            container.scrollTop = container.scrollHeight;
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>
