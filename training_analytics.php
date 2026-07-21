<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_training');

$course_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM training_courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Invalid course.");
}

$assignments = $pdo->prepare("
    SELECT ta.*, u.name as user_name, u.email, tr.score
    FROM training_assignments ta
    JOIN users u ON ta.user_id = u.login_id
    LEFT JOIN training_results tr ON ta.id = tr.assignment_id
    WHERE ta.course_id = ?
    ORDER BY ta.assigned_at DESC
");
$assignments->execute([$course_id]);
$enrollments = $assignments->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📊 Analytics: <?= htmlspecialchars($course['title']) ?></h2>
        <button class="edit-button" onclick="window.location.href='training.php'">Back to Training Hub</button>
    </div>

    <div style="background:white; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.05); overflow:hidden; border:1px solid #e5e7eb; margin-top: 20px;">
        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Employee</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Status</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Assigned At</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Completed At</th>
                    <th style="padding:15px; font-weight:600; color:#4b5563;">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($enrollments as $e): ?>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:15px;">
                        <div style="font-weight:600; color:#111827;"><?= htmlspecialchars($e['user_name']) ?></div>
                        <div style="font-size:12px; color:#6b7280;"><?= htmlspecialchars($e['user_id']) ?></div>
                    </td>
                    <td style="padding:15px;">
                        <span class="status-badge status-<?= strtolower(str_replace(' ','', $e['status'])) ?>"><?= htmlspecialchars($e['status']) ?></span>
                    </td>
                    <td style="padding:15px; color:#6b7280; font-size:13px;"><?= $e['assigned_at'] ? date('M d, Y H:i', strtotime($e['assigned_at'])) : '-' ?></td>
                    <td style="padding:15px; color:#6b7280; font-size:13px;"><?= $e['completed_at'] ? date('M d, Y H:i', strtotime($e['completed_at'])) : '-' ?></td>
                    <td style="padding:15px; font-weight:bold;"><?= $e['score'] !== null ? $e['score'].'%' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($enrollments)): ?>
                <tr>
                    <td colspan="5" style="padding:20px; text-align:center; color:#6b7280;">No employees are currently enrolled in this course.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
