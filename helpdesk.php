<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_tickets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id TEXT NOT NULL,
        department TEXT NOT NULL,
        subject TEXT NOT NULL,
        description TEXT NOT NULL,
        priority TEXT DEFAULT 'Medium',
        status TEXT DEFAULT 'Open',
        assigned_to TEXT,
        resolution_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME
    )");
} catch (Exception $e) {}

$isAgent = hasPermission($pdo, 'manage_users') || in_array($_SESSION['role'], ['Admin', 'Super Admin']);
$myId = $_SESSION['login_id'];

// Fetch Tickets
if ($isAgent) {
    $tickets = $pdo->query("SELECT t.*, t.requester_name as user_name, a.name as agent_name FROM unified_tickets t LEFT JOIN users a ON t.assigned_agent_id = a.login_id WHERE t.source = 'IT_Helpdesk' ORDER BY CASE WHEN t.status='Open' THEN 1 WHEN t.status='In Progress' THEN 2 ELSE 3 END, t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT t.*, t.requester_name as user_name, a.name as agent_name FROM unified_tickets t LEFT JOIN users a ON t.assigned_agent_id = a.login_id WHERE t.requester_id = ? AND t.source = 'IT_Helpdesk' ORDER BY t.created_at DESC");
    $stmt->execute([$myId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stats
$openCount = 0; $resolvedCount = 0;
foreach($tickets as $t) {
    if($t['status'] === 'Resolved' || $t['status'] === 'Closed') $resolvedCount++;
    else $openCount++;
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>🎫 IT & HR Helpdesk</h2>
        <button class="add-button" onclick="document.getElementById('ticketModal').style.display='flex'">+ New Support Ticket</button>
    </div>

    <!-- Stats -->
    <div style="display:flex; gap:20px; margin-bottom:20px;">
        <div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; flex:1; box-shadow:0 2px 4px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase;">Open Tickets</div>
                <div style="font-size:28px; font-weight:900; color:#ef4444;"><?= $openCount ?></div>
            </div>
            <div style="font-size:32px;">🔥</div>
        </div>
        <div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; flex:1; box-shadow:0 2px 4px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:12px; color:#64748b; font-weight:bold; text-transform:uppercase;">Resolved</div>
                <div style="font-size:28px; font-weight:900; color:#10b981;"><?= $resolvedCount ?></div>
            </div>
            <div style="font-size:32px;">✅</div>
        </div>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Requester</th>
                    <th>Subject</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $t): 
                    $prioColor = $t['priority']=='High' ? '#ef4444' : ($t['priority']=='Medium' ? '#f59e0b' : '#10b981');
                    $statColor = $t['status']=='Open' ? '#ef4444' : ($t['status']=='In Progress' ? '#3b82f6' : '#10b981');
                ?>
                <tr>
                    <td style="font-family:monospace; font-weight:bold; color:#64748b;">#TKT-<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td style="font-weight:bold; color:#1e293b;"><?= htmlspecialchars($t['user_name']) ?></td>
                    <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($t['subject']) ?>"><?= htmlspecialchars($t['subject']) ?></td>
                    <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold;"><?= htmlspecialchars($t['department']) ?></span></td>
                    <td style="color:<?= $prioColor ?>; font-weight:bold;"> <?= $t['priority'] ?></td>
                    <td>
                        <span style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; background:<?= $statColor ?>20; color:<?= $statColor ?>;">
                            <?= $t['status'] ?>
                        </span>
                    </td>
                    <td style="font-size:12px; color:#64748b;"><?= $t['agent_name'] ? htmlspecialchars($t['agent_name']) : 'Unassigned' ?></td>
                    <td>
                        <button onclick='viewTicket(<?= json_encode($t) ?>)' style="background:#f1f5f9; border:none; padding:6px 12px; border-radius:6px; font-size:11px; font-weight:bold; cursor:pointer;">View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($tickets)) echo "<tr><td colspan='8' style='text-align:center;'>No support tickets found.</td></tr>"; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="modal" id="ticketModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Raise Support Ticket</h2>
        <form method="POST" action="controllers/save_ticket.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="create">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Department</label>
                    <select name="department" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="IT Support">IT Support</option>
                        <option value="HR Support">HR Support</option>
                        <option value="Facilities">Facilities</option>
                        <option value="Payroll">Payroll</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Priority</label>
                    <select name="priority" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High (Urgent)</option>
                    </select>
                </div>
            </div>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Subject</label>
            <input type="text" name="subject" required placeholder="Short summary of the issue" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Detailed Description</label>
            <textarea name="description" required rows="4" placeholder="Please describe the issue in detail..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;"></textarea>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('ticketModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Submit Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- View/Resolve Ticket Modal -->
<div class="modal" id="viewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2 style="margin:0;" id="v_subject">Ticket Details</h2>
            <span id="v_status" style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; background:#e2e8f0;"></span>
        </div>
        
        <div style="background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:20px; font-size:14px; border:1px solid #e2e8f0;">
            <p style="margin:0 0 10px 0;"><strong style="color:#64748b;">Requester:</strong> <span id="v_requester"></span></p>
            <p style="margin:0 0 10px 0;"><strong style="color:#64748b;">Department:</strong> <span id="v_dept"></span></p>
            <p style="margin:0 0 10px 0;"><strong style="color:#64748b;">Description:</strong><br><span id="v_desc" style="display:block; margin-top:5px; white-space:pre-wrap;"></span></p>
            <p style="margin:0;"><strong style="color:#64748b;">Resolution Notes:</strong><br><span id="v_notes" style="display:block; margin-top:5px; font-style:italic; color:#10b981;"></span></p>
        </div>
        
        <?php if($isAgent): ?>
        <form method="POST" action="controllers/save_ticket.php" style="border-top:1px solid #e2e8f0; padding-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="u_id">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:13px;">Status</label>
                    <select name="status" id="u_status" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="Open">Open</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:13px;">Assign To Me?</label>
                    <select name="assign_me" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        <option value="0">No change</option>
                        <option value="1">Yes, Assign to Me</option>
                    </select>
                </div>
            </div>
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:13px;">Resolution Notes (Required if Resolved)</label>
            <textarea name="resolution_notes" id="u_notes" rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;"></textarea>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('viewModal').style.display='none'" style="background:#e2e8f0; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:bold;">Close</button>
                <button type="submit" style="background:#10b981; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:bold; cursor:pointer;">Update Ticket</button>
            </div>
        </form>
        <?php else: ?>
        <div style="display:flex; justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('viewModal').style.display='none'" style="background:#e2e8f0; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:bold;">Close Window</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewTicket(t) {
    document.getElementById('v_subject').innerText = t.subject;
    document.getElementById('v_status').innerText = t.status;
    document.getElementById('v_requester').innerText = t.user_name;
    document.getElementById('v_dept').innerText = t.department + ' (' + t.priority + ')';
    document.getElementById('v_desc').innerText = t.description;
    document.getElementById('v_notes').innerText = t.resolution_notes ? t.resolution_notes : 'No resolution notes yet.';
    
    const u_id = document.getElementById('u_id');
    if(u_id) {
        u_id.value = t.id;
        document.getElementById('u_status').value = t.status;
        document.getElementById('u_notes').value = t.resolution_notes || '';
    }
    
    document.getElementById('viewModal').style.display = 'flex';
}
</script>

<?php require_once 'includes/footer.php'; ?>
