<?php
// controllers/ai_card_parser.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$rawText = trim($_POST['raw_text'] ?? '');

if (empty($rawText)) {
    echo json_encode(['success' => false, 'error' => 'No raw OCR text provided.']);
    exit();
}

// Advanced pattern & NLP extraction heuristic
function aiParseCardText($text) {
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen'));
    
    $result = [
        'contact_name' => '',
        'job_title'    => '',
        'company_name' => '',
        'email'        => '',
        'phone'        => '',
        'website'      => '',
        'address'      => ''
    ];

    // Email matching
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
        $result['email'] = strtolower($matches[0]);
    }

    // Phone matching (Mobile, Tel, Direct, Fax)
    if (preg_match('/(?:\+?\d{1,4}[-.\s]?)?\(?\d{2,5}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}/', $text, $matches)) {
        $result['phone'] = trim($matches[0]);
    }

    // Website matching
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?[a-zA-Z0-9-]+\.(?:com|org|net|co|io|in|tech|biz|gov|edu)/i', $text, $matches)) {
        $result['website'] = strtolower($matches[0]);
    }

    // Address heuristics
    $addressKeywords = ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'suite', 'ste', 'floor', 'fl', 'building', 'bldg', 'park', 'block', 'city', 'p.o. box'];
    $addressLines = [];
    foreach ($lines as $line) {
        $lower = strtolower($line);
        foreach ($addressKeywords as $kw) {
            if (str_contains($lower, $kw) || preg_match('/\b\d{5,6}\b/', $line)) {
                $addressLines[] = $line;
                break;
            }
        }
    }
    if (!empty($addressLines)) {
        $result['address'] = implode(', ', array_unique($addressLines));
    }

    // Designation / Title heuristics
    $titleKeywords = ['manager', 'director', 'ceo', 'cto', 'cfo', 'coo', 'president', 'vp', 'vice president', 'head', 'lead', 'consultant', 'engineer', 'architect', 'developer', 'founder', 'co-founder', 'partner', 'specialist', 'executive', 'officer', 'advisor'];
    foreach ($lines as $line) {
        $lower = strtolower($line);
        foreach ($titleKeywords as $tk) {
            if (str_contains($lower, $tk)) {
                $result['job_title'] = $line;
                break 2;
            }
        }
    }

    // Company Name heuristics
    $companyKeywords = ['inc', 'ltd', 'limited', 'corp', 'corporation', 'llc', 'pvt', 'private', 'group', 'technologies', 'solutions', 'labs', 'services', 'enterprises', 'studio', 'agency', 'holdings', 'global', 'systems'];
    foreach ($lines as $line) {
        $lower = strtolower($line);
        foreach ($companyKeywords as $ck) {
            if (str_contains($lower, $ck)) {
                $result['company_name'] = $line;
                break 2;
            }
        }
    }

    // Name heuristic (Line with 2-3 capitalized words, not containing email/numbers/company)
    foreach ($lines as $line) {
        if ($line === $result['job_title'] || $line === $result['company_name'] || str_contains($line, '@') || str_contains($line, 'www.')) continue;
        if (preg_match('/^[A-Z][a-zA-Z\.\'-]+\s+[A-Z][a-zA-Z\.\'-]+(?:\s+[A-Z][a-zA-Z\.\'-]+)?$/', $line)) {
            $result['contact_name'] = $line;
            break;
        }
    }

    // Fallback name if no regex match
    if (empty($result['contact_name']) && count($lines) > 0) {
        foreach ($lines as $l) {
            if (!str_contains($l, '@') && !preg_match('/\d{5,}/', $l) && strlen($l) < 35 && $l !== $result['job_title'] && $l !== $result['company_name']) {
                $result['contact_name'] = $l;
                break;
            }
        }
    }

    return $result;
}

$parsed = aiParseCardText($rawText);

echo json_encode([
    'success' => true,
    'data'    => $parsed
]);
