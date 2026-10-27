<?php
require_once __DIR__ . '/includes/functions.php';
$docCurrency = $doc['currency'] ?? 'INR';
$docCurrInfo = getCurrencyInfo($docCurrency);
$currSym     = $docCurrInfo['symbol'] ?? '₹';

require_once __DIR__ . '/includes/auth.php';

$isPublicEstimation = false;
$reqId = $_GET['id'] ?? '';
if (!isLoggedIn()) {
    $checkDoc = $reqId ? getDocument($reqId) : null;
    if ($checkDoc) {
        $isPublicEstimation = true;
    } else {
        requireLogin();
    }
}

$id  = $_GET['id'] ?? '';
$doc = $id ? getDocument($id) : null;
// Compute return URL preserving document filters
$docsReturnUrl = 'documents.php';
$retParams = [];
if (!empty($_SESSION['doc_filter_type'])) {
    $retParams['type'] = $_SESSION['doc_filter_type'];
}
if (!empty($_SESSION['doc_filter_search'])) {
    $retParams['q'] = $_SESSION['doc_filter_search'];
}
if (!empty($retParams)) {
    $docsReturnUrl = 'documents.php?' . http_build_query($retParams);
}

if (!$doc) { flashSet('error', 'Document not found.'); redirect('documents.php'); }

$settings  = getSettings();
$type         = $doc['type'] ?? 'estimation';
$isGst        = isset($doc['is_gst']) ? (bool)$doc['is_gst'] : true;
$isPoAck      = (($_GET['mode'] ?? '') === 'po_ack');
$isProforma   = (($_GET['mode'] ?? '') === 'proforma' || ($_GET['mode'] ?? '') === 'pi' || in_array($type, ['proforma_invoice', 'proforma']));

if ($isPoAck) {
    $displayDocId = preg_replace('/^(EST|INV|BILL|PRO|PI)/i', 'APO', $doc['id']);
    $typeLabel = 'P.O. ACKNOWLEDGEMENT';
} elseif ($isProforma) {
    $displayDocId = (strpos($doc['id'], 'PI-') === 0 || strpos($doc['id'], 'PRO-') === 0)
        ? $doc['id']
        : preg_replace('/^(EST|INV|BILL)/i', 'PI', $doc['id']);
    $typeLabel = 'PROFORMA INVOICE';
} else {
    $displayDocId = $doc['id'];
    $typeLabel = strtoupper(docTypeLabel($type, $isGst));
}
$saleMode = $doc['sale_unit_mode'] ?? 'sqft';
$saleLabel = $doc['sale_unit_label'] ?? '';
$custCountry = $doc['bill_to']['country'] ?? 'India';
$countryList = getCountriesList();
$estSubtype = $doc['estimation_subtype'] ?? 'normal';
$subtypeLabels = [
  'normal'              => '',
  'supply_only'         => 'SUPPLY ONLY',
  'supply_installation' => 'SUPPLY & INSTALLATION',
  'cricket_net'         => 'CRICKET NET SETUP',
  'sports_net'          => 'SPORTS NET SETUP',
  'turnkey_court'       => 'TURNKEY SPORTS COURT SETUP',
];
$isTurnkey = ($type === 'estimation' && $estSubtype === 'turnkey_court');
$isAmenitiesProposal = (($doc['turnkey_config']['sport_type'] ?? '') === 'outdoor_multi_sports_120x60') || (($doc['id'] ?? '') === 'EST-202609-064');
$showAmenitiesLayout = (isset($_GET['template']) && $_GET['template'] === 'amenities') || ($isAmenitiesProposal && !isset($_GET['standard']));
$showAmenitiesRates = isset($_GET['show_rates']) && $_GET['show_rates'] == '1';

$turnkeyConfig = $isTurnkey ? ($doc['turnkey_config'] ?? null) : null;
if ($isTurnkey) {
    require_once __DIR__ . '/includes/turnkey_calculator.php';
    $tkL = (float)($turnkeyConfig['length'] ?? 90);
    $tkW = (float)($turnkeyConfig['width'] ?? 60);
    $tkH = (float)($turnkeyConfig['height'] ?? 25);
    $tkSport = $turnkeyConfig['sport_type'] ?? 'box_cricket';
    $tkRoof = $turnkeyConfig['roof_type'] ?? 'light_cable_top';
    $tkPkg = $turnkeyConfig['package'] ?? 'commercial';
    $tkPole = $turnkeyConfig['pole_style'] ?? 'ladder_2line';
    $tkPipeSize = $turnkeyConfig['pipe_size'] ?? '40x40';
    $tkPipeGauge = $turnkeyConfig['pipe_gauge'] ?? '2.0mm';
    $tkTurf = $turnkeyConfig['turf_type'] ?? 'infill_turf';
    $tkCivilBed = $turnkeyConfig['civil_bed_type'] ?? 'compacted_wmm';
    $tkSideNet = $turnkeyConfig['side_net'] ?? 'braided_2_5mm_45mm';
    $tkTopNet = $turnkeyConfig['top_net'] ?? 'twisted_1_5mm_35mm';
    $tkPitch = (float)($turnkeyConfig['pitch_length'] ?? 66);
    $isTNetEarly = (strpos($tkSport, 'training_net') !== false || ($turnkeyConfig['shape'] ?? '') === 'training_net' || !empty($turnkeyConfig['is_training_net']));
    $rawSidesEarly = $turnkeyConfig['open_sides'] ?? [];
    $isFrontOpenEarly = !empty($rawSidesEarly['front']) || $isTNetEarly;
    $tkGateCount = isset($turnkeyConfig['gate_count']) ? (int)$turnkeyConfig['gate_count'] : ($isFrontOpenEarly ? 0 : 1);
    if ($isFrontOpenEarly) {
        $tkGateCount = 0; // Training net / large open front already has entrance, no gate needed!
    }
    $tkCustom = !empty($turnkeyConfig['custom_rate']) ? (float)$turnkeyConfig['custom_rate'] : null;

    // Actual BOQ subtotal from document items
    $docSubtotal = (float)($doc['subtotal'] ?? 0);
    if ($docSubtotal <= 0 && !empty($doc['items'])) {
        $docSubtotal = array_sum(array_column($doc['items'], 'amount'));
    }

    $safeOpenSidesEarly = [
        'front' => $isFrontOpenEarly,
        'left'  => !empty($rawSidesEarly['left']),
        'right' => !empty($rawSidesEarly['right']),
        'back'  => !empty($rawSidesEarly['back']),
        'top'   => !empty($rawSidesEarly['top']) || ($tkRoof === 'open_top'),
    ];

    // Dynamic layer detection based on actual line items in the quotation
    $docItems = $doc['items'] ?? [];
    $docItemsDesc = '';
    foreach ($docItems as $it) {
        $docItemsDesc .= ' ' . ($it['description'] ?? '');
    }

    $hasDocItems = !empty($docItems);
    $hasItemStructure = $hasDocItems ? (bool)preg_match('/(column|pole|fabrication|ladder|truss|steel|pipe|framework|vertical upright|horizontal tie|purlin)/i', $docItemsDesc) : true;
    $hasItemNetting   = $hasDocItems ? (bool)preg_match('/(net|netting|ball-stop|hdpe|braided|twisted|containment|mesh)/i', $docItemsDesc) : true;
    $hasItemLighting  = $hasDocItems ? (bool)preg_match('/(light|floodlight|stadium light|sports led|lighting fixture|electrical|cabling|panel)/i', $docItemsDesc) : true;
    $hasItemCivil     = $hasDocItems ? (bool)preg_match('/(civil|base slab|concrete|pcc|rcc|wmm|sub-base|subbase|drainage|earth|asphalt|bitumen)/i', $docItemsDesc) : true;
    $hasItemTurf      = $hasDocItems ? (bool)preg_match('/(turf|grass|flooring|matting|acrylic|carpet|outfield sports)/i', $docItemsDesc) : true;
    $hasItemPitch     = $hasDocItems ? (bool)preg_match('/(pitch|wicket|batting pitch)/i', $docItemsDesc) : true;
    $hasItemGates     = $hasDocItems ? (bool)preg_match('/(gate|player access door|access door)/i', $docItemsDesc) : true;

    // Respect saved config layers, but strictly enforce false if item is completely absent from quote
    $cfgLayers = $turnkeyConfig['layers'] ?? [];
    $safeStructure = $hasItemStructure && ($cfgLayers['structure'] ?? true);
    $safeNetting   = $hasItemNetting && ($cfgLayers['netting'] ?? true);
    $safeLighting  = $hasItemLighting && ($cfgLayers['lighting'] ?? true);
    $safeGates     = $hasItemGates && ($cfgLayers['gates'] ?? true);
    $safeCivil     = $hasItemCivil && ($cfgLayers['civil'] ?? true);
    $safeTurf      = $hasItemTurf && ($cfgLayers['turf'] ?? true);
    $safePitch     = $hasItemPitch && ($cfgLayers['pitch'] ?? true);

    $safeLayers = [
        'structure' => $safeStructure,
        'netting'   => $safeNetting,
        'lighting'  => $safeLighting,
        'gates'     => $safeGates,
        'civil'     => $safeCivil,
        'turf'      => $safeTurf,
        'pitch'     => $safePitch,
        'zones'     => $turnkeyConfig['zones'] ?? null,
    ];

    if (!$safeGates) {
        $tkGateCount = 0;
    }

    $turnkeyBOQ = generateTurnkeyBOQ($tkL, $tkW, $tkH, $tkSport, $tkRoof, $tkPkg, $tkPole, $tkPipeSize, $tkPipeGauge, $tkTurf, $tkCustom, $tkPitch, $tkCivilBed, $tkSideNet, $tkTopNet, $tkGateCount, $safeOpenSidesEarly, $safeLayers);
    $turnkeySpecs = $turnkeyBOQ['specs'];
    if (!empty($turnkeyConfig['specs']) && is_array($turnkeyConfig['specs'])) {
        $turnkeySpecs = array_merge($turnkeySpecs, $turnkeyConfig['specs']);
    }
    
    // Dynamic Scaled Packages: synchronizes active package rate & budget to exact BOQ subtotal
    $docGrandTotal = (float)($doc['grand_total'] ?? $doc['total_amount'] ?? $doc['total'] ?? 0);
    if ($docGrandTotal <= 0 && $docSubtotal > 0) {
        $taxAmt = (float)($doc['tax_amount'] ?? 0);
        $docGrandTotal = ($taxAmt > 0) ? ($docSubtotal + $taxAmt) : round($docSubtotal * 1.160308, 2);
    }
    $turnkeyAllPackages = getTurnkeyScaledPackages($turnkeySpecs, $docSubtotal, $tkPkg, $tkCivilBed, $docGrandTotal);
    $multiZoneBreakdown = getMultiZoneTurnkeyBreakdown($doc, $turnkeySpecs);
    
    // Detailed Technical Specification Rows (Columns & Rows Table)
    $turnkeySpecRows = getTurnkeyDetailedSpecRows($turnkeySpecs, $tkPkg);
    $turnkeySpecRows = array_filter($turnkeySpecRows, function($row) use ($safeStructure, $safeNetting, $safeLighting, $safeCivil, $safeTurf, $safeGates) {
        $cat = $row['cat'] ?? '';
        $item = $row['item'] ?? '';
        if ($cat === 'Structural Engineering' && !$safeStructure) return false;
        if ($cat === 'Roof Structure' && !$safeStructure && !$safeNetting) return false;
        if (($item === 'Structural Column Foundations' || $row['component'] === 'Structural Column Foundations' || strpos($row['component'], 'Foundation') !== false || strpos($row['component'], 'Flange') !== false) && !$safeStructure) return false;
        if (in_array($cat, ['Sports Netting', 'Overhead Roof Netting']) && !$safeNetting) return false;
        if ($cat === 'Lighting System' || $cat === 'Lighting & Electrical') {
            if (!$safeLighting) return false;
        }
        if ($cat === 'Player Access Door' && !$safeGates) return false;
        if ($cat === 'Civil Sub-Base' && !$safeCivil) return false;
        if (($cat === 'Sports Flooring / Turf' || $cat === 'Surface & Flooring') && !$safeTurf) return false;
        return true;
    });
    // Re-index sl numbers
    $slIdx = 1;
    foreach ($turnkeySpecRows as &$row) {
        $row['sl'] = sprintf('%02d', $slIdx++);
    }
    unset($row);

    $currentPoleProfile = $turnkeySpecs['pole_profile'];
    $currentCivilBedProfile = $turnkeySpecs['civil_bed_profile'];
}
$subtypeLabel = ($type === 'estimation' && $estSubtype !== 'normal') ? ($subtypeLabels[$estSubtype] ?? '') : '';

// Use company snapshot for text fields (name, address, GSTIN, bank)
// but ALWAYS load fresh company data for assets (logo/sig/stamp change after doc created)
$company = $doc['supplier'] ?? getDefaultCompany();
if (!$company) $company = $settings['company'] ?? [];

$freshCompany = null;
$companyId    = $doc['company_id'] ?? ($doc['supplier']['id'] ?? null);
if ($companyId) $freshCompany = getCompany($companyId);
if (!$freshCompany) $freshCompany = getDefaultCompany();

// Always sync the latest registered address and bank details from the company profile:
if (!empty($freshCompany['address'])) $company['address'] = $freshCompany['address'];
if (!empty($freshCompany['bank_name'])) $company['bank_name'] = $freshCompany['bank_name'];
if (!empty($freshCompany['bank_account'])) $company['bank_account'] = $freshCompany['bank_account'];
if (!empty($freshCompany['bank_ifsc'])) $company['bank_ifsc'] = $freshCompany['bank_ifsc'];
if (!empty($freshCompany['bank_branch'])) $company['bank_branch'] = $freshCompany['bank_branch'];

// Resolve upload paths — prefer fresh company assets, fall back to settings globals
// show_signature/show_stamp: show unless EXPLICITLY set to false (false because asset
// didn't exist at creation time should not permanently hide it)
$sigPath   = null;
$stampPath = null;
if ($doc['show_signature'] !== false) {
    $raw = $freshCompany['signature'] ?? $doc['supplier']['signature'] ?? $settings['signature'] ?? null;
    if ($raw) $sigPath = preg_replace('/\?.*$/', '', $raw);
}
if ($doc['show_stamp'] !== false) {
    $raw = $freshCompany['stamp'] ?? $doc['supplier']['stamp'] ?? $settings['stamp'] ?? null;
    if ($raw) $stampPath = preg_replace('/\?.*$/', '', $raw);
}
$rawLogo  = $freshCompany['logo'] ?? $doc['supplier']['logo'] ?? $settings['logo'] ?? null;
$logoPath = $rawLogo ? preg_replace('/\?.*$/', '', $rawLogo) : null;

// Bank details — always use fresh company data; show unless explicitly disabled
$showBank   = $doc['show_bank'] !== false;

// Russea Branding toggle: check URL param override first (?russea=1 / ?russea=0), then doc setting, then global default
if (isset($_GET['russea'])) {
    $showRussea = ($_GET['russea'] === '1' || $_GET['russea'] === 'true');
} else {
    $showRussea = isset($doc['show_russea']) ? (bool)$doc['show_russea'] : ($settings['defaults']['show_russea'] ?? true);
}
$liveBank   = $freshCompany ?? $company;
$website    = $liveBank['website'] ?? $company['website'] ?? $settings['website'] ?? 'nettingteam.com';
$bankName   = !empty($liveBank['bank_name']) ? $liveBank['bank_name'] : (!empty($company['bank_name']) ? $company['bank_name'] : 'STATE BANK OF INDIA');
$bankAcc    = !empty($liveBank['bank_account']) ? $liveBank['bank_account'] : (!empty($company['bank_account']) ? $company['bank_account'] : '44979437825');
$bankIfsc   = !empty($liveBank['bank_ifsc']) ? $liveBank['bank_ifsc'] : (!empty($company['bank_ifsc']) ? $company['bank_ifsc'] : 'SBIN0021462');
$bankBranch = !empty($liveBank['bank_branch']) ? $liveBank['bank_branch'] : (!empty($company['bank_branch']) ? $company['bank_branch'] : 'Greenpark Colony');
$bankType   = $liveBank['bank_account_type'] ?? $company['bank_account_type'] ?? 'Current Account';
// $showBank retained from doc['show_bank']

// ── UNIVERSAL STANDARD HEADER & FOOTER HELPERS ─────────────────────────
if (!function_exists('renderStandardDocHeader')) {
function renderStandardDocHeader($company, $logoPath, $showRussea) {
?>
  <div class="doc-header">
    <table style="width:100%;border-collapse:collapse;">
      <colgroup>
        <col style="width:66%">
        <col style="width:34%">
      </colgroup>
      <tbody>
      <tr>
        <td style="padding:6px 10px 4px 12px;vertical-align:middle;">
          <div style="font-size:7.5pt;color:#555;">Proprietor : <?= e($company['proprietor'] ?? '') ?> &nbsp;&nbsp; Contact : <?= e($company['phone'] ?? '') ?></div>
          <div class="doc-company-name"><?= e($company['name'] ?? '') ?></div>
          <div id="russeaHeaderBadge" style="display:<?= $showRussea ? 'inline-flex' : 'none' ?>;align-items:center;gap:6px;margin:2px 0 3px;">
            <span style="background:#ff0000;color:#fff;font-size:6.5pt;font-weight:800;padding:1px 7px;border-radius:20px;letter-spacing:.6px;text-transform:uppercase;print-color-adjust:exact;-webkit-print-color-adjust:exact;">Authorized Dealer</span>
            <span style="font-size:7.5pt;font-weight:700;color:#ff0000;letter-spacing:.3px;">Russea™ Brand</span>
          </div>
          <div class="doc-address"><?= e($company['address'] ?? '') ?></div>
          <div class="doc-gstin">GSTIN : <?= e($company['gstin'] ?? '') ?></div>
        </td>
        <td style="padding:4px 0;text-align:center;vertical-align:middle;border-left:0;">
          <?php if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)): ?>
          <img src="<?= e($logoPath) ?>?v=<?= time() ?>" alt="GCM Logo" style="max-height:105px;max-width:100%;object-fit:contain;display:block;margin:0 auto;">
          <?php else: ?>
          <div style="height:55px;display:flex;align-items:center;justify-content:center;font-size:7pt;color:#aaa;text-align:center;">
            <span>Upload Logo<br>in Companies</span>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      </tbody>
    </table>
  </div>
<?php
}
}

if (!function_exists('renderStandardDocFooter')) {
function renderStandardDocFooter($company, $website = null) {
    if (!$website) {
        $website = $company['website'] ?? 'nettingteam.com';
    }
?>
  <div class="doc-footer-pinned" style="margin-top:auto !important; width:100%;">
    <div class="doc-bottom-bar">
      <div class="doc-bottom-bar-inner">
        <?php if (!empty($company['phone'])): ?>
        <div class="doc-footer-item">
          <span class="fi-icon">&#9990;</span>
          <span><?= e($company['phone']) ?><?= !empty($company['phone2']) ? ' &nbsp;/&nbsp; '.e($company['phone2']) : '' ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($company['email'])): ?>
        <div class="doc-footer-item">
          <span class="fi-icon">&#9993;</span>
          <span><?= e($company['email']) ?></span>
        </div>
        <?php endif; ?>
        <div class="doc-footer-item">
          <span class="fi-icon">&#127760;</span>
          <span><?= e($website) ?></span>
        </div>
        <?php if (!empty($company['gstin'])): ?>
        <div class="doc-footer-item">
          <span class="fi-icon" style="font-size:5.5pt;font-weight:700;">GST</span>
          <span><?= e($company['gstin']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php
}
}

