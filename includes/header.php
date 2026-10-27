<?php require_once __DIR__ . '/init.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'NettingTeam - Authorized Russea™ Brand Dealer. Professional Safety Nets, Invisible Grills & Bird Nets in Hyderabad. 5000+ Happy Customers, 10+ Years Experience, 5-Year Warranty.'; ?>">
    <meta name="keywords" content="<?php echo isset($page_keywords) ? $page_keywords : 'safety nets hyderabad, invisible grills hyderabad, bird nets hyderabad, pigeon nets hyderabad, balcony safety nets hyderabad, sports nets hyderabad, cloth hangers hyderabad, gcm enterprises, russea™ nets hyderabad, child safety nets hyderabad, anti bird netting hyderabad'; ?>">
    <meta name="author" content="NettingTeam — Safety Net Dealers, Suppliers & Installation Experts, Hyderabad">
    <meta name="google-site-verification" content="XGC5sRZ4oAT7iEjjOZsBtGpi7ZEv6vobtLwIgyy6oBg" />
    <meta name="robots" content="<?php echo isset($page_robots) ? $page_robots : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1'; ?>">
    <?php
require_once __DIR__ . '/geo_resolver.php';

// Dynamically determine location and brand name for current page
$page_uri_path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$page_geo_raw  = resolveSmartGeoLocation($page_uri_path, '');
$dyn_brand_loc = 'Hyderabad, Telangana';
$dyn_placename = 'Hyderabad';
$gState = 'Telangana';

if (!empty($page_geo_raw) && $page_geo_raw !== 'India') {
    $gParts = array_map('trim', explode(',', $page_geo_raw));
    $gFirst = $gParts[0] ?? '';
    $gState = end($gParts) ?? '';
    $dyn_placename = $gFirst;
    if (in_array(strtolower($gFirst), ['hyderabad', 'telangana'])) {
        $dyn_brand_loc = 'Hyderabad, Telangana';
    } elseif (count($gParts) >= 3 && $gParts[1] === 'Hyderabad') {
        $dyn_brand_loc = $gFirst . ', Hyderabad, Telangana';
    } else {
        $dyn_brand_loc = ($gFirst !== $gState) ? ($gFirst . ', ' . $gState) : $gFirst;
    }
} elseif (isset($A) && isset($state_name)) {
    $dyn_placename = $A;
    $dyn_brand_loc = (isset($is_hyd) && $is_hyd) ? ($A . ', Hyderabad, Telangana') : ($A . ', ' . $state_name);
}

$dyn_brand_name = 'NettingTeam ' . $dyn_brand_loc;

$_state_code_map = [
    'Telangana' => 'IN-TG',
    'Andhra Pradesh' => 'IN-AP',
    'Karnataka' => 'IN-KA',
    'Tamil Nadu' => 'IN-TN',
    'Maharashtra' => 'IN-MH',
    'Kerala' => 'IN-KL',
    'Gujarat' => 'IN-GJ',
    'Rajasthan' => 'IN-RJ',
    'Uttar Pradesh' => 'IN-UP',
    'Bihar' => 'IN-BR',
    'Delhi' => 'IN-DL',
    'West Bengal' => 'IN-WB',
    'Madhya Pradesh' => 'IN-MP',
    'Punjab' => 'IN-PB',
    'Haryana' => 'IN-HR',
    'Odisha' => 'IN-OD',
    'Goa' => 'IN-GA',
    'Jharkhand' => 'IN-JH',
    'Chhattisgarh' => 'IN-CT',
    'Uttarakhand' => 'IN-UT',
    'Himachal Pradesh' => 'IN-HP',
    'Assam' => 'IN-AS',
    'Jammu and Kashmir' => 'IN-JK',
    'Chandigarh' => 'IN-CH',
    'Puducherry' => 'IN-PY',
];
$dyn_geo_region = $_state_code_map[$gState] ?? 'IN-TG';

