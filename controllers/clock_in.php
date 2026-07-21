<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) die("Unauthorized access.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $punch_type = $_POST['punch_type'] ?? '';
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    if (!in_array($punch_type, ['clock_in', 'clock_out'])) {
        header("Location: ../timesheets.php?error=Invalid action");
        exit;
    }

    // Settings for Geofence
    $settings = [];
    foreach($pdo->query("SELECT * FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $geoEnabled = ($settings['geo_fence_enabled'] ?? 'false') === 'true';

    if ($geoEnabled) {
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            header("Location: ../timesheets.php?error=Location required for geo-fencing.");
            exit;
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
            header("Location: ../timesheets.php?error=You are too far from HQ (" . round($distance) . "m). Max radius is {$radius}m.");
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO time_punches (user_id, punch_type, latitude, longitude) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['login_id'], $punch_type, $lat, $lng]);

    $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['login_id'], 'Time Punch', ucfirst(str_replace('_', ' ', $punch_type))]);

    header("Location: ../timesheets.php?success=Successfully " . str_replace('_', ' ', $punch_type) . "ed");
    exit;
}