if (!function_exists('renderDocTotalsSection')) {
function renderDocTotalsSection($doc, $isGst = true) {
    $type = $doc['type'] ?? 'estimation';
    $isPoAck = (($_GET['mode'] ?? '') === 'po_ack');
?>
  <div class="totals-section">
    <table class="totals-tbl">
      <tr class="total-row">
        <td class="lbl" style="font-size:9.5pt;font-weight:700;background:#1a3a7c;color:#fff;">TOTAL</td>
        <td class="val" style="background:#1a3a7c;color:#fff;"><?= fmtAmount($doc['subtotal'] ?? 0) ?></td>
      </tr>
      <?php if (($doc['discount_amount'] ?? 0) > 0): ?>
      <?php $discType = $doc['discount_type'] ?? 'percent'; ?>
      <tr>
        <td class="lbl">
          <?php if ($discType === 'amount'): ?>
            DISCOUNT (<?= number_format($doc['discount_percent'] ?? 0, 2) ?>%)
          <?php else: ?>
            DISCOUNT <?= number_format($doc['discount_percent'] ?? 0, 2) ?>%
          <?php endif; ?>
        </td>
        <td class="val">–<?= fmtAmount($doc['discount_amount']) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($isGst): ?>
      <tr class="taxable-row" style="background:#ffffff;">
        <td class="lbl" style="font-weight:800;color:#0f172a;">TAXABLE VALUE</td>
        <td class="val" style="font-weight:800;color:#0f172a;"><?= fmtAmount($doc['taxable_value'] ?? 0) ?></td>
      </tr>
      <?php
        $gm = $doc['gst_mode'] ?? ($isGst ? 'cgst_sgst' : 'none');
        $breakdown = $doc['gst_breakdown'] ?? [];
        if (empty($breakdown) && !empty($doc['items'])) {
            $discFactor = (($doc['subtotal'] ?? 0) > 0 && ($doc['discount_amount'] ?? 0) > 0)
                ? (1 - ($doc['discount_amount'] / $doc['subtotal'])) : 1.0;
            $tmpSlabs = [];
            foreach ($doc['items'] as $it) {
                $r = (float)($it['gst_rate'] ?? 0);
                if ($r <= 0) {
                    $hsn = trim($it['hsn'] ?? '');
                    if (!$isGst) { $r = 0.0; } elseif (in_array($hsn, ['2505', '9965', '5608', '5703'])) $r = 5.0; elseif (in_array($hsn, ['9506'])) $r = 12.0; else $r = 18.0;
                }
                $amt = round(((float)($it['amount'] ?? 0)) * $discFactor, 2);
                $k = number_format($r, 1);
                $tmpSlabs[$k] = ($tmpSlabs[$k] ?? 0) + $amt;
            }
            ksort($tmpSlabs);
            foreach ($tmpSlabs as $rStr => $taxable) {
                $r = (float)$rStr;
                if ($gm === 'cgst_sgst') {
                    $hr = $r / 2;
                    $ha = round($taxable * ($hr / 100), 2);
                    $breakdown[] = [
                        'rate' => $r, 'taxable' => $taxable,
                        'cgst_rate' => $hr, 'cgst_amount' => $ha,
                        'sgst_rate' => $hr, 'sgst_amount' => $ha,
                    ];
                } else {
                    $ta = round($taxable * ($r / 100), 2);
                    $breakdown[] = [
                        'rate' => $r, 'taxable' => $taxable,
                        'igst_rate' => $r, 'igst_amount' => $ta,
                    ];
                }
            }
        }
      ?>

      <?php if (!empty($breakdown)): ?>
        <?php $totTaxSum = 0; ?>
        <?php foreach ($breakdown as $slab): ?>
          <?php if ($gm === 'cgst_sgst'): ?>
            <?php $totTaxSum += (($slab['cgst_amount'] ?? 0) + ($slab['sgst_amount'] ?? 0)); ?>
            <tr class="tax-row">
              <td class="lbl" style="padding-left:14px;color:#334155;font-size:8pt;">
                CGST @ <?= number_format($slab['cgst_rate'] ?? 0, 1) ?>% <span style="color:#64748b;font-size:7pt;">(on <?= fmtAmount($slab['taxable'] ?? 0) ?>)</span>
              </td>
              <td class="val" style="font-size:8pt;"><?= fmtAmount($slab['cgst_amount'] ?? 0) ?></td>
            </tr>
            <tr class="tax-row">
              <td class="lbl" style="padding-left:14px;color:#334155;font-size:8pt;">
                SGST @ <?= number_format($slab['sgst_rate'] ?? 0, 1) ?>% <span style="color:#64748b;font-size:7pt;">(on <?= fmtAmount($slab['taxable'] ?? 0) ?>)</span>
              </td>
              <td class="val" style="font-size:8pt;"><?= fmtAmount($slab['sgst_amount'] ?? 0) ?></td>
            </tr>
          <?php else: ?>
            <?php $totTaxSum += ($slab['igst_amount'] ?? 0); ?>
            <tr class="tax-row">
              <td class="lbl" style="padding-left:14px;color:#334155;font-size:8pt;">
                IGST @ <?= number_format($slab['igst_rate'] ?? 0, 1) ?>% <span style="color:#64748b;font-size:7pt;">(on <?= fmtAmount($slab['taxable'] ?? 0) ?>)</span>
              </td>
              <td class="val" style="font-size:8pt;"><?= fmtAmount($slab['igst_amount'] ?? 0) ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        <tr class="tax-row" style="font-weight:700;border-top:1px dashed #cbd5e1;background:#ffffff;">
          <td class="lbl" style="font-size:8.5pt;color:#0f172a;padding-left:10px;">TOTAL GST AMOUNT</td>
          <td class="val" style="font-size:8.5pt;color:#0f172a;"><?= fmtAmount($totTaxSum) ?></td>
        </tr>
      <?php elseif ($isGst && $gm === 'cgst_sgst'): ?>
      <tr class="tax-row">
        <td class="lbl">CGST <?= fmtAmount($doc['cgst_rate'] ?? 0) ?> %</td>
        <td class="val"><?= fmtAmount($doc['cgst_amount'] ?? 0) ?></td>
      </tr>
      <tr class="tax-row">
        <td class="lbl">SGST <?= fmtAmount($doc['sgst_rate'] ?? 0) ?> %</td>
        <td class="val"><?= fmtAmount($doc['sgst_amount'] ?? 0) ?></td>
      </tr>
      <?php elseif ($isGst && $gm === 'igst'): ?>
      <tr class="tax-row">
        <td class="lbl">IGST <?= fmtAmount($doc['igst_rate'] ?? 0) ?> %</td>
        <td class="val"><?= fmtAmount($doc['igst_amount'] ?? 0) ?></td>
      </tr>
      <?php endif; ?>
      <?php endif; ?>
      <tr class="grand-row">
        <td class="lbl" style="font-size:11pt;font-weight:900;background:#1a3a7c;color:#fff;">GRAND TOTAL</td>
        <td class="val" style="font-size:11pt;background:#1a3a7c;color:#fff;"><?= fmtAmount($doc['grand_total'] ?? 0) ?></td>
      </tr>
      <?php if (!$isPoAck && in_array($type, ['tax_invoice', 'bill', 'proforma'])): ?>
      <?php
      $pStatus = $doc['payment_status'] ?? (($doc['status'] ?? '') === 'paid' ? 'paid' : 'unpaid');
      $grandTot = (float)($doc['grand_total'] ?? $doc['total'] ?? 0);
      $advAmt = (float)($doc['advance_amount'] ?? 0);
      if ($pStatus === 'paid') {
          $advAmt = $grandTot;
          $balDue = 0;
      } elseif ($pStatus === 'unpaid') {
          $advAmt = 0;
          $balDue = $grandTot;
      } else {
          $balDue = max(0, round($grandTot - $advAmt, 2));
      }
      ?>
      <tr style="border-top:2px solid #cbd5e1;">
        <td class="lbl" style="font-size:8.5pt;font-weight:700;color:#475569;background:#f8fafc;">PAYMENT STATUS</td>
        <td class="val" style="font-size:8.5pt;font-weight:800;background:#f8fafc;">
          <?php if ($pStatus === 'paid'): ?>
            <span style="color:#15803d;background:#dcfce7;padding:2px 8px;border-radius:4px;border:1px solid #86efac;font-size:8pt;font-weight:800;letter-spacing:0.5px;">✓ FULLY PAID</span>
          <?php elseif ($pStatus === 'partial'): ?>
            <span style="color:#b45309;background:#fef3c7;padding:2px 8px;border-radius:4px;border:1px solid #fcd34d;font-size:8pt;font-weight:800;letter-spacing:0.5px;">⏳ PARTIALLY PAID (ADVANCE)</span>
          <?php else: ?>
            <span style="color:#b91c1c;background:#fee2e2;padding:2px 8px;border-radius:4px;border:1px solid #fca5a5;font-size:8pt;font-weight:800;letter-spacing:0.5px;">⏳ UNPAID (BEFORE PAYMENT)</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($pStatus === 'partial'): ?>
      <tr style="background:#f0fdf4;">
        <td class="lbl" style="font-size:8.5pt;font-weight:700;color:#166534;">
          ADVANCE RECEIVED
          <?php if (!empty($doc['payment_mode'])): ?>
            <span style="font-size:7pt;color:#15803d;display:block;">(Via <?= e($doc['payment_mode']) ?>)</span>
          <?php endif; ?>
        </td>
        <td class="val" style="font-size:8.5pt;font-weight:800;color:#166534;"><?= $currSym ?><?= fmtAmount($advAmt) ?></td>
      </tr>
      <tr style="background:#fff1f2;border-top:1.5px solid #f43f5e;">
        <td class="lbl" style="font-size:10pt;font-weight:900;color:#9f1239;">BALANCE PENDING / DUE</td>
        <td class="val" style="font-size:10pt;font-weight:900;color:#e11d48;"><?= $currSym ?><?= fmtAmount($balDue) ?></td>
      </tr>
      <?php elseif ($pStatus === 'unpaid'): ?>
      <tr style="background:#fff1f2;border-top:1.5px solid #f43f5e;">
        <td class="lbl" style="font-size:9.5pt;font-weight:900;color:#9f1239;">BALANCE PENDING / DUE</td>
        <td class="val" style="font-size:9.5pt;font-weight:900;color:#e11d48;"><?= $currSym ?><?= fmtAmount($grandTot) ?></td>
      </tr>
      <?php elseif ($pStatus === 'paid'): ?>
      <tr style="background:#f0fdf4;border-top:1.5px solid #86efac;">
        <td class="lbl" style="font-size:8.5pt;font-weight:700;color:#166534;">BALANCE DUE</td>
        <td class="val" style="font-size:8.5pt;font-weight:800;color:#16a34a;">₹0.00 (NIL)</td>
      </tr>
      <?php endif; ?>
      <?php endif; ?>
    </table>
  </div>
<?php
}
}

if (!function_exists('renderStandardCommercialFooter')) {
function renderStandardCommercialFooter($doc, $company, $showRussea, $showBank, $bankName, $bankAcc, $bankIfsc, $bankBranch, $stampPath, $sigPath, $website) {
?>
  <!-- Pinned Bottom Container: Russea Strip + Footer Table + Universal Bottom Bar -->
  <div class="doc-footer-pinned" style="margin-top:auto !important; width:100%;">
    <!-- Russea Brand strip -->
    <div id="russeaBottomStrip" style="display:<?= $showRussea ? 'flex' : 'none' ?>;background:#0d1b3e;padding:4px 12px;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;print-color-adjust:exact;-webkit-print-color-adjust:exact;margin-top:2px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="background:#ff0000;color:#fff;font-size:6.5pt;font-weight:800;padding:2px 8px;border-radius:20px;letter-spacing:.8px;text-transform:uppercase;print-color-adjust:exact;-webkit-print-color-adjust:exact;">Authorized Dealer</span>
        <span style="color:#fff;font-size:8.5pt;font-weight:800;letter-spacing:.5px;">Russea&#8482; Brand</span>
        <span style="color:rgba(255,255,255,.5);font-size:6.5pt;">— ISO 9001:2015 Certified HDPE Safety Nets</span>
      </div>
      <div style="color:rgba(255,255,255,.7);font-size:6.5pt;font-weight:600;letter-spacing:.3px;">South India&#39;s #1 Safety Net Manufacturer</div>
    </div>

    <!-- FOOTER TABLE: single table so vertical divider lines stay perfectly aligned -->
    <table style="width:100%;border-collapse:collapse;border-top:2px solid #0d1b3e;">
      <colgroup>
        <col style="width:55%">
        <col style="width:45%">
      </colgroup>
      <tbody>
        <!-- ROW 1: Bank Details (left) | Amount in Words (right) -->
        <tr>
          <td style="padding:0;border-right:2px solid #0d1b3e;vertical-align:top;">
            <?php if ($showBank && !empty($bankAcc)): ?>
            <div class="section-bar-navy">Bank Account Details</div>
            <table style="font-size:7.8pt;width:100%;border-collapse:collapse;">
              <tr><td style="color:#555;padding:2px 8px;width:38%;font-weight:600;">Account Name</td><td style="padding:2px 6px;font-weight:700;"><?= e($company['name']) ?></td></tr>
              <tr><td style="color:#555;padding:2px 8px;font-weight:600;">Account Number</td><td style="padding:2px 6px;font-weight:700;letter-spacing:.5px;"><?= e($bankAcc) ?></td></tr>
              <?php if ($bankIfsc): ?><tr><td style="color:#555;padding:2px 8px;font-weight:600;">IFSC Code</td><td style="padding:2px 6px;font-weight:700;"><?= e($bankIfsc) ?></td></tr><?php endif; ?>
              <?php if ($bankName || $bankBranch): ?><tr><td style="color:#555;padding:2px 8px;font-weight:600;">Bank &amp; Branch</td><td style="padding:2px 6px;"><?= e(trim(($bankName ?? '') . ($bankBranch ? ' (' . $bankBranch . ')' : ''))) ?></td></tr><?php endif; ?>
            </table>
            <?php elseif ($showBank): ?>
            <div style="padding:6px 8px;font-size:7.2pt;color:#aaa;font-style:italic;">No bank details — add via Companies page.</div>
            <?php endif; ?>
          </td>
          <td style="padding:0;vertical-align:top;">
            <div class="section-bar-navy">Amount in Words</div>
            <div style="padding:10px 14px;min-height:55px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;">
              <div style="font-size:8.8pt;font-weight:700;color:#1e293b;font-style:italic;text-align:center;line-height:1.35;"><?= e($doc['amount_in_words'] ?? '') ?></div>
              <?php if (!empty($doc['notes'])): ?>
              <div style="font-size:7pt;color:#555;margin-top:5px;text-align:center;"><strong>Notes:</strong> <?= e($doc['notes']) ?></div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <!-- ROW 2: Important Terms (left) | Signature (right) -->
        <tr style="border-top:2px solid #0d1b3e;">
          <td style="padding:0;border-right:2px solid #0d1b3e;vertical-align:top;border-top:2px solid #0d1b3e;">
            <?php
              $displayTerms = $doc['terms'] ?? [];
              $isTurnkeyDoc = (($doc['type'] ?? '') === 'estimation' && ($doc['estimation_subtype'] ?? '') === 'turnkey_court');
              if ($isTurnkeyDoc && (empty($displayTerms) || in_array('50% Advance payment required to proceed order', $displayTerms) || !preg_grep('/40%/', $displayTerms))) {
                  $displayTerms = [
                      'Payment Terms: 40% Advance on order confirmation, 40% upon civil base & structural steel erection, 20% upon final turf installation & court handover',
                      '40% Advance payment required to commence site excavation, civil bed work, and structural steel procurement',
                      '40% Milestone payment upon completion of civil base slab and structural steel column & truss erection',
                      '20% Final balance payment upon sports turf installation, LED floodlighting testing, and final court handover',
                      'Transport & Material Freight: Material delivery directly to project site as per BOQ',
                      'Site Utilities: Water and electricity power source to be arranged at site by client during execution',
                      'System Warranty: 1 Year (Essential) / 2 Years (Commercial) / 3-5 Years (Premium Pro) Complete System Warranty',
                  ];
              }
            ?>
            <?php if (!empty($displayTerms)): ?>
            <div class="section-bar-red">Important Terms</div>
            <ol style="margin:0;padding:3px 8px 3px 22px;font-size:7.2pt;color:#333;line-height:1.35;">
            <?php foreach ($displayTerms as $term): ?>
              <li><?= e($term) ?></li>
            <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <div style="padding:6px;font-size:7.5pt;color:#aaa;">—</div>
            <?php endif; ?>
          </td>
          <td style="padding:6px 10px;vertical-align:top;border-top:2px solid #0d1b3e;text-align:center;">
            <div style="font-size:8pt;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#0d1b3e;margin-bottom:2px;"><?= e($company['name']) ?></div>
            <div style="display:flex;align-items:center;justify-content:center;position:relative;min-height:75px;margin:3px 0;">
              <?php if ($stampPath && file_exists(__DIR__ . '/' . $stampPath)): ?>
              <img src="<?= e($stampPath) ?>?v=<?= filemtime(__DIR__ . '/' . $stampPath) ?>" alt="Stamp" style="position:absolute;max-height:75px;max-width:200px;left:50%;transform:translateX(-50%) rotate(-7deg);pointer-events:none;opacity:0.92;">
              <?php endif; ?>
              <?php if ($sigPath && file_exists(__DIR__ . '/' . $sigPath)): ?>
              <img src="<?= e($sigPath) ?>?v=<?= filemtime(__DIR__ . '/' . $sigPath) ?>" alt="Signature" style="position:relative;z-index:2;max-height:52px;max-width:150px;transform:translateY(2px);">
              <?php endif; ?>
            </div>
            <div style="border-top:1.5px solid #0d1b3e;padding-top:2px;margin-top:2px;">
              <div style="font-weight:800;font-size:7.8pt;letter-spacing:.5px;"><?= e($company['proprietor'] ?? '') ?></div>
              <div style="font-size:6.8pt;color:#555;text-transform:uppercase;letter-spacing:.5px;">Proprietor</div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Universal Bottom Bar -->
    <div class="doc-bottom-bar">
      <div class="doc-bottom-bar-inner">
        <?php if (!empty($company['phone'])): ?>
        <div class="doc-footer-item">
          <span class="fi-icon">&#9990;</span>
          <span><?= e($company['phone']) ?><?= !empty($company['phone2']) ? ' &nbsp;/&nbsp; '.e($company['phone2']) : '' ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($company['email'])): ?>
        <div class="doc-footer-item">
          <span class="fi-icon">&#9993;</span>
          <span><?= e($company['email']) ?></span>
        </div>
        <?php endif; ?>
        <div class="doc-footer-item">
          <span class="fi-icon">&#127760;</span>
          <span><?= e($website) ?></span>
        </div>
        <?php if (!empty($company['gstin'])): ?>
        <div class="doc-footer-item">
          <span class="fi-icon" style="font-size:5.5pt;font-weight:700;">GST</span>
          <span><?= e($company['gstin']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /doc-footer-pinned -->
<?php
}
}

if (!function_exists('renderDocItemRow')) {
function renderDocItemRow($item, $saleMode = 'sqft', $saleLabel = '', $isGst = true) {
    $descLines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $item['description'] ?? ''))));
    $mainDesc  = $descLines[0] ?? '';
    $inlineSpecs = array_slice($descLines, 1);
    $allSpecs = array_filter(array_merge($inlineSpecs, $item['specs'] ?? []));

    $itemHsnRaw = $item['hsn'] ?? '';
    $displayHsn = function_exists('resolveItemHsn') ? resolveItemHsn($mainDesc, $itemHsnRaw) : ($itemHsnRaw ?: '5608');
    $itemGst = $isGst ? (function_exists('resolveItemGstRate') ? resolveItemGstRate($mainDesc, $displayHsn, $item['gst_rate'] ?? null) : (float)($item['gst_rate'] ?? 18.0)) : 0.0;

    $unitNorm = function_exists('normalizeUqcUnit') ? normalizeUqcUnit($item['unit'] ?? '') : ($item['unit'] ?? '');
    $uqcCode = function_exists('getUqcCodeOnly') ? getUqcCodeOnly($unitNorm) : $unitNorm;
    $uqcSub = (strpos($unitNorm, '-') !== false) ? explode('-', $unitNorm, 2)[1] : '';
?>
  <tr>
    <td class="col-desc">
      <div style="font-weight:700;font-size:8pt;color:#0f172a;line-height:1.15;"><?= e($mainDesc) ?></div>
      <?php if ($allSpecs): ?>
      <div style="font-size:6.5pt;color:#475569;margin-top:1px;font-style:italic;line-height:1.15;">
        <?= e(implode(' • ', $allSpecs)) ?>
      </div>
      <?php endif; ?>
    </td>
    <td class="col-hsn">
      <div style="font-weight:700;color:#1e293b;"><?= e($displayHsn) ?></div>
      <?php if ($isGst && $itemGst > 0): ?><div style="font-size:6.5pt;font-weight:700;color:#2563eb;margin-top:1px;"><?= $itemGst ?>% GST</div><?php endif; ?>
    </td>
    <td class="col-qty"><?= e($item['qty'] % 1 == 0 ? (int)$item['qty'] : $item['qty']) ?></td>
    <td class="col-unit">
      <div style="font-weight:700;color:#1e293b;"><?= e($uqcCode) ?></div>
      <?php if ($uqcSub): ?><div style="font-size:6pt;color:#64748b;line-height:1;margin-top:1px;"><?= e($uqcSub) ?></div><?php endif; ?>
    </td>
    <td class="col-rate"><?= fmtAmount($item['rate']) ?></td>
    <td class="col-amount"><?= fmtAmount($item['amount']) ?></td>
  </tr>
