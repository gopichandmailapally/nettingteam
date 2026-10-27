<?php
/* NettingTeam — Pure Hyderabad Sitemap Index (auto-calculated)
   183 Hyderabad localities × 273 services = 49,959 base city pages (1 batch)
   183 Hyderabad localities × 273 services × 8 intents = 399,672 intent pages (8 batches)
   Plus core static pages.
   Grand total: ~449,660 pure Hyderabad URLs.
*/

header('Content-Type: application/xml; charset=utf-8');
require __DIR__ . '/_data.php';

$base = 'https://nettingteam.com/';
$date = date('Y-m-d');

$n_city_urls    = count($svc_data) * count($areas);
$city_batches   = max(1, (int)ceil($n_city_urls / 50000));

$n_intent_urls  = count($svc_data) * count($areas) * 8;
$intent_batches = max(1, (int)ceil($n_intent_urls / 50000));

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Static Core Pages -->
  <sitemap>
    <loc><?=$base?>sitemap-static.xml</loc>
    <lastmod><?=$date?></lastmod>
  </sitemap>

  <!-- Base Hyderabad Area Pages (<?=$n_city_urls?> URLs) -->
<?php for ($i = 1; $i <= $city_batches; $i++): ?>
  <sitemap>
    <loc><?=$base?>sitemap-city-<?=$i?>.xml</loc>
    <lastmod><?=$date?></lastmod>
  </sitemap>
<?php endfor; ?>

  <!-- Commercial Intent Pages for Hyderabad (<?=$n_intent_urls?> URLs) -->
<?php for ($i = 1; $i <= $intent_batches; $i++): ?>
  <sitemap>
    <loc><?=$base?>sitemap-intent-<?=$i?>.xml</loc>
    <lastmod><?=$date?></lastmod>
  </sitemap>
<?php endfor; ?>

</sitemapindex>
