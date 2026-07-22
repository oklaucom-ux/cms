<?php
/**
 * CRON TASKS SCRIPT
 * Usage via CLI: php cron_tasks.php YOUR_SECRET_KEY
 * Usage via Web: https://yourdomain.com/cron_tasks.php?key=YOUR_SECRET_KEY
 * 
 * Recommended execution: Run this daily at 00:01 AM
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/notifications.php';

// Secure the script with a secret key to prevent random public internet triggers.
$CRON_SECRET = $GLOBAL_SETTINGS['cron_secret'] ?? 'Admin123!SecureCronKey'; 

// Fetch provided key
$providedKey = $_GET['key'] ?? (isset($argv[1]) ? $argv[1] : null);

if ($providedKey !== $CRON_SECRET) {
    http_response_code(403);
    die("Unauthorized Access: Invalid Cron Secret Key. Execution halted.\n");
}

echo "========================================\n";
echo "Starting Scheduled CRON Tasks: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// -----------------------------------------------------
// 1. Task Deadline Reminders (Urgent/Overdue tasks)
// -----------------------------------------------------
echo "[1] Processing Task Reminders...\n";
$stmtTasks = $pdo->query("SELECT * FROM tasks WHERE status != 'Completed' AND status != 'Deleted' AND date(due_date) <= CURDATE()");
$urgentTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
$notifyCount = 0;

foreach ($urgentTasks as $t) {
    $assigned_users = explode(',', $t['assigned_to']);
    foreach ($assigned_users as $user) {
        $login_id = trim($user);
        if(empty($login_id)) continue;
        
        $msg = "Action Required: The task '{$t['name']}' is overdue or due today ({$t['due_date']}).";
        
        // In-app Notification
        createNotification($pdo, $login_id, 'Task Reminder', $msg, 'tasks.php');
        
        // Email Notification
        $email = getUserEmail($pdo, $login_id);
        if ($email) {
            sendSystemEmail($email, "Urgent: Task Deadline Reminder", $msg);
        }
        $notifyCount++;
    }
}
echo "    -> Sent {$notifyCount} task reminders.\n\n";

// -----------------------------------------------------
// 2. Attendance Auto-Closure (Missed checkouts)
// -----------------------------------------------------
echo "[2] Processing Attendance Fallbacks...\n";
// Any attendance from yesterday or earlier that hasn't clocked out gets marked as "Missed Clock Out"
$stmtAtt = $pdo->query("SELECT id FROM attendance WHERE clock_out IS NULL AND date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$missedOut = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
foreach ($missedOut as $att) {
    $pdo->exec("UPDATE attendance SET status = 'Missed Clock Out' WHERE id = " . $att['id']);
}
echo "    -> Auto-closed " . count($missedOut) . " forgotten attendances.\n\n";

// -----------------------------------------------------
// 3. Invoice Overdue Marking
// -----------------------------------------------------
echo "[3] Processing Overdue Invoices...\n";
$stmtInv = $pdo->query("SELECT id FROM invoices WHERE status = 'Unpaid' AND date(due_date) < CURDATE()");
$overdueInvoices = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
foreach ($overdueInvoices as $inv) {
    $pdo->exec("UPDATE invoices SET status = 'Overdue' WHERE id = " . $inv['id']);
}
echo "    -> Marked " . count($overdueInvoices) . " invoices as Overdue.\n\n";

// -----------------------------------------------------
// 4. Contract/Lead Follow-up Reminders
// -----------------------------------------------------
echo "[4] Processing CRM Follow-ups...\n";
$stmtCrm = $pdo->query("SELECT * FROM crm_leads WHERE follow_up_date = CURDATE() AND stage NOT IN ('Won', 'Lost')");
$pendingLeads = $stmtCrm->fetchAll(PDO::FETCH_ASSOC);
foreach ($pendingLeads as $lead) {
    $msg = "CRM Reminder: Follow up scheduled today for Lead '{$lead['lead_name']}' ({$lead['company']}).";
    createNotification($pdo, $lead['owner_id'], 'CRM Follow Up', $msg, 'crm.php');
}
echo "    -> Triggered " . count($pendingLeads) . " CRM reminders.\n\n";


// -----------------------------------------------------
// 5. LMS Compliance & Certificate Expirations
// -----------------------------------------------------
echo "[5] Processing LMS Compliance Expirations...\n";

// 5a. Expire certificates
$stmtExp = $pdo->query("SELECT ta.id, ta.user_id, c.title FROM training_assignments ta JOIN training_courses c ON ta.course_id = c.id WHERE ta.status = 'Completed' AND ta.expires_at IS NOT NULL AND date(ta.expires_at) < CURDATE()");
$expiredRecords = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
foreach ($expiredRecords as $ex) {
    // Reset to assigned so they have to retake it. Wipe answers.
    $pdo->prepare("UPDATE training_assignments SET status='Assigned', user_answers=NULL, expires_at=NULL WHERE id=?")->execute([$ex['id']]);
    
    $msg = "COMPLIANCE ALERT: Your certification for '{$ex['title']}' has expired today. You must retake this corporate module immediately.";
    createNotification($pdo, $ex['user_id'], 'Certificate Expired', $msg, 'training.php');
}
echo "    -> Processed " . count($expiredRecords) . " LMS expirations.\n\n";

// 5b. Pre-Expiration Warning (30 days out)
$stmtWarn = $pdo->query("SELECT ta.user_id, c.title, ta.expires_at FROM training_assignments ta JOIN training_courses c ON ta.course_id = c.id WHERE ta.status = 'Completed' AND ta.expires_at = DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$warningRecords = $stmtWarn->fetchAll(PDO::FETCH_ASSOC);
foreach ($warningRecords as $w) {
    $msg = "Reminder: Your certificate for '{$w['title']}' expires in 30 days on {$w['expires_at']}.";
    createNotification($pdo, $w['user_id'], 'Certificate Renewing Soon', $msg, 'training.php');
}
echo "    -> Sent " . count($warningRecords) . " LMS 30-day renewal warnings.\n\n";

// -----------------------------------------------------
// 6. Service Desk SLA Enforcement
// -----------------------------------------------------
echo "[6] Processing SLA Enforcements...\n";

// Critical: 2 hours | High: 12 hours
$slaViolations = [];

// Check for SLA breaches on tickets
$stmtSla = $pdo->query("SELECT * FROM unified_tickets WHERE status = 'Open'");
$openTickets = $stmtSla->fetchAll(PDO::FETCH_ASSOC);

foreach ($openTickets as $t) {
    if (empty($t['updated_at'])) continue;
    
    $hoursIdle = (time() - strtotime($t['updated_at'])) / 3600;
    
    if ($t['priority'] === 'Critical' && $hoursIdle > 2) {
        $slaViolations[] = $t;
    } else if ($t['priority'] === 'High' && $hoursIdle > 12) {
        $slaViolations[] = $t;
    }
}

if (count($slaViolations) > 0) {
    $admins = $pdo->query("SELECT login_id, email FROM users WHERE role = 'Admin'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($slaViolations as $v) {
        foreach ($admins as $admin) {
            $msg = "🚨 SLA BREACH: Support Ticket {$v['ticket_number']} ({$v['priority']}) has been idle for too long!";
            createNotification($pdo, $admin['login_id'], 'SLA Violation', $msg, 'desk.php');
            if ($admin['email']) {
                sendSystemEmail($admin['email'], "SLA Breach - Action Required", $msg);
            }
        }
    }
}
echo "    -> Processed " . count($slaViolations) . " SLA violations.\n\n";

echo "========================================\n";
echo "CRON Execution Complete.\n";
echo "========================================\n";
?>

