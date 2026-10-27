<?php
/**
 * Smart Dynamic WhatsApp & Context Helper for NettingTeam
 * Generates rich, high-converting WhatsApp inquiry messages tailored to the specific product/service, location, and page.
 */

require_once __DIR__ . '/geo_resolver.php';

if (!function_exists('getGcmPageContext')) {
function getGcmPageContext($customService = null, $customLocation = null) {
    global $page_title, $page_canonical, $breadcrumbs, $s, $A, $A_city, $sv, $ar, $svc_data, $areas;

    // Load data dictionary if not loaded
    if (empty($svc_data) || empty($areas)) {
        $dataFile = dirname(__DIR__) . '/_data.php';
        if (file_exists($dataFile)) {
            require_once $dataFile;
        }
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $cleanUri = trim(strtok($uri, '?'), '/');

    // 1. Detect Product / Service Name
    $service = $customService;
    if (empty($service)) {
        if (!empty($s['display'])) {
            $service = $s['display'];
        } elseif (!empty($_GET['service']) && !empty($svc_data[$_GET['service']]['display'])) {
            $service = $svc_data[$_GET['service']]['display'];
        } else {
            // Check if URI matches pattern: {service}-in-{area} or {service}-{intent}-in-{area}
            if (preg_match('/^([a-z0-9-]+)-(?:dealers|suppliers|price|near-me|installation|wholesalers|manufacturers|rate)-in-([a-z0-9-]+)/', $cleanUri, $m) ||
                preg_match('/^([a-z0-9-]+)-in-([a-z0-9-]+)/', $cleanUri, $m)) {
                $slug = $m[1];
                if (!empty($svc_data[$slug]['display'])) {
                    $service = $svc_data[$slug]['display'];
                }
            }
        }
    }

    if (empty($service)) {
        $checkStr = strtolower(($page_title ?? '') . ' ' . $cleanUri . ' ' . $script);

        // Specific categories in priority order (Specific products BEFORE general netting!)
        if (strpos($checkStr, 'cloth-hanger') !== false || strpos($checkStr, 'cloth hanger') !== false || strpos($checkStr, 'drying-hanger') !== false || strpos($checkStr, 'drying-stand') !== false || strpos($checkStr, 'drying rack') !== false || strpos($checkStr, 'pull-and-dry') !== false || strpos($checkStr, 'pull and dry') !== false || strpos($checkStr, 'ceiling-cloth') !== false || strpos($checkStr, 'wall-mounted-drying') !== false || strpos($checkStr, 'wooden-hanger') !== false || strpos($checkStr, 'space-saving-hanger') !== false || strpos($checkStr, 'luxury-cloth-hanger') !== false) {
            if (strpos($checkStr, 'balcony-cloth-hanger') !== false || strpos($checkStr, 'balcony cloth hanger') !== false) {
                $service = 'Balcony Cloth Drying Hanger';
            } elseif (strpos($checkStr, 'ceiling') !== false) {
                $service = 'Ceiling Cloth Drying Hangers';
            } elseif (strpos($checkStr, 'stand') !== false) {
                $service = 'Cloth Drying Stands & Racks';
            } elseif (strpos($checkStr, 'wall-mounted') !== false) {
                $service = 'Wall Mounted Cloth Drying Racks';
            } else {
                $service = 'Balcony & Ceiling Cloth Drying Hangers';
            }
        } elseif (strpos($checkStr, 'spike') !== false) {
            $service = 'Bird & Pigeon Protection Spikes';
        } elseif (strpos($checkStr, 'invisible-grill') !== false || strpos($checkStr, 'invisible grill') !== false || strpos($checkStr, 'grill') !== false) {
            $service = 'Stainless Steel Invisible Grills';
        } elseif (strpos($checkStr, 'box-cricket') !== false || strpos($checkStr, 'box cricket') !== false) {
            $service = 'Box Cricket Ground Setup & Netting';
        } elseif (strpos($checkStr, '5-a-side') !== false) {
            $service = '5-a-Side Football Turf Setup';
        } elseif (strpos($checkStr, '7-a-side') !== false) {
            $service = '7-a-Side Football Turf Setup';
        } elseif (strpos($checkStr, '8-a-side') !== false) {
            $service = '8-a-Side Football Turf Setup';
        } elseif (strpos($checkStr, 'football-turf') !== false || strpos($checkStr, 'football turf') !== false) {
            $service = 'Football Turf Setup & Perimeter Netting';
        } elseif (strpos($checkStr, 'cricket') !== false) {
            $service = 'Cricket Practice Pitch Nets Installation';
        } elseif (strpos($checkStr, 'balcony') !== false) {
            $service = 'Balcony Safety Nets Installation';
        } elseif (strpos($checkStr, 'child') !== false) {
            $service = 'Child Safety Nets Installation';
        } elseif (strpos($checkStr, 'pigeon') !== false) {
            $service = 'Pigeon Safety Nets & Spikes';
        } elseif (strpos($checkStr, 'bird') !== false) {
            $service = 'Anti-Bird & Pigeon Protection Nets';
        } elseif (strpos($checkStr, 'sports') !== false) {
            $service = 'Sports Netting & Court Enclosures';
        } elseif (strpos($checkStr, 'grass') !== false || strpos($checkStr, 'turf') !== false) {
            $service = 'Artificial Grass Turf & Flooring';
        } elseif (strpos($checkStr, 'coconut') !== false) {
            $service = 'Coconut Tree Safety Nets';
        } elseif (strpos($checkStr, 'duct') !== false) {
            $service = 'Duct Area Safety Nets';
        } elseif (strpos($checkStr, 'industrial') !== false) {
            $service = 'Industrial & Construction Safety Nets';
        } else {
            $service = 'Safety Nets & Invisible Grills';
        }
    }

    // 2. Detect Granular Geographic Location
    $location = !empty($customLocation) ? resolveSmartGeoLocation($customLocation) : "";
    if (empty($location)) {
        if (!empty($_GET['area'])) {
            $location = resolveSmartGeoLocation($_GET['area']);
        } elseif (!empty($A)) {
            $location = resolveSmartGeoLocation($A);
        } elseif (!empty($A_city)) {
            $location = resolveSmartGeoLocation($A_city);
        } elseif (preg_match('/-in-([a-z0-9-]+)/', $cleanUri, $m)) {
            $location = resolveSmartGeoLocation($m[1]);
        }
    }

    if (empty($location)) {
        $checkLoc = strtolower(($page_title ?? '') . ' ' . $cleanUri . ' ' . $script);
        $location = resolveSmartGeoLocation($checkLoc, 'Hyderabad, Telangana (All Areas & Pan-India Supply)');
    }

    // 3. Determine Pricing / Unit Prompt based on product category
    $searchKey = strtolower($service . ' ' . $cleanUri . ' ' . $script);
    $unitType = 'sft'; // default
    if (strpos($searchKey, 'hanger') !== false || strpos($searchKey, 'drying stand') !== false || strpos($searchKey, 'drying rack') !== false || strpos($searchKey, 'pull-and-dry') !== false || strpos($searchKey, 'pull and dry') !== false) {
        $unitType = 'piece_hanger';
        $pricingText = 'Please share pricing per piece / set, model options, technical specifications, and installation availability.';
    } elseif (strpos($searchKey, 'spike') !== false) {
        $unitType = 'piece_spikes';
        $pricingText = 'Please share pricing per piece / per strip, technical specifications, and installation availability.';
    } elseif (strpos($searchKey, 'box cricket') !== false || strpos($searchKey, 'box-cricket') !== false || strpos($searchKey, 'football turf') !== false || strpos($searchKey, 'football-turf') !== false || strpos($searchKey, '5-a-side') !== false || strpos($searchKey, '7-a-side') !== false || strpos($searchKey, '8-a-side') !== false || strpos($searchKey, 'court setup') !== false) {
        $unitType = 'court_setup';
        $pricingText = 'Please share turnkey court setup cost / pricing per sq.ft, technical specifications, and installation availability.';
    } else {
        $unitType = 'sft';
        $pricingText = 'Please share pricing per sq.ft, technical specifications, and installation availability.';
    }

    // 4. Build Full Canonical URL Reference
    $pageUrl = $page_canonical ?? '';
    if (empty($pageUrl)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'nettingteam.com';
        $pageUrl = 'https://' . $host . '/' . ltrim($uri, '/');
    }

    return [
        'service'      => $service,
        'location'     => $location,
        'page_url'     => $pageUrl,
        'page_title'   => $page_title ?? $service,
        'website'      => 'www.nettingteam.com',
        'company'      => 'NettingTeam',
        'unit_type'    => $unitType,
        'pricing_text' => $pricingText
    ];
}
}

if (!function_exists('getSmartWhatsAppUrl')) {
function getSmartWhatsAppUrl($customService = null, $customLocation = null, $phone = '919912399334') {
    $ctx = getGcmPageContext($customService, $customLocation);

    $msg = "Hi " . $ctx['company'] . ", I would like to inquire about *" . $ctx['service'] . "* in *" . $ctx['location'] . "*.\n\n"
         . "• Service: " . $ctx['service'] . "\n"
         . "• Location: " . $ctx['location'] . "\n"
         . "• Page: " . $ctx['page_url'] . "\n"
         . "• Website: " . $ctx['website'] . "\n\n"
         . $ctx['pricing_text'];

    return "https://wa.me/" . $phone . "?text=" . rawurlencode($msg);
}
}

if (!function_exists("getGcmWhatsAppUrl")) {
function getGcmWhatsAppUrl($customService = null, $customLocation = null, $phone = "919912399334") {
    return getSmartWhatsAppUrl($customService, $customLocation, $phone);
}
}
