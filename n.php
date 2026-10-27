<?php require_once __DIR__ . '/includes/whatsapp_helper.php'; ?>
<?php
// n.php — Intent-variant pages now redirect to base page with anchor
// /{service}-{intent}-in-{area} → 301 → /{service}-in-{area}#{intent}
// All intent content (dealers/price/installation/suppliers/near-me/wholesalers/manufacturers/rate)
// is now included directly on every nets.php page for richer, comprehensive content.

require '_data.php'; // provides $areas, $svc_data

$sv = strtolower(trim($_GET['service'] ?? ''));
$it = strtolower(trim($_GET['intent']  ?? ''));
$ar = strtolower(trim($_GET['area']    ?? ''));

// Fallback 1: If $sv-$it is an actual base service (e.g. invisible-grill-manufacturers)
if (!isset($svc_data[$sv]) && isset($svc_data[$sv . '-' . $it])) {
    $_GET['service'] = $sv . '-' . $it;
    unset($_GET['intent']);
    require __DIR__ . '/nets.php';
    exit;
}

// Fallback 2: Singular / Plural variation matching
if (!isset($svc_data[$sv])) {
    if (isset($svc_data[$sv . 's'])) {
        $sv = $sv . 's';
    } elseif (substr($sv, -1) === 's' && isset($svc_data[substr($sv, 0, -1)])) {
        $sv = substr($sv, 0, -1);
    }
}

if (!isset($svc_data[$sv]) || !isset($areas[$ar])) {
    http_response_code(404);
    include '404.php';
    exit;
}


$s = $svc_data[$sv];
$a = $areas[$ar];
$A = $a['label'] ?? $a['name'] ?? ucfirst($ar);
$near = $a['near'] ?? [];
$city_name = $a['city'] ?? $A;

require_once __DIR__ . "/includes/geo_resolver.php";
$geoResolved = resolveSmartGeoLocation($ar);
$geoParts = array_map("trim", explode(",", $geoResolved));
$resolvedState = end($geoParts);
if (!empty($resolvedState) && $resolvedState !== "India") {
    $state_name = $resolvedState;
} else {
    $state_name = "Telangana";
}

$is_hyd = (stripos($geoResolved, "Hyderabad") !== false || stripos($geoResolved, "Telangana") !== false);
if ($is_hyd) {
    $brandLoc = ($city_name === "Hyderabad" || $A === "Hyderabad") ? "Hyderabad, Telangana" : $A . ", Hyderabad, Telangana";
} else {
    $brandLoc = $A . ", " . $state_name;
}

