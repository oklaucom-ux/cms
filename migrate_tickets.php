<?php
require_once 'includes/db.php';

echo "Starting Ticketing Unification Migration...\n";

// 1. Create unified_tickets table
$pdo->exec("CREATE TABLE IF NOT EXISTS unified_tickets (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    source TEXT NOT NULL, 
    ticket_number TEXT,
    requester_id TEXT,
    requester_name TEXT,
    department TEXT,
    subject TEXT NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(255) DEFAULT 'Medium',
    status VARCHAR(255) DEFAULT 'Open',
    assigned_agent_id TEXT,
    resolution_notes TEXT,
    is_anonymous INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

echo "Table unified_tickets created.\n";

$migrated = 0;

// 2. Migrate support_tickets (Client Support)
try {
    $clientTickets = $pdo->query("SELECT * FROM support_tickets")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, requester_name, subject, description, priority, status, assigned_agent_id, created_at, updated_at) VALUES ('Client_Support', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach($clientTickets as $t) {
        $stmt->execute([
            $t['ticket_number'],
            $t['client_id'],
            $t['client_name'],
            $t['subject'],
            'Client Support Request', // support_tickets didn't have a 'description' field directly, it relied on ticket_replies for initial message
            $t['priority'] ?? 'Medium',
            $t['status'],
            $t['assigned_agent_id'],
            $t['created_at'],
            $t['updated_at']
        ]);
        $migrated++;
    }
    echo "Migrated Client Support Tickets.\n";
} catch(Exception $e) { echo "Error migrating support_tickets: " . $e->getMessage() . "\n"; }

// 3. Migrate helpdesk_tickets (Internal IT)
try {
    $helpdeskTickets = $pdo->query("SELECT * FROM helpdesk_tickets")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, department, subject, description, priority, status, assigned_agent_id, resolution_notes, created_at, updated_at) VALUES ('IT_Helpdesk', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach($helpdeskTickets as $t) {
        // generate a pseudo ticket number
        $tnum = 'HD-' . str_pad($t['id'], 4, '0', STR_PAD_LEFT);
        
        $stmt->execute([
            $tnum,
            $t['user_id'],
            $t['department'],
            $t['subject'],
            $t['description'],
            $t['priority'] ?? 'Medium',
            $t['status'],
            $t['assigned_to'],
            $t['resolution_notes'],
            $t['created_at'],
            $t['resolved_at'] ?? $t['created_at']
        ]);
        $migrated++;
    }
    echo "Migrated IT Helpdesk Tickets.\n";
} catch(Exception $e) { echo "Error migrating helpdesk_tickets: " . $e->getMessage() . "\n"; }

// 4. Migrate feedback (Anonymous / General)
try {
    $feedbackTickets = $pdo->query("SELECT * FROM feedback")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT INTO unified_tickets (source, ticket_number, requester_id, department, subject, description, status, is_anonymous, created_at, updated_at) VALUES ('Feedback', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach($feedbackTickets as $t) {
        $tnum = 'FB-' . str_pad($t['id'], 4, '0', STR_PAD_LEFT);
        
        $stmt->execute([
            $tnum,
            $t['submitted_by'],
            $t['type'], // mapped type to department visually
            $t['subject'],
            $t['details'],
            $t['status'],
            $t['is_anonymous'] ?? 0,
            $t['submitted_at'],
            $t['submitted_at']
        ]);
        $migrated++;
    }
    echo "Migrated Feedback Tickets.\n";
} catch(Exception $e) { echo "Error migrating feedback: " . $e->getMessage() . "\n"; }

echo "Successfully migrated $migrated total tickets into unified_tickets matrix.\n";
