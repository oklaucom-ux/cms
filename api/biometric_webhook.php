<?php
// api/biometric_webhook.php - Biometric Device & Mobile GPS Attendance Ingestion API
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST']);
    exit;
}

// Secret Token Authentication
$apiKey = $_SERVER['HTTP_X_BIOMETRIC_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$expectedKey = $GLOBAL_SETTINGS['biometric_api_key'] ?? 'BiometricSecretKey2026';

if ($apiKey !== $expectedKey && !empty($expectedKey)) {
    // If not matching global setting, check api_keys table
    try {
        $stmtKey = $pdo->prepare("SELECT id FROM api_keys WHERE api_key = ?");
        $stmtKey->execute([$apiKey]);
        if (!$stmtKey->fetchColumn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Invalid Biometric API Key']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized Key']);
        exit;
    }
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$userId   = $data['user_id'] ?? $data['employee_id'] ?? $data['card_number'] ?? '';
$punchType = strtolower($data['punch_type'] ?? $data['type'] ?? 'checkin'); // checkin or checkout
$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
$device    = $data['device_name'] ?? $data['device_id'] ?? 'Biometric Scanner';
$lat       = $data['latitude'] ?? null;
$lng       = $data['longitude'] ?? null;

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id parameter']);
    exit;
}

$today = date('Y-m-d', strtotime($timestamp));
$time  = date('H:i:s', strtotime($timestamp));

try {
    // Ensure attendance tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        clock_in DATETIME,
        clock_out DATETIME,
        status VARCHAR(50) DEFAULT 'Present',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS time_punches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id VARCHAR(255) NOT NULL,
        punch_type VARCHAR(50),
        punch_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        device VARCHAR(100),
        latitude VARCHAR(50),
        longitude VARCHAR(50)
    )");

    try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN punch_time DATETIME"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN device VARCHAR(100)"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN latitude VARCHAR(50)"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN longitude VARCHAR(50)"); } catch (Exception $e) {}

    // Log raw punch
    try {
        $stmtPunch = $pdo->prepare("INSERT INTO time_punches (user_id, punch_type, punch_time, device, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtPunch->execute([$userId, $punchType, $timestamp, $device, $lat, $lng]);
    } catch (Exception $e) {
        try {
            $stmtPunch = $pdo->prepare("INSERT INTO time_punches (user_id, punch_type, device) VALUES (?, ?, ?)");
            $stmtPunch->execute([$userId, $punchType, $device]);
        } catch (Exception $ex) {}
    }

    // Check existing attendance for today
    $stmtExist = $pdo->prepare("SELECT id, clock_in FROM attendance WHERE user_id = ? AND date = ?");
    $stmtExist->execute([$userId, $today]);
    $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($punchType === 'checkout' || (in_array($punchType, ['checkin', 'in']) && !empty($existing['clock_in']))) {
            $stmtUp = $pdo->prepare("UPDATE attendance SET clock_out = ?, status = 'Present' WHERE id = ?");
            $stmtUp->execute([$timestamp, $existing['id']]);
            $action = 'clock_out_updated';
        } else {
            $action = 'already_clocked_in';
        }
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in, status) VALUES (?, ?, ?, 'Present')");
        $stmtIns->execute([$userId, $today, $timestamp]);
        $action = 'clock_in_created';
    }

    echo json_encode([
        'success'   => true,
        'action'    => $action,
        'user_id'   => $userId,
        'timestamp' => $timestamp,
        'device'    => $device
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