$page_canonical = "https://nettingteam.com/" . $sv . "-" . $it . "-in-" . $ar;
$canon = "/" . $sv . "-" . $it . "-in-" . $ar;
$req = strtok($_SERVER["REQUEST_URI"] ?? "", "?");
if ($req && $req !== $canon && $req !== "/" . ltrim($canon, "/")) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $canon);
    exit;
}
$intents = [
'dealers' => [
  'label'    => 'Dealers',
  'title'    => $s['display'].' Dealers in '.$brandLoc.' | NettingTeam',
  'h1'       => 'Authorized '.$s['display'].' Dealers in '.$brandLoc.'',
  'h2'       => 'Where to Buy '.$s['display'].' in '.$A.' — Authorized Dealer Network',
  'hero'     => 'Looking for authorized '.$s['display'].' dealers in '.$A.'? NettingTeam is the official Russea™ dealer in '.$A.' — genuine products, factory prices, 5-year warranty.',
  'body'     => '<p>NettingTeam is the authorized Russea™ brand dealer for '.$s['display'].' in <strong>'.$brandLoc.'</strong>. As a certified authorized dealer, we stock only genuine Russea™ materials — no counterfeits, no substandard alternatives. Customers in '.$A.' can walk in to our store, inspect stock, verify Russea™ authenticity certificates, and purchase retail or in wholesale quantities.</p>
<p>Being an authorized dealer means you get the <strong>manufacturer\'s full warranty</strong> on every product, certified installation by our trained team, and direct after-sales support. Unauthorized sellers of safety nets in '.$A.' cannot offer Russea™ genuine products or manufacturer-backed warranties. Always buy from an authorized dealer like NettingTeam.</p>
<p>Our dealer network covers the entire Hyderabad city. From '.$A.' we serve all surrounding localities with walk-in at our Karmanghat offices and next-day installation. Retail customers, bulk buyers, contractors and builders are all welcome. Call <strong>9912399334</strong> for dealer pricing.</p>',
  'intent_title' => 'Why Buy from an Authorized '.$s['display'].' Dealer in '.$A.'?',
  'points' => ['Genuine Russea™ certified materials — no counterfeits','Manufacturer-backed 5-year warranty on all products','Dealer price = factory-direct, no distributor markup','Walk in, inspect stock, buy retail or wholesale','After-sales support and warranty claims handled directly','Certified installation team included with purchase'],
  'faqs' => [
    ['Who are the authorized '.$s['display'].' dealers in '.$A.'?','NettingTeam is the official Russea™ authorized dealer for '.$s['display'].' in '.$A.'. We stock genuine Russea™ certified materials. Call 9912399334.'],
    ['Why is it important to buy from an authorized '.$s['display'].' dealer in '.$A.'?','Authorized dealers provide genuine materials, manufacturer warranty and certified installation. Unauthorized sellers cannot guarantee product authenticity or warranty.'],
    ['Do '.$A.' '.$s['display'].' dealers offer installation?','Yes — NettingTeam includes professional certified installation with every '.$s['display'].' purchase in '.$A.'.'],
    ['What is the '.$s['display'].' dealer price in '.$A.'?','As an authorized Russea™ dealer we offer factory-direct pricing with no middleman markup. Call 9912399334 for an exact quote tailored to your property in '.$A.'.'],
    ['How to verify an authorized '.$s['display'].' dealer in '.$A.'?','Look for Russea™ authorized dealer certificate, ISO certification documents, and verified Google reviews. NettingTeam displays all credentials.'],
  ],
],
'suppliers' => [
  'label'    => 'Suppliers',
  'title'    => $s['display'].' Wholesale Suppliers in '.$brandLoc.' | NettingTeam',
  'h1'       => $s['display'].' Wholesale Suppliers in '.$brandLoc.'',
  'h2'       => 'Direct '.$s['display'].' Supply in '.$A.' — Factory to Customer',
  'hero'     => 'Wholesale '.$s['display'].' suppliers in '.$A.' — NettingTeam supplies directly from Russea™ factory. Bulk orders welcome. Same-day dispatch.',
  'body'     => '<p>NettingTeam operates as a wholesale supplier of '.$s['display'].' in <strong>'.$brandLoc.'</strong>. We source all materials directly from Russea™ manufacturing plant — eliminating middlemen and passing factory-direct savings to customers. Whether you need one unit or bulk wholesale quantities, our supply chain ensures consistent quality and availability for '.$A.' customers.</p>
<p>As a direct supplier, we maintain a ready stock of '.$s['display'].' at our warehouse. Same-day dispatch is available for stock items. Contractors, builders, housing societies and individual customers in '.$A.' can place bulk supply orders with minimum lead times. We supply Russea™ HDPE nets, hardware, anchors and all accessories as a single-source supplier.</p>
<p>Our '.$A.' wholesale supply pricing is transparent — no hidden charges, no variable rates. Bulk discounts apply for orders above 500 sqft. We also provide supply-only (without installation) for customers who have their own installation teams. Call <strong>9912399334</strong> for wholesale supply pricing in '.$A.'.</p>',
  'intent_title' => 'NettingTeam — '.$s['display'].' Supplier in '.$A.'',
  'points' => ['Direct from Russea™ factory — no middlemen','Wholesale and retail supply both available','Same-day dispatch for stock items in '.$A,'Bulk discount above 500 sqft orders','Supply-only option for contractors with own teams','All accessories and hardware supplied together'],
  'faqs' => [
    ['Who supplies '.$s['display'].' in bulk in '.$A.'?','NettingTeam — authorized wholesale supplier of '.$s['display'].' in '.$A.'. Direct from Russea™ manufacturing. Call 9912399334 for bulk pricing.'],
    ['What is the minimum order for '.$s['display'].' wholesale supply in '.$A.'?','No strict minimum for retail orders. Wholesale pricing applies above 500 sqft. Call 9912399334 for wholesale rate sheet.'],
    ['Do you supply '.$s['display'].' without installation in '.$A.'?','Yes — supply-only available for contractors and builders with their own installation teams.'],
    ['How quickly can you supply '.$s['display'].' in '.$A.'?','Same-day dispatch for in-stock items. Call 9912399334 for current stock availability in '.$A.'.'],
    ['What is the '.$s['display'].' wholesale price in '.$A.'?','Wholesale pricing is available on call — rates depend on quantity and specification. Call 9912399334 for the current wholesale rate sheet for '.$A.'.'],
  ],
],
'price' => [
  'label'    => 'Price',
  'title'    => $s['display'].' Price in '.$brandLoc.' — Free Quote | NettingTeam',
  'h1'       => $s['display'].' Price in '.$brandLoc.' — Free Quote & Site Survey',
  'h2'       => $s['display'].' Pricing in '.$A.' — What Affects Your Quote',
  'hero'     => $s['display'].' price in '.$A.' depends on your property dimensions, floor height and material grade. No hidden charges. Visit our office at Karmanghat and exact quote. NettingTeam — authorized Russea™ dealer.',
  'body'     => '<p>The price of '.$s['display'].' in <strong>'.$brandLoc.'</strong> depends on several factors — exact area to be covered, floor height, net type (mesh size, material grade) and installation complexity. NettingTeam provides transparent, all-inclusive pricing with no hidden charges. The best way to get the right price is a visit our Karmanghat office where our technician measures your property and provides an exact written quote.</p>
<p><strong>What is included in our '.$A.' price?</strong> Our quoted price covers all materials (Russea™ certified nets, anchors, hooks, and hardware), installation labor by our certified team, and a 5-year written warranty. There are no extra charges for basic residential installations in '.$A.'. Our Russea™ materials come directly from the manufacturer — so you pay factory-direct prices, not inflated retail markups.</p>
<p>Factors that affect the final price in '.$A.': (1) Size — larger areas cost less per sqft due to economy of scale; (2) Floor height — above 10th floor requires additional safety equipment for our team; (3) Mesh size and material grade — standard HDPE vs premium UV Grade-A; (4) Custom shapes — non-rectangular areas require custom cutting. Call <strong>9912399334</strong> for a on-site quote — visit our Karmanghat office in '.$A.'.</p>',
  'intent_title' => $s['display'].' Price Factors in '.$A.'',
  'points' => ['All-inclusive quote — materials, labor and 5-year warranty','No hidden charges — one transparent invoice','No extra charges for basic residential in '.$A,'Bulk and wholesale pricing for large orders','Factory-direct Russea™ prices — no middleman markup','On-site quote — visit our Karmanghat office with exact measurements'],
  'faqs' => [
    ['What is the '.$s['display'].' price in '.$A.'?','Price depends on area size, floor height and mesh grade. We provide an exact written quote after a visit our office at Karmanghat — call 9912399334.'],
    ['Why does '.$s['display'].' price vary in '.$A.'?','Price varies by area size, floor height, mesh quality and installation complexity. NettingTeam provides transparent itemized quotes.'],
    ['Is '.$s['display'].' price the same on all floors in '.$A.'?','Basic price is same. Floors above 10th may have a small additional charge due to safety equipment needs for installation team.'],
    ['Are there any hidden charges for '.$s['display'].' in '.$A.'?','No — our quotes in '.$A.' include all materials, labor and warranty. No surprise charges.'],
    ['Can I get EMI for '.$s['display'].' in '.$A.'?','Yes — UPI, bank transfer and installment options available for large orders in '.$A.'.'],
  ],
],
'near-me' => [
  'label'    => 'Near Me',
  'title'    => $s['display'].' Near '.$brandLoc.' — Same Day Available | NettingTeam',
  'h1'       => $s['display'].' Near '.$brandLoc.' — Immediate Availability',
  'h2'       => $s['display'].' Service Near '.$A.' — In Stock, Walk-In at Our Karmanghat Office',
  'hero'     => 'Searching for '.$s['display'].' near '.$A.'? NettingTeam is your nearest authorized dealer — stock available, walk-in at our Karmanghat office, next-day installation.',
  'body'     => '<p>NettingTeam is the nearest authorized '.$s['display'].' dealer and installation service provider for residents of <strong>'.$brandLoc.'</strong>. Our team is stationed to serve '.$A.' and all nearby areas — we can reach your location for a visit our office at Karmanghat on the same day you call. Materials are always in stock and ready for immediate dispatch.</p>
<p>Why NettingTeam is the best nearby option for '.$s['display'].' near '.$A.': (1) We maintain ready stock at our warehouse — no waiting for orders to arrive; (2) Our installation team covers '.$A.' as a priority service zone; (3) Walk-in at our Karmanghat office, next-day installation for most properties; (4) You can also visit our store to inspect materials before purchase. Our showroom is accessible from '.$A.' within a short drive.</p>
<p>Nearby areas we serve from '.$A.': '.implode(', ', array_slice($near, 0, 5)).'. No matter which part of Hyderabad you are in, our team can reach you quickly. Call <strong>9912399334</strong> now and our '.$A.' area team will call you back within minutes.</p>',
  'intent_title' => 'Why NettingTeam is the Best '.$s['display'].' Service Near '.$A.'',
  'points' => ['Nearest authorized Russea™ dealer to '.$A,'In-stock materials — no waiting','Same-day visit our office at Karmanghat to '.$A.' and surrounding areas','Walk-in welcome — inspect materials before buying','Team based in Hyderabad — fastest response time','Serves '.(implode(', ', array_slice($near,0,3))).' and all nearby areas'],
  'faqs' => [
    ['Where can I find '.$s['display'].' near '.$A.'?','NettingTeam — the authorized '.$s['display'].' dealer nearest to '.$A.'. Walk-in at our Karmanghat office available. Call 9912399334.'],
    ['Is '.$s['display'].' available in stock near '.$A.'?','Yes — NettingTeam maintains ready stock. Immediate dispatch available near '.$A.'.'],
    ['How soon can you install '.$s['display'].' near '.$A.'?','Walk-in at our Karmanghat office, installation next day for most properties near '.$A.'.'],
    ['Can I visit the store near '.$A.' before buying?','Yes — walk-in welcome. Inspect Russea™ stock, check quality and get retail or wholesale pricing.'],
    ['Do you serve areas around '.$A.'?','Yes — we serve '.$A.' and all surrounding areas including '.implode(', ', array_slice($near,0,3)).'. Call 9912399334.'],
  ],
],
'installation' => [
  'label'    => 'Installation',
  'title'    => $s['display'].' Installation in '.$brandLoc.' — Expert Service | NettingTeam',
  'h1'       => $s['display'].' Installation Service in '.$brandLoc.'',
  'h2'       => 'Professional '.$s['display'].' Installation in '.$A.' — Certified Experts',
  'hero'     => 'Professional '.$s['display'].' installation in '.$A.' by NettingTeam certified team. 5000+ completed jobs. Walk-in at our Karmanghat office. 5-year warranty.',
  'body'     => '<p>NettingTeam provides professional '.$s['display'].' installation service across <strong>'.$brandLoc.'</strong> by a certified team with 10+ years of experience. Our installation process is precise, safe and designed to deliver maximum performance and durability. Every installation in '.$A.' follows our standardized protocol — from site survey to final inspection.</p>
<p><strong>Our installation process in '.$A.':</strong> Step 1 — Visit our office at Karmanghat and measurements by our certified technician; Step 2 — Material selection based on your specific needs and floor height; Step 3 — Professional installation using precision drilling, corrosion-resistant anchors and Russea™ certified nets; Step 4 — Post-installation tension check and quality inspection; Step 5 — 5-year written warranty issued.</p>
<p>Our installation team uses professional-grade tools, harness safety equipment for high-rise work, and follows all safety protocols during installation in '.$A.'. No mess, no damage to walls (only precision-drilled anchor points), and installation is typically completed in 2-4 hours for standard balconies. Call <strong>9912399334</strong> for walk-in at our Karmanghat office in '.$A.'.</p>',
  'intent_title' => $s['display'].' Installation Process in '.$A.'',
  'points' => ['Certified installation team — 10+ years experience','Visit our Karmanghat office and measurements in '.$A,'Precision drilling with corrosion-resistant anchors','Harness safety for all high-rise installations','2-4 hours typical installation time in '.$A,'5-year written warranty from installation date'],
  'faqs' => [
    ['How long does '.$s['display'].' installation take in '.$A.'?','Most residential installations in '.$A.' are completed in 2-4 hours. Large properties may take 1 full day.'],
    ['Who installs '.$s['display'].' in '.$A.'?','NettingTeam certified installation team — trained professionals with 10+ years experience in '.$A.' and across Hyderabad.'],
    ['Do I need to be present during '.$s['display'].' installation in '.$A.'?','Yes, someone should be present for access. Our team will inform you of the exact installation time.'],
    ['Is there any wall damage during installation in '.$A.'?','Minimal — only precision anchor points are drilled. Our team patches as needed and leaves the area clean.'],
    ['What warranty is given after '.$s['display'].' installation in '.$A.'?','5-year written warranty on all materials and workmanship from the date of installation.'],
  ],
],
'wholesalers' => [
  'label'    => 'Wholesalers',
  'title'    => $s['display'].' Wholesalers in '.$brandLoc.' | NettingTeam',
  'h1'       => $s['display'].' Wholesalers in '.$brandLoc.' — Bulk Pricing',
  'h2'       => 'Wholesale '.$s['display'].' in '.$A.' — Bulk Quantities, Distributor Rates',
  'hero'     => 'Wholesale '.$s['display'].' in '.$A.' — NettingTeam offers distributor-level bulk pricing. Contractors and builders welcome. Same-day supply.',
  'body'     => '<p>NettingTeam is the leading wholesaler of '.$s['display'].' in <strong>'.$brandLoc.'</strong>. We operate as a wholesale distributor for Russea™ certified safety nets and related products — supplying contractors, builders, housing societies, real estate developers and bulk buyers in '.$A.' at wholesale rates. Our wholesale pricing starts where retail pricing ends — significant savings on bulk orders.</p>
<p>As a registered wholesale distributor, NettingTeam maintains the largest stock of Russea™ nets and accessories in Hyderabad. Bulk orders placed before noon are dispatched same day to addresses in '.$A.' and surrounding areas. We issue proper GST invoices for all wholesale transactions and provide material certificates for project compliance requirements.</p>
<p>Our wholesale minimum in '.$A.' is flexible — we work with contractors needing 200 sqft and builders needing 50,000 sqft. Volume pricing tiers apply: the more you order, the lower your per-sqft cost. Call <strong>9912399334</strong> for our current wholesale rate card for '.$A.'.</p>',
  'intent_title' => 'Wholesale '.$s['display'].' Benefits in '.$A.'',
  'points' => ['Distributor-level pricing for bulk orders','Minimum order: 200 sqft — flexible for all project sizes','GST invoices and material certificates provided','Same-day dispatch for orders placed before noon','Volume pricing tiers — more you buy, lower per-sqft cost','Russea™ ISO 9001:2015 certified materials only'],
  'faqs' => [
    ['Who are the '.$s['display'].' wholesalers in '.$A.'?','NettingTeam — authorized Russea™ wholesale distributor for '.$s['display'].' in '.$A.'. Call 9912399334 for wholesale rate card.'],
    ['What is the minimum wholesale order in '.$A.'?','Our minimum is flexible — starts from 200 sqft. Wholesale pricing applies above 500 sqft. Call for exact tiers.'],
    ['Do you provide GST invoices for wholesale in '.$A.'?','Yes — all wholesale transactions include proper GST invoices and Russea™ material certificates.'],
    ['How quickly can bulk orders be dispatched in '.$A.'?','Same-day dispatch for orders placed before noon in '.$A.'.'],
    ['What are the wholesale rates for '.$s['display'].' in '.$A.'?','Wholesale rates depend on order quantity and specification. Call 9912399334 for the current wholesale rate sheet for '.$A.'.'],
  ],
],
'manufacturers' => [
  'label'    => 'Manufacturers',
  'title'    => $s['display'].' Manufacturers in '.$brandLoc.' — Russea™ | NettingTeam',
  'h1'       => $s['display'].' from Manufacturer in '.$brandLoc.' — Russea™ ISO 9001:2015',
  'h2'       => 'Manufacturer-Direct '.$s['display'].' in '.$A.' — Russea™ ISO Certified',
  'hero'     => 'Manufacturer-direct '.$s['display'].' in '.$A.' — Russea™ ISO 9001:2015 certified. Factory quality, no middlemen, genuine materials. NettingTeam authorized dealer.',
  'body'     => '<p>NettingTeam supplies '.$s['display'].' in <strong>'.$A.'</strong> directly from Russea™ — South India\'s #1 ISO 9001:2015 certified net manufacturer. Russea™ manufactures premium HDPE and nylon nets at their factory with strict quality control at every production stage. As an authorized dealer of Russea™ manufacturer-direct products in '.$A.', NettingTeam guarantees you receive only genuine, certified materials.</p>
<p><strong>About Russea™ Manufacturing:</strong> Russea™ is ISO 9001:2015 certified and manufactures safety nets with UV Grade-A stabilizers, tested breaking strength, eco-friendly recyclable HDPE fibers, and anti-corrosive performance rated for marine environments. Every batch comes with material test certificates. This manufacturer-grade quality is what NettingTeam supplies in '.$A.' — not cheaper alternatives from unverified sources.</p>
<p>The advantage of buying manufacturer-direct through NettingTeam in '.$A.': you get the same quality that large construction companies and international projects use, at prices accessible to individual homeowners. No compromised quality, no adulterated materials. Call <strong>9912399334</strong> for manufacturer-backed pricing in '.$A.'.</p>',
  'intent_title' => 'Russea™ Manufacturing Quality for '.$A.' Customers',
  'points' => ['Russea™ ISO 9001:2015 certified manufacturer','UV Grade-A stabilizers — 10-year outdoor life','Breaking strength tested per batch','Eco-friendly recyclable HDPE fibers','Material test certificates provided','Factory-direct pricing through NettingTeam'],
  'faqs' => [
    ['Who manufactures '.$s['display'].' used in '.$A.'?','Russea™ — South India\'s #1 ISO 9001:2015 certified net manufacturer. NettingTeam is the authorized dealer in '.$A.'.'],
    ['Is Russea™ a certified manufacturer?','Yes — Russea™ is ISO 9001:2015 certified with UV Grade-A rated HDPE materials and documented breaking strength tests.'],
    ['Do you provide material certificates for '.$s['display'].' in '.$A.'?','Yes — Russea™ material test certificates are provided with all bulk orders in '.$A.'.'],
    ['How is Russea™ better than other manufacturers?','Russea™ uses UV Grade-A stabilizers, tested breaking strength, and eco-friendly HDPE — outperforming cheaper alternatives.'],
    ['Can I visit to inspect the '.$s['display'].' material quality in '.$A.'?','Yes — walk in to NettingTeam showroom. Inspect Russea™ certified materials before purchase in '.$A.'.'],
  ],
],
'rate' => [
  'label'    => 'Rate',
  'title'    => $s['display'].' Rate in '.$brandLoc.' — Transparent Quote | NettingTeam',
  'h1'       => $s['display'].' Rate in '.$brandLoc.' — Transparent Custom Quote',
  'h2'       => $s['display'].' Rate Factors in '.$A.' — All Types, All Sizes',
  'hero'     => $s['display'].' rate in '.$A.' depends on your property size, floor height and material grade. No hidden charges. Visit our office at Karmanghat and exact estimate. Call 9912399334.',
  'body'     => '<p>The rate for '.$s['display'].' in <strong>'.$brandLoc.'</strong> varies depending on the specification and total area. NettingTeam maintains transparent, all-inclusive rates — no hidden costs. The quoted rate covers materials (Russea™ certified nets, anchors and hardware), installation labor by our certified team, and a 5-year written warranty for '.$A.' properties.</p>
<p><strong>Rate factors for '.$A.':</strong> (1) Area size — larger areas get a lower per-sqft rate due to economy of scale; (2) Floor height — properties above 10th floor may have a small access charge; (3) Material grade — standard HDPE vs premium UV Grade-A; (4) Custom shapes — non-rectangular areas require custom cutting. Hardware and anchor cost is included in all quotes. Call <strong>9912399334</strong> for an exact all-inclusive rate for your '.$A.' property.</p>
<p>Why our rates in '.$A.' are competitive: We source directly from Russea™ manufacturer with no distributor middleman. Our rates are all-inclusive — you do not pay separately for materials, labor and warranty. One transparent rate, one invoice. Compare our rate with any other '.$A.' service provider and you will see the value. Call <strong>9912399334</strong> for your exact rate in '.$A.'.</p>',
  'intent_title' => $s['display'].' Rate Comparison in '.$A.'',
  'points' => ['All-inclusive rate — no separate material or labor charges','Economy of scale — larger area means lower per-sqft rate','Bulk rate for orders above 2000 sqft','Floor premium only above 10th floor','Transparent GST-inclusive billing','Free exact estimate after site visit in '.$A],
  'faqs' => [
    ['What is the '.$s['display'].' rate in '.$A.'?','Rate depends on your property size, floor height and material grade. Visit our office at Karmanghat and exact written estimate — call 9912399334.'],
    ['Is the '.$s['display'].' rate per sqft in '.$A.'?','Yes — for net-type products, rate is per sqft of area covered. For cage-type products, rate is per complete unit.'],
    ['Does the '.$s['display'].' rate include installation in '.$A.'?','Yes — our rate in '.$A.' is all-inclusive: materials, installation labor and 5-year warranty.'],
    ['Is there a bulk rate for '.$s['display'].' in '.$A.'?','Yes — bulk rates apply above 2000 sqft. Call 9912399334 for current bulk rate card for '.$A.'.'],
    ['How do I get an exact rate for my property in '.$A.'?','Visit our office at Karmanghat — our team visits your property in '.$A.', takes exact measurements and provides a detailed rate quote.'],
  ],
],
];

