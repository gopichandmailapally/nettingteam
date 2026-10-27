<?php
/* NettingTeam — Static pages sitemap */
header('Content-Type: application/xml; charset=utf-8');
$base = 'https://nettingteam.com/';
$date = date('Y-m-d');

$pages = [
  // Core pages
  ['url'=>'',              'priority'=>'1.0', 'changefreq'=>'weekly'],
  ['url'=>'about',         'priority'=>'0.8', 'changefreq'=>'monthly'],
  ['url'=>'contact',       'priority'=>'0.8', 'changefreq'=>'monthly'],
  ['url'=>'services',      'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'gallery',       'priority'=>'0.7', 'changefreq'=>'monthly'],
  ['url'=>'videos',        'priority'=>'0.7', 'changefreq'=>'monthly'],
  // Service hub pages
  ['url'=>'service-balcony-safety-nets',    'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'service-bird-nets',              'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'service-pigeon-nets',            'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'service-sports-nets',            'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'service-invisible-grills',       'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'service-cloth-hangers',          'priority'=>'0.9', 'changefreq'=>'monthly'],
  ['url'=>'service-artificial-grass',       'priority'=>'0.9', 'changefreq'=>'monthly'],
  // Balcony safety net sub-pages
  ['url'=>'balcony-netting-hyderabad',         'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'safety-net-installation-hyderabad', 'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'uv-resistant-nets-hyderabad',       'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'secure-balcony-toddlers-hyderabad', 'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'cat-safety-netting-hyderabad',      'priority'=>'0.80','changefreq'=>'monthly'],
  // Bird net sub-pages
  ['url'=>'anti-bird-netting-hyderabad',            'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'anti-bird-netting-installation-hyderabad','priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'bird-netting-installation-hyderabad',    'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'bird-proofing-balconies-hyderabad',      'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'bird-proofing-gardens-hyderabad',        'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'hdpe-bird-netting-hyderabad',            'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'invisible-bird-mesh-hyderabad',          'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'keep-birds-off-balcony-hyderabad',       'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'nylon-bird-netting-hyderabad',           'priority'=>'0.80','changefreq'=>'monthly'],
  // Pigeon net sub-pages
  ['url'=>'heavy-duty-pigeon-netting-hyderabad', 'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'invisible-pigeon-mesh-hyderabad',     'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'pigeon-control-solutions-hyderabad',  'priority'=>'0.80','changefreq'=>'monthly'],
  // Sports net sub-pages
  ['url'=>'ball-stop-netting-hyderabad',   'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'perimeter-netting-hyderabad',   'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'impact-resistant-mesh-hyderabad','priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'court-divider-nets-hyderabad',  'priority'=>'0.80','changefreq'=>'monthly'],
  // Invisible grill sub-pages
  ['url'=>'high-tensile-invisible-grill-hyderabad',       'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'unobstructed-view-balcony-grill-hyderabad',    'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'high-rise-grill-installation-hyderabad',       'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'stainless-steel-316-grill-hyderabad',          'priority'=>'0.80','changefreq'=>'monthly'],
  // Cloth hanger sub-pages
  ['url'=>'space-saving-hangers-hyderabad',         'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'wall-mounted-drying-rack-hyderabad',     'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'heavy-duty-wooden-hangers-hyderabad',    'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'collapsible-drying-stand-hyderabad',     'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'ceiling-cloth-hangers-hyderabad',        'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'cloth-drying-stands-hyderabad',          'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'luxury-cloth-hangers-hyderabad',         'priority'=>'0.80','changefreq'=>'monthly'],
  ['url'=>'pull-and-dry-hyderabad',                 'priority'=>'0.80','changefreq'=>'monthly'],
  // Child safety pages
  ['url'=>'child-safety-nets-hyderabad',            'priority'=>'0.85','changefreq'=>'monthly'],
  // Geo-specific landing pages
  ['url'=>'safety-nets-financial-district', 'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'safety-nets-hitech-city',        'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'safety-nets-kondapur',           'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'safety-nets-madhapur',           'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'safety-nets-manikonda',          'priority'=>'0.85','changefreq'=>'monthly'],
  ['url'=>'safety-nets-tolichowki',         'priority'=>'0.85','changefreq'=>'monthly'],
  // Utility / trust pages
  ['url'=>'privacy-policy',                 'priority'=>'0.40','changefreq'=>'yearly'],
  ['url'=>'terms-of-service',               'priority'=>'0.40','changefreq'=>'yearly'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($pages as $p){
  $loc = $base.($p['url']==='' ? '' : $p['url']);
  echo '<url>'
     .'<loc>'.htmlspecialchars($loc).'</loc>'
     .'<lastmod>'.$date.'</lastmod>'
     .'<changefreq>'.$p['changefreq'].'</changefreq>'
     .'<priority>'.$p['priority'].'</priority>'
     .'</url>';
}
echo '</urlset>';
