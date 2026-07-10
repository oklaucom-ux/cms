<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$page_title = "Feedback & Complaints";
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'manage_feedback');

$isAdmin = in_array($_SESSION['role'], ['Admin', 'Manager']);
$my_login = $_SESSION['login_id'];

if ($isAdmin) {
    $stmt = $pdo->query("SELECT f.*, u.name as user_name FROM unified_tickets f 
                         LEFT JOIN users u ON f.requester_id = u.login_id 
                         WHERE f.source = 'Feedback'
                         ORDER BY f.created_at DESC");
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Only show user's own named submissions
    $stmt = $pdo->prepare("SELECT f.*, u.name as user_name FROM unified_tickets f LEFT JOIN users u ON f.requester_id = u.login_id WHERE f.source = 'Feedback' AND f.requester_id = ? ORDER BY f.created_at DESC");
    $stmt->execute([$my_login]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="header-action">
        <h2>📬 Feedback, Suggestions & Complaints</h2>
        <button class="btn btn-primary" onclick="document.getElementById('feedbackModal').style.display='block'">
            <i class="fas fa-plus"></i> Submit New
        </button>
    </div>

    <!-- Active Feedbacks Grid -->
    <div class="dashboard-grid" style="margin-top: 20px;">
        <?php foreach ($feedbacks as $fb): 
            $badge_style = 'background: rgba(59, 130, 246, 0.1); color: #3b82f6;';
            if ($fb['status'] === 'Resolved') $badge_style = 'background: rgba(16, 185, 129, 0.1); color: #10B981;';
            if ($fb['status'] === 'Reviewed') $badge_style = 'background: rgba(245, 158, 11, 0.1); color: #F59E0B;';
        ?>
            <div class="card" style="">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="color: var(--text-color);"><?= htmlspecialchars($fb['department'] ?? 'Feedback') ?></strong>
                    <span class="badge" style="<?= $badge_style ?> padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 600;"><?= htmlspecialchars($fb['status']) ?></span>
                </div>
                <h3 style="margin: 12px 0 8px 0; font-size: 1.1rem; color: var(--text-heading);"><?= htmlspecialchars($fb['subject']) ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 20px; line-height: 1.5;">
                    <?= nl2br(htmlspecialchars($fb['description'] ?? '')) ?>
                </p>
                <div style="font-size: 0.85em; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 12px; display:flex; justify-content:space-between; align-items:flex-end;">
                    <span>
                        <?php if ($fb['is_anonymous']): ?>
                            <i class="fas fa-user-secret"></i> <strong>Anonymous User</strong>
                        <?php else: ?>
                            <i class="fas fa-user"></i> <strong><?= htmlspecialchars($fb['user_name'] ?? $_SESSION['name']) ?></strong>
                        <?php endif; ?>
                        <br>
                        <span style="font-size: 0.9em; opacity: 0.8;"><?= date('M d, Y h:i A', strtotime($fb['created_at'])) ?></span>
                    </span>
                    
                    <?php if($isAdmin && $fb['status'] !== 'Resolved'): ?>
                        <button class="btn btn-sm btn-outline" style="padding: 4px 10px;" onclick="openUpdateModal(<?= $fb['id'] ?>, '<?= $fb['status'] ?>')">Update Status</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if(empty($feedbacks)): ?>
            <div class="no-data" style="grid-column: 1/-1; text-align: center; padding: 40px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color); color: var(--text-muted);">
                No feedback or complaints submitted yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Submit Form Modal -->
<div id="feedbackModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="document.getElementById('feedbackModal').style.display='none'">&times;</span>
        <h2>Submit to HR / Admin</h2>
        <form method="POST" action="controllers/save_feedback.php" style="margin-top: 15px;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Category Type</label>
                <select name="type" required>
                    <option value="Suggestion">💡 General Suggestion</option>
                    <option value="Feedback">🗣️ System/Work Feedback</option>
                    <option value="Complaint">⚠️ Official Complaint</option>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" required placeholder="Brief summary...">
            </div>
            <div class="form-group">
                <label>Detailed Explanation</label>
                <textarea name="details" rows="6" required placeholder="Please provide all necessary details..."></textarea>
            </div>
            
            <div class="form-group" style="background: var(--bg-hover); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                <label style="display:flex; align-items:center; gap: 12px; cursor: pointer; margin:0;">
                    <input type="checkbox" name="is_anonymous" value="1" style="width:18px; height:18px;">
                    <div>
                        <strong style="display:block; color: var(--text-heading);">Submit Anonymously</strong>
                        <span style="font-size:0.85em; color:var(--text-muted); font-weight:normal; display:block; margin-top:4px;">If checked, your name and profile details will be completely scrubbed from this report. (Default is named submission).</span>
                    </div>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Securely</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Status Modal (Admin) -->
<?php if($isAdmin): ?>
<div id="updateModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <span class="close-button" onclick="document.getElementById('updateModal').style.display='none'">&times;</span>
        <h2>Manage Status</h2>
        <form method="POST" action="controllers/update_ticket_status.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="update_feedback_id">
            <div class="form-group">
                <label>New Status</label>
                <select name="status" required>
                    <option value="Open">Open</option>
                    <option value="Reviewed">Reviewed</option>
                    <option value="Resolved">Resolved</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
        </form>
    </div>
</div>
<script>
function openUpdateModal(id, current) {
    document.getElementById('update_feedback_id').value = id;
    document.getElementById('updateModal').style.display = 'block';
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