<?php
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($isPoAck ? ('P.O. Acknowledgement ' . $displayDocId) : ($isProforma ? ('Proforma Invoice ' . $displayDocId) : $doc['id'])) ?> — <?= e($company['name']) ?></title>
<!-- Favicon -->
<link rel="icon" type="image/png" sizes="512x512" href="/images/logos/favicon.png">
<link rel="shortcut icon" type="image/png" href="/images/logos/favicon.png">
<link rel="apple-touch-icon" href="/images/logos/favicon.png">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --red:#ff0000; --red2:#ff0000; --navy:#0d1b3e; --blue:#1a3a7c;
  --gold:#f39c12; --green:#27ae60; --teal:#16a085;
  --bs-danger:#ff0000 !important;
  --bs-danger-rgb: 255, 0, 0 !important;
}
.text-danger { color: #ff0000 !important; }
.btn-danger { background-color: #ff0000 !important; border-color: #ff0000 !important; color: #fff !important; }
.badge.bg-danger, .bg-danger { background-color: #ff0000 !important; color: #fff !important; }
body { background:#e8eaf2; font-family:'Segoe UI',Arial,sans-serif; }

/* ── Admin Toolbar ─────────────────────────────────────── */
.admin-toolbar {
  background:var(--navy); color:#fff; padding:.6rem 1.5rem;
  display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;
  position:sticky; top:0; z-index:100;
}
.admin-toolbar .doc-id { font-weight:800; font-size:1rem; letter-spacing:1px; color:#ffd; }

/* ── Document Wrapper ──────────────────────────────────── */
.doc-wrapper { max-width:800px; margin:1.5rem auto 3rem; }
.doc-view-outer { width: 100%; }

/* ── Mobile & Tablet Viewport Adaptations ── */
@media screen and (max-width: 991px) {
  .admin-toolbar {
    padding: 0.5rem 0.75rem !important;
    gap: 0.4rem !important;
  }
  .admin-toolbar .btn {
    padding: 0.35rem 0.55rem !important;
    font-size: 0.78rem !important;
  }
  .admin-toolbar .doc-id {
    font-size: 0.88rem !important;
  }
  .doc-view-outer {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    padding: 0.5rem 0.25rem 2rem !important;
  }
  .doc-wrapper {
    margin: 0.5rem auto 1.5rem !important;
    min-width: 760px !important; /* Preserves pixel-perfect A4 typography and table columns */
  }
  .table-swipe-hint-view {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    background: #0d1b3e;
    color: #fff;
    padding: 0.45rem 0.85rem;
    font-size: 0.75rem;
    border-radius: 6px;
    margin: 0.5rem 0.5rem 0.2rem;
  }
}
@media screen and (max-width: 576px) {
  .admin-toolbar {
    padding: 0.4rem 0.5rem !important;
  }
  .admin-toolbar .ms-auto {
    margin-left: 0 !important;
    width: 100% !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 0.3rem !important;
    justify-content: flex-start !important;
  }
}

/* ── Document Sheet — flex column so footer is always pinned to bottom ── */
.doc-sheet {
  background:#fff;
  box-shadow:0 8px 32px rgba(0,0,0,.18);
  border-radius:4px;
  font-size:9.5pt;
  font-family:Arial, sans-serif;
  color:#000;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  justify-content:flex-start;
  min-height:277mm;
  box-sizing:border-box;
}
/* Main body between parties and footer — grows to fill page */
.doc-main-content { flex:1; display:flex; flex-direction:column; }
.doc-items-grow   { flex:1; } /* items+totals area that stretches */

/* ── Header ── */
.doc-header {
  padding:0;
  border-bottom:3px solid var(--navy);
  background:#ffffff;
  border-top:5px solid #ff0000;
}
.doc-company-name {
  color:#ff0000;
  font-size:21pt;
  font-weight:900;
  letter-spacing:2px;
  line-height:1;
  margin:0;
  text-shadow:none;
}
.doc-proprietor { font-size:8pt; color:#333; }
.doc-address { font-size:8pt; color:#555; margin-top:2px; }
.doc-gstin { font-size:9pt; font-weight:700; margin-top:4px; color:var(--navy); }

/* ── Parties + GSTIN unified table ── */
.doc-parties-tbl {
  width:100%; border-collapse:collapse;
  border-top:2px solid var(--navy); border-bottom:2px solid var(--navy);
}
.doc-parties-tbl td, .doc-parties-tbl th {
  border-right:2px solid var(--navy); vertical-align:top; padding:0;
}
.doc-parties-tbl td:last-child,
.doc-parties-tbl th:last-child { border-right:none; }

.party-bill     { padding:6px 10px !important; background:#ffffff !important; }
.party-supplier { padding:6px 10px !important; background:#ffffff !important; }
.party-type     { padding:0 !important; vertical-align:top !important; background:#ffffff !important; }

.party-label { font-size:7pt; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#888; margin-bottom:2px; }
.party-name  { font-weight:700; font-size:10pt; text-transform:uppercase; color:var(--blue); }
.party-address { font-size:7.5pt; color:#444; margin-top:2px; line-height:1.4; }

/* Type badge */
.type-badge-doc {
  background:#ff0000;
  color:#fff; font-size:17pt; font-weight:900;
  letter-spacing:2px; text-align:center; padding:12px 8px;
  text-transform:uppercase; line-height:1.2;
  display:block; width:100%;
  text-shadow:1px 1px 2px rgba(0,0,0,.3);
}

/* Doc No box under badge */
.doc-meta-row { display:flex; flex-direction:column; justify-content:center; align-items:center; border-top:2px solid var(--navy); width:100%; background:#ffffff; text-align:center; min-height:55px; }
.doc-meta-box { display:flex; flex-direction:column; justify-content:center; align-items:center; padding:8px 6px; text-align:center; width:100%; }
.doc-meta-label { font-size:7.5pt; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:3px; text-align:center; width:100%; }
.doc-meta-value { font-size:12.5pt; font-weight:900; letter-spacing:1px; color:var(--navy); word-break:break-all; line-height:1.25; text-align:center; width:100%; }

/* GSTIN row */
.gstin-row td { padding:5px 10px; font-size:8pt; border-top:2px solid var(--navy); background:#ffffff; }
.gstin-row td:last-child { border-right:none; background:#ffffff; text-align:center; }
.gstin-row td strong { letter-spacing:.5px; color:var(--navy); }

/* ── Items Table ── */
.items-section { padding:0; }
.items-tbl { width:100%; border-collapse:collapse; }
.items-tbl thead tr th {
  background: #1a3a7c;
  color: #fff; padding: 6px 8px; font-size: 8pt; font-weight: 700;
  text-transform: uppercase; letter-spacing: .5px; border: none;
}
.items-tbl tbody tr:nth-child(odd)  td { background:#ffffff; }
.items-tbl tbody tr:nth-child(even) td { background:#ffffff; }
.items-tbl td {
  padding:3.5px 7px; border:1px solid #d0d8e8; font-size:8pt; vertical-align:top;
}
.items-tbl .spec-line td { border:none; font-size:8pt; color:#555; padding:1px 8px; background:#ffffff; }
.col-desc  { width:40%; }
.col-hsn   { width:12%; text-align:center; }
.col-qty   { width:9%;  text-align:right; }
.col-unit  { width:8%;  text-align:center; }
.col-rate  { width:11%; text-align:right; }
.col-amount{ width:14%; text-align:right; font-weight:700; color:var(--navy); }

/* ── Totals ── */
.totals-section { padding:0; }
.totals-tbl { width:100%; border-collapse:collapse; }
.totals-tbl td { padding:3px 8px; font-size:8pt; border:1px solid #d8dce8; }
.totals-tbl .lbl { color:#444; font-weight:600; text-align:right; width:65%; }
.totals-tbl .val { text-align:right; font-weight:700; width:35%; }
.totals-tbl .total-row td   { background: #1a3a7c; color: #fff; font-size: 9.5pt; border: none; }
.totals-tbl .taxable-row td { background:#ffffff; color:var(--navy); font-weight:700; }
.totals-tbl .tax-row td     { background:#ffffff; color:#334155; }
.totals-tbl .grand-row td   {
  background: #1a3a7c;
  color: #fff; font-size: 11.5pt; font-weight: 900;
  border: none;
}

/* ── Footer pinned area ── */
.doc-footer-pinned { margin-top:auto !important; width:100%; }

/* ── Doc Bottom Bar ── */
.doc-bottom-bar {
  background: #0d1b3e;
  color: #fff; margin-top: 0; border: none;
}
.doc-bottom-bar-inner {
  display:flex; align-items:center; justify-content:center;
  flex-wrap:wrap; gap:0; padding:8px 16px;
}
.doc-footer-item {
  display:flex; align-items:center; gap:6px;
  padding:0 16px; font-size:8pt; white-space:nowrap;
  border-right:1px solid rgba(255,255,255,.3);
  font-weight:600;
}
.doc-footer-item:last-child { border-right:none; }
.doc-footer-item .fi-icon {
  width:18px; height:18px; border-radius:50%;
  background:rgba(255,255,255,.25); display:inline-flex;
  align-items:center; justify-content:center; flex-shrink:0; font-size:8pt;
}

/* Section header bars */
.section-bar-navy { background: #1a3a7c; color: #fff; font-size: 8pt; font-weight: 700; padding: 4px 10px; letter-spacing: .5px; text-transform: uppercase; border: none; }
.text-danger { color:#ff0000 !important; }
.section-bar-red  { background:#ff0000 !important;  color:#fff; font-size:8pt; font-weight:700; padding:4px 10px; letter-spacing:.5px; text-transform:uppercase; }


/* ── Turnkey 3D & 2D Styles ── */
.turnkey-metric-badge {
  background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;
  padding:4px 8px; font-size:7.5pt; font-weight:700; color:#334155;
  display:inline-flex; align-items:center; gap:5px;
}
.turnkey-pkg-card {
  border:2px solid #e2e8f0; border-radius:8px; padding:10px;
  background:#fff; height:100%; transition:all .2s ease;
}
.turnkey-pkg-card.active-pkg {
  border-color:#2563eb; background:#f0f7ff;
  box-shadow:0 4px 15px rgba(37,99,235,0.15);
}

/* ── Screen Styles for Turnkey 3D & SVGs (Strictly 16:9 Ratio, 680px x 320px) ── */
@media screen {
  #turnkey-3d-print-img { display: none !important; }
  #turnkey3DContainer {
    width: 100% !important;
    max-width: 680px !important;
    height: 320px !important;
    max-height: 320px !important;
    aspect-ratio: 16 / 9 !important;
    margin: 0 auto !important;
  }
  #turnkey3DContainer canvas {
    display: block !important;
    width: 100% !important;
    max-width: 680px !important;
    height: 320px !important;
    max-height: 320px !important;
    aspect-ratio: 16 / 9 !important;
    margin: 0 auto !important;
    border-radius: 4px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
  }
  #turnkeyFloorPlanSvg, #turnkeySideElevationSvg, #turnkeyCrossElevationSvg {
    width: 100% !important;
    max-width: 680px !important;
    height: 320px !important;
    max-height: 320px !important;
    aspect-ratio: 16 / 9 !important;
    margin: 0 auto !important;
  }
  #turnkeyFloorPlanSvg svg, #turnkeySideElevationSvg svg, #turnkeyCrossElevationSvg svg {
    width: 100% !important;
    height: 100% !important;
    aspect-ratio: 16 / 9 !important;
    margin: 0 auto !important;
    display: block !important;
  }
  .no-print-3d-controls { display: inline-block; }
}

/* ── Print / PDF Styles (Clean Margins Gap & Elegant Page Framing) ── */
@media print {
  @page {
    size: A4 portrait;
    margin: 5mm 5mm !important;
  }
  @page :first { margin: 5mm 5mm !important; }
  @page :left  { margin: 5mm 5mm !important; }
  @page :right { margin: 5mm 5mm !important; }

  html, body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: auto !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .admin-toolbar, .no-print, .no-print-3d-controls { display: none !important; }

  .doc-wrapper {
    margin: 0 auto !important;
    padding: 0 !important;
    width: 200mm !important;
    max-width: 200mm !important;
    min-height: 285mm !important;
    height: 285mm !important;
    max-height: 285mm !important;
    page-break-before: auto !important;
    break-before: auto !important;
    page-break-after: always !important;
    break-after: page !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
    position: relative !important;
  }
  .doc-wrapper:last-of-type {
    page-break-after: auto !important;
    break-after: auto !important;
  }

  .doc-sheet {
    box-shadow: none !important;
    border-radius: 0 !important;
    border: 1px solid #0d1b3e !important;
    min-height: 285mm !important;
    height: 285mm !important;
    max-height: 285mm !important;
    box-sizing: border-box !important;
    padding: 0 !important;
    margin: 0 auto !important;
    width: 200mm !important;
    max-width: 200mm !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .doc-main-content {
    flex: 1 1 auto !important;
    display: flex !important;
    flex-direction: column !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .doc-items-grow {
    flex: 1 1 auto !important;
  }

  .doc-footer-pinned {
    margin-top: auto !important;
    width: 100% !important;
    flex-shrink: 0 !important;
    page-break-inside: avoid !important;
  }

  /* Universal Header in print - EXACT SAME SIZE ON ALL PAGES */
  .doc-header { padding: 4px 8px 3px !important; margin: 0 !important; border-top: 5px solid #ff0000 !important; }
  .doc-header img { max-height: 91px !important; }
  .doc-company-name { font-size: 13pt !important; line-height: 1.1 !important; }
  .doc-address { font-size: 7pt !important; line-height: 1.2 !important; }
  .doc-gstin { font-size: 7pt !important; }

  /* Universal Footer in print - EXACT SAME SIZE ON ALL PAGES */
  .doc-bottom-bar {
  background: #0d1b3e;
  color: #fff; margin-top: 0; border: none;
}
  .doc-bottom-bar-inner { padding: 3px 0 !important; font-size: 6.2pt !important; }

  /* Quotation tables in print */
  .doc-parties-tbl td { padding: 4px 8px !important; }
  .party-name { font-size: 8.5pt !important; }
  .party-address { font-size: 7pt !important; line-height: 1.2 !important; }
  .gstin-row td { padding: 3px 8px !important; font-size: 7.5pt !important; }
  .items-tbl th { padding: 3px 6px !important; font-size: 7.5pt !important; }
  .items-tbl td { padding: 2.5px 6px !important; font-size: 7.5pt !important; }
  .totals-section { margin-top: 3px !important; page-break-inside: avoid !important; }
  .totals-tbl td { padding: 1.5px 8px !important; font-size: 7.5pt !important; }
  .totals-tbl .grand-row td   {
  background: #1a3a7c;
  color: #fff; font-size: 11.5pt; font-weight: 900;
  border: none;
}
  #russeaBottomStrip { padding: 3px 10px !important; font-size: 6.5pt !important; margin-top: 2px !important; }
  .doc-footer-pinned table { font-size: 7.5pt !important; }
  .doc-footer-pinned td { padding: 2px 6px !important; }
  .doc-footer-pinned ol { padding: 2px 8px 2px 20px !important; font-size: 6.8pt !important; line-height: 1.25 !important; margin: 0 !important; }
  .doc-footer-pinned .section-bar-navy,
  .doc-footer-pinned .section-bar-red { padding: 2px 6px !important; font-size: 7pt !important; font-weight: 700 !important; }

  /* Scope of work sheet compact print */
  .doc-sheet-scope table td { padding: 3px 6px !important; font-size: 7.2pt !important; line-height: 1.25 !important; }
  .doc-sheet-scope table th { padding: 3px 6px !important; font-size: 7.5pt !important; }
  .doc-sheet-scope div { font-size: 7.5pt !important; }

  /* 3-Package Comparison Matrix Print Compactness & Zero-Cutoff Rules */
  .turnkey-matrix-sheet {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .turnkey-matrix-tbl {
    width: 100% !important;
    border-collapse: collapse !important;
    font-family: Arial, sans-serif !important;
    margin-bottom: 2px !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .turnkey-matrix-tbl th {
    padding: 2.5px 4px !important;
    font-size: 6.8pt !important;
    line-height: 1.15 !important;
  }
  .turnkey-matrix-tbl td {
    padding: 1.8px 4px !important;
    font-size: 6.2pt !important;
    line-height: 1.15 !important;
    border: 1px solid #e2e8f0 !important;
  }
  .turnkey-matrix-tbl .matrix-rate-row td {
    padding: 2.2px 4px !important;
    font-size: 7pt !important;
    font-weight: 800 !important;
  }
  .turnkey-matrix-tbl .matrix-budget-row td {
    padding: 2.5px 4px !important;
    font-size: 7.5pt !important;
    font-weight: 900 !important;
  }

  /* Turnkey Architectural Plans print sizing (Strictly 16:9 Ratio, 2 Views Per Page, 680px x 320px) */
  #turnkey3DContainer { display: none !important; }
  #turnkey-3d-print-img {
    display: block !important;
    width: 100% !important;
    max-width: 680px !important;
    height: 320px !important;
    max-height: 320px !important;
    aspect-ratio: 16 / 9 !important;
    object-fit: contain !important;
    margin: 0 auto !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  #turnkeyFloorPlanSvg, #turnkeySideElevationSvg, #turnkeyCrossElevationSvg {
    display: block !important;
    width: 100% !important;
    max-width: 680px !important;
    height: 320px !important;
    max-height: 320px !important;
    aspect-ratio: 16 / 9 !important;
    margin: 0 auto !important;
  }
  #turnkeyFloorPlanSvg svg, #turnkeySideElevationSvg svg, #turnkeyCrossElevationSvg svg {
    width: 100% !important;
    height: 100% !important;
    aspect-ratio: 16 / 9 !important;
    margin: 0 auto !important;
    display: block !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  /* Exact Color Preservation */
  .items-tbl tbody tr:nth-child(odd)  td { background: #fafbff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .items-tbl tbody tr:nth-child(even) td { background: #f3f6ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .items-tbl thead tr th,
  .totals-tbl .total-row td,
  .totals-tbl .grand-row td,
  .doc-bottom-bar,
  .type-badge-doc,
  .section-bar-navy,
  .section-bar-red,
  .doc-header,
  .party-bill,
  .party-supplier,
  .turnkey-metric-badge,
  .gstin-row td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

@media print {
  #turnkey3DContainer canvas { display: none !important; }
  #turnkey3DContainer .turnkey-print-snapshot { display: block !important; }
  .turnkey-page-break { page-break-before: always !important; break-before: page !important; }
}

</style>
<script src="/content-protect.js"></script>
<?php if (!empty($isTurnkey)): ?>
<script src="assets/js/three.min.js"></script>
<script src="assets/js/OrbitControls.js"></script>
<script src="assets/js/turnkey_court_viewer_v5.js?v=<?= time() ?>"></script>
<?php endif; ?>
</head>
<body>

<!-- Admin Toolbar (hidden when printing) -->
<?php if ($isPublicEstimation): ?>
<div class="admin-toolbar no-print" style="background: #0d1b3e; color: white; display: flex; align-items: center; justify-content: space-between; padding: 10px 20px;">
  <div style="display: flex; align-items: center; gap: 12px;">
    <span style="font-weight: 800; font-size: 1.05rem; letter-spacing: 0.5px;">NETTINGTEAM</span>
    <span class="badge" style="background: <?= $isProforma ? '#7e22ce' : ($isPoAck ? '#0284c7' : '#2563eb') ?>; color: #fff; font-size: 0.75rem; padding: 4px 8px; border-radius: 4px;">Official <?= e($typeLabel) ?></span>
    <span style="font-family: monospace; font-size: 0.85rem; background: rgba(255,255,255,0.18); padding: 3px 8px; border-radius: 4px;"><?= e($displayDocId) ?></span>
  </div>
  <div style="display: flex; align-items: center; gap: 10px;">
    <button onclick="window.print();" class="btn btn-sm btn-success fw-bold" style="padding: 6px 14px; border-radius: 6px;">
      <i class="fa fa-print me-1"></i>Print / Save PDF
    </button>
    <a href="https://wa.me/919912399334?text=<?= urlencode('Hello NettingTeam, I want to confirm my <?= strtolower($typeLabel) ?> ' . $doc['id'] . ' for ₹' . number_format($doc['grand_total'], 2)) ?>" target="_blank" class="btn btn-sm text-white" style="background:#25D366; font-weight:700; padding: 6px 14px; border-radius: 6px;">
      <i class="fab fa-whatsapp me-1"></i>Confirm Order on WhatsApp
    </a>
  </div>
</div>
<?php else: ?>
<div class="admin-toolbar no-print">
  <a href="index.php" class="btn btn-sm btn-outline-light"><i class="fa fa-home"></i></a>
  <a href="<?= e($docsReturnUrl) ?>" class="btn btn-sm btn-outline-light" onclick="if(window.history.length>1 && document.referrer.indexOf('documents.php')!==-1){event.preventDefault();window.history.back();}"><i class="fa fa-arrow-left me-1"></i>Back</a>
  <span class="doc-id" style="<?= $isPoAck ? 'background:#0284c7;color:#fff;border-color:#0369a1;' : ($isProforma ? 'background:#7e22ce;color:#fff;border-color:#6b21a8;' : '') ?>"><?= e($displayDocId) ?></span>
  <span class="badge" style="<?= $isProforma ? 'background:#7e22ce;color:#fff;' : 'background:#64748b;' ?>"><?= $isProforma ? 'Proforma Invoice' : docTypeLabel($type, $isGst) ?></span>
  <?= statusBadge($doc['status'] ?? 'draft') ?>
  <?php if (in_array($type, ['tax_invoice', 'bill', 'proforma'])): ?>
    <?php $curPs = $doc['payment_status'] ?? (($doc['status'] ?? '') === 'paid' ? 'paid' : 'unpaid'); ?>
    <button type="button" class="btn btn-sm <?= $curPs === 'paid' ? 'btn-success' : 'btn-danger' ?> fw-bold ms-1" onclick="toggleViewPayment('<?= e($doc['id']) ?>')" title="Click to toggle Payment Status (Paid / Unpaid)" style="font-size:0.75rem;padding:3px 10px;">
      <i class="fa <?= $curPs === 'paid' ? 'fa-check-circle' : 'fa-clock' ?> me-1"></i><?= $curPs === 'paid' ? '✓ FULLY PAID' : '⏳ UNPAID' ?>
    </button>
  <?php endif; ?>
  <?php if (!empty($doc['converted_from'])): ?>
  <span class="badge no-print" style="background:#dbeafe;color:#1e40af;font-size:.7rem;font-weight:600;">
    <i class="fa fa-link me-1"></i>from <?= e($doc['converted_from']) ?>
  </span>
  <?php endif; ?>
  <div class="ms-auto d-flex gap-2">
    <button type="button" id="btnToggleRussea" class="btn btn-sm <?= $showRussea ? 'btn-danger' : 'btn-outline-secondary text-white' ?>" onclick="toggleRusseaBrand()" style="font-size:.8rem;font-weight:700;" title="Click to toggle Russea Brand On / Off">
      <i class="fa fa-tag me-1"></i>Russea Brand: <span id="lblRusseaStatus"><?= $showRussea ? 'ON' : 'OFF' ?></span>
    </button>
    <a href="create.php?edit=<?= urlencode($doc['id']) ?>" class="btn btn-sm btn-warning">
      <i class="fa fa-pen me-1"></i>Edit
    </a>
        <?php 
      $custCountry = $doc['bill_to']['country'] ?? 'India';
      $countryList = getCountriesList();
      $defaultPrefixDigits = preg_replace('/[^0-9]/', '', $countryList[$custCountry]['phone_prefix'] ?? '91');
      $waRaw = trim($doc['bill_to']['whatsapp'] ?? ($doc['bill_to']['phone'] ?? ''));
      $waNum = preg_replace('/[^0-9]/', '', $waRaw);
      if (strlen($waNum) <= 10 && !empty($defaultPrefixDigits)) {
          $waNum = $defaultPrefixDigits . $waNum;
      }
      if (!empty($waNum)):
        $docTypeLabelClean = 'Estimation';
        if ($isProforma || in_array($doc['type'] ?? '', ['proforma_invoice', 'proforma'])) {
            $docTypeLabelClean = 'Proforma Invoice';
        } elseif ($isPoAck) {
            $docTypeLabelClean = 'P.O. Acknowledgement';
        } elseif (($doc['type'] ?? '') === 'tax_invoice') {
            $docTypeLabelClean = 'Tax Invoice';
        } elseif (($doc['type'] ?? '') === 'bill') {
            $docTypeLabelClean = 'Bill of Supply';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'nettingteam.com';
        $docUrlParam = ($isProforma && strpos($doc['id'], 'PI-') !== 0) ? '&mode=proforma' : ($isPoAck ? '&mode=po_ack' : '');
        $docUrl = "https://{$host}/admin/view.php?id=" . urlencode($doc['id']) . $docUrlParam;
        $grandTotalFmt = number_format((float)($doc['grand_total'] ?? 0), 2);
        $customerName = trim($doc['bill_to']['name'] ?? 'Sir/Madam');
        $msgText = "Hello {$customerName},\n\nPlease find your official {$docTypeLabelClean} #{$displayDocId} for {$currSym}{$grandTotalFmt} from NettingTeam.\n\n📄 View & Download PDF:\n{$docUrl}\n\nThank you,\nNettingTeam\n📞 9912399334\n🌐 https://nettingteam.com";
    ?>
    <a href="https://wa.me/<?= $waNum ?>?text=<?= urlencode($msgText) ?>" target="_blank" class="btn btn-sm text-white" style="background:#25D366;font-weight:700;" title="Send document to customer on WhatsApp">
      <i class="fab fa-whatsapp me-1"></i>WhatsApp
    </a>
    <?php endif; ?>
    <?php if ($isPoAck): ?>
    <a href="view.php?id=<?= urlencode($doc['id']) ?>" class="btn btn-sm btn-light fw-bold" title="Return to standard invoice/estimation view">
      <i class="fa fa-arrow-left me-1"></i>Standard View
    </a>
    <?php elseif ($isProforma): ?>
    <a href="view.php?id=<?= urlencode($doc['id']) ?>" class="btn btn-sm btn-light fw-bold" title="Return to standard estimation view">
      <i class="fa fa-arrow-left me-1"></i>Estimation View
    </a>
    <?php if ($type === 'estimation' || strpos($doc['id'], 'EST-') === 0): ?>
    <a href="ajax.php?action=convert&id=<?= urlencode($doc['id']) ?>&to=proforma_invoice" class="btn btn-sm text-white fw-bold" style="background:#7e22ce;" title="Save as permanent Proforma Invoice (PI serial)">
      <i class="fa fa-bolt me-1"></i>Save as Official PI (PI-...)
    </a>
    <?php endif; ?>
    <?php else: ?>
    <?php if ($type === 'estimation' || strpos($doc['id'], 'EST-') === 0): ?>
    <a href="view.php?id=<?= urlencode($doc['id']) ?>&mode=proforma" class="btn btn-sm text-white fw-bold" style="background:#7e22ce;" title="View and print official Proforma Invoice copy">
      <i class="fa fa-file-invoice-dollar me-1"></i>Proforma Invoice
    </a>
    <?php endif; ?>
    <a href="view.php?id=<?= urlencode($doc['id']) ?>&mode=po_ack" class="btn btn-sm text-white fw-bold" style="background:#0284c7;" title="View and print official P.O. Acknowledgement / Acceptance Copy">
      <i class="fa fa-file-signature me-1"></i>P.O. Acknowledgement Copy
    </a>
    <?php endif; ?>
        <?php if ($isAmenitiesProposal): ?>
      <?php if ($showAmenitiesLayout): ?>
      <a href="view.php?id=<?= urlencode($doc['id']) ?>&standard=1" class="btn btn-sm btn-outline-light fw-bold" title="Switch to Official GCM Multi-Page Layout with 3D Court & BOQ">
        <i class="fa fa-file-invoice me-1"></i>Standard GCM Layout
      </a>
      <a href="view.php?id=<?= urlencode($doc['id']) ?>&template=amenities&show_rates=<?= $showAmenitiesRates ? '0' : '1' ?>" class="btn btn-sm btn-outline-warning fw-bold" title="Toggle itemized line rates on/off">
        <i class="fa fa-eye me-1"></i><?= $showAmenitiesRates ? 'Hide Line Rates' : 'Show Line Rates' ?>
      </a>
      <?php else: ?>
      <a href="view.php?id=<?= urlencode($doc['id']) ?>&template=amenities" class="btn btn-sm text-white fw-bold" style="background:#16a34a;" title="Switch to Exact PDF Proposal Layout matching customer scan">
        <i class="fa fa-file-contract me-1"></i>Exact PDF Proposal Layout
      </a>
      <?php endif; ?>
    <?php endif; ?>

    <button onclick="if(window.TurnkeyViewer && window.TurnkeyViewer.updateSnapshot){window.TurnkeyViewer.updateSnapshot('turnkey-3d-print-img', 'turnkey3DContainer');} window.print();" class="btn btn-sm btn-success fw-bold">
      <i class="fa fa-print me-1"></i>Print / Save PDF
    </button>
    <a href="ajax.php?action=duplicate&id=<?= urlencode($doc['id']) ?>" class="btn btn-sm btn-outline-light" onclick="return confirm('Duplicate this estimation as a new draft?');" title="Duplicate this estimation as a new draft">
      <i class="fa fa-copy me-1"></i>Duplicate
    </a>
    <a href="<?= e($docsReturnUrl) ?>" class="btn btn-sm btn-outline-light" title="Close and return to documents"><i class="fa fa-xmark"></i></a>
  </div>
</div>
<?php endif; ?>

<?php if ($successMsg = flashGet('success')): ?>
<div class="alert alert-success alert-dismissible fade show no-print m-2 py-2 px-3 fw-bold" role="alert" style="box-shadow:0 4px 12px rgba(16,185,129,0.15);">
  <i class="fa fa-circle-check me-2"></i><?= e($successMsg) ?>
  <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errorMsg = flashGet('error')): ?>
<div class="alert alert-danger alert-dismissible fade show no-print m-2 py-2 px-3 fw-bold" role="alert">
  <i class="fa fa-triangle-exclamation me-2"></i><?= e($errorMsg) ?>
  <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<!-- ── Mobile Pinch / Swipe Hint ── -->
<div class="d-lg-none table-swipe-hint-view no-print">
  <span><i class="fa fa-arrows-left-right text-info me-1"></i>Pinch or swipe sideways to view full A4 quotation</span>
  <button type="button" class="btn btn-xs btn-outline-light py-0 px-2 fw-bold" onclick="window.print()"><i class="fa fa-print me-1"></i>Print</button>
</div>
<div class="doc-view-outer">
<!-- ── Business Workflow Sequence Bar ─────────────────────────────── -->
<div class="no-print" style="background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:.6rem 1rem;margin:.5rem 0 0;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
    <!-- Pipeline Sequence Steps -->
    <div style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:700;">
      <span style="color:#64748b;font-size:.72rem;text-transform:uppercase;letter-spacing:0.5px;margin-right:4px;">Workflow:</span>
      
      <!-- Step 1: Estimation -->
      <a href="view.php?id=<?= urlencode($doc['id']) ?>" class="badge text-decoration-none" style="<?= ($type === 'estimation' && !$isPoAck && !$isProforma) ? 'background:#1d4ed8;color:#fff;box-shadow:0 2px 4px rgba(29,78,216,0.3);' : 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;' ?>padding:5px 9px;border-radius:6px;">
        1. Estimation (<?= e(strpos($doc['id'], 'EST-')===0 ? $doc['id'] : 'EST') ?>)
      </a>
      <span style="color:#94a3b8;">&rarr;</span>

      <!-- Step 2: Proforma Invoice -->
      <a href="view.php?id=<?= urlencode($doc['id']) ?>&mode=proforma" class="badge text-decoration-none" style="<?= $isProforma ? 'background:#7e22ce;color:#fff;box-shadow:0 2px 4px rgba(126,34,206,0.3);' : 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;' ?>padding:5px 9px;border-radius:6px;">
        2. Proforma (<?= e(strpos($doc['id'], 'PI-')===0 ? $doc['id'] : preg_replace('/^(EST|INV|BILL|PRO)/i', 'PI', $doc['id'])) ?>)
      </a>
      <span style="color:#94a3b8;">&rarr;</span>

      <!-- Step 3: P.O. Acknowledgement -->
      <a href="view.php?id=<?= urlencode($doc['id']) ?>&mode=po_ack" class="badge text-decoration-none" style="<?= $isPoAck ? 'background:#0284c7;color:#fff;box-shadow:0 2px 4px rgba(2,132,199,0.3);' : 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;' ?>padding:5px 9px;border-radius:6px;">
        3. P.O. Ack (<?= e(preg_replace('/^(EST|INV|BILL|PRO|PI)/i', 'APO', $doc['id'])) ?>)
      </a>
      <span style="color:#94a3b8;">&rarr;</span>

      <!-- Step 4: Tax Invoice -->
      <?php if (!empty($doc['converted_from']) || $type === 'tax_invoice'): ?>
      <span class="badge" style="<?= ($type === 'tax_invoice' && !$isPoAck && !$isProforma) ? 'background:#15803d;color:#fff;box-shadow:0 2px 4px rgba(21,128,61,0.3);' : 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;' ?>padding:5px 9px;border-radius:6px;">
        4. Tax Invoice (<?= e($type==='tax_invoice' ? $doc['id'] : 'INV') ?>)
      </span>
      <?php else: ?>
      <span class="badge" style="background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1;padding:5px 9px;border-radius:6px;">
        4. Tax Invoice (Pending)
      </span>
      <?php endif; ?>
    </div>

    <!-- Quick Action Buttons -->
    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
      <?php if ($type === 'estimation' && !$isPoAck && !$isProforma): ?>
        <a href="view.php?id=<?= urlencode($doc['id']) ?>&mode=proforma" class="btn btn-sm text-white fw-bold" style="background:#7e22ce;font-size:.78rem;">
          <i class="fa fa-file-invoice-dollar me-1"></i>View as Proforma Invoice
        </a>
        <a href="ajax.php?action=convert&id=<?= urlencode($doc['id']) ?>&to=proforma_invoice"
           onclick="return confirm('Generate Official Proforma Invoice (PI) from <?= e($doc['id']) ?>?\nA new PI document will be created with PI serial.')"
           class="btn btn-sm text-white fw-bold" style="background:#9333ea;font-size:.78rem;">
          <i class="fa fa-bolt me-1"></i>Convert to PI (PI-...)
        </a>
        <a href="view.php?id=<?= urlencode($doc['id']) ?>&mode=po_ack" class="btn btn-sm text-white fw-bold" style="background:#0284c7;font-size:.78rem;">
          <i class="fa fa-file-signature me-1"></i>Issue P.O. Ack (APO)
        </a>
        <a href="ajax.php?action=convert&id=<?= urlencode($doc['id']) ?>&to=tax_invoice"
           onclick="return confirm('Create Tax Invoice from <?= e($doc['id']) ?>?\nItems, GST, and terms will be copied with new INV serial.')"
           class="btn btn-sm btn-success fw-bold" style="font-size:.78rem;">
          <i class="fa fa-file-invoice me-1"></i>Convert to Tax Invoice &rarr;
        </a>
      <?php elseif ($isProforma): ?>
        <a href="view.php?id=<?= urlencode($doc['id']) ?>" class="btn btn-sm btn-outline-secondary fw-bold" style="font-size:.78rem;">
          <i class="fa fa-arrow-left me-1"></i>Back to Estimation
        </a>
        <?php if ($type === 'estimation' || strpos($doc['id'], 'EST-') === 0): ?>
        <a href="ajax.php?action=convert&id=<?= urlencode($doc['id']) ?>&to=proforma_invoice"
           onclick="return confirm('Save as permanent Proforma Invoice with dedicated PI serial?')"
           class="btn btn-sm text-white fw-bold" style="background:#7e22ce;font-size:.78rem;">
          <i class="fa fa-bolt me-1"></i>Save as Permanent PI (PI-...)
        </a>
        <?php endif; ?>
        <a href="ajax.php?action=convert&id=<?= urlencode($doc['id']) ?>&to=tax_invoice"
           onclick="return confirm('Create Tax Invoice from this Proforma Invoice?\nItems, GST, customer, and terms will be carried forward to INV.')"
           class="btn btn-sm btn-success fw-bold" style="font-size:.78rem;">
          <i class="fa fa-file-invoice me-1"></i>Convert to Tax Invoice (INV) &rarr;
        </a>
      <?php elseif ($isPoAck): ?>
        <a href="view.php?id=<?= urlencode($doc['id']) ?>" class="btn btn-sm btn-outline-secondary fw-bold" style="font-size:.78rem;">
          <i class="fa fa-arrow-left me-1"></i>Back to Estimation
        </a>
        <a href="ajax.php?action=convert&id=<?= urlencode($doc['id']) ?>&to=tax_invoice"
           onclick="return confirm('Create Tax Invoice from this order?\nItems, GST, customer, and P.O. reference will be carried forward to INV.')"
           class="btn btn-sm btn-success fw-bold" style="font-size:.78rem;">
          <i class="fa fa-file-invoice me-1"></i>Convert to Tax Invoice (INV) &rarr;
        </a>
      <?php elseif ($type === 'tax_invoice'): ?>
        <?php if (!empty($doc['converted_from'])): ?>
        <a href="view.php?id=<?= urlencode($doc['converted_from']) ?>" class="btn btn-sm btn-outline-primary fw-semibold" style="font-size:.78rem;">
          <i class="fa fa-link me-1"></i>Originating Document: <?= e($doc['converted_from']) ?>
        </a>
        <a href="view.php?id=<?= urlencode($doc['converted_from']) ?>&mode=proforma" class="btn btn-sm btn-outline-purple fw-semibold" style="font-size:.78rem;color:#7e22ce;border:1px solid #d8b4fe;">
          <i class="fa fa-file-invoice-dollar me-1"></i>Proforma Copy
        </a>
        <a href="view.php?id=<?= urlencode($doc['converted_from']) ?>&mode=po_ack" class="btn btn-sm btn-outline-info fw-semibold" style="font-size:.78rem;">
          <i class="fa fa-file-signature me-1"></i>P.O. Ack (<?= e(preg_replace('/^(EST|INV)/i', 'APO', $doc['converted_from'])) ?>)
        </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (!empty($doc['converted_from'])): ?>
<div class="no-print" style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:.45rem .9rem;margin:.4rem 0 0;font-size:.78rem;color:#166534;">
  <i class="fa fa-link me-1"></i>Converted from Estimation:
  <a href="view.php?id=<?= urlencode($doc['converted_from']) ?>" style="color:#15803d;font-weight:700;"><?= e($doc['converted_from']) ?></a>
  &mdash; <a href="view.php?id=<?= urlencode($doc['converted_from']) ?>" style="color:#15803d;">View original estimation &rarr;</a>
</div>
<?php endif; ?>

<?php
$allItems = $doc['items'] ?? [];
$totalItemCount = count($allItems);

// Universal Dynamic Pagination Engine for Quotation Pages:
// - If 1 to 5 items: Fits on 1 single page with totals and commercial footer.
// - If 6 to 16 items: Splits across 2 pages:
//     * Page 1: holds up to 9 items (~60% of items)
//     * Page 2: holds remaining items (up to 6-7 items) + Commercial Totals (Grand Total) + Bank Details + Terms + Stamp & Sign
// - If 17+ items: Splits across 3+ pages:
//     * Intermediate Pages (Page 1, Page 2, etc.): hold up to 9 items each
//     * Final Quotation Page: holds remaining items (up to 6 items) + Commercial Totals + Bank Details + Terms + Stamp & Sign

// Intelligent visual line calculation:
// - Small items: 8-12 items fit on Page 1
// - Big items: up to 8 items fit on Page 1
// - Item size (small vs big) is dynamically determined by description length and specifications
$totalItemVisualLines = 0;
$bigItemCount = 0;
$smallItemCount = 0;

foreach ($allItems as $it) {
    $desc = trim($it['description'] ?? '');
    $descLen = strlen($desc);
    $specs = $it['specs'] ?? [];
    if (is_string($specs)) $specs = array_filter(explode("\n", $specs));
    $specCount = count($specs);
    $specsText = implode(' ', $specs);
    $specsLen = strlen($specsText);

    // Visual line height calculation
    $lines = 1.0;
    if ($descLen > 65)  $lines += 0.5; // wraps to line 2
    if ($descLen > 130) $lines += 0.8; // wraps to line 3
    if ($descLen > 200) $lines += 1.0; // wraps to line 4+

    // Specs line height (joined with ' • ' in table)
    if ($specCount > 0) {
        $lines += ($specsLen > 90) ? 1.2 : 0.6;
    }

    if ($lines >= 2.0) {
        $bigItemCount++;
    } else {
        $smallItemCount++;
    }

    $totalItemVisualLines += $lines;
}

$quotationPages = [];
// 1 Page Rule:
// - Small items can add 8-12 items on Page 1
// - Big items can add up to 8 items on Page 1
// - Total visual capacity for 1 page with commercial totals, bank, terms, & stamp: up to 18.5 lines
$fitsOnOnePage = ($totalItemCount <= 12 && $totalItemVisualLines <= 18.5 && $bigItemCount <= 8);

if ($fitsOnOnePage) {
    $quotationPages = [$allItems];
} elseif ($totalItemCount <= 22) {
    // Multi-page Quotation:
    // - For 17 turnkey court items: exactly 9 items on Page 1, remaining 8 items + commercial footer on Page 2
    if ($totalItemCount === 17) {
        $p1Count = 9;
    } else {
        $isPredominantlyBig = ($bigItemCount >= ($totalItemCount * 0.4));
        if ($isPredominantlyBig) {
            // Big items: Page 1 holds up to 8-9 items, leaving remaining items (up to 8) for Page 2
            $p1Count = min(9, max($totalItemCount - 8, (int)ceil($totalItemCount * 0.55)));
        } else {
            // Small items: Page 1 can hold 8-12 items!
            $p1Count = min(12, max(8, max($totalItemCount - 8, (int)ceil($totalItemCount * 0.65))));
            // Ensure Page 2 has at least 2 items
            if ($p1Count >= $totalItemCount) {
                $p1Count = $totalItemCount - 2;
            }
        }
    }
    $quotationPages[] = array_slice($allItems, 0, $p1Count);
    $quotationPages[] = array_slice($allItems, $p1Count);
} else {
    // 3+ Pages
    $rem = $allItems;
    while (count($rem) > 8) {
        $remCount = count($rem);
        if ($remCount <= 18) {
            $pCount = min(9, (int)ceil($remCount * 0.55));
        } else {
            $pCount = 9;
        }
        $quotationPages[] = array_splice($rem, 0, $pCount);
    }
    if (!empty($rem)) {
        $quotationPages[] = $rem;
    }
}

$totalQuotPages = count($quotationPages);
$itemGlobalNum = 0;
?>

<?php if (!empty($showAmenitiesLayout)): ?>
<!-- ── EXACT AMENITIES PROPOSAL (2 PAGES) ── -->
<div class="doc-wrapper amenities-proposal-wrapper amenities-page-1" style="max-width:840px;margin:1.5rem auto 2rem;">
  <div class="amenities-sheet" style="background:#fff;box-shadow:0 4px 20px rgba(0,0,0,0.12);padding:25px;border:1.5px solid #000;font-family:Arial,Helvetica,sans-serif;font-size:9.5pt;color:#000;">
    
    <!-- Top Green Header Banner -->
    <div style="background-color:#c6efce;color:#78281f;text-align:center;font-weight:700;font-size:13pt;padding:10px 14px;border:1.5px solid #000;border-bottom:none;letter-spacing:0.2px;">
      <?= e($doc['proposal_title'] ?? 'Proposal for Supply and Installation of Outdoor Multi Sports Flooring Amenities') ?>
    </div>
    
    <!-- Lavender / Grey Sub-Header -->
    <div style="background-color:#d9d2e9;color:#20124d;text-align:center;font-weight:700;font-size:10.5pt;padding:6px 14px;border:1.5px solid #000;border-bottom:1.5px solid #000;">
      <?= e($doc['court_subtitle'] ?? 'Multi Purpose court - 120ft x 60ft') ?>
    </div>

    <!-- Table of 7 Items -->
    <table class="amenities-tbl" style="width:100%;border-collapse:collapse;border:1.5px solid #000;font-size:9pt;margin-top:-1px;">
      <colgroup>
        <col style="width:5%;">
        <col style="width:18%;">
        <col style="width:51%;">
        <col style="width:8%;">
        <col style="width:8%;">
        <col style="width:10%;">
      </colgroup>
      <tbody>
      <?php 
        $defaultAmenitiesItems = [
          [
            'num' => 1,
            'cat' => 'Drainage',
            'title' => 'Construction of open saucer drain',
            'desc' => "Open saucer drain of width (6 inches x 24 inches) or (9 inches x 24 inches).\nThe height of drain will be made to match the side of the court. This drain will\nchannelize water till one corner of the court. From their drainage connection\nto be provided by client.",
            'unit' => 'Rft',
            'qty' => 120,
            'rate' => 450.00,
            'amount' => 54000.00
          ],
          [
            'num' => 2,
            'cat' => 'Sub base',
            'title' => 'Asphalt',
            'desc' => "Asphalt Subbase Construction:\n*Layer of up to 4\" (100mm) Compacted WMM using Stone Size 40 MM to\n60 MM and Compaction of same in One Layer\n*Cleaning of Sub base (Wire Brushing) before starting Asphalting Layers\n*Tack/Primer Coat to be applied before BM & AC Layers (Qty Consumption\nwill have Tack Coat of Up to 3kg Per 10 Sq. Meter)\n*Bituminous Layer of compacted thickness of up to 30 mm using mix of 20\nand 10 MM Stone with 80-100/60-70 Grade Bitumen(Subject to availability).\nBituminous binder percentage upto3.5%.\n*Compacting thickness of AC of up to 20 mm using 6mm Stone with 80-\n100/60-70 Grade Bitumen( Subject to availability). Bituminous binder\npercentage up to 3.5-4%.\n*Final Layer of Seal Coat using Stone Dust with Bitumen to make the surface\nappropriate for Synthetic Installation\n*If a hot mix plant is being brought to the site, path, location and barrication\nshould be provided by client, keeping in mind the atmospheric and\nenvironmental factors.",
            'unit' => 'Sqft',
            'qty' => 7200,
            'rate' => 95.00,
            'amount' => 684000.00
          ],
          [
            'num' => 3,
            'cat' => 'Fabrication',
            'title' => 'Fabrication of 15 feet height',
            'desc' => "Fencing Height: 15 ft overall height\nVertical Posts: 72 × 72 mm MS square pipes with 3 mm wall thickness.\nHorizontal Members: 50 × 50 mm MS square pipes with 2 mm wall\nthickness.\nPost Spacing: 10 ft centre-to-centre with Top, Centre & Bottom horizontal\nsupports.\nInstallation: Vertical posts embedded 2 ft in ground or fixed on base plate\nwith anchor fasteners.\nFinish: Primer and Enamel Paint in Bus Green colour.",
            'unit' => 'Sqft',
            'qty' => 5400,
            'rate' => 95.00,
            'amount' => 513000.00
          ],
          [
            'num' => 4,
            'cat' => 'Nylon Net',
            'title' => 'Net',
            'desc' => (!empty($showRussea) ? "RUSSEA™ High-Tenacity Green Net 2.5mm thick- 40mm box size (Russea™/Tuff equivalent & exceeding specifications)." : "Russea™/Tuff Green Net 2.5mm thick- 40mm box size.") . " Netting to be done on\nall four sides, provided to minimize the ball from escaping outside of the net",
            'unit' => 'Sqft',
            'qty' => 5824,
            'rate' => 22.00,
            'amount' => 128128.00
          ],
          [
            'num' => 5,
            'cat' => 'Acrylic sports flooring',
            'title' => 'Acrylic 8 layer - Synthetic sports flooring',
            'desc' => "Acrylic courts give the necessary speed, texture, and vibrant colors. Acrylic\nCoating Systems are multi-layer latex based paint systems that establish or\nrestore surface texture, protect underlying asphalt systems from the weather\nand climatic change, enhance overall playability by establishing play speed\nand ultimately provide the surface with a vibrant color scheme.\n\n8 Layer System :- (1 layer of primer + 1 layer of resurfacer + 3 layers of\nliquid rubber + 1 layer of resurfacer + 1 layers of Acrylic pre coats + 1\nacrylic top coat which is blended with UV granules and Silica sand +\nLine marking )\n\nProduct Specification\n• High density for improved strength.\n• Environment friendly.",
            'unit' => 'Sqft',
            'qty' => 7200,
            'rate' => 85.00,
            'amount' => 612000.00
          ],
          [
            'num' => 6,
            'cat' => 'Tennis Poles',
            'title' => 'Fixed Tennis Poles along with professional net',
            'desc' => "- Fixed Post Made of 80mm x 80mm MS pipe\n- with special internal ratchet system for tightening of rope\n- The ratchet enables perfect tension control of the net\n- with branded professional quality net.",
            'unit' => 'Pair',
            'qty' => 1,
            'rate' => 45000.00,
            'amount' => 45000.00
          ],
          [
            'num' => 7,
            'cat' => 'Basketball poles',
            'title' => 'Fixed Basketball poles',
            'desc' => "*Pole will Made of (6\") Pipe\n*equipped with 1.2 m extention.\n*Acrylic Board Thickness - 20 MM (Imported Quality),\n*Size of Board - 180cm x 105cm\n*Olympion Ring\n*Quality Net\n*Spray Painted\n*Approximately 200 kgs per pair",
            'unit' => 'Pair',
            'qty' => 1,
            'rate' => 145000.00,
            'amount' => 145000.00
          ],
        ];

        $displayItems = [];
        if (!empty($doc['items']) && count($doc['items']) === 7) {
          foreach ($doc['items'] as $i => $it) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $it['description'] ?? ''))));
            $cat = $it['category'] ?? ($lines[0] ?? $defaultAmenitiesItems[$i]['cat']);
            $title = $it['item_title'] ?? ($lines[1] ?? $defaultAmenitiesItems[$i]['title']);
            $desc = implode("\n", array_slice($lines, 2));
            if (empty($desc)) $desc = $defaultAmenitiesItems[$i]['desc'];
            $displayItems[] = [
              'num'    => $i + 1,
              'cat'    => $cat,
              'title'  => $title,
              'desc'   => $desc,
              'unit'   => $it['unit'] ?? $defaultAmenitiesItems[$i]['unit'],
              'qty'    => $it['qty'] ?? $defaultAmenitiesItems[$i]['qty'],
              'rate'   => (float)($it['rate'] ?? $defaultAmenitiesItems[$i]['rate']),
              'amount' => (float)($it['amount'] ?? $defaultAmenitiesItems[$i]['amount'])
            ];
          }
        } else {
          $displayItems = $defaultAmenitiesItems;
        }
      ?>

      <?php foreach ($displayItems as $row): ?>
        <tr>
          <td style="border:1px solid #000;text-align:center;vertical-align:top;font-weight:700;padding:6px 4px;"><?= $row['num'] ?></td>
          <td style="border:1px solid #000;text-align:center;vertical-align:top;font-weight:700;padding:6px 6px;"><?= e($row['cat']) ?></td>
          <td style="border:1px solid #000;vertical-align:top;padding:6px 8px;">
            <div style="font-weight:700;text-align:center;margin-bottom:6px;"><?= e($row['title']) ?></div>
            <div style="font-size:8.3pt;line-height:1.32;text-align:left;">
              <?= nl2br(e($row['desc'])) ?>
            </div>
          </td>
          <td style="border:1px solid #000;text-align:center;vertical-align:top;padding:6px;"><?= e($row['unit']) ?></td>
          <td style="border:1px solid #000;text-align:center;vertical-align:top;padding:6px;"><?= e($row['qty']) ?></td>
          <td style="border:1px solid #000;text-align:right;vertical-align:top;padding:6px;">
            <?= !empty($showAmenitiesRates) ? ('₹ ' . number_format($row['amount'], 2)) : '' ?>
          </td>
        </tr>
      <?php endforeach; ?>

        <!-- Page 1 Bottom Summary Row -->
        <tr style="background-color:#fce5cd;font-weight:700;font-size:9.5pt;">
          <td colspan="5" style="border:1.5px solid #000;text-align:right;padding:8px 10px;">
            Total value Inclusive of Installation and line markings but excluding GST &amp; Freight :
          </td>
          <td style="border:1.5px solid #000;text-align:right;padding:8px 8px;font-weight:800;font-size:10pt;">
            ₹ <?= number_format($doc['subtotal'] ?? 2181128, 2) ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ── PAGE 2 — FREIGHT, GST & COMMERCIAL SUMMARY ── -->
<div class="doc-wrapper amenities-proposal-wrapper amenities-page-2" style="max-width:840px;margin:1.5rem auto 3rem;page-break-before:always;break-before:page;">
  <div class="amenities-sheet" style="background:#fff;box-shadow:0 4px 20px rgba(0,0,0,0.12);padding:25px;border:1.5px solid #000;min-height:900px;font-family:Arial,Helvetica,sans-serif;font-size:9.5pt;color:#000;">
    <table class="amenities-tbl" style="width:100%;border-collapse:collapse;border:1.5px solid #000;font-size:9.5pt;">
      <colgroup>
        <col style="width:78%;">
        <col style="width:22%;">
      </colgroup>
      <tbody>
        <tr style="background-color:#fce5cd;font-weight:700;">
          <td style="border:1px solid #000;text-align:right;padding:8px 12px;">
            Freight :
          </td>
          <td style="border:1px solid #000;text-align:right;padding:8px 12px;font-weight:800;">
            ₹ <?= number_format($doc['freight'] ?? ($doc['turnkey_config']['freight'] ?? 25000), 2) ?>
          </td>
        </tr>
        <tr style="background-color:#fce5cd;font-weight:700;">
          <td style="border:1px solid #000;text-align:right;padding:8px 12px;">
            GST @ 18% :
          </td>
          <td style="border:1px solid #000;text-align:right;padding:8px 12px;font-weight:800;">
            ₹ <?= number_format($doc['tax_amount'] ?? 392603.04, 2) ?>
          </td>
        </tr>
        <tr style="background-color:#fce5cd;font-weight:800;font-size:10pt;">
          <td style="border:1.5px solid #000;text-align:right;padding:10px 12px;">
            Total value Inclusive of Installation and line markings ,GST &amp; Freight :
          </td>
          <td style="border:1.5px solid #000;text-align:right;padding:10px 12px;font-size:11.5pt;color:#78281f;font-weight:900;">
            ₹ <?= number_format($doc['grand_total'] ?? 2598731.04, 2) ?>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Commercial Notes & Payment Terms -->
    <div style="margin-top:25px;border:1px solid #999;padding:15px;background:#f9f9f9;font-size:9pt;line-height:1.6;">
      <div style="font-weight:700;font-size:9.5pt;color:#1e3a8a;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">Commercial Terms &amp; Conditions:</div>
      <ol style="margin:0;padding-left:18px;">
        <li><strong>Turnkey Scope:</strong> Complete supply, fabrication, civil sub-base, 8-layer synthetic acrylic surfacing, line marking, and professional installation of fixed tennis and basketball amenities.</li>
        <li><strong>Drainage Outlet:</strong> Saucer drain channelizes water to one corner of the court. Final discharge pipeline connection from corner to be provided by client.</li>
        <li><strong>Hot Mix Plant:</strong> If a hot mix plant is brought to site, necessary approach path, designated location, and safety barrication to be provided by client.</li>
        <li><strong>Payment Terms:</strong> 50% Advance with confirmation, 40% Progressive against supply &amp; execution, 10% on completion and handover.</li>
        <li><strong>Site Amenities:</strong> Electricity, construction water, secure material storage, and site barrication to be provided by client at site.</li>
      </ol>
    </div>

    <!-- Official Stamp & Authorized Signatory -->
    <div style="margin-top:50px;display:flex;justify-content:space-between;align-items:flex-end;padding:0 10px;">
      <div style="font-size:8.5pt;color:#444;line-height:1.5;">
        <div><strong>NETTINGTEAM</strong></div>
        <div>GSTIN: 36EBFPM5722R2ZY &bull; Phone: 9912399334</div>
        <div>Hyderabad, Telangana &bull; www.nettingteam.com</div>
        <div style="font-size:8pt;color:#16a34a;font-weight:600;margin-top:2px;">RUSSEA™ &mdash; Official Registered Brand</div>
      </div>
      <div style="text-align:center;min-width:180px;">
        <img src="uploads/COMP-0001_signature.png" alt="Signature" style="max-height:45px;display:block;margin:0 auto 4px;" onerror="this.style.display='none';">
        <div style="font-weight:700;font-size:9pt;border-top:1.5px solid #222;padding-top:4px;">Authorized Signatory</div>
        <div style="font-size:8pt;color:#555;">For NETTINGTEAM</div>
      </div>
    </div>

  </div>
</div>
<?php elseif (!empty($doc['blocks']) && (($doc['estimation_subtype'] ?? '') === 'multi_block')): ?>
<!-- ── MULTI-BLOCK / MULTI-OPTION ESTIMATION DOCUMENT ──────────────────────────────────────── -->
<div class="doc-wrapper doc-page-quotation">
<div class="doc-sheet" style="padding-bottom:10px;">

  <!-- Universal Header -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <!-- Document Title / Header Banner -->
  <div style="background:#0d1b3e;color:#fff;text-align:center;padding:5px 10px;margin:2px 0 4px;font-weight:900;font-size:12pt;letter-spacing:1px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
    <?= !empty($doc['title']) ? e($doc['title']) : 'ESTIMATION' ?>
  </div>

  <!-- Parties & Meta Table -->
  <table class="doc-parties-tbl" style="margin-bottom:6px;">
    <colgroup>
      <col style="width:34%">
      <col style="width:33%">
      <col style="width:33%">
    </colgroup>
    <tbody>
      <tr>
        <td class="party-bill" style="vertical-align:top;padding:5px 8px;">
          <div class="party-label" style="font-size:7pt;font-weight:800;color:#1e40af;text-transform:uppercase;">Supplier (From)</div>
          <div class="party-name" style="font-size:9pt;font-weight:900;color:#0f172a;"><?= e($company['name']) ?></div>
          <div class="party-address" style="font-size:7pt;line-height:1.25;color:#334155;"><?= nl2br(e($company['address'])) ?></div>
          <div style="font-size:7pt;margin-top:2px;font-weight:700;">
            GSTIN: <span><?= e($company['gstin'] ?: 'NA') ?></span> &bull; Ph: <span><?= e($company['phone'] ?: '9912399334') ?></span>
          </div>
        </td>
        <td class="party-bill" style="vertical-align:top;padding:5px 8px;border-left:1.5px solid var(--navy);">
          <div class="party-label" style="font-size:7pt;font-weight:800;color:#1e40af;text-transform:uppercase;">Customer (To)</div>
          <div class="party-name" style="font-size:9pt;font-weight:900;color:#0f172a;"><?= e($doc['bill_to']['name'] ?? '—') ?></div>
          <div class="party-address" style="font-size:7pt;line-height:1.25;color:#334155;"><?= nl2br(e($doc['bill_to']['address'] ?? '')) ?></div>
          <?php if (!empty($doc['bill_to']['phone'])): ?>
          <div style="font-size:7pt;margin-top:2px;font-weight:700;color:#0284c7;">
            <i class="fa fa-phone me-1"></i>Contact: <?= e($doc['bill_to']['phone']) ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($doc['bill_to']['gstin'])): ?>
          <div style="font-size:7pt;font-weight:700;color:#334155;">
            GSTIN: <?= e($doc['bill_to']['gstin']) ?>
          </div>
          <?php endif; ?>
        </td>
        <td class="party-meta" style="vertical-align:top;padding:5px 8px;border-left:1.5px solid var(--navy);background:#f8fafc;">
          <div style="margin-bottom:4px;">
            <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#64748b;font-weight:700;display:block;">ESTIMATION / QUOTE NO</span>
            <span style="font-size:10pt;font-weight:900;color:#0d1b3e;"><?= e($displayDocId) ?></span>
          </div>
          <div>
            <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#64748b;font-weight:700;display:block;">DATE</span>
            <span style="font-size:8.5pt;font-weight:800;color:#0f172a;"><?= e(date('d/m/Y', strtotime($doc['date'] ?? 'today'))) ?></span>
          </div>
          <?php if (!empty($doc['po_number'])): ?>
          <div style="margin-top:3px;">
            <span style="font-size:6.5pt;text-transform:uppercase;color:#b45309;font-weight:700;display:block;">CUSTOMER P.O. REF</span>
            <span style="font-size:8pt;font-weight:800;color:#b45309;"><?= e($doc['po_number']) ?></span>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Individual Block Tables (Each with its own Grand Total) -->
  <div class="doc-main-content">
  <div class="doc-items-grow" style="padding:0 2px;">

  <?php foreach ($doc['blocks'] as $bIdx => $b): 
    $firstUnit = !empty($b['items'][0]['unit']) ? $b['items'][0]['unit'] : 'SFT';
  ?>
  <div class="multi-block-box" style="margin-bottom:12px;page-break-inside:avoid;border:1.5px solid #000;border-radius:2px;overflow:hidden;background:#fff;">
    <!-- Block Title Bar -->
    <div style="background:#0d1b3e;color:#fff;padding:4px 8px;font-weight:900;font-size:9pt;letter-spacing:0.5px;display:flex;justify-content:space-between;align-items:center;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
      <span><?= e($b['title']) ?></span>
      <span style="font-size:8pt;color:#fef08a;font-weight:800;">GRAND TOTAL: ₹<?= fmtAmount($b['grand_total']) ?></span>
    </div>
    
    <!-- Table -->
    <table class="items-tbl" style="width:100%;border-collapse:collapse;font-size:8pt;">
      <thead>
        <tr style="background:#f1f5f9;border-bottom:1.5px solid #000;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
          <th style="width:6%;text-align:center;padding:4px;border-right:1px solid #cbd5e1;">S.No</th>
          <th style="width:46%;padding:4px 8px;border-right:1px solid #cbd5e1;">Description</th>
          <th style="width:16%;text-align:center;padding:4px;border-right:1px solid #cbd5e1;">Quantity in <?= e($firstUnit) ?></th>
          <th style="width:14%;text-align:right;padding:4px 8px;border-right:1px solid #cbd5e1;">Rate (₹)</th>
          <th style="width:18%;text-align:right;padding:4px 8px;">Amount (₹)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($b['items'] as $sIdx => $it): ?>
        <tr style="border-bottom:1px solid #e2e8f0;">
          <td style="text-align:center;font-weight:700;vertical-align:top;padding:5px;border-right:1px solid #cbd5e1;"><?= $sIdx + 1 ?>.</td>
          <td style="padding:5px 8px;vertical-align:top;border-right:1px solid #cbd5e1;">
            <div style="font-weight:800;color:#0f172a;font-size:8.5pt;"><?= e($it['description']) ?></div>
            <?php if (!empty($it['specs'])): ?>
            <div style="font-size:7pt;color:#334155;margin-top:2px;line-height:1.3;">
              <?php foreach ($it['specs'] as $sp): ?>
                <div>&bull; <?= e($sp) ?></div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-weight:700;vertical-align:top;padding:5px;border-right:1px solid #cbd5e1;font-size:8.5pt;"><?= number_format($it['qty']) ?></td>
          <td style="text-align:right;font-weight:700;vertical-align:top;padding:5px 8px;border-right:1px solid #cbd5e1;font-size:8.5pt;"><?= fmtAmount($it['rate']) ?></td>
          <td style="text-align:right;font-weight:800;vertical-align:top;padding:5px 8px;font-size:8.5pt;"><?= fmtAmount($it['amount']) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Subtotal -->
        <tr style="background:#fafafa;border-top:1.5px solid #000;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
          <td colspan="4" style="text-align:right;font-weight:700;padding:4px 8px;font-size:8pt;border-right:1px solid #cbd5e1;">Total Amount</td>
          <td style="text-align:right;font-weight:800;padding:4px 8px;font-size:8pt;"><?= fmtAmount($b['subtotal']) ?></td>
        </tr>

        <!-- GST breakdown -->
        <?php if ($isGst && ($b['gst_amount'] ?? 0) > 0): ?>
        <tr style="background:#fafafa;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
          <td colspan="4" style="text-align:right;font-weight:700;padding:3px 8px;font-size:7.5pt;color:#2563eb;border-right:1px solid #cbd5e1;">
            GST <?= $b['gst_rate'] ?? 5 ?>% (<?= (($b['gst_rate'] ?? 5) / 2) ?>% SGST + <?= (($b['gst_rate'] ?? 5) / 2) ?>% CGST)
          </td>
          <td style="text-align:right;font-weight:800;padding:3px 8px;font-size:7.5pt;color:#2563eb;"><?= fmtAmount($b['gst_amount']) ?></td>
        </tr>
        <?php endif; ?>

        <!-- Block Grand Total -->
        <tr style="background:#fef08a;border-top:1.5px solid #000;color:#000;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
          <td colspan="4" style="text-align:right;font-weight:900;padding:5px 8px;font-size:9pt;letter-spacing:0.5px;border-right:1px solid #000;">GRAND TOTAL</td>
          <td style="text-align:right;font-weight:900;padding:5px 8px;font-size:9.5pt;">₹<?= fmtAmount($b['grand_total']) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>

  <?php if (!empty($doc['show_combined_total'])): ?>
  <!-- Project Summary & Combined Grand Total -->
  <div style="margin:10px 0 14px;page-break-inside:avoid;border:1.5px solid #16a34a;border-radius:4px;overflow:hidden;background:#f0fdf4;">
    <div style="background:#16a34a;color:#fff;padding:5px 8px;font-weight:900;font-size:8.5pt;letter-spacing:0.5px;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
      <i class="fa fa-calculator me-1"></i> COMBINED PROJECT ESTIMATION SUMMARY
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:7.5pt;">
      <thead>
        <tr style="background:#dcfce7;border-bottom:1px solid #86efac;font-weight:700;">
          <th style="padding:4px 8px;">Block / Section</th>
          <th style="padding:4px 8px;text-align:center;">Items</th>
          <th style="padding:4px 8px;text-align:right;">Subtotal</th>
          <th style="padding:4px 8px;text-align:right;">GST</th>
          <th style="padding:4px 8px;text-align:right;">Grand Total (₹)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($doc['blocks'] as $b): ?>
        <tr style="border-bottom:1px solid #bbf7d0;">
          <td style="padding:4px 8px;font-weight:800;color:#0f172a;"><?= e($b['title']) ?></td>
          <td style="padding:4px 8px;text-align:center;"><?= count($b['items']) ?></td>
          <td style="padding:4px 8px;text-align:right;"><?= fmtAmount($b['subtotal']) ?></td>
          <td style="padding:4px 8px;text-align:right;"><?= fmtAmount($b['gst_amount']) ?></td>
          <td style="padding:4px 8px;text-align:right;font-weight:800;">₹<?= fmtAmount($b['grand_total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#15803d;color:#fff;font-weight:900;font-size:8.5pt;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
          <td colspan="4" style="padding:5px 8px;text-align:right;">COMBINED PROJECT GRAND TOTAL:</td>
          <td style="padding:5px 8px;text-align:right;font-size:9pt;">₹<?= fmtAmount($doc['grand_total']) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  </div><!-- /doc-items-grow -->
  </div><!-- /doc-main-content -->

  <!-- Commercial Footer (Bank Details, Terms, Stamp & Signatory) -->
  <?= renderStandardCommercialFooter($doc, $company, $showRussea, $showBank, $bankName, $bankAcc, $bankIfsc, $bankBranch, $stampPath, $sigPath, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper -->
<?php else: ?>

<?php foreach ($quotationPages as $qPageIdx => $pageItems): ?>
<?php 
  $isFirstPage = ($qPageIdx === 0);
  $isLastQuotPage = ($qPageIdx === ($totalQuotPages - 1));
?>
<!-- ── QUOTATION PAGE <?= $qPageIdx + 1 ?> OF <?= $totalQuotPages ?> ──────────────────────────────────────── -->
<div class="doc-wrapper doc-page-quotation" style="<?= !$isFirstPage ? 'margin-top:0;' : '' ?>">
<div class="doc-sheet">

  <!-- Universal Header (Page <?= $qPageIdx + 1 ?>) -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <?php if ($isFirstPage): ?>
  <!-- E-Way Bill & Transport Logistics Box (If Present on Invoices / Bills) -->
  <?php 
    $ewData = $doc['eway_bill'] ?? [];
    $hasEway = !empty($ewData['enabled']) || !empty($ewData['eway_bill_no']) || !empty($ewData['vehicle_no']);
  ?>
  <?php if ($hasEway): ?>
  <div style="margin:4px 0 6px;padding:6px 12px;background:#f0fdf4;border:1.5px solid #22c55e;border-radius:6px;font-size:7.5pt;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <span style="background:#166534;color:#fff;font-weight:900;padding:2px 8px;border-radius:4px;letter-spacing:0.5px;font-size:7pt;">
        <i class="fa fa-truck me-1"></i>GST E-WAY BILL
      </span>
      <?php if (!empty($ewData['eway_bill_no'])): ?>
      <span><strong>E-Way Bill No:</strong> <span style="font-family:monospace;font-weight:800;color:#0f172a;font-size:8.5pt;"><?= e(chunk_split($ewData['eway_bill_no'], 4, ' ')) ?></span></span>
      <?php endif; ?>
      <?php if (!empty($ewData['eway_bill_date'])): ?>
      <span><strong>Generated:</strong> <?= e(date('d-M-Y', strtotime($ewData['eway_bill_date']))) ?></span>
      <?php endif; ?>
      <?php if (!empty($ewData['valid_upto'])): ?>
      <span><strong>Valid Upto:</strong> <?= e(date('d-M-Y', strtotime($ewData['valid_upto']))) ?></span>
      <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <?php if (!empty($ewData['vehicle_no'])): ?>
      <span style="background:#fef3c7;color:#b45309;padding:2px 6px;border-radius:4px;border:1px solid #fcd34d;font-weight:800;">
        <i class="fa fa-car me-1"></i>Vehicle: <?= e($ewData['vehicle_no']) ?>
      </span>
      <?php endif; ?>
      <?php if (!empty($ewData['transporter_name'])): ?>
      <span><strong>Transporter:</strong> <?= e($ewData['transporter_name']) ?></span>
      <?php endif; ?>
      <?php if (!empty($ewData['transporter_doc_no'])): ?>
      <span><strong>LR/Doc:</strong> <?= e($ewData['transporter_doc_no']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Parties + GSTIN + Shipping Details (Page 1) -->
  <?php 
    $shipData = $doc['ship_to'] ?? [];
    // Smart customer master fallback if shipping address is empty
    if (empty($shipData['address']) && !empty($doc['bill_to']['name'])) {
        $allCust = getAllCustomers();
        foreach ($allCust as $c) {
            if (strcasecmp(trim($c['name'] ?? ''), trim($doc['bill_to']['name'])) === 0) {
                if (!empty($c['shipping_address'])) {
                    $shipData['address'] = $c['shipping_address'];
                    $shipData['name']    = $shipData['name'] ?: ($c['shipping_name'] ?? $c['name']);
                    $shipData['phone']   = $shipData['phone'] ?: ($c['shipping_phone'] ?? $c['phone'] ?? '');
                    $shipData['state']   = $shipData['state'] ?: ($c['shipping_state'] ?? $c['state'] ?? '');
                    $shipData['show_on_doc'] = 1;
                }
                break;
            }
        }
    }
    $hasDistinctShip = !empty($shipData['show_on_doc']) && (!empty($shipData['address']) || !empty($shipData['name']));
  ?>
  <table class="doc-parties-tbl">
    <colgroup>
      <?php if ($hasDistinctShip): ?>
      <col style="width:28%">
      <col style="width:28%">
      <col style="width:24%">
      <col style="width:20%">
      <?php else: ?>
      <col style="width:33.33%">
      <col style="width:33.33%">
      <col style="width:33.34%">
      <?php endif; ?>
    </colgroup>
    <tbody>
      <!-- Row 1: Bill To | Ship To (if distinct) | Supplier | Badge + Doc No/Date -->
      <?php
        $custType = $doc['customer_type'] ?? 'client';
        if (empty($doc['bill_to']['name']) && !empty($doc['customer_id'])) {
            $cObj = getCustomer($doc['customer_id']);
            if ($cObj) {
                $doc['bill_to']['name']     = $cObj['name'] ?? '';
                $doc['bill_to']['address']  = $cObj['address'] ?? '';
                $doc['bill_to']['phone']    = $cObj['phone'] ?? '';
                $doc['bill_to']['whatsapp'] = $cObj['whatsapp'] ?? ($cObj['phone'] ?? '');
                $doc['bill_to']['state']    = $cObj['state'] ?? '';
                $doc['bill_to']['gstin']    = $cObj['gstin'] ?? '';
            }
        }
      ?>
      <tr>
                <td class="party-bill">
          <div class="party-label"><?= $custType === 'dealer' ? 'Dealer / Buyer' : 'Bill To (Buyer)' ?></div>
          <div class="party-name"><?= e($doc['bill_to']['name'] ?? '') ?></div>
          <div class="party-address">
            <?= nl2br(e($doc['bill_to']['address'] ?? '')) ?>
            <?php
              $bState   = trim($doc['bill_to']['state'] ?? '');
              $bCountry = trim($doc['bill_to']['country'] ?? 'India');
              if ($bState !== '' || $bCountry !== ''):
            ?>
              <div style="font-size:7pt;color:#0f172a;font-weight:700;margin-top:2px;">
                <?= e($bState) ?><?= ($bState && $bCountry) ? ', ' : '' ?><span style="color:var(--navy);"><?= e($bCountry) ?></span>
              </div>
            <?php endif; ?>
          </div>
          <?php
            $custPhone    = trim($doc['bill_to']['phone'] ?? '');
            $custWhatsapp = trim($doc['bill_to']['whatsapp'] ?? '');
          ?>
          <?php if ($custPhone !== '' || $custWhatsapp !== ''): ?>
          <div style="margin-top:3px;margin-bottom:2px;font-size:7pt;line-height:1.3;">
            <?php if ($custPhone !== '' && $custWhatsapp !== '' && $custPhone === $custWhatsapp): ?>
              <div>
                <span style="color:var(--navy);font-weight:700;"><i class="fa fa-phone text-primary" style="font-size:6.5pt;"></i> / <i class="fab fa-whatsapp text-success" style="font-size:7pt;"></i> Ph:</span>
                <span style="font-weight:700;color:#0f172a;"><?= (strpos($custPhone, '+') === 0 ? '' : ($countryList[$custCountry]['phone_prefix'] ?? '+91') . ' ') ?><?= e($custPhone) ?></span>
              </div>
            <?php else: ?>
              <?php if ($custPhone !== ''): ?>
                <div><span style="color:var(--navy);font-weight:700;">Mobile:</span> <span style="font-weight:700;"><?= (strpos($custPhone, '+') === 0 ? '' : ($countryList[$custCountry]['phone_prefix'] ?? '+91') . ' ') ?><?= e($custPhone) ?></span></div>
              <?php endif; ?>
              <?php if ($custWhatsapp !== ''): ?>
                <div><span style="color:var(--navy);font-weight:700;">WA:</span> <span style="font-weight:700;"><?= (strpos($custWhatsapp, '+') === 0 ? '' : ($countryList[$custCountry]['phone_prefix'] ?? '+91') . ' ') ?><?= e($custWhatsapp) ?></span></div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        </td>

        <?php if ($hasDistinctShip): ?>
        <td class="party-bill" style="background:#ffffff;border-left:2px solid var(--navy);">
          <div class="party-label" style="color:#166534;"><i class="fa fa-truck-ramp-box me-1"></i>Ship To (Delivery Site)</div>
          <div class="party-name" style="color:#15803d;"><?= e($shipData['name'] ?: ($doc['bill_to']['name'] ?? '')) ?></div>
          <div class="party-address"><?= nl2br(e($shipData['address'] ?: ($doc['bill_to']['address'] ?? ''))) ?></div>
          <?php if (!empty($shipData['phone'])): ?>
          <div style="margin-top:3px;font-size:7pt;color:#166534;font-weight:700;">
            <i class="fa fa-phone me-1"></i>Contact: <?= e($shipData['phone']) ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($shipData['state'])): ?>
          <div style="margin-top:2px;font-size:6.5pt;color:#475569;">
            <strong>Place of Supply / Destination:</strong> <?= e($shipData['state'] ?: ($doc['bill_to']['state'] ?? '')) ?><?= (!empty($shipData['country']) || !empty($doc['bill_to']['country'])) ? ', ' . e($shipData['country'] ?: ($doc['bill_to']['country'] ?? 'India')) : '' ?>
          </div>
          <?php endif; ?>
        </td>
        <?php endif; ?>

                <td class="party-supplier">
          <div class="party-label">Supplier</div>
          <div class="party-name"><?= e($company['name']) ?></div>
          <div class="party-address">
            <?= nl2br(e($company['address'])) ?>
            <?php 
              $sCompState   = trim($company['state'] ?? '');
              $sCompCountry = trim($company['country'] ?? 'India');
              $addrText     = $company['address'] . ' ' . $sCompState;
              if (stripos($addrText, 'India') === false):
            ?>
              <div style="font-size:7pt;color:#334155;font-weight:700;margin-top:2px;"><?= e($sCompState) ?><?= ($sCompState && $sCompCountry) ? ', ' : '' ?><?= e($sCompCountry) ?></div>
            <?php endif; ?>
          </div>
        </td>

        <td class="party-type">
          <?php
      $isCustDomestic = (($doc['bill_to']['country'] ?? 'India') === 'India');
      if ($isProforma) {
          $customDocBadge = 'PROFORMA INVOICE';
      } elseif ($isPoAck) {
          $customDocBadge = 'P.O. ACKNOWLEDGEMENT';
      } else {
          $customDocBadge = $typeLabel;
          if (!$isCustDomestic) {
              if (($doc['type'] ?? '') === 'tax_invoice') $customDocBadge = 'EXPORT INVOICE';
              elseif (($doc['type'] ?? '') === 'bill') $customDocBadge = 'COMMERCIAL BILL';
              else $customDocBadge = 'INTERNATIONAL ESTIMATION';
          }
      }
    ?>
    <div class="type-badge-doc" style="font-size:<?= ($isPoAck || $isProforma) ? '13pt' : '15pt' ?>;padding:<?= ($isPoAck || $isProforma) ? '8px 4px' : '10px 6px' ?>;<?= $isProforma ? 'background:#7e22ce !important;' : '' ?>">
      <?= $customDocBadge ?>
    </div>
          <?php if ($subtypeLabel && !$isPoAck): ?>
          <div style="background:#1a3a7c;color:#fff;font-size:7.5pt;font-weight:800;text-align:center;padding:3px 6px;letter-spacing:1px;text-transform:uppercase;print-color-adjust:exact;-webkit-print-color-adjust:exact;border-bottom:1px solid #0d1b3e;">
            <?= e($subtypeLabel) ?>
          </div>
          <?php endif; ?>
          <div class="doc-meta-row">
            <div class="doc-meta-box" style="<?= $isPoAck ? 'background:#f0f9ff;border-bottom:1px solid #bae6fd;' : ($isProforma ? 'background:#faf5ff;border-bottom:1px solid #e9d5ff;' : '') ?>">
              <div class="doc-meta-label" style="<?= $isPoAck ? 'color:#0369a1;' : ($isProforma ? 'color:#7e22ce;' : '') ?>"><?= $isPoAck ? 'P.O. ACKNOWLEDGEMENT NO' : ($isProforma ? 'PROFORMA INVOICE NO' : ($typeLabel . ' NO')) ?></div>
              <div class="doc-meta-value" style="font-size:9.5pt;font-weight:900;color:<?= $isPoAck ? '#0284c7' : ($isProforma ? '#7e22ce' : 'var(--navy)') ?>;"><?= e($displayDocId) ?></div>
              <?php if (($isPoAck || $isProforma) && strpos($doc['id'], 'EST-') === 0): ?>
              <div style="font-size:6.5pt;color:#64748b;margin-top:2px;">Est Ref: <strong><?= e($doc['id']) ?></strong></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($doc['po_number'])): ?>
            <div class="doc-meta-box" style="border-top:1px dashed #cbd5e1;background:#fffbeb;">
              <div class="doc-meta-label" style="color:#b45309;">CUSTOMER P.O. REF</div>
              <div class="doc-meta-value" style="font-size:8.5pt;color:#b45309;font-weight:800;"><?= e($doc['po_number']) ?></div>
              <?php if (!empty($doc['po_date'])): ?>
              <div style="font-size:6.5pt;color:#78350f;">Date: <?= e(date('d-M-Y', strtotime($doc['po_date']))) ?></div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </td>
      </tr>

      <!-- Row 2: GSTIN (left+middle) + DATE (right) -->
      <tr class="gstin-row">
        <td><strong><?= (($doc['bill_to']['country'] ?? 'India') === 'India') ? 'GSTIN' : 'TAX ID / TRN' ?> : <?= ($doc['bill_to']['gstin'] ?? '') ?: 'NA' ?></strong></td>
        <?php if ($hasDistinctShip): ?>
        <td style="background:#f0fdf4;color:#166534;font-size:7pt;"><strong>STATE : <?= e($shipData['state'] ?: ($doc['bill_to']['state'] ?: 'NA')) ?></strong></td>
        <?php endif; ?>
        <td><strong>GSTIN : <?= e($company['gstin']) ?></strong></td>
        <td style="text-align:center;"><strong>DATE : <?= e(date('d-M-Y', strtotime($doc['date'] ?? 'today'))) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <?php if ($isPoAck): ?>
  <!-- Official P.O. Acceptance Statement Box -->
  <div style="background:#f0f9ff;border:1.5px solid #0284c7;border-radius:6px;padding:6px 12px;margin:5px 0 4px 0;font-size:7.5pt;color:#0369a1;line-height:1.4;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
    <div style="font-weight:800;font-size:8pt;color:#0284c7;margin-bottom:2px;display:flex;align-items:center;justify-content:space-between;">
      <span><i class="fa fa-stamp me-1"></i> PURCHASE ORDER ACCEPTANCE &amp; ACKNOWLEDGEMENT</span>
      <?php if (!empty($doc['delivery_timeline'])): ?>
      <span style="background:#0284c7;color:#fff;padding:1px 6px;border-radius:4px;font-size:6.5pt;font-weight:700;">Delivery: <?= e($doc['delivery_timeline']) ?></span>
      <?php endif; ?>
    </div>
    We gratefully acknowledge receipt of your Purchase Order Ref: <strong><?= e(($doc['po_number'] ?? '') ?: 'As Provided') ?></strong><?= !empty($doc['po_date']) ? ' dated <strong>'.e(date('d-M-Y', strtotime($doc['po_date']))).'</strong>' : '' ?>. We hereby confirm formal acceptance of all ordered materials, technical specifications, agreed rates, and commercial terms stated below for manufacturing and execution.
  </div>
  <?php endif; ?>

  <?php else: ?>
  <!-- Page Meta Continuation Strip (Pages 2+) -->
  <table style="width:100%;border-collapse:collapse;border-top:1.5px solid var(--navy);border-bottom:1.5px solid var(--navy);background:#ffffff;margin-bottom:4px;">
    <colgroup><col style="width:40%"><col style="width:30%"><col style="width:30%"></colgroup>
    <tbody>
    <tr>
      <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);">
        <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Document Ref.</span>
        <?= e($displayDocId) ?> &bull; <?= $typeLabel ?><?= $totalQuotPages > 1 ? ' (Page '.($qPageIdx + 1).' of '.$totalQuotPages.')' : '' ?>
      </td>
      <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
        <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Customer</span>
        <?= e($doc['bill_to']['name'] ?? 'NA') ?>
      </td>
      <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
        <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Date</span>
        <?= e(date('d-M-Y', strtotime($doc['date'] ?? 'today'))) ?>
      </td>
    </tr>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Items Table (Page <?= $qPageIdx + 1 ?>) -->
  <div class="doc-main-content">
  <div class="doc-items-grow">
  <div class="items-section">
    <table class="items-tbl">
      <thead>
        <tr>
          <th class="col-desc">Description<?= !$isFirstPage ? ' (Continued)' : '' ?></th>
          <th class="col-hsn">HSN</th>
          <th class="col-qty">Qty</th>
          <th class="col-unit">Units</th>
          <th class="col-rate">Rate</th>
          <th class="col-amount">Amount</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pageItems as $item): ?>
        <?= renderDocItemRow($item, $saleMode, $saleLabel, $isGst) ?>
      <?php endforeach; ?>

      <?php if (!$isLastQuotPage): ?>
      <tr style="background:#f0f7ff;font-weight:700;">
        <td colspan="6" style="text-align:right;padding:4px 10px;font-size:7.5pt;color:#1d4ed8;border-top:1.5px solid #93c5fd;">
          <i class="fa fa-arrow-right me-1"></i>Continued on Page <?= $qPageIdx + 2 ?> &mdash; Remaining Items &amp; Commercial GST Breakdown
        </td>
      </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($isLastQuotPage): ?>
  <!-- Commercial Totals Section on the Final Quotation Page -->
  <?= renderDocTotalsSection($doc, $isGst) ?>
  <?php endif; ?>

  </div><!-- /doc-items-grow -->
  </div><!-- /doc-main-content -->

  <?php if ($isLastQuotPage): ?>
  <!-- Final Page Commercial Footer (Russea Strip + Bank/Words/Terms/Stamp + Bottom Bar) -->
  <?= renderStandardCommercialFooter($doc, $company, $showRussea, $showBank, $bankName, $bankAcc, $bankIfsc, $bankBranch, $stampPath, $sigPath, $website) ?>
  <?php else: ?>
  <!-- Intermediate Page Standard Footer Bar -->
  <?= renderStandardDocFooter($company, $website) ?>
  <?php endif; ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Page <?= $qPageIdx + 1 ?> -->
<?php endforeach; ?>

<?php if (!empty($isTurnkey)): ?>
<!-- ── PAGE 3 — TURNKEY 3D COURT & ARCHITECTURAL FLOOR PLAN (SHEET 1 OF 2) ── -->
<div class="doc-wrapper turnkey-page turnkey-page-plans" style="margin-top:0;">
<div class="doc-sheet turnkey-plans-sheet">

  <!-- Universal Header (Page 3) - EXACT SAME SIZE & LOGO -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <div>
    <!-- Doc Meta Strip -->
    <table style="width:100%;border-collapse:collapse;border-top:1.5px solid var(--navy);border-bottom:1.5px solid var(--navy);background:#ffffff;margin-bottom:4px;">
      <colgroup><col style="width:34%"><col style="width:33%"><col style="width:33%"></colgroup>
      <tbody>
      <tr>
        <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);">
          <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Document Ref.</span>
          <?= e($doc['id']) ?> &bull; TURNKEY COURT (SHEET 1 OF 2)
        </td>
        <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
          <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Court Dimensions</span>
          <?= (int)$tkL ?> FT × <?= (int)$tkW ?> FT &bull; Area: <?= number_format($turnkeySpecs['base_area_sft']) ?> SFT
        </td>
        <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
          <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Sport &amp; Package</span>
          <?= ($tkSport === 'multisport_4in1_100x60') ? '4-in-1 Multi-Sport Ground (Cricket • Football • Volleyball • Badminton)' : (($tkSport === 'outdoor_multi_sports_120x60') ? 'Outdoor Multi Sports (Badminton + Basketball)' : ucwords(str_replace('_', ' ', $tkSport))) ?> &bull; <?= strtoupper(str_replace('_', ' ', $tkPkg)) ?>
        </td>
      </tr>
      </tbody>
    </table>

    <!-- Title Bar -->
    <div style="background:#1a3a7c;color:#fff;text-align:center;padding:4px 10px;margin-bottom:4px;print-color-adjust:exact;-webkit-print-color-adjust:exact;border-radius:4px;">
      <div style="font-size:9.5pt;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;">3D Interactive Configuration &amp; Architectural Floor Plan</div>
      <div style="font-size:6.8pt;opacity:.85;margin-top:1px;">Sheet 1 of 2 &bull; All Engineering Drawings Formatted in 16:9 Ratio &bull; 3D Model &bull; 2D Floor Plan</div>
    </div>
  </div>

  <!-- Main Diagrams Container (Sheet 1: 2 Views in 16:9 Ratio) -->
  <div style="display:flex;flex-direction:column;gap:8px;justify-content:flex-start;margin-top:4px;padding:0 8px;">

    <!-- VIEW 1: 3D Court Model Viewport (Strict 16:9 Ratio) -->
    <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:4px 6px;position:relative;">
      <div class="d-flex justify-content-between align-items-center px-1" style="margin-bottom:2px;">
        <div class="d-flex align-items-center gap-2">
          <span style="font-size:7.5pt;font-weight:800;color:#0f172a;letter-spacing:.3px;">
            <i class="fa fa-cube text-primary me-1"></i><?= $safeStructure ? '3D ARENA &amp; CANOPY MODEL (16:9 RATIO)' : '3D SPORTS TURF &amp; BASE WORK MODEL (16:9 RATIO)' ?>
          </span>
          <?php if ($safeStructure): ?>
          <button type="button" class="btn btn-xs btn-outline-primary no-print fw-bold px-2 py-0" style="font-size:6.5pt;border-radius:4px;" id="btnViewToggleRoof" onclick="toggleDocRoofView()">
            <i class="fa fa-layer-group me-1"></i><span id="docRoofText">Toggle: Hide Roof Sheets (Look Inside)</span>
          </button>
          <?php endif; ?>
        </div>
        <div class="no-print-3d-controls text-muted" style="font-size:6.5pt;">
          <i class="fa fa-hand-pointer text-info me-1"></i>Drag to rotate &bull; Scroll to zoom
        </div>
      </div>

      <!-- WebGL Interactive 3D Canvas -->
      <div id="turnkey3DContainer" style="width:100%;max-width:680px;height:320px;aspect-ratio:16/9;background:#f1f5f9;border-radius:4px;overflow:hidden;position:relative;margin:0 auto;border:1px solid #e2e8f0;"></div>
      <!-- Print/PDF Snapshot Image -->
      <img id="turnkey-3d-print-img" alt="3D Court Structure" style="display:none;width:100%;max-width:680px;height:320px;aspect-ratio:16/9;object-fit:contain;background:#f8fafc;border-radius:4px;margin:0 auto;border:1px solid #e2e8f0;">

      <!-- Engineering Metrics Strip -->
      <div style="margin-top:3px;display:flex;flex-wrap:wrap;gap:4px;justify-content:center;">
        <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;"><i class="fa fa-ruler-combined text-danger"></i><?= (int)$tkL ?>' × <?= (int)$tkW ?>'<?= $safeStructure ? (' &times; ' . (int)$tkH . ' FT High') : ' Base Area' ?></span>
        <?php if ($safeStructure): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;"><i class="fa fa-building-columns text-primary"></i><?= $turnkeySpecs['total_poles'] ?> Columns (<?= round($turnkeySpecs['length_spans']['bay_width'],1) ?>' c/c)</span>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;"><i class="fa fa-bars text-success"></i><?= $turnkeySpecs['tier_count'] ?> Horizontal Tiers (<?= number_format($turnkeySpecs['horizontal_pipe_rft']) ?> RFT)</span>
        <?php endif; ?>
        <?php if ($safeNetting): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;"><i class="fa fa-border-all text-warning"></i><?= number_format($turnkeySpecs['total_net_sft']) ?> SFT Russea™ Net</span>
        <?php endif; ?>
        <?php if ($safeLighting): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;"><i class="fa fa-lightbulb text-warning"></i><?= $turnkeySpecs['lights_count'][$tkPkg] ?? 8 ?> Stadium LEDs</span>
        <?php endif; ?>
        <?php if ($safeGates && $tkGateCount > 0): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;"><i class="fa fa-door-open text-danger"></i><?= $tkGateCount ?> No<?= $tkGateCount > 1 ? 's' : '' ?>. 4' × 7' Access Gate</span>
        <?php elseif ($safeStructure): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;"><i class="fa fa-person-running text-info"></i>Open Bowler Entry (0 Gates)</span>
        <?php endif; ?>
        <?php if (!$safeStructure && !$safeNetting): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;"><i class="fa fa-layer-group text-primary me-1"></i>Scope: Civil Base Slab &amp; Sports Grass Only</span>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"><i class="fa fa-ban text-danger me-1"></i>Metal Fabrication &amp; Netting Excluded</span>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;"><i class="fa fa-seedling text-success me-1"></i><?= (int)$turnkeySpecs['base_area_sft'] ?> SFT Sports Grass &amp; Pitch</span>
        <?php endif; ?>
        <?php 
          $eqConfig = $turnkeyConfig['equipment'] ?? [];
          $hasCricketEq = isset($eqConfig['cricket_pitch']) ? !empty($eqConfig['cricket_pitch']) : ($tkPitch > 0);
          $hasFootballEq = isset($eqConfig['football_goals']) ? !empty($eqConfig['football_goals']) : ($tkSport === 'multisport_4in1_100x60' || (($doc['id'] ?? '') === 'EST-202610-026'));
          $hasVolleyballEq = isset($eqConfig['volleyball_net']) ? !empty($eqConfig['volleyball_net']) : ($tkSport === 'multisport_4in1_100x60' || (($doc['id'] ?? '') === 'EST-202610-026'));
          $hasBadmintonEq = isset($eqConfig['badminton_net']) ? !empty($eqConfig['badminton_net']) : ($tkSport === 'multisport_4in1_100x60' || (($doc['id'] ?? '') === 'EST-202610-026'));
          $hasMarkingsEq = isset($eqConfig['court_markings']) ? !empty($eqConfig['court_markings']) : ($tkSport === 'multisport_4in1_100x60' || (($doc['id'] ?? '') === 'EST-202610-026'));
        ?>
        <?php if ($tkSport === 'multisport_4in1_100x60' || ($hasFootballEq && $hasVolleyballEq && $hasBadmintonEq)): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;"><i class="fa fa-trophy text-success me-1"></i>4-in-1 Multi-Sport Ground</span>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;"><i class="fa fa-seedling text-success me-1"></i>30mm Non-Infill Sports Grass</span>
        <?php endif; ?>
        <?php if ($hasCricketEq): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#fefce8;color:#854d0e;border:1px solid #fde047;"><i class="fa fa-cricket-bat-ball text-warning me-1"></i>Cricket Center Pitch</span>
        <?php endif; ?>
        <?php if ($hasFootballEq): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;"><i class="fa fa-futbol text-primary me-1"></i>Mini Football Goals (Pair)</span>
        <?php endif; ?>
        <?php if ($hasVolleyballEq): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#fefce8;color:#a16207;border:1px solid #facc15;"><i class="fa fa-volleyball text-warning me-1"></i>Volleyball Posts &amp; Net</span>
        <?php endif; ?>
        <?php if ($hasBadmintonEq): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#f0fdf4;color:#047857;border:1px solid #6ee7b7;"><i class="fa fa-table-tennis-paddle-ball text-emerald me-1"></i>Badminton Movable Net</span>
        <?php endif; ?>
        <?php if ($hasMarkingsEq): ?>
          <span class="turnkey-metric-badge" style="padding:1.5px 6px;font-size:6.2pt;background:#fdf2f8;color:#9d174d;border:1px solid #fbcfe8;"><i class="fa fa-paintbrush text-pink me-1"></i>Multi-Sport Line Markings</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- VIEW 2: 2D Floor Plan (Top View) - Strict 16:9 Ratio -->
    <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:6px;padding:4px 6px;display:flex;flex-direction:column;">
      <div style="font-size:7.5pt;font-weight:800;color:#0f172a;margin-bottom:2px;border-bottom:1px solid #e2e8f0;padding-bottom:2px;">
        <i class="fa fa-compass text-danger me-1"></i>2D PROPORTIONAL FLOOR PLAN — <?= (int)$tkL ?> FT × <?= (int)$tkW ?> FT (16:9 RATIO)
      </div>
      <div id="turnkeyFloorPlanSvg" style="width:100%;max-width:680px;height:320px;aspect-ratio:16/9;margin:0 auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid #e2e8f0;border-radius:4px;background:#ffffff;"></div>
    </div>

  </div><!-- /flex container -->

  <!-- Universal Footer (Page 3) - PINNED TO VERY BOTTOM -->
  <?= renderStandardDocFooter($company, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Page 3 -->

<!-- ── PAGE 4 — TURNKEY 2D ARCHITECTURAL ELEVATIONS (SHEET 2 OF 2) ────────── -->
<div class="doc-wrapper turnkey-page turnkey-page-plans" style="margin-top:0;">
<div class="doc-sheet turnkey-plans-sheet">

  <!-- Universal Header (Page 4) - EXACT SAME SIZE & LOGO -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <div>
    <!-- Doc Meta Strip -->
    <table style="width:100%;border-collapse:collapse;border-top:1.5px solid var(--navy);border-bottom:1.5px solid var(--navy);background:#ffffff;margin-bottom:4px;">
      <colgroup><col style="width:34%"><col style="width:33%"><col style="width:33%"></colgroup>
      <tbody>
      <tr>
        <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);">
          <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Document Ref.</span>
          <?= e($doc['id']) ?> &bull; TURNKEY COURT (SHEET 2 OF 2)
        </td>
        <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
          <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Court Dimensions</span>
          <?= (int)$tkL ?> FT × <?= (int)$tkW ?> FT &bull; Clear Height: <?= (int)$tkH ?> FT
        </td>
        <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
          <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Sport &amp; Package</span>
          <?= ($tkSport === 'multisport_4in1_100x60') ? '4-in-1 Multi-Sport Ground (Cricket • Football • Volleyball • Badminton)' : (($tkSport === 'outdoor_multi_sports_120x60') ? 'Outdoor Multi Sports (Badminton + Basketball)' : ucwords(str_replace('_', ' ', $tkSport))) ?> &bull; <?= strtoupper(str_replace('_', ' ', $tkPkg)) ?>
        </td>
      </tr>
      </tbody>
    </table>

    <!-- Title Bar -->
    <div style="background:#1a3a7c;color:#fff;text-align:center;padding:4px 10px;margin-bottom:4px;print-color-adjust:exact;-webkit-print-color-adjust:exact;border-radius:4px;">
      <div style="font-size:9.5pt;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;">2D Architectural Elevations &amp; Cross-Sections</div>
      <div style="font-size:6.8pt;opacity:.85;margin-top:1px;">Sheet 2 of 2 &bull; All Engineering Drawings Formatted in 16:9 Ratio &bull; Longitudinal Side Elevation &bull; Cross Side Elevation</div>
    </div>
  </div>

  <!-- Main Diagrams Container (Sheet 2: 2 Views in 16:9 Ratio) -->
  <div style="display:flex;flex-direction:column;gap:8px;justify-content:flex-start;margin-top:4px;padding:0 8px;">

    <!-- VIEW 3: 2D Longitudinal Side Elevation (Length Span) -->
    <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:6px;padding:4px 6px;display:flex;flex-direction:column;">
      <div style="font-size:7.5pt;font-weight:800;color:#0f172a;margin-bottom:2px;border-bottom:1px solid #e2e8f0;padding-bottom:2px;">
        <i class="fa fa-arrows-up-down text-primary me-1"></i>2D LONGITUDINAL SIDE ELEVATION — <?= (int)$tkL ?> FT LENGTH SPAN × <?= (int)$tkH ?> FT CLEAR HEIGHT (16:9 RATIO)
      </div>
      <div id="turnkeySideElevationSvg" style="width:100%;max-width:680px;height:320px;aspect-ratio:16/9;margin:0 auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid #e2e8f0;border-radius:4px;background:#ffffff;"></div>
    </div>

    <!-- VIEW 4: 2D Cross Side Elevation (Width Span) -->
    <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:6px;padding:4px 6px;display:flex;flex-direction:column;">
      <div style="font-size:7.5pt;font-weight:800;color:#0f172a;margin-bottom:2px;border-bottom:1px solid #e2e8f0;padding-bottom:2px;">
        <i class="fa fa-layer-group me-1" style="color:#ff0000;"></i>2D CROSS SIDE ELEVATION — <?= (int)$tkW ?> FT WIDTH SPAN × <?= (int)$tkH ?> FT CLEAR HEIGHT (16:9 RATIO)
      </div>
      <div id="turnkeyCrossElevationSvg" style="width:100%;max-width:680px;height:320px;aspect-ratio:16/9;margin:0 auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid #e2e8f0;border-radius:4px;background:#ffffff;"></div>
    </div>

  </div><!-- /flex container -->

  <!-- Universal Footer (Page 4) - PINNED TO VERY BOTTOM -->
  <?= renderStandardDocFooter($company, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Page 4 -->

<!-- ── PAGE 5 — PROJECT ENGINEERING & MATERIAL SPECIFICATION CHART ───────── -->
<div class="doc-wrapper turnkey-page" style="margin-top:0;">
<div class="doc-sheet">

  <!-- Header -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <!-- Page Title Bar -->
  <div style="background:#1a3a7c;color:#fff;text-align:center;padding:7px 14px;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
    <div style="font-size:11pt;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;">
      <?= (int)$tkL ?> × <?= (int)$tkW ?> FT TURNKEY COURT — ENGINEERING &amp; MATERIAL SPECIFICATION CHART
    </div>
    <div style="font-size:7pt;opacity:.85;margin-top:1px;letter-spacing:.5px;">
      <?= $safeStructure ? 'Clear Height: ' . (int)$tkH . ' FT &bull; ' . $turnkeySpecs['total_poles'] . ' Columns (' . htmlspecialchars($currentPoleProfile['short_name']) . ') &bull; ' : '' ?>Civil Bed: <?= htmlspecialchars($currentCivilBedProfile['short_name']) ?><?= $safeStructure ? ' &bull; ' . $turnkeySpecs['tier_count'] . ' Purlin Tiers' : '' ?> &bull; Surface: <?= $tkTurf === 'non_infill_turf' ? 'Non-Infill Multi-Sport Turf (Zero Infill)' : ($tkTurf === 'no_turf' ? 'Civil Hardcourt' : 'Infill Turf Surface') ?><?= !$safeStructure ? ' &bull; Scope: Base Work &amp; Sports Flooring Only' : '' ?>
    </div>
  </div>

  <!-- Engineering Spec Boxed Table (Columns & Rows) -->
  <div style="padding:8px 12px;flex:1;display:flex;flex-direction:column;justify-content:space-between;">
    <table style="width:100%;border-collapse:collapse;font-size:6.8pt;font-family:Arial,sans-serif;border:1.5px solid #0f172a;margin-bottom:6px;">
      <thead>
        <tr style="background:#0f172a;color:#fff;text-transform:uppercase;letter-spacing:.3px;">
          <th style="padding:4px 5px;text-align:center;width:3.5%;border:1px solid #334155;">#</th>
          <th style="padding:4px 6px;text-align:left;width:15%;border:1px solid #334155;">Component / Stage</th>
          <th style="padding:4px 6px;text-align:left;width:26%;border:1px solid #334155;">Material &amp; Structural Specification</th>
          <th style="padding:4px 6px;text-align:left;width:14%;border:1px solid #334155;">Metal / Material Gauge</th>
          <th style="padding:4px 6px;text-align:left;width:18%;border:1px solid #334155;">Gaps &amp; Spacing Intervals</th>
          <th style="padding:4px 6px;text-align:left;width:13.5%;border:1px solid #334155;">Quantity &amp; Area</th>
          <th style="padding:4px 5px;text-align:center;width:10%;border:1px solid #334155;">Standard / Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($turnkeySpecRows as $sIdx => $row): 
          $isEven = ($sIdx % 2 === 1);
          $rowBg = $isEven ? '#f8fafc' : '#ffffff';
          $isHighlighted = str_contains($row['component'], 'Column') || str_contains($row['component'], 'Netting') || str_contains($row['component'], 'Turf');
        ?>
        <tr style="background:<?= $rowBg ?>;border-bottom:1px solid #cbd5e1;">
          <td style="padding:3px 5px;text-align:center;font-weight:700;border:1px solid #cbd5e1;color:#475569;"><?= $row['sl'] ?></td>
          <td style="padding:3px 6px;font-weight:800;border:1px solid #cbd5e1;color:#0f172a;"><?= e($row['component']) ?></td>
          <td style="padding:3px 6px;border:1px solid #cbd5e1;color:#1e293b;line-height:1.2;"><?= e($row['spec']) ?></td>
          <td style="padding:3px 6px;border:1px solid #cbd5e1;font-weight:700;color:<?= $isHighlighted ? '#0369a1' : '#334155' ?>;line-height:1.2;"><?= e($row['gauge']) ?></td>
          <td style="padding:3px 6px;border:1px solid #cbd5e1;color:#334155;line-height:1.2;"><?= e($row['gaps']) ?></td>
          <td style="padding:3px 6px;border:1px solid #cbd5e1;font-weight:700;color:#0f172a;line-height:1.2;"><?= e($row['qty']) ?></td>
          <td style="padding:3px 5px;border:1px solid #cbd5e1;text-align:center;font-size:6.2pt;color:#475569;line-height:1.15;"><?= e($row['standard']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Quality Assurance & Compliance Strip -->
    <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:5px;padding:6px 12px;display:flex;align-items:center;justify-content:space-between;font-size:7.2pt;color:#14532d;margin-top:4px;line-height:1.35;">
      <div style="flex:1;">
        <i class="fa fa-shield-halved text-success me-1"></i>
        <strong>Structural &amp; Civil Engineering Compliance:</strong> Fabricated in strict accordance with IS 4923 &amp; IS 1239 structural steel codes &bull; IS 456 &amp; IS 1786 RCC foundation &amp; base slab standards &bull; Wind-load certified up to 130+ km/h with heavy-duty structural anchor system.
      </div>
      <div style="font-weight:800;color:#15803d;white-space:nowrap;flex-shrink:0;margin-left:14px;background:#dcfce7;border:1px solid #86efac;padding:3px 8px;border-radius:4px;font-size:6.8pt;">
        <i class="fa fa-certificate me-1"></i>Russea™ Genuine Certified Nets
      </div>
    </div>
  </div>

  <!-- Page Footer -->
  <?= renderStandardDocFooter($company, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Page 5 -->

<!-- ── PAGE 5 — 3-PACKAGE TURNKEY COMPARISON MATRIX (SHEET 3 OF TURNKEY) ── -->
<div class="doc-wrapper turnkey-page turnkey-page-break" style="margin-top:0;">
<div class="doc-sheet turnkey-matrix-sheet">

  <!-- Header -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <!-- Page Title Bar -->
  <div style="background:#1a3a7c;color:#fff;text-align:center;padding:4px 10px;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
    <div style="font-size:9.5pt;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;">
      <?= (int)$tkL ?> × <?= (int)$tkW ?> FT TURNKEY — 3-PACKAGE COMPARISON MATRIX
    </div>
    <div style="font-size:6.8pt;opacity:.85;margin-top:1px;letter-spacing:.3px;">
      Dynamic Pricing Scaled to Clear Height (<?= (int)$tkH ?> FT), <?= $turnkeySpecs['tier_count'] ?> Steel Tiers &amp; Custom Scope
    </div>
  </div>

  <!-- 3-Package Matrix Table & Commercial Terms -->
  <div style="padding:4px 10px;flex:1;display:flex;flex-direction:column;justify-content:space-between;">
    <table class="turnkey-matrix-tbl">
      <thead>
        <tr>
          <th style="background:#0f172a;color:#fff;text-align:left;width:22%;border:1px solid #1e293b;">Specification</th>
          <th style="background:#065f46;color:#fff;text-align:center;width:26%;border:1px solid #047857;<?= $tkPkg==='essential'?'border:2.5px solid #10b981;background:#047857;':'' ?>">
            <div style="font-size:7.2pt;font-weight:900;">🟢 ESSENTIAL</div>
            <div style="font-size:6.8pt;margin-top:1px;font-weight:800;"><?= $currSym ?><?= number_format($turnkeyAllPackages['essential']['rate_excl_gst'] ?? $turnkeyAllPackages['essential']['rate'], 2) ?>/SFT <span style="font-size:5.8pt;opacity:.85;">(Excl. GST)</span></div>
            <div style="font-size:6.2pt;color:#a7f3d0;font-weight:700;"><?= $currSym ?><?= number_format($turnkeyAllPackages['essential']['rate_incl_gst'], 2) ?>/SFT (Incl. GST)</div>
            <?php if ($tkPkg==='essential'): ?><div style="font-size:5.8pt;color:#fff;background:#059669;padding:1px 4px;border-radius:3px;margin-top:1px;letter-spacing:.5px;font-weight:900;">★ CURRENT QUOTATION</div><?php endif; ?>
          </th>
          <th style="background:#1e40af;color:#fff;text-align:center;width:26%;border:1px solid #1d4ed8;<?= $tkPkg==='commercial'?'border:2.5px solid #38bdf8;background:#1d4ed8;':'' ?>">
            <div style="font-size:7.2pt;font-weight:900;">🔵 COMMERCIAL</div>
            <div style="font-size:7.2pt;margin-top:1px;font-weight:900;color:#fef08a;"><?= $currSym ?><?= number_format($turnkeyAllPackages['commercial']['rate_excl_gst'] ?? $turnkeyAllPackages['commercial']['rate'], 2) ?>/SFT <span style="font-size:5.8pt;opacity:.9;">(Excl. GST)</span></div>
            <div style="font-size:6.5pt;color:#93c5fd;font-weight:800;"><?= $currSym ?><?= number_format($turnkeyAllPackages['commercial']['rate_incl_gst'], 2) ?>/SFT (Incl. GST)</div>
            <div style="font-size:5.8pt;color:#1e3a8a;background:#93c5fd;padding:1px 4px;border-radius:3px;margin-top:1px;letter-spacing:.5px;font-weight:900;"><?= $tkPkg==='commercial'?'★ CURRENT QUOTED &bull; RECOMMENDED':'★ RECOMMENDED STANDARD' ?></div>
          </th>
          <th style="background:#991b1b;color:#fff;text-align:center;width:26%;border:1px solid #b91c1c;<?= $tkPkg==='premium_pro'?'border:2.5px solid #f87171;background:#b91c1c;':'' ?>">
            <div style="font-size:7.2pt;font-weight:900;">🔴 PREMIUM PRO</div>
            <div style="font-size:6.8pt;margin-top:1px;font-weight:800;"><?= $currSym ?><?= number_format($turnkeyAllPackages['premium_pro']['rate_excl_gst'] ?? $turnkeyAllPackages['premium_pro']['rate'], 2) ?>/SFT <span style="font-size:5.8pt;opacity:.85;">(Excl. GST)</span></div>
            <div style="font-size:6.2pt;color:#fecaca;font-weight:700;"><?= $currSym ?><?= number_format($turnkeyAllPackages['premium_pro']['rate_incl_gst'], 2) ?>/SFT (Incl. GST)</div>
            <?php if ($tkPkg==='premium_pro'): ?><div style="font-size:5.8pt;color:#fff;background:#dc2626;padding:1px 4px;border-radius:3px;margin-top:1px;letter-spacing:.5px;font-weight:900;">★ CURRENT QUOTATION</div><?php endif; ?>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">Playing Court Area</td>
          <td style="text-align:center;"><?= (int)$tkL ?> × <?= (int)$tkW ?> ft (<?= number_format($turnkeySpecs['base_area_sft']) ?> SFT)</td>
          <td style="text-align:center;font-weight:700;"><?= (int)$tkL ?> × <?= (int)$tkW ?> ft (<?= number_format($turnkeySpecs['base_area_sft']) ?> SFT)</td>
          <td style="text-align:center;"><?= (int)$tkL ?> × <?= (int)$tkW ?> ft (<?= number_format($turnkeySpecs['base_area_sft']) ?> SFT)</td>
        </tr>
        <?php
          $isTerraceCivil = in_array($tkCivilBed, ['terrace_base_plates', 'rooftop_base_plates']) || str_starts_with($tkSport, 'terrace');
          $isClientCivil = ($tkCivilBed === 'client_scope');
          $is3inchPcc = str_starts_with($tkCivilBed, 'pcc_m15_3inch') || $tkCivilBed === 'concrete_pcc_3inch';
          $is4inchPcc = str_starts_with($tkCivilBed, 'pcc_m15_4inch');
          $is5or6inchPcc = str_starts_with($tkCivilBed, 'pcc_m15_5inch') || str_starts_with($tkCivilBed, 'pcc_m15_6inch');
          $isRcc = str_starts_with($tkCivilBed, 'rcc_m20') || str_starts_with($tkCivilBed, 'rcc_m25') || $tkCivilBed === 'concrete_rcc_4inch';
          $isAsphalt = ($tkCivilBed === 'asphalt_bitumen');
          $isEarth = ($tkCivilBed === 'laser_graded_earth');
        ?>
        <tr>
          <td style="font-weight:700;">
            <?= $isTerraceCivil ? 'Column Base &amp; Roof Slab Mounting' : 'Civil Base Construction' ?>
          </td>
          <td>
            <?php if ($isTerraceCivil): ?>
              <strong>6–8mm Base Plates:</strong> Structural MS plates + M12 mechanical anchors &amp; bitumen seal pads.
            <?php elseif ($isClientCivil): ?>
              <strong>Client Scope:</strong> Civil bed prepared &amp; executed directly by client (₹0 in GCM scope).
            <?php elseif ($is3inchPcc): ?>
              <strong>3" PCC M15 (Broom Finish):</strong> 75mm M15 plain cement concrete slab with broom texture.
            <?php elseif ($is4inchPcc): ?>
              <strong>4" PCC M15 (Broom Finish):</strong> 100mm M15 plain cement concrete slab with broom texture.
            <?php elseif ($is5or6inchPcc): ?>
              <strong>4" PCC M15:</strong> 100mm plain cement concrete slab.
            <?php elseif ($isRcc): ?>
              <strong>4" RCC M20 + Mesh:</strong> 100mm M20 concrete slab with 6mm welded steel mesh.
            <?php elseif ($isAsphalt): ?>
              <strong>50mm Asphalt:</strong> 50mm asphalt binder course over stone aggregate.
            <?php elseif ($isEarth): ?>
              <strong>Stabilized Earth:</strong> Graded natural earth with lime / stone dust leveling.
            <?php else: ?>
              <strong>3-Inch Base:</strong> Subgrade compaction, 60mm aggregate bed, stone dust leveling.
            <?php endif; ?>
          </td>
          <td style="background:#f0f7ff;">
            <?php if ($isTerraceCivil): ?>
              <strong>8–10mm Flange Plates:</strong> Structural MS/GI plates + M12/M16 chemical anchors &amp; bitumen pads.
            <?php elseif ($isClientCivil): ?>
              <strong>Client Scope:</strong> Civil bed by client as per GCM benchmark levels (₹0 in GCM scope).
            <?php elseif ($is3inchPcc): ?>
              <strong>3" PCC M15 (Smooth Troweled):</strong> 75mm M15 concrete pad with power float smooth finish.
            <?php elseif ($is4inchPcc): ?>
              <strong>4" PCC M15 (Laser Screeded):</strong> 100mm M15 concrete pad with laser leveling &amp; smooth finish.
            <?php elseif ($is5or6inchPcc): ?>
              <strong>5" Heavy PCC M15:</strong> 125mm heavy vacuum dewatered Tremix concrete slab.
            <?php elseif ($isRcc): ?>
              <strong>5" RCC M20 + TMT:</strong> 125mm M20 slab + 8mm TMT rebar grid @ 150mm c/c &amp; power float finish.
            <?php elseif ($isAsphalt): ?>
              <strong>Dual-Layer Asphalt (DBM+BC):</strong> 60mm DBM + 40mm Bituminous Concrete laser leveled.
            <?php elseif ($isEarth): ?>
              <strong>Laser-Graded Earth:</strong> Laser leveled compacted ground with drainage gradient.
            <?php else: ?>
              <strong>4-Inch Engineered:</strong> Heavy roller compaction, 75mm WMM, 25mm stone dust + 0.5% slope.
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isTerraceCivil): ?>
              <strong>10–12mm Gusseted Plates:</strong> Hot-dip GI plates + M16 Hilti anchors &amp; triangular gusset stiffeners.
            <?php elseif ($isClientCivil): ?>
              <strong>Client Scope:</strong> Civil bed by client with on-site GCM engineering supervision (₹0 in GCM scope).
            <?php elseif ($is3inchPcc): ?>
              <strong>3" PCC M15 (Vacuum Tremix):</strong> 75mm vacuum dewatered dense M15 slab + contraction joints.
            <?php elseif ($is4inchPcc): ?>
              <strong>4" PCC M15 (Tremix / Hardener):</strong> 100mm Tremix slab + floor hardener &amp; PU sealed expansion joints.
            <?php elseif ($is5or6inchPcc): ?>
              <strong>6" Extra-Heavy PCC M15:</strong> 150mm heavy Tremix concrete slab for multi-court heavy load arenas.
            <?php elseif ($isRcc): ?>
              <strong>6" RCC M25 Dual TMT:</strong> 150mm M25 heavy Tremix slab with dual TMT rebar cage &amp; PU joint sealant.
            <?php elseif ($isAsphalt): ?>
              <strong>Heavy-Duty Polymer Asphalt:</strong> Polymer-modified asphalt wear layer for ITF tournament courts.
            <?php elseif ($isEarth): ?>
              <strong>Heavy Stabilized Earth:</strong> Heavy roller compaction with geotextile sub-layer.
            <?php else: ?>
              <strong>6-Inch Heavy Duty:</strong> Geotextile membrane, 125mm compacted WMM / GSB base &amp; stone dust.
            <?php endif; ?>
          </td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">
            <?= $isTerraceCivil ? 'Roof Drainage &amp; Water Runoff' : 'Perimeter Drainage' ?>
          </td>
          <td>
            <?php if ($isTerraceCivil): ?>
              Direct natural slope runoff to existing terrace rain water drain outlets.
            <?php elseif ($isClientCivil): ?>
              Client scope drainage.
            <?php else: ?>
              Basic natural gravel perimeter trench.
            <?php endif; ?>
          </td>
          <td style="background:#f0f7ff;">
            <?php if ($isTerraceCivil): ?>
              <strong>Unobstructed Roof Flow:</strong> Base clearance allowing free rainwater flow to collection spouts.
            <?php elseif ($isClientCivil): ?>
              Client scope drainage as per GCM benchmark layout.
            <?php else: ?>
              Perforated PVC sub-surface drain pipe + aggregate &amp; catch pits.
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isTerraceCivil): ?>
              <strong>Engineered Roof Flow:</strong> Perimeter runoff path with mesh leaf guards to main storm downpipes.
            <?php elseif ($isClientCivil): ?>
              Client scope drainage with GCM technical gradient supervision.
            <?php else: ?>
              Full French drain grid + concrete storm water channel &amp; GI grating.
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td style="font-weight:700;">Structural Steel Columns</td>
          <td>
            <strong><?= $currentPoleProfile['pole_type'] === 'ladder_4line' ? '4-Line Box Lattice (32×32mm, 1.5mm)' : ($currentPoleProfile['pole_type'] === 'ladder_2line' ? '2-Line Ladder (32×32mm, 1.5mm)' : '3" × 3" MS Square (14G / 2.0mm)') ?></strong>, ~15 FT bays.
          </td>
          <td style="background:#f0f7ff;">
            <strong><?= htmlspecialchars($currentPoleProfile['full_name']) ?></strong>
          </td>
          <td>
            <strong><?= $currentPoleProfile['pole_type'] === 'ladder_4line' ? '4-Line Heavy Box Tower (50×50mm, 2.0mm)' : ($currentPoleProfile['pole_type'] === 'ladder_2line' ? '2-Line Heavy Ladder (50×50mm, 2.0mm)' : '5" × 5" Super Heavy (10G / 3.0mm)') ?></strong>.
          </td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">Poles &amp; Bay Gaps</td>
          <td><?= $turnkeySpecs['total_poles'] ?> Columns @ ~<?= round($turnkeySpecs['length_spans']['bay_width'],1) ?>' c/c length, ~<?= round($turnkeySpecs['width_spans']['bay_width'],1) ?>' c/c width</td>
          <td style="background:#f0f7ff;"><?= $turnkeySpecs['total_poles'] ?> Columns @ ~<?= round($turnkeySpecs['length_spans']['bay_width'],1) ?>' c/c length, ~<?= round($turnkeySpecs['width_spans']['bay_width'],1) ?>' c/c width</td>
          <td><?= $turnkeySpecs['total_poles'] ?> Columns @ ~<?= round($turnkeySpecs['length_spans']['bay_width'],1) ?>' c/c length, ~<?= round($turnkeySpecs['width_spans']['bay_width'],1) ?>' c/c width</td>
        </tr>
        <tr>
          <td style="font-weight:700;">Horizontal Tie Framework</td>
          <td>2" × 1.5" MS pipes (<?= min(4, $turnkeySpecs['tier_count']) ?> tiers, 14 Gauge).</td>
          <td style="background:#f0f7ff;">2" × 2" MS/GI pipes (<?= $turnkeySpecs['tier_count'] ?> tiers @ ~<?= $turnkeySpecs['tier_spacing_ft'] ?>' gaps, 14 Gauge).</td>
          <td>Heavy-gauge MS/GI purlins (<?= $turnkeySpecs['tier_count'] ?> tiers @ ~<?= $turnkeySpecs['tier_spacing_ft'] ?>' gaps) + wire rope grid.</td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">Side &amp; Back Netting</td>
          <td>N1 Standard HDPE 2.5mm twisted net (45mm mesh).</td>
          <td style="background:#f0f7ff;">N2 Commercial UV HDPE Braided 2.5mm (40mm square mesh, 120+ kgf).</td>
          <td>N3 Russea™ High-Tenacity 3.0mm Braided HDPE (40mm mesh, 150+ kgf).</td>
        </tr>
        <tr>
          <td style="font-weight:700;">Overhead Top Enclosure</td>
          <td>
            <?= ($tkRoof === 'open_top') ? 'Open Top (No roof net)' : (($tkRoof === 'light_cable_top') ? 'Lightweight 1.5mm / 24-Ply HDPE Net (40mm mesh)' : (($tkRoof === 'pipe_top') ? '2.0mm HDPE net on cross tie pipes' : 'Structural roof trusses with HDPE net')) ?>
          </td>
          <td style="background:#f0f7ff;">
            <?= ($tkRoof === 'open_top') ? '<strong>Open Top:</strong> No overhead roof' : (($tkRoof === 'light_cable_top') ? '<strong>Lightweight 1.5mm / 24-Ply Braided Net (35mm/40mm Mesh):</strong> Cable suspended (Zero rigid steel)' : (($tkRoof === 'pipe_top') ? '<strong>2.5mm Braided Net:</strong> Resting on horizontal cross tie pipes' : '<strong>Heavy 2.5mm/3.0mm Braided Net:</strong> Mounted over structural triangular trusses')) ?>
          </td>
          <td>
            <?= ($tkRoof === 'open_top') ? 'Open Top (Side & back nets only)' : (($tkRoof === 'light_cable_top') ? 'Russea™ 1.5mm/24-Ply braided net with stainless steel tensioners' : (($tkRoof === 'pipe_top') ? 'Russea™ 3.0mm braided net on heavy horizontal cross pipes' : 'Russea™ 3.0mm heavy-duty net on engineered trusses')) ?>
          </td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">Artificial Sports Turf</td>
          <td>
            <?= ($tkTurf === 'non_infill_turf') ? '25mm Non-Infill Curled Thatch (Zero Infill)' : (($tkTurf === 'no_turf') ? 'No Turf (Hardcourt)' : '30mm/40mm mono-filament turf') ?>
          </td>
          <td style="background:#f0f7ff;">
            <?= ($tkTurf === 'non_infill_turf') ? '<strong>25mm/30mm Non-Infill Multi-Sport Turf:</strong> High-density curled thatch (Zero Infill)' : (($tkTurf === 'no_turf') ? '<strong>No Turf:</strong> Civil Hardcourt Only' : '<strong>40mm/50mm Commercial Turf:</strong> Double PP + PU backing, high Dtex') ?>
          </td>
          <td>
            <?= ($tkTurf === 'non_infill_turf') ? '30mm FIFA-Quality Non-Infill Curled Thatch' : (($tkTurf === 'no_turf') ? 'No Turf (Hardcourt)' : '50mm/60mm FIFA-standard diamond-spine turf') ?>
          </td>
        </tr>
        <tr>
          <td style="font-weight:700;">Turf Infill System</td>
          <td>
            <?= ($tkTurf === 'non_infill_turf') ? 'Not Applicable (Zero Infill System)' : (($tkTurf === 'no_turf') ? 'Not Applicable' : 'Graded washed silica sand (0.5mm - 1.0mm).') ?>
          </td>
          <td style="background:#f0f7ff;">
            <?= ($tkTurf === 'non_infill_turf') ? '<strong>Not Applicable:</strong> Zero Infill System (Zero Sand &amp; Rubber)' : (($tkTurf === 'no_turf') ? '<strong>Not Applicable</strong>' : '<strong>Commercial Infill:</strong> Silica sand (15 kg/sqm) + SBR rubber (4 kg/sqm)') ?>
          </td>
          <td>
            <?= ($tkTurf === 'non_infill_turf') ? 'Not Applicable (Zero Infill System)' : (($tkTurf === 'no_turf') ? 'Not Applicable' : 'Rounded silica sand + cryogenic SBR / virgin EPDM granules.') ?>
          </td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">LED Stadium Floodlights</td>
          <td>6 Nos 150W Commercial LEDs (IP66, 120 lm/W).</td>
          <td style="background:#f0f7ff;">8 Nos 200W Stadium LEDs (IP66, 140 lm/W, 250+ lux avg).</td>
          <td>10-12 Nos 300W Professional Asymmetric Sports Floodlights (350+ lux).</td>
        </tr>
        <tr>
          <td style="font-weight:700;">Electrical &amp; Controls</td>
          <td>4-core armored copper cable, manual MCB box.</td>
          <td style="background:#f0f7ff;">Heavy armored cable, industrial DB + auto-timer controller.</td>
          <td>Schneider IP65 digital distribution panel + smart timer &amp; earthing.</td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="font-weight:700;">Corrosion Protection</td>
          <td>Red oxide primer (1 coat) + synthetic enamel coat.</td>
          <td style="background:#f0f7ff;">Zinc chromate primer (2 coats) + 2 coats weather-proof PU paint.</td>
          <td>Hot-dip galvanized (GI) or Zinc-rich epoxy + aliphatic PU coating.</td>
        </tr>
        <tr>
          <td style="font-weight:700;">Warranty &amp; Target Client</td>
          <td><strong>1 Year</strong> &bull; Budget turf operator / low-density use</td>
          <td style="background:#f0f7ff;"><strong>2 Years</strong> &bull; Commercial turf facility with daily bookings</td>
          <td><strong>3–5 Years</strong> &bull; High-footfall commercial academy / franchise</td>
        </tr>
        <!-- SFT Rates & Total Budgets (Excl. GST & Incl. GST) -->
        <tr class="matrix-rate-row" style="background:#f8fafc;border-top:1.5px solid #0f172a;">
          <td style="color:#0f172a;">
            <strong>SFT RATE (WITHOUT GST / TAXABLE)</strong>
            <div style="font-size:5.5pt;color:#64748b;font-weight:normal;">Base rate per SFT excluding applicable GST</div>
          </td>
          <td style="text-align:center;color:#065f46;<?= $tkPkg==='essential'?'font-weight:900;background:#d1fae5;':'' ?>"><?= $currSym ?><?= number_format($turnkeyAllPackages['essential']['rate_excl_gst'] ?? $turnkeyAllPackages['essential']['rate'], 2) ?> / SFT</td>
          <td style="text-align:center;color:#1e40af;background:#e0f2fe;border:1.5px solid #38bdf8;<?= $tkPkg==='commercial'?'font-weight:900;':'' ?>"><?= $currSym ?><?= number_format($turnkeyAllPackages['commercial']['rate_excl_gst'] ?? $turnkeyAllPackages['commercial']['rate'], 2) ?> / SFT <?= $tkPkg==='commercial'?'★':'' ?></td>
          <td style="text-align:center;color:#991b1b;<?= $tkPkg==='premium_pro'?'font-weight:900;background:#fee2e2;':'' ?>"><?= $currSym ?><?= number_format($turnkeyAllPackages['premium_pro']['rate_excl_gst'] ?? $turnkeyAllPackages['premium_pro']['rate'], 2) ?> / SFT</td>
        </tr>
        <tr class="matrix-rate-row" style="background:#e0e7ff;border-top:1px solid #cbd5e1;">
          <td style="color:#1e1b4b;">
            <strong>SFT RATE (WITH GST / HANDOVER)</strong>
            <div style="font-size:5.5pt;color:#4338ca;font-weight:normal;">Comprehensive all-inclusive handover rate per SFT</div>
          </td>
          <td style="text-align:center;color:#047857;"><?= $currSym ?><?= number_format($turnkeyAllPackages['essential']['rate_incl_gst'], 2) ?> / SFT</td>
          <td style="text-align:center;color:#1d4ed8;background:#dbeafe;border:1.5px solid #2563eb;"><?= $currSym ?><?= number_format($turnkeyAllPackages['commercial']['rate_incl_gst'], 2) ?> / SFT ★ QUOTED</td>
          <td style="text-align:center;color:#b91c1c;"><?= $currSym ?><?= number_format($turnkeyAllPackages['premium_pro']['rate_incl_gst'], 2) ?> / SFT</td>
        </tr>
        <tr class="matrix-budget-row" style="background:#334155;color:#fff;">
          <td>TOTAL ESTIMATED BUDGET (EXCL. GST)</td>
          <td style="text-align:center;color:#a7f3d0;"><?= $currSym ?><?= number_format($turnkeyAllPackages['essential']['budget_excl_gst'] ?? $turnkeyAllPackages['essential']['budget']) ?></td>
          <td style="text-align:center;color:#93c5fd;border:1.5px solid #38bdf8;"><?= $currSym ?><?= number_format($turnkeyAllPackages['commercial']['budget_excl_gst'] ?? $turnkeyAllPackages['commercial']['budget']) ?></td>
          <td style="text-align:center;color:#fca5a5;"><?= $currSym ?><?= number_format($turnkeyAllPackages['premium_pro']['budget_excl_gst'] ?? $turnkeyAllPackages['premium_pro']['budget']) ?></td>
        </tr>
        <tr class="matrix-budget-row" style="background:#0f172a;color:#fff;">
          <td>GRAND TOTAL BUDGET (INCL. GST)</td>
          <td style="text-align:center;color:#34d399;<?= $tkPkg==='essential'?'border:1.5px solid #10b981;':'' ?>"><?= $currSym ?><?= number_format($turnkeyAllPackages['essential']['budget_incl_gst']) ?></td>
          <td style="text-align:center;color:#60a5fa;border:1.5px solid #38bdf8;"><?= $currSym ?><?= number_format($turnkeyAllPackages['commercial']['budget_incl_gst']) ?></td>
          <td style="text-align:center;color:#f87171;<?= $tkPkg==='premium_pro'?'border:1.5px solid #ef4444;':'' ?>"><?= $currSym ?><?= number_format($turnkeyAllPackages['premium_pro']['budget_incl_gst']) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Commercial Value Proposition & Payment Milestones Container (Fitted on Same Sheet) -->
    <?php
      $turfBannerText = ($tkTurf === 'non_infill_turf') 
          ? 'non-infill multi-sport artificial grass (zero infill required)' 
          : (($tkTurf === 'no_turf') ? 'hardcourt playing surface' : 'artificial turf with silica & rubber infill');
      $roofBannerText = ($tkRoof === 'open_top')
          ? 'open top enclosure'
          : (($tkRoof === 'light_cable_top') ? 'lightweight 1.5mm/24-ply cable net' : (($tkRoof === 'pipe_top') ? 'cross pipes top support' : 'engineered structural roof trusses'));
    ?>
    <!-- Payment Milestone Bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border:1px solid #cbd5e1;border-radius:4px;padding:2.5px 8px;margin-top:2px;font-size:6.6pt;color:#334155;">
      <div>
        <span style="background:#1e40af;color:#fff;font-weight:800;padding:1px 5px;border-radius:3px;font-size:6pt;margin-right:4px;letter-spacing:.5px;">PAYMENT MILESTONES</span>
        <strong>40% Advance</strong> on Order Confirmation &bull; <strong>40% Milestone</strong> on Civil Base &amp; Structural Steel &bull; <strong>20% Final Balance</strong> on Turf &amp; Handover.
      </div>
      <div style="color:#64748b;font-size:6pt;white-space:nowrap;margin-left:8px;">
        <i class="fa fa-calendar-check me-1 text-primary"></i>Execution: 15–21 Days
      </div>
    </div>

    <!-- Turnkey Investment Value Box -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;padding:3px 8px;margin-top:2px;font-size:6.3pt;color:#1e40af;line-height:1.2;">
      <strong>Turnkey Investment Value:</strong> At <?= $currSym ?><?= number_format($turnkeyAllPackages[$tkPkg]['rate_excl_gst'] ?? $turnkeyAllPackages[$tkPkg]['rate'], 2) ?>/SFT Excl. GST (<?= $currSym ?><?= number_format($turnkeyAllPackages[$tkPkg]['rate_incl_gst'], 2) ?>/SFT Incl. GST &bull; Total Turnkey Handover <?= $currSym ?><?= number_format(round(($turnkeyAllPackages[$tkPkg]['budget_incl_gst'] ?? $turnkeyAllPackages[$tkPkg]['budget']) / 100000, 2)) ?> Lakh for <?= number_format($turnkeySpecs['base_area_sft']) ?> SFT at <?= (int)$tkH ?> FT clear height), this quotation includes <?= htmlspecialchars($currentCivilBedProfile['short_name']) ?>, <?= htmlspecialchars($currentPoleProfile['short_name']) ?> structural columns, <?= $turnkeySpecs['tier_count'] ?> tiers horizontal purlins, <?= $roofBannerText ?>, <?= $turfBannerText ?>, and stadium floodlighting.
    </div>
  </div><!-- /flex-1 padding -->

  <!-- Page Footer -->
  <?= renderStandardDocFooter($company, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Turnkey Matrix -->

<!-- ── MULTI-ZONE INDIVIDUAL SPORT SFT & COST BREAKDOWN (ONLY IF CONFIGURED) ── -->
<?php if (!empty($multiZoneBreakdown['is_multi_zone'])): ?>
<div class="doc-wrapper turnkey-page turnkey-page-break" style="margin-top:0;">
<div class="doc-sheet">

  <!-- Header -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <!-- Page Title Bar -->
  <div style="background:#0f172a;color:#fff;text-align:center;padding:4px 10px;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
    <div style="font-size:9.5pt;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;">
      MULTI-ZONE INDIVIDUAL SPORT SFT &amp; COST BREAKDOWN
    </div>
    <div style="font-size:6.8pt;opacity:.85;margin-top:1px;letter-spacing:.3px;">
      Separate SFT Cost &amp; Technical Scope for Cricket Ground, Badminton Complex &amp; Utility Arena (<?= number_format($multiZoneBreakdown['total_area_sft'] ?? ($doc['turnkey_config']['base_area_sft'] ?? 17850)) ?> SFT Total)
    </div>
  </div>

  <div style="padding:4px 10px;flex:1;">
    <div style="border:1.5px solid #0284c7;border-radius:4px;overflow:hidden;background:#fff;">
      <div style="background:#0f172a;color:#fff;padding:3px 8px;display:flex;align-items:center;justify-content:space-between;">
        <div style="font-weight:900;font-size:7.2pt;letter-spacing:1px;text-transform:uppercase;">
          <i class="fa fa-layer-group text-warning me-1"></i> MULTI-ZONE INDIVIDUAL SPORT SFT &amp; COST BREAKDOWN (3 SEPARATE COURTS)
        </div>
        <div style="font-size:6.5pt;color:#bae6fd;font-weight:700;">
          Entire Arena: <?= number_format($multiZoneBreakdown['total_area_sft']) ?> SFT &bull; Excl. GST: <?= $currSym ?><?= number_format($multiZoneBreakdown['total_rate_excl'], 2) ?>/SFT &bull; Incl. GST: <?= $currSym ?><?= number_format($multiZoneBreakdown['total_rate_incl'], 2) ?>/SFT
        </div>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:6.5pt;font-family:Arial,sans-serif;">
        <thead>
          <tr style="background:#f1f5f9;border-bottom:1.5px solid #cbd5e1;">
            <th style="padding:3px 5px;text-align:left;width:32%;border-right:1px solid #e2e8f0;">Court / Zone Details</th>
            <th style="padding:3px 5px;text-align:center;width:15%;border-right:1px solid #e2e8f0;">Area &amp; Share</th>
            <th style="padding:3px 5px;text-align:right;width:17%;background:#f0fdf4;color:#166534;border-right:1px solid #e2e8f0;">Without GST (Taxable)</th>
            <th style="padding:3px 5px;text-align:right;width:14%;border-right:1px solid #e2e8f0;">GST Amount</th>
            <th style="padding:3px 5px;text-align:right;width:22%;background:#eff6ff;color:#1e40af;">With GST (Turnkey Handover)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($multiZoneBreakdown['zones'] as $z): ?>
          <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:3px 5px;border-right:1px solid #e2e8f0;">
              <div style="font-weight:800;color:<?= $z['color'] ?>;font-size:7pt;">
                <i class="fa <?= $z['icon'] ?> me-1"></i><?= htmlspecialchars($z['name']) ?>
              </div>
              <div style="color:#64748b;font-size:5.8pt;margin-top:1px;line-height:1.2;">
                <?= htmlspecialchars($z['inclusions']) ?>
              </div>
              <?php if (!empty($z['per_court_total'])): ?>
              <div style="display:inline-block;margin-top:1px;background:#e0f2fe;color:#0369a1;padding:1px 4px;border-radius:3px;font-size:5.8pt;font-weight:700;">
                Per Badminton Court (1,350 SFT): Excl. GST <?= $currSym ?><?= number_format($z['per_court_subtotal']) ?> (₹<?= $z['per_court_rate_excl'] ?>/SFT) &bull; Incl. GST <?= $currSym ?><?= number_format($z['per_court_total']) ?> (₹<?= $z['per_court_rate_incl'] ?>/SFT)
              </div>
              <?php endif; ?>
            </td>
            <td style="padding:3px 5px;text-align:center;border-right:1px solid #e2e8f0;">
              <div style="font-weight:800;color:#0f172a;font-size:7pt;"><?= number_format($z['area_sft']) ?> SFT</div>
              <div style="color:#64748b;font-size:5.8pt;"><?= htmlspecialchars($z['dim']) ?> (<?= $z['share_pct'] ?>%)</div>
            </td>
            <td style="padding:3px 5px;text-align:right;background:#f0fdf4;border-right:1px solid #e2e8f0;">
              <div style="font-weight:800;color:#15803d;font-size:7pt;"><?= $currSym ?><?= number_format($z['subtotal_excl_gst'], 2) ?></div>
              <div style="color:#166534;font-size:6.2pt;font-weight:700;"><?= $currSym ?><?= number_format($z['rate_excl_gst'], 2) ?> / SFT</div>
            </td>
            <td style="padding:3px 5px;text-align:right;color:#475569;border-right:1px solid #e2e8f0;">
              <div style="font-weight:700;font-size:6.5pt;"><?= $currSym ?><?= number_format($z['gst_amount'], 2) ?></div>
            </td>
            <td style="padding:3px 5px;text-align:right;background:#eff6ff;">
              <div style="font-weight:900;color:#1d4ed8;font-size:7.2pt;"><?= $currSym ?><?= number_format($z['total_incl_gst'], 2) ?></div>
              <div style="color:#1e40af;font-size:6.2pt;font-weight:800;"><?= $currSym ?><?= number_format($z['rate_incl_gst'], 2) ?> / SFT</div>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:#0f172a;color:#fff;font-weight:900;border-top:2px solid #0f172a;">
            <td style="padding:4px 5px;font-size:6.8pt;border-right:1px solid #1e293b;">
              COMBINED ARENA COMPLEX TOTAL (100%)
            </td>
            <td style="padding:4px 5px;text-align:center;font-size:7pt;color:#38bdf8;border-right:1px solid #1e293b;">
              <?= number_format($multiZoneBreakdown['total_area_sft']) ?> SFT
            </td>
            <td style="padding:4px 5px;text-align:right;color:#34d399;font-size:7pt;border-right:1px solid #1e293b;">
              <div><?= $currSym ?><?= number_format($multiZoneBreakdown['total_subtotal'], 2) ?></div>
              <div style="font-size:6.2pt;color:#a7f3d0;"><?= $currSym ?><?= number_format($multiZoneBreakdown['total_rate_excl'], 2) ?> / SFT</div>
            </td>
            <td style="padding:4px 5px;text-align:right;color:#cbd5e1;font-size:6.5pt;border-right:1px solid #1e293b;">
              <?= $currSym ?><?= number_format($multiZoneBreakdown['total_gst'], 2) ?>
            </td>
            <td style="padding:4px 5px;text-align:right;color:#60a5fa;font-size:7.5pt;">
              <div><?= $currSym ?><?= number_format($multiZoneBreakdown['total_grand'], 2) ?></div>
              <div style="font-size:6.2pt;color:#93c5fd;"><?= $currSym ?><?= number_format($multiZoneBreakdown['total_rate_incl'], 2) ?> / SFT</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Page Footer -->
  <?= renderStandardDocFooter($company, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Multi-Zone -->
<?php endif; ?>
<?php endif; /* /isTurnkey */ ?>
<?php endif; /* /showAmenitiesLayout */ ?>


<?php if (!empty($doc['scope_items'])): ?>
<?php
  // Paginate Scope of Work items into 15 items per page so all scope points
  // are cleanly displayed together on each page without unnecessary multi-page splitting
  $scopeChunks = array_chunk($doc['scope_items'], 20);
  $totalScopeParts = count($scopeChunks);
  $globalScopeNum = 0;
?>
<?php foreach ($scopeChunks as $partIdx => $scopeChunk): ?>
<!-- ── SCOPE OF WORK (PART <?= $partIdx + 1 ?> OF <?= $totalScopeParts ?>) ─────────────────────── -->
<div class="doc-wrapper doc-page-scope" style="page-break-before:always; margin-top:0;">
<div class="doc-sheet">

  <!-- Scope Header -->
  <?= renderStandardDocHeader($company, $logoPath, $showRussea) ?>

  <!-- Scope Doc Meta Strip -->
  <table style="width:100%;border-collapse:collapse;border-top:1.5px solid var(--navy);border-bottom:1.5px solid var(--navy);background:#ffffff;">
    <colgroup><col style="width:40%"><col style="width:30%"><col style="width:30%"></colgroup>
    <tbody>
    <tr>
      <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);">
        <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Document Ref.</span>
        <?= e($doc['id']) ?><?= $totalScopeParts > 1 ? ' &bull; Scope of Work (Part '.($partIdx + 1).' of '.$totalScopeParts.')' : '' ?>
      </td>
      <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
        <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Date</span>
        <?= e(date('d-M-Y', strtotime($doc['date'] ?? 'today'))) ?>
      </td>
      <td style="padding:4px 8px;font-size:7.5pt;font-weight:700;color:var(--navy);border-left:1.5px solid var(--navy);">
        <span style="font-size:6.5pt;text-transform:uppercase;letter-spacing:.5px;color:#888;display:block;">Customer</span>
        <?= e($doc['bill_to']['name'] ?? '—') ?>
      </td>
    </tr>
    </tbody>
  </table>

  <!-- Scope of Work Title -->
  <div style="background:#1a3a7c;color:#fff;text-align:center;padding:5px 12px;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
    <div style="font-size:10.5pt;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;">Scope of Work<?= $totalScopeParts > 1 ? ' (Part '.($partIdx + 1).' of '.$totalScopeParts.')' : '' ?></div>
    <div style="font-size:6.8pt;opacity:.85;margin-top:1px;letter-spacing:.5px;">Detailed description and specifications of items included in this estimation (15 items per page)</div>
  </div>

  <!-- Scope of Work Table -->
  <div class="doc-main-content doc-sheet-scope" style="flex:1;padding:0;">
    <table style="width:100%;border-collapse:collapse;font-size:7.5pt;font-family:Arial,sans-serif;">
      <thead>
        <tr>
          <th style="background:#1a3a7c;color:#fff;padding:3px 6px;font-size:7.2pt;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border:none;width:7%;text-align:center;print-color-adjust:exact;-webkit-print-color-adjust:exact;">Sl.No</th>
          <th style="background:#1a3a7c;color:#fff;padding:3px 6px;font-size:7.2pt;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border:none;print-color-adjust:exact;-webkit-print-color-adjust:exact;">Item Description &amp; Technical Execution Scope</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($scopeChunk as $sitem): ?>
      <?php $globalScopeNum++; ?>
        <tr style="border-bottom:1px solid #d0d8e8;">
          <td style="padding:3px 6px;border:1px solid #d0d8e8;text-align:center;font-weight:700;font-size:7.5pt;vertical-align:top;background:#ffffff;print-color-adjust:exact;-webkit-print-color-adjust:exact;"><?= $globalScopeNum ?>.</td>
          <td style="padding:4px 8px;border:1px solid #d0d8e8;background:#ffffff;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
            <?php 
              if (is_string($sitem)): 
                $sTitle = $sitem;
                $sDesc = '';
                if (strpos($sitem, ':') !== false) {
                    $parts = explode(':', $sitem, 2);
                    $sTitle = trim($parts[0]);
                    $sDesc = trim($parts[1] ?? '');
                }
            ?>
              <div style="font-weight:700;color:var(--navy);margin-bottom:2px;font-size:7.8pt;"><?= e(preg_replace('/^\d+[\.\)]\s*/', '', $sTitle)) ?></div>
              <?php if (!empty($sDesc)): ?>
              <div style="color:#222;line-height:1.35;font-size:7.2pt;"><?= nl2br(e($sDesc)) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <?php if (!empty($sitem['title'])): ?>
              <div style="font-weight:700;color:var(--navy);margin-bottom:2px;font-size:7.8pt;"><?= e(preg_replace('/^\d+[\.\)]\s*/', '', $sitem['title'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($sitem['description'])): ?>
              <div style="color:#222;line-height:1.35;font-size:7.2pt;"><?= nl2br(e($sitem['description'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($sitem['note'])): ?>
              <div style="margin-top:3px;padding:2px 6px;background:#ffffff;border-left:3px solid #f39c12;font-size:6.8pt;color:#5d4037;line-height:1.25;">
                <strong style="color:#b45309;">Please Note:</strong> <?= nl2br(e($sitem['note'])) ?>
              </div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Scope Footer -->
  <div class="doc-footer-pinned" style="margin-top:auto !important; width:100%;">
    <div style="background:#0d1b3e;padding:4px 10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:4px;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
      <span style="color:rgba(255,255,255,.9);font-size:7pt;font-weight:600;"><?= e($company['name']) ?> — Scope of Work <?= $totalScopeParts > 1 ? '(Part '.($partIdx + 1).' of '.$totalScopeParts.')' : '' ?> — <?= e($doc['id']) ?></span>
      <span style="color:rgba(255,255,255,.7);font-size:6.5pt;"><?= ($partIdx + 1 < $totalScopeParts) ? 'Continued on Part '.($partIdx + 2).' &rarr;' : 'End of Scope of Work Specification' ?></span>
    </div>
  </div>

  <!-- Page Footer -->
  <?= renderStandardDocFooter($company, $website) ?>

</div><!-- /doc-sheet -->
</div><!-- /doc-wrapper Page Scope -->
<?php endforeach; ?>
<?php endif; ?>


</div><!-- /doc-view-outer -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let russeaActive = <?= $showRussea ? 'true' : 'false' ?>;
function toggleRusseaBrand() {
  russeaActive = !russeaActive;
  const badge = document.getElementById('russeaHeaderBadge');
  const strip = document.getElementById('russeaBottomStrip');
  const lbl   = document.getElementById('lblRusseaStatus');
  const btn   = document.getElementById('btnToggleRussea');

  if (badge) badge.style.display = russeaActive ? 'inline-flex' : 'none\;
  if (strip) strip.style.display = russeaActive ? 'flex' : 'none\;
  if (lbl)   lbl.textContent   = russeaActive ? 'ON' : 'OFF\;
  if (btn) {
    btn.className = 'btn btn-sm ' + (russeaActive ? 'btn-danger' : 'btn-outline-secondary text-white');
  }

  const formData = new URLSearchParams();
  formData.append('action', 'toggle_russea');
  formData.append('id', '<?= e($doc['id']) ?>');
  formData.append('show', russeaActive ? '1' : '0');

  fetch('ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  }).catch(() => {});
}

function duplicateDoc() {
  if (confirm('Duplicate this estimation as a new draft?')) {
    window.location.href = 'ajax.php?action=duplicate&id=<?= urlencode($doc['id']) ?>';
  }
}
</script>

<?php if (!empty($isTurnkey)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  <?php
    $rawSides = $turnkeyConfig['open_sides'] ?? [];
    $isAllSidesOpen = !empty($rawSides['front']) && !empty($rawSides['left']) && !empty($rawSides['right']) && !empty($rawSides['back']) && !empty($rawSides['top']);
    $isTNet = (strpos($tkSport, 'training_net') !== false || ($turnkeyConfig['shape'] ?? '') === 'training_net' || !empty($turnkeyConfig['is_training_net']));
    if ($isAllSidesOpen || empty($rawSides)) {
      $safeOpenSides = [
        'front' => $isTNet,
        'left'  => false,
        'right' => false,
        'back'  => false,
        'top'   => ($tkRoof === 'open_top'),
      ];
    } else {
      $safeOpenSides = $rawSides;
    }
  ?>
  var tkCfg = <?= json_encode([
    'length'          => $tkL,
    'width'           => $tkW,
    'height'          => $tkH,
    'pitch_length'    => (float)($turnkeyConfig['pitch_length'] ?? 66),
    'sport_type'      => $tkSport,
    'is_training_net' => $isTNet,
    'open_front'      => !empty($safeOpenSides['front']),
    'open_sides'      => $safeOpenSides,
    'layers'          => $safeLayers,
    'gate_count'      => ($safeGates ? $tkGateCount : 0),
    'gates'           => ($safeGates ? ($turnkeyConfig['gates'] ?? null) : null),
    'roof_type'       => $tkRoof,
    'package'         => $tkPkg,
    'pole_style'      => $tkPole,
    'turf_type'       => $tkTurf,
    'civil_bed_type'  => $tkCivilBed,
    'container_id'    => 'turnkey3DContainer',
    'snapshot_img_id' => 'turnkey-3d-print-img',
    'tiers'           => $turnkeyConfig['tiers'] ?? ($turnkeyConfig['specs']['tiers'] ?? null),
    'id'              => $doc['id'] ?? '',
    'doc_id'          => $doc['id'] ?? '',
    'is160Builtup'    => !empty($doc['is160Builtup']) || !empty($turnkeyConfig['is160Builtup']) || (($doc['id'] ?? '') === 'EST-202609-078'),
    'is_multi_4in1'   => !empty($turnkeyConfig['is_multi_4in1']) || ($tkSport === 'multisport_4in1_100x60') || (($doc['id'] ?? '') === 'EST-202610-026'),
    'equipment'       => $turnkeyConfig['equipment'] ?? null,
    'zones'           => $turnkeyConfig['zones'] ?? null,
    'badminton_length'=> $turnkeyConfig['badminton_length'] ?? ($doc['badminton_length'] ?? null),
    'utility_length'  => $turnkeyConfig['utility_length'] ?? ($doc['utility_length'] ?? null),
  ]) ?>;

  if (window.TurnkeyViewer) {
    window.tkDocViewerInstance = window.TurnkeyViewer.init3D('turnkey3DContainer', tkCfg);
    window.TurnkeyViewer.renderFloorPlan('turnkeyFloorPlanSvg', tkCfg);
    window.TurnkeyViewer.renderSideElevation('turnkeySideElevationSvg', tkCfg);
    if (window.TurnkeyViewer.renderCrossElevation) {
      window.TurnkeyViewer.renderCrossElevation('turnkeyCrossElevationSvg', tkCfg);
    }
  }
});
</script>
<?php endif; ?>


<!-- Duplicate Confirmation Modal -->
<div class="modal fade no-print" id="duplicateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="fa fa-copy text-info me-2"></i>Duplicate Estimation</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-3">
          <i class="fa fa-clone fa-3x text-info"></i>
        </div>
        <h5 class="fw-bold mb-1"><?= e($doc['id']) ?></h5>
        <p class="text-muted mb-3"><?= e($doc['bill_to']['name'] ?? '—') ?> &bull; <?= $currSym ?><?= fmtAmount($doc['grand_total'] ?? 0) ?></p>
        <p class="mb-0 text-secondary" style="font-size:.9rem;">
          Create a new Draft duplicate of this estimation with all items, specifications, and configurations copied?
        </p>
      </div>
      <div class="modal-footer justify-content-center bg-light">
        <form method="POST" action="documents.php">
          <input type="hidden" name="action" value="duplicate">
          <input type="hidden" name="id" value="<?= e($doc['id']) ?>">
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info text-white fw-bold px-4"><i class="fa fa-copy me-1"></i>Duplicate as Draft</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function toggleViewPayment(docId) {
  if (!confirm('Toggle payment status for ' + docId + '?')) return;
  fetch('ajax.php?action=toggle_payment', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id=' + encodeURIComponent(docId)
  }).then(r => r.json()).then(res => {
    if (res.ok) {
      location.reload();
    } else {
      alert('Error updating payment status: ' + (res.msg || 'Unknown error'));
    }
  }).catch(() => alert('Network error updating payment status'));
}
</script>

<script>
let docRoofVisible = true;
function toggleDocRoofView() {
  docRoofVisible = !docRoofVisible;
  const txt = document.getElementById("docRoofText");
  if (txt) txt.textContent = docRoofVisible ? "Toggle: Hide Roof Sheets (Look Inside)" : "Toggle: Show Roof Sheets (Enclosed)";
  if (window.tkDocViewerInstance && window.tkDocViewerInstance.toggleRoof) {
    window.tkDocViewerInstance.toggleRoof(docRoofVisible);
  } else if (window.TurnkeyViewer && window.TurnkeyViewer.toggleRoof) {
    window.TurnkeyViewer.toggleRoof(docRoofVisible);
  }
}
</script>

</body>
</html>
