<?php
ini_set('memory_limit', '512M');
set_time_limit(300);
/*
 * NettingTeam — IndexNow + Sitemap Ping (Auto-indexing after deploy)
 *
 * HOW TO USE AFTER EVERY DEPLOYMENT:
 *   1. Visit: https://nettingteam.com/indexnow-submit.php?run=1&key=YOUR_INDEXNOW_KEY
 *   2. Or add to your deploy script: curl "https://nettingteam.com/indexnow-submit.php?run=1&key=KEY"
 *
 * SETUP (one-time):
 *   1. Get a free IndexNow key at: https://www.bing.com/indexnow/getstarted
 *   2. Create a file named {key}.txt in the website root containing just the key
 *   3. Set $api_key below OR pass via ?key= URL param
 *
 * WHY THIS MATTERS:
 *   - IndexNow notifies Bing, Yandex and other engines instantly
 *   - Google sitemap ping triggers fresh crawl scheduling
 *   - Eliminates "Discovered – currently not indexed" delays
 */

// Hardcode key here (optional, can also pass via ?key=)
$api_key = $_GET['key'] ?? '2a1afdcb80add136049559298fd07569';

require __DIR__ . '/_data.php'; // provides $areas, $svc_data

$base = 'https://nettingteam.com';

// --- Static pages ---
$static_pages = [
    '/', '/about', '/contact', '/services', '/gallery', '/videos',
    '/service-balcony-safety-nets', '/service-bird-nets', '/service-pigeon-nets',
    '/service-sports-nets', '/service-invisible-grills', '/service-cloth-hangers',
    '/service-artificial-grass', '/privacy-policy', '/terms-of-service',
    '/child-safety-nets-hyderabad', '/cat-safety-netting-hyderabad',
    '/balcony-netting-hyderabad', '/safety-net-installation-hyderabad',
    '/uv-resistant-nets-hyderabad', '/secure-balcony-toddlers-hyderabad',
    '/anti-bird-netting-hyderabad', '/bird-proofing-gardens-hyderabad',
    '/bird-netting-installation-hyderabad', '/hdpe-bird-netting-hyderabad',
    '/invisible-bird-mesh-hyderabad', '/keep-birds-off-balcony-hyderabad',
    '/bird-proofing-balconies-hyderabad', '/anti-bird-netting-installation-hyderabad',
    '/heavy-duty-pigeon-netting-hyderabad', '/invisible-pigeon-mesh-hyderabad',
    '/pigeon-control-solutions-hyderabad', '/nylon-bird-netting-hyderabad',
    '/ball-stop-netting-hyderabad', '/perimeter-netting-hyderabad',
    '/impact-resistant-mesh-hyderabad', '/court-divider-nets-hyderabad',
    '/high-tensile-invisible-grill-hyderabad', '/unobstructed-view-balcony-grill-hyderabad',
    '/high-rise-grill-installation-hyderabad', '/stainless-steel-316-grill-hyderabad',
    '/space-saving-hangers-hyderabad', '/wall-mounted-drying-rack-hyderabad',
    '/heavy-duty-wooden-hangers-hyderabad', '/collapsible-drying-stand-hyderabad',
    '/ceiling-cloth-hangers-hyderabad', '/cloth-drying-stands-hyderabad',
    '/luxury-cloth-hangers-hyderabad', '/pull-and-dry-hyderabad',
];

// --- Dynamic service+area pages (all 45,180) built from _data.php ---
$dynamic_pages = [];
foreach (array_keys($svc_data) as $svc) {
    foreach (array_keys($areas) as $area) {
        $dynamic_pages[] = '/' . $svc . '-in-' . $area;
    }
}

$all_urls   = array_merge($static_pages, $dynamic_pages);
$full_urls  = array_map(fn($p) => $base . $p, $all_urls);
$total      = count($full_urls);

// --- Show stats if not running ---
if (empty($_GET['run'])) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>IndexNow Submission — NettingTeam</title></head><body style="font-family:sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem">';
    echo '<h2>NettingTeam — IndexNow &amp; Sitemap Submission</h2>';
    echo '<table border="1" cellpadding="8" style="border-collapse:collapse;width:100%">';
    echo '<tr><th>Type</th><th>Count</th></tr>';
    echo '<tr><td>Static pages</td><td>' . count($static_pages) . '</td></tr>';
    echo '<tr><td>Dynamic service+area pages</td><td>' . count($dynamic_pages) . '</td></tr>';
    echo '<tr><td><strong>Total URLs</strong></td><td><strong>' . $total . '</strong></td></tr>';
    echo '</table>';
    echo '<p><strong>IndexNow API Key:</strong> ' . ($api_key ? '<span style="color:green">✓ Provided</span>' : '<span style="color:red">✗ Not provided — pass via ?key=YOUR_KEY</span>') . '</p>';
    echo '<h3>Submit:</h3>';
    echo '<ul>';
    echo '<li><a href="?run=1' . ($api_key ? '&key=' . htmlspecialchars($api_key) : '') . '">Run full IndexNow + sitemap ping</a></li>';
    echo '<li><a href="?run=1&sitemap_only=1">Run sitemap ping only (no key needed)</a></li>';
    echo '</ul>';
    echo '<p style="color:#666;font-size:.9rem">Deploy command: <code>curl "https://nettingteam.com/indexnow-submit.php?run=1&amp;key=YOUR_KEY"</code></p>';
    echo '</body></html>';
    exit;
}

$results = ['timestamp' => date('c'), 'total_urls' => $total, 'steps' => []];

// ── STEP 1: Google Search Console sitemap ping ──────────────────────────────
$sitemap_url = urlencode($base . '/sitemap-index.php');
$google_ping = 'https://www.google.com/ping?sitemap=' . $sitemap_url;
$ch = curl_init($google_ping);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$results['steps'][] = ['step' => 'Google sitemap ping', 'url' => $google_ping, 'http_status' => $status];

// ── STEP 2: Bing sitemap ping ────────────────────────────────────────────────
$bing_ping = 'https://www.bing.com/ping?sitemap=' . $sitemap_url;
$ch = curl_init($bing_ping);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$results['steps'][] = ['step' => 'Bing sitemap ping', 'url' => $bing_ping, 'http_status' => $status];

// ── STEP 3: IndexNow submission (if key provided) ────────────────────────────
if (empty($api_key) || !empty($_GET['sitemap_only'])) {
    $results['steps'][] = ['step' => 'IndexNow', 'skipped' => true, 'reason' => 'No API key provided'];
} else {
    $key_location = $base . '/' . $api_key . '.txt';
    $batches      = array_chunk($full_urls, 10000);
    $batch_results = [];

    foreach ($batches as $i => $batch) {
        $payload = json_encode([
            'host'        => 'nettingteam.com',
            'key'         => $api_key,
            'keyLocation' => $key_location,
            'urlList'     => $batch,
        ]);
        // Submit to IndexNow (distributes to Bing, Yandex, etc.)
        $ch = curl_init('https://api.indexnow.org/IndexNow');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $batch_results[] = ['batch' => ($i + 1), 'urls_in_batch' => count($batch), 'http_status' => $status];
    }
    $results['steps'][] = ['step' => 'IndexNow submission', 'batches' => $batch_results, 'total_submitted' => $total];
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
