<?php
/**
 * Ultra-Fast Asynchronous Visitor Tracking Endpoint
 * Runs in < 2ms, ignores bots, handles pageview & heartbeat beacons.
 */

// Don't block client response
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

// Fast-path bot filtering: Ignore web crawlers, search bots & headless scrapers
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (empty($ua) || preg_match('/(bot|crawl|spider|slurp|facebook|meta|applebot|bingbot|googlebot|yandex|baidu|petalbot|headless|curl|python|wget|search|inspection)/i', $ua)) {
    echo json_encode(['status' => 'ignored_bot']);
    exit;
}

// Read JSON payload (sendBeacon or fetch)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['visitor_id']) || empty($data['session_id'])) {
    echo json_encode(['status' => 'invalid_payload']);
    exit;
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/geo_resolver.php';

$pdo = getGcmDb();
if (!$pdo) {
    echo json_encode(['status' => 'db_unavailable']);
    exit;
}

$action     = trim($data['action'] ?? 'pageview');
$visitor_id = substr(trim($data['visitor_id']), 0, 64);
$session_id = substr(trim($data['session_id']), 0, 64);
$page_url   = substr(trim($data['page_url'] ?? '/'), 0, 500);
$page_title = substr(trim($data['page_title'] ?? ''), 0, 255);
$entry_page = substr(trim($data['entry_page'] ?? $page_url), 0, 500);
$referrer   = substr(trim($data['referrer'] ?? ''), 0, 500);
$visit_cnt  = max(1, (int)($data['visit_count'] ?? 1));
$time_spent = max(0, (int)($data['time_spent'] ?? 0));
$device     = in_array($data['device'] ?? '', ['Mobile', 'Tablet', 'Desktop']) ? $data['device'] : 'Desktop';
$os         = substr(trim($data['os'] ?? 'Unknown'), 0, 50);
$browser    = substr(trim($data['browser'] ?? 'Unknown'), 0, 50);

// Client IP
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// Determine City / Location:
// 1. First from current page URL using master geo resolver (e.g. /safety-nets-in-madurai)
$resolvedLocation = resolveSmartGeoLocation($page_url, '');
if (empty($resolvedLocation) && !empty($entry_page)) {
    $resolvedLocation = resolveSmartGeoLocation($entry_page, '');
}
$city  = '';
$state = '';

if (!empty($resolvedLocation) && $resolvedLocation !== 'India') {
    $parts = array_map('trim', explode(',', $resolvedLocation));
    $city  = $parts[0] ?? '';
    $state = end($parts) ?? '';
} else {
    // 2. Fallback to Cloudflare Geo Headers if available
    $cfCity = trim($_SERVER['HTTP_CF_IPCITY'] ?? '');
    $cfRegion = trim($_SERVER['HTTP_CF_REGION'] ?? '');
    if (!empty($cfCity)) {
        $city = $cfCity;
        $state = $cfRegion;
    } else {
        // 3. Fallback to IP Geolocation lookup
        $ipGeo = resolveIpLocationFast($ip, $pdo);
        if ($ipGeo && !empty($ipGeo['city'])) {
            $city  = $ipGeo['city'];
            $state = $ipGeo['state'];
        } else {
            $city  = 'Hyderabad';
            $state = 'Telangana';
        }
    }
}

// Determine Traffic Source
$source = 'Direct';
if (!empty($referrer)) {
    $parsedRef = parse_url($referrer);
    $refHost   = strtolower($parsedRef['host'] ?? '');
    
    // Check for Paid Ads parameters in page_url
    if (strpos($page_url, 'gclid=') !== false || strpos($page_url, 'gad_source=') !== false || strpos($page_url, 'utm_medium=cpc') !== false) {
        $source = 'Google Ads (Paid)';
    } elseif (strpos($refHost, 'google.') !== false) {
        $source = 'Google Search (Organic)';
    } elseif (strpos($refHost, 'bing.') !== false) {
        $source = 'Bing Search';
    } elseif (strpos($refHost, 'wa.me') !== false || strpos($refHost, 'whatsapp') !== false) {
        $source = 'WhatsApp';
    } elseif (strpos($refHost, 'facebook.') !== false || strpos($refHost, 'instagram.') !== false) {
        $source = 'Social Media';
    } elseif (strpos($refHost, 'youtube.') !== false) {
        $source = 'YouTube';
    } else {
        $source = $refHost;
    }
} elseif (strpos($page_url, 'gclid=') !== false || strpos($page_url, 'gad_source=') !== false) {
    $source = 'Google Ads (Paid)';
}