if (!isset($intents[$it])) {
    http_response_code(404);
    include '404.php';
    exit;
}

$I = $intents[$it];

$page_title       = $I['title'];
$page_description = strip_tags($I['hero']).' Authorized Russea™ dealer, wholesale supplier and installation experts in '.$brandLoc.'. Call 9912399334.';
$page_keywords    = strtolower($s['display']).' '.$it.' '.$A.', '.strtolower($s['display']).' '.$A.', '.strtolower($s['display']).' dealer '.$A.', '.strtolower($s['display']).' supplier hyderabad, '.strtolower($s['display']).' wholesaler '.$A.', '.strtolower($s['display']).' price '.$A.', '.strtolower($s['display']).' installation '.$A.', gcm enterprises '.$A.', russea™ dealer hyderabad';
$page_canonical   = 'https://nettingteam.com/'.$sv.'-'.$it.'-in-'.$ar;

// FAQ Schema
$faq_items = '';
foreach ($I['faqs'] as $f) {
    $faq_items .= '{"@type":"Question","name":'.json_encode($f[0]).',"acceptedAnswer":{"@type":"Answer","text":'.json_encode($f[1]).'}},';
}
$page_schema = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"FAQPage","mainEntity":['.rtrim($faq_items,',').']}</script>';
$breadcrumbs = [['Home','/'],['Services','/'.$s['parent']],[$A.' '.ucfirst($it),$page_canonical]];

