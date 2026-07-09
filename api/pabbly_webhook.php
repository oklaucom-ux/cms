<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json');

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST requests are accepted"]);
    exit;
}

// 1. Authenticate via Bearer Token or GET api_key
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$apiKey = '';

if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $apiKey = $matches[1];
} elseif (isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Missing authentication token"]);
    exit;
}

// Verify API Key
$stmtKey = $pdo->prepare("SELECT user_id FROM api_keys WHERE api_key = ?");
$stmtKey->execute([$apiKey]);
$ownerId = $stmtKey->fetchColumn();

if (!$ownerId) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API Key"]);
    exit;
}

// 2. Parse Incoming Payload (JSON or Form URL Encoded)
$rawPost = file_get_contents('php://input');
$payload = json_decode($rawPost, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $payload = $_POST; // Fallback to form data
}

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Empty payload"]);
    exit;
}

// 3. Fuzzy Field Matching
$leadName = '';
$email = '';
$phone = '';
$company = '';

// Helper for fuzzy search
function findField(&$payload, $possibleKeys) {
    foreach ($possibleKeys as $pk) {
        foreach ($payload as $key =>$value) {
            if (stripos(str_replace([' ', '_', '-'], '', $key), $pk) !== false) {
                $val = $value;
                unset($payload[$key]); // Remove from custom data payload
                return $val;
            }
        }
    }
    return '';
}

// Extract core fields and automatically remove them from $payload to leave only custom_data behind
$firstName = findField($payload, ['firstname', 'first']);
$lastName = findField($payload, ['lastname', 'last', 'surname']);
if ($firstName && $lastName) {
    $leadName = trim("$firstName $lastName");
} else {
    $leadName = findField($payload, ['name', 'leadname', 'contactname', 'fullname', 'person']);
}

$email = findField($payload, ['email', 'mail', 'contactemail']);
$phone = findField($payload, ['phone', 'mobile', 'cell', 'contactnumber', 'telephone']);
$company = findField($payload, ['company', 'organization', 'business', 'employer']);

// Fallbacks
if (empty($leadName)) {
    $leadName = $email ?: 'Unknown Pabbly Lead';
}

$customDataJson = json_encode($payload);
$source = "Pabbly Connect Webhook";

// 4. Insert Lead into CRM
try {
    $stmt = $pdo->prepare("INSERT INTO crm_leads (lead_name, company, email, phone, stage, owner_id, source, custom_data) VALUES (?, ?, ?, ?, 'Prospect', ?, ?, ?)");
    $stmt->execute([
        $leadName,
        $company,
        $email,
        $phone,
        $ownerId,
        $source,
        $customDataJson
    ]);
    
    $leadId = $pdo->lastInsertId();
    
    // Log API Activity
    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, 'Webhook Ingestion', ?)")->execute([
        $ownerId, 
        "Ingested lead '{$leadName}' via Pabbly Webhook."
    ]);
    
    // Attempt to notify Owner
    createNotification($pdo, $ownerId, 'New Lead Arrived', "An automated lead '{$leadName}' just landed in your pipeline from Pabbly.", 'crm.php');
    
    echo json_encode(["status" => "success", "message" => "Lead created successfully", "lead_id" =>$leadId]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
