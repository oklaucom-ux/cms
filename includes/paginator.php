<?php
/**
 * paginator.php — Reusable Pagination Helper
 * 
 * Usage:
 *   require_once 'includes/paginator.php';
 *   $pg = paginate($pdo, 'SELECT * FROM tasks WHERE status=?', ['Pending'], 50);
 *   // $pg['rows']       — fetched rows for this page
 *   // $pg['total']      — total record count
 *   // $pg['page']       — current page number
 *   // $pg['totalPages'] — total pages
 *   // $pg['perPage']    — items per page
 *   // renderPagination($pg, 'tasks.php', ['status' => 'Pending']);
 */

/**
 * Execute a paginated query.
 */
function paginate(PDO $pdo, string $sql, array $params = [], int $perPage = 50): array {
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    
    // Build count query by replacing SELECT ... FROM with SELECT COUNT(*) FROM
    $countSql = preg_replace('/^SELECT\s+.*?\s+FROM\s/is', 'SELECT COUNT(*) FROM ', $sql, 1);
    // Remove any ORDER BY clause from count query for performance
    $countSql = preg_replace('/\s+ORDER BY\s+.*$/is', '', $countSql);
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));
    
    // Append LIMIT/OFFSET to the data query
    $dataSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'totalPages' => $totalPages,
        'perPage'    => $perPage,
        'offset'     => $offset,
    ];
}

/**
 * Render pagination HTML controls.
 * 
 * @param array  $pg       The result from paginate()
 * @param string $baseUrl  The page URL (e.g., 'tasks.php')
 * @param array  $extraParams  Additional query parameters to preserve
 */
function renderPagination(array $pg, string $baseUrl, array $extraParams = []): void {
    if ($pg['totalPages'] <= 1) return;
    
    $page = $pg['page'];
    $totalPages = $pg['totalPages'];
    $qs = http_build_query(array_filter($extraParams));
    
    $link = function(int $p) use ($baseUrl, $qs): string {
        return htmlspecialchars($baseUrl . "?page={$p}" . ($qs ? "&{$qs}" : ""));
    };
    
    echo '<div style="display:flex; justify-content:center; gap:4px; margin-top:20px; flex-wrap:wrap; align-items:center;">';
    
    if ($page > 1) {
        echo '<a href="' . $link(1) . '" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">«</a>';
        echo '<a href="' . $link($page - 1) . '" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">‹ Prev</a>';
    }
    
    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $cls = ($i === $page) ? 'add-button' : 'edit-button';
        echo '<a href="' . $link($i) . '" class="' . $cls . '" style="padding:4px 10px; font-size:12px; text-decoration:none;">' . $i . '</a>';
    }
    
    if ($page < $totalPages) {
        echo '<a href="' . $link($page + 1) . '" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">Next ›</a>';
        echo '<a href="' . $link($totalPages) . '" class="edit-button" style="padding:4px 10px; font-size:12px; text-decoration:none;">»</a>';
    }
    
    echo '<span style="margin-left:8px; font-size:11px; color:var(--text-muted);">Page ' . $page . '/' . $totalPages . ' (' . number_format($pg['total']) . ' records)</span>';
    echo '</div>';
}
