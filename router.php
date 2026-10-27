<?php
/**
 * PHP built-in dev server router — mimics .htaccess rewrite rules.
 * Usage: php -S localhost:8080 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = ltrim(urldecode($uri), '/');

// Remove trailing slash
$uri = rtrim($uri, '/');

// ── 1. Serve real files/dirs as-is ─────────────────────────
$file = __DIR__ . '/' . $uri;
if ($uri !== '' && (is_file($file) || is_dir($file))) {
    return false; // let built-in server handle it
}

// ── 2. Sitemap XML aliases ────────────────────────────────────
$sitemap_map = [
    'sitemap.xml'          => ['sitemap-index.php', []],
    'sitemap-static.xml'   => ['sitemap-static.php', []],
    'sitemap-city-1.xml'   => ['sitemap-city.php',   ['batch'=>'1']],
    'sitemap-city-2.xml'   => ['sitemap-city.php',   ['batch'=>'2']],
    'sitemap-city-3.xml'   => ['sitemap-city.php',   ['batch'=>'3']],
    'sitemap-city-4.xml'   => ['sitemap-city.php',   ['batch'=>'4']], 'sitemap-city-5.xml'   => ['sitemap-city.php',   ['batch'=>'5']], 'sitemap-city-6.xml'   => ['sitemap-city.php',   ['batch'=>'6']],
    'sitemap-city.xml'     => ['sitemap-city.php',   ['batch'=>'1']],
];
// Intent batches: sitemap-intent-1.xml ... sitemap-intent-23.xml
if (preg_match('/^sitemap-intent-(\d+)\.xml$/', $uri, $sm)) {
    $_GET['batch'] = $sm[1];
    require __DIR__ . '/sitemap-intent.php';
    exit;
}
if (isset($sitemap_map[$uri])) {
    [$file, $params] = $sitemap_map[$uri];
    foreach ($params as $k => $v) $_GET[$k] = $v;
    require __DIR__ . '/' . $file;
    exit;
}

// ── 3. Intent-variant: {service}-{intent}-in-{area} → n.php ─
// Catch-all: n.php/nets.php validate slugs against $svc_data and 404 unknowns
$intents = 'dealers|suppliers|price|near-me|installation|wholesalers|manufacturers|rate';
$services = '[a-z0-9-]+'; // catch-all — was hardcoded slug list (now 251+ slugs)
// (legacy slug list removed)

if (preg_match('/^('.$services.')-('.$intents.')-in-([a-z0-9-]+)$/', $uri, $m)) {
    $_GET['service'] = $m[1];
    $_GET['intent']  = $m[2];
    $_GET['area']    = $m[3];
    require __DIR__ . '/n.php';
    exit;
}

// ── 4. Base service+area: {service}-in-{area} → nets.php ────
if (preg_match('/^('.$services.')-in-([a-z0-9-]+)$/', $uri, $m)) {
    $_GET['service'] = $m[1];
    $_GET['area']    = $m[2];
    require __DIR__ . '/nets.php';
    exit;
}

// ── 5. Clean .php extension: /contact → contact.php ─────────
if ($uri !== '' && is_file(__DIR__ . '/' . $uri . '.php')) {
    require __DIR__ . '/' . $uri . '.php';
    exit;
}

// ── 6. Home ──────────────────────────────────────────────────
if ($uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// ── 7. 404 ───────────────────────────────────────────────────
http_response_code(404);
if (is_file(__DIR__ . '/404.php')) {
    require __DIR__ . '/404.php';
} else {
    echo '<h1>404 Not Found</h1>';
}
