<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'view_crm');
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo '[]'; exit; }
$lead_id = intval($_GET['lead_id'] ?? 0);
$stmt = $pdo->prepare("SELECT *, CASE WHEN (julianday('now')-julianday(logged_at))<1/24.0 THEN round((julianday('now')-julianday(logged_at))*24*60)||' min ago' WHEN (julianday('now')-julianday(logged_at))<1 THEN round((julianday('now')-julianday(logged_at))*24)||'h ago' ELSE round(julianday('now')-julianday(logged_at))||'d ago' END as ago FROM crm_activities WHERE lead_id=? ORDER BY logged_at DESC LIMIT 20");
$stmt->execute([$lead_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
