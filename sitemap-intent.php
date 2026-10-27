<?php
/* NettingTeam — Intent-variant sitemap (streaming, no memory spike)
   8 intents × 273 services × 920 areas = 2,009,280 URLs
   Split into 41 batches of 50,000

   URL format: /{service}-{intent}-in-{area}
   e.g. /safety-nets-dealers-in-gachibowli, /safety-nets-dealers-in-mumbai

   Usage: sitemap-intent.php?batch=1..41 */

ini_set('memory_limit', '256M');
set_time_limit(120);

require __DIR__ . '/_data.php'; // $areas, $svc_data

header('Content-Type: application/xml; charset=utf-8');

$intents  = ['dealers','suppliers','price','near-me','installation','wholesalers','manufacturers','rate'];
$batch    = max(1, (int)($_GET['batch'] ?? 1));
$per_page = 50000;
$offset   = ($batch - 1) * $per_page;
$base     = 'https://nettingteam.com/';
$date     = date('Y-m-d');

$svc_keys  = array_keys($svc_data);
$area_keys = array_keys($areas);
$n_svcs    = count($svc_keys);
$n_areas   = count($area_keys);
$n_intents = count($intents);

// Total URLs = n_svcs × n_areas × n_intents
// Index formula: idx = svc_i * (n_areas * n_intents) + area_i * n_intents + intent_i

$total = $n_svcs * $n_areas * $n_intents;
$end   = min($offset + $per_page, $total);

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

for ($i = $offset; $i < $end; $i++) {
    $intent_i = $i % $n_intents;
    $tmp      = (int)($i / $n_intents);
    $area_i   = $tmp % $n_areas;
    $svc_i    = (int)($tmp / $n_areas);

    $url = $base . $svc_keys[$svc_i] . '-' . $intents[$intent_i] . '-in-' . $area_keys[$area_i];
    echo '<url><loc>' . $url . '</loc><lastmod>' . $date . '</lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>';
}

echo '</urlset>';
