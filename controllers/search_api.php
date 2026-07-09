<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['login_id'])) { echo '{}'; exit; }

$q = '%' . trim($_GET['q'] ?? '') . '%';
$results = ['total' => 0];

// Tasks
try {
    $s = $pdo->prepare("SELECT name as title, assigned_to as meta FROM tasks WHERE (name LIKE ? OR description LIKE ? OR assigned_to LIKE ?) AND status != 'Deleted' LIMIT 5");
    $s->execute([$q,$q,$q]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $results['tasks'] = $rows; $results['total'] += count($rows); }
} catch(Exception $e){}

// Users
try {
    $s = $pdo->prepare("SELECT name as title, login_id as meta FROM users WHERE name LIKE ? OR login_id LIKE ? OR email LIKE ? LIMIT 5");
    $s->execute([$q,$q,$q]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $results['users'] = $rows; $results['total'] += count($rows); }
} catch(Exception $e){}

// Projects
try {
    $s = $pdo->prepare("SELECT name as title, client as meta FROM projects WHERE name LIKE ? OR client LIKE ? LIMIT 5");
    $s->execute([$q,$q]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $results['projects'] = $rows; $results['total'] += count($rows); }
} catch(Exception $e){}

// CRM Leads
try {
    $s = $pdo->prepare("SELECT lead_name as title, company as meta FROM crm_leads WHERE lead_name LIKE ? OR company LIKE ? OR email LIKE ? LIMIT 5");
    $s->execute([$q,$q,$q]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $results['leads'] = $rows; $results['total'] += count($rows); }
} catch(Exception $e){}

// Assets
try {
    $s = $pdo->prepare("SELECT name as title, asset_tag as meta FROM assets WHERE name LIKE ? OR asset_tag LIKE ? OR assigned_to LIKE ? LIMIT 5");
    $s->execute([$q,$q,$q]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $results['assets'] = $rows; $results['total'] += count($rows); }
} catch(Exception $e){}

// Documents
try {
    $s = $pdo->prepare("SELECT title, category as meta FROM documents WHERE title LIKE ? OR category LIKE ? LIMIT 5");
    $s->execute([$q,$q]); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $results['documents'] = $rows; $results['total'] += count($rows); }
} catch(Exception $e){}

echo json_encode($results);
