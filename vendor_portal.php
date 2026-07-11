<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!in_array($_SESSION['role'], ['Admin', 'Super Admin']) && $_SESSION['role'] !== 'Vendor') {
    die("<div class='content-section active'><h2>Unauthorized Access</h2><p>This portal is exclusively for Vendors and Subcontractors.</p></div>");
}

$me = $_SESSION['login_id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $taskId = (int)$_POST['task_id'];
    $newStatus = trim($_POST['status']);
    
    // Security check: Only update if assigned to this vendor
    $checkStmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to LIKE ?");
    $checkStmt->execute([$taskId, "%$me%"]);
    if ($checkStmt->fetch(PDO::FETCH_ASSOC) && in_array($newStatus, ['Pending', 'In Progress', 'Completed'])) {
        $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$newStatus, $taskId]);
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$me, 'Vendor Task Update', "Updated task #{$taskId} to {$newStatus}"]);
        header("Location: vendor_portal.php?updated=1");
        exit;
    }
}

// Fetch tasks
$tasksStmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to LIKE ? AND status != 'Deleted' ORDER BY due_date ASC");
$tasksStmt->execute(["%$me%"]);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>Vendor Portal</h2>
        <p style="color:var(--text-muted);">Manage your allocated tasks and update their progression status.</p>
    </div>

    <?php if(isset($_GET['updated'])): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        Task status updated successfully.
    </div>
    <?php endif; ?>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Task Name</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tasks as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['id']) ?></td>
                    <td><?= htmlspecialchars($t['name']) ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars($t['description']) ?></small></td>
                    <td>
                        <?php
                        $dueDate = $t['due_date'];
                        $isUrgent = (strtotime($dueDate) <= strtotime('+1 day')) && $t['status'] !== 'Completed';
                        if ($isUrgent) echo "<span style='color:#dc2626; font-weight:bold;'>⚠️ $dueDate</span>";
                        else echo htmlspecialchars($dueDate);
                        ?>
                    </td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 6px; font-weight:600; font-size:12px;
                            background: <?= $t['status']=='Completed' ? '#d1fae5; color:#065f46;' : ($t['status']=='In Progress' ? '#e0e7ff; color:#3730a3;' : '#fef3c7; color:#92400e;') ?>">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if($t['status'] !== 'Completed'): ?>
                        <form method="POST" style="display:flex; gap:10px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <select name="status" style="padding:4px; border-radius:4px; border:1px solid #ccc;">
                                <option value="Pending" <?= $t['status']=='Pending'?'selected':'' ?>>Pending</option>
                                <option value="In Progress" <?= $t['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                            <button type="submit" style="background:#10b981; color:white; border:none; padding:4px 10px; border-radius:4px; cursor:pointer;">Save</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#10b981; font-weight:bold;">✓ Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($tasks)): ?>
                <tr><td colspan="5" style="text-align:center;">No active tasks assigned to you right now.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
