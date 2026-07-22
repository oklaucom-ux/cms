<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_support');

// unified_tickets table is handled globally

$statusFilter = $_GET['status'] ?? 'Open';
$query = "SELECT * FROM unified_tickets WHERE source = 'Client_Support' ";
$params = [];

if ($statusFilter !== 'All') {
    $query .= "WHERE status = ? ";
    $params[] = $statusFilter;
}

$query .= "ORDER BY 
    CASE priority 
        WHEN 'Critical' THEN 1 
        WHEN 'High' THEN 2 
        WHEN 'Medium' THEN 3 
        WHEN 'Low' THEN 4 
    END ASC, created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate SLA metrics
$openTickets = $pdo->query("SELECT COUNT(*) FROM unified_tickets WHERE status = 'Open'")->fetchColumn();
$criticalOpen = $pdo->query("SELECT COUNT(*) FROM unified_tickets WHERE status = 'Open' AND priority = 'Critical'")->fetchColumn();
?>
<style>
.priority-Critical { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.priority-High { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
.priority-Medium { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
.priority-Low { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

.status-Open { background: #dbeafe; color: #1d4ed8; }
.status-Closed { background: #f3f4f6; color: #6b7280; }
.status-Ongoing { background: #fef08a; color: #a16207; }
</style>
<div class="content-section active">
    <div class="section-header">
        <h2>Enterprise Support Desk (Triage)</h2>
        <button class="add-button" onclick="document.getElementById('createTicketModal').style.display='block'">+ Create Ticket</button>
    </div>

    <div class="dashboard-grid" style="margin-bottom: 20px;">
        <div class="dashboard-card" style="">
            <h3><?= $openTickets ?></h3>
            <p>Total Open Tickets</p>
        </div>
        <div class="dashboard-card" style=" background: <?= $criticalOpen > 0 ? '#fef2f2' : 'white' ?>;">
            <h3><?= $criticalOpen ?></h3>
            <p>Critical SLA Alerts</p>
        </div>
        <div class="dashboard-card" style="">
            <h3><a href="?status=All" style="text-decoration:none; color:inherit;">View All History</a></h3>
            <p>Includes Closed Tickets</p>
        </div>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Client</th>
                    <th>Subject</th>
                    <th>Priority (SLA)</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $t): ?>
                <tr>
                    <td style="font-family:monospace; font-weight:bold;"><?= htmlspecialchars($t['ticket_number']) ?></td>
                    <td>
                        <div style="font-weight:600; color:#111827;"><?= htmlspecialchars($t['requester_name']) ?></div>
                        <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($t['requester_id']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($t['subject']) ?></td>
                    <td>
                        <span style="padding:4px 8px; border-radius:6px; font-weight:600; font-size:12px;" class="priority-<?= $t['priority'] ?>">
                            <?= htmlspecialchars($t['priority']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="padding:4px 8px; border-radius:6px; font-weight:600; font-size:12px;" class="status-<?= str_replace(' ', '', $t['status']) ?>">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($t['created_at']) ?></td>
                    <td>
                        <button class="edit-button" onclick="openTicket(<?= $t['id'] ?>)">Manage</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="7" style="text-align:center;">No tickets in this view!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="ticketModal" class="modal">
    <div class="modal-content" style="max-width: 800px; padding:0; background:#f8fafc; display:flex; flex-direction:column; height:80vh;">
        <div style="padding:20px; background:#1e293b; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; font-size:18px;" id="tmTitle">Loading...</h2>
            <span class="close-modal" style="color:white; cursor:pointer;" onclick="document.getElementById('ticketModal').style.display='none'">&times;</span>
        </div>
        
        <div id="tmReplies" style="flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:16px;">
            <!-- Threads injected here via JS -->
            <div style="text-align:center; padding:50px;">Loading thread...</div>
        </div>
        
        <div style="padding:20px; background:white; border-top:1px solid #e2e8f0;">
            <form method="POST" action="controllers/ticket_reply.php" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="ticket_id" id="tmTicketId">
                <div style="display:flex; gap:10px;">
                    <select name="status" id="tmStatus" style="width:150px; border-radius:6px; border:1px solid #cbd5e1; padding:8px;" required>
                        <option value="Open">Open</option>
                        <option value="Ongoing">Ongoing (WIP)</option>
                        <option value="Closed">Closed</option>
                    </select>
                    <input type="text" name="message" required placeholder="Type your response to the client..." style="flex:1; border-radius:6px; border:1px solid #cbd5e1; padding:8px;">
                    <button type="submit" style="background:#4f46e5; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer;">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div id="createTicketModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="document.getElementById('createTicketModal').style.display='none'">&times;</span>
        <h2>Create Support Ticket</h2>
        <form method="POST" action="controllers/submit_internal_ticket.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Client / User</label>
                <select name="client_id" required>
                    <?php 
                    $all_users = $pdo->query("SELECT login_id, name, role FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach($all_users as $u) {
                        echo "<option value='{$u['login_id']}'>{$u['name']} ({$u['role']})</option>";
                    }
                    ?>
                </select>
            </div>
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
                <textarea name="message" rows="5" required></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="cancel" onclick="document.getElementById('createTicketModal').style.display='none'">Cancel</button>
                <button type="submit" class="submit">Create Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTicket(id) {
    document.getElementById('ticketModal').style.display = 'block';
    document.getElementById('tmTicketId').value = id;
    
    // Fetch replies
    fetch('controllers/get_ticket_thread.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('tmTitle').textContent = data.ticket.ticket_number + ' - ' + data.ticket.subject;
            document.getElementById('tmStatus').value = data.ticket.status;
            
            let html = '';
            data.replies.forEach(r => {
                let align = r.is_client == 1 ? 'flex-start' : 'flex-end';
                let bg = r.is_client == 1 ? 'white' : '#4f46e5';
                let color = r.is_client == 1 ? '#111827' : 'white';
                let Name = r.is_client == 1 ? data.ticket.requester_name : (r.user_name || 'Agent');

                html += `<div style="align-self:${align}; max-width:80%; display:flex; flex-direction:column; gap:4px;">
                    <div style="font-size:11px; color:#6b7280; margin:0 4px; text-align:${r.is_client==1?'left':'right'}">${Name} • ${r.created_at}</div>
                    <div style="background:${bg}; color:${color}; padding:12px 16px; border-radius:12px; border:1px solid #e2e8f0; font-size:14px; white-space:pre-wrap;">${r.message}</div>
                </div>`;
            });
            document.getElementById('tmReplies').innerHTML = html;
            // scroll to bottom
            const container = document.getElementById('tmReplies');
            container.scrollTop = container.scrollHeight;
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>
