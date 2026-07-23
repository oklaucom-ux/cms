<?php
require 'includes/db.php';
try {
    // 1. Total Headcount
    $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'Deactivated' AND role = 'Employee'");
    echo "1 OK\n";
    // 2. Active Projects
    $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Active'");
    echo "2 OK\n";
    // 3. Support Tickets (Open/In Progress)
    $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Closed'");
    echo "3 OK\n";
    // 4. Pending Onboarding
    $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Pending_Docs'");
    echo "4 OK\n";
    // Data for Chart: Headcount by Department
    $pdo->query("SELECT department, COUNT(*) as count FROM users WHERE status != 'Deactivated' GROUP BY department");
    echo "5 OK\n";
    // Data for Chart: Tickets by Priority
    $pdo->query("SELECT priority, COUNT(*) as count FROM support_tickets WHERE status != 'Closed' GROUP BY priority");
    echo "6 OK\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