try {
    if ($action === 'pageview') {
        // 1. Upsert Visitor
        $stmtV = $pdo->prepare("
            INSERT INTO gcm_visitors (
                visitor_id, ip_address, city, state, country, device, os, browser, user_agent, 
                first_landing_page, first_referrer, traffic_source, first_seen, last_seen, visit_count, total_pageviews
            ) VALUES (
                ?, ?, ?, ?, 'India', ?, ?, ?, ?, 
                ?, ?, ?, NOW(), NOW(), ?, 1
            )
            ON DUPLICATE KEY UPDATE 
                ip_address = VALUES(ip_address),
                city = IF(city IS NULL OR city = '', VALUES(city), city),
                state = IF(state IS NULL OR state = '', VALUES(state), state),
                device = VALUES(device),
                os = VALUES(os),
                browser = VALUES(browser),
                last_seen = NOW(),
                visit_count = GREATEST(visit_count, VALUES(visit_count)),
                total_pageviews = total_pageviews + 1
        ");
        $stmtV->execute([
            $visitor_id, $ip, $city, $state, $device, $os, $browser, $ua,
            $page_url, $referrer, $source, $visit_cnt
        ]);

        // 2. Upsert Live Session
        $stmtS = $pdo->prepare("
            INSERT INTO gcm_live_sessions (
                session_id, visitor_id, ip_address, city, state, device, os, browser,
                entry_page, current_page, page_title, referrer, traffic_source,
                started_at, last_heartbeat, time_spent_seconds, pageview_count, is_online
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                NOW(), NOW(), 0, 1, 1
            )
            ON DUPLICATE KEY UPDATE 
                current_page = VALUES(current_page),
                page_title = VALUES(page_title),
                last_heartbeat = NOW(),
                pageview_count = pageview_count + 1,
                is_online = 1
        ");
        $stmtS->execute([
            $session_id, $visitor_id, $ip, $city, $state, $device, $os, $browser,
            $entry_page, $page_url, $page_title, $referrer, $source
        ]);

        // 3. Insert Pageview History
        $stmtP = $pdo->prepare("
            INSERT INTO gcm_pageviews (
                visitor_id, session_id, page_url, page_title, referrer, device, city, viewed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtP->execute([$visitor_id, $session_id, $page_url, $page_title, $referrer, $device, $city]);

    } elseif ($action === 'heartbeat') {
        // Update Session Heartbeat & Duration
        $stmtH = $pdo->prepare("
            UPDATE gcm_live_sessions 
            SET last_heartbeat = NOW(),
                current_page = ?,
                time_spent_seconds = GREATEST(time_spent_seconds, ?),
                is_online = 1
            WHERE session_id = ?
        ");
        $stmtH->execute([$page_url, $time_spent, $session_id]);

        $stmtVH = $pdo->prepare("UPDATE gcm_visitors SET last_seen = NOW() WHERE visitor_id = ?");
        $stmtVH->execute([$visitor_id]);

    } elseif ($action === 'leave') {
        // Mark session inactive
        $stmtL = $pdo->prepare("
            UPDATE gcm_live_sessions 
            SET is_online = 0, last_heartbeat = NOW(), time_spent_seconds = GREATEST(time_spent_seconds, ?)
            WHERE session_id = ?
        ");
        $stmtL->execute([$time_spent, $session_id]);
    }

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    error_log("Tracking error: " . $e->getMessage());
    echo json_encode(['status' => 'error']);
}