require 'includes/header.php';
?>
<main>
<!-- HERO -->
<section class="hero" style="padding:3rem 0 2rem;background:linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 60%,#2563eb 100%)">
<div class="container" style="text-align:center;color:white">
<div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);border-radius:20px;padding:.3rem .9rem;font-size:.85rem;margin-bottom:1rem">
<i class="fas fa-certificate"></i> Authorized Russea™ Dealer &amp; <?=ucfirst($it)?> Expert
</div>
<h1 style="font-size:clamp(1.5rem,4vw,2.4rem);font-weight:800;margin:0 0 1rem"><?=htmlspecialchars($I['h1'])?></h1>
<p style="font-size:1.1rem;max-width:700px;margin:0 auto 1.5rem;opacity:.92"><?=htmlspecialchars($I['hero'])?></p>
<div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
<a href="tel:+919912399334" style="background:white;color:#1d4ed8;padding:.75rem 1.8rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:1.05rem"><i class="fas fa-phone-alt"></i> Call 9912399334</a>
<a href="/contact" style="background:transparent;border:2px solid white;color:white;padding:.75rem 1.8rem;border-radius:8px;font-weight:700;text-decoration:none">Visit Our Office at Karmanghat</a>
</div>
</div>
</section>

<!-- DEALER/SUPPLIER TRUST BLURB -->
<section style="background:#fffbeb;border-bottom:3px solid #f59e0b;padding:.75rem 0">
<div class="container" style="text-align:center;font-size:.95rem">
<strong>NettingTeam</strong> — Authorized Russea™ <strong>Dealer</strong> &bull; <strong>Wholesale Supplier</strong> &bull; <strong>Manufacturer-Direct Retailer</strong> &bull; <strong>Certified Installation Experts</strong> in <?=htmlspecialchars($brandLoc)?>
</div>
</section>

