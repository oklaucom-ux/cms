<?php
// api/index.php — REST API Router
// Auth: pass X-API-Key header or ?api_key= param
// All responses: JSON

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../includes/db.php';

// ── API Key Auth (stored in settings table) ────────────────────────────────────
function apiAuth($pdo) {
    global $GLOBAL_SETTINGS;
    $key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $valid = $GLOBAL_SETTINGS['api_key'] ?? null;
    if (!$valid) {
        // Auto-generate key if not set
        $generated = bin2hex(random_bytes(24));
        $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES ('api_key', ?)")->execute([$generated]);
        apiError(401, 'API key not configured. A new key has been generated — retrieve it from System Settings.');
    }
    if (!hash_equals($valid, $key)) apiError(401, 'Invalid API key.');
}
function apiError($code, $msg) { http_response_code($code); echo json_encode(['error'=>$msg,'code'=>$code]); exit; }
function apiOk($data, $meta=[]) { echo json_encode(array_merge(['success'=>true,'data'=>$data], $meta)); exit; }
function getBody() { return json_decode(file_get_contents('php://input'), true) ?: []; }

apiAuth($pdo);

// ── Route Dispatch ─────────────────────────────────────────────────────────────
$method  = $_SERVER['REQUEST_METHOD'];
$path    = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$parts   = explode('/', $path);
// Find 'api' segment and take what comes after
$apiIdx  = array_search('api', $parts);
$resource= $parts[$apiIdx+1] ?? '';
$id      = isset($parts[$apiIdx+2]) && is_numeric($parts[$apiIdx+2]) ? intval($parts[$apiIdx+2]) : null;

switch ($resource) {
    case 'users':    require __DIR__ . '/resources/users.php'; break;
    case 'tasks':    require __DIR__ . '/resources/tasks.php'; break;
    case 'projects': require __DIR__ . '/resources/projects.php'; break;
    case 'leads':    require __DIR__ . '/resources/leads.php'; break;
    case 'assets':   require __DIR__ . '/resources/assets.php'; break;
    case 'expenses': require __DIR__ . '/resources/expenses.php'; break;
    case 'leaves':   require __DIR__ . '/resources/leaves.php'; break;
    case 'docs':     apiOk(['api_version'=>'1.0','resources'=>['users','tasks','projects','leads','assets','expenses','leaves'],'auth'=>'X-API-Key header or ?api_key= param','base_url'=>'api/']); break;
    default:         apiError(404, "Resource '{$resource}' not found. GET api/docs for available resources.");
}
