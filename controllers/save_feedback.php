<?php
// controllers/save_feedback.php
session_start();
require_once '../includes/db.php';
require_once '../includes/mailer.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'Feedback';
    $subject = $_POST['subject'] ?? '';
    $details = $_POST['details'] ?? '';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Explicitly enforce anonymity by ensuring submitted_by is NULL
    $submitted_by = $is_anonymous ? null : $_SESSION['login_id'];

    if (empty($subject) || empty($details)) {
        echo "<script>alert('Please fill out all critically required fields.'); window.history.back();</script>";
        exit;
    }

    try {
        $ticket_number = 'FB-' . date('ym') . '-' . rand(1000, 9999);
        
        $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, department, subject, description, is_anonymous, status) VALUES ('Feedback', ?, ?, ?, ?, ?, ?, 'Open')");
        $stmt->execute([$ticket_number, $submitted_by, $type, $subject, $details, $is_anonymous]);
        
        $ticket_id = $pdo->lastInsertId();
        
        // Trigger Webhook
        require_once '../includes/webhook_helper.php';
        fireWebhook($pdo, 'ticket_created', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'source' => 'Feedback',
            'department' => $type,
            'subject' => $subject,
            'is_anonymous' => $is_anonymous
        ]);

        // Enterprise routing logic: notify Admins immediately if a severe Complaint is filed
        if ($type === 'Complaint') {
            $admin_stmt = $pdo->query("SELECT email FROM users WHERE role = 'Admin' AND status = 'Active'");
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $admin) {
                $msg = "A new internal Complaint has been securely submitted on the system.<br><br>";
                $msg .= "<strong>Subject:</strong> " . htmlspecialchars($subject) . "<br>";
                $msg .= "<strong>Status:</strong> Needs Review<br><br>";
                $msg .= "Please log into the Corporate Feedback dashboard to review this case securely.";
                sendSystemEmail($admin['email'], "System Alert: New Official Complaint Logged", $msg);
            }
        }

        echo "<script>alert('Your submission was securely recorded.'); window.location.href='../feedback.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Database logic error occurred: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else {
    header("Location: ../feedback.php");
    exit;
}
