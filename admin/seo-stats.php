<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('seo_stats','view');

// Load data
$dataFile = __DIR__ . '/../_data.php';
include $dataFile; // loads $areas, $svc_data

$totalAreas    = count($areas);
$totalServices = count($svc_data ?? []);
$totalPages    = $totalAreas * $totalServices;
$intentMulti   = 8;
$intentPages   = $totalPages * $intentMulti;
$grandTotal    = $totalPages + $intentPages;

$batchSize   = 50000;
$cityBatches = ceil($totalPages / $batchSize);
$intentBatches = ceil($intentPages / $batchSize);

// Group services by parent
$byParent = [];
foreach (($svc_data ?? []) as $slug => $s) {
    $p = $s['parent'] ?? 'other';
    $byParent[$p][] = $slug;
}

// State distribution of areas
$byState = [];
$zoneStateMap = [
    // Hyderabad zones
    'city'=>'Telangana (Hyderabad)','west'=>'Telangana (Hyderabad)','east'=>'Telangana (Hyderabad)',
    'north'=>'Telangana (Hyderabad)','south'=>'Telangana (Hyderabad)','central'=>'Telangana (Hyderabad)','hyderabad'=>'Telangana (Hyderabad)',
    // Telangana
    'telangana'=>'Telangana','warangal'=>'Telangana','karimnagar'=>'Telangana','nizamabad'=>'Telangana','khammam'=>'Telangana','hanamkonda'=>'Telangana',
    // Andhra Pradesh
    'andhra-pradesh'=>'Andhra Pradesh','visakhapatnam'=>'Andhra Pradesh','vijayawada'=>'Andhra Pradesh','guntur'=>'Andhra Pradesh',
    'tirupati'=>'Andhra Pradesh','nellore'=>'Andhra Pradesh','kurnool'=>'Andhra Pradesh','kadapa'=>'Andhra Pradesh',
    'anantapur'=>'Andhra Pradesh','rajahmundry'=>'Andhra Pradesh','kakinada'=>'Andhra Pradesh',
    // Karnataka
    'karnataka'=>'Karnataka','bengaluru'=>'Karnataka','mysuru'=>'Karnataka','mangaluru'=>'Karnataka','hubli'=>'Karnataka',
    'belagavi'=>'Karnataka','kalaburagi'=>'Karnataka','davangere'=>'Karnataka','shivamogga'=>'Karnataka',
    'tumkur'=>'Karnataka','hassan'=>'Karnataka','raichur'=>'Karnataka','ballari'=>'Karnataka','bidar'=>'Karnataka',
    'vijayapura'=>'Karnataka','bagalkot'=>'Karnataka','dharwad'=>'Karnataka','gadag'=>'Karnataka','haveri'=>'Karnataka',
    'chitradurga'=>'Karnataka','koppal'=>'Karnataka','yadgir'=>'Karnataka','mandya'=>'Karnataka','kolar'=>'Karnataka',
    // Tamil Nadu
    'tamil-nadu'=>'Tamil Nadu','chennai'=>'Tamil Nadu','coimbatore'=>'Tamil Nadu','madurai'=>'Tamil Nadu',
    'trichy'=>'Tamil Nadu','salem'=>'Tamil Nadu','tiruppur'=>'Tamil Nadu','erode'=>'Tamil Nadu',
    'tirunelveli'=>'Tamil Nadu','vellore'=>'Tamil Nadu','thanjavur'=>'Tamil Nadu','puducherry'=>'Tamil Nadu',
    'hosur'=>'Tamil Nadu','krishnagiri'=>'Tamil Nadu','dharmapuri'=>'Tamil Nadu','namakkal'=>'Tamil Nadu',
    'cuddalore'=>'Tamil Nadu','villupuram'=>'Tamil Nadu','tiruvannamalai'=>'Tamil Nadu','kancheepuram'=>'Tamil Nadu',
    'chengalpet'=>'Tamil Nadu','thoothukudi'=>'Tamil Nadu','nagercoil'=>'Tamil Nadu','dindigul'=>'Tamil Nadu',
    'theni'=>'Tamil Nadu','karur'=>'Tamil Nadu','ooty'=>'Tamil Nadu','pollachi'=>'Tamil Nadu',
    'ramanathapuram'=>'Tamil Nadu','sivaganga'=>'Tamil Nadu','nagapattinam'=>'Tamil Nadu','tenkasi'=>'Tamil Nadu',
    // Kerala
    'kerala'=>'Kerala','kochi'=>'Kerala','thiruvananthapuram'=>'Kerala','kozhikode'=>'Kerala',
    'thrissur'=>'Kerala','kollam'=>'Kerala','kannur'=>'Kerala','palakkad'=>'Kerala','malappuram'=>'Kerala',
    'kasaragod'=>'Kerala','wayanad'=>'Kerala','idukki'=>'Kerala','kottayam'=>'Kerala','alappuzha'=>'Kerala',
    'pathanamthitta'=>'Kerala','ernakulam'=>'Kerala',
    // Goa
    'goa'=>'Goa',
    // Maharashtra
    'maharashtra'=>'Maharashtra','mumbai'=>'Maharashtra','pune'=>'Maharashtra','nagpur'=>'Maharashtra',
    'nashik'=>'Maharashtra','aurangabad'=>'Maharashtra','solapur'=>'Maharashtra','kolhapur'=>'Maharashtra',
    'amravati'=>'Maharashtra','nanded'=>'Maharashtra','sangli'=>'Maharashtra','jalgaon'=>'Maharashtra',
    'akola'=>'Maharashtra','latur'=>'Maharashtra','dhule'=>'Maharashtra','ahmednagar'=>'Maharashtra',
    'chandrapur'=>'Maharashtra','satara'=>'Maharashtra','ratnagiri'=>'Maharashtra','wardha'=>'Maharashtra',
    // Madhya Pradesh
    'madhya-pradesh'=>'Madhya Pradesh','bhopal'=>'Madhya Pradesh','indore'=>'Madhya Pradesh',
    'jabalpur'=>'Madhya Pradesh','gwalior'=>'Madhya Pradesh','ujjain'=>'Madhya Pradesh',
    'rewa'=>'Madhya Pradesh','satna'=>'Madhya Pradesh','dewas'=>'Madhya Pradesh','ratlam'=>'Madhya Pradesh',
    'chhindwara'=>'Madhya Pradesh','vidisha'=>'Madhya Pradesh','mandsaur'=>'Madhya Pradesh',
    'mhow'=>'Madhya Pradesh','katni'=>'Madhya Pradesh','balaghat'=>'Madhya Pradesh','betul'=>'Madhya Pradesh',
    'khandwa'=>'Madhya Pradesh','khargone'=>'Madhya Pradesh','shivpuri'=>'Madhya Pradesh','morena'=>'Madhya Pradesh',
    // Odisha
    'odisha'=>'Odisha','bhubaneswar'=>'Odisha','cuttack'=>'Odisha','rourkela'=>'Odisha','sambalpur'=>'Odisha',
    'berhampur'=>'Odisha','balasore'=>'Odisha','jharsuguda'=>'Odisha','puri'=>'Odisha','koraput'=>'Odisha',
    'rayagada'=>'Odisha','angul'=>'Odisha','dhenkanal'=>'Odisha','keonjhar'=>'Odisha','baripada'=>'Odisha',
    'bhadrak'=>'Odisha','bolangir'=>'Odisha','bargarh'=>'Odisha','kalahandi'=>'Odisha','sundargarh'=>'Odisha',
    // Chhattisgarh
    'chhattisgarh'=>'Chhattisgarh','raipur'=>'Chhattisgarh','bhilai'=>'Chhattisgarh','durg'=>'Chhattisgarh',
    'bilaspur'=>'Chhattisgarh','korba'=>'Chhattisgarh','rajnandgaon'=>'Chhattisgarh','jagdalpur'=>'Chhattisgarh',
    'ambikapur'=>'Chhattisgarh','mahasamund'=>'Chhattisgarh','dhamtari'=>'Chhattisgarh',
    // Uttar Pradesh
    'uttar-pradesh'=>'Uttar Pradesh','lucknow'=>'Uttar Pradesh','kanpur'=>'Uttar Pradesh','agra'=>'Uttar Pradesh',
    'varanasi'=>'Uttar Pradesh','meerut'=>'Uttar Pradesh','prayagraj'=>'Uttar Pradesh','ghaziabad'=>'Uttar Pradesh',
    'noida'=>'Uttar Pradesh','aligarh'=>'Uttar Pradesh','bareilly'=>'Uttar Pradesh','moradabad'=>'Uttar Pradesh',
    'gorakhpur'=>'Uttar Pradesh','jhansi'=>'Uttar Pradesh','mathura'=>'Uttar Pradesh','saharanpur'=>'Uttar Pradesh',
    'azamgarh'=>'Uttar Pradesh','ballia'=>'Uttar Pradesh','ayodhya'=>'Uttar Pradesh','firozabad'=>'Uttar Pradesh',
    'greater-noida'=>'Uttar Pradesh','bulandshahr'=>'Uttar Pradesh','hapur'=>'Uttar Pradesh','etawah'=>'Uttar Pradesh',
    // Rajasthan
    'rajasthan'=>'Rajasthan','jaipur'=>'Rajasthan','jodhpur'=>'Rajasthan','udaipur'=>'Rajasthan',
    'kota'=>'Rajasthan','ajmer'=>'Rajasthan','bikaner'=>'Rajasthan','alwar'=>'Rajasthan',
    'bharatpur'=>'Rajasthan','sikar'=>'Rajasthan','pali'=>'Rajasthan','bhilwara'=>'Rajasthan',
    'ganganagar'=>'Rajasthan','jaisalmer'=>'Rajasthan','barmer'=>'Rajasthan','chittorgarh'=>'Rajasthan',
    'tonk'=>'Rajasthan','dausa'=>'Rajasthan','jhunjhunu'=>'Rajasthan','churu'=>'Rajasthan',
    'nagaur'=>'Rajasthan','dungarpur'=>'Rajasthan','banswara'=>'Rajasthan','hanumangarh'=>'Rajasthan',
    // Gujarat
    'gujarat'=>'Gujarat','ahmedabad'=>'Gujarat','surat'=>'Gujarat','vadodara'=>'Gujarat','rajkot'=>'Gujarat',
    'bhavnagar'=>'Gujarat','jamnagar'=>'Gujarat','gandhinagar'=>'Gujarat','junagadh'=>'Gujarat',
    'anand'=>'Gujarat','nadiad'=>'Gujarat','morbi'=>'Gujarat','surendranagar'=>'Gujarat',
    'bharuch'=>'Gujarat','navsari'=>'Gujarat','valsad'=>'Gujarat','amreli'=>'Gujarat','mehsana'=>'Gujarat',
    // West Bengal
    'west-bengal'=>'West Bengal','kolkata'=>'West Bengal','howrah'=>'West Bengal','siliguri'=>'West Bengal',
    'durgapur'=>'West Bengal','asansol'=>'West Bengal','bardhaman'=>'West Bengal','kharagpur'=>'West Bengal',
    'haldia'=>'West Bengal','darjeeling'=>'West Bengal','jalpaiguri'=>'West Bengal','malda'=>'West Bengal',
    'murshidabad'=>'West Bengal','nadia'=>'West Bengal','purulia'=>'West Bengal','bankura'=>'West Bengal',
    // Bihar
    'bihar'=>'Bihar','patna'=>'Bihar','gaya'=>'Bihar','muzaffarpur'=>'Bihar','bhagalpur'=>'Bihar',
    'darbhanga'=>'Bihar','begusarai'=>'Bihar','munger'=>'Bihar','purnia'=>'Bihar','samastipur'=>'Bihar',
    'nalanda'=>'Bihar','siwan'=>'Bihar','rohtas'=>'Bihar','madhubani'=>'Bihar','sitamarhi'=>'Bihar',
    // Delhi
    'delhi'=>'Delhi NCR',
    // Haryana
    'haryana'=>'Haryana','gurugram'=>'Haryana','faridabad'=>'Haryana','ambala'=>'Haryana',
    'hisar'=>'Haryana','rohtak'=>'Haryana','panipat'=>'Haryana','sonepat'=>'Haryana',
    'karnal'=>'Haryana','kurukshetra'=>'Haryana','yamunanagar'=>'Haryana','rewari'=>'Haryana',
    'bhiwani'=>'Haryana','sirsa'=>'Haryana','panchkula'=>'Haryana','manesar'=>'Haryana',
    // Punjab
    'punjab'=>'Punjab','chandigarh'=>'Punjab','ludhiana'=>'Punjab','amritsar'=>'Punjab',
    'jalandhar'=>'Punjab','patiala'=>'Punjab','bathinda'=>'Punjab','mohali'=>'Punjab',
    'pathankot'=>'Punjab','hoshiarpur'=>'Punjab','zirakpur'=>'Punjab','sangrur'=>'Punjab',
    // Uttarakhand
    'uttarakhand'=>'Uttarakhand','dehradun'=>'Uttarakhand','haridwar'=>'Uttarakhand','rishikesh'=>'Uttarakhand',
    'haldwani'=>'Uttarakhand','roorkee'=>'Uttarakhand','rudrapur'=>'Uttarakhand','nainital'=>'Uttarakhand',
    // Jharkhand
    'jharkhand'=>'Jharkhand','ranchi'=>'Jharkhand','jamshedpur'=>'Jharkhand','dhanbad'=>'Jharkhand',
    'bokaro'=>'Jharkhand','hazaribagh'=>'Jharkhand','deoghar'=>'Jharkhand','giridih'=>'Jharkhand',
    // Assam & North East
    'assam'=>'Assam','guwahati'=>'Assam','silchar'=>'Assam','dibrugarh'=>'Assam',
    'north-east'=>'North East India',
    // Himachal Pradesh
    'himachal-pradesh'=>'Himachal Pradesh',
];
foreach ($areas as $slug => $a) {
    $state = $zoneStateMap[$a['zone'] ?? ''] ?? 'Other';
    $byState[$state] = ($byState[$state] ?? 0) + 1;
}
arsort($byState);

