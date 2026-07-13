<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_support');
$auto_inc = $use_mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';

$statusFilter = $_GET['status'] ?? 'Open';
$sourceFilter = $_GET['source'] ?? 'All';

$query = "SELECT t.*, u.name as assigned_name FROM unified_tickets t LEFT JOIN users u ON t.assigned_agent_id = u.login_id WHERE 1=1 ";
$params = [];

if ($statusFilter !== 'All') {
    $query .= "AND t.status = ? ";
    $params[] = $statusFilter;
}
if ($sourceFilter !== 'All') {
    $query .= "AND t.source = ? ";
    $params[] = $sourceFilter;
}

$query .= "ORDER BY 
    CASE t.priority 
        WHEN 'Critical' THEN 1 
        WHEN 'High' THEN 2 
        WHEN 'Medium' THEN 3 
        WHEN 'Low' THEN 4 
        ELSE 5 
    END, 
    t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM unified_tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$openCount = $counts['Open'] ?? 0;
$progressCount = $counts['In Progress'] ?? 0;
?>

<div class="main-content">
    <div class="header-action">
        <div>
            <h2>🏢 Omni-Channel Support Matrix</h2>
            <p style="color:#6b7280; font-size:14px; margin-top:5px;">Manage Client Support, Internal IT Helpdesk, and Feedback.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <div style="background:var(--bg-card); padding:8px 15px; border-radius:8px; border:1px solid #e5e7eb; display:flex; align-items:center; gap:10px;">
                <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444;"></span>
                <strong><?= $openCount ?> Open</strong>
            </div>
            <div style="background:var(--bg-card); padding:8px 15px; border-radius:8px; border:1px solid #e5e7eb; display:flex; align-items:center; gap:10px;">
                <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#f59e0b;"></span>
                <strong><?= $progressCount ?> Active</strong>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div style="background:var(--bg-card); padding:15px 20px; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:20px; display:flex; gap:15px; align-items:center;">
        <strong>Filters:</strong>
        <form method="GET" style="display:flex; gap:15px; margin:0;">
            <select name="status" onchange="this.form.submit()" style="padding:6px 10px; border-radius:6px; border:1px solid #d1d5db;">
                <option value="All" <?= $statusFilter=='All'?'selected':'' ?>>All Statuses</option>
                <option value="Open" <?= $statusFilter=='Open'?'selected':'' ?>>Open</option>
                <option value="In Progress" <?= $statusFilter=='In Progress'?'selected':'' ?>>In Progress</option>
                <option value="Resolved" <?= $statusFilter=='Resolved'?'selected':'' ?>>Resolved / Closed</option>
            </select>
            <select name="source" onchange="this.form.submit()" style="padding:6px 10px; border-radius:6px; border:1px solid #d1d5db;">
                <option value="All" <?= $sourceFilter=='All'?'selected':'' ?>>All Sources</option>
                <option value="Client_Support" <?= $sourceFilter=='Client_Support'?'selected':'' ?>>Client Support</option>
                <option value="IT_Helpdesk" <?= $sourceFilter=='IT_Helpdesk'?'selected':'' ?>>Internal IT Helpdesk</option>
                <option value="Feedback" <?= $sourceFilter=='Feedback'?'selected':'' ?>>Feedback / Complaints</option>
            </select>
        </form>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Ticket ID / Source</th>
                    <th>Requester</th>
                    <th>Subject</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Agent</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $t): 
                    $srcColor = '#6b7280';
                    $srcLabel = 'Feedback';
                    if($t['source'] == 'Client_Support') { $srcColor = '#4f46e5'; $srcLabel = 'Client Support'; }
                    if($t['source'] == 'IT_Helpdesk') { $srcColor = '#10b981'; $srcLabel = 'IT Helpdesk'; }
                ?>
                <tr>
                    <td>
                        <div style="font-family:monospace; font-weight:bold; color:#111827;"><?= htmlspecialchars($t['ticket_number']) ?></div>
                        <div style="font-size:11px; font-weight:bold; color:<?= $srcColor ?>; margin-top:2px; text-transform:uppercase; letter-spacing:0.5px;"><?= $srcLabel ?></div>
                    </td>
                    <td>
                        <?php if($t['is_anonymous']): ?>
                            <div style="font-weight:600; color:#6b7280;"><i class="fas fa-user-secret"></i> Anonymous</div>
                        <?php else: ?>
                            <div style="font-weight:600; color:#111827;"><?= htmlspecialchars($t['requester_name'] ?? 'Unknown') ?></div>
                            <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($t['requester_id'] ?? '') ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:500; color:#111827;"><?= htmlspecialchars($t['subject']) ?></div>
                        <div style="font-size:12px; color:#6b7280; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($t['description']) ?></div>
                    </td>
                    <td>
                        <span style="font-size:12px; font-weight:bold; padding:2px 6px; border-radius:4px; 
                            color: <?= $t['priority']=='Critical' ? '#dc2626' : ($t['priority']=='High' ? '#ea580c' : '#4b5563') ?>;">
                            <?= htmlspecialchars($t['priority'] ?? 'Low') ?>
                        </span>
                    </td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 6px; font-weight:600; font-size:12px;
                            background: <?= in_array($t['status'], ['Resolved','Closed']) ? '#f3f4f6; color:#6b7280;' : '#dbeafe; color:#1e40af;' ?>">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:13px; color:#111827; font-weight:500;"><?= htmlspecialchars($t['assigned_name'] ?? 'Unassigned') ?></div>
                    </td>
                    <td style="font-size:12px; color:#6b7280;">
                        <?= date('M d, Y', strtotime($t['created_at'])) ?>
                    </td>
                    <td>
                        <?php if($t['source'] == 'Client_Support'): ?>
                            <a href="desk.php?status=All" style="text-decoration:none; background:var(--bg-card); border:1px solid #cbd5e1; padding:4px 10px; border-radius:4px; font-size:12px; cursor:pointer; color:#111827;">Open Desk</a>
                        <?php elseif($t['source'] == 'IT_Helpdesk'): ?>
                            <a href="helpdesk.php" style="text-decoration:none; background:var(--bg-card); border:1px solid #cbd5e1; padding:4px 10px; border-radius:4px; font-size:12px; cursor:pointer; color:#111827;">Open Helpdesk</a>
                        <?php else: ?>
                            <a href="feedback.php" style="text-decoration:none; background:var(--bg-card); border:1px solid #cbd5e1; padding:4px 10px; border-radius:4px; font-size:12px; cursor:pointer; color:#111827;">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($tickets)): ?>
                <tr><td colspan="8" style="text-align:center;">No tickets found matching criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
