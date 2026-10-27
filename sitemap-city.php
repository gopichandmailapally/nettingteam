<?php
/* NettingTeam — City service+area sitemap (batched)
   920 areas × 273 services = 251,160 URLs
   Split into batches of 50,000 (Google Sitemaps limit = 50,000 URLs/file)
   6 batches total.

   URL routing:
     sitemap-city-1.xml → sitemap-city.php?batch=1
     sitemap-city-2.xml → sitemap-city.php?batch=2
     sitemap-city-3.xml → sitemap-city.php?batch=3
     sitemap-city-4.xml → sitemap-city.php?batch=4
     sitemap-city-5.xml → sitemap-city.php?batch=5
     sitemap-city-6.xml → sitemap-city.php?batch=6
*/

require __DIR__ . '/_data.php'; // $areas, $svc_data

header('Content-Type: application/xml; charset=utf-8');

$batch    = max(1, (int)($_GET['batch'] ?? 1));
$per_page = 50000;
$offset   = ($batch - 1) * $per_page;
$base     = 'https://nettingteam.com/';
$date     = date('Y-m-d');

// Build flat URL list
$urls = [];
foreach (array_keys($svc_data) as $svc) {
    foreach (array_keys($areas) as $area) {
        $urls[] = $base . $svc . '-in-' . $area;
    }
}

$total  = count($urls);
$slice  = array_slice($urls, $offset, $per_page);

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($slice as $url) {
    echo '<url><loc>' . $url . '</loc><lastmod>' . $date . '</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>';
}
echo '</urlset>';
