<?php
// controllers/crm_ai_assistant.php - AI Sales & Lead Intent Scoring Assistant Engine
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$lead_id = (int)($_REQUEST['lead_id'] ?? 0);

if ($lead_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid Lead ID required.']);
    exit();
}

try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_leads (
        id {$pkDef},
        lead_name VARCHAR(255) NOT NULL,
        company VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        status VARCHAR(50) DEFAULT 'New',
        notes TEXT,
        address TEXT,
        lead_score INT DEFAULT 50,
        ai_pitch TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN status VARCHAR(50) DEFAULT 'New'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN notes TEXT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN address TEXT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN lead_score INT DEFAULT 50"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN ai_pitch TEXT"); } catch(Exception $e){}

    // Fetch lead details
    $stmt = $pdo->prepare("SELECT * FROM crm_leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        echo json_encode(['success' => false, 'error' => 'Lead not found.']);
        exit();
    }

    $text = strtolower(($lead['lead_name'] ?? '') . ' ' . ($lead['company'] ?? '') . ' ' . ($lead['email'] ?? '') . ' ' . ($lead['notes'] ?? '') . ' ' . ($lead['address'] ?? ''));

    // Lead Intent Scoring Heuristics
    $score = 40; // Base score
    if (strpos($text, 'urgent') !== false || strpos($text, 'immediately') !== false) $score += 20;
    if (strpos($text, 'price') !== false || strpos($text, 'quote') !== false || strpos($text, 'pricing') !== false) $score += 15;
    if (strpos($text, 'distributor') !== false || strpos($text, 'wholesale') !== false || strpos($text, 'bulk') !== false) $score += 20;
    if (!empty($lead['email']) && !empty($lead['phone'])) $score += 10;
    $score = min(98, max(15, $score));

    // Generate AI Pitch Response
    $clientName = $lead['lead_name'] ?: 'Valued Client';
    $companyName = $lead['company'] ?: 'your company';

    $aiResponse = "Hello {$clientName},\n\nThank you for reaching out to Cyno Pharmaceuticals Ltd. We noticed your interest regarding potential collaboration with {$companyName}.\n\nBased on your requirements, our enterprise sales team would love to present our specialized wholesale catalog, bulk pricing tiers, and credit terms tailored for your business.\n\nCould we schedule a brief 10-minute call tomorrow at 11 AM IST?\n\nBest regards,\n{$_SESSION['login_id']}\nCyno ERP Sales Team";

    // Auto-migrate columns
    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN lead_score INT DEFAULT 50"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE crm_leads ADD COLUMN ai_pitch TEXT"); } catch(Exception $e){}

    // Update lead record
    $stmtUp = $pdo->prepare("UPDATE crm_leads SET lead_score = ?, ai_pitch = ? WHERE id = ?");
    $stmtUp->execute([$score, $aiResponse, $lead_id]);

    echo json_encode([
        'success' => true,
        'lead_id' => $lead_id,
        'lead_score' => $score,
        'intent_tier' => $score >= 70 ? '🔥 HIGH INTENT' : ($score >= 45 ? '⚡ MEDIUM INTENT' : '❄️ LOW INTENT'),
        'ai_response' => $aiResponse,
        'message' => "AI Sales Analysis complete! Lead score calculated at {$score}%."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'AI Assistant Error: ' . $e->getMessage()]);
}
