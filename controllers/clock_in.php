<?php
// controllers/clock_in.php - Clock-In / Clock-Out Handler
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $punch_type = strtolower(trim($_POST['punch_type'] ?? ''));
    $lat = $_POST['lat'] ?? $_POST['latitude'] ?? null;
    $lng = $_POST['lng'] ?? $_POST['longitude'] ?? null;

    if (!in_array($punch_type, ['clock_in', 'clock_out', 'checkin', 'checkout'])) {
        header("Location: ../timesheets.php?error=Invalid+action");
        exit();
    }

    // Normalize punch type
    if ($punch_type === 'checkin') $punch_type = 'clock_in';
    if ($punch_type === 'checkout') $punch_type = 'clock_out';

    // Auto-migrate time_punches table
    try {
        $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
        $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

        $pdo->exec("CREATE TABLE IF NOT EXISTS time_punches (
            id {$pkDef},
            user_id VARCHAR(255) NOT NULL,
            punch_type VARCHAR(50) NOT NULL,
            lat DECIMAL(10,8),
            lng DECIMAL(11,8),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN lat DECIMAL(10,8)"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN lng DECIMAL(11,8)"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN latitude DECIMAL(10,8)"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE time_punches ADD COLUMN longitude DECIMAL(11,8)"); } catch(Exception $e){}
    } catch (Exception $e) {}

    // Settings for Geofence
    $settings = [];
    try {
        foreach($pdo->query("SELECT * FROM settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}

    $geoEnabled = ($settings['geo_fence_enabled'] ?? 'false') === 'true';

    if ($geoEnabled) {
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            header("Location: ../timesheets.php?error=Location+required+for+geo-fencing.");
            exit();
        }

        $hqLat = (float) ($settings['geo_lat'] ?? 0);
        $hqLng = (float) ($settings['geo_lng'] ?? 0);
        $radius = (float) ($settings['geo_radius'] ?? 500);

        // Haversine formula
        $earthRadius = 6371000; // meters
        $dLat = deg2rad((float)$lat - $hqLat);
        $dLng = deg2rad((float)$lng - $hqLng);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($hqLat)) * cos(deg2rad((float)$lat)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        if ($distance > $radius) {
            header("Location: ../timesheets.php?error=You+are+too+far+from+HQ+(" . round($distance) . "m).+Max+radius+is+{$radius}m.");
            exit();
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO time_punches (user_id, punch_type, lat, lng, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['login_id'], $punch_type, $lat, $lng, $lat, $lng]);
    } catch (Exception $e) {
        // Fallback insert if column names vary
        try {
            $stmt = $pdo->prepare("INSERT INTO time_punches (user_id, punch_type) VALUES (?, ?)");
            $stmt->execute([$_SESSION['login_id'], $punch_type]);
        } catch (Exception $ex) {}
    }

    try {
        $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['login_id'], 'Time Punch', ucfirst(str_replace('_', ' ', $punch_type))]);
    } catch (Exception $e) {}

    header("Location: ../timesheets.php?success=Successfully+" . str_replace('_', '+', $punch_type) . "ed");
    exit();
}