// Cricket-related services
$cricketSvcs = array_filter(array_keys($svc_data ?? []), fn($k) => str_contains($k,'cricket') || str_contains($k,'braided') || str_contains($k,'box-cricket') || str_contains($k,'turf') || str_contains($k,'pitch-'));
$cricketPageCount = count($cricketSvcs) * $totalAreas;

$pageTitle = 'SEO Statistics';
require_once __DIR__ . '/includes/layout.php';
?>
<style>
.stat-card { background:#fff; border-radius:14px; padding:1.5rem; box-shadow:0 2px 16px rgba(0,0,0,.07); border:1px solid #e8ecf0; }
.stat-card .num { font-size:2.2rem; font-weight:900; line-height:1; }
.stat-card .lbl { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#6b7280; margin-top:.3rem; }
.stat-card.red { border-top:3px solid #dc2626; }
.stat-card.blue { border-top:3px solid #2563eb; }
.stat-card.green { border-top:3px solid #16a34a; }
.stat-card.amber { border-top:3px solid #d97706; }
.stat-card.purple { border-top:3px solid #7c3aed; }
.progress-bar-outer { background:#f3f4f6; border-radius:10px; height:10px; overflow:hidden; }
.progress-bar-inner { height:10px; border-radius:10px; background:linear-gradient(90deg,#dc2626,#ef4444); }
.seo-table th { background:#f8f9fc; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; }
.seo-table td { font-size:.87rem; vertical-align:middle; }
.badge-count { background:#dc2626; color:#fff; border-radius:20px; padding:2px 10px; font-size:.72rem; font-weight:700; }
</style>

<!-- Page Title -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h4 mb-0 fw-800">SEO Statistics</h1>
    <p class="text-muted small mb-0">Total indexed pages, service coverage and keyword statistics</p>
  </div>
  <div class="d-flex gap-2">
    <a href="../sitemap-index.xml" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-sitemap me-1"></i>Sitemap</a>
    <a href="../cricket-nets.php" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fa fa-baseball-ball me-1"></i>Cricket Nets Page</a>
  </div>
</div>

<!-- TOP STATS -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-2">
    <div class="stat-card red text-center">
      <div class="num text-danger"><?= number_format($grandTotal) ?></div>
      <div class="lbl">Total SEO Pages</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card blue text-center">
      <div class="num" style="color:#2563eb"><?= number_format($totalPages) ?></div>
      <div class="lbl">Base City Pages</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card green text-center">
      <div class="num" style="color:#16a34a"><?= number_format($intentPages) ?></div>
      <div class="lbl">Intent Pages</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card amber text-center">
      <div class="num" style="color:#d97706"><?= $totalServices ?></div>
      <div class="lbl">Keywords/Services</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card purple text-center">
      <div class="num" style="color:#7c3aed"><?= $totalAreas ?></div>
      <div class="lbl">Cities &amp; Areas</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="stat-card text-center" style="border-top:3px solid #0ea5e9">
      <div class="num" style="color:#0ea5e9"><?= number_format($cricketPageCount) ?></div>
      <div class="lbl">Cricket Pages</div>
    </div>
  </div>
</div>

<div class="row g-3">

<!-- LEFT COLUMN -->
<div class="col-lg-7">

  <!-- Service Category Breakdown -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom fw-700" style="font-size:.9rem"><i class="fa fa-list text-danger me-2"></i>Services by Category</div>
    <div class="card-body p-0">
      <table class="table seo-table mb-0">
        <thead><tr><th>Service Page</th><th>Keywords</th><th>Pages (×<?= $totalAreas ?> areas)</th><th>% of Total</th></tr></thead>
        <tbody>
          <?php
          $parentLabels = [
              'service-balcony-safety-nets'=>'🏠 Balcony Safety Nets',
              'service-bird-nets'=>'🐦 Bird Nets',
              'service-pigeon-nets'=>'🕊️ Pigeon Nets',
              'service-sports-nets'=>'🏏 Sports & Cricket Nets',
              'service-invisible-grills'=>'🔲 Invisible Grills',
              'service-artificial-grass'=>'🌿 Artificial Grass',
              'service-cloth-hangers'=>'👕 Cloth Hangers',
              'other'=>'📦 Other',
          ];
          arsort($byParent);
          foreach ($byParent as $p => $svcs):
              $cnt = count($svcs);
              $pages = $cnt * $totalAreas;
              $pct = round($pages / $totalPages * 100, 1);
              $label = $parentLabels[$p] ?? $p;
          ?>
          <tr>
            <td><a href="../<?= htmlspecialchars($p) ?>.php" target="_blank" style="color:inherit;text-decoration:none"><?= $label ?></a></td>
            <td><span class="badge-count"><?= $cnt ?></span></td>
            <td><?= number_format($pages) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress-bar-outer flex-grow-1"><div class="progress-bar-inner" style="width:<?= $pct ?>%"></div></div>
                <small class="text-muted"><?= $pct ?>%</small>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Cricket Keywords Detail -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom fw-700" style="font-size:.9rem"><i class="fa fa-baseball-ball text-danger me-2"></i>Cricket &amp; Sports Keywords (<?= count($cricketSvcs) ?> keywords × <?= $totalAreas ?> areas = <?= number_format(count($cricketSvcs) * $totalAreas) ?> pages)</div>
    <div class="card-body">
      <div style="display:flex;flex-wrap:wrap;gap:.35rem">
        <?php foreach ($cricketSvcs as $slug):
          $display = ($svc_data[$slug]['display'] ?? $slug);
        ?>
        <a href="../<?= htmlspecialchars($slug) ?>-in-hyderabad" target="_blank" style="background:#fff5f5;border:1px solid #fecaca;border-radius:20px;padding:.25rem .75rem;font-size:.75rem;color:#dc2626;text-decoration:none;white-space:nowrap"><?= htmlspecialchars($display) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Sitemap Status -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom fw-700" style="font-size:.9rem"><i class="fa fa-sitemap text-danger me-2"></i>Sitemap Files</div>
    <div class="card-body p-0">
      <table class="table seo-table mb-0">
        <thead><tr><th>Sitemap File</th><th>URLs</th><th>Status</th></tr></thead>
        <tbody>
          <tr>
            <td><a href="../sitemap-index.xml" target="_blank">sitemap-index.xml</a></td>
            <td><?= $cityBatches + $intentBatches + 2 ?> sitemaps</td>
            <td><span class="badge bg-success">Active</span></td>
          </tr>
          <tr>
            <td><a href="../sitemap-static.xml" target="_blank">sitemap-static.xml</a></td>
            <td>Core pages</td>
            <td><span class="badge bg-success">Active</span></td>
          </tr>
          <?php for ($i = 1; $i <= $cityBatches; $i++): ?>
          <tr>
            <td><a href="../sitemap-city-<?= $i ?>.xml" target="_blank">sitemap-city-<?= $i ?>.xml</a></td>
            <td><?= number_format(min($batchSize, $totalPages - ($i-1)*$batchSize)) ?> URLs</td>
            <td><span class="badge bg-success">Active</span></td>
          </tr>
          <?php endfor; ?>
          <?php for ($i = 1; $i <= $intentBatches; $i++): ?>
          <tr>
            <td><a href="../sitemap-intent-<?= $i ?>.xml" target="_blank">sitemap-intent-<?= $i ?>.xml</a></td>
            <td><?= number_format(min($batchSize, $intentPages - ($i-1)*$batchSize)) ?> URLs</td>
            <td><span class="badge bg-success">Active</span></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- RIGHT COLUMN -->
<div class="col-lg-5">

  <!-- Geographic Coverage -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom fw-700" style="font-size:.9rem"><i class="fa fa-map-marker-alt text-danger me-2"></i>Geographic Coverage (<?= $totalAreas ?> areas)</div>
    <div class="card-body">
      <?php foreach ($byState as $state => $cnt):
        $pct = round($cnt / $totalAreas * 100);
      ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between mb-1">
          <span style="font-size:.82rem;font-weight:600"><?= htmlspecialchars($state) ?></span>
          <span style="font-size:.78rem;color:#6b7280"><?= $cnt ?> areas</span>
        </div>
        <div class="progress-bar-outer"><div class="progress-bar-inner" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#2563eb,#3b82f6)"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SEO Checklist -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom fw-700" style="font-size:.9rem"><i class="fa fa-check-circle text-danger me-2"></i>SEO Checklist</div>
    <div class="card-body p-0">
      <?php
      $checks = [
          ['Dynamic city/service pages', true, number_format($totalPages).' pages'],
          ['Intent variant pages (buy/price/near me)', true, number_format($intentPages).' pages'],
          ['Sitemap index', true, ($cityBatches+$intentBatches+2).' files'],
          ['Cricket Nets landing page', true, '/cricket-nets.php'],
          ['Cricket in nav menu', true, 'Header updated'],
          ['Russea™ in JSON-LD schema', true, 'All service pages'],
          ['Google Search Console verified', true, 'Meta tag added'],
          ['Facebook Pixel', true, 'All pages'],
          ['Google Ads (gtag)', true, 'All pages'],
          ['Structured data (LocalBusiness)', true, 'header.php'],
          ['Canonical URLs', true, 'Each service page'],
          ['Mobile-responsive', true, 'Bootstrap + responsive CSS'],
          ['Page speed (PHP native, no framework)', true, 'Fast PHP render'],
          ['Google Maps linked', true, 'Footer + contact page'],
      ];
      foreach ($checks as $c): ?>
      <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <i class="fa <?= $c[1] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
        <div class="flex-grow-1" style="font-size:.82rem"><?= $c[0] ?></div>
        <div style="font-size:.72rem;color:#6b7280"><?= $c[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom fw-700" style="font-size:.9rem"><i class="fa fa-link text-danger me-2"></i>Quick SEO Links</div>
    <div class="card-body d-grid gap-2">
      <a href="../cricket-nets.php" target="_blank" class="btn btn-outline-danger btn-sm"><i class="fa fa-baseball-ball me-1"></i>Cricket Nets Landing Page</a>
      <a href="../service-sports-nets.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa fa-running me-1"></i>Sports Nets Page</a>
      <a href="../sitemap-index.xml" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa fa-sitemap me-1"></i>Sitemap Index</a>
      <a href="https://search.google.com/search-console" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa fa-search me-1"></i>Google Search Console</a>
      <a href="https://www.google.com/webmasters/tools/submit-url" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fab fa-google me-1"></i>Submit Sitemap to Google</a>
      <a href="../nets.php?sv=cricket-nets&ar=hyderabad" target="_blank" class="btn btn-outline-danger btn-sm"><i class="fa fa-eye me-1"></i>Test: Cricket Nets in Hyderabad</a>
      <a href="../nets.php?sv=box-cricket-nets&ar=bengaluru" target="_blank" class="btn btn-outline-danger btn-sm"><i class="fa fa-eye me-1"></i>Test: Box Cricket in Bengaluru</a>
      <a href="../nets.php?sv=25mm-cricket-net&ar=chennai" target="_blank" class="btn btn-outline-danger btn-sm"><i class="fa fa-eye me-1"></i>Test: 2.5mm Cricket Net in Chennai</a>
    </div>
  </div>

</div><!-- /col-lg-5 -->
</div><!-- /row -->
