<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('documents','edit');
require_once __DIR__ . '/includes/turnkey_calculator.php';

$settings  = getSettings();
$customers = getAllCustomers();
$defaults  = $settings['defaults'] ?? [];

// Editing existing document?
$editId  = $_GET['edit'] ?? null;
$editDoc = $editId ? getDocument($editId) : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If auto-save created a draft before the user clicked Save, use that draft's ID
    // so we UPDATE it instead of creating a duplicate new document.
    if (!$editId && !empty($_POST['autosave_id'])) {
        $autosaveId = trim($_POST['autosave_id']);
        if ($autosaveId && getDocument($autosaveId)) {
            $editId = $autosaveId;
        }
    }
    // Turnkey Court Auto-Sync: ONLY for turnkey_court estimations
    $isTurnkeyPost = (($_POST['type'] ?? 'estimation') === 'estimation') && (($_POST['estimation_subtype'] ?? '') === 'turnkey_court');
    if ($isTurnkeyPost) {
        $postH = (float)($_POST['tk_height'] ?? 24);
        $postL = (float)($_POST['tk_length'] ?? 90);
        $postW = (float)($_POST['tk_width'] ?? 60);
        $postPkg = $_POST['tk_package'] ?? 'commercial';
        $postSport = $_POST['tk_sport_type'] ?? 'box_cricket';
        $postRoof = $_POST['tk_roof_type'] ?? 'light_cable_top';
        $postPoleStyle = $_POST['tk_pole_style'] ?? 'ladder_2line';
        $postPipeSize = $_POST['tk_pipe_size'] ?? '40x40';
        $postPipeGauge = $_POST['tk_pipe_gauge'] ?? '2.0mm';
        $postTurfType = $_POST['tk_turf_type'] ?? 'infill_turf';
        $postCivilBed = $_POST['tk_civil_bed'] ?? 'compacted_wmm';
        $postPitch = (float)($_POST['tk_pitch_length'] ?? 66);
        $postGateCount = (int)($_POST['tk_gate_count'] ?? 1);
        $postCustom = !empty($_POST['tk_custom_rate']) ? (float)$_POST['tk_custom_rate'] : null;

        $excludedJson = $_POST['turnkey_excluded_json'] ?? '';
        $excludedItems = !empty($excludedJson) ? (is_array($excludedJson) ? $excludedJson : json_decode($excludedJson, true)) : [];

        require_once __DIR__ . '/includes/turnkey_calculator.php';
        $serverBOQ = generateTurnkeyBOQ($postL, $postW, $postH, $postSport, $postRoof, $postPkg, $postPoleStyle, $postPipeSize, $postPipeGauge, $postTurfType, $postCustom, $postPitch, $postCivilBed, 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', $postGateCount);

        $postedCfg = !empty($_POST['turnkey_config']) ? (is_array($_POST['turnkey_config']) ? $_POST['turnkey_config'] : json_decode($_POST['turnkey_config'], true)) : [];

        // If items are submitted in $_POST, compute actual subtotal from user's customized table:
        $actualSubtotal = 0;
        $hasCustomItems = !empty($_POST['item_desc']) && count($_POST['item_desc']) > 0;
        if ($hasCustomItems && !empty($_POST['item_qty']) && !empty($_POST['item_rate'])) {
            foreach ($_POST['item_qty'] as $idx => $q) {
                $r = (float)($_POST['item_rate'][$idx] ?? 0);
                $actualSubtotal += ((float)$q * $r);
            }
        }
        $courtArea = max(1, $postL * $postW);
        $effectiveTargetRate = ($actualSubtotal > 0) ? round($actualSubtotal / $courtArea, 2) : $serverBOQ['selected_pkg']['target_rate'];
        $effectiveTargetTotal = ($actualSubtotal > 0) ? round($actualSubtotal, 2) : $serverBOQ['target_total'];

        $_POST['turnkey_config'] = [
            'length'         => $postL,
            'width'          => $postW,
            'height'         => $postH,
            'pitch_length'   => $postPitch,
            'gate_count'     => $postGateCount,
            'sport_type'     => $postSport,
            'roof_type'      => $postRoof,
            'package'        => $postPkg,
            'pole_style'     => $postPoleStyle,
            'pipe_size'      => $postPipeSize,
            'pipe_gauge'     => $postPipeGauge,
            'turf_type'      => $postTurfType,
            'civil_bed_type' => $postCivilBed,
            'custom_rate'    => $postCustom ?: $effectiveTargetRate,
            'target_rate'    => $effectiveTargetRate,
            'target_total'   => $effectiveTargetTotal,
            'specs'          => $serverBOQ['specs'],
            'excluded_items' => $excludedItems ?: ($postedCfg['excluded_items'] ?? []),
        ];

        // Ensure turnkey court terms reflect the 40% / 40% / 20% milestone terms
        if (!empty($_POST['terms']) && in_array('50% Advance payment required to proceed order', $_POST['terms'])) {
            $_POST['terms'] = [
                'Payment Terms: 40% Advance on order confirmation, 40% upon civil base & structural steel erection, 20% upon final turf installation & court handover',
                '40% Advance payment required to commence site excavation, civil bed work, and structural steel procurement',
                '40% Milestone payment upon completion of civil base slab and structural steel column & truss erection',
                '20% Final balance payment upon sports turf installation, LED floodlighting testing, and final court handover',
                'Transport & Material Freight: Material delivery directly to project site as per BOQ',
                'Site Utilities: Water and electricity power source to be arranged at site by client during execution',
                'System Warranty: 1 Year (Essential) / 2 Years (Commercial) / 3-5 Years (Premium Pro) Complete System Warranty',
            ];
        }

        // If items in POST are completely empty, sync items from serverBOQ
        if (empty($_POST['item_desc']) || count($_POST['item_desc']) === 0) {
            $_POST['item_desc']     = array_column($serverBOQ['doc_items'], 'description');
            $_POST['item_hsn']      = array_column($serverBOQ['doc_items'], 'hsn');
            $_POST['item_qty']      = array_column($serverBOQ['doc_items'], 'qty');
            $_POST['item_unit']     = array_column($serverBOQ['doc_items'], 'unit');
            $_POST['item_rate']     = array_column($serverBOQ['doc_items'], 'rate');
            $_POST['item_gst_rate'] = array_column($serverBOQ['doc_items'], 'gst_rate');
            $rebuiltSpecs = [];
            foreach ($serverBOQ['doc_items'] as $bIdx => $bIt) {
                $rebuiltSpecs[$bIdx] = $bIt['specs'] ?? [];
            }
            $_POST['item_specs'] = $rebuiltSpecs;
        }
    } else {
        // Non-turnkey estimations must NEVER carry turnkey_config
        $_POST['turnkey_config'] = null;
    }

    // Smart Type-Switching: If editing an EST document and user changes category to Invoice/Bill,
    // generate a proper INV- / BILL- serial number and preserve original estimation.
    $saveId = $editId;
    $targetType = $_POST['type'] ?? 'estimation';
    if ($editDoc) {
        $isEstToInv = (strpos((string)$editId, 'EST-') === 0 && in_array($targetType, ['tax_invoice', 'invoice']));
        $isEstToBill = (strpos((string)$editId, 'EST-') === 0 && in_array($targetType, ['bill']));
        $isInvToEst = (strpos((string)$editId, 'INV-') === 0 && in_array($targetType, ['estimation']));
        
        if ($isEstToInv || $isEstToBill || $isInvToEst) {
            $saveId = generateDocNumber($targetType);
            $_POST['converted_from'] = $editId;
        }
    }

    $doc = buildDocument($_POST, $saveId ?: null);
    if (!empty($_POST['converted_from'])) {
        $doc['converted_from'] = $_POST['converted_from'];
    }
    saveDocument($doc);

    // Save customer if "save_customer" checked
    if (!empty($_POST['save_customer']) && !empty($_POST['bt_name'])) {
        $cid = $_POST['customer_id'] ?: generateCustomerId();
        saveCustomer([
            'id'       => $cid,
            'name'     => trim($_POST['bt_name']),
            'address'  => trim($_POST['bt_address'] ?? ''),
            'gstin'    => strtoupper(trim($_POST['bt_gstin'] ?? '')),
            'phone'    => trim($_POST['bt_phone'] ?? ''),
            'whatsapp' => trim($_POST['bt_whatsapp'] ?? ($_POST['bt_phone'] ?? '')),
            'state'    => trim($_POST['bt_state'] ?? ''),
        ]);
    }

    flashSet('success', ($editId ? 'Document updated' : 'Document created') . ': ' . $doc['id']);
    redirect('view.php?id=' . urlencode($doc['id']));
}

// Pre-fill from edit or defaults
$d          = $editDoc;
$type       = $d['type']    ?? ($_GET['type'] ?? 'estimation');
$estSubtype = $d['estimation_subtype'] ?? 'normal';
$isGst      = isset($d['is_gst']) ? (bool)$d['is_gst'] : true;
$terms = $d['terms'] ?? null;
// Automatically supply 40% / 40% / 20% milestone terms for turnkey courts
if ($estSubtype === 'turnkey_court') {
    if (empty($terms) || in_array('50% Advance payment required to proceed order', $terms) || !preg_grep('/40%/', $terms)) {
        $terms = [
            'Payment Terms: 40% Advance on order confirmation, 40% upon civil base & structural steel erection, 20% upon final turf installation & court handover',
            '40% Advance payment required to commence site excavation, civil bed work, and structural steel procurement',
            '40% Milestone payment upon completion of civil base slab and structural steel column & truss erection',
            '20% Final balance payment upon sports turf installation, LED floodlighting testing, and final court handover',
            'Transport & Material Freight: Material delivery directly to project site as per BOQ',
            'Site Utilities: Water and electricity power source to be arranged at site by client during execution',
            'System Warranty: 1 Year (Essential) / 2 Years (Commercial) / 3-5 Years (Premium Pro) Complete System Warranty',
        ];
    }
} elseif (empty($terms)) {
    $terms = $defaults['terms'] ?? [];
}
$gstMode    = $d['gst_mode'] ?? ($isGst ? 'cgst_sgst' : 'none');
$items      = $d['items']   ?? [['description'=>'','hsn'=>'','qty'=>'','unit'=>'SQFT','rate'=>'','amount'=>0,'specs'=>[]]];
$companies  = getAllCompanies();
$selCompId  = $d['company_id'] ?? ($_GET['company_id'] ?? null);
// Default to the set-default company
if (!$selCompId) { $def = getDefaultCompany(); $selCompId = $def['id'] ?? null; }
$selCompany = $selCompId ? getCompany($selCompId) : getDefaultCompany();

// Default GST rate per item (for pre-filling when editing existing docs)
$defaultItemGst = 0;
if ($isGst) {
    if (($d['gst_mode'] ?? 'cgst_sgst') === 'igst') {
        $defaultItemGst = $d['igst_rate'] ?? 5;
    } else {
        $defaultItemGst = ($d['cgst_rate'] ?? 2.5) + ($d['sgst_rate'] ?? 2.5);
    }
}

$convertedFrom = $_GET['converted_from'] ?? null;
$pageTitle = $editId ? 'Edit Document' : 'New Document';
require_once __DIR__ . '/includes/layout.php';
?>

