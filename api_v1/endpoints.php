<?php
/**
 * Enterprise REST API v1 — Pabbly Connect Compatible
 * Authentication: Bearer Token (API Key from user profile)
 * Routing: ?resource=<name>&action=<action>
 *
 * GET  ?resource=projects
 * GET  ?resource=leads
 * GET  ?resource=leads&stage=Prospect
 * POST ?resource=leads                 { lead_name, email, phone, company, value, source }
 * GET  ?resource=employees
 * GET  ?resource=attendance
 * POST ?resource=attendance_log        { date }
 * GET  ?resource=expenses
 * GET  ?resource=leaves
 * GET  ?resource=kpi
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/db.php';

// ── Authentication ────────────────────────────────────────────────────────────
function apiError($code, $message) {
    http_response_code($code);
    echo json_encode(["status" => "error", "error" =>$message]);
    exit;
}

function apiSuccess($data, $meta = []) {
    $response = ["status" => "success", "data" =>$data];
    if (!empty($meta)) $response = array_merge($response, $meta);
    echo json_encode($response);
    exit;
}

$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
// Fallback for nginx/CGI environments
if (empty($headers)) {
    foreach ($_SERVER as $k =>$v) {
        if (substr($k, 0, 5) === 'HTTP_') {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$key] = $v;
        }
    }
}

$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$api_key = trim(str_replace('Bearer', '', $authHeader));

if (empty($api_key)) {
    // Also accept via query string for Pabbly webhook testing
    $api_key = $_GET['api_key'] ?? '';
}

if (empty($api_key)) {
    apiError(401, "Unauthorized. Provide Bearer token in Authorization header.");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ? AND status = 'Active'");
$stmt->execute([$api_key]);
$apiUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$apiUser) {
    apiError(403, "Forbidden. Invalid or revoked API Key.");
}

$isAdmin  = ($apiUser['role'] === 'Admin' || $apiUser['role'] === 'Manager');
$myId     = $apiUser['login_id'];
$resource = strtolower(trim($_GET['resource'] ?? ''));
$method   = $_SERVER['REQUEST_METHOD'];

// ── GET Endpoints ─────────────────────────────────────────────────────────────
if ($method === 'GET') {

    switch ($resource) {

        // ── Projects ──────────────────────────────────────────────────────────
        case 'projects':
            if ($isAdmin) {
                $data = $pdo->query("SELECT p.id, p.name, p.status, p.budget, p.client, p.start_date, p.end_date FROM projects p ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $s = $pdo->prepare("SELECT p.id, p.name, p.status, p.budget, p.client, p.start_date, p.end_date FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ?");
                $s->execute([$myId]);
                $data = $s->fetchAll(PDO::FETCH_ASSOC);
            }
            apiSuccess($data, ["count" => count($data)]);

        // ── CRM Leads ─────────────────────────────────────────────────────────
        case 'leads':
            if (!$isAdmin) apiError(403, "Insufficient permissions to access CRM leads.");
            $stage = $_GET['stage'] ?? null;
            if ($stage) {
                $s = $pdo->prepare("SELECT l.*, u.name AS assigned_rep FROM crm_leads l LEFT JOIN users u ON l.assigned_to = u.login_id WHERE l.stage = ? ORDER BY l.created_at DESC");
                $s->execute([$stage]);
            } else {
                $s = $pdo->query("SELECT l.*, u.name AS assigned_rep FROM crm_leads l LEFT JOIN users u ON l.assigned_to = u.login_id ORDER BY l.created_at DESC");
            }
            $data = $s->fetchAll(PDO::FETCH_ASSOC);
            apiSuccess($data, ["count" => count($data)]);

        // ── Employees ─────────────────────────────────────────────────────────
        case 'employees':
            if (!$isAdmin) apiError(403, "Insufficient permissions to view employee list.");
            $data = $pdo->query("SELECT login_id, name, email, role, department, status, created_at FROM users WHERE status = 'Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            apiSuccess($data, ["count" => count($data)]);

        // ── Attendance ────────────────────────────────────────────────────────
        case 'attendance':
            $limit = min(intval($_GET['limit'] ?? 30), 200);
            if ($isAdmin) {
                $s = $pdo->prepare("SELECT a.*, u.name FROM attendance a JOIN users u ON a.user_id = u.login_id ORDER BY a.date DESC LIMIT ?");
                $s->execute([$limit]);
            } else {
                $s = $pdo->prepare("SELECT date, clock_in, clock_out, status FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT ?");
                $s->execute([$myId, $limit]);
            }
            $data = $s->fetchAll(PDO::FETCH_ASSOC);
            apiSuccess($data, ["count" => count($data)]);

        // ── Expenses ─────────────────────────────────────────────────────────
        case 'expenses':
            if (!$isAdmin) apiError(403, "Insufficient permissions to access expenses.");
            $status_filter = $_GET['status'] ?? null;
            if ($status_filter) {
                $s = $pdo->prepare("SELECT e.*, p.name AS project_name FROM expenses e LEFT JOIN projects p ON e.project_id = p.id WHERE e.status = ? ORDER BY e.created_at DESC");
                $s->execute([$status_filter]);
            } else {
                $s = $pdo->query("SELECT e.*, p.name AS project_name FROM expenses e LEFT JOIN projects p ON e.project_id = p.id ORDER BY e.created_at DESC");
            }
            $data = $s->fetchAll(PDO::FETCH_ASSOC);
            apiSuccess($data, ["count" => count($data)]);

        // ── Leaves ───────────────────────────────────────────────────────────
        case 'leaves':
            $status_filter = $_GET['status'] ?? null;
            if ($isAdmin) {
                if ($status_filter) {
                    $s = $pdo->prepare("SELECT l.*, u.name AS employee_name FROM leaves l JOIN users u ON l.user_id = u.login_id WHERE l.status = ? ORDER BY l.created_at DESC");
                    $s->execute([$status_filter]);
                } else {
                    $s = $pdo->query("SELECT l.*, u.name AS employee_name FROM leaves l JOIN users u ON l.user_id = u.login_id ORDER BY l.created_at DESC");
                }
            } else {
                $s = $pdo->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC");
                $s->execute([$myId]);
            }
            $data = $s->fetchAll(PDO::FETCH_ASSOC);
            apiSuccess($data, ["count" => count($data)]);

        // ── KPI Targets ───────────────────────────────────────────────────────
        case 'kpi':
            if ($isAdmin) {
                $data = $pdo->query("SELECT k.*, u.name AS employee_name, u.department FROM kpi_targets k JOIN users u ON k.user_id = u.login_id ORDER BY k.deadline ASC")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $s = $pdo->prepare("SELECT * FROM kpi_targets WHERE user_id = ? ORDER BY deadline ASC");
                $s->execute([$myId]);
                $data = $s->fetchAll(PDO::FETCH_ASSOC);
            }
            apiSuccess($data, ["count" => count($data)]);

        // ── Payroll ───────────────────────────────────────────────────────────
        case 'payroll':
            if (!$isAdmin) apiError(403, "Insufficient permissions to access payroll data.");
            $period = $_GET['period'] ?? null;
            if ($period) {
                $s = $pdo->prepare("SELECT pr.*, u.name, u.department FROM payroll_runs pr JOIN users u ON pr.user_id = u.login_id WHERE pr.period = ? ORDER BY u.name");
                $s->execute([$period]);
            } else {
                $s = $pdo->query("SELECT pr.*, u.name, u.department FROM payroll_runs pr JOIN users u ON pr.user_id = u.login_id ORDER BY pr.period DESC, u.name LIMIT 200");
            }
            $data = $s->fetchAll(PDO::FETCH_ASSOC);
            apiSuccess($data, ["count" => count($data)]);

        // ── API Self-Test / Ping ───────────────────────────────────────────────
        case 'ping':
        case 'me':
            apiSuccess([
                "user"      =>$apiUser['name'],
                "login_id"  =>$myId,
                "role"      =>$apiUser['role'],
                "timestamp" => date('c'),
                "available_resources" => ["projects", "leads", "employees", "attendance", "expenses", "leaves", "kpi", "payroll"]
            ]);

        default:
            apiError(404, "Endpoint '{$resource}' not found. Use ?resource=ping to test connection.");
    }
}

// ── POST Endpoints ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    switch ($resource) {

        // ── Create CRM Lead (Pabbly → CMS) ────────────────────────────────────
        case 'leads':
            if (!$isAdmin) apiError(403, "Insufficient permissions to create leads.");
            $lead_name = trim($input['lead_name'] ?? $input['name'] ?? '');
            $email     = trim($input['email'] ?? '');
            if (empty($lead_name)) apiError(400, "lead_name is required.");

            $s = $pdo->prepare("INSERT INTO crm_leads (lead_name, email, phone, company, value, stage, source, assigned_to, created_at) VALUES (?,?,?,?,?,'New',?,?,CURRENT_TIMESTAMP)");
            $s->execute([
                $lead_name,
                $email,
                $input['phone']   ?? '',
                $input['company'] ?? '',
                floatval($input['value'] ?? 0),
                $input['source']  ?? 'API / Pabbly',
                $input['assigned_to'] ?? $myId
            ]);
            $newId = $pdo->lastInsertId();
            $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$myId}', 'API Create Lead', 'Created lead \"{$lead_name}\" via REST API / Pabbly Connect')");
            apiSuccess(["id" =>$newId, "lead_name" =>$lead_name, "message" => "Lead created successfully."]);

        // ── Log Attendance via API ────────────────────────────────────────────
        case 'attendance_log':
            $date = $input['date'] ?? date('Y-m-d');
            $exists = $pdo->prepare("SELECT id FROM attendance WHERE user_id=? AND date=?");
            $exists->execute([$myId, $date]);
            if ($exists->fetchColumn()) apiError(409, "Attendance already logged for {$date}.");

            $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in, status, latitude, longitude) VALUES (?,?,?,'Present','API','API')")
                ->execute([$myId, $date, date('Y-m-d H:i:s')]);
            apiSuccess(["message" => "Clock-in logged for {$date} via API."]);

        // ── Create Leave Request via API ──────────────────────────────────────
        case 'leaves':
            $start  = $input['start_date'] ?? '';
            $end    = $input['end_date']   ?? '';
            $type   = $input['leave_type'] ?? 'Annual Leave';
            $reason = $input['reason']     ?? 'Submitted via API';
            if (empty($start) || empty($end)) apiError(400, "start_date and end_date are required.");

            $pdo->prepare("INSERT INTO leaves (user_id, start_date, end_date, leave_type, reason, status) VALUES (?,?,?,?,?,'Pending')")
                ->execute([$myId, $start, $end, $type, $reason]);
            apiSuccess(["message" => "Leave request submitted for {$start} to {$end}."]);

        default:
            apiError(404, "POST endpoint '{$resource}' not found.");
    }
}

apiError(405, "HTTP method {$method} is not supported.");
?>