<!-- MAIN CONTENT -->
<section class="section"><div class="container">
<div style="display:grid;grid-template-columns:1fr 300px;gap:2.5rem;align-items:start" class="content-grid">
<div>
<h2 style="color:#1e3a8a;margin-bottom:1rem"><?=htmlspecialchars($I['h2'])?></h2>
<?=$I['body']?>

<div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:10px;padding:1.5rem;margin:1.5rem 0">
<h3 style="color:#15803d;margin:0 0 1rem"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($I['intent_title'])?></h3>
<ul style="margin:0;padding-left:1.2rem;line-height:2">
<?php foreach($I['points'] as $pt): ?><li><strong><?=htmlspecialchars($pt)?></strong></li><?php endforeach; ?>
</ul>
</div>

<!-- SPECS TABLE -->
<h3 style="margin-top:2rem">Technical Specifications — <?=htmlspecialchars($s['display'])?></h3>
<table class="specs-table" style="width:100%;border-collapse:collapse;margin-bottom:1.5rem">
<?php foreach($s['specs'] as $spec): ?>
<tr style="border-bottom:1px solid #e5e7eb">
<td style="padding:.6rem;font-weight:600;background:#f9fafb;width:40%"><?=htmlspecialchars($spec[0])?></td>
<td style="padding:.6rem"><?=htmlspecialchars($spec[1])?></td>
</tr>
<?php endforeach; ?>
</table>

