<?php
// controllers/submit_public_form.php
require_once '../includes/db.php';

// Fully permit Cross-Origin Resource Sharing (CORS) to allow embedding onto external customer domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight checks
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_id = intval($_POST['form_id'] ?? 0);
    $custom_data = $_POST['custom_data'] ?? [];

    if ($form_id > 0 && !empty($custom_data)) {
        $stmt = $pdo->prepare("SELECT is_public FROM dynamic_forms WHERE id = ?");
        $stmt->execute([$form_id]);
        $is_public = $stmt->fetchColumn();

        if ($is_public == 1) {
            $json_payload = json_encode($custom_data);
            $user_id = "Guest / External Lead";
            
            $insert = $pdo->prepare("INSERT INTO form_submissions (form_id, user_id, data_json) VALUES (?, ?, ?)");
            $insert->execute([$form_id, $user_id, $json_payload]);
            
            echo "<script>alert('Submission successful! We have securely collected your information.'); window.location.href='../embed_form.php?id={$form_id}&success=1';</script>";
            exit;
        } else {
            http_response_code(403);
            echo "<script>alert('SECURITY VIOLATION: This form is strictly set to Internal-Only.'); window.history.back();</script>";
            exit;
        }
    }
}
http_response_code(400);
echo "<script>alert('Invalid Data format detected.'); window.history.back();</script>";
exit;
