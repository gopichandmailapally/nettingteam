<?php
/**
 * Hyderabad & Secunderabad Localities Grid — included in service pages.
 * Requires: $svc_slug (e.g. 'safety-nets'), $svc_label (e.g. 'Safety Nets')
 * Groups all 183 areas by Hyderabad Zones (West, Central, North, East, South)
 */
if (!isset($areas)) { include __DIR__ . '/../_data.php'; }

$zone_labels = [
    'west'    => 'West Hyderabad & IT Corridor (Gachibowli, Madhapur, Kondapur, Financial District, Miyapur, Kukatpally)',
    'central' => 'Central Hyderabad (Banjara Hills, Jubilee Hills, Somajiguda, Begumpet, Ameerpet, Abids)',
    'north'   => 'North Hyderabad & Secunderabad (Secunderabad, Marredpally, Alwal, Kompally, Medchal, Malkajgiri, ECIL)',
    'east'    => 'East Hyderabad (LB Nagar, Uppal, Dilsukhnagar, Kothapet, Karmanghat, Vanasthalipuram, Nagole)',
    'south'   => 'South Hyderabad (Mehdipatnam, Tolichowki, Attapur, Rajendra Nagar, Shaikpet, Shamshabad)',
];

$zone_order = ['west', 'central', 'north', 'east', 'south'];

$by_zone = [];
foreach ($areas as $slug => $a) {
    if ($slug === 'hyderabad') continue;
    $z = $a['zone'] ?? 'central';
    if (!isset($zone_labels[$z])) $z = 'central';
    $by_zone[$z][$slug] = $a['label'];
}

$svc_slug_safe  = htmlspecialchars($svc_slug ?? 'safety-nets');
$svc_label_safe = htmlspecialchars($svc_label ?? 'Safety Nets');
$total_hyd_areas = count($areas);
?>

<section class="section" style="background: var(--color-gray-50); border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">
    <div class="container">
        <div class="text-center" style="max-width: 820px; margin: 0 auto 2.5rem;">
            <div style="display: inline-flex; align-items: center; gap: 8px; background: #fee2e2; color: #dc2626; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 6px 14px; border-radius: 50px; margin-bottom: 0.75rem;">
                <i class="fas fa-map-marker-alt"></i> 100% Hyderabad &amp; Secunderabad Focused
            </div>
            <h2 class="section-title" style="margin-bottom: 0.75rem;">
                <?= $svc_label_safe ?> &mdash; All Greater Hyderabad Service Areas
            </h2>
            <p class="section-subtitle" style="margin: 0; font-size: 1rem; color: #4b5563;">
                NettingTeam covers every gated community, high-rise apartment tower, villa society, and commercial building across all <?= $total_hyd_areas ?>+ localities in Greater Hyderabad &amp; Secunderabad. Same-day site visit &amp; free measurement.
            </p>
        </div>

        <?php foreach ($zone_order as $z): 
            if (empty($by_zone[$z])) continue;
            $z_label = $zone_labels[$z];
            $z_areas = $by_zone[$z];
            asort($z_areas);
        ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <h3 style="font-size: 0.95rem; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-block; width: 10px; height: 10px; background: #dc2626; border-radius: 50%;"></span>
                        <?= $z_label ?>
                    </h3>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f8fafc; padding: 3px 10px; border-radius: 20px; border: 1px solid #e2e8f0;">
                        <?= count($z_areas) ?> Localities
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem 0.6rem;">
                    <?php foreach ($z_areas as $aslug => $alabel): ?>
                        <a href="/<?= $svc_slug_safe ?>-in-<?= htmlspecialchars($aslug) ?>" 
                           style="color: #334155; font-size: 0.8rem; font-weight: 500; text-decoration: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.3rem 0.7rem; white-space: nowrap; transition: all 0.2s;" 
                           onmouseover="this.style.background='#dc2626';this.style.color='#fff';this.style.borderColor='#dc2626'" 
                           onmouseout="this.style.background='#f8fafc';this.style.color='#334155';this.style.borderColor='#e2e8f0'">
                            <?= htmlspecialchars($alabel) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="text-center" style="margin-top: 2.5rem; background: #1e293b; padding: 2rem; border-radius: 12px; color: white;">
            <h4 style="color: white; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">
                Looking for <?= $svc_label_safe ?> Installation Anywhere in Hyderabad?
            </h4>
            <p style="color: #94a3b8; font-size: 0.95rem; max-width: 600px; margin: 0 auto 1.25rem;">
                Our technicians reach your location in Hyderabad within 60 minutes for free measurement &amp; on-the-spot demonstration.
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="tel:+919912399334" class="btn btn-primary btn-lg" style="padding: 0.75rem 2rem;">
                    <i class="fas fa-phone me-2"></i> Call: 9912399334
                </a>
                <a href="https://wa.me/919912399334?text=Hi+NettingTeam,+I+need+<?= urlencode($svc_label_safe) ?>+in+Hyderabad.+Please+share+details." 
                   target="_blank" class="btn btn-success btn-lg" style="padding: 0.75rem 2rem; background: #16a34a; border-color: #16a34a;">
                    <i class="fab fa-whatsapp me-2"></i> WhatsApp Now
                </a>
            </div>
        </div>
    </div>
</section>