<!-- FAQs -->
<h3 style="margin-top:2rem">Frequently Asked Questions — <?=htmlspecialchars($s['display'])?> <?=ucfirst($it)?> in <?=htmlspecialchars($A)?></h3>
<?php foreach($I['faqs'] as $f): ?>
<div style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:.75rem;overflow:hidden">
<div style="padding:.8rem 1rem;background:#f9fafb;font-weight:600;color:#1e3a8a">Q: <?=htmlspecialchars($f[0])?></div>
<div style="padding:.8rem 1rem">A: <?=htmlspecialchars($f[1])?></div>
</div>
<?php endforeach; ?>
</div>

<!-- SIDEBAR -->
<aside>
<div style="background:#1e3a8a;color:white;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;text-align:center">
<div style="font-size:2rem;margin-bottom:.5rem"><i class="fas fa-phone-alt"></i></div>
<div style="font-size:1.5rem;font-weight:800">9912399334</div>
<p style="margin:.5rem 0;opacity:.9;font-size:.9rem"><?= $is_hyd ? 'Visit Our Office at Karmanghat in ' . htmlspecialchars($A) : 'Serving All Localities in ' . htmlspecialchars($brandLoc) ?></p>
<a href="tel:+919912399334" style="display:block;background:white;color:#1e3a8a;padding:.6rem;border-radius:8px;font-weight:700;text-decoration:none;margin-top:.5rem">Call Now</a>
<a href="<?= getSmartWhatsAppUrl() ?>" target="_blank" style="display:block;background:#25d366;color:white;padding:.6rem;border-radius:8px;font-weight:700;text-decoration:none;margin-top:.5rem"><i class="fab fa-whatsapp"></i> WhatsApp</a>
</div>

