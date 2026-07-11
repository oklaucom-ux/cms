<?php
/**
 * rate_limiter.php — Database-backed API Rate Limiting
 * 
 * Usage: require_once 'includes/rate_limiter.php';
 *        checkRateLimit($pdo, 'api', 60, 60);  // 60 requests per 60 seconds
 *
 * For high-traffic: swap the database backend for Redis with minimal changes.
 */

/**
 * Check if the current IP has exceeded the rate limit.
 * 
 * @param PDO    $pdo       Database connection
 * @param string $scope     Rate limit scope (e.g., 'api', 'login', 'upload')
 * @param int    $maxHits   Maximum requests allowed in the window
 * @param int    $windowSec Time window in seconds
 * @return bool  True if within limits, false if rate limited
 */
function checkRateLimit(PDO $pdo, string $scope = 'api', int $maxHits = 60, int $windowSec = 60): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = $scope . ':' . $ip;
    $now = time();
    $windowStart = $now - $windowSec;
    
    // Ensure the rate_limits table exists (lightweight IF NOT EXISTS check)
    static $tableChecked = false;
    if (!$tableChecked) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                rate_key VARCHAR(255) NOT NULL,
                hit_at INTEGER NOT NULL,
                INDEX idx_rate_key_time (rate_key, hit_at)
            )");
        } catch (Exception $e) {
            // Table may already exist or syntax differs between MySQL/SQLite
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    rate_key TEXT NOT NULL,
                    hit_at INTEGER NOT NULL
                )");
            } catch (Exception $e2) {}
        }
        $tableChecked = true;
    }
    
    // Clean expired entries (older than window) — do this periodically, not every request
    if (mt_rand(1, 10) === 1) {
        $pdo->prepare("DELETE FROM rate_limits WHERE hit_at < ?")->execute([$windowStart]);
    }
    
    // Count hits in the current window
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND hit_at >= ?");
    $stmt->execute([$key, $windowStart]);
    $hitCount = (int) $stmt->fetchColumn();
    
    // Set rate limit headers
    $remaining = max(0, $maxHits - $hitCount);
    header("X-RateLimit-Limit: {$maxHits}");
    header("X-RateLimit-Remaining: {$remaining}");
    header("X-RateLimit-Reset: " . ($now + $windowSec));
    
    if ($hitCount >= $maxHits) {
        $retryAfter = $windowSec;
        header("Retry-After: {$retryAfter}");
        return false;
    }
    
    // Record this hit
    $pdo->prepare("INSERT INTO rate_limits (rate_key, hit_at) VALUES (?, ?)")->execute([$key, $now]);
    
    return true;
}

/**
 * Enforce rate limit — responds with 429 and exits if exceeded.
 */
function enforceRateLimit(PDO $pdo, string $scope = 'api', int $maxHits = 60, int $windowSec = 60): void {
    if (!checkRateLimit($pdo, $scope, $maxHits, $windowSec)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Too Many Requests',
            'message' => "Rate limit exceeded. Maximum {$maxHits} requests per {$windowSec} seconds.",
            'retry_after' => $windowSec
        ]);
        exit;
    }
}