// Prepare final page title without duplication
if (isset($page_title)) {
    if ($dyn_brand_loc !== 'Hyderabad, Telangana' && $dyn_brand_loc !== 'Hyderabad') {
        $page_title = str_replace([
            'NettingTeam - Safety Nets Hyderabad',
            'NettingTeam – Safety Nets Hyderabad',
            'NettingTeam Hyderabad',
        ], $dyn_brand_name, $page_title);
    }
    $final_meta_title = $page_title;
    if (strpos($page_title, 'NettingTeam') === false) {
        $final_meta_title = $page_title . ' – ' . $dyn_brand_name;
    }
} else {
    $final_meta_title = 'NettingTeam – #1 Safety Nets, Invisible Grills & Bird Nets | ' . $dyn_brand_name;
}
?>
    <meta name="geo.region" content="<?php echo htmlspecialchars($dyn_geo_region); ?>">
    <meta name="geo.placename" content="<?php echo htmlspecialchars($dyn_placename); ?>">
    <meta name="geo.position" content="17.3850;78.4867">
    <meta name="ICBM" content="17.3850, 78.4867">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo isset($page_canonical) ? $page_canonical : 'https://nettingteam.com' . strtok($_SERVER['REQUEST_URI'], '?'); ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($dyn_brand_name); ?>">
    <meta property="og:locale" content="en_IN">
    <meta property="og:title" content="<?php echo htmlspecialchars($final_meta_title); ?>">
    <meta property="og:description" content="<?php echo isset($page_description) ? $page_description : 'Authorized Russea™ Brand Dealer. Professional safety solutions with 5-year warranty. Serving ' . htmlspecialchars($dyn_brand_loc) . '.'; ?>">
    <meta property="og:url" content="<?php echo isset($page_canonical) ? $page_canonical : 'https://nettingteam.com' . strtok($_SERVER['REQUEST_URI'], '?'); ?>">
    <meta property="og:image" content="<?php echo isset($page_og_image) ? $page_og_image : 'https://nettingteam.com/images/hero/balcony-safety-nets.jpg'; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($final_meta_title); ?>">
    <meta property="twitter:description" content="<?php echo isset($page_description) ? $page_description : 'Authorized Russea™ Brand Dealer. 5-year warranty. Serving ' . htmlspecialchars($dyn_brand_loc) . '.'; ?>">
    <meta property="twitter:image" content="<?php echo isset($page_og_image) ? $page_og_image : 'https://nettingteam.com/images/hero/balcony-safety-nets.jpg'; ?>">
    
    <title><?php echo htmlspecialchars($final_meta_title); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="512x512" href="/images/logos/favicon.png">
    <link rel="shortcut icon" type="image/png" href="/images/logos/favicon.png">
    <link rel="apple-touch-icon" href="/images/logos/favicon.png">
    
    <!-- DNS prefetch for external resources -->
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//www.googletagmanager.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

    <!-- Main CSS (Render-blocking to eliminate FOUC entirely) -->
    <link rel="stylesheet" href="/css/styles.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php
    // Load area data for schema generation (if not already loaded by the calling page)
    if (!isset($areas)) { require_once __DIR__.'/../_data.php'; }
    $schema_areas = [];
    foreach($areas as $slug => $a){
        $schema_areas[] = '{"@type":"Neighborhood","name":'.json_encode($a['label']).',"containedInPlace":{"@type":"City","name":"Hyderabad"}}';
    }
    $schema_areas_json = implode(",\n        ", $schema_areas);
    ?>
    <!-- LocalBusiness JSON-LD Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": ["LocalBusiness","Store"],
      "@id": "https://nettingteam.com/#organization",
      "name": "NettingTeam",
      "alternateName": ["NettingTeam Safety Nets Hyderabad","GCM Net Dealers Hyderabad","Safety Net Suppliers Hyderabad"],
      "description": "Authorized Russea™ brand dealer, wholesale supplier, manufacturer-authorized retailer and certified installation experts for safety nets, bird nets, pigeon nets, invisible grills, sports nets and cloth hangers in Hyderabad. Direct factory pricing — retail and wholesale available. 5000+ completed installations. 10+ years experience.",
      "slogan": "Authorized Dealer. Direct Supplier. Certified Installation Experts.",
      "keywords": "safety net dealers hyderabad, bird net suppliers hyderabad, pigeon net wholesalers hyderabad, invisible grill dealers hyderabad, sports net suppliers hyderabad, net installation experts hyderabad, russea™ dealer hyderabad, safety net manufacturers hyderabad, safety net retailers hyderabad",
      "url": "https://nettingteam.com",
      "telephone": "+919912399334",
      "email": "contact@nettingteam.com",
      "logo": "https://nettingteam.com/images/logos/logo.png",
      "image": "https://nettingteam.com/images/hero/balcony-safety-nets.jpg",
      "priceRange": "₹₹",
      "currenciesAccepted": "INR",
      "paymentAccepted": "Cash, UPI, Bank Transfer",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Plot No. 68, Behind Mega Function Hall, Pavanpuri Colony, MRR Reddy Nagar",
        "addressLocality": "Karmanghat",
        "addressRegion": "Telangana",
        "addressCountry": "IN",
        "postalCode": "500079"
      },
      "hasMap": "https://share.google/nHDBKeOvA7Pt9ozrH",
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": "17.3262",
        "longitude": "78.5511"
      },
      "foundingDate": "2014",
      "numberOfEmployees": {"@type": "QuantitativeValue", "value": 15},
      "areaServed": [
        {"@type": "City", "name": "Hyderabad"},
        {"@type": "City", "name": "Secunderabad"},
        <?php echo $schema_areas_json; ?>
      ],
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],
          "opens": "08:00",
          "closes": "20:00"
        },
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Sunday"],
          "opens": "09:00",
          "closes": "18:00"
        }
      ],
      "sameAs": [
        "https://www.youtube.com/@gcmsafetynets",
        "https://share.google/AiQbAkBDA8cI4D954",
        "https://g.page/r/CYsaqtWlebHrEBM/review",
        "https://www.justdial.com/Hyderabad/Gcm-Enterprises-Near-Karmanghat/040PXX40-XX40-221019145242-W4L4_BZDET",
        "https://www.indiamart.com/gcm-enterprises-hyderabad/",
        "https://www.facebook.com/gcmsafetynets",
        "https://www.instagram.com/gcmsafetynets"
      ],
      "potentialAction": [
        {
          "@type": "ReserveAction",
          "target": {"@type": "EntryPoint", "urlTemplate": "https://nettingteam.com/contact", "inLanguage": "en-IN", "actionPlatform": ["http://schema.org/DesktopWebPlatform","http://schema.org/MobileWebPlatform"]},
          "result": {"@type": "Reservation", "name": "Free Site Assessment Booking"}
        },
        {
          "@type": "CommunicateAction",
          "target": "tel:+919912399334"
        }
      ],
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Safety Net Services & Products — Hyderabad",
        "itemListElement": [
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Balcony Safety Nets Hyderabad", "url": "https://nettingteam.com/service-balcony-safety-nets"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Bird Nets Hyderabad", "url": "https://nettingteam.com/service-bird-nets"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Pigeon Nets Hyderabad", "url": "https://nettingteam.com/service-pigeon-nets"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Sports & Cricket Nets Hyderabad", "url": "https://nettingteam.com/service-sports-nets"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Invisible Grills Hyderabad", "url": "https://nettingteam.com/service-invisible-grills"}},
          {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Cloth Hangers Hyderabad", "url": "https://nettingteam.com/service-cloth-hangers"}},
          {"@type": "Offer", "itemOffered": {"@type": "Product", "name": "Russea™ HDPE Balcony Safety Nets — Dealer & Supplier Hyderabad"}},
          {"@type": "Offer", "itemOffered": {"@type": "Product", "name": "Russea™ HDPE Bird Nets — Wholesale Supplier Hyderabad"}},
          {"@type": "Offer", "itemOffered": {"@type": "Product", "name": "SS316 Invisible Grills — Dealer & Installer Hyderabad"}},
          {"@type": "Offer", "itemOffered": {"@type": "Product", "name": "Cricket & Sports Nets — Supplier & Installation Experts Hyderabad"}}
        ]
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.9",
        "ratingCount": "312",
        "bestRating": "5",
        "worstRating": "1"
      },
      "brand": {
        "@type": "Brand",
        "name": "Russea™",
        "url": "https://www.russea™.in",
        "description": "ISO 9001:2015 Certified Net Manufacturer - Hyderabad's #1 Certified Net Brand"
      },
      "additionalType": "https://www.wikidata.org/wiki/Q1058914"
    }
    </script>

    <?php if (isset($page_schema)) echo $page_schema; ?>

    <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://nettingteam.com/"},
        <?php
        $pos = 2;
        foreach ($breadcrumbs as $bc) {
            $bName = $bc['name'] ?? $bc[0] ?? '';
            $bUrl  = $bc['url']  ?? $bc[1] ?? '';
            echo '{"@type": "ListItem", "position": ' . $pos . ', "name": "' . addslashes($bName) . '"' . (!empty($bUrl) ? ', "item": "' . $bUrl . '"' : '') . '}';
            if ($pos < count($breadcrumbs) + 1) echo ',';
            $pos++;
        }
        ?>
      ]
    }
    </script>
    <?php endif; ?>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-17837588954"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'AW-17837588954');
