<?php
require 'includes/db.php';

$aiUrlStr = trim($pdo->query("SELECT setting_value FROM settings WHERE setting_key='local_ai_url'")->fetchColumn() ?: 'http://127.0.0.1:8080');
$baseUrl = rtrim($aiUrlStr, '/');
$apiUrl = $baseUrl . "/v1/chat/completions";

echo "Testing URL: $apiUrl\n";

$data = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "user", "content" => "Hello"] 
    ],
    "temperature" => 0.3,
    "max_tokens" => 50
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer local"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: $error\n";
echo "Response: $response\n";