<div style="border:2px solid #e5e7eb;border-radius:12px;padding:1.2rem;margin-bottom:1.5rem">
<h4 style="margin:0 0 .75rem;color:#1e3a8a">Get a Custom Quote</h4>
<div style="font-size:1.1rem;font-weight:700;color:#16a34a">Free On-Site Estimate</div>
<p style="font-size:.85rem;color:#6b7280;margin:.25rem 0 0">All-inclusive · 5-yr warranty</p>
</div>

<div style="border:2px solid #dbeafe;border-radius:12px;padding:1.2rem;background:#eff6ff">
<h4 style="margin:0 0 .75rem;color:#1e3a8a">Nearby Areas We Serve</h4>
<ul style="margin:0;padding-left:1.2rem;font-size:.9rem;line-height:1.9">
<?php foreach(array_slice($near,0,6) as $n): ?><li><?=htmlspecialchars($n)?></li><?php endforeach; ?>
</ul>
</div>
</aside>
</div>
</div></section>

<!-- COMMERCIAL CREDENTIALS -->
<section class="section" style="background:#f8fafc"><div class="container">
<h2 class="section-title"><?=htmlspecialchars($s['display'])?> <?=ucfirst($it)?> Experts in <?=htmlspecialchars($A)?></h2>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem">
<div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-store" style="color:#16a34a;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Authorized Dealer</strong><br><small>Russea™ official dealer in <?=htmlspecialchars($A)?></small></div>
<div style="background:#eff6ff;border:2px solid #2563eb;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-boxes" style="color:#2563eb;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Wholesale Supplier</strong><br><small>Bulk orders — factory prices</small></div>
<div style="background:#fefce8;border:2px solid #ca8a04;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-industry" style="color:#ca8a04;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Manufacturer Direct</strong><br><small>Russea™ ISO 9001:2015 certified</small></div>
<div style="background:#fdf4ff;border:2px solid #9333ea;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-hard-hat" style="color:#9333ea;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Installation Experts</strong><br><small>Certified team — 10+ years</small></div>
<div style="background:#fff1f2;border:2px solid #e11d48;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-tags" style="color:#e11d48;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Retail Available</strong><br><small>Walk-in — inspect before buying</small></div>
<div style="background:#f0fdfa;border:2px solid #0d9488;border-radius:10px;padding:1rem;text-align:center"><i class="fas fa-truck" style="color:#0d9488;font-size:1.8rem;margin-bottom:.5rem"></i><br><strong>Immediate Dispatch</strong><br><small>In-stock — same-day dispatch</small></div>
</div>
</div></section>

<!-- CTA -->
<section style="background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white;padding:3rem 0;text-align:center">
<div class="container">
<h2 style="margin:0 0 .5rem">Get Your Free <?=htmlspecialchars($s['display'])?> Quote in <?=htmlspecialchars($A)?></h2>
<p style="margin:0 0 1.5rem;opacity:.9">Authorized dealer · Wholesale supplier · Certified installation experts · 5-year warranty</p>
<a href="tel:+919912399334" style="background:white;color:#1e3a8a;padding:.8rem 2rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:1.1rem;margin:.4rem"><i class="fas fa-phone-alt"></i> 9912399334</a>
<a href="/contact" style="background:transparent;border:2px solid white;color:white;padding:.8rem 2rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:1.1rem;margin:.4rem">Request Callback</a>
</div>
</section>
</main>
<?php require 'includes/footer.php'; ?>
