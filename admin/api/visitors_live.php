<?php
/**
 * Real-Time Visitors API Endpoint for Admin Dashboard
 * Secure, requires admin authentication, returns JSON with custom time filters.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

date_default_timezone_set('Asia/Kolkata');

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Authentication check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/includes/db.php';
$pdo = getGcmDb();

if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'live_count' => 0,
        'range_unique' => 0,
        'range_new' => 0,
        'range_repeat' => 0,
        'range_views' => 0,
        'live_sessions' => [],
        'recent_visitors' => [],
        'top_pages' => [],
        'sources' => [],
        'devices' => []
    ]);
    exit;
}

// Time Range Resolution & Pagination
$rawRange = strtolower(trim($_GET['range'] ?? 'today'));
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = max(10, min(500, (int)($_GET['limit'] ?? 25)));
$offset   = ($page - 1) * $limit;

$normalizedRange = match($rawRange) {
    '5m', '5mins', 'last_5mins'       => '5m',
    '30m', '30mins', 'last_30mins'   => '30m',
    '1h', '1hr', 'last_1hr', '1hour'  => '1h',
    '6h', '6hrs', 'last_6hrs'        => '6h',
    '24h', '24hrs', 'last_24hrs'     => '24h',
    'today', 'todays'                => 'today',
    '7d', '7days', 'last_7days'      => '7d',
    '30d', '30days', 'last_30days'   => '30d',
    '3m', '3months', 'last_3months'  => '3m',
    '6m', '6months', 'last_6months'  => '6m',
    '1y', '1year', 'last_1year'      => '1y',
    '5y', '5years', 'last_5years'    => '5y',
    'all', 'alltime', 'total'        => 'all',
    default                          => 'today'
};

$rangeLabels = [
    '5m'    => 'Last 5 Minutes',
    '30m'   => 'Last 30 Minutes',
    '1h'    => 'Last 1 Hour',
    '6h'    => 'Last 6 Hours',
    '24h'   => 'Last 24 Hours',
    'today' => 'Today (Since 12 AM)',
    '7d'    => 'Last 7 Days',
    '30d'   => 'Last 30 Days',
    '3m'    => 'Last 3 Months',
    '6m'    => 'Last 6 Months',
    '1y'    => 'Last 1 Year',
    '5y'    => 'Last 5 Years',
    'all'   => 'All Time (Total)',
];

$vWhere = match($normalizedRange) {
    '5m'    => 'last_seen >= NOW() - INTERVAL 5 MINUTE',
    '30m'   => 'last_seen >= NOW() - INTERVAL 30 MINUTE',
    '1h'    => 'last_seen >= NOW() - INTERVAL 1 HOUR',
    '6h'    => 'last_seen >= NOW() - INTERVAL 6 HOUR',
    '24h'   => 'last_seen >= NOW() - INTERVAL 24 HOUR',
    'today' => 'last_seen >= DATE_SUB(DATE(DATE_ADD(NOW(), INTERVAL 330 MINUTE)), INTERVAL 330 MINUTE)',
    '7d'    => 'last_seen >= NOW() - INTERVAL 7 DAY',
    '30d'   => 'last_seen >= NOW() - INTERVAL 30 DAY',
    '3m'    => 'last_seen >= NOW() - INTERVAL 90 DAY',
    '6m'    => 'last_seen >= NOW() - INTERVAL 180 DAY',
    '1y'    => 'last_seen >= NOW() - INTERVAL 365 DAY',
    '5y'    => 'last_seen >= NOW() - INTERVAL 1825 DAY',
    'all'   => '1=1',
};

$pWhere = match($normalizedRange) {
    '5m'    => 'viewed_at >= NOW() - INTERVAL 5 MINUTE',
    '30m'   => 'viewed_at >= NOW() - INTERVAL 30 MINUTE',
    '1h'    => 'viewed_at >= NOW() - INTERVAL 1 HOUR',
    '6h'    => 'viewed_at >= NOW() - INTERVAL 6 HOUR',
    '24h'   => 'viewed_at >= NOW() - INTERVAL 24 HOUR',
    'today' => 'viewed_at >= DATE_SUB(DATE(DATE_ADD(NOW(), INTERVAL 330 MINUTE)), INTERVAL 330 MINUTE)',
    '7d'    => 'viewed_at >= NOW() - INTERVAL 7 DAY',
    '30d'   => 'viewed_at >= NOW() - INTERVAL 30 DAY',
    '3m'    => 'viewed_at >= NOW() - INTERVAL 90 DAY',
    '6m'    => 'viewed_at >= NOW() - INTERVAL 180 DAY',
    '1y'    => 'viewed_at >= NOW() - INTERVAL 365 DAY',
    '5y'    => 'viewed_at >= NOW() - INTERVAL 1825 DAY',
    'all'   => '1=1',
};

try {
    // 1. Live active visitors right now (active in last 3 minutes) - independent of range
    $liveCount = (int)$pdo->query("SELECT COUNT(*) FROM gcm_live_sessions WHERE is_online = 1 AND last_heartbeat >= NOW() - INTERVAL 3 MINUTE")->fetchColumn();

    // 2. All-time baseline totals
    $allTimeUnique = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM gcm_visitors")->fetchColumn();
    $allTimeViews  = (int)$pdo->query("SELECT COUNT(*) FROM gcm_pageviews")->fetchColumn();

    // 3. Filtered metrics for selected time range
    $rangeUnique = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM gcm_visitors WHERE {$vWhere}")->fetchColumn();
    $rangeNew    = (int)$pdo->query("SELECT COUNT(*) FROM gcm_visitors WHERE visit_count = 1 AND {$vWhere}")->fetchColumn();
    $rangeRepeat = (int)$pdo->query("SELECT COUNT(*) FROM gcm_visitors WHERE visit_count > 1 AND {$vWhere}")->fetchColumn();
    $rangeViews  = (int)$pdo->query("SELECT COUNT(*) FROM gcm_pageviews WHERE {$pWhere}")->fetchColumn();

    // 4. Active live sessions list (where they entered, what page they are on now)
    $stmtSessions = $pdo->query("
        SELECT 
            s.session_id, s.visitor_id, s.ip_address, s.city, s.state, s.device, s.os, s.browser,
            s.entry_page, s.current_page, s.page_title, s.referrer, s.traffic_source,
            s.started_at, s.last_heartbeat, s.time_spent_seconds, s.pageview_count,
            TIMESTAMPDIFF(SECOND, s.last_heartbeat, NOW()) as seconds_ago,
            v.visit_count
        FROM gcm_live_sessions s
        LEFT JOIN gcm_visitors v ON s.visitor_id = v.visitor_id
        WHERE s.is_online = 1 AND s.last_heartbeat >= NOW() - INTERVAL 5 MINUTE
        ORDER BY s.last_heartbeat DESC
        LIMIT 50
    ");
    $liveSessions = $stmtSessions->fetchAll();

    // Calculate pagination values
    $totalPages = max(1, (int)ceil($rangeUnique / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = max(0, ($page - 1) * $limit);
    }

    // 5. Recent human visitors filtered by selected time range (paginated, converted to Indian Standard Time)
    $stmtRecent = $pdo->prepare("
        SELECT 
            visitor_id, city, state, device, os, browser,
            first_landing_page, first_referrer, traffic_source,
            first_seen,
            DATE_FORMAT(DATE_ADD(last_seen, INTERVAL 330 MINUTE), '%Y-%m-%d %h:%i:%s %p') as last_seen,
            visit_count, total_pageviews,
            TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
        FROM gcm_visitors
        WHERE {$vWhere}
        ORDER BY last_seen DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmtRecent->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtRecent->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtRecent->execute();
    $recentVisitors = $stmtRecent->fetchAll();

    // 6. Top pages viewed in selected time range
    $stmtTop = $pdo->query("
        SELECT page_url, COALESCE(NULLIF(page_title,''), page_url) as title, COUNT(*) as views
        FROM gcm_pageviews
        WHERE {$pWhere}
        GROUP BY page_url
        ORDER BY views DESC
        LIMIT 10
    ");
    $topPages = $stmtTop->fetchAll();

    // 7. Traffic Sources Breakdown in selected time range
    $stmtSources = $pdo->query("
        SELECT traffic_source as name, COUNT(*) as count 
        FROM gcm_visitors 
        WHERE {$vWhere}
        GROUP BY traffic_source 
        ORDER BY count DESC 
        LIMIT 6
    ");
    $sources = $stmtSources->fetchAll();

    // 8. Devices Breakdown in selected time range
    $stmtDevices = $pdo->query("
        SELECT device as name, COUNT(*) as count 
        FROM gcm_visitors 
        WHERE {$vWhere}
        GROUP BY device 
        ORDER BY count DESC
    ");
    $devices = $stmtDevices->fetchAll();

    echo json_encode([
        'status'           => 'ok',
        'timestamp'        => date('Y-m-d H:i:s'),
        'range'            => $normalizedRange,
        'range_label'      => $rangeLabels[$normalizedRange] ?? 'Custom',
        'live_count'       => $liveCount,
        'range_unique'     => $rangeUnique,
        'range_new'        => $rangeNew,
        'range_repeat'     => $rangeRepeat,
        'range_views'      => $rangeViews,
        'all_time_unique'  => $allTimeUnique,
        'all_time_views'   => $allTimeViews,
        'live_sessions'    => $liveSessions,
        'recent_visitors'  => $recentVisitors,
        'pagination'       => [
            'page'          => $page,
            'limit'         => $limit,
            'total_records' => $rangeUnique,
            'total_pages'   => $totalPages,
            'offset'        => $offset,
            'start'         => $rangeUnique > 0 ? $offset + 1 : 0,
            'end'           => min($offset + count($recentVisitors), $rangeUnique),
        ],
        'top_pages'        => $topPages,
        'sources'          => $sources,
        'devices'          => $devices
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
