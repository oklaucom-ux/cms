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
$today = date('Y-m-d');
$stmtTasks = $pdo->prepare("SELECT * FROM tasks WHERE status != 'Completed' AND status != 'Done' AND status != 'Deleted' AND due_date IS NOT NULL AND due_date != '' AND date(due_date) <= ?");
$stmtTasks->execute([$today]);
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
$yesterday = date('Y-m-d', strtotime('-1 day'));
try {
    $stmtAtt = $pdo->prepare("SELECT id FROM attendance WHERE clock_out IS NULL AND date <= ?");
    $stmtAtt->execute([$yesterday]);
    $missedOut = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($missedOut as $att) {
        $pdo->exec("UPDATE attendance SET status = 'Missed Clock Out' WHERE id = " . $att['id']);
    }
    echo "    -> Auto-closed " . count($missedOut) . " forgotten attendances.\n\n";
} catch (Exception $e) {
    echo "    -> Attendance Cron Skip: " . $e->getMessage() . "\n\n";
}

// -----------------------------------------------------
// 3. Invoice Overdue Marking
// -----------------------------------------------------
echo "[3] Processing Overdue Invoices...\n";
try {
    $stmtInv = $pdo->prepare("SELECT id FROM invoices WHERE status = 'Unpaid' AND date(due_date) < ?");
    $stmtInv->execute([$today]);
    $overdueInvoices = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
    foreach ($overdueInvoices as $inv) {
        $pdo->exec("UPDATE invoices SET status = 'Overdue' WHERE id = " . $inv['id']);
    }
    echo "    -> Marked " . count($overdueInvoices) . " invoices as Overdue.\n\n";
} catch (Exception $e) {
    echo "    -> Invoice Cron Skip: " . $e->getMessage() . "\n\n";
}

// -----------------------------------------------------
// 4. Contract/Lead Follow-up Reminders
// -----------------------------------------------------
echo "[4] Processing CRM Follow-ups...\n";
try {
    $stmtCrm = $pdo->prepare("SELECT * FROM crm_leads WHERE follow_up_date = ? AND stage NOT IN ('Won', 'Lost')");
    $stmtCrm->execute([$today]);
    $pendingLeads = $stmtCrm->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pendingLeads as $lead) {
        $msg = "CRM Reminder: Follow up scheduled today for Lead '{$lead['lead_name']}' ({$lead['company']}).";
        createNotification($pdo, $lead['owner_id'], 'CRM Follow Up', $msg, 'crm.php');
    }
    echo "    -> Triggered " . count($pendingLeads) . " CRM reminders.\n\n";
} catch (Exception $e) {
    echo "    -> CRM Cron Skip: " . $e->getMessage() . "\n\n";
}


// -----------------------------------------------------
// 5. LMS Compliance & Certificate Expirations
// -----------------------------------------------------
echo "[5] Processing LMS Compliance Expirations...\n";
try {
    $stmtExp = $pdo->prepare("SELECT ta.id, ta.user_id, c.title FROM training_assignments ta JOIN training_courses c ON ta.course_id = c.id WHERE ta.status = 'Completed' AND ta.expires_at IS NOT NULL AND date(ta.expires_at) < ?");
    $stmtExp->execute([$today]);
    $expiredRecords = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
    foreach ($expiredRecords as $ex) {
        $pdo->prepare("UPDATE training_assignments SET status='Assigned', user_answers=NULL, expires_at=NULL WHERE id=?")->execute([$ex['id']]);
        $msg = "COMPLIANCE ALERT: Your certification for '{$ex['title']}' has expired today. You must retake this corporate module immediately.";
        createNotification($pdo, $ex['user_id'], 'Certificate Expired', $msg, 'training.php');
    }
    echo "    -> Processed " . count($expiredRecords) . " LMS expirations.\n\n";
} catch (Exception $e) {
    echo "    -> LMS Cron Skip: " . $e->getMessage() . "\n\n";
}