<style>
.form-section { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.07); padding:1.25rem 1.5rem; margin-bottom:1rem; }
.form-section h5 { font-weight:700; font-size:.95rem; color:#1a1a2e; margin-bottom:1rem; padding-bottom:.5rem; border-bottom:2px solid #f0f2f7; display:flex; align-items:center; gap:.5rem; }
.form-section h5 i { color:var(--red); width:20px; }
.form-label { font-size:.82rem; font-weight:600; color:#555; margin-bottom:.2rem; }
.form-control, .form-select { font-size:.88rem; }
.items-table th { background:#f8f9fc; font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; color:#888; padding:.5rem .4rem; white-space:nowrap; }
.items-table td { padding:.4rem .3rem; vertical-align:top; }
.items-table .form-control-sm, .items-table .form-select-sm { padding-left:.35rem; padding-right:.25rem; }
.items-table .item-rate {
  min-width: 130px !important;
  font-size: .88rem;
  font-weight: 600;
  padding-left: .45rem !important;
  padding-right: .25rem !important;
}
.items-table .item-qty {
  min-width: 75px !important;
}
.items-table .item-hsn {
  min-width: 65px !important;
}
.items-table .item-unit-select {
  min-width: 85px !important;
  font-size: .84rem;
  padding-right: 1.25rem !important;
}
.item-row { border-bottom:1px solid #f0f2f7; }
.spec-row { background:#fafbfe; }
.spec-row td { padding:.25rem .5rem; }
.btn-add-row { border-style:dashed !important; }
.amount-col { font-weight:700; color:#1a1a2e; min-width:115px; }
.totals-table td { padding:.4rem .75rem; font-size:.9rem; }
.totals-table .grand { font-size:1.05rem; font-weight:800; background:var(--navy,#0d1b3e); color:#fff; }
#amountWords { font-style:italic; font-size:.85rem; color:#555; background:#f8f9fc; padding:.5rem .75rem; border-radius:6px; border-left:3px solid var(--red); }
.type-pills .btn-check:checked + .btn { background:var(--red); color:#fff; border-color:var(--red); }
.spec-input { font-size:.8rem; }
</style>

<form method="POST" id="docForm">
<?php if ($convertedFrom): ?>
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
  <i class="fa fa-wand-magic-sparkles" style="color:#2563eb;font-size:1.2rem;"></i>
  <div>
    <div style="font-weight:800;font-size:.9rem;color:#1e40af;">Converted from Estimation <a href="view.php?id=<?= urlencode($convertedFrom) ?>" target="_blank" style="color:#1d4ed8;"><?= e($convertedFrom) ?></a></div>
    <div style="font-size:.78rem;color:#3b82f6;">All items, customer details, GST and terms have been copied. Review &amp; adjust below, then click <strong>Save &amp; Finalize</strong>.</div>
  </div>
  <a href="view.php?id=<?= urlencode($convertedFrom) ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-auto" style="font-size:.78rem;white-space:nowrap;">
    <i class="fa fa-eye me-1"></i>View Original Estimation
  </a>
</div>
<?php endif; ?>

<!-- COMPANY SELECTOR BANNER -->
<div class="card border-0 rounded-3 mb-3" style="background:linear-gradient(135deg,#14213d 60%,#c0392b);box-shadow:0 3px 18px rgba(20,33,61,.18);">
  <div class="card-body py-2 px-3">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="d-flex align-items-center gap-2 flex-grow-1">
        <div style="width:38px;height:38px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa fa-building text-white" style="font-size:1.1rem;"></i>
        </div>
        <div>
          <div class="text-white opacity-75" style="font-size:.7rem;letter-spacing:.8px;text-transform:uppercase;">Document will be created for</div>
          <div class="text-white fw-bold" style="font-size:1rem;letter-spacing:.5px;" id="companyPreviewName"><?= e($selCompany['name'] ?? '') ?></div>
          <div class="text-white opacity-60" style="font-size:.7rem;" id="companyPreviewSub">
            <?= $selCompany['gstin'] ? 'GSTIN: '.e($selCompany['gstin']).' &bull; ' : '' ?><?= e($selCompany['phone'] ?? '') ?><?= $selCompany['state'] ? ' &bull; '.e($selCompany['state']) : '' ?>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (count($companies) > 1): ?>
        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:.3rem .5rem;">
          <div class="text-white opacity-75 mb-1" style="font-size:.65rem;letter-spacing:.8px;text-transform:uppercase;"><i class="fa fa-exchange-alt me-1"></i>Switch Company</div>
          <select name="company_id" id="companySelector" class="form-select form-select-sm" style="min-width:220px;font-weight:700;border:2px solid rgba(255,255,255,.4);background:rgba(255,255,255,.95);color:#14213d;border-radius:6px;" onchange="switchCompany(this)">
            <?php foreach ($companies as $co): ?>
            <option value="<?= e($co['id']) ?>" <?= $co['id']===$selCompId?'selected':'' ?>>
              <?= !empty($co['is_default']) ? '★ ' : '' ?><?= e($co['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="company_id" value="<?= e($selCompId) ?>">
        <div style="background:rgba(255,255,255,.1);border:1px dashed rgba(255,255,255,.4);border-radius:8px;padding:.4rem .8rem;font-size:.75rem;color:rgba(255,255,255,.8);">
          <i class="fa fa-info-circle me-1"></i>Only one company. <a href="companies.php?action=new" target="_blank" style="color:#ffd700;text-decoration:underline;font-weight:700;">Add another company →</a>
        </div>
        <?php endif; ?>
        <a href="companies.php?action=new" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;white-space:nowrap;font-size:.75rem;">
          <i class="fa fa-plus me-1"></i>New Company
        </a>
      </div>
    </div>
  </div>
</div>
<!-- hidden company data for JS -->
<div id="companyData" style="display:none;"><?php
  $compArr = [];
  foreach ($companies as $co) { $compArr[$co['id']] = $co; }
  echo htmlspecialchars(json_encode($compArr), ENT_QUOTES, 'UTF-8');
?></div>

<div class="row g-3">

<!-- LEFT COLUMN -->
<div class="col-lg-8">

  <!-- Document Type & Number -->
  <div class="form-section">
    <h5><i class="fa fa-file-alt"></i> Document Details</h5>
    <div class="row g-3">

      <!-- P.O. Number & Delivery Timeline Row -->
      <div class="col-12" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:6px;">
        <div class="row g-2 align-items-center">
          <div class="col-md-4">
            <label class="form-label small fw-bold text-primary mb-1"><i class="fa fa-file-invoice me-1"></i>Customer P.O. Number</label>
            <input type="text" name="po_number" id="po_number" class="form-control form-control-sm" value="<?= e($d['po_number'] ?? '') ?>" placeholder="e.g. UDPL/PO/2026/045">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold text-primary mb-1"><i class="fa fa-calendar-check me-1"></i>P.O. Date</label>
            <input type="date" name="po_date" id="po_date" class="form-control form-control-sm" value="<?= e($d['po_date'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold text-primary mb-1"><i class="fa fa-truck-fast me-1"></i>Delivery / Execution Timeline</label>
            <input type="text" name="delivery_timeline" id="delivery_timeline" class="form-control form-control-sm" value="<?= e($d['delivery_timeline'] ?? '') ?>" placeholder="e.g. Within 7-10 Days / Immediate">
          </div>
        </div>
      </div>

      <!-- Row 1: Document category + GST toggle -->
      <div class="col-12">
        <div class="d-flex flex-wrap gap-2 align-items-end">
          <div style="flex:1;min-width:220px;">
            <label class="form-label">Document Category</label>
            <div class="btn-group type-pills w-100" role="group">
              <?php foreach (['estimation'=>'Estimation','tax_invoice'=>'Invoice','bill'=>'Bill'] as $k=>$lbl): ?>
              <input type="radio" class="btn-check" name="type" id="type_<?= $k ?>" value="<?= $k ?>" <?= $type===$k?'checked':'' ?> onchange="refreshDocLabel()">
              <label class="btn btn-outline-danger" for="type_<?= $k ?>"><?= $lbl ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="flex:0 0 auto;min-width:220px;">
            <label class="form-label">GST Applicable?</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="is_gst" id="is_gst_yes" value="1" <?= $isGst?'checked':'' ?> onchange="toggleGST()">
              <label class="btn btn-outline-success" for="is_gst_yes"><i class="fa fa-percent me-1"></i>With GST</label>
              <input type="radio" class="btn-check" name="is_gst" id="is_gst_no" value="0" <?= !$isGst?'checked':'' ?> onchange="toggleGST()">
              <label class="btn btn-outline-secondary" for="is_gst_no"><i class="fa fa-ban me-1"></i>Without GST</label>
            </div>
          </div>
          <!-- Live doc-type label badge -->
          <div style="flex:0 0 auto;padding-bottom:2px;">
            <div id="docTypePreview" style="background:var(--navy);color:#fff;border-radius:8px;padding:6px 14px;font-weight:800;font-size:.85rem;letter-spacing:1px;white-space:nowrap;min-width:130px;text-align:center;">
              <?= strtoupper(docTypeLabel($type, $isGst)) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 1b: Estimation Sub-Type (visible only when type=estimation) -->
      <div class="col-12" id="estSubtypeRow" <?= $type !== 'estimation' ? 'style="display:none;"' : '' ?>>
        <label class="form-label"><i class="fa fa-layer-group text-danger me-1"></i>Estimation Type</label>
        <div class="btn-group flex-wrap w-100" role="group" id="estSubtypeGroup">
          <?php
            $subtypes = [
              'normal'               => ['label'=>'Normal',              'icon'=>'fa-file-alt',        'color'=>'secondary'],
              'supply_only'          => ['label'=>'Supply Only',         'icon'=>'fa-truck',            'color'=>'primary'],
              'supply_installation'  => ['label'=>'Supply & Installation','icon'=>'fa-tools',           'color'=>'warning'],
              'cricket_net'          => ['label'=>'Cricket Net Setup',   'icon'=>'fa-cricket-bat-ball', 'color'=>'success'],
              'sports_net'           => ['label'=>'Sports Net Setup',    'icon'=>'fa-futbol',           'color'=>'danger'],
              'turnkey_court'        => ['label'=>'🏟️ Turnkey Sports Court', 'icon'=>'fa-trophy',      'color'=>'danger'],
            ];
            foreach ($subtypes as $sk => $sv):
          ?>
          <input type="radio" class="btn-check" name="estimation_subtype" id="est_<?= $sk ?>"
            value="<?= $sk ?>" <?= $estSubtype===$sk?'checked':'' ?>
            onchange="onEstSubtypeChange('<?= $sk ?>')">
          <label class="btn btn-outline-<?= $sv['color'] ?> btn-sm" for="est_<?= $sk ?>">
            <i class="fa <?= $sv['icon'] ?> me-1"></i><?= $sv['label'] ?>
          </label>
          <?php endforeach; ?>
        </div>
        <div id="estSubtypeHint" class="small text-muted mt-1" style="min-height:18px;"></div>
      </div>

      <!-- Row 2: Doc# + Date -->
      <div class="col-md-6">
        <label class="form-label">Document # <small class="text-muted">(auto)</small></label>
        <input type="text" class="form-control bg-light" value="<?= $editId ? e($editId) : 'Auto-generated on save' ?>" disabled>
      </div>
      <div class="col-md-6">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($d['date'] ?? date('Y-m-d')) ?>" required>
      </div>
    </div>
  </div>

  <!-- Bill To -->
  <div class="form-section">
    <h5><i class="fa fa-user-tag"></i> Bill To (Customer)</h5>
    <input type="hidden" name="customer_id" id="customer_id" value="<?= e($d['customer_id'] ?? '') ?>">
    <div class="row g-2 mb-2">
      <div class="col-12">
        <label class="form-label">Quick Fill from Saved Customers</label>
        <select class="form-select" id="custSelect" onchange="fillCustomer(this.value)">
          <option value="">— Select saved customer —</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= e($c['id']) ?>"
            data-name="<?= e($c['name']) ?>"
            data-address="<?= e($c['address']) ?>"
            data-gstin="<?= e($c['gstin'] ?? '') ?>"
            data-state="<?= e($c['state'] ?? '') ?>"
            data-phone="<?= e($c['phone'] ?? '') ?>"
            data-whatsapp="<?= e($c['whatsapp'] ?? ($c['phone'] ?? '')) ?>"
            <?= ($d['customer_id'] ?? '') === $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?><?= !empty($c['phone']) ? ' (📞 '.$c['phone'].')' : ($c['gstin'] ? ' — '.$c['gstin'] : '') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Company / Customer Name <span class="text-danger">*</span></label>
        <input type="text" name="bt_name" id="bt_name" class="form-control" value="<?= e($d['bill_to']['name'] ?? '') ?>" required placeholder="Customer or Company Name">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">GSTIN</label>
        <input type="text" name="bt_gstin" id="bt_gstin" class="form-control" value="<?= e($d['bill_to']['gstin'] ?? '') ?>" placeholder="22AAAAA0000A1Z5" maxlength="15" style="text-transform:uppercase;">
      </div>

      <!-- Mandatory Customer Mobile & WhatsApp Numbers -->
      <?php
        $valPhone = $d['bill_to']['phone'] ?? '';
        $valWa    = $d['bill_to']['whatsapp'] ?? '';
        if ($valPhone !== '' && $valWa === '') {
            $valWa = $valPhone;
        }
        $isSameMobile = ($valPhone === '' && $valWa === '') || ($valPhone !== '' && $valPhone === $valWa);
      ?>
      <div class="col-md-6">
        <label class="form-label fw-semibold"><i class="fa fa-phone text-primary me-1"></i>Customer Mobile Number <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-light text-muted fw-bold" style="font-size:0.85rem;">+91</span>
          <input type="tel" name="bt_phone" id="bt_phone" class="form-control fw-semibold"
            value="<?= e($valPhone) ?>"
            required
            placeholder="10-digit Mobile Number"
            pattern="[0-9]{10}"
            maxlength="10"
            title="Please enter a valid 10-digit customer mobile number"
            autocomplete="tel">
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <small class="text-muted" style="font-size:0.72rem;">Mandatory 10-digit Indian mobile number</small>
        </div>
      </div>

      <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label fw-semibold mb-0"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Number <span class="text-danger">*</span></label>
          <div class="form-check form-check-inline m-0">
            <input class="form-check-input" type="checkbox" id="same_as_mobile" <?= $isSameMobile ? 'checked' : '' ?> onchange="toggleSameAsMobile()">
            <label class="form-check-label small fw-bold text-success" for="same_as_mobile" style="cursor:pointer;font-size:0.75rem;">
              <i class="fab fa-whatsapp me-1"></i>Same as Mobile Number
            </label>
          </div>
        </div>
        <div class="input-group">
          <span class="input-group-text bg-light text-success fw-bold" style="font-size:0.85rem;"><i class="fab fa-whatsapp"></i>&nbsp;+91</span>
          <input type="tel" name="bt_whatsapp" id="bt_whatsapp" class="form-control fw-semibold"
            value="<?= e($valWa ?: $valPhone) ?>"
            required
            placeholder="10-digit WhatsApp Number"
            pattern="[0-9]{10}"
            maxlength="10"
            title="Please enter a valid 10-digit WhatsApp number"
            <?= $isSameMobile ? 'readonly style="background-color:#f8fafc;"' : '' ?>
            autocomplete="tel">
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <small id="waHint" class="text-muted" style="font-size:0.72rem;"><?= $isSameMobile ? 'Synchronized with Mobile Number' : 'Custom WhatsApp number' ?></small>
        </div>
      </div>

      <div class="col-md-8">
        <label class="form-label fw-semibold">Address</label>
        <textarea name="bt_address" id="bt_address" class="form-control" rows="2" placeholder="Full postal address"><?= e($d['bill_to']['address'] ?? '') ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">State</label>
        <input type="text" name="bt_state" id="bt_state" class="form-control" value="<?= e($d['bill_to']['state'] ?? '') ?>" placeholder="Telangana">
      </div>
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="save_customer" id="save_customer" value="1">
          <label class="form-check-label small fw-semibold text-secondary" for="save_customer">Save / update this customer with Mobile &amp; WhatsApp in the customer list</label>
        </div>
      </div>

      <!-- Shipping / Consignee Delivery Address (SHIP TO) -->
      <?php
        $shipData = $d['ship_to'] ?? [];
        $isSameShip = !empty($shipData['same_as_bill']) || (empty($shipData['address']) && empty($shipData['name']));
      ?>
      <div class="col-12 mt-2 pt-2 border-top">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="form-label fw-bold mb-0 text-primary">
            <i class="fa fa-truck-ramp-box text-danger me-1"></i> Shipping / Site Delivery Address (SHIP TO)
          </label>
          <div class="form-check form-check-inline m-0">
            <input class="form-check-input" type="checkbox" name="ship_same_as_bill" id="ship_same_as_bill" value="1" <?= $isSameShip ? 'checked' : '' ?> onchange="toggleShipAddress()">
            <label class="form-check-label small fw-bold text-primary" for="ship_same_as_bill" style="cursor:pointer;font-size:0.75rem;">
              <i class="fa fa-link me-1"></i>Same as Billing Address
            </label>
          </div>
        </div>

        <div id="shipAddressBox" style="display:<?= $isSameShip ? 'none' : 'block' ?>;background:#f0f7ff;border:1.5px solid #93c5fd;border-radius:8px;padding:12px;">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-dark mb-1">Consignee / Site Name</label>
              <input type="text" name="st_name" id="st_name" class="form-control form-control-sm" value="<?= e($shipData['name'] ?? '') ?>" placeholder="e.g. Ultra Dimensions Site Office / Project Warehouse">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-dark mb-1">Site Contact Person &amp; Phone</label>
              <input type="text" name="st_phone" id="st_phone" class="form-control form-control-sm" value="<?= e($shipData['phone'] ?? '') ?>" placeholder="e.g. Site Supervisor (+91 9502944409)">
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold text-dark mb-1">Site / Delivery Address</label>
              <textarea name="st_address" id="st_address" class="form-control form-control-sm" rows="2" placeholder="Exact delivery location, landmark, city, pin code"><?= e($shipData['address'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">Destination State / Place of Supply</label>
              <input type="text" name="st_state" id="st_state" class="form-control form-control-sm" value="<?= e($shipData['state'] ?? '') ?>" placeholder="e.g. Andhra Pradesh">
            </div>
          </div>
        </div>
      </div>

      <!-- Customer Type + Sale Unit Mode -->
      <div class="col-md-6">
        <label class="form-label"><i class="fa fa-user-tag text-danger me-1"></i>Customer Type</label>
        <div class="btn-group w-100" role="group" id="custTypeGroup">
          <input type="radio" class="btn-check" name="customer_type" id="ct_client" value="client"
            <?= ($d['customer_type'] ?? 'client') === 'client' ? 'checked' : '' ?> onchange="onCustTypeChange()">
          <label class="btn btn-outline-primary" for="ct_client"><i class="fa fa-user me-1"></i>Client (Retail)</label>
          <input type="radio" class="btn-check" name="customer_type" id="ct_dealer" value="dealer"
            <?= ($d['customer_type'] ?? '') === 'dealer' ? 'checked' : '' ?> onchange="onCustTypeChange()">
          <label class="btn btn-outline-warning" for="ct_dealer"><i class="fa fa-handshake me-1"></i>Dealer (Wholesale)</label>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label"><i class="fa fa-balance-scale text-danger me-1"></i>Default Sale Unit</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="sale_unit_mode" id="su_sqft" value="sqft"
            <?= ($d['sale_unit_mode'] ?? 'sqft') === 'sqft' ? 'checked' : '' ?>>
          <label class="btn btn-outline-secondary" for="su_sqft">SFT (Sq.Ft)</label>
          <input type="radio" class="btn-check" name="sale_unit_mode" id="su_kg" value="kg"
            <?= ($d['sale_unit_mode'] ?? '') === 'kg' ? 'checked' : '' ?>>
          <label class="btn btn-outline-secondary" for="su_kg">KG (Weight)</label>
          <input type="radio" class="btn-check" name="sale_unit_mode" id="su_both" value="both"
            <?= ($d['sale_unit_mode'] ?? '') === 'both' ? 'checked' : '' ?>>
          <label class="btn btn-outline-secondary" for="su_both">Both</label>
        </div>
        <div class="small text-muted mt-1" id="saleUnitHint">Sets the default unit for new item rows</div>
      </div>
    </div>
  </div>

  <!-- TURNKEY SPORTS COURT SETUP PANEL (Visible when estimation_subtype === 'turnkey_court') -->
  <div class="form-section mb-3" id="turnkeyPanel" style="background:linear-gradient(135deg,#fffbf5 0%,#fef2f2 100%);border:2px solid #ef4444;box-shadow:0 4px 20px rgba(239,68,68,0.12);<?= $estSubtype !== 'turnkey_court' ? 'display:none;' : '' ?>">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
      <div class="d-flex align-items-center gap-2">
        <span style="background:#ef4444;color:#fff;width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;">🏟️</span>
        <div>
          <h5 class="mb-0 text-danger fw-bold">Turnkey Sports Court Estimation Generator</h5>
          <small class="text-muted">Enter dimensions to auto-calculate poles, pipes, nets, turf, floodlights & 3-package pricing</small>
        </div>
      </div>
      <span class="badge bg-danger text-white px-3 py-2" style="letter-spacing:.5px;font-size:.8rem;">
        <i class="fa fa-calculator me-1"></i>3-PACKAGE MODEL (₹250 / ₹300 / ₹350)
      </span>
    </div>

    <!-- Hidden Turnkey Config Field -->
    <input type="hidden" name="turnkey_config" id="turnkeyConfigJson" value="<?= e(json_encode($d['turnkey_config'] ?? null)) ?>">
    <input type="hidden" name="turnkey_excluded_json" id="turnkeyExcludedJson" value="<?= e(json_encode($d['turnkey_config']['excluded_items'] ?? [])) ?>">

    <!-- Row 1: Quick Presets -->
    <div class="mb-3">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <label class="form-label text-dark fw-bold mb-0"><i class="fa fa-bookmark text-danger me-1"></i>1-Click Standard Court &amp; Stadium Presets</label>
        <span class="text-muted" style="font-size:7.5pt;"><i class="fa fa-circle-check text-success me-1"></i>Auto-configures dimensions, turf, civil base, netting &amp; equipment</span>
      </div>
      
      <!-- Cricket Presets -->
      <div class="mb-2">
        <span class="badge bg-dark me-1" style="font-size:7pt;">🏏 CRICKET</span>
        <div class="d-inline-flex flex-wrap gap-1">
          <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 tk-preset-btn active" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(100, 50, 25, 'box_cricket', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
            <strong>100 × 50 ft</strong> (5,000 SFT) — Standard Box Cricket
          </button>
          <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(90, 60, 24, 'box_cricket', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
            <strong>90 × 60 ft</strong> (5,400 SFT) — Turnkey Match Model
          </button>
          <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(80, 50, 22, 'box_cricket', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
            <strong>80 × 50 ft</strong> (4,000 SFT) — Compact Box Cricket
          </button>
          <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(140, 140, 30, 'cricket_circular', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
            <strong>140 FT Dia</strong> (15,394 SFT) — Circular Cricket Arena
          </button>
          <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(180, 120, 30, 'cricket_oval', 'open_top', 'non_infill_turf', 'compacted_wmm')">
            <strong>180 × 120 ft</strong> (16,965 SFT) — Oval Cricket Ground
          </button>
        </div>
      </div>

      <!-- Football Presets (5a, 6a, 7a, 8a compact 12k, 8a std 16k, 9a, 10a, 11a, Futsal) -->
      <div class="mb-2">
        <span class="badge bg-success me-1" style="font-size:7pt;">⚽ FOOTBALL TURFS (5a to 11a)</span>
        <div class="d-inline-flex flex-wrap gap-1">
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(100, 60, 25, 'football_5a', 'light_cable_top', 'infill_turf', 'compacted_wmm')">
            <strong>60 × 100 ft</strong> (6,000 SFT) — 5-a-Side Football
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(110, 70, 25, 'football_6a', 'light_cable_top', 'infill_turf', 'compacted_wmm')">
            <strong>70 × 110 ft</strong> (7,700 SFT) — 6-a-Side Football
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(140, 80, 28, 'football_7a', 'light_cable_top', 'infill_turf', 'compacted_wmm')">
            <strong>80 × 140 ft</strong> (11,200 SFT) — 7-a-Side Football
          </button>
          <button type="button" class="btn btn-xs btn-success text-white py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(150, 80, 28, 'football_8a_compact', 'open_top', 'infill_turf', 'compacted_wmm')">
            ★ <strong>80 × 150 ft (12,000 SFT) — 8-a-Side Compact Football Turf</strong>
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(160, 100, 30, 'football_8a', 'open_top', 'infill_turf', 'compacted_wmm')">
            <strong>100 × 160 ft</strong> (16,000 SFT) — 8-a-Side Standard Arena
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(180, 120, 32, 'football_9a', 'open_top', 'infill_turf', 'compacted_wmm')">
            <strong>120 × 180 ft</strong> (21,600 SFT) — 9-a-Side Ground
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(220, 140, 35, 'football_10a', 'open_top', 'infill_turf', 'compacted_wmm')">
            <strong>140 × 220 ft</strong> (30,800 SFT) — 10-a-Side Stadium
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(320, 200, 35, 'football_11a', 'open_top', 'infill_turf', 'compacted_wmm')">
            <strong>200 × 320 ft</strong> (64,000 SFT) — 11-a-Side Full Stadium
          </button>
          <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(130, 75, 25, 'futsal', 'light_cable_top', 'infill_turf', 'compacted_wmm')">
            <strong>75 × 130 ft</strong> (9,750 SFT) — Official Futsal
          </button>
        </div>
      </div>

      <!-- Terrace & Rooftop Cricket & Multi-Sport Presets -->
      <div class="mb-2">
        <span class="badge bg-warning text-dark me-1" style="font-size:7pt;">🏢 TERRACE / ROOFTOP NETS (10–20 FT Height Recommended)</span>
        <div class="d-inline-flex flex-wrap gap-1">
          <button type="button" class="btn btn-xs btn-outline-warning text-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(30, 15, 12, 'terrace_cricket_small', 'light_cable_top', 'no_turf', 'terrace_base_plates')">
            <strong>15 × 30 ft</strong> (450 SFT, 12'H) — Terrace Single Net
          </button>
          <button type="button" class="btn btn-xs btn-outline-warning text-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(50, 20, 14, 'terrace_cricket_lane', 'light_cable_top', 'non_infill_turf', 'terrace_base_plates')">
            <strong>20 × 50 ft</strong> (1,000 SFT, 14'H) — Terrace Practice Lane
          </button>
          <button type="button" class="btn btn-xs btn-outline-warning text-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 30, 16, 'terrace_cricket_box', 'light_cable_top', 'non_infill_turf', 'terrace_base_plates')">
            <strong>30 × 60 ft</strong> (1,800 SFT, 16'H) — Terrace Box Cricket
          </button>
          <button type="button" class="btn btn-xs btn-outline-warning text-dark py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(80, 40, 18, 'terrace_sports_arena', 'light_cable_top', 'non_infill_turf', 'terrace_base_plates')">
            <strong>40 × 80 ft</strong> (3,200 SFT, 18'H) — Commercial Rooftop Arena
          </button>
        </div>
      </div>

      <!-- Racket, Ball & Multi-Sports -->
      <div>
        <span class="badge bg-primary me-1" style="font-size:7pt;">🏸 RACKET &amp; MULTI-SPORT</span>
        <div class="d-inline-flex flex-wrap gap-1">
          <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 30, 25, 'badminton', 'light_cable_top', 'bwf_vinyl_mat', 'concrete_pcc_3inch')">
            <strong>30 × 60 ft</strong> (1,800 SFT) — Badminton Single
          </button>
          <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(94, 50, 28, 'basketball_full', 'open_top', 'synthetic_acrylic_8layer', 'concrete_pcc_3inch')">
            <strong>50 × 94 ft</strong> (4,700 SFT) — Basketball Full Court (FIBA)
          </button>
          <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(120, 60, 30, 'tennis', 'open_top', 'synthetic_acrylic_8layer', 'concrete_pcc_3inch')">
            <strong>60 × 120 ft</strong> (7,200 SFT) — Tennis Court (ITF)
          </button>
          <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 30, 22, 'pickleball', 'open_top', 'synthetic_acrylic_8layer', 'concrete_pcc_3inch')">
            <strong>30 × 60 ft</strong> (1,800 SFT) — Pickleball Court
          </button>
          <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(100, 60, 25, 'multi_sport', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
            <strong>60 × 100 ft</strong> (6,000 SFT) — Multi-Sport (Cricket + Football)
          </button>
        </div>
      </div>
    </div>

    <!-- Row 2: Court Dimensions & Sport Type -->
    <div class="row g-2 mb-3">
      <div class="col-md-3">
        <label class="form-label text-danger fw-bold">Length (Feet) *</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_length" name="tk_length" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['length'] ?? 90) ?>" min="20" max="300" step="1" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-danger fw-bold">Width (Feet) *</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_width" name="tk_width" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['width'] ?? 60) ?>" min="15" max="200" step="1" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-danger fw-bold">Clear Height (Feet) *</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_height" name="tk_height" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['height'] ?? 25) ?>" min="10" max="60" step="1" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
        <div class="d-flex flex-wrap gap-1 mt-1">
          <button type="button" class="btn btn-outline-warning text-dark py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(10)">10'</button>
          <button type="button" class="btn btn-outline-warning text-dark py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(12)">12'</button>
          <button type="button" class="btn btn-outline-warning text-dark py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(14)">14'</button>
          <button type="button" class="btn btn-outline-warning text-dark py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(16)">16'</button>
          <button type="button" class="btn btn-outline-warning text-dark py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(18)">18'</button>
          <button type="button" class="btn btn-outline-secondary py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(20)">20'</button>
          <button type="button" class="btn btn-outline-secondary py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(25)">25'</button>
          <button type="button" class="btn btn-outline-secondary py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(30)">30'</button>
          <button type="button" class="btn btn-outline-secondary py-0 px-1 tk-height-btn" style="font-size:6.8pt;" onclick="setTurnkeyHeight(35)">35'</button>
        </div>
        <div class="text-muted mt-1" style="font-size:6.8pt;"><span class="text-warning-emphasis fw-bold">🏢 Terrace:</span> 10–20 FT recommended for lower wind load; 25–35 FT for ground.</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold">Sport Type</label>
        <select id="tk_sport_type" name="tk_sport_type" class="form-select form-select-sm fw-semibold" onchange="onSportTypeChange()">
          <optgroup label="🏢 Terrace &amp; Rooftop Sports Nets">
            <option value="terrace_cricket_small" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'terrace_cricket_small' ? 'selected' : '' ?>>🏢 Terrace Single Cricket Net (15×30 / 12'H)</option>
            <option value="terrace_cricket_lane" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'terrace_cricket_lane' ? 'selected' : '' ?>>🏢 Terrace Practice Bowling Lane (20×50 / 14'H)</option>
            <option value="terrace_cricket_box" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'terrace_cricket_box' ? 'selected' : '' ?>>🏢 Terrace Box Cricket Arena (30×60 / 16'H)</option>
            <option value="terrace_sports_arena" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'terrace_sports_arena' ? 'selected' : '' ?>>🏢 Commercial Rooftop Arena (40×80 / 18'H)</option>
          </optgroup>
          <optgroup label="🏏 Cricket Grounds &amp; Arenas">
            <option value="box_cricket" <?= in_array(($d['turnkey_config']['sport_type'] ?? 'box_cricket'), ['box_cricket', 'cricket']) ? 'selected' : '' ?>>🏏 Box Cricket Turf (Standard)</option>
            <option value="cricket_circular" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'cricket_circular' ? 'selected' : '' ?>>🏏 Circular Cricket Arena (Round 360°)</option>
            <option value="cricket_oval" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'cricket_oval' ? 'selected' : '' ?>>🏏 Oval Cricket Ground (Major × Minor)</option>
          </optgroup>
          <optgroup label="⚽ Football &amp; Futsal Turfs">
            <option value="football_5a" <?= in_array(($d['turnkey_config']['sport_type'] ?? ''), ['football_5a', 'football']) ? 'selected' : '' ?>>⚽ Football 5-a-Side Turf (60×100)</option>
            <option value="football_6a" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'football_6a' ? 'selected' : '' ?>>⚽ Football 6-a-Side Turf (70×110)</option>
            <option value="football_7a" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'football_7a' ? 'selected' : '' ?>>⚽ Football 7-a-Side Arena (80×140)</option>
            <option value="football_8a_compact" <?= in_array(($d['turnkey_config']['sport_type'] ?? ''), ['football_8a_compact', 'football_8a_12k']) ? 'selected' : '' ?>>★ ⚽ Football 8-a-Side Compact (80×150 / 12k SFT)</option>
            <option value="football_8a" <?= in_array(($d['turnkey_config']['sport_type'] ?? ''), ['football_8a', 'football_8a_std']) ? 'selected' : '' ?>>⚽ Football 8-a-Side Standard (100×160 / 16k SFT)</option>
            <option value="football_9a" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'football_9a' ? 'selected' : '' ?>>⚽ Football 9-a-Side Ground (120×180 / 220×140)</option>
            <option value="football_10a" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'football_10a' ? 'selected' : '' ?>>⚽ Football 10-a-Side Stadium (140×220 / 30.8k SFT)</option>
            <option value="football_11a" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'football_11a' ? 'selected' : '' ?>>⚽ Football 11-a-Side Full Stadium (200×320+)</option>
            <option value="futsal" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'futsal' ? 'selected' : '' ?>>🥅 Official Futsal Arena (75×130)</option>
          </optgroup>
          <optgroup label="🏸 Racket &amp; Ball Sports">
            <option value="badminton" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'badminton' ? 'selected' : '' ?>>🏸 Badminton Single Court (30×60)</option>
            <option value="badminton_multi" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'badminton_multi' ? 'selected' : '' ?>>🏸 Badminton Multi-Court Complex (60×60+)</option>
            <option value="basketball_half" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'basketball_half' ? 'selected' : '' ?>>🏀 Basketball Half Court (47×50)</option>
            <option value="basketball_full" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'basketball_full' ? 'selected' : '' ?>>🏀 Basketball Full Court FIBA (50×94)</option>
            <option value="tennis" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'tennis' ? 'selected' : '' ?>>🎾 ITF Standard Tennis Court (60×120)</option>
            <option value="pickleball" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'pickleball' ? 'selected' : '' ?>>🏓 Pickleball Single Court (30×60)</option>
            <option value="pickleball_multi" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'pickleball_multi' ? 'selected' : '' ?>>🏓 Pickleball 2-Court Complex (60×60)</option>
          </optgroup>
          <optgroup label="🏆 Multi-Sport Combinations">
            <option value="multi_sport" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'multi_sport' ? 'selected' : '' ?>>🏏⚽ Multi-Sport (Cricket + Football Combo)</option>
            <option value="multi_futsal_basket" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'multi_futsal_basket' ? 'selected' : '' ?>>⚽🏀 Multi-Sport (Futsal + Basketball Combo)</option>
          </optgroup>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold">Cricket Pitch Length</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_pitch_length" name="tk_pitch_length" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['pitch_length'] ?? 66) ?>" min="30" max="100" step="1" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
        <div class="text-muted" style="font-size:7pt;">Width: <strong>6.5 FT (2.0 MTR)</strong> standard 1 roll</div>
      </div>
    </div>

    <!-- Row 3: Civil Bed System, Turf Infill Type, Roof Support Netting & Pole Style -->
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-trowel-bricks text-danger me-1"></i>Civil Ground &amp; Bed System</label>
        <select id="tk_civil_bed" name="tk_civil_bed" class="form-select form-select-sm fw-semibold" onchange="updateTurnkeyCalculations(true)">
          <optgroup label="🏢 Rooftop &amp; Terrace Mounting (No Ground Digging)">
            <option value="terrace_base_plates" <?= in_array(($d['turnkey_config']['civil_bed_type'] ?? ''), ['terrace_base_plates', 'rooftop_base_plates']) ? 'selected' : '' ?>>🏢 Terrace / Rooftop Base Plates (M12/M16 Chemical Anchors) (~₹18/SFT) [No Digging]</option>
          </optgroup>
          <optgroup label="🚜 Granular Sub-Bases (Turf Drainage)">
            <option value="compacted_wmm" <?= in_array(($d['turnkey_config']['civil_bed_type'] ?? 'compacted_wmm'), ['compacted_wmm', 'compacted_wmm_4inch']) ? 'selected' : '' ?>>🚜 4" Compacted WMM Base + 1" Stone Dust (~₹40/SFT)</option>
            <option value="compacted_wmm_6inch" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'compacted_wmm_6inch' ? 'selected' : '' ?>>🚜 6" Heavy Compacted WMM / GSB Base (~₹50/SFT)</option>
          </optgroup>
          <optgroup label="🧱 3-Inch Plain Cement Concrete (M15 Non-Reinforced) [75mm]">
            <option value="pcc_m15_3inch_broom" <?= in_array(($d['turnkey_config']['civil_bed_type'] ?? ''), ['pcc_m15_3inch_broom', 'concrete_pcc_3inch']) ? 'selected' : '' ?>>🧱 3" PCC M15 Broom Finish (~₹55/SFT) [Turf/Tiles/Mat]</option>
            <option value="pcc_m15_3inch_smooth" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_3inch_smooth' ? 'selected' : '' ?>>🧱 3" PCC M15 Smooth Troweled (~₹60/SFT) [Acrylic/Indoor]</option>
            <option value="pcc_m15_3inch_tremix" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_3inch_tremix' ? 'selected' : '' ?>>🧱 3" PCC M15 Vacuum Tremix (~₹68/SFT) [Dense Bed]</option>
          </optgroup>
          <optgroup label="🧱 4-Inch Plain Cement Concrete (M15 Non-Reinforced) [100mm]">
            <option value="pcc_m15_4inch_broom" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_4inch_broom' ? 'selected' : '' ?>>🧱 4" PCC M15 Broom Textured (~₹70/SFT) [Standard Box Cricket]</option>
            <option value="pcc_m15_4inch_smooth" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_4inch_smooth' ? 'selected' : '' ?>>🧱 4" PCC M15 Smooth Laser Screeded (~₹76/SFT) [ITF Courts]</option>
            <option value="pcc_m15_4inch_tremix" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_4inch_tremix' ? 'selected' : '' ?>>🧱 4" PCC M15 Vacuum Tremix + Joints (~₹84/SFT) [Commercial]</option>
            <option value="pcc_m15_4inch_ips" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_4inch_ips' ? 'selected' : '' ?>>🧱 4" PCC M15 + IPS Floor Hardener (~₹90/SFT) [Monolithic Polish]</option>
          </optgroup>
          <optgroup label="🧱 5-Inch &amp; 6-Inch Heavy M15 Concrete Beds [125mm &amp; 150mm]">
            <option value="pcc_m15_5inch_tremix" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_5inch_tremix' ? 'selected' : '' ?>>🧱 5" PCC M15 Heavy Tremix Slab (~₹98/SFT) [Multi-Courts]</option>
            <option value="pcc_m15_6inch_tremix" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'pcc_m15_6inch_tremix' ? 'selected' : '' ?>>🧱 6" PCC M15 Extra-Heavy Tremix (~₹116/SFT) [Heavy Load/Clay]</option>
          </optgroup>
          <optgroup label="🛣️ Asphalt &amp; Natural Earth">
            <option value="asphalt_bitumen" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'asphalt_bitumen' ? 'selected' : '' ?>>🛣️ Dual-Layer Machine-Laid Asphalt (~₹82/SFT) [ITF Tennis]</option>
            <option value="laser_graded_earth" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'laser_graded_earth' ? 'selected' : '' ?>>🌱 Laser-Graded Stabilized Earth (~₹24/SFT) [Economic Base]</option>
          </optgroup>
          <optgroup label="🏗️ REINFORCED CONCRETE (RCC M20/M25) [Highest Rate Variants]">
            <option value="rcc_m20_4inch_mesh" <?= in_array(($d['turnkey_config']['civil_bed_type'] ?? ''), ['rcc_m20_4inch_mesh', 'concrete_rcc_4inch']) ? 'selected' : '' ?>>🏗️ 4" RCC M20 + 6mm Wire Mesh (~₹110/SFT) [Rooftops/Weak Soil]</option>
            <option value="rcc_m20_5inch_tmt" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'rcc_m20_5inch_tmt' ? 'selected' : '' ?>>🏗️ 5" RCC M20 + 8mm TMT Rebar Grid (~₹132/SFT) [Elevated Deck]</option>
            <option value="rcc_m25_6inch_tmt" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'rcc_m25_6inch_tmt' ? 'selected' : '' ?>>🏗️ 6" RCC M25 + Dual TMT Steel Cage (~₹160/SFT) [Commercial Stadium]</option>
          </optgroup>
          <optgroup label="🤝 Client Scope">
            <option value="client_scope" <?= ($d['turnkey_config']['civil_bed_type'] ?? '') === 'client_scope' ? 'selected' : '' ?>>🤝 Civil Bed Executed by Client (₹0 in BOQ)</option>
          </optgroup>
        </select>
        <div class="text-muted" style="font-size:7pt;">M15 Plain Concrete is standard for sports; RCC reinforced placed at end for special decks</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-seedling text-success me-1"></i>Sports Flooring / Turf System</label>
        <select id="tk_turf_type" name="tk_turf_type" class="form-select form-select-sm fw-semibold" onchange="updateTurnkeyCalculations(true)">
          <optgroup label="🏏 Cricket &amp; Multi-Sport Turfs">
            <option value="non_infill_turf" <?= in_array(($d['turnkey_config']['turf_type'] ?? 'non_infill_turf'), ['non_infill_turf', '']) ? 'selected' : '' ?>>🏏 15mm Curled Monofilament Cricket Turf (Non-Infill) (~₹65/SFT)</option>
            <option value="turf_cricket_20mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'turf_cricket_20mm' ? 'selected' : '' ?>>🏏 20mm High-Density Multi-Sport Turf (Non-Infill) (~₹72/SFT)</option>
            <option value="cricket_dual_turf" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'cricket_dual_turf' ? 'selected' : '' ?>>🏏 15mm Outfield + 6.5'x66' Dedicated Pitch Strip (~₹76/SFT)</option>
          </optgroup>
          <optgroup label="⚽ Football &amp; Futsal Turf Systems">
            <option value="infill_turf" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'infill_turf' ? 'selected' : '' ?>>⚽ 50mm FIFA Football Turf + Sand/SBR Infill (~₹75/SFT)</option>
            <option value="turf_football_40mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'turf_football_40mm' ? 'selected' : '' ?>>⚽ 40mm Futsal &amp; 5-a-Side Football Turf + Infill (~₹68/SFT)</option>
            <option value="turf_football_55mm_fifa" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'turf_football_55mm_fifa' ? 'selected' : '' ?>>⚽ 55mm FIFA Pro Tournament Football Turf (~₹88/SFT)</option>
            <option value="turf_football_non_infill" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'turf_football_non_infill' ? 'selected' : '' ?>>⚽ 32mm Non-Infill Football Grass (No Rubber) (~₹82/SFT)</option>
          </optgroup>
          <optgroup label="🎾 Acrylic Synthetic Hardcourt / Cushion Systems">
            <option value="synthetic_acrylic_3layer" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'synthetic_acrylic_3layer' ? 'selected' : '' ?>>🏓 3-Layer Acrylic Hardcourt (Pickleball/Basket) (~₹45/SFT)</option>
            <option value="synthetic_acrylic_5layer" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'synthetic_acrylic_5layer' ? 'selected' : '' ?>>🏀 5-Layer Synthetic Acrylic Standard Hardcourt (~₹58/SFT)</option>
            <option value="synthetic_acrylic_8layer" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'synthetic_acrylic_8layer' ? 'selected' : '' ?>>🎾 8-Layer Synthetic Acrylic Cushion Court (~₹85/SFT)</option>
            <option value="synthetic_acrylic_10layer" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'synthetic_acrylic_10layer' ? 'selected' : '' ?>>🏆 10-Layer Pro Elite Grand Slam Cushion (~₹115/SFT)</option>
          </optgroup>
          <optgroup label="🏸 Badminton &amp; Indoor Vinyl Sports Flooring">
            <option value="bwf_vinyl_mat" <?= in_array(($d['turnkey_config']['turf_type'] ?? ''), ['bwf_vinyl_mat', 'bwf_vinyl_4_5mm']) ? 'selected' : '' ?>>🏸 4.5mm BWF Approved Anti-Slip Vinyl Sports Mat (~₹70/SFT)</option>
            <option value="bwf_vinyl_5_0mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'bwf_vinyl_5_0mm' ? 'selected' : '' ?>>🏸 5.0mm BWF Tournament Grade Sports Vinyl (~₹80/SFT)</option>
            <option value="bwf_vinyl_7_0mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'bwf_vinyl_7_0mm' ? 'selected' : '' ?>>🏸 7.0mm Multi-Sport Gymnasium Vinyl Floor (~₹98/SFT)</option>
          </optgroup>
          <optgroup label="🏓 Modular Interlocking PP Sports Tiles">
            <option value="pp_modular_tiles" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'pp_modular_tiles' ? 'selected' : '' ?>>🏓 15.8mm UV Interlocking Modular Sports Tiles (~₹75/SFT)</option>
            <option value="pp_modular_tiles_cushion" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'pp_modular_tiles_cushion' ? 'selected' : '' ?>>🏓 19mm Double-Tier Elastic Suspended PP Tiles (~₹88/SFT)</option>
          </optgroup>
          <optgroup label="🪵 Indoor Wooden Sports Flooring">
            <option value="wooden_teak_maple" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'wooden_teak_maple' ? 'selected' : '' ?>>🪵 21mm Solid Teak/Maple Hardwood Sports Floor (~₹220/SFT)</option>
          </optgroup>
          <optgroup label="🚫 Base / Civil Only">
            <option value="no_turf" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'no_turf' ? 'selected' : '' ?>>🚫 No Top Flooring (Civil Sub-Base Only) (₹0 in BOQ)</option>
          </optgroup>
        </select>
        <div class="text-muted" style="font-size:7pt;">Categorized by sport discipline with certified pile &amp; layer standards</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-arrow-up-from-bracket text-primary me-1"></i>Overhead Roof Support</label>
        <select id="tk_roof_type" name="tk_roof_type" class="form-select form-select-sm fw-semibold" onchange="onRoofTypeChange()">
          <option value="light_cable_top" <?= in_array(($d['turnkey_config']['roof_type'] ?? 'light_cable_top'), ['light_cable_top', 'top_net']) ? 'selected' : '' ?>>🕸️ Lightweight Cable Net Top (No Steel)</option>
          <option value="pipe_top" <?= ($d['turnkey_config']['roof_type'] ?? '') === 'pipe_top' ? 'selected' : '' ?>>📏 Cross Pipes Top Support (Small Courts)</option>
          <option value="truss_top" <?= in_array(($d['turnkey_config']['roof_type'] ?? ''), ['truss_top', 'full_roof']) ? 'selected' : '' ?>>🏗️ Engineered Trusses (Large Courts / High Wind)</option>
          <option value="open_top" <?= ($d['turnkey_config']['roof_type'] ?? '') === 'open_top' ? 'selected' : '' ?>>☀️ Open Top (Side &amp; Back Nets Only)</option>
        </select>
        <div class="text-muted" style="font-size:7pt;">Trusses cost more than normal pipes or cable suspended nets</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-border-all text-danger me-1"></i>Side &amp; Back Sports Net</label>
        <?php $curSideNet = $d['turnkey_config']['side_net'] ?? 'braided_2_5mm_45mm'; ?>
        <select id="tk_side_net" name="tk_side_net" class="form-select form-select-sm fw-semibold" onchange="updateTurnkeyCalculations(true)">
          <optgroup label="2.5mm Braided Net (Standard &amp; Heavy)">
            <option value="braided_2_5mm_45mm" <?= in_array($curSideNet, ['braided_2_5mm_45mm', '45mm']) ? 'selected' : '' ?>>2.5mm Braided (45mm Mesh - Recommended Standard)</option>
            <option value="braided_2_5mm_40mm" <?= in_array($curSideNet, ['braided_2_5mm_40mm', '40mm']) ? 'selected' : '' ?>>2.5mm Braided (40mm Mesh - Tournament Heavy)</option>
            <option value="braided_2_5mm_35mm" <?= in_array($curSideNet, ['braided_2_5mm_35mm', '35mm']) ? 'selected' : '' ?>>2.5mm Braided (35mm Mesh - Compact Impact)</option>
            <option value="braided_2_5mm_50mm" <?= in_array($curSideNet, ['braided_2_5mm_50mm', '50mm']) ? 'selected' : '' ?>>2.5mm Braided (50mm Mesh - Multi-Sport)</option>
          </optgroup>
          <optgroup label="2.0mm Braided Net (Medium)">
            <option value="braided_2_0mm_45mm" <?= $curSideNet === 'braided_2_0mm_45mm' ? 'selected' : '' ?>>2.0mm Braided (45mm Mesh)</option>
            <option value="braided_2_0mm_40mm" <?= $curSideNet === 'braided_2_0mm_40mm' ? 'selected' : '' ?>>2.0mm Braided (40mm Mesh)</option>
            <option value="braided_2_0mm_35mm" <?= $curSideNet === 'braided_2_0mm_35mm' ? 'selected' : '' ?>>2.0mm Braided (35mm Mesh)</option>
            <option value="braided_2_0mm_50mm" <?= $curSideNet === 'braided_2_0mm_50mm' ? 'selected' : '' ?>>2.0mm Braided (50mm Mesh)</option>
          </optgroup>
          <optgroup label="3.0mm Heavy Braided Net (Pro Arena)">
            <option value="braided_3_0mm_40mm" <?= $curSideNet === 'braided_3_0mm_40mm' ? 'selected' : '' ?>>3.0mm Heavy Braided (40mm Mesh)</option>
            <option value="braided_3_0mm_45mm" <?= $curSideNet === 'braided_3_0mm_45mm' ? 'selected' : '' ?>>3.0mm Heavy Braided (45mm Mesh)</option>
            <option value="braided_3_0mm_50mm" <?= $curSideNet === 'braided_3_0mm_50mm' ? 'selected' : '' ?>>3.0mm Heavy Braided (50mm Mesh)</option>
          </optgroup>
          <optgroup label="Football &amp; Large Mesh Nets">
            <option value="braided_3_5mm_100mm" <?= in_array($curSideNet, ['braided_3_5mm_100mm', '100mm']) ? 'selected' : '' ?>>3.5mm Heavy Braided (100mm Football Mesh)</option>
            <option value="braided_4_0mm_120mm" <?= in_array($curSideNet, ['braided_4_0mm_120mm', '120mm']) ? 'selected' : '' ?>>4.0mm High-Impact Braided (120mm Stadium Mesh)</option>
            <option value="dual_cricket_football" <?= $curSideNet === 'dual_cricket_football' ? 'selected' : '' ?>>Dual-Zone Combination (40mm Bottom 10 FT + 100mm Top)</option>
          </optgroup>
          <optgroup label="1.5mm Twisted (Budget / Light)">
            <option value="twisted_1_5mm_35mm" <?= $curSideNet === 'twisted_1_5mm_35mm' ? 'selected' : '' ?>>1.5mm Twisted (35mm Mesh)</option>
          </optgroup>
        </select>
        <div class="text-muted" style="font-size:7pt;">Heavy-duty boundary ball-stop netting</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-cloud-arrow-up text-primary me-1"></i>Overhead / Top Roof Net</label>
        <?php 
          $isInitOpenTop = ($d['turnkey_config']['roof_type'] ?? '') === 'open_top';
          $curTopNet = $d['turnkey_config']['top_net'] ?? 'twisted_1_5mm_35mm';
        ?>
        <select id="tk_top_net" name="tk_top_net" class="form-select form-select-sm fw-semibold <?= $isInitOpenTop ? 'bg-light' : '' ?>" onchange="updateTurnkeyCalculations(true)" <?= $isInitOpenTop ? 'disabled' : '' ?>>
          <?php if ($isInitOpenTop): ?>
            <option value="open_top" selected>☀️ None (Open Top — No Overhead Net)</option>
          <?php else: ?>
            <optgroup label="Uniform Top Net (Same as Side Net)">
              <option value="same_as_side" <?= in_array($curTopNet, ['same_as_side', 'all_sides_same']) ? 'selected' : '' ?>>🎯 Same as Side Net (Uniform 2.5mm Braided All Sides &amp; Top)</option>
            </optgroup>
            <optgroup label="2.5mm Braided Heavy Top Net (Trusses / Heavy Cable)">
              <option value="braided_2_5mm_45mm" <?= $curTopNet === 'braided_2_5mm_45mm' ? 'selected' : '' ?>>2.5mm Braided (45mm Mesh - Heavy Overhead)</option>
              <option value="braided_2_5mm_40mm" <?= $curTopNet === 'braided_2_5mm_40mm' ? 'selected' : '' ?>>2.5mm Braided (40mm Mesh - Heavy Overhead)</option>
              <option value="braided_2_5mm_35mm" <?= $curTopNet === 'braided_2_5mm_35mm' ? 'selected' : '' ?>>2.5mm Braided (35mm Mesh - Heavy Overhead)</option>
              <option value="braided_2_5mm_50mm" <?= $curTopNet === 'braided_2_5mm_50mm' ? 'selected' : '' ?>>2.5mm Braided (50mm Mesh - Multi-Sport Top)</option>
              <option value="braided_2_5mm_100mm" <?= in_array($curTopNet, ['braided_2_5mm_100mm', '100mm']) ? 'selected' : '' ?>>2.5mm Braided (100mm Football Roof Mesh)</option>
            </optgroup>
            <optgroup label="2.0mm Braided Medium Top Net">
              <option value="braided_2_0mm_45mm" <?= $curTopNet === 'braided_2_0mm_45mm' ? 'selected' : '' ?>>2.0mm Braided (45mm Mesh)</option>
              <option value="braided_2_0mm_40mm" <?= $curTopNet === 'braided_2_0mm_40mm' ? 'selected' : '' ?>>2.0mm Braided (40mm Mesh)</option>
              <option value="braided_2_0mm_35mm" <?= $curTopNet === 'braided_2_0mm_35mm' ? 'selected' : '' ?>>2.0mm Braided (35mm Mesh)</option>
              <option value="braided_2_0mm_50mm" <?= $curTopNet === 'braided_2_0mm_50mm' ? 'selected' : '' ?>>2.0mm Braided (50mm Mesh)</option>
            </optgroup>
            <optgroup label="1.5mm / 1.0mm Lightweight Cable Top Net (Zero Sag)">
              <option value="twisted_1_5mm_35mm" <?= in_array($curTopNet, ['twisted_1_5mm_35mm', '35mm']) ? 'selected' : '' ?>>1.5mm Twisted (35mm Mesh / 24-Ply - Recommended Light)</option>
              <option value="twisted_1_5mm_30mm" <?= in_array($curTopNet, ['twisted_1_5mm_30mm', '30mm']) ? 'selected' : '' ?>>1.5mm Twisted (30mm Mesh / 24-Ply)</option>
              <option value="twisted_1_5mm_40mm" <?= in_array($curTopNet, ['twisted_1_5mm_40mm', '40mm']) ? 'selected' : '' ?>>1.5mm Twisted (40mm Mesh / 24-Ply)</option>
              <option value="twisted_1_0mm_30mm" <?= $curTopNet === 'twisted_1_0mm_30mm' ? 'selected' : '' ?>>1.0mm Twisted (30mm Mesh - Ultra Light)</option>
            </optgroup>
          <?php endif; ?>
        </select>
        <div class="text-muted" style="font-size:7pt;">2.5mm braided (35/40/45/50mm) or 1.5mm lightweight twisted net</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-bars-staggered text-danger me-1"></i>Column / Pole Style</label>
        <select id="tk_pole_style" name="tk_pole_style" class="form-select form-select-sm fw-semibold" onchange="onPoleStyleChange()">
          <option value="ladder_2line" <?= in_array(($d['turnkey_config']['pole_style'] ?? 'ladder_2line'), ['ladder_2line', 's3_ladder_truss']) ? 'selected' : '' ?>>🪜 2-Lines Ladder (1.5' Rungs)</option>
          <option value="ladder_4line" <?= ($d['turnkey_config']['pole_style'] ?? '') === 'ladder_4line' ? 'selected' : '' ?>>🗼 4-Lines Box Lattice Tower</option>
          <option value="single_pipe" <?= in_array(($d['turnkey_config']['pole_style'] ?? ''), ['single_pipe', 's2_heavy_4x4', 's4_heavy_5x5', 's1_standard_3x3']) ? 'selected' : '' ?>>⬛ Single Heavy Pipe Column</option>
        </select>
        <div class="text-muted" style="font-size:7pt;">Column framework for climbing access &amp; wind rigidity</div>
      </div>
    </div>

    <!-- Row 4: Column Pipe Size, Metal Gauge, Player Access Gates & Custom Rate -->
    <div class="row g-2 mb-2">
<?php
  $curPole = $d['turnkey_config']['pole_style'] ?? 'ladder_2line';
  $curSize = $d['turnkey_config']['pipe_size'] ?? ($curPole === 'single_pipe' ? '4x4' : '40x40');
  $curGauge = $d['turnkey_config']['pipe_gauge'] ?? '2.0mm';
  $curGateCount = (int)($d['turnkey_config']['gate_count'] ?? 1);
?>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-ruler-combined text-info me-1"></i>Column Pipe Size</label>
        <select id="tk_pipe_size" name="tk_pipe_size" class="form-select form-select-sm fw-semibold" onchange="onPipeSizeChange()">
          <?php if ($curPole === 'single_pipe'): ?>
            <option value="3x3" <?= $curSize==='3x3'?'selected':'' ?>>3" × 3" (75×75mm) Column</option>
            <option value="4x4" <?= $curSize==='4x4'?'selected':'' ?>>4" × 4" (100×100mm) Heavy (Recommended)</option>
            <option value="5x5" <?= $curSize==='5x5'?'selected':'' ?>>5" × 5" (125×125mm) Super Heavy</option>
          <?php else: ?>
            <option value="32x32" <?= $curSize==='32x32'?'selected':'' ?>>32 × 32 mm (1.25" × 1.25")</option>
            <option value="40x40" <?= $curSize==='40x40'?'selected':'' ?>>40 × 40 mm (1.5" × 1.5" - Recommended)</option>
            <option value="50x50" <?= $curSize==='50x50'?'selected':'' ?>>50 × 50 mm (2.0" × 2.0")</option>
          <?php endif; ?>
        </select>
        <div class="text-muted" style="font-size:7pt;">Outer dimensions of structural steel members</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-shield text-warning me-1"></i>Metal Wall Thickness / Gauge</label>
        <select id="tk_pipe_gauge" name="tk_pipe_gauge" class="form-select form-select-sm fw-semibold" onchange="updateTurnkeyCalculations(true)">
          <?php if ($curPole === 'single_pipe'): ?>
            <option value="2.0mm" <?= $curGauge==='2.0mm'?'selected':'' ?>>2.0mm (14 Gauge)</option>
            <option value="2.5mm" <?= $curGauge==='2.5mm'?'selected':'' ?>>2.5mm (12 Gauge - Recommended)</option>
            <option value="3.0mm" <?= $curGauge==='3.0mm'?'selected':'' ?>>3.0mm (10 Gauge - Heavy Duty)</option>
          <?php else: ?>
            <option value="1.5mm" <?= $curGauge==='1.5mm'?'selected':'' ?>>1.5mm (16 Gauge)</option>
            <option value="2.0mm" <?= $curGauge==='2.0mm'?'selected':'' ?>>2.0mm (14 Gauge - Recommended)</option>
            <option value="2.5mm" <?= $curGauge==='2.5mm'?'selected':'' ?>>2.5mm (12 Gauge - Heavy)</option>
          <?php endif; ?>
        </select>
        <div class="text-muted" style="font-size:7pt;">Steel wall thickness for load bearing &amp; durability</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-door-open text-danger me-1"></i>Player Access Gates</label>
        <select id="tk_gate_count" name="tk_gate_count" class="form-select form-select-sm fw-semibold" onchange="updateTurnkeyCalculations(true)">
          <option value="1" <?= $curGateCount === 1 ? 'selected' : '' ?>>🚪 1 Gate (Standard 4'×7')</option>
          <option value="2" <?= $curGateCount === 2 ? 'selected' : '' ?>>🚪 2 Gates (Dual Sidelines)</option>
          <option value="3" <?= $curGateCount === 3 ? 'selected' : '' ?>>🚪 3 Gates (2 Player + 1 Equip)</option>
          <option value="4" <?= $curGateCount === 4 ? 'selected' : '' ?>>🚪 4 Gates (Quad Corner Access)</option>
          <option value="0" <?= $curGateCount === 0 ? 'selected' : '' ?>>❌ 0 Gates (Client / Existing)</option>
        </select>
        <div class="text-muted" style="font-size:7pt;">Choose 1 to 3+ entry doors (drawn in 2D &amp; 3D plans)</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-pencil text-primary me-1"></i>Custom Target Rate (Optional)</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text fw-bold bg-white text-danger">₹</span>
          <input type="number" id="tk_custom_rate" name="tk_custom_rate" class="form-control fw-bold text-end" placeholder="e.g. 314" step="any" min="0" value="<?= e($d['turnkey_config']['custom_rate'] ?? '') ?>" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text fw-bold bg-light">/SFT</span>
        </div>
        <div class="text-muted" style="font-size:7pt;">Directly scales all BOQ rates proportionally</div>
      </div>
    </div>

    <!-- Row 5: Turnkey Quality Package Selection -->
    <div class="row g-2 mb-3">
      <div class="col-md-12">
        <label class="form-label text-dark fw-bold"><i class="fa fa-layer-group text-primary me-1"></i>Select Turnkey Quality Package</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="tk_package" id="tk_pkg_essential" value="essential" <?= ($d['turnkey_config']['package'] ?? '') === 'essential' ? 'checked' : '' ?> onchange="updateTurnkeyCalculations(true)">
          <label class="btn btn-outline-success btn-sm py-1" for="tk_pkg_essential">
            <div class="fw-bold" style="font-size:8pt;">🟢 Essential Quality Package</div>
            <div id="disp_pkg_rate_essential" style="font-size:.75rem;">₹250 / SFT</div>
          </label>
          <input type="radio" class="btn-check" name="tk_package" id="tk_pkg_commercial" value="commercial" <?= ($d['turnkey_config']['package'] ?? 'commercial') === 'commercial' ? 'checked' : '' ?> onchange="updateTurnkeyCalculations(true)">
          <label class="btn btn-outline-primary btn-sm py-1" for="tk_pkg_commercial">
            <div class="fw-bold" style="font-size:8pt;">🔵 Commercial Standard Package ★</div>
            <div id="disp_pkg_rate_commercial" style="font-size:.75rem;">₹300 / SFT ★</div>
          </label>
          <input type="radio" class="btn-check" name="tk_package" id="tk_pkg_premium" value="premium_pro" <?= ($d['turnkey_config']['package'] ?? '') === 'premium_pro' ? 'checked' : '' ?> onchange="updateTurnkeyCalculations(true)">
          <label class="btn btn-outline-danger btn-sm py-1" for="tk_pkg_premium">
            <div class="fw-bold" style="font-size:8pt;">🔴 Premium Pro Club Package</div>
            <div id="disp_pkg_rate_premium" style="font-size:.75rem;">₹350 / SFT</div>
          </label>
        </div>
      </div>
    </div>

    <!-- Turf Roll & Pitch Specification Info -->
    <div class="p-2 mb-2 bg-light border rounded d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size:7.5pt;color:#334155;">
      <div><i class="fa fa-circle-info text-primary me-1"></i><strong>Turf Roll Standard:</strong> 2.0M × 25.0M (6.5 FT × 83 FT = 540 SFT per roll) &bull; <strong>Cricket Pitch Pad:</strong> 6.5 FT (2 MTR) Width</div>
      <div class="text-success fw-bold"><i class="fa fa-check-double me-1"></i>All rates, quantities & items can be edited, added or deleted with instant live update!</div>
    </div>

    <!-- Live Engineering Metric Preview Cards -->
    <div class="p-3 mb-3" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;">
      <div class="row g-2 text-center">
        <div class="col-6 col-md-2 border-end">
          <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:700;">Base Area</div>
          <div class="fw-bold fs-6 text-dark" id="disp_tk_area">5,400 SFT</div>
          <small class="text-muted" id="disp_tk_perim">300 RFT Perim</small>
        </div>
        <div class="col-6 col-md-2 border-end">
          <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:700;">Vertical Poles</div>
          <div class="fw-bold fs-6 text-danger" id="disp_tk_poles">20 Columns</div>
          <small class="text-muted" id="disp_tk_spacing">15 ft Bay Spacing</small>
        </div>
        <div class="col-6 col-md-2 border-end">
          <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:700;">Horizontal Pipes</div>
          <div class="fw-bold fs-6 text-primary" id="disp_tk_pipes">1,200 RFT</div>
          <small class="text-muted" id="disp_tk_tiers">4 Tiers (60 Pipes)</small>
        </div>
        <div class="col-6 col-md-2 border-end">
          <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:700;">Sports Netting</div>
          <div class="fw-bold fs-6 text-success" id="disp_tk_net">13,230 SFT</div>
          <small class="text-muted" id="disp_tk_net_detail">Side + Top Net</small>
        </div>
        <div class="col-6 col-md-2 border-end">
          <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:700;">Floodlighting</div>
          <div class="fw-bold fs-6 text-warning" id="disp_tk_lights">8x 200W LEDs</div>
          <small class="text-muted">6 Mast Positions</small>
        </div>
        <div class="col-6 col-md-2">
          <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;font-weight:700;">Target Turnkey Total</div>
          <div class="fw-bold fs-5 text-success" id="disp_tk_total">₹16,20,000</div>
          <small class="badge bg-primary-subtle text-primary" id="disp_tk_rate">₹300 / SFT</small>
        </div>
      </div>
    </div>

    <!-- Generate BOQ Button & Real-Time Auto-Calculation Status -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="small" id="turnkeySyncStatus">
        <span class="text-success fw-bold"><i class="fa fa-check-circle me-1"></i>Auto-Calculated Turnkey BOQ (Civil Base, Steel Cage, Nets, Turf, Infill, Lights, Gate, Labour, Logistics)</span>
      </div>
      <button type="button" id="btnPopulateTurnkey" class="btn btn-danger px-4 fw-bold shadow-sm" onclick="generateTurnkeyBOQAndPopulate(false)">
        <i class="fa fa-wand-magic-sparkles me-1"></i>Re-Populate Turnkey BOQ (15 Items)
      </button>
    </div>
  </div>

  <!-- Items -->
  <div class="form-section">
    <h5><i class="fa fa-list"></i> Items / Products</h5>
    <div class="table-responsive">
      <table class="table items-table border" id="itemsTable" style="min-width:920px;">
        <thead>
          <tr>
            <th style="width:25%;min-width:190px;">Description</th>
            <th style="width:8%;min-width:70px;">HSN</th>
            <th style="width:9%;min-width:75px;">Qty</th>
            <th style="width:9%;min-width:85px;">Unit</th>
            <th style="width:16%;min-width:130px;">Rate (₹)</th>
            <th style="width:10%;min-width:90px;" title="GST Rate for this item">GST %</th>
            <th style="width:14%;min-width:115px;">Amount (₹)</th>
            <th style="width:auto;white-space:nowrap"></th>
          </tr>
        </thead>
        <tbody id="itemsTbody">
        <?php foreach ($items as $i => $item): 
          $rowUid = 'it_' . $i . '_' . substr(md5($i . ($item['description'] ?? '')), 0, 6);
        ?>
          <tr class="item-row" data-item-uid="<?= $rowUid ?>" data-row="<?= $i ?>">
            <td><textarea name="item_desc[<?= $i ?>]" class="form-control form-control-sm" rows="2" placeholder="Description of goods/service" required><?= e($item['description']) ?></textarea></td>
            <td><input type="text" name="item_hsn[<?= $i ?>]" class="form-control form-control-sm item-hsn" value="<?= e($item['hsn']) ?>" placeholder="HSN"></td>
            <td><input type="number" name="item_qty[<?= $i ?>]" class="form-control form-control-sm item-qty" value="<?= e($item['qty']) ?>" step="0.01" min="0" placeholder="0"></td>
            <td>
              <select name="item_unit[<?= $i ?>]" class="form-select form-select-sm item-unit-select">
                <?php
                  $allUnits = ['SQFT','SQMT','RFT','MTR','MTRS','NOS','PCS','KG','KGS','LTR','LTRS','LOT','LOTS','SET','SETS','JOB','BOX','BOXES','ROLL','ROLLS','BAG','BAGS','TON','TONS','PKT'];
                  $curUnit = strtoupper(trim($item['unit'] ?? 'SQFT'));
                  if (!empty($curUnit) && !in_array($curUnit, $allUnits)) {
                      $allUnits[] = $curUnit;
                  }
                  foreach ($allUnits as $u):
                ?>
                <option value="<?= e($u) ?>" <?= (strcasecmp($item['unit'] ?? 'SQFT', $u) === 0) ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="item_rate[<?= $i ?>]" class="form-control form-control-sm item-rate" value="<?= e($item['rate']) ?>" step="0.01" min="0" placeholder="0.00"></td>
            <td><input type="number" name="item_gst_rate[<?= $i ?>]" class="form-control form-control-sm item-gst-rate text-center fw-bold"
              value="<?= e($item['gst_rate'] ?? $defaultItemGst) ?>"
              step="any" min="0" max="100" list="gstRateList"
              style="min-width:75px;padding:.3rem .4rem;font-size:0.85rem;" title="GST % for this item"></td>
            <td class="amount-col"><span class="item-amount">₹<?= fmtAmount($item['amount']) ?></span>
              <input type="hidden" name="item_amount[<?= $i ?>]" class="item-amount-val" value="<?= e($item['amount']) ?>">
            </td>
            <td style="white-space:nowrap;">
              <div class="d-flex flex-column gap-1">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-row" title="Remove row"><i class="fa fa-xmark"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 add-spec-btn" title="Add spec">+Spec</button>
              </div>
            </td>
          </tr>
          <!-- Spec rows -->
          <?php foreach (($item['specs'] ?? []) as $si => $spec): ?>
          <tr class="spec-row" data-parent-uid="<?= $rowUid ?>" data-parent="<?= $i ?>">
            <td colspan="6" style="padding:2px 8px 2px 28px;">
              <div class="d-flex align-items-center gap-1">
                <span class="text-muted" style="font-size:.75rem;">↳</span>
                <input type="text" name="item_specs[<?= $i ?>][]" class="form-control form-control-sm spec-input" value="<?= e($spec) ?>" placeholder="e.g. Thickness : 1.5mm">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-spec" title="Remove spec"><i class="fa fa-minus"></i></button>
              </div>
            </td>
            <td></td>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
<datalist id="gstRateList">
  <option value="0"><option value="3"><option value="5"><option value="12"><option value="18"><option value="28">
</datalist>
<input type="hidden" name="gst_breakdown_json" id="gst_breakdown_json" value="<?= isset($d['gst_breakdown']) ? e(json_encode($d['gst_breakdown'])) : '' ?>">

    <div class="d-flex gap-2 mt-2">
      <button type="button" class="btn btn-sm btn-outline-danger btn-add-row" id="addItemBtn">
        <i class="fa fa-plus me-1"></i>Add Item Row
      </button>
      <small class="text-muted d-flex align-items-center gap-1"><i class="fa fa-circle-info"></i> Click <strong>+Spec</strong> to add item specifications (shown on document)</small>
    </div>

    <!-- ── Turnkey Excluded / Not Added Items Tray ── -->
    <div id="turnkeyExcludedSection" class="mt-3 p-3 rounded border" style="display:none; background:#f8fafc; border-color:#e2e8f0 !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-warning text-dark px-2 py-1"><i class="fa fa-boxes-packing me-1"></i>Not Added Items</span>
          <span class="fw-bold text-dark" style="font-size:8.5pt;">Excluded Turnkey Items (<span id="turnkeyExcludedCount">0</span>)</span>
        </div>
        <small class="text-muted" style="font-size:7.5pt;"><i class="fa fa-hand-pointer text-primary me-1"></i>Click on any excluded item below to restore it back into your estimation at any time</small>
      </div>
      <div id="turnkeyExcludedList" class="d-flex flex-wrap gap-2">
        <!-- Dynamically rendered excluded item cards with + Restore button -->
      </div>
    </div>
  </div>

  <!-- ── SCOPE OF WORK (2nd Page) ──────────────────────────────────────── -->
  <?php
    $scopeItems = $d['scope_items'] ?? [];
    if ($estSubtype === 'turnkey_court' && empty($scopeItems)) {
        require_once __DIR__ . '/includes/turnkey_calculator.php';
        $tkLength    = (float)($d['turnkey_config']['length'] ?? 90);
        $tkWidth     = (float)($d['turnkey_config']['width'] ?? 60);
        $tkHeight    = (float)($d['turnkey_config']['height'] ?? 24);
        $tkSport     = $d['turnkey_config']['sport_type'] ?? 'box_cricket';
        $tkRoof      = $d['turnkey_config']['roof_type'] ?? 'light_cable_top';
        $tkPkg       = $d['turnkey_config']['package'] ?? 'commercial';
        $tkPoleStyle = $d['turnkey_config']['pole_style'] ?? 'ladder_2line';
        $tkPipeSize  = $d['turnkey_config']['pipe_size'] ?? '40x40';
        $tkPipeGauge = $d['turnkey_config']['pipe_gauge'] ?? '2.0mm';
        $tkTurfType  = $d['turnkey_config']['turf_type'] ?? 'infill_turf';
        $tkCivilBed  = $d['turnkey_config']['civil_bed_type'] ?? 'compacted_wmm';
        $initSpecs   = calculateTurnkeyCourtSpecs($tkLength, $tkWidth, $tkHeight, $tkSport, $tkRoof, $tkPoleStyle, $tkPipeSize, $tkPipeGauge, $tkTurfType, $tkCivilBed);
        $scopeItems  = generateTurnkeyScopeItems($initSpecs, $tkPkg);
    }
    $showScope  = in_array($estSubtype, ['turnkey_court','supply_installation','cricket_net','sports_net']) || !empty($scopeItems);
  ?>
  <div class="form-section" id="scopeSection" <?= !$showScope ? 'style="display:none;"' : '' ?>>
    <h5><i class="fa fa-list-check"></i> Scope of Work
      <span class="ms-2 badge bg-secondary fw-normal" style="font-size:.7rem;vertical-align:middle;">2nd page on printed document</span>
    </h5>
    <div class="small text-muted mb-2"><i class="fa fa-circle-info me-1 text-info"></i>Each item below will appear as a numbered row in the Scope of Work table on page 2 of the estimation.</div>
    <div id="scopeList">
    <?php foreach ($scopeItems as $si => $sitem): ?>
      <div class="scope-item border rounded p-3 mb-3 bg-light" data-scope="<?= $si ?>">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-bold text-danger" style="font-size:.82rem;">ITEM <?= $si+1 ?></span>
          <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-scope-item"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="mb-2">
          <label class="form-label">Title <span class="text-muted fw-normal">(shown bold/underlined)</span></label>
          <input type="text" name="scope_title[]" class="form-control form-control-sm" value="<?= e($sitem['title'] ?? '') ?>" placeholder="e.g. Base work">
        </div>
        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea name="scope_desc[]" class="form-control form-control-sm" rows="3" placeholder="Detailed description of this scope item..."><?= e($sitem['description'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="form-label">Please Note <span class="text-muted fw-normal">(optional — shown in bold)</span></label>
          <textarea name="scope_note[]" class="form-control form-control-sm" rows="2" placeholder="Optional note, e.g. Materials to be arranged by client..."><?= e($sitem['note'] ?? '') ?></textarea>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-add-row" id="addScopeBtn">
      <i class="fa fa-plus me-1"></i>Add Scope Item
    </button>
  </div>

  <!-- Terms -->
  <div class="form-section">
    <h5><i class="fa fa-file-contract"></i> Terms & Conditions</h5>
    <div id="termsList">
    <?php foreach ($terms as $ti => $term): ?>
      <div class="d-flex align-items-center gap-2 mb-2 term-row">
        <input type="text" name="terms[]" class="form-control form-control-sm" value="<?= e($term) ?>">
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-term"><i class="fa fa-minus"></i></button>
      </div>
    <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="addTermBtn">
      <i class="fa fa-plus me-1"></i>Add Term
    </button>
    <div class="mt-2">
      <label class="form-label">Notes / Remarks</label>
      <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes on this document…"><?= e($d['notes'] ?? '') ?></textarea>
    </div>
  </div>

</div><!-- /col-lg-8 -->

<!-- RIGHT COLUMN -->
<div class="col-lg-4">

  <!-- Totals -->
  <div class="form-section sticky-top" style="top:70px;">
    <h5><i class="fa fa-calculator"></i> Totals</h5>
    <table class="table table-sm totals-table mb-2">
      <tbody>
        <tr>
          <td class="text-muted">Subtotal</td>
          <td class="text-end fw-semibold" id="disp_subtotal">₹0.00</td>
          <input type="hidden" name="subtotal" id="h_subtotal" value="0">
        </tr>
        <tr>
          <td class="text-muted">
            <span id="lbl_discount">Discount</span>
            <span class="ms-1">
              <button type="button" id="btn_disc_pct" onclick="setDiscType('percent')"
                class="btn btn-xs px-1 py-0" style="font-size:.7rem;border:1px solid #aaa;">%</button>
              <button type="button" id="btn_disc_flat" onclick="setDiscType('amount')"
                class="btn btn-xs px-1 py-0" style="font-size:.7rem;border:1px solid #aaa;">₹</button>
            </span>
          </td>
          <td class="text-end">
            <input type="hidden" name="discount_type" id="inp_disc_type" value="<?= e($d['discount_type'] ?? 'percent') ?>">
            <input type="hidden" name="discount_percent" id="h_disc_pct" value="0">
            <input type="hidden" name="discount_flat"    id="h_disc_flat" value="0">
            <input type="number" id="inp_discount" class="form-control form-control-sm text-end" style="width:90px;margin-left:auto;"
              value="<?= e($d['discount_type'] ?? 'percent') === 'amount' ? e($d['discount_flat'] ?? 0) : e($d['discount_percent'] ?? 0) ?>"
              step="0.01" min="0">
          </td>
        </tr>
        <tr>
          <td class="text-muted" id="lbl_disc_result">Discount Amt</td>
          <td class="text-end text-danger" id="disp_discount_amt">₹0.00</td>
        </tr>
        <tr class="table-info">
          <td class="fw-semibold">Taxable Value</td>
          <td class="text-end fw-bold" id="disp_taxable">₹0.00</td>
          <input type="hidden" name="taxable_value" id="h_taxable" value="0">
        </tr>
      </tbody>
    </table>

    <!-- GST Section (hidden for Non-GST docs) -->
    <div id="gstSection" <?= !$isGst ? 'style="display:none;"' : '' ?>>
      <div class="mb-2">
        <label class="form-label">GST Type <span class="text-muted fw-normal" style="font-size:.75rem;">(Intra/Inter state)</span></label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="gst_mode" id="gm_cs" value="cgst_sgst" <?= ($gstMode==='cgst_sgst'||$gstMode==='')?'checked':'' ?> onchange="calcTotals()">
          <label class="btn btn-sm btn-outline-secondary" for="gm_cs">CGST + SGST</label>
          <input type="radio" class="btn-check" name="gst_mode" id="gm_ig" value="igst" <?= $gstMode==='igst'?'checked':'' ?> onchange="calcTotals()">
          <label class="btn btn-sm btn-outline-secondary" for="gm_ig">IGST</label>
        </div>
      </div>
      <div class="small text-muted mb-1" style="font-size:.72rem;"><i class="fa fa-circle-info me-1 text-info"></i>Set GST% per item row in the items table above</div>
      <!-- Dynamic per-slab GST breakdown (rendered by calcTotals JS) -->
      <table class="table table-sm totals-table mb-1" id="gstSlabTable">
        <tbody id="gstBreakdownDisplay"></tbody>
      </table>
      <!-- Hidden legacy rate fields kept for backward compat -->
      <input type="hidden" name="cgst_rate" id="inp_cgst" value="<?= e($d['cgst_rate'] ?? 2.5) ?>">
      <input type="hidden" name="sgst_rate" id="inp_sgst" value="<?= e($d['sgst_rate'] ?? 2.5) ?>">
      <input type="hidden" name="igst_rate" id="inp_igst" value="<?= e($d['igst_rate'] ?? 5) ?>">
    </div>
    <!-- Hidden gst_mode when Non-GST (always send 'none') -->
    <input type="hidden" id="h_gst_mode_none" name="gst_mode_override" value="<?= !$isGst ? 'none' : '' ?>">

    <table class="table table-sm totals-table">
      <tbody>
        <tr class="grand">
          <td class="py-2 px-3 fs-6">GRAND TOTAL</td>
          <td class="text-end py-2 px-3 fs-6" id="disp_grand">₹0.00</td>
          <input type="hidden" name="grand_total" id="h_grand" value="0">
        </tr>
      </tbody>
    </table>

    <!-- Amount in Words -->
    <div style="margin-bottom:.75rem;">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#c0392b;margin-bottom:3px;"><i class="fa fa-spell-check me-1"></i>Amount in Words</div>
      <div id="amountWords" style="font-style:italic;font-size:.85rem;color:#555;background:#f8f9fc;padding:.5rem .75rem;border-radius:6px;border-left:3px solid var(--red);">Will appear automatically…</div>
    </div>

    <!-- Signature / Stamp / Bank -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Print Options</label>
      <?php
        $sig   = $selCompany['signature'] ?? $settings['signature'] ?? null;
        $stamp = $selCompany['stamp']     ?? $settings['stamp']     ?? null;
        $hasBankDetails = !empty($selCompany['bank_name']) || !empty($selCompany['bank_account']);
      ?>
      <div class="d-flex gap-3 flex-wrap align-items-center">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_signature" value="1" id="chk_sig" <?= ($d['show_signature'] ?? true)?'checked':'' ?>>
          <label class="form-check-label small" for="chk_sig">
            <?php if ($sig): ?>
            <img src="<?= e(preg_replace('/\?.*$/','',$sig)) ?>?v=<?= time() ?>" alt="sig" style="height:28px;"> Signature
            <?php else: ?>
            <span class="text-muted">Signature <a href="companies.php" class="ms-1">(upload in Companies)</a></span>
            <?php endif; ?>
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_stamp" value="1" id="chk_stamp" <?= ($d['show_stamp'] ?? true)?'checked':'' ?>>
          <label class="form-check-label small" for="chk_stamp">
            <?php if ($stamp): ?>
            <img src="<?= e(preg_replace('/\?.*$/','',$stamp)) ?>?v=<?= time() ?>" alt="stamp" style="height:28px;"> Stamp
            <?php else: ?>
            <span class="text-muted">Stamp <a href="companies.php" class="ms-1">(upload in Companies)</a></span>
            <?php endif; ?>
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_bank" value="1" id="chk_bank"
            <?= ($d['show_bank'] ?? true) ? 'checked' : '' ?>>  
          <label class="form-check-label small" for="chk_bank">
            <?php if ($hasBankDetails): ?>
            <i class="fa fa-landmark text-primary me-1"></i><strong>Bank Details</strong> on document
            <?php else: ?>
            <span class="text-muted"><i class="fa fa-landmark me-1"></i>Bank Details <a href="companies.php?edit=<?= urlencode($selCompany['id'] ?? '') ?>" class="ms-1">(add in Companies)</a></span>
            <?php endif; ?>
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="show_russea" value="1" id="chk_russea"
            <?= ($d['show_russea'] ?? ($defaults['show_russea'] ?? true)) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="chk_russea">
            <i class="fa fa-tag text-danger me-1"></i><strong>Russea™ Brand</strong> (Authorized Dealer badge &amp; strip)
          </label>
        </div>
      </div>
    </div>

    <!-- Payment & Advance Status Option Box (Applicable on Tax Invoices & Bills) -->
    <div id="paymentStatusCard" class="card border-0 shadow-sm mb-3" style="display:<?= in_array($type ?? 'estimation', ['tax_invoice', 'bill', 'proforma']) ? 'block' : 'none' ?>;background:#f8fafc;border:1px solid #cbd5e1 !important;border-radius:10px;">
      <div class="card-body p-3">
        <label class="form-label fw-bold d-flex align-items-center justify-content-between mb-2">
          <span><i class="fa fa-credit-card text-danger me-1"></i> Payment &amp; Advance Status</span>
          <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem;">Select Option</span>
        </label>
        
        <?php 
        $currPayStatus = $d['payment_status'] ?? (($d['status'] ?? '') === 'paid' ? 'paid' : 'unpaid');
        $currAdvAmt = (float)($d['advance_amount'] ?? 0);
        $currPayMode = $d['payment_mode'] ?? '';
        ?>
        <div class="btn-group w-100 mb-2" role="group">
          <input type="radio" class="btn-check" name="payment_status" id="pay_unpaid" value="unpaid" <?= $currPayStatus==='unpaid'?'checked':'' ?> onchange="onPaymentStatusChange()">
          <label class="btn btn-outline-danger btn-sm fw-bold" for="pay_unpaid">⏳ Unpaid</label>
          
          <input type="radio" class="btn-check" name="payment_status" id="pay_partial" value="partial" <?= $currPayStatus==='partial'?'checked':'' ?> onchange="onPaymentStatusChange()">
          <label class="btn btn-outline-warning btn-sm fw-bold text-dark" for="pay_partial">🟡 Advance</label>
          
          <input type="radio" class="btn-check" name="payment_status" id="pay_paid" value="paid" <?= $currPayStatus==='paid'?'checked':'' ?> onchange="onPaymentStatusChange()">
          <label class="btn btn-outline-success btn-sm fw-bold" for="pay_paid">✓ Paid</label>
        </div>

        <div id="advanceDetailsBox" style="display:<?= $currPayStatus==='partial'?'block':'none' ?>;background:#fff;border:1.5px solid #f59e0b;border-radius:8px;padding:10px;margin-top:8px;">
          <div class="mb-2">
            <label class="form-label small fw-bold text-dark mb-1">Advance / Paid Amount (₹)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light fw-bold">₹</span>
              <input type="number" step="any" min="0" name="advance_amount" id="advanceAmountInput" class="form-control fw-bold text-success" value="<?= $currAdvAmt > 0 ? $currAdvAmt : '' ?>" placeholder="e.g. 50000" oninput="calcTotals()">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small text-muted mb-1">Payment Mode / Remarks (Optional)</label>
            <input type="text" name="payment_mode" class="form-control form-control-sm" value="<?= e($currPayMode) ?>" placeholder="e.g. UPI / Bank Transfer / Advance Cheque">
          </div>
          <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="small fw-bold text-danger">Remaining Balance Due:</span>
            <span class="fw-bold text-danger" id="liveBalanceDueDisplay">₹0.00</span>
          </div>
        </div>

        <div id="unpaidNoticeBox" style="display:<?= $currPayStatus==='unpaid'?'block':'none' ?>;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:6px 10px;margin-top:6px;font-size:0.75rem;color:#991b1b;">
          <i class="fa fa-info-circle me-1"></i> Providing before payment. Full grand total remains pending.
        </div>
        <div id="paidNoticeBox" style="display:<?= $currPayStatus==='paid'?'block':'none' ?>;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:6px 10px;margin-top:6px;font-size:0.75rem;color:#166534;">
          <i class="fa fa-check-circle me-1"></i> Full payment received (100% Paid). Balance due is NIL.
        </div>
      </div>
    </div>

    <!-- Status -->
    <div class="mb-3">
      <label class="form-label">Workflow Status</label>
      <select name="status" class="form-select form-select-sm">
        <?php foreach (['draft','sent','accepted','rejected','paid'] as $s): ?>
        <option value="<?= $s ?>" <?= ($d['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="d-grid gap-2">
      <!-- Auto-save status bar -->
      <div id="autosaveBar" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.5rem 0.75rem;display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;color:#166534;display:none;">
        <span id="autosaveIcon"><i class="fa fa-circle-notch fa-spin"></i></span>
        <span id="autosaveMsg">Auto-saving draft...</span>
      </div>
      <input type="hidden" name="autosave_id" id="autosaveId" value="<?= e($editId ?? '') ?>">

      <button type="submit" class="btn text-white fw-bold" style="background:var(--red);">
        <i class="fa fa-floppy-disk me-2"></i><?= $editId ? 'Update Document' : 'Save & Finalize Document' ?>
      </button>
      <?php if ($editId): ?>
      <a href="view.php?id=<?= urlencode($editId) ?>" class="btn btn-outline-secondary">
        <i class="fa fa-eye me-2"></i>View Document
      </a>
      <?php endif; ?>
      <a href="documents.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </div>

</div><!-- /col-lg-4 -->
</div><!-- /row -->
</form>

<script>
const customers = <?= json_encode(array_values($customers), JSON_HEX_TAG) ?>;

function toggleSameAsMobile() {
  const sameCheck = document.getElementById('same_as_mobile');
  const phoneIn   = document.getElementById('bt_phone');
  const waIn      = document.getElementById('bt_whatsapp');
  const waHint    = document.getElementById('waHint');
  if (!sameCheck || !waIn || !phoneIn) return;

  if (sameCheck.checked) {
    waIn.value = phoneIn.value;
    waIn.readOnly = true;
    waIn.style.backgroundColor = '#f8fafc';
    if (waHint) waHint.textContent = 'Synchronized with Mobile Number';
  } else {
    waIn.readOnly = false;
    waIn.style.backgroundColor = '#ffffff';
    if (waHint) waHint.textContent = 'Enter custom WhatsApp number';
    waIn.focus();
  }
}

function cleanPhoneDigits(str) {
  let val = (str || '').replace(/[^0-9]/g, '');
  if (val.length > 10 && val.startsWith('91')) {
    val = val.substring(2);
  }
  if (val.length > 10) {
    val = val.slice(-10);
  }
  return val;
}

function fillCustomer(id) {
  const el = document.getElementById('custSelect');
  const opt = el.options[el.selectedIndex];
  if (!id) return;
  document.getElementById('bt_name').value    = opt.dataset.name    || '';
  document.getElementById('bt_address').value = opt.dataset.address || '';
  document.getElementById('bt_gstin').value   = opt.dataset.gstin   || '';
  document.getElementById('bt_state').value   = opt.dataset.state   || '';

  const phoneIn   = document.getElementById('bt_phone');
  const waIn      = document.getElementById('bt_whatsapp');
  const sameCheck = document.getElementById('same_as_mobile');
  const waHint    = document.getElementById('waHint');
  const custPhone = opt.dataset.phone || '';
  const custWa    = opt.dataset.whatsapp || '';

  if (phoneIn) phoneIn.value = custPhone;
  if (sameCheck && waIn) {
    if (custWa && custPhone && custWa !== custPhone) {
      sameCheck.checked = false;
      waIn.value = custWa;
      waIn.readOnly = false;
      waIn.style.backgroundColor = '#ffffff';
      if (waHint) waHint.textContent = 'Enter custom WhatsApp number';
    } else {
      sameCheck.checked = true;
      waIn.value = custPhone;
      waIn.readOnly = true;
      waIn.style.backgroundColor = '#f8fafc';
      if (waHint) waHint.textContent = 'Synchronized with Mobile Number';
    }
  }

  document.getElementById('customer_id').value = id;
}

let _uidCounter = 100;
function getNextItemUid() {
  return 'it_' + Date.now() + '_' + (++_uidCounter);
}

function reindexRows() {
  let curIdx = 0;
  document.querySelectorAll('#itemsTbody .item-row').forEach(row => {
    let uid = row.dataset.itemUid;
    if (!uid) {
      uid = getNextItemUid();
      row.dataset.itemUid = uid;
    }
    row.dataset.row = curIdx;

    const desc = row.querySelector('textarea[name^="item_desc"]');
    if (desc) desc.name = `item_desc[${curIdx}]`;
    const hsn = row.querySelector('input[name^="item_hsn"]');
    if (hsn) hsn.name = `item_hsn[${curIdx}]`;
    const qty = row.querySelector('input[name^="item_qty"]');
    if (qty) qty.name = `item_qty[${curIdx}]`;
    const unit = row.querySelector('select[name^="item_unit"]');
    if (unit) unit.name = `item_unit[${curIdx}]`;
    const rate = row.querySelector('input[name^="item_rate"]');
    if (rate) rate.name = `item_rate[${curIdx}]`;
    const gstRate = row.querySelector('input[name^="item_gst_rate"]');
    if (gstRate) gstRate.name = `item_gst_rate[${curIdx}]`;
    const amount = row.querySelector('input[name^="item_amount"]');
    if (amount) amount.name = `item_amount[${curIdx}]`;

    // Strictly find and update specs belonging to THIS item UID
    document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`).forEach(specRow => {
      specRow.dataset.parent = curIdx;
      const specInput = specRow.querySelector('.spec-input');
      if (specInput) specInput.name = `item_specs[${curIdx}][]`;
    });

    curIdx++;
  });
  rowIdx = curIdx;
}

// ── Calculations ──────────────────────────────────────────────────────────────
function fmtNum(n) {
  return '₹' + parseFloat(n).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function calcTotals() {
  let subtotal = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const qty  = parseFloat(row.querySelector('.item-qty')?.value  || 0);
    const rate = parseFloat(row.querySelector('.item-rate')?.value || 0);
    const amt  = Math.round(qty * rate * 100) / 100;
    const span = row.querySelector('.item-amount');
    const hid  = row.querySelector('.item-amount-val');
    if (span) span.textContent = fmtNum(amt);
    if (hid)  hid.value = amt;
    subtotal += amt;
  });

  const discType = document.getElementById('inp_disc_type').value;
  const discVal  = parseFloat(document.getElementById('inp_discount').value || 0);
  let discAmt, discPct;
  if (discType === 'amount') {
    discAmt = Math.min(Math.round(discVal * 100) / 100, subtotal);
    discPct = subtotal > 0 ? Math.round(discAmt / subtotal * 10000) / 100 : 0;
  } else {
    discPct = discVal;
    discAmt = Math.round(subtotal * discPct / 100 * 100) / 100;
  }
  const taxable = Math.round((subtotal - discAmt) * 100) / 100;

  document.getElementById('h_disc_pct').value  = discPct;
  document.getElementById('h_disc_flat').value = discType === 'amount' ? discVal : discAmt;
  document.getElementById('disp_subtotal').textContent     = fmtNum(subtotal);
  document.getElementById('disp_discount_amt').textContent = discAmt > 0 ? '\u2212' + fmtNum(discAmt) : '0.00';
  document.getElementById('lbl_disc_result').textContent   = discType === 'amount' ? 'Discount ('+discPct.toFixed(2)+'%)' : 'Discount Amt';
  document.getElementById('disp_taxable').textContent      = fmtNum(taxable);
  document.getElementById('h_subtotal').value = subtotal;
  document.getElementById('h_taxable').value  = taxable;

  const withGST = document.querySelector('[name=is_gst]:checked')?.value === '1';
  let totalGST = 0;

  if (withGST) {
    const mode = document.querySelector('[name=gst_mode]:checked')?.value || 'cgst_sgst';
    const discFactor = subtotal > 0 ? taxable / subtotal : 1;

    // Build per-item GST slab map
    const slabMap = {};
    document.querySelectorAll('.item-row').forEach(row => {
      const amtVal = parseFloat(row.querySelector('.item-amount-val')?.value || 0);
      const gstR   = parseFloat(row.querySelector('.item-gst-rate')?.value  || 0);
      const slabAmt = Math.round(amtVal * discFactor * 100) / 100;
      if (slabAmt === 0 && gstR === 0) return;
      const key = String(gstR);
      if (!slabMap[key]) slabMap[key] = 0;
      slabMap[key] += slabAmt;
    });

    // Compute tax per slab, build breakdown array
    const breakdown = Object.entries(slabMap)
      .map(([r, taxableAmt]) => {
        const rate    = parseFloat(r);
        const gstAmt  = Math.round(taxableAmt * rate / 100 * 100) / 100;
        totalGST     += gstAmt;
        return { rate, taxableAmt: Math.round(taxableAmt * 100) / 100, gstAmt };
      })
      .sort((a, b) => a.rate - b.rate);

    // Store for PHP submission
    document.getElementById('gst_breakdown_json').value = JSON.stringify(breakdown);

    // Render slab breakdown in totals panel
    const tbody = document.getElementById('gstBreakdownDisplay');
    if (tbody) {
      let html = '';
      breakdown.forEach(({rate, taxableAmt, gstAmt}) => {
        if (rate === 0) {
          html += '<tr><td colspan="2" class="text-muted" style="font-size:.72rem;padding:.15rem .5rem;">GST 0% (Exempt) \u2014 \u20b9' + fmtNum(taxableAmt) + '</td></tr>';
          return;
        }
        html += '<tr><td colspan="2" style="font-size:.7rem;color:#888;padding:.1rem .5rem 0;">@ ' + rate + '% on \u20b9' + fmtNum(taxableAmt) + '</td></tr>';
        if (mode === 'cgst_sgst') {
          const halfR   = rate / 2;
          const halfAmt = Math.round(gstAmt / 2 * 100) / 100;
          html += '<tr><td class="text-muted" style="padding:.1rem .5rem;">CGST ' + halfR + '%</td><td class="text-end fw-semibold" style="padding:.1rem .5rem;">' + fmtNum(halfAmt) + '</td></tr>';
          html += '<tr><td class="text-muted" style="padding:.1rem .5rem;">SGST ' + halfR + '%</td><td class="text-end fw-semibold" style="padding:.1rem .5rem;">' + fmtNum(halfAmt) + '</td></tr>';
        } else {
          html += '<tr><td class="text-muted" style="padding:.1rem .5rem;">IGST ' + rate + '%</td><td class="text-end fw-semibold" style="padding:.1rem .5rem;">' + fmtNum(gstAmt) + '</td></tr>';
        }
      });
      tbody.innerHTML = html || '<tr><td colspan="2" class="text-muted small" style="font-size:.72rem;">Add GST% to item rows above</td></tr>';
    }
  } else {
    const bd = document.getElementById('gst_breakdown_json');
    if (bd) bd.value = '[]';
    const tbody = document.getElementById('gstBreakdownDisplay');
    if (tbody) tbody.innerHTML = '';
  }

  const grand = Math.round((taxable + totalGST) * 100) / 100;
  document.getElementById('disp_grand').textContent = fmtNum(grand);
  document.getElementById('h_grand').value           = grand;
  document.getElementById('amountWords').textContent = numberToWords(grand);

  // Update Live Balance Due based on Payment Status & Advance Amount
  const pStatus = document.querySelector('input[name="payment_status"]:checked')?.value || 'unpaid';
  const advInput = document.getElementById('advanceAmountInput');
  const advAmt = parseFloat(advInput?.value || 0);
  const balDueEl = document.getElementById('liveBalanceDueDisplay');
  if (balDueEl) {
    if (pStatus === 'paid') {
      balDueEl.textContent = '₹0.00 (NIL)';
      balDueEl.className = 'fw-bold text-success';
    } else if (pStatus === 'unpaid') {
      balDueEl.textContent = fmtNum(grand);
      balDueEl.className = 'fw-bold text-danger';
    } else {
      const bal = Math.max(0, grand - advAmt);
      balDueEl.textContent = fmtNum(bal);
      balDueEl.className = 'fw-bold text-danger';
    }
  }

  if (typeof window.scheduleAutosave === 'function') {
    window.scheduleAutosave();
  }
}

// ── GST Toggle ────────────────────────────────────────────────────────────────
function toggleGST() {
  const withGST = document.querySelector('[name=is_gst]:checked')?.value === '1';
  const sec = document.getElementById('gstSection');
  if (sec) sec.style.display = withGST ? '' : 'none';
  // Default to cgst_sgst when turning GST on
  if (withGST && !document.querySelector('[name=gst_mode]:checked')) {
    document.getElementById('gm_cs').checked = true;
  }
  refreshDocLabel();
  calcTotals();
}

// ── Live doc-type label ────────────────────────────────────────────────────────
const docLabels = {
  gst:    { estimation:'GST ESTIMATION', tax_invoice:'TAX INVOICE', bill:'GST BILL' },
  nongst: { estimation:'ESTIMATION',     tax_invoice:'INVOICE',     bill:'BILL'     },
};
function refreshDocLabel() {
  const type    = document.querySelector('[name=type]:checked')?.value  || 'estimation';
  const withGST = document.querySelector('[name=is_gst]:checked')?.value === '1';
  const lbl     = withGST ? (docLabels.gst[type] || 'DOCUMENT') : (docLabels.nongst[type] || 'DOCUMENT');
  const el = document.getElementById('docTypePreview');
  if (el) el.textContent = lbl;

  const payCard = document.getElementById('paymentStatusCard');
  if (payCard) {
    payCard.style.display = (type === 'tax_invoice' || type === 'bill' || type === 'proforma') ? 'block' : 'none';
  }
}

// ── Number to Words (Indian) ──────────────────────────────────────────────────
function numberToWords(amount) {
  amount = Math.round(parseFloat(amount) * 100) / 100;
  const integer = Math.floor(amount);
  const decimal = Math.round((amount - integer) * 100);
  let words = integer === 0 ? 'Zero' : intToWords(integer);
  let result = 'Rupees ' + words;
  if (decimal > 0) result += ' and ' + intToWords(decimal) + ' Paise';
  return result + ' Only';
}

function intToWords(n) {
  if (n === 0) return '';
  const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
    'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  if (n < 20) return ones[n];
  if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? ' '+ones[n%10] : '');
  if (n < 1000) return ones[Math.floor(n/100)] + ' Hundred' + (n%100 ? ' '+intToWords(n%100) : '');
  if (n < 100000) return intToWords(Math.floor(n/1000)) + ' Thousand' + (n%1000 ? ' '+intToWords(n%1000) : '');
  if (n < 10000000) return intToWords(Math.floor(n/100000)) + ' Lakh' + (n%100000 ? ' '+intToWords(n%100000) : '');
  return intToWords(Math.floor(n/10000000)) + ' Crore' + (n%10000000 ? ' '+intToWords(n%10000000) : '');
}

// ── Dynamic Rows ─────────────────────────────────────────────────────────────
let rowIdx = <?= count($items) ?>;

function getDefaultUnit() {
  const mode = document.querySelector('input[name="sale_unit_mode"]:checked')?.value || 'sqft';
  return mode === 'kg' ? 'KG' : 'SQFT';
}

const AVAILABLE_UNITS = ['SQFT','SQMT','RFT','MTR','MTRS','NOS','PCS','KG','KGS','LTR','LTRS','LOT','LOTS','SET','SETS','JOB','BOX','BOXES','ROLL','ROLLS','BAG','BAGS','TON','TONS','PKT'];

function getUnitOptions(selectedUnit) {
  const normSelected = (selectedUnit || '').trim();
  let list = [...AVAILABLE_UNITS];
  if (normSelected && !list.some(u => u.toLowerCase() === normSelected.toLowerCase())) {
    list.push(normSelected.toUpperCase());
  }
  return list.map(u => {
    const sel = (normSelected.toLowerCase() === u.toLowerCase()) ? ' selected' : '';
    return `<option value="${u}"${sel}>${u}</option>`;
  }).join('');
}

function getRowHtml(idx, uid = null) {
  const itemUid = uid || getNextItemUid();
  const defUnit = getDefaultUnit();
  const opts = getUnitOptions(defUnit);
  const defaultGstRate = parseFloat(document.querySelector('.item-gst-rate')?.value || '5') || 5;
  return `<tr class="item-row" data-item-uid="${itemUid}" data-row="${idx}">
    <td><textarea name="item_desc[${idx}]" class="form-control form-control-sm" rows="2" placeholder="Description" required></textarea></td>
    <td><input type="text" name="item_hsn[${idx}]" class="form-control form-control-sm item-hsn" placeholder="HSN"></td>
    <td><input type="number" name="item_qty[${idx}]" class="form-control form-control-sm item-qty" step="0.01" min="0" placeholder="0"></td>
    <td><select name="item_unit[${idx}]" class="form-select form-select-sm item-unit-select">${opts}</select></td>
    <td><input type="number" name="item_rate[${idx}]" class="form-control form-control-sm item-rate" step="0.01" min="0" placeholder="0.00"></td>
    <td><input type="number" name="item_gst_rate[${idx}]" class="form-control form-control-sm item-gst-rate text-end" value="${defaultGstRate}" step="0.5" min="0" max="100" list="gstRateList" style="min-width:75px;padding:.3rem .4rem;font-size:0.85rem;" title="GST%"></td>
    <td class="amount-col"><span class="item-amount">₹0.00</span><input type="hidden" name="item_amount[${idx}]" class="item-amount-val" value="0"></td>
    <td style="white-space:nowrap;">
      <div class="d-flex flex-column gap-1">
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-row" title="Remove row"><i class="fa fa-xmark"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 add-spec-btn" title="Add spec">+Spec</button>
      </div>
    </td>
  </tr>`;
}

function onCustTypeChange() {
  const type = document.querySelector('input[name="customer_type"]:checked')?.value;
  const hint = document.getElementById('saleUnitHint');
  if (type === 'dealer') {
    document.getElementById('su_kg').checked = true;
    if (hint) hint.innerHTML = '<span class="text-warning fw-bold"><i class="fa fa-handshake me-1"></i>Dealer — default unit set to KG</span>';
  } else {
    document.getElementById('su_sqft').checked = true;
    if (hint) hint.innerHTML = '<span class="text-primary fw-bold"><i class="fa fa-user me-1"></i>Client — default unit set to SQFT</span>';
  }
}

function getSpecHtml(parentUid, parentIdx, specValue = '', isAuto = false) {
  const autoAttr = isAuto ? ' data-auto="1"' : '';
  return `<tr class="spec-row" data-parent-uid="${parentUid}" data-parent="${parentIdx}"${autoAttr}>
    <td colspan="6" style="padding:2px 8px 2px 28px;">
      <div class="d-flex align-items-center gap-1">
        <span class="text-muted" style="font-size:.75rem;">↳</span>
        <input type="text" name="item_specs[${parentIdx}][]" class="form-control form-control-sm spec-input" value="${escapeHtml(specValue)}" placeholder="e.g. Thickness : 1.5mm">
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-spec" title="Remove spec"><i class="fa fa-minus"></i></button>
      </div>
    </td><td></td>
  </tr>`;
}

document.getElementById('addItemBtn').addEventListener('click', () => {
  const tbody = document.getElementById('itemsTbody');
  tbody.insertAdjacentHTML('beforeend', getRowHtml(rowIdx++));
  reindexRows();
  calcTotals();
});

document.getElementById('itemsTbody').addEventListener('click', e => {
  const btn = e.target.closest('button');
  if (!btn) return;

  if (btn.classList.contains('remove-row')) {
    const row = btn.closest('.item-row');
    const uid = row.dataset.itemUid;
    const idx = row.dataset.row;

    // Capture item data before removing
    const desc = row.querySelector('textarea[name^="item_desc"]')?.value || '';
    const hsn = row.querySelector('input[name^="item_hsn"]')?.value || '';
    const qty = row.querySelector('input[name^="item_qty"]')?.value || '1';
    const unit = row.querySelector('select[name^="item_unit"]')?.value || 'SQFT';
    const rate = row.querySelector('input[name^="item_rate"]')?.value || '0';
    const gstRate = row.querySelector('input[name^="item_gst_rate"]')?.value || '18';
    const amountVal = row.querySelector('input[name^="item_amount"]')?.value || (parseFloat(qty||0)*parseFloat(rate||0)).toString();

    const specs = [];
    document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"] .spec-input`).forEach(s => {
      if (s.value.trim()) specs.push(s.value.trim());
    });

    if (desc.trim()) {
      addTurnkeyExcludedItem({
        description: desc,
        hsn: hsn,
        qty: qty,
        unit: unit,
        rate: rate,
        gst_rate: gstRate,
        amount: amountVal,
        specs: specs
      });
    }

    // Strictly remove ONLY spec rows belonging to THIS item UID
    document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`).forEach(s => s.remove());
    row.remove();
    reindexRows();
    calcTotals();
  }
  if (btn.classList.contains('add-spec-btn')) {
    const row = btn.closest('.item-row');
    const uid = row.dataset.itemUid;
    const idx = row.dataset.row;
    // Insert spec row after last spec for this item UID, or after item row
    const specs = document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`);
    const insertAfter = specs.length > 0 ? specs[specs.length - 1] : row;
    insertAfter.insertAdjacentHTML('afterend', getSpecHtml(uid, idx));
    reindexRows();
  }
  if (btn.classList.contains('remove-spec')) {
    const specRow = btn.closest('.spec-row');
    const uid = specRow.dataset.parentUid;
    const specVal = specRow.querySelector('.spec-input')?.value?.trim();
    if (specVal && uid) {
      window.removedItemSpecs = window.removedItemSpecs || {};
      window.removedItemSpecs[uid] = window.removedItemSpecs[uid] || [];
      if (!window.removedItemSpecs[uid].includes(specVal)) {
        window.removedItemSpecs[uid].push(specVal);
      }
    }
    specRow.remove();
    reindexRows();
    if (uid) updateRemovedSpecsTray(uid);
  }
});

document.getElementById('itemsTbody').addEventListener('input', e => {
  if (e.target.classList.contains('item-qty') || 
      e.target.classList.contains('item-rate') || 
      e.target.classList.contains('item-gst-rate')) {
    calcTotals();
  }
  if (e.target.name === 'item_desc[]') {
    clearTimeout(e.target._specTimer);
    e.target._specTimer = setTimeout(() => autoAddSpecs(e.target), 600);
  }
});

document.getElementById('itemsTbody').addEventListener('change', e => {
  if (e.target.classList.contains('item-qty') || 
      e.target.classList.contains('item-rate') || 
      e.target.classList.contains('item-gst-rate') ||
      e.target.name === 'item_unit[]') {
    calcTotals();
  }
});



// ── Removed Specs Tray Helper ────────────────────────────────────────────────
window.removedItemSpecs = {};

function updateRemovedSpecsTray(uid) {
  const existingTray = document.querySelector('.spec-removed-tray[data-parent-uid="' + uid + '"]');
  if (existingTray) existingTray.remove();

  const list = window.removedItemSpecs && window.removedItemSpecs[uid] ? window.removedItemSpecs[uid] : [];
  if (!list || list.length === 0) return;

  const itemRow = document.querySelector('.item-row[data-item-uid="' + uid + '"]');
  if (!itemRow) return;

  const specs = document.querySelectorAll('#itemsTbody .spec-row[data-parent-uid="' + uid + '"]');
  const insertAfter = specs.length > 0 ? specs[specs.length - 1] : itemRow;

  const pillsHtml = list.map((s, sIdx) => `
    <span class="badge bg-light text-dark border px-2 py-1 restore-spec-pill" data-uid="${uid}" data-spec-idx="${sIdx}" style="cursor:pointer;font-size:7.5pt;transition:all .15s ease;" title="Click to restore this specification" onmouseover="this.style.background='#fef3c7';this.style.borderColor='#f59e0b'" onmouseout="this.style.background='#f8fafc';this.style.borderColor='#e2e8f0'">
      <i class="fa fa-plus text-success me-1"></i>${escapeHtml(s)}
    </span>
  `).join('');

  const trayHtml = `
    <tr class="spec-removed-tray" data-parent-uid="${uid}">
      <td colspan="6" style="padding:2px 8px 4px 28px;background:#fefbf2;">
        <div class="d-flex align-items-center flex-wrap gap-1">
          <small class="text-muted fw-bold" style="font-size:7.5pt;"><i class="fa fa-clock-rotate-left me-1 text-warning"></i>Removed Specs (click to restore):</small>
          ${pillsHtml}
        </div>
      </td><td></td>
    </tr>
  `;
  insertAfter.insertAdjacentHTML('afterend', trayHtml);
}

document.getElementById('itemsTbody').addEventListener('click', e => {
  const pill = e.target.closest('.restore-spec-pill');
  if (pill) {
    const uid = pill.dataset.uid;
    const sIdx = parseInt(pill.dataset.specIdx, 10);
    const list = window.removedItemSpecs && window.removedItemSpecs[uid] ? window.removedItemSpecs[uid] : [];
    if (list[sIdx] !== undefined) {
      const restoredSpec = list[sIdx];
      list.splice(sIdx, 1);
      
      const itemRow = document.querySelector('.item-row[data-item-uid="' + uid + '"]');
      const parentIdx = itemRow ? itemRow.dataset.row : 0;
      const specs = document.querySelectorAll('#itemsTbody .spec-row[data-parent-uid="' + uid + '"]');
      const insertAfter = specs.length > 0 ? specs[specs.length - 1] : itemRow;
      insertAfter.insertAdjacentHTML('afterend', getSpecHtml(uid, parentIdx, restoredSpec));
      
      reindexRows();
      updateRemovedSpecsTray(uid);
    }
  }
});

// ── Turnkey Excluded Items Tray Management ───────────────────────────────────
let turnkeyExcludedItems = <?= json_encode($d['turnkey_config']['excluded_items'] ?? []) ?>;

function escapeHtml(text) {
  return (text || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function addTurnkeyExcludedItem(item) {
  turnkeyExcludedItems.push(item);
  renderTurnkeyExcludedItems();
}

function restoreTurnkeyExcludedItem(index) {
  if (index < 0 || index >= turnkeyExcludedItems.length) return;
  const item = turnkeyExcludedItems.splice(index, 1)[0];
  renderTurnkeyExcludedItems();

  const tbody = document.getElementById('itemsTbody');
  const newRowIdx = rowIdx++;
  const html = getRowHtmlWithData(newRowIdx, item);
  tbody.insertAdjacentHTML('beforeend', html);
  calcTotals();
  reindexRows();
}

function renderTurnkeyExcludedItems() {
  const container = document.getElementById('turnkeyExcludedSection');
  const list = document.getElementById('turnkeyExcludedList');
  const countSpan = document.getElementById('turnkeyExcludedCount');
  const hiddenInput = document.getElementById('turnkeyExcludedJson');

  if (hiddenInput) {
    hiddenInput.value = JSON.stringify(turnkeyExcludedItems);
  }

  if (!container || !list) return;

  const isTurnkey = document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court';
  if (!isTurnkey || turnkeyExcludedItems.length === 0) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'block';
  if (countSpan) countSpan.textContent = turnkeyExcludedItems.length;

  list.innerHTML = turnkeyExcludedItems.map((item, idx) => {
    const title = (item.description || 'Turnkey Item').split('\n')[0].replace(/\\n/g, ' ');
    const shortTitle = title.length > 50 ? title.substring(0, 47) + '...' : title;
    const rateTxt = item.rate ? `₹${parseFloat(item.rate).toLocaleString('en-IN')}/${item.unit}` : '';
    const amtTxt = item.amount ? `₹${parseFloat(item.amount).toLocaleString('en-IN')}` : '';
    return `
      <div class="card shadow-sm border p-2 d-flex flex-row align-items-center justify-content-between gap-2" style="background:#ffffff; border-radius:8px; min-width:280px; max-width:400px; border-color:#cbd5e1 !important;">
        <div class="overflow-hidden">
          <div class="fw-bold text-dark text-truncate" style="font-size:8pt;" title="${escapeHtml(title)}">${escapeHtml(shortTitle)}</div>
          <div class="text-muted d-flex align-items-center gap-2" style="font-size:7pt;">
            <span>${item.qty} ${item.unit}</span>
            <span>&bull;</span>
            <span class="text-primary fw-semibold">${rateTxt}</span>
            <span>&bull;</span>
            <span class="text-success fw-bold">${amtTxt}</span>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-success px-2 py-1 text-nowrap d-flex align-items-center gap-1 shadow-sm" style="font-size:7.5pt;" onclick="restoreTurnkeyExcludedItem(${idx})">
          <i class="fa fa-plus"></i> Add Back
        </button>
      </div>
    `;
  }).join('');
}

function getRowHtmlWithData(idx, item, uid = null) {
  const itemUid = uid || getNextItemUid();
  const opts = getUnitOptions(item.unit || 'SQFT');
  const specsHtml = (item.specs || []).map(s => `
    <tr class="spec-row" data-parent-uid="${itemUid}" data-parent="${idx}">
      <td colspan="6" style="padding:2px 8px 2px 28px;">
        <div class="d-flex align-items-center gap-1">
          <span class="text-muted" style="font-size:.75rem;">↳</span>
          <input type="text" name="item_specs[${idx}][]" class="form-control form-control-sm spec-input py-0" value="${escapeHtml(s)}" style="font-size:.78rem;background:#fff;" placeholder="e.g. Thickness : 1.5mm">
          <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-spec" title="Remove spec"><i class="fa fa-minus"></i></button>
        </div>
      </td><td></td>
    </tr>
  `).join('');

  const amountVal = parseFloat(item.amount || (item.qty * item.rate) || 0).toFixed(2);
  const formattedAmt = parseFloat(amountVal).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

  return `<tr class="item-row" data-item-uid="${itemUid}" data-row="${idx}">
    <td><textarea name="item_desc[${idx}]" class="form-control form-control-sm" rows="2" placeholder="Description" required>${escapeHtml(item.description||'')}</textarea></td>
    <td><input type="text" name="item_hsn[${idx}]" class="form-control form-control-sm item-hsn" value="${escapeHtml(item.hsn||'')}" placeholder="HSN"></td>
    <td><input type="number" name="item_qty[${idx}]" class="form-control form-control-sm item-qty" value="${escapeHtml(item.qty||'')}" step="0.01" min="0" placeholder="0"></td>
    <td><select name="item_unit[${idx}]" class="form-select form-select-sm item-unit-select">${opts}</select></td>
    <td><input type="number" name="item_rate[${idx}]" class="form-control form-control-sm item-rate" value="${escapeHtml(item.rate||'')}" step="0.01" min="0" placeholder="0.00"></td>
    <td><input type="number" name="item_gst_rate[${idx}]" class="form-control form-control-sm item-gst-rate text-end" value="${escapeHtml(item.gst_rate||'18')}" step="0.5" min="0" max="100" list="gstRateList" style="min-width:75px;padding:.3rem .4rem;font-size:0.85rem;" title="GST%"></td>
    <td class="amount-col"><span class="item-amount">₹${formattedAmt}</span><input type="hidden" name="item_amount[${idx}]" class="item-amount-val" value="${amountVal}"></td>
    <td style="white-space:nowrap;">
      <div class="d-flex flex-column gap-1">
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-row" title="Remove row"><i class="fa fa-xmark"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 add-spec-btn" title="Add spec">+Spec</button>
      </div>
    </td>
  </tr>` + specsHtml;
}

// Dynamic Pole & Pipe Controls
function onPoleStyleChange() {
  const pole = document.getElementById('tk_pole_style')?.value || 'ladder_2line';
  const sizeSelect = document.getElementById('tk_pipe_size');
  const gaugeSelect = document.getElementById('tk_pipe_gauge');
  if (!sizeSelect || !gaugeSelect) return;

  if (pole === 'single_pipe') {
    sizeSelect.innerHTML = `
      <option value="3x3">3" × 3" (75×75mm) Column</option>
      <option value="4x4" selected>4" × 4" (100×100mm) Heavy (Recommended)</option>
      <option value="5x5">5" × 5" (125×125mm) Super Heavy</option>
    `;
    gaugeSelect.innerHTML = `
      <option value="2.0mm">2.0mm (14 Gauge)</option>
      <option value="2.5mm" selected>2.5mm (12 Gauge - Recommended)</option>
      <option value="3.0mm">3.0mm (10 Gauge - Heavy Duty)</option>
    `;
  } else {
    sizeSelect.innerHTML = `
      <option value="32x32">32 × 32 mm (1.25" × 1.25")</option>
      <option value="40x40" selected>40 × 40 mm (1.5" × 1.5" - Recommended)</option>
      <option value="50x50">50 × 50 mm (2.0" × 2.0")</option>
    `;
    gaugeSelect.innerHTML = `
      <option value="1.5mm">1.5mm (16 Gauge)</option>
      <option value="2.0mm" selected>2.0mm (14 Gauge - Recommended)</option>
      <option value="2.5mm">2.5mm (12 Gauge - Heavy)</option>
    `;
  }
  updateTurnkeyCalculations(true);
}

function onPipeSizeChange() {
  updateTurnkeyCalculations(true);
}

// ── Auto-spec templates ───────────────────────────────────────────────────────
// Matched by simple keyword presence in description (case-insensitive)
function detectSpecTemplate(desc) {
  if (/grill/i.test(desc))                        return ['Guage/Thickness : ', 'Line Gap : ', 'Coating : '];
  if (/\bnet\b/i.test(desc))                      return ['Thickness : ', 'Square Gap : ', 'Colour : '];
  if (/grass|turf|artificial/i.test(desc))        return ['Pile Height : ', 'Layer : ', 'Colour : '];
  return null;
}

function detectHsn(desc) {
  const d = (desc || '').toLowerCase();
  if (/(freight|transport|logistics|carriage|lr\b)/i.test(d)) return '9965';
  if (/(civil|excavation|sub-base|drainage|labour|labor|installation|erection|fixing|fitting)/i.test(d)) return '9954';
  if (/(grass|turf|carpet|lawn|matting)/i.test(d)) return '5703';
  if (/(sand|silica)/i.test(d)) return '2505';
  if (/(rubber|granule|infill)/i.test(d)) return '4004';
  if (/(light|flood|lamp|fixture|lux)/i.test(d)) return '9405';
  if (/(cable|panel|switch|db|earthing|electric)/i.test(d)) return '8537';
  if (/(pipe|pole|column|purlin|gate|steel|iron|frame|structure|truss|shs|ms|gi)/i.test(d)) return '7308';
  if (/(goal|marking|pad|stump|crease|accessory|accessories)/i.test(d)) return '9506';
  if (/(paint|primer|epoxy|coating)/i.test(d)) return '3208';
  if (/(engineering|consult|supervision|design|inspection)/i.test(d)) return '9983';
  if (/(custom|stitch|seam|charge)/i.test(d)) return '5608';
  return '5608';
}
function autoAddSpecs(textarea) {
  const row = textarea.closest('.item-row');
  if (!row) return;
  const uid      = row.dataset.itemUid;
  const idx      = row.dataset.row;
  const desc     = textarea.value;
  const hsnInput = row.querySelector('input[name^="item_hsn"]');
  if (hsnInput && !hsnInput.value.trim()) { hsnInput.value = detectHsn(desc); }
  const matched  = detectSpecTemplate(desc);

  // Remove only auto-generated spec rows (marked data-auto="1") for THIS UID
  document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"][data-auto="1"]`).forEach(s => s.remove());

  if (!matched) return;

  // Check if any manually-added (non-auto) specs already exist — insert after those
  const remaining = document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`);
  let insertAfter = remaining.length > 0 ? remaining[remaining.length - 1] : row;

  matched.forEach(label => {
    const html = getSpecHtml(uid, idx, label, true);
    insertAfter.insertAdjacentHTML('afterend', html);
    insertAfter = insertAfter.nextElementSibling;
  });
  reindexRows();
}

document.getElementById('inp_discount').addEventListener('input', calcTotals);
document.querySelectorAll('[name=gst_mode]').forEach(r => r.addEventListener('change', calcTotals));

function setDiscType(type) {
  document.getElementById('inp_disc_type').value = type;
  const pctBtn  = document.getElementById('btn_disc_pct');
  const flatBtn = document.getElementById('btn_disc_flat');
  const inp     = document.getElementById('inp_discount');
  if (type === 'percent') {
    pctBtn.style.background  = '#1a3a7c'; pctBtn.style.color  = '#fff';
    flatBtn.style.background = '';        flatBtn.style.color = '';
    inp.max = 100; inp.placeholder = '0';
    document.getElementById('lbl_discount').textContent = 'Discount %';
  } else {
    flatBtn.style.background = '#1a3a7c'; flatBtn.style.color = '#fff';
    pctBtn.style.background  = '';        pctBtn.style.color  = '';
    inp.removeAttribute('max'); inp.placeholder = '0.00';
    document.getElementById('lbl_discount').textContent = 'Discount ₹';
  }
  inp.value = 0;
  calcTotals();
}
// Init button state on page load
(function(){ setDiscType(document.getElementById('inp_disc_type').value || 'percent'); })();
document.querySelectorAll('[name=type]').forEach(r => r.addEventListener('change', refreshDocLabel));
['inp_cgst','inp_sgst','inp_igst'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', calcTotals);
});

// Terms
document.getElementById('addTermBtn').addEventListener('click', () => {
  document.getElementById('termsList').insertAdjacentHTML('beforeend',
    `<div class="d-flex align-items-center gap-2 mb-2 term-row">
      <input type="text" name="terms[]" class="form-control form-control-sm" placeholder="Enter term…">
      <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-term"><i class="fa fa-minus"></i></button>
    </div>`);
});
document.getElementById('termsList').addEventListener('click', e => {
  if (e.target.closest('.remove-term')) e.target.closest('.term-row').remove();
});

// ── Scope of Work dynamic rows ────────────────────────────────────────────────
let scopeIdx = <?= count($d['scope_items'] ?? []) ?>;

function getScopeItemHtml(idx, title='', desc='', note='') {
  const t = title.replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  const d = desc.replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const n = note.replace(/</g,'&lt;').replace(/>/g,'&gt;');
  return `<div class="scope-item border rounded p-3 mb-3 bg-light" data-scope="${idx}">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-bold text-danger" style="font-size:.82rem;">ITEM ${idx+1}</span>
      <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-scope-item"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="mb-2">
      <label class="form-label" style="font-size:.82rem;font-weight:600;color:#555;">Title <span class="text-muted fw-normal">(shown bold/underlined)</span></label>
      <input type="text" name="scope_title[]" class="form-control form-control-sm" value="${t}" placeholder="e.g. Base work">
    </div>
    <div class="mb-2">
      <label class="form-label" style="font-size:.82rem;font-weight:600;color:#555;">Description</label>
      <textarea name="scope_desc[]" class="form-control form-control-sm" rows="3" placeholder="Detailed description...">${d}</textarea>
    </div>
    <div>
      <label class="form-label" style="font-size:.82rem;font-weight:600;color:#555;">Please Note <span class="text-muted fw-normal">(optional)</span></label>
      <textarea name="scope_note[]" class="form-control form-control-sm" rows="2" placeholder="Optional note for client...">${n}</textarea>
    </div>
  </div>`;
}

document.getElementById('addScopeBtn').addEventListener('click', () => {
  document.getElementById('scopeList').insertAdjacentHTML('beforeend', getScopeItemHtml(scopeIdx++));
  renumberScopeItems();
});

document.getElementById('scopeList').addEventListener('click', e => {
  if (e.target.closest('.remove-scope-item')) {
    e.target.closest('.scope-item').remove();
    renumberScopeItems();
  }
});

function renumberScopeItems() {
  document.querySelectorAll('#scopeList .scope-item').forEach((el, i) => {
    const badge = el.querySelector('.fw-bold.text-danger');
    if (badge) badge.textContent = 'ITEM ' + (i+1);
  });
}

// ── Scope templates per sub-type ──────────────────────────────────────────────
const SCOPE_TEMPLATES = {
  supply_installation: [
    { title: 'Material Supply', description: 'Supply of all specified materials as per the estimation including packing, quality check, and delivery to site.', note: 'All materials will be supplied as per approved specifications. Any changes after order confirmation may incur additional charges.' },
    { title: 'Installation Work', description: 'Professional installation of all supplied materials by trained technicians. Work includes fixing, tensioning, fastening and all necessary labour for complete installation.', note: 'Client to ensure site is ready and accessible before installation date. Civil work if any to be completed by client.' },
  ],
  cricket_net: [
    { title: 'Base work', description: 'Preparation of the cricket net base including excavation, sub-base formation, drain board, aggregate stone, perforated PVC drainage pipes, geotextile fabric, proper leveling and complete drainage system.', note: 'Aggregate stone, bricks, cement, sand and other locally available materials for the base shall be provided by the client and also JCB and roller required for base preparation shall be arranged by the client.' },
    { title: 'Surface Material', description: 'Supply and installation of Artificial Grass / Cricket Pitch Matting as per specifications agreed. Roll seaming, joining and infill as applicable.', note: '' },
    { title: 'Cricket Pitch', description: 'Installation of high-density non-infill cricket pitch matting (12mm – 15mm) glued directly onto the prepared concrete/aggregate base. Provides consistent ball bounce, seam and spin as per standard pitch requirements.', note: '' },
    { title: 'Nylon Net Fencing', description: 'Providing and installing UV-stabilized HDPE / Nylon braided net fencing on all sides with required height as per specification. Net to be fixed with GI wire rope, turnbuckles and SS shackles.', note: '' },
    { title: 'Fabrication of Fencing', description: 'Fabrication of MS pipe fencing structure using specified pipe dimensions. Vertical columns to be installed at regular intervals and anchored into concrete footings. Horizontal support beams / purlins to be welded as per design. Complete with anti-rust primer and enamel paint / powder coating.', note: '' },
    { title: 'Lighting System', description: '200W LED Flood Lights (IP65 / IP66), including wiring, cabling, earthing, MCB panel and all required materials for a complete lighting system. Poles mounted at specified height for uniform lux levels across the entire playing area.', note: 'This work is to be carried out by the client. NettingTeam to provide advisory and support only unless separately quoted.' },
  ],
  sports_net: [
    { title: 'Base work', description: 'Preparation of the sports ground base including excavation, sub-base formation, drain board, aggregate stone, perforated PVC drainage pipes, geotextile fabric, proper leveling and complete drainage system for all-weather playability.', note: 'Aggregate stone, bricks, cement, sand and other locally available materials for the base shall be provided by the client and also JCB and roller required for base preparation shall be arranged by the client.' },
    { title: 'Surface Material', description: 'Supply and installation of 50mm Monofilament Artificial Turf (PE) with double PU backing and water drain holes. Roll size as specified. Silica sand infill (10–12 kg/sqm) and SBR rubber granule infill (5–6 kg/sqm) included.', note: '' },
    { title: 'Line Marking', description: 'Line marking using White Turf seaming tape and high-strength adhesive as per standard court dimensions. All rolls to be joined using seaming tape for seamless playing surface.', note: '' },
    { title: 'Nylon Net Fencing', description: 'Providing and installing 50mm × 50mm UV-stabilized HDPE / Nylon braided net fencing with required height as per specification. Net area and roll size as quoted. Fixed with GI wire rope, turnbuckles and SS shackles.', note: '' },
    { title: 'Fabrication of Fencing', description: 'Fabrication of MS pipe fencing structure using 70mm × 70mm / 50mm × 25mm pipes installed at regular intervals (4m each). Vertical columns anchored into concrete footings along perimeter wall. Horizontal beams at 0m, 4m & 8m height. Complete with heavy-duty HDPE netting on all four sides on top, including all necessary accessories. Anti-rust primer + enamel paint finish.', note: '' },
    { title: 'Lighting System', description: '200W LED Flood Lights (IP65 / IP66), including wiring, cabling, earthing, MCB panel and all required materials for a complete lighting system. Poles mounted at specified height for uniform lux levels across the entire playing area.', note: 'This work is to be carried out by the client. NettingTeam to provide advisory and support only unless separately quoted.' },
  ],
  turnkey_court: [
    { title: '1. Civil Ground Base & Compaction', description: 'Engineered sub-base preparation including site excavation (6-9 inches), heavy mechanical roller compaction, geotextile anti-weed membrane, 75mm-100mm graded blue metal aggregate (WMM/GSB) and 25mm stone dust leveling layer with 0.5% laser-graded slope.', note: 'Provides all-weather playability and prevents surface undulations.' },
    { title: '2. Sub-Surface & Perimeter Drainage', description: 'Engineered drainage system with perforated PVC drain pipes in aggregate trenches or French drain channels along perimeter with catch pits and stormwater gradient connection.', note: 'Ensures zero water accumulation even during heavy rainfall.' },
    { title: '3. Structural MS/GI Cage & Column Framework', description: 'Heavy-duty vertical columns (3"x3" or 4"x4" SHS MS/GI pipes) spaced at engineered 15 ft bays, anchored into 1.5ft x 1.5ft x 3ft deep M20 concrete footings. 4 tiers of horizontal tie purlins running continuously around perimeter. Anti-rust primer + double coat weather-resistant PU finish.', note: 'Engineered for high wind load stability and long service life.' },
    { title: '4. Sports Netting System (Side & Top)', description: 'UV-stabilized HDPE braided / Russea™ high-tenacity sports netting (40mm-45mm square mesh). Side/back ball-stop protection up to full court height, plus heavy-duty overhead roof net supported on cross-tension steel wire ropes with turnbuckles and stainless steel D-shackles.', note: 'Prevents ball escape and protects lighting fixtures and spectators.' },
    { title: '5. Artificial Sports Turf System', description: 'High-density monofilament sports turf (30mm/40mm/50mm/60mm) with multi-layer reinforced PP + PU backing and drainage perforations. High-purity silica sand and SBR rubber granule infill evenly distributed via mechanical brush. Turf rolls bonded with 30cm seaming tape and two-part polyurethane adhesive.', note: 'Meets regulation bounce, shock absorption and player traction standards.' },
    { title: '6. High-Output Stadium LED Floodlighting', description: 'IP66/IP67 rated commercial sports LED floodlights (150W/200W/300W) mounted on structural perimeter columns. Complete with industrial weather-proof distribution board, MCBs, digital timer control, heavy 4-core copper armored cabling, and chemical earthing pit.', note: 'Designed for uniform 250-350+ lux illumination for tournament-grade night play.' },
    { title: '7. Player Entry Gate & Court Accessories', description: 'Heavy-duty steel tubular access door (3ft/4ft x 7ft) with cylinder lock, weather-proof hinges and protective padding. Official regulation inlaid court line markings, cricket pitch pad or heavy-duty football goalposts with nets.', note: 'Safe and convenient player access.' },
    { title: '8. Complete Turnkey Installation & Handover', description: 'End-to-end execution by certified sports infrastructure team. Includes laser leveling, ultrasonic structural welding checks, turf tensioning, netting rigging, lux level verification, and official handover certification.', note: 'Turnkey handover — fully ready to play.' },
  ],
};

function populateScopeTemplate(subtype) {
  const tpl = SCOPE_TEMPLATES[subtype];
  const section = document.getElementById('scopeSection');
  if (!tpl) {
    if (section) section.style.display = 'none';
    return;
  }
  if (section) section.style.display = '';
  const list = document.getElementById('scopeList');
  if (!list) return;
  // Only auto-populate if currently empty
  const existing = list.querySelectorAll('.scope-item');
  if (existing.length > 0) return;
  scopeIdx = 0;
  list.innerHTML = '';
  tpl.forEach(item => {
    list.insertAdjacentHTML('beforeend', getScopeItemHtml(scopeIdx++, item.title, item.description, item.note));
  });
  renumberScopeItems();
}

// ── Estimation Sub-Type Logic ─────────────────────────────────────────────────
const EST_SUBTYPE_TERMS = {
  normal: {
    hint: 'Standard estimation — 50% advance payment terms.',
    terms: [
      'Transport charges excluded',
      '50% Advance payment required to proceed order',
      'All other charges included',
      'Installation charges are included',
    ]
  },
  supply_only: {
    hint: 'Material supply only — 100% payment before dispatch.',
    terms: [
      '100% payment to be made before supply / delivery',
      'Transport charges excluded',
      'All other charges included',
      'Installation not included in this estimation',
    ]
  },
  supply_installation: {
    hint: 'Supply + installation — 50% before supply, 50% after supply.',
    terms: [
      '50% advance payment required before supply / delivery',
      '50% remaining payment after supply and before installation',
      'Transport charges excluded',
      'All other charges included',
    ]
  },
  cricket_net: {
    hint: 'Full cricket net ground setup — 40% / 40% / 20% milestone payments.',
    terms: [
      '40% advance payment required before project commencement',
      '40% payment after ground base setup and steel fabrication completion',
      '20% remaining balance payment after full project completion',
      'Transport charges excluded',
      'Aggregate stone, bricks, cement, sand and locally available materials to be arranged by the client',
      'JCB and roller required for base preparation to be arranged by the client',
    ]
  },
  sports_net: {
    hint: 'Full sports net / turf ground setup — 40% / 40% / 20% milestone payments.',
    terms: [
      '40% advance payment required before project commencement',
      '40% payment after ground base setup and steel fabrication completion',
      '20% remaining balance payment after full project completion',
      'Transport charges excluded',
      'All charges included',
    ]
  },
  turnkey_court: {
    hint: 'Complete Turnkey Sports Court Construction — 40% Advance / 40% Structural & Base / 20% Handover.',
    terms: [
      'Payment Terms: 40% Advance on order confirmation, 40% upon civil base & structural steel erection, 20% upon final turf installation & court handover',
      '40% Advance payment required to commence site excavation, civil bed work, and structural steel procurement',
      '40% Milestone payment upon completion of civil base slab and structural steel column & truss erection',
      '20% Final balance payment upon sports turf installation, LED floodlighting testing, and final court handover',
      'Transport & Material Freight: Material delivery directly to project site as per BOQ',
      'Site Utilities: Water and electricity power source to be arranged at site by client during execution',
      'System Warranty: 1 Year (Essential) / 2 Years (Commercial) / 3-5 Years (Premium Pro) Complete System Warranty',
    ]
  },
};

function onEstSubtypeChange(subtype) {
  const config = EST_SUBTYPE_TERMS[subtype];
  if (!config) return;

  // Toggle Turnkey Panel
  const tkPanel = document.getElementById('turnkeyPanel');
  if (tkPanel) {
    tkPanel.style.display = (subtype === 'turnkey_court') ? '' : 'none';
    if (subtype === 'turnkey_court') updateTurnkeyCalculations();
  }

  // Update hint
  const hint = document.getElementById('estSubtypeHint');
  if (hint) hint.innerHTML = '<i class="fa fa-circle-info me-1 text-info"></i>' + config.hint;

  // Replace terms
  const list = document.getElementById('termsList');
  list.innerHTML = '';
  config.terms.forEach(t => {
    list.insertAdjacentHTML('beforeend',
      `<div class="d-flex align-items-center gap-2 mb-2 term-row">
        <input type="text" name="terms[]" class="form-control form-control-sm" value="${t.replace(/"/g,'&quot;')}">
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-term"><i class="fa fa-minus"></i></button>
      </div>`
    );
  });

  // Populate scope of work template
  populateScopeTemplate(subtype);

  // Refresh doc type label badge
  refreshDocLabel();
}

// Show/hide sub-type row when doc type changes
document.querySelectorAll('[name=type]').forEach(r => r.addEventListener('change', function() {
  const row = document.getElementById('estSubtypeRow');
  if (row) row.style.display = this.value === 'estimation' ? '' : 'none';
  refreshDocLabel();
}));

// Init hint on page load
(function() {
  const cur = document.querySelector('[name=estimation_subtype]:checked')?.value || 'normal';
  const hint = document.getElementById('estSubtypeHint');
  const config = EST_SUBTYPE_TERMS[cur];
  if (hint && config) hint.innerHTML = '<i class="fa fa-circle-info me-1 text-info"></i>' + config.hint;
})();

// Init
toggleGST();
refreshDocLabel();
calcTotals();


// ── Turnkey Sports Court Generator Helper Functions ────────────────────────────
let _turnkeyDebounceTimer = null;
let _turnkeyInitialized = false;
window.TURNKEY_SPORT_DEFAULTS = <?= json_encode(getTurnkeySportProfiles()) ?>;

function onRoofTypeChange(triggerCalc = true) {
  const roof = document.getElementById('tk_roof_type')?.value || 'light_cable_top';
  const topNetSelect = document.getElementById('tk_top_net');
  if (topNetSelect) {
    if (roof === 'open_top') {
      topNetSelect.innerHTML = '<option value="open_top" selected>☀️ None (Open Top — No Overhead Net)</option>';
      topNetSelect.disabled = true;
      topNetSelect.classList.add('bg-light');
    } else {
      topNetSelect.disabled = false;
      topNetSelect.classList.remove('bg-light');
      if (!topNetSelect.querySelector('option[value="braided_2_5mm_45mm"]')) {
        const curVal = topNetSelect.value || 'twisted_1_5mm_35mm';
        topNetSelect.innerHTML = `
          <optgroup label="Uniform Top Net (Same as Side Net)">
            <option value="same_as_side">🎯 Same as Side Net (Uniform 2.5mm Braided All Sides &amp; Top)</option>
          </optgroup>
          <optgroup label="2.5mm Braided Heavy Top Net (Trusses / Heavy Cable)">
            <option value="braided_2_5mm_45mm">2.5mm Braided (45mm Mesh - Heavy Overhead)</option>
            <option value="braided_2_5mm_40mm">2.5mm Braided (40mm Mesh - Heavy Overhead)</option>
            <option value="braided_2_5mm_35mm">2.5mm Braided (35mm Mesh - Heavy Overhead)</option>
            <option value="braided_2_5mm_50mm">2.5mm Braided (50mm Mesh - Multi-Sport Top)</option>
            <option value="braided_2_5mm_100mm">2.5mm Braided (100mm Football Roof Mesh)</option>
          </optgroup>
          <optgroup label="2.0mm Braided Medium Top Net">
            <option value="braided_2_0mm_45mm">2.0mm Braided (45mm Mesh)</option>
            <option value="braided_2_0mm_40mm">2.0mm Braided (40mm Mesh)</option>
            <option value="braided_2_0mm_35mm">2.0mm Braided (35mm Mesh)</option>
            <option value="braided_2_0mm_50mm">2.0mm Braided (50mm Mesh)</option>
          </optgroup>
          <optgroup label="1.5mm / 1.0mm Lightweight Cable Top Net (Zero Sag)">
            <option value="twisted_1_5mm_35mm">1.5mm Twisted (35mm Mesh / 24-Ply - Recommended Light)</option>
            <option value="twisted_1_5mm_30mm">1.5mm Twisted (30mm Mesh / 24-Ply)</option>
            <option value="twisted_1_5mm_40mm">1.5mm Twisted (40mm Mesh / 24-Ply)</option>
            <option value="twisted_1_0mm_30mm">1.0mm Twisted (30mm Mesh - Ultra Light)</option>
          </optgroup>
        `;
        if (topNetSelect.querySelector(`option[value="${curVal}"]`)) {
          topNetSelect.value = curVal;
        } else {
          topNetSelect.value = 'twisted_1_5mm_35mm';
        }
      }
    }
  }
  if (triggerCalc) updateTurnkeyCalculations(true);
}

function onSportTypeChange(triggerCalc = true) {
  const sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
  const profile = (window.TURNKEY_SPORT_DEFAULTS && window.TURNKEY_SPORT_DEFAULTS[sport]) ? window.TURNKEY_SPORT_DEFAULTS[sport] : null;

  // When user actively changes the sport type in dropdown (triggerCalc === true):
  // auto-fill standard measurements and options for that sport while keeping inputs completely editable
  if (triggerCalc && profile) {
    if (document.getElementById('tk_length') && profile.default_l) document.getElementById('tk_length').value = profile.default_l;
    if (document.getElementById('tk_width') && profile.default_w)  document.getElementById('tk_width').value  = profile.default_w;
    if (document.getElementById('tk_height') && profile.default_h) document.getElementById('tk_height').value = profile.default_h;
    
    const pitchEl = document.getElementById('tk_pitch_length');
    if (pitchEl && profile.default_pitch !== undefined) {
      pitchEl.value = profile.default_pitch;
    }

    if (profile.default_turf && document.getElementById('tk_turf_type')) {
      document.getElementById('tk_turf_type').value = profile.default_turf;
    }
    if (profile.default_civil && document.getElementById('tk_civil_bed')) {
      document.getElementById('tk_civil_bed').value = profile.default_civil;
    }
    if (profile.default_side_net && document.getElementById('tk_side_net')) {
      document.getElementById('tk_side_net').value = profile.default_side_net;
    }
    if (profile.default_top_net && document.getElementById('tk_top_net')) {
      document.getElementById('tk_top_net').value = profile.default_top_net;
    }
    if (profile.category === 'terrace' && document.getElementById('tk_roof_type')) {
      document.getElementById('tk_roof_type').value = 'light_cable_top';
    }
    onRoofTypeChange(false);
  } else if (!triggerCalc) {
    // Initial page load or subtle sync: auto-tune options if unset
    const turfSelect = document.getElementById('tk_turf_type');
    const civilSelect = document.getElementById('tk_civil_bed');
    const sideNetSelect = document.getElementById('tk_side_net');

    if (sport === 'tennis' || sport.startsWith('basketball') || sport.startsWith('pickleball')) {
      if (turfSelect && turfSelect.value === 'non_infill_turf') turfSelect.value = 'synthetic_acrylic_8layer';
      if (civilSelect && civilSelect.value === 'compacted_wmm') civilSelect.value = 'concrete_pcc_3inch';
    } else if (sport.startsWith('badminton')) {
      if (turfSelect && turfSelect.value === 'non_infill_turf') turfSelect.value = 'bwf_vinyl_mat';
      if (civilSelect && civilSelect.value === 'compacted_wmm') civilSelect.value = 'concrete_pcc_3inch';
    } else if (sport.startsWith('football') || sport === 'futsal') {
      if (turfSelect && turfSelect.value === 'non_infill_turf') turfSelect.value = 'infill_turf';
      if (civilSelect && civilSelect.value !== 'compacted_wmm' && civilSelect.value !== 'client_scope') civilSelect.value = 'compacted_wmm';
      if (sideNetSelect) sideNetSelect.value = 'braided_3_5mm_100mm';
    } else if (sport.startsWith('terrace')) {
      if (civilSelect && civilSelect.value === 'compacted_wmm') civilSelect.value = 'terrace_base_plates';
    }
  }

  // Show/Hide pitch length input
  const pitchRow = document.getElementById('tk_pitch_length')?.closest('.col-md-3');
  if (pitchRow) {
    if (sport.includes('cricket') || sport === 'multi_sport') {
      pitchRow.style.display = 'block';
    } else {
      pitchRow.style.display = 'none';
    }
  }

  if (triggerCalc) updateTurnkeyCalculations(true);
}

function setTurnkeyHeight(h) {
  const el = document.getElementById('tk_height');
  if (el) {
    el.value = h;
    updateTurnkeyCalculations(true);
  }
}

function applyTurnkeyPreset(L, W, H, sport, roof, turf, civil) {
  if (document.getElementById('tk_length')) document.getElementById('tk_length').value = L;
  if (document.getElementById('tk_width'))  document.getElementById('tk_width').value  = W;
  if (document.getElementById('tk_height')) document.getElementById('tk_height').value = H;
  if (sport && document.getElementById('tk_sport_type')) document.getElementById('tk_sport_type').value = sport;
  if (roof && document.getElementById('tk_roof_type'))  document.getElementById('tk_roof_type').value  = roof;
  if (turf && document.getElementById('tk_turf_type'))  document.getElementById('tk_turf_type').value  = turf;
  if (civil && document.getElementById('tk_civil_bed')) document.getElementById('tk_civil_bed').value = civil;
  
  onRoofTypeChange(false);
  onSportTypeChange(false);

  document.querySelectorAll('.tk-preset-btn').forEach(b => b.classList.remove('active'));
  if (window.event && window.event.currentTarget) window.event.currentTarget.classList.add('active');
  
  updateTurnkeyCalculations(true);
}

function syncTurnkeyBadgesOnly() {
  const L = parseFloat(document.getElementById('tk_length')?.value) || 90;
  const W = parseFloat(document.getElementById('tk_width')?.value)  || 60;
  const H = parseFloat(document.getElementById('tk_height')?.value) || 25;
  const roof = document.getElementById('tk_roof_type')?.value || 'top_net';
  const hasRoof = (roof !== 'open_top');
  const customRate = parseFloat(document.getElementById('tk_custom_rate')?.value || 0);
  
  const pkgRadio = document.querySelector('input[name="tk_package"]:checked');
  const pkg = pkgRadio ? pkgRadio.value : 'commercial';
  
  const baseArea = Math.round(L * W);
  const perim    = Math.round(2 * (L + W));
  
  const nL = Math.max(1, Math.round(L / 15.0));
  const nW = Math.max(1, Math.round(W / 15.0));
  const bayW = Math.round((L / nL) * 10) / 10;
  const totalPoles = 4 + 2 * (nL - 1) + 2 * (nW - 1);
  
  // Dynamic horizontal tie tier count (structural purlins spaced ~5-6 ft)
  let tierCount = 4;
  if (H <= 16)      tierCount = 3;
  else if (H <= 20) tierCount = 4;
  else if (H <= 24) tierCount = 5;
  else if (H <= 28) tierCount = 6;
  else if (H <= 32) tierCount = 7;
  else              tierCount = 8;

  const totalPipesRft = Math.round(perim * tierCount);
  const pipe20ftCount = Math.ceil(totalPipesRft / 20);
  
  const perimNet = Math.round(perim * H * 1.05);
  const topNet   = hasRoof ? Math.round(baseArea * 1.05) : 0;
  const totalNet = perimNet + topNet;
  
  // Standard 20 FT base rates adjusted for civil bed
  const civilBed = document.getElementById('tk_civil_bed')?.value || 'compacted_wmm';
  let civilAdj = 0;
  if (civilBed.includes('terrace_base_plates') || civilBed.includes('rooftop_base_plates')) civilAdj = -22;
  else if (civilBed === 'compacted_wmm_6inch') civilAdj = 10;
  else if (civilBed.startsWith('pcc_m15_3inch') || civilBed === 'concrete_pcc_3inch') {
    if (civilBed.includes('tremix')) civilAdj = 28;
    else if (civilBed.includes('smooth')) civilAdj = 20;
    else civilAdj = 15;
  } else if (civilBed.startsWith('pcc_m15_4inch')) {
    if (civilBed.includes('ips')) civilAdj = 50;
    else if (civilBed.includes('tremix')) civilAdj = 44;
    else if (civilBed.includes('smooth')) civilAdj = 36;
    else civilAdj = 30;
  } else if (civilBed.startsWith('pcc_m15_5inch')) civilAdj = 58;
  else if (civilBed.startsWith('pcc_m15_6inch')) civilAdj = 76;
  else if (civilBed === 'asphalt_bitumen') civilAdj = 42;
  else if (civilBed === 'laser_graded_earth') civilAdj = -16;
  else if (civilBed.startsWith('rcc_m20_4inch') || civilBed === 'concrete_rcc_4inch') civilAdj = 70;
  else if (civilBed.startsWith('rcc_m20_5inch')) civilAdj = 92;
  else if (civilBed.startsWith('rcc_m25_6inch')) civilAdj = 120;
  else if (civilBed === 'client_scope') civilAdj = -40;

  const pkgBaseRates = { essential: (hasRoof ? 250 : 240) + Math.round(civilAdj * 0.8), commercial: (hasRoof ? 300 : 285) + civilAdj, premium_pro: (hasRoof ? 350 : 330) + Math.round(civilAdj * 1.2) };
  const pkgFtRates   = { essential: 12000, commercial: 15000, premium_pro: 18000 };
  const pkgTierRates = { essential: 21000, commercial: 24000, premium_pro: 27000 };
  
  const pRatio = perim / 300.0;
  let baseRate = pkgBaseRates[pkg] || 300;
  let scale = 1.0;
  if (customRate > 0) {
    scale = customRate / baseRate;
    baseRate = customRate;
  }
  
  const ftRate   = (pkgFtRates[pkg] || 15000) * pRatio * scale;
  const tierRate = (pkgTierRates[pkg] || 24000) * pRatio * scale;
  
  const dH = H - 20.0;
  const dT = Math.max(0, tierCount - 4);
  const targetTotal = Math.round((baseArea * baseRate) + (dH * ftRate) + (dT * tierRate));
  const effectiveRate = Math.round((targetTotal / Math.max(1, baseArea)) * 100) / 100;
  
  // Update badges
  const elArea   = document.getElementById('disp_tk_area');
  const elPerim  = document.getElementById('disp_tk_perim');
  const elPoles  = document.getElementById('disp_tk_poles');
  const elSpace  = document.getElementById('disp_tk_spacing');
  const elPipes  = document.getElementById('disp_tk_pipes');
  const elTiers  = document.getElementById('disp_tk_tiers');
  const elNet    = document.getElementById('disp_tk_net');
  const elNetDet = document.getElementById('disp_tk_net_detail');
  const elTotal  = document.getElementById('disp_tk_total');
  const elRate   = document.getElementById('disp_tk_rate');
  const elLights = document.getElementById('disp_tk_lights');
  
  const poleStyle = document.getElementById('tk_pole_style')?.value || 'ladder_2line';
  const roofStyle = document.getElementById('tk_roof_type')?.value || 'light_cable_top';
  const turfStyle = document.getElementById('tk_turf_type')?.value || 'infill_turf';
  const is4Line   = (poleStyle === 'ladder_4line');
  const is2Line   = (poleStyle === 'ladder_2line' || poleStyle === 's3_ladder_truss');

  let poleLabel = totalPoles + ' Single Columns';
  if (is4Line) poleLabel = totalPoles + ' 4-Line Towers';
  else if (is2Line) poleLabel = totalPoles + ' Ladder Columns';

  let roofNetLabel = 'Side + Top Net';
  if (roofStyle === 'open_top') roofNetLabel = 'Open Top (Side Only)';
  else if (roofStyle === 'light_cable_top') roofNetLabel = '1.5mm/24ply Cable Net';
  else if (roofStyle === 'pipe_top') roofNetLabel = 'Cross Pipes + Net';
  else if (roofStyle === 'truss_top') roofNetLabel = 'Trusses + Heavy Net';

  if (elArea)   elArea.textContent  = baseArea.toLocaleString('en-IN') + ' SFT';
  if (elPerim)  elPerim.textContent = perim.toLocaleString('en-IN') + ' RFT Perim';
  if (elPoles)  elPoles.textContent = poleLabel;
  if (elSpace)  elSpace.textContent = bayW + ' ft Bay Spacing' + ((is2Line || is4Line) ? ' (1.5\' Rungs)' : '');
  if (elPipes)  elPipes.textContent = totalPipesRft.toLocaleString('en-IN') + ' RFT';
  if (elTiers)  elTiers.textContent = tierCount + ' Tiers (' + pipe20ftCount + ' Pipes)';
  if (elNet)    elNet.textContent   = totalNet.toLocaleString('en-IN') + ' SFT';
  if (elNetDet) elNetDet.textContent = roofNetLabel;
  if (elTotal)  elTotal.textContent = '₹' + targetTotal.toLocaleString('en-IN');
  if (elRate)   elRate.textContent  = '₹' + effectiveRate + ' / SFT';

  // Dynamically update package buttons so they never stay static 250, 300, 350
  const elPkgEss = document.getElementById('disp_pkg_rate_essential');
  const elPkgCom = document.getElementById('disp_pkg_rate_commercial');
  const elPkgPrem = document.getElementById('disp_pkg_rate_premium');
  if (elPkgEss) elPkgEss.textContent = '₹' + Math.round(effectiveRate * 0.83) + ' / SFT';
  if (elPkgCom) elPkgCom.textContent = '₹' + Math.round(effectiveRate) + ' / SFT ★';
  if (elPkgPrem) elPkgPrem.textContent = '₹' + Math.round(effectiveRate * 1.17) + ' / SFT';
  
  if (elLights) {
    if (pkg === 'essential') elLights.textContent = (L >= 80 ? '6x 150W LEDs' : '4x 150W LEDs');
    if (pkg === 'commercial') elLights.textContent = (L >= 80 ? '8x 200W LEDs' : '6x 200W LEDs');
    if (pkg === 'premium_pro') elLights.textContent = (L >= 80 ? '10x 300W LEDs' : '8x 300W LEDs');
  }

  // Highlight height button
  document.querySelectorAll('.tk-height-btn').forEach(btn => {
    const isCur = parseFloat(btn.textContent) === H;
    btn.classList.toggle('btn-primary', isCur);
    btn.classList.toggle('text-white', isCur);
    btn.classList.toggle('btn-outline-secondary', !isCur);
  });
}

function updateTurnkeyCalculations(immediate = false) {
  const isTurnkeySubtype = document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court';
  if (!isTurnkeySubtype) return;

  syncTurnkeyBadgesOnly();

  const statusEl = document.getElementById('turnkeySyncStatus');
  if (statusEl) {
    statusEl.innerHTML = '<span class="text-primary fw-bold"><i class="fa fa-spinner fa-spin me-1"></i>Recalculating rates & items automatically...</span>';
  }

  clearTimeout(_turnkeyDebounceTimer);
  const delay = immediate ? 0 : 250;
  _turnkeyDebounceTimer = setTimeout(() => {
    generateTurnkeyBOQAndPopulate(true);
  }, delay);
}

function generateTurnkeyBOQAndPopulate(isAuto = false) {
  const isTurnkeySubtype = document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court';
  if (!isTurnkeySubtype) return;

  const L = parseFloat(document.getElementById('tk_length')?.value) || 90;
  const W = parseFloat(document.getElementById('tk_width')?.value)  || 60;
  const H = parseFloat(document.getElementById('tk_height')?.value) || 25;
  const pitchL = parseFloat(document.getElementById('tk_pitch_length')?.value) || 66;
  const sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
  const roof  = document.getElementById('tk_roof_type')?.value  || 'top_net';
  const pkgRadio = document.querySelector('input[name="tk_package"]:checked');
  const pkg   = pkgRadio ? pkgRadio.value : 'commercial';
  const customRate = parseFloat(document.getElementById('tk_custom_rate')?.value || 0);
  
  const btn = document.getElementById('btnPopulateTurnkey');
  const statusEl = document.getElementById('turnkeySyncStatus');

  if (btn && !isAuto) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Calculating Engineering BOQ...';
  }
  
  const fd = new FormData();
  fd.append('action', 'calc_turnkey_boq');
  fd.append('length', L);
  fd.append('width', W);
  fd.append('height', H);
  fd.append('pitch_length', pitchL);
  fd.append('sport_type', sport);
  fd.append('roof_type', roof);
  const poleStyle = document.getElementById('tk_pole_style')?.value || 'ladder_2line';
  const pipeSize  = document.getElementById('tk_pipe_size')?.value || '40x40';
  const pipeGauge = document.getElementById('tk_pipe_gauge')?.value || '2.0mm';
  const turfType  = document.getElementById('tk_turf_type')?.value || 'infill_turf';
  const civilBed  = document.getElementById('tk_civil_bed')?.value || 'compacted_wmm';
  fd.append('package', pkg);
  fd.append('pole_style', poleStyle);
  fd.append('pipe_size', pipeSize);
  fd.append('pipe_gauge', pipeGauge);
  fd.append('turf_type', turfType);
  fd.append('civil_bed_type', civilBed);
  const sideNet = document.getElementById('tk_side_net')?.value || 'braided_2_5mm_45mm';
  const topNet  = document.getElementById('tk_top_net')?.value  || 'twisted_1_5mm_35mm';
  fd.append('side_net', sideNet);
  fd.append('top_net', topNet);
  const gateCount = parseInt(document.getElementById('tk_gate_count')?.value || 1);
  fd.append('gate_count', gateCount);
  if (customRate > 0) fd.append('custom_rate', customRate);
  
  return fetch('ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (btn && !isAuto) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-wand-magic-sparkles me-1"></i>Re-Populate Turnkey BOQ (15 Items)';
      }
      if (!res.ok || !res.data) {
        if (!isAuto) alert('Error generating BOQ: ' + (res.msg || 'Unknown error'));
        if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Calculation error: ' + (res.msg || 'Unknown error') + '</span>';
        return;
      }
      
      const boq = res.data;
      
      // 1. Save config into hidden JSON field
      if (!isAuto) {
        turnkeyExcludedItems = [];
      }
      renderTurnkeyExcludedItems();

      const configObj = {
        length: L,
        width: W,
        height: H,
        pitch_length: pitchL,
        gate_count: gateCount,
        sport_type: sport,
        roof_type: roof,
        package: pkg,
        pole_style: poleStyle,
        pipe_size: pipeSize,
        pipe_gauge: pipeGauge,
        turf_type: turfType,
        civil_bed_type: civilBed,
        target_rate: boq.selected_pkg?.target_rate,
        target_total: boq.target_total,
        specs: boq.specs,
        excluded_items: turnkeyExcludedItems,
      };
      const cfgInput = document.getElementById('turnkeyConfigJson');
      if (cfgInput) cfgInput.value = JSON.stringify(configObj);
      
      // 2. Filter BOQ items against excluded items
      const isItemExcluded = (it) => {
        if (!isAuto || !turnkeyExcludedItems || turnkeyExcludedItems.length === 0) return false;
        const desc = (it.description || '').split('\n')[0].trim().toLowerCase();
        return turnkeyExcludedItems.some(ex => {
          const exDesc = (ex.description || '').split('\n')[0].trim().toLowerCase();
          return desc === exDesc || (desc.substring(0, 18) === exDesc.substring(0, 18) && desc.length > 8);
        });
      };

      const allDocItems = boq.doc_items || boq.items || [];
      const activeItems = allDocItems.filter(it => !isItemExcluded(it));

      // 3. Populate table with updated BOQ items
      const tbody = document.getElementById('itemsTbody');
      tbody.innerHTML = '';
      rowIdx = 0;
      
      activeItems.forEach((item, idx) => {
        const itemUid = getNextItemUid();
        const trHtml = `
          <tr class="item-row" data-item-uid="${itemUid}" data-row="${idx}">
            <td><textarea name="item_desc[${idx}]" class="form-control form-control-sm" rows="2" required>${item.description.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea></td>
            <td><input type="text" name="item_hsn[${idx}]" class="form-control form-control-sm item-hsn" value="${item.hsn}"></td>
            <td><input type="number" name="item_qty[${idx}]" class="form-control form-control-sm item-qty" value="${item.qty}" step="0.01" min="0"></td>
            <td>
              <select name="item_unit[${idx}]" class="form-select form-select-sm item-unit-select">
                ${getUnitOptions(item.unit)}
              </select>
            </td>
            <td><input type="number" name="item_rate[${idx}]" class="form-control form-control-sm item-rate" value="${item.rate}" step="0.01" min="0"></td>
            <td><input type="number" name="item_gst_rate[${idx}]" class="form-control form-control-sm item-gst-rate text-center fw-bold" value="${item.gst_rate}" step="any" min="0" max="100" style="min-width:75px;padding:.3rem .4rem;font-size:0.85rem;" list="gstRateList"></td>
            <td class="amount-col"><span class="item-amount">₹${item.amount.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
              <input type="hidden" name="item_amount[${idx}]" class="item-amount-val" value="${item.amount}">
            </td>
            <td style="white-space:nowrap;">
              <div class="d-flex flex-column gap-1">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-row" title="Remove row"><i class="fa fa-xmark"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 add-spec-btn" title="Add spec">+Spec</button>
              </div>
            </td>
          </tr>
          ${(item.specs || []).map(spec => `
            <tr class="spec-row" data-parent-uid="${itemUid}" data-parent="${idx}">
              <td colspan="6" style="padding:2px 8px 2px 28px;">
                <div class="d-flex align-items-center gap-1">
                  <span class="text-muted" style="font-size:.75rem;">↳</span>
                  <input type="text" name="item_specs[${idx}][]" class="form-control form-control-sm spec-input py-0" value="${spec.replace(/"/g,'&quot;')}" style="font-size:.78rem;background:#fff;">
                  <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 remove-spec" title="Remove spec"><i class="fa fa-minus"></i></button>
                </div>
              </td><td></td>
            </tr>
          `).join('')}
        `;
        tbody.insertAdjacentHTML('beforeend', trHtml);
      });
      
      rowIdx = activeItems.length;
      reindexRows();
      calcTotals();
      if (typeof window.scheduleAutosave === 'function') {
        window.scheduleAutosave();
      }
      
      // Auto-populate / Synchronize Scope of Work with dynamically configured scope items
      const scopeList = document.getElementById('scopeList');
      if (scopeList) {
        const dynamicScope = (boq.scope_items && boq.scope_items.length > 0) ? boq.scope_items : SCOPE_TEMPLATES['turnkey_court'];
        if (dynamicScope && dynamicScope.length > 0) {
          scopeList.innerHTML = '';
          scopeIdx = 0;
          dynamicScope.forEach(s => {
            scopeList.insertAdjacentHTML('beforeend', getScopeItemHtml(scopeIdx++, s.title, s.description, s.note));
          });
          renumberScopeItems();
        }
      }
      
      if (statusEl) {
        const pkgName = pkg.replace('_',' ').toUpperCase();
        statusEl.innerHTML = '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="fa fa-check-circle me-1"></i>Auto-Calculated: ' + L + '×' + W + '×' + H + ' FT • ' + pkgName + ' (₹' + (boq.selected_pkg?.target_rate || '') + '/SFT) • Subtotal: ₹' + boq.target_total.toLocaleString('en-IN') + '</span>';
      }

      // Success feedback toast if manual
      if (!isAuto) {
        const feedback = document.createElement('div');
        feedback.className = 'alert alert-success mt-2 py-2 px-3 fw-bold';
        feedback.innerHTML = '<i class="fa fa-check-circle me-1"></i> Successfully generated complete Turnkey BOQ (' + allDocItems.length + ' items) totaling ₹' + boq.target_total.toLocaleString('en-IN') + ' for ' + L + '×' + W + '×' + H + ' ft court!';
        const p = document.getElementById('turnkeyPanel');
        if (p) {
          const oldAlert = p.querySelector('.alert-success');
          if (oldAlert) oldAlert.remove();
          p.appendChild(feedback);
          setTimeout(() => feedback.remove(), 5000);
        }
      }
    })
    .catch(err => {
      console.error('Turnkey auto-calc error:', err);
      if (btn && !isAuto) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-wand-magic-sparkles me-1"></i>Re-Populate Turnkey BOQ (15 Items)';
      }
      if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="fa fa-triangle-exclamation me-1"></i>Calc failed: ' + err.message + '</span>';
    });
}

// Initial badge sync on load (only if turnkey court subtype selected)
if (document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court') {
  syncTurnkeyBadgesOnly();
}

// Company switcher
function switchCompany(sel) {
  const data = JSON.parse(document.getElementById('companyData').textContent);
  const c    = data[sel.value];
  if (!c) return;
  const nameEl = document.getElementById('companyPreviewName');
  const subEl  = document.getElementById('companyPreviewSub');
  if (nameEl) nameEl.textContent = c.name || '';
  if (subEl)  subEl.textContent  = (c.gstin ? 'GSTIN: ' + c.gstin + ' · ' : '') + (c.phone || '') + (c.state ? ' · ' + c.state : '');
  // Reload page with company_id so PHP can re-render assets (signatures, stamps, logo)
  const url = new URL(window.location.href);
  url.searchParams.set('company_id', sel.value);
  window.location.href = url.toString();
}

// ─── AUTO-SAVE ────────────────────────────────────────────────────────────────
(function() {
  let autosaveTimer = null;
  let isSaving = false;
  let lastSaved = null;
  const bar  = document.getElementById('autosaveBar');
  const icon = document.getElementById('autosaveIcon');
  const msg  = document.getElementById('autosaveMsg');
  const idIn = document.getElementById('autosaveId');

  function showBar(html, color) {
    bar.style.display = 'flex';
    bar.style.background = color === 'green' ? '#f0fdf4' : (color === 'red' ? '#fef2f2' : '#fefce8');
    bar.style.borderColor = color === 'green' ? '#bbf7d0' : (color === 'red' ? '#fecaca' : '#fde68a');
    bar.style.color = color === 'green' ? '#166534' : (color === 'red' ? '#991b1b' : '#92400e');
    msg.innerHTML = html;
  }

  function doAutosave() {
    if (isSaving) return;
    isSaving = true;
    icon.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i>';
    showBar('Auto-saving draft...', 'yellow');

    reindexRows();
    const sc = document.getElementById('same_as_mobile');
    const pi = document.getElementById('bt_phone');
    const wi = document.getElementById('bt_whatsapp');
    if (sc && sc.checked && pi && wi) {
      wi.value = pi.value;
    }
    const form = document.getElementById('docForm');
    const data = new FormData(form);
    data.set('action', 'autosave');
    if (idIn.value) data.set('autosave_id', idIn.value);

    fetch('ajax.php', { method: 'POST', body: data })
      .then(r => r.json())
      .then(res => {
        isSaving = false;
        if (res.ok) {
          idIn.value = res.id;
          // update the hidden original edit id so "View Document" link works
          lastSaved = res.saved_at;
          icon.innerHTML = '<i class="fa fa-cloud-upload-alt"></i>';
          showBar('Draft saved at ' + res.saved_at + ' &nbsp;<a href="view.php?id=' + encodeURIComponent(res.id) + '" target="_blank" style="color:inherit;font-weight:700;text-decoration:underline;">View draft →</a>', 'green');
        } else {
          icon.innerHTML = '<i class="fa fa-exclamation-triangle"></i>';
          showBar('Auto-save failed. Data is safe in browser.', 'red');
        }
      })
      .catch(() => {
        isSaving = false;
        icon.innerHTML = '<i class="fa fa-exclamation-triangle"></i>';
        showBar('Auto-save failed. Data is safe in browser.', 'red');
      });
  }

  function scheduleAutosave() {
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(doAutosave, 2000); // save 2s after last change
  }

  // Expose globally so BOQ calculator and row actions trigger autosave
  window.scheduleAutosave = scheduleAutosave;

  // Phone & WhatsApp input event listeners
  const phoneInputEl = document.getElementById('bt_phone');
  const waInputEl    = document.getElementById('bt_whatsapp');
  const sameCheckEl  = document.getElementById('same_as_mobile');

  if (phoneInputEl) {
    phoneInputEl.addEventListener('input', function() {
      const cleaned = cleanPhoneDigits(this.value);
      if (this.value !== cleaned && this.value.length >= 10) {
        this.value = cleaned;
      }
      if (sameCheckEl && sameCheckEl.checked && waInputEl) {
        waInputEl.value = this.value;
      }
    });
  }

  if (waInputEl) {
    waInputEl.addEventListener('input', function() {
      const cleaned = cleanPhoneDigits(this.value);
      if (this.value !== cleaned && this.value.length >= 10) {
        this.value = cleaned;
      }
    });
  }

  // Ensure auto-save listens to all form changes & reindex on submit
  const theDocForm = document.getElementById('docForm');
  if (theDocForm) {
    theDocForm.addEventListener('input', scheduleAutosave);
    theDocForm.addEventListener('change', scheduleAutosave);
    theDocForm.addEventListener('submit', function(e) {
      reindexRows();
      if (sameCheckEl && sameCheckEl.checked && phoneInputEl && waInputEl) {
        waInputEl.value = phoneInputEl.value;
      }
      const pVal = phoneInputEl ? phoneInputEl.value.trim() : '';
      const wVal = waInputEl ? waInputEl.value.trim() : '';

      if (!pVal || pVal.length < 10) {
        alert('Please enter a valid 10-digit Customer Mobile Number.');
        if (phoneInputEl) {
          phoneInputEl.focus();
          phoneInputEl.classList.add('is-invalid');
        }
        e.preventDefault();
        return false;
      }

      if (!wVal || wVal.length < 10) {
        alert('Please enter a valid 10-digit WhatsApp Number (or check "Same as Mobile Number").');
        if (waInputEl) {
          waInputEl.focus();
          waInputEl.classList.add('is-invalid');
        }
        e.preventDefault();
        return false;
      }
    });
  }

  // Trigger on any input/change in the form
  document.getElementById('docForm').addEventListener('input',  scheduleAutosave);
  document.getElementById('docForm').addEventListener('change', scheduleAutosave);

  // Also auto-save every 30 seconds regardless
  setInterval(doAutosave, 30000);

  // Initial auto-save after 5 seconds (to catch pre-filled forms)
  autosaveTimer = setTimeout(doAutosave, 5000);

  // Initial re-indexing on page load
  reindexRows();
})();

// ── Real-Time Auto-Save Functionality ──────────────────────────────────────────
let _autoSaveTimer = null;
function triggerAutoSave() {
  const docId = '<?= e($editId ?? '') ?>';
  if (!docId) return; // Only auto-save existing edited documents to prevent accidental new doc spam

  const indicator = document.getElementById('autoSaveIndicator');
  if (indicator) {
    indicator.className = 'badge bg-warning-subtle text-warning border border-warning py-1 px-2';
    indicator.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Saving draft...';
  }

  clearTimeout(_autoSaveTimer);
  _autoSaveTimer = setTimeout(() => {
    const form = document.querySelector('form');
    if (!form) return;
    const fd = new FormData(form);
    fd.append('action', 'autosave_doc');
    fd.append('edit_id', docId);

    fetch('ajax.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (indicator) {
          if (res.ok) {
            indicator.className = 'badge bg-success-subtle text-success border border-success py-1 px-2';
            indicator.innerHTML = '<i class="fa fa-circle-check me-1"></i>Auto-saved at ' + (res.time || new Date().toLocaleTimeString());
          } else {
            indicator.className = 'badge bg-secondary-subtle text-muted border py-1 px-2';
            indicator.innerHTML = '<i class="fa fa-cloud-arrow-up me-1"></i>Auto-save ready';
          }
        }
      })
      .catch(err => {
        console.error('Autosave error:', err);
        if (indicator) {
          indicator.className = 'badge bg-secondary-subtle text-muted border py-1 px-2';
          indicator.innerHTML = '<i class="fa fa-cloud-arrow-up me-1"></i>Auto-save ready';
        }
      });
  }, 1800);
}

// Attach auto-save to input & textarea changes
document.addEventListener('input', (e) => {
  if (e.target.matches('input, textarea, select')) {
    triggerAutoSave();
  }
});
document.addEventListener('change', (e) => {
  if (e.target.matches('input, textarea, select')) {
    triggerAutoSave();
  }
});


function onPaymentStatusChange() {
  const pStatus = document.querySelector('input[name="payment_status"]:checked')?.value || 'unpaid';
  const advBox = document.getElementById('advanceDetailsBox');
  const unpaidBox = document.getElementById('unpaidNoticeBox');
  const paidBox = document.getElementById('paidNoticeBox');
  const statusSelect = document.querySelector('select[name="status"]');
  
  if (advBox) advBox.style.display = (pStatus === 'partial') ? 'block' : 'none';
  if (unpaidBox) unpaidBox.style.display = (pStatus === 'unpaid') ? 'block' : 'none';
  if (paidBox) paidBox.style.display = (pStatus === 'paid') ? 'block' : 'none';

  if (pStatus === 'paid' && statusSelect) {
    statusSelect.value = 'paid';
  } else if (statusSelect && statusSelect.value === 'paid') {
    statusSelect.value = 'draft';
  }
  calcTotals();
}


function toggleShipAddress() {
  const isSame = document.getElementById('ship_same_as_bill')?.checked;
  const box = document.getElementById('shipAddressBox');
  if (box) box.style.display = isSame ? 'none' : 'block';
}

</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
