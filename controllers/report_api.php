<?php
session_start();
require_once '../includes/db.php';

// Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(["error" => "Unauthorized"]));
}

$type = $_GET['type'] ?? '';
$data = [];
$columns = [];

try {
    switch ($type) {
        case 'users':
            requirePermission($pdo, 'view_users');
            $columns = ["Login ID", "Name", "Email", "Role", "Department", "Designation", "Status"];
            $stmt = $pdo->query("SELECT login_id, name, email, role, department, designation, status FROM users ORDER BY name ASC");
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            break;

        case 'attendance':
            requirePermission($pdo, 'view_attendance');
            $columns = ["Date", "User", "Clock In", "Clock Out", "Status"];
            $stmt = $pdo->query("
                SELECT a.date, u.name, a.clock_in, a.clock_out, a.status 
                FROM attendance a 
                JOIN users u ON a.user_id = u.login_id 
                ORDER BY a.date DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            break;

        case 'invoices':
            requirePermission($pdo, 'view_invoices');
            $columns = ["Invoice ID", "Client Name", "Amount (₹)", "Issue Date", "Due Date", "Status"];
            $stmt = $pdo->query("SELECT invoice_id, client_name, amount, issue_date, due_date, status FROM invoices ORDER BY issue_date DESC");
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            break;

        case 'crm':
            requirePermission($pdo, 'view_crm');
            $columns = ["Lead Name", "Company", "Email", "Phone", "Assigned To", "Stage", "Value (₹)", "Status", "Created At"];
            $stmt = $pdo->query("SELECT lead_name, company, email, phone, assigned_to, stage, value, status, created_at FROM crm_leads ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            break;

        case 'projects':
            requirePermission($pdo, 'view_projects');
            $columns = ["Project Name", "Client", "Budget (₹)", "Deadline", "Status"];
            $stmt = $pdo->query("SELECT name, client, budget, deadline, status FROM projects ORDER BY created_at DESC");
            $data = $stmt->fetchAll(PDO::FETCH_NUM);
            break;

        default:
            http_response_code(400);
            die(json_encode(["error" => "Invalid report type requested."]));
    }

    echo json_encode(["status" => "success", "columns" =>$columns, "data" =>$data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