// -----------------------------------------------------
// 6. Helpdesk SLA Escalation Engine
// -----------------------------------------------------
echo "[6] Processing Helpdesk SLA Escalations...\n";
try {
    $openTickets = $pdo->query("SELECT * FROM unified_tickets WHERE status = 'Open'")->fetchAll(PDO::FETCH_ASSOC);
    $escalatedCount = 0;

    foreach ($openTickets as $ticket) {
        $createdTime = strtotime($ticket['created_at']);
        $hoursOpen = (time() - $createdTime) / 3600;
        
        $slaHours = 48; // Default Medium priority
        if ($ticket['priority'] === 'Urgent') $slaHours = 12;
        if ($ticket['priority'] === 'High')   $slaHours = 24;
        
        if ($hoursOpen >= $slaHours) {
            $pdo->prepare("UPDATE unified_tickets SET status = 'Escalated' WHERE id = ?")->execute([$ticket['id']]);
            $escalatedCount++;
            
            $admins = $pdo->query("SELECT email FROM users WHERE role IN ('Admin', 'Super Admin') AND email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminEmail) {
                $sub = "SLA BREACH ALERT: Ticket #{$ticket['ticket_number']} Escalated";
                $body = "<h3 style='color:#ef4444;'>Helpdesk SLA Escalation Warning</h3>
                         <p>Ticket <strong>#{$ticket['ticket_number']}</strong> ({$ticket['subject']}) has breached its SLA resolution target of {$slaHours} hours.</p>
                         <p><strong>Status:</strong> Escalated to Management</p>";
                sendSystemEmail($adminEmail, $sub, $body);
            }
        }
    }
    echo "    -> Escalated " . $escalatedCount . " SLA-breached tickets.\n\n";
} catch (Exception $e) {
    echo "    -> Helpdesk SLA Cron Skip: " . $e->getMessage() . "\n\n";
}

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
// -----------------------------------------------------
// 7. Inventory Expiry (<30 Days) & Low Stock Alerts
// -----------------------------------------------------
echo "[7] Processing Inventory Expiry & Low Stock Alerts...\n";
try {
    $expiring30 = date('Y-m-d', strtotime('+30 days'));
    $stmtInvExp = $pdo->prepare("SELECT * FROM inventory_items WHERE expiry_date IS NOT NULL AND expiry_date != '' AND expiry_date <= ?");
    $stmtInvExp->execute([$expiring30]);
    $expItems = $stmtInvExp->fetchAll(PDO::FETCH_ASSOC);

    $admins = $pdo->query("SELECT login_id, email FROM users WHERE role IN ('Admin', 'Super Admin')")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expItems as $ei) {
        $msg = "⚠️ INVENTORY ALERT: Medicine '{$ei['name']}' (Batch: {$ei['batch_number']}) is expiring soon on {$ei['expiry_date']}!";
        foreach ($admins as $ad) {
            createNotification($pdo, $ad['login_id'], 'Medicine Expiry Warning', $msg, 'inventory.php');
        }
    }
    echo "    -> Processed " . count($expItems) . " expiring inventory items.\n\n";
} catch (Exception $e) {
    echo "    -> Inventory Cron Skip: " . $e->getMessage() . "\n\n";
}

// -----------------------------------------------------
// 8. Automated Sales Drip Campaign Step Execution
// -----------------------------------------------------
echo "[8] Processing Sales Drip Sequences...\n";
try {
    $stmtDrips = $pdo->query("SELECT * FROM crm_drip_sequences");
    $drips = $stmtDrips->fetchAll(PDO::FETCH_ASSOC);
    $dripExecCount = 0;

    foreach ($drips as $d) {
        $stmtSteps = $pdo->prepare("SELECT * FROM crm_drip_steps WHERE sequence_id = ? ORDER BY step_number ASC");
        $stmtSteps->execute([$d['id']]);
        $steps = $stmtSteps->fetchAll(PDO::FETCH_ASSOC);

        if (empty($steps)) continue;

        $stmtLeads = $pdo->prepare("SELECT * FROM crm_leads WHERE stage = ?");
        $stmtLeads->execute([$d['trigger_stage']]);
        $targetLeads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

        foreach ($targetLeads as $l) {
            foreach ($steps as $st) {
                if (!empty($l['email'])) {
                    $sub = strtr($st['subject'] ?: 'Special Offer', ['{{name}}' => $l['lead_name'], '{{company}}' => $l['company']]);
                    $body = strtr($st['body'], ['{{name}}' => $l['lead_name'], '{{company}}' => $l['company']]);
                    sendSystemEmail($l['email'], $sub, $body);
                    $dripExecCount++;
                }
            }
        }
    }
    echo "    -> Dispatched " . $dripExecCount . " drip sequence messages.\n\n";
} catch (Exception $e) {
    echo "    -> Drip Cron Skip: " . $e->getMessage() . "\n\n";
}

// -----------------------------------------------------
// 9. Monthly Leave Accrual (1st of the month)
// -----------------------------------------------------
echo "[9] Processing Monthly Leave Accruals...\n";
try {
    if (date('j') === '1' || isset($_GET['force_accrual'])) {
        $users = $pdo->query("SELECT login_id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $accrualCount = 0;
        foreach ($users as $uid) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS leave_balances (user_id VARCHAR(255) PRIMARY KEY, casual_leave DECIMAL(5,1) DEFAULT 12, sick_leave DECIMAL(5,1) DEFAULT 12, earned_leave DECIMAL(5,1) DEFAULT 15)");
            $stmtAcc = $pdo->prepare("INSERT INTO leave_balances (user_id, casual_leave, sick_leave, earned_leave) VALUES (?, 13.5, 13.0, 16.0) ON CONFLICT(user_id) DO UPDATE SET casual_leave = casual_leave + 1.5, sick_leave = sick_leave + 1.0");
            $stmtAcc->execute([$uid]);
            $accrualCount++;
        }
        echo "    -> Credited monthly leave allocations to " . $accrualCount . " employees.\n\n";
    } else {
        echo "    -> Skipped (Runs automatically on 1st of month).\n\n";
    }
} catch (Exception $e) {
    echo "    -> Leave Accrual Skip: " . $e->getMessage() . "\n\n";
}

// -----------------------------------------------------
// 10. Recurring Invoices Auto-Generation
// -----------------------------------------------------
echo "[10] Processing Recurring Invoices...\n";
try {
    require_once __DIR__ . '/controllers/generate_recurring_invoices.php';
} catch (Exception $e) {
    echo "    -> Recurring Invoices Skip: " . $e->getMessage() . "\n\n";
}

echo "========================================\n";
echo "CRON Execution Complete.\n";
echo "========================================\n";
?>

