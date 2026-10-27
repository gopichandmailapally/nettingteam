<?php
/**
 * Session Activity Keep-Alive Endpoint
 * Called periodically by layout_end.php when the admin user is actively interacting with the page.
 * Keeps the session alive and reports remaining time before the 2-hour inactivity auto-logout.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or not logged in',
        'redirect' => adminUrl('login.php?timeout=2hr')
    ]);
    exit;
}

// User is actively working: refresh last activity timestamp
$now = time();
$_SESSION['gcm_last_activity'] = $now;

$sessUuid = $_SESSION['gcm_session_uuid'] ?? '';
if ($sessUuid) {
    updateActiveSessionActivity($sessUuid, $now);
}

echo json_encode([
    'ok'                => true,
    'time'              => $now,
    'remaining_seconds' => SESSION_INACTIVITY_TIMEOUT,
    'timeout_minutes'   => round(SESSION_INACTIVITY_TIMEOUT / 60),
    'user'              => currentEmployeeName()
]);