</script>
<script src="/js/geo_resolver.js"></script>
</head>
<body>
    <!-- Marquee Section -->
    <div class="marquee-bar">
        <div class="marquee-content">
            <span class="marquee-item"><i class="fas fa-store"></i> Authorized Dealer &amp; Supplier</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-industry"></i> Manufacturer Direct</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Balcony Safety Nets</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-boxes"></i> Wholesale Available</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Bird &amp; Pigeon Nets</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-hard-hat"></i> Installation Experts</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Cricket &amp; Sports Nets</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-tags"></i> Retail &amp; Wholesale</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Invisible Grills</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-award"></i> 5000+ Happy Customers</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-certificate"></i> 5 Year Warranty</span>
            <span class="marquee-separator">★</span>
            <!-- Duplicate for seamless loop -->
            <span class="marquee-item"><i class="fas fa-star"></i> Balcony Safety Nets</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Invisible Grills</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Bird & Pigeon Nets</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Sports & Cricket Nets</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Cloth Hangers</span>
            <span class="marquee-separator">★</span>
            <span class="marquee-item"><i class="fas fa-star"></i> Child Safety Solutions</span>
            <span class="marquee-separator">★</span>
        </div>
    </div>
    
    <!-- Top Contact Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="top-bar-content">
                <div class="top-bar-left">
                    <span><i class="fas fa-map-marker-alt"></i> Karmanghat, Hyderabad &mdash; Serving All Greater Hyderabad &amp; Secunderabad</span>
                    <span><i class="fas fa-clock"></i> Mon-Sat: 8AM - 8PM | Sun: 9AM - 6PM</span>
                </div>
                <div class="top-bar-right">
                    <a href="tel:+919912399334" class="top-bar-link">
                        <i class="fas fa-phone-alt"></i> +919912399334
                    </a>
                    <a href="<?= getSmartWhatsAppUrl() ?>" target="_blank" rel="noopener noreferrer" class="top-bar-link whatsapp-link" aria-label="Chat with NettingTeam on WhatsApp">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <img src="/images/logos/logo.png" alt="NettingTeam — Safety Nets Hyderabad" style="height: 80px; width: auto; display: inline-block; vertical-align: middle;">
            </a>
            
            <!-- Desktop Navigation -->
            <nav class="nav">
                <a href="index.php" class="nav-link">Home</a>
                <a href="about.php" class="nav-link">About Us</a>
                
                <!-- Services Dropdown -->
                <div class="nav-dropdown">
                    <a href="services.php" class="nav-link">
                        Services <i class="fas fa-chevron-down" style="font-size: 0.75rem; margin-left: 0.25rem;"></i>
                    </a>
                    <div class="dropdown-menu dropdown-services-list">
                        <a href="service-balcony-safety-nets.php" class="dropdown-service-item">
                            <i class="fas fa-home"></i> Balcony Safety Nets
                        </a>
                        <a href="service-bird-nets.php" class="dropdown-service-item">
                            <i class="fas fa-dove"></i> Bird Nets
                        </a>
                        <a href="service-pigeon-nets.php" class="dropdown-service-item">
                            <i class="fas fa-feather"></i> Pigeon Nets
                        </a>
                        <a href="cricket-nets.php" class="dropdown-service-item">
                            <i class="fas fa-baseball-ball"></i> Cricket Nets
                        </a>
                        <a href="cricket-nets.php#box-cricket" class="dropdown-service-item ps-4" style="font-size:.82rem">
                            <i class="fas fa-cubes"></i> Box Cricket Setup
                        </a>
                        <a href="cricket-nets.php#supply" class="dropdown-service-item ps-4" style="font-size:.82rem">
                            <i class="fas fa-boxes"></i> Cricket Nets Supply
                        </a>
                        <a href="service-sports-nets.php" class="dropdown-service-item">
                            <i class="fas fa-running"></i> All Sports Nets
                        </a>
                        <div style="border-top:1px solid #f3f4f6;margin:.3rem 0;"></div>
                        <span style="padding:.25rem 1rem;font-size:.7rem;font-weight:800;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;display:block;">Ground Setups</span>
                        <a href="football-turf-setup-hyderabad" class="dropdown-service-item">
                            <i class="fas fa-futbol"></i> Football Turf Setup
                        </a>
                        <a href="5-a-side-football-turf-hyderabad" class="dropdown-service-item ps-4" style="font-size:.82rem">
                            <i class="fas fa-circle"></i> 5-a-Side Turf
                        </a>
                        <a href="7-a-side-football-turf-hyderabad" class="dropdown-service-item ps-4" style="font-size:.82rem">
                            <i class="fas fa-circle"></i> 7-a-Side Turf
                        </a>
                        <a href="8-a-side-football-turf-hyderabad" class="dropdown-service-item ps-4" style="font-size:.82rem">
                            <i class="fas fa-circle"></i> 8-a-Side Turf
                        </a>
                        <a href="box-cricket-ground-setup-hyderabad" class="dropdown-service-item">
                            <i class="fas fa-cricket"></i> Box Cricket Court Setup
                        </a>
                        <div style="border-top:1px solid #f3f4f6;margin:.3rem 0;"></div>
                        <a href="service-invisible-grills.php" class="dropdown-service-item">
                            <i class="fas fa-border-all"></i> Invisible Grills
                        </a>
                        <a href="service-artificial-grass.php" class="dropdown-service-item">
                            <i class="fas fa-leaf"></i> Artificial Grass / Turf
                        </a>
                        <a href="service-cloth-hangers.php" class="dropdown-service-item">
                            <i class="fas fa-tshirt"></i> Cloth Hangers
                        </a>
                    </div>
                </div>
                
                <a href="gallery.php" class="nav-link"><i class="fas fa-images" style="color: var(--color-primary);"></i> Gallery</a>
                <a href="videos.php" class="nav-link"><i class="fab fa-youtube" style="color: #dc2626;"></i> Videos</a>
                <a href="contact.php" class="nav-link">Contact</a>
                <a href="tel:+919912399334" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">
                    <i class="fas fa-phone"></i> 9912399334
                </a>
            </nav>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation -->
        <nav class="mobile-nav">
            <!-- Mobile menu logo -->
            <div style="padding:1.25rem 1rem 0.75rem;border-bottom:1px solid #f3f4f6;margin-bottom:0.5rem">
                <a href="index.php">
                    <img src="/images/logos/logo.png" alt="NettingTeam" style="height:60px;width:auto;display:block">
                </a>
            </div>
            <a href="index.php" class="mobile-nav-link">Home</a>
            <a href="about.php" class="mobile-nav-link">About Us</a>
            
            <!-- Mobile Services Submenu -->
            <div class="mobile-submenu">
                <a href="services.php" class="mobile-nav-link mobile-submenu-toggle">
                    Services <i class="fas fa-chevron-down" style="font-size: 0.75rem; margin-left: 0.25rem;"></i>
                </a>
                <div class="mobile-submenu-items">
                    <a href="service-balcony-safety-nets.php" class="mobile-subitem"><i class="fas fa-home"></i> Balcony Safety Nets</a>
                    <a href="service-bird-nets.php" class="mobile-subitem"><i class="fas fa-dove"></i> Bird Nets</a>
                    <a href="service-pigeon-nets.php" class="mobile-subitem"><i class="fas fa-feather"></i> Pigeon Nets</a>
                    <a href="cricket-nets.php" class="mobile-subitem"><i class="fas fa-baseball-ball"></i> Cricket Nets</a>
                    <a href="cricket-nets.php#box-cricket" class="mobile-subitem ps-4" style="font-size:.82rem"><i class="fas fa-cubes"></i> Box Cricket Setup</a>
                    <a href="cricket-nets.php#supply" class="mobile-subitem ps-4" style="font-size:.82rem"><i class="fas fa-boxes"></i> Cricket Nets Supply</a>
                    <a href="service-sports-nets.php" class="mobile-subitem"><i class="fas fa-running"></i> All Sports Nets</a>
                    <span style="padding:.2rem .75rem;font-size:.7rem;font-weight:800;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;display:block;margin-top:.3rem;">Ground Setups</span>
                    <a href="football-turf-setup-hyderabad" class="mobile-subitem"><i class="fas fa-futbol"></i> Football Turf Setup</a>
                    <a href="5-a-side-football-turf-hyderabad" class="mobile-subitem ps-4" style="font-size:.81rem"><i class="fas fa-circle"></i> 5-a-Side Turf</a>
                    <a href="7-a-side-football-turf-hyderabad" class="mobile-subitem ps-4" style="font-size:.81rem"><i class="fas fa-circle"></i> 7-a-Side Turf</a>
                    <a href="8-a-side-football-turf-hyderabad" class="mobile-subitem ps-4" style="font-size:.81rem"><i class="fas fa-circle"></i> 8-a-Side Turf</a>
                    <a href="box-cricket-ground-setup-hyderabad" class="mobile-subitem"><i class="fas fa-cricket"></i> Box Cricket Setup</a>
                    <a href="service-invisible-grills.php" class="mobile-subitem"><i class="fas fa-border-all"></i> Invisible Grills</a>
                    <a href="service-artificial-grass.php" class="mobile-subitem"><i class="fas fa-leaf"></i> Artificial Grass / Turf</a>
                    <a href="service-cloth-hangers.php" class="mobile-subitem"><i class="fas fa-tshirt"></i> Cloth Hangers</a>
                </div>
            </div>
            
            <a href="gallery.php" class="mobile-nav-link"><i class="fas fa-images" style="color: var(--color-primary);"></i> Gallery</a>
            <a href="videos.php" class="mobile-nav-link"><i class="fab fa-youtube" style="color: #dc2626;"></i> Videos</a>
            <a href="contact.php" class="mobile-nav-link">Contact</a>
            <a href="tel:+919912399334" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-phone"></i> 9912399334
            </a>
        </nav>
    </header>
