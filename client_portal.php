<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Client') {
    die("<div class='content-section active'><h2>Unauthorized Access</h2><p>This portal is exclusively for Clients.</p></div>");
}

$clientName = $_SESSION['name'];

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
<div class="content-section active">
    <div class="section-header">
        <h2>Client Portal: <?= htmlspecialchars($clientName) ?></h2>
        <p style="color:var(--text-muted);">Overview of your projects and billing accounts.</p>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card" style="">
            <h3><?= $totalProjects ?></h3>
            <p>Active Projects</p>
        </div>
        <div class="dashboard-card" style="">
            <h3><?= $totalInvoicesActive ?></h3>
            <p>Open Invoices</p>
        </div>
        <div class="dashboard-card" style="">
            <h3><?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($totalUnpaidAmount, 2) ?></h3>
            <p>Unpaid Balance</p>
        </div>
    </div>

    <!-- MILESTONE SIGN-OFF REQUESTS -->
    <?php if(count($milestones) > 0): ?>
    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:20px; margin-bottom:30px; box-shadow:0 4px 6px rgba(0,0,0,0.05);">
        <h3 style="margin-top:0; color:#b45309; display:flex; align-items:center; gap:8px;">
            <span style="font-size:24px;">⚠️</span> Action Required: Milestone Sign-offs
        </h3>
        <p style="color:#92400e; font-size:14px; margin-bottom:20px;">The following project milestones have been completed by the team and require your formal review.</p>
        
        <div style="display:flex; flex-direction:column; gap:16px;">
            <?php foreach($milestones as $m): ?>
            <div style="background:white; border:1px solid #fcd34d; padding:16px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:12px; font-weight:bold; color:#b45309; text-transform:uppercase; margin-bottom:4px;"><?= htmlspecialchars($m['project_name']) ?></div>
                    <div style="font-size:16px; font-weight:700; color:#1f2937; margin-bottom:4px;"><?= htmlspecialchars($m['name']) ?></div>
                    <div style="font-size:13px; color:#4b5563; max-width:600px;"><?= htmlspecialchars($m['description']) ?></div>
                </div>
                <div style="display:flex; gap:10px;">
                    <form method="POST" action="controllers/client_milestone_action.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="task_id" value="<?= $m['id'] ?>">
                        <button type="submit" name="action" value="Reject" style="background:#ef4444; color:white; border:none; padding:10px 16px; border-radius:6px; font-weight:bold; cursor:pointer;" onclick="return confirm('Send back for revisions?');">❌ Request Revisions</button>
                        <button type="submit" name="action" value="Approve" style="background:#10b981; color:white; border:none; padding:10px 16px; border-radius:6px; font-weight:bold; cursor:pointer;" onclick="return confirm('Certify this milestone as fully completed?');">✅ Approve Milestone</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <h3 style="margin-top: 30px; margin-bottom: 15px; color:var(--text-heading);">My Projects</h3>
    <div class="data-table" style="margin-bottom: 30px;">
        <table>
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
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 6px; font-weight:600; font-size:12px;
                            background: <?= $p['status']=='Active' ? '#d1fae5; color:#065f46;' : ($p['status']=='Completed' ? '#e0e7ff; color:#3730a3;' : '#fef3c7; color:#92400e;') ?>">
                            <?= htmlspecialchars($p['status']) ?>
                        </span>
                    </td>
                    <td><?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($p['budget'], 2) ?></td>
                    <td><?= htmlspecialchars($p['deadline'] ?: 'No date') ?></td>
                    <td>
                        <a href="client_gantt.php?id=<?= $p['id'] ?>" style="background:#10b981; color:white; padding:4px 10px; border-radius:4px; text-decoration:none; font-size:12px; font-weight:bold;">📊 View Gantt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($projects)): ?>
                <tr><td colspan="5" style="text-align:center;">No active projects associated with your account.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3 style="margin-top: 30px; margin-bottom: 15px; color:var(--text-heading);">My Invoices</h3>
    <div class="data-table">
        <table>
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
                    <td><?= htmlspecialchars($inv['invoice_id']) ?></td>
                    <td><?= ($GLOBAL_SETTINGS['currency'] ?? '\xe2\x82\xb9') ?><?= number_format($inv['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($inv['issue_date']) ?></td>
                    <td><?= htmlspecialchars($inv['due_date']) ?></td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 6px; font-weight:600; font-size:12px;
                            background: <?= $inv['status']=='Paid' ? '#d1fae5; color:#065f46;' : ($inv['status']=='Overdue' ? '#fee2e2; color:#991b1b;' : '#fef3c7; color:#92400e;') ?>">
                            <?= htmlspecialchars($inv['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if($inv['status'] !== 'Paid'): ?>
                            <a href="client_checkout.php?invoice_id=<?= urlencode($inv['invoice_id']) ?>" style="background:#4f46e5; color:white; padding:4px 10px; border-radius:4px; text-decoration:none; font-size:12px; font-weight:bold;">💳 Pay Now</a>
                        <?php else: ?>
                            <span style="color:#10b981; font-size:12px; font-weight:bold;">Paid</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($invoices)): ?>
                <tr><td colspan="6" style="text-align:center;">No invoice history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 40px; margin-bottom:15px;">
        <h3 style="color:var(--text-heading); margin:0;">Enterprise Service Desk</h3>
        <button onclick="document.getElementById('ticketModal').style.display='block'" style="background:#4f46e5; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer;">+ Create Support Ticket</button>
    </div>
    
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-bottom: 30px;">
        <!-- Ticket List -->
        <div class="data-table" style="margin:0;">
            <table>
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tickets as $t): ?>
                    <tr>
                        <td style="font-family:monospace; font-weight:bold;"><?= htmlspecialchars($t['ticket_number']) ?></td>
                        <td><?= htmlspecialchars($t['subject']) ?></td>
                        <td>
                            <span style="font-size:12px; font-weight:bold; padding:2px 6px; border-radius:4px; 
                                color: <?= $t['priority']=='Critical' ? '#dc2626' : ($t['priority']=='High' ? '#ea580c' : '#4b5563') ?>;">
                                <?= htmlspecialchars($t['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 6px; font-weight:600; font-size:12px;
                                background: <?= $t['status']=='Closed' ? '#f3f4f6; color:#6b7280;' : '#dbeafe; color:#1e40af;' ?>">
                                <?= htmlspecialchars($t['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button style="background:var(--bg-card); border:1px solid #cbd5e1; padding:4px 10px; border-radius:4px; font-size:12px; cursor:pointer;" onclick="openTicket(<?= $t['id'] ?>)">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($tickets)): ?>
                    <tr><td colspan="4" style="text-align:center;">You have no active support tickets.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Knowledge Base Quick View -->
        <div style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:20px;">
            <h4 style="margin-top:0; color:#111827; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">📚 Knowledge Base FAQ</h4>
            <div style="max-height:300px; overflow-y:auto; padding-right:10px;">
                <?php 
                $lastCat = '';
                foreach ($kbArticles as $kb): 
                    if($lastCat !== $kb['category']): 
                        echo "<div style='font-size:11px; font-weight:bold; color:#6b7280; text-transform:uppercase; margin-top:15px; margin-bottom:5px;'>".htmlspecialchars($kb['category'])."</div>";
                        $lastCat = $kb['category'];
                    endif;
                ?>
                <details style="margin-bottom:8px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:4px; padding:5px;">
                    <summary style="font-size:13px; font-weight:600; color:#4f46e5; cursor:pointer; list-style:none; outline:none;"><?= htmlspecialchars($kb['title']) ?></summary>
                    <div style="font-size:13px; color:#4b5563; padding:10px; border-top:1px solid #e5e7eb; margin-top:5px; white-space:pre-wrap;"><?= htmlspecialchars($kb['content_body']) ?></div>
                </details>
                <?php endforeach; ?>
                <?php if(empty($kbArticles)): ?>
                <p style="font-size:13px; color:#9ca3af;">No articles published yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Support Ticket Modal -->
<div id="ticketModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="document.getElementById('ticketModal').style.display='none'">&times;</span>
        <h2>Submit Support Ticket</h2>
        <p style="color:#6b7280; font-size:13px; margin-top:-10px; margin-bottom:15px;">Our SLA guarantees a response to Critical issues within 2 hours.</p>
        <form method="POST" action="controllers/submit_ticket.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Priority Level</label>
                <select name="priority" required>
                    <option value="Low">Low - General Question</option>
                    <option value="Medium" selected>Medium - Issue or Bug</option>
                    <option value="High">High - Workflow Blocked</option>
                    <option value="Critical">Critical - Production Down</option>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" required>
            </div>
            <div class="form-group">
                <label>Description of Issue</label>
                <textarea name="message" rows="5" required placeholder="Please provide specific steps to reproduce the issue..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="document.getElementById('ticketModal').style.display='none'">Cancel</button>
                <button type="submit" class="submit">Submit to Helpdesk</button>
            </div>
        </form>
    </div>
</div>

<!-- Ticket View/Reply Modal (Clients) -->
<div id="ticketViewModal" class="modal">
    <div class="modal-content" style="max-width: 800px; padding:0; display:flex; flex-direction:column; height:80vh;">
        <div style="padding:20px; background:#1e293b; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:18px;" id="tvTitle">Loading...</h2>
            <span class="close-modal" style="color:white; cursor:pointer;" onclick="document.getElementById('ticketViewModal').style.display='none'">&times;</span>
        </div>
        
        <div id="tvReplies" style="flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:16px;">
            <div style="text-align:center; padding:50px;">Loading thread...</div>
        </div>
        
        <div style="padding:20px; background:white; border-top:1px solid #e2e8f0;">
            <form method="POST" action="controllers/ticket_reply.php" style="margin:0; display:flex; gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="ticket_id" id="tvTicketId">
                <input type="hidden" name="status" value="Open">
                <input type="text" name="message" required placeholder="Type a reply..." style="flex:1; border-radius:6px; border:1px solid #cbd5e1; padding:8px;">
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer;">Send Reply</button>
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
            document.getElementById('tvTitle').textContent = data.ticket.ticket_number + ' - ' + data.ticket.subject;
            
            let html = '';
            data.replies.forEach(r => {
                let isClient = (r.is_client == 1);
                let align = isClient ? 'flex-end' : 'flex-start';
                let bg = isClient ? '#4f46e5' : '#f1f5f9';
                let color = isClient ? 'white' : '#111827';
                let Name = isClient ? 'You' : (r.user_name || 'Support Agent');

                html += `<div style="align-self:${align}; max-width:80%; display:flex; flex-direction:column; gap:4px;">
                    <div style="font-size:11px; color:#6b7280; margin:0 4px; text-align:${isClient?'right':'left'}">${Name} • ${r.created_at}</div>
                    <div style="background:${bg}; color:${color}; padding:12px 16px; border-radius:12px; font-size:14px; white-space:pre-wrap;">${r.message}</div>
                </div>`;
            });
            document.getElementById('tvReplies').innerHTML = html;
            const container = document.getElementById('tvReplies');
            container.scrollTop = container.scrollHeight;
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>
