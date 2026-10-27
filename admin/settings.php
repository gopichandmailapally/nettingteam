<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$settings = getSettings();
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Document Numbering ────────────────────────────────────────
    if ($action === 'numbering') {
        $settings['numbering'] = [
            'est_prefix'  => strtoupper(trim($_POST['est_prefix']  ?? 'EST')),
            'pi_prefix'   => strtoupper(trim($_POST['pi_prefix']   ?? 'PI')),
            'inv_prefix'  => strtoupper(trim($_POST['inv_prefix']  ?? 'INV')),
            'bill_prefix' => strtoupper(trim($_POST['bill_prefix'] ?? 'BILL')),
            'separator'   => substr(trim($_POST['separator'] ?? '-'), 0, 1) ?: '-',
            'padding'     => max(1, min(6, (int)($_POST['padding'] ?? 3))),
        ];
        saveSettings($settings);
        flashSet('success', 'Document numbering settings saved.');
        redirect('settings.php');
    }

    // ── Tax & Document Defaults ───────────────────────────────────
    if ($action === 'defaults') {
        $settings['defaults']['cgst_rate'] = (float)($_POST['cgst_rate'] ?? 2.5);
        $settings['defaults']['sgst_rate'] = (float)($_POST['sgst_rate'] ?? 2.5);
        $settings['defaults']['igst_rate'] = (float)($_POST['igst_rate'] ?? 5);
        $settings['defaults']['gst_mode']  = in_array($_POST['gst_mode'] ?? '', ['cgst_sgst','igst']) ? $_POST['gst_mode'] : 'cgst_sgst';
        $settings['defaults']['doc_type']  = in_array($_POST['doc_type'] ?? '', ['estimation','tax_invoice','bill']) ? $_POST['doc_type'] : 'estimation';
        $settings['defaults']['discount']  = max(0, min(100, (float)($_POST['discount'] ?? 0)));
        $settings['defaults']['notes']     = trim($_POST['notes'] ?? '');
        $terms = array_values(array_filter(array_map('trim', explode("\n", $_POST['default_terms'] ?? ''))));
        $settings['defaults']['terms']     = $terms;
        $settings['defaults']['show_russea'] = !empty($_POST['default_show_russea']);
        saveSettings($settings);
        flashSet('success', 'Default settings saved.');
        redirect('settings.php');
    }

    // ── Turf, Pitch & Infill Pricing Catalog ─────────────────────
    if ($action === 'turf_pricing') {
        $catalog = getTurfCatalog();
        
        // Cricket Pitch
        $catalog['cricket_pitch']['rate_per_sft']         = max(1, (float)($_POST['pitch_rate_per_sft'] ?? 78.0));
        $catalog['cricket_pitch']['install_rate_per_sft'] = max(1, (float)($_POST['pitch_install_rate_per_sft'] ?? 9.0));
        $catalog['cricket_pitch']['boundary_offset_ft']   = max(0, (float)($_POST['pitch_boundary_offset_ft'] ?? 3.0));
        $catalog['cricket_pitch']['half_roll_cutoff_ft']  = max(20, (float)($_POST['pitch_half_roll_cutoff_ft'] ?? 48.0));
        $catalog['cricket_pitch']['width_ft']             = max(1, (float)($_POST['pitch_width_ft'] ?? 6.5));
        $catalog['cricket_pitch']['full_roll_sft']        = max(100, (float)($_POST['pitch_full_roll_sft'] ?? 540));
        $catalog['cricket_pitch']['half_roll_sft']        = max(50, (float)($_POST['pitch_half_roll_sft'] ?? 270));

        // Landscape Grass Rates
        $catalog['landscape_grass']['rates']['25mm'] = max(1, (float)($_POST['landscape_rate_25mm'] ?? 30.0));
        $catalog['landscape_grass']['rates']['30mm'] = max(1, (float)($_POST['landscape_rate_30mm'] ?? 35.0));
        $catalog['landscape_grass']['rates']['35mm'] = max(1, (float)($_POST['landscape_rate_35mm'] ?? 40.0));
        $catalog['landscape_grass']['rates']['40mm'] = max(1, (float)($_POST['landscape_rate_40mm'] ?? 45.0));

        // Landscape Installation Sliding Scale
        $catalog['landscape_grass']['install_tiers'] = [
            ['min_sft' => 0,    'max_sft' => 1500,   'rate' => max(1, (float)($_POST['landscape_inst_tier1'] ?? 8.0))],
            ['min_sft' => 1501, 'max_sft' => 3000,   'rate' => max(1, (float)($_POST['landscape_inst_tier2'] ?? 6.5))],
            ['min_sft' => 3001, 'max_sft' => 999999, 'rate' => max(1, (float)($_POST['landscape_inst_tier3'] ?? 5.0))],
        ];

        // Sports Grasses
        $catalog['sports_grass']['30mm_non_infill']['rate']         = max(1, (float)($_POST['sports_30mm_rate'] ?? 60.0));
        $catalog['sports_grass']['30mm_non_infill']['install_rate'] = max(1, (float)($_POST['sports_30mm_install'] ?? 9.0));

        $catalog['sports_grass']['50mm_12000_dtex']['rate']         = max(1, (float)($_POST['sports_50mm_12k_rate'] ?? 58.0));
        $catalog['sports_grass']['50mm_12000_dtex']['install_rate'] = max(1, (float)($_POST['sports_50mm_12k_install'] ?? 12.0));

        $catalog['sports_grass']['50mm_14000_dtex']['rate']         = max(1, (float)($_POST['sports_50mm_14k_rate'] ?? 64.0));
        $catalog['sports_grass']['50mm_14000_dtex']['install_rate'] = max(1, (float)($_POST['sports_50mm_14k_install'] ?? 12.0));

        $catalog['sports_grass']['50mm_16000_dtex']['rate']         = max(1, (float)($_POST['sports_50mm_16k_rate'] ?? 75.0));
        $catalog['sports_grass']['50mm_16000_dtex']['install_rate'] = max(1, (float)($_POST['sports_50mm_16k_install'] ?? 13.0));

        // Infill Standards
        $catalog['infill_standards']['silica_sand']['rate_per_kg']      = max(0.1, (float)($_POST['infill_sand_rate'] ?? 6.50));
        $catalog['infill_standards']['silica_sand']['kg_per_sqft']      = max(0.01, (float)($_POST['infill_sand_kg_sft'] ?? 2.207));

        $catalog['infill_standards']['rubber_granules']['rate_per_kg']  = max(0.1, (float)($_POST['infill_rubber_rate'] ?? 35.00));
        $catalog['infill_standards']['rubber_granules']['kg_per_sqft']  = max(0.01, (float)($_POST['infill_rubber_kg_sft'] ?? 0.617));

        $catalog['infill_standards']['joint_tape']['rate_per_mtr']      = max(0.1, (float)($_POST['infill_tape_rate'] ?? 45.00));
        $catalog['infill_standards']['pu_glue']['rate_per_kg']          = max(0.1, (float)($_POST['infill_glue_rate'] ?? 330.00));

        saveTurfCatalog($catalog);
        flashSet('success', 'Turf, Cricket Pitch & Infill pricing catalog saved successfully.');
        redirect('settings.php#turf-pricing');
    }

    // ── Branding uploads ──────────────────────────────────────────
    if ($action === 'upload') {
        $type = $_POST['upload_type'] ?? '';
        if (in_array($type, ['signature', 'stamp', 'logo'])) {
            $result = uploadFile('upload_file', $type);
            if ($result['ok']) {
                $settings[$type] = $result['path'];
                saveSettings($settings);
                flashSet('success', ucfirst($type) . ' uploaded successfully.');
            } else {
                flashSet('error', $result['msg']);
            }
        }
        redirect('settings.php');
    }

    if ($action === 'remove_upload') {
        $type = $_POST['upload_type'] ?? '';
        if (in_array($type, ['signature', 'stamp', 'logo'])) {
            $old = $settings[$type] ?? null;
            if ($old) {
                $path = __DIR__ . '/' . preg_replace('/\?.*$/', '', $old);
                if (file_exists($path)) unlink($path);
            }
            $settings[$type] = null;
            saveSettings($settings);
            flashSet('success', ucfirst($type) . ' removed.');
        }
        redirect('settings.php');
    }
}

$defaults    = $settings['defaults']  ?? [];
$turfCatalog = getTurfCatalog();
$cricketP    = $turfCatalog['cricket_pitch'] ?? [];
$landGrass   = $turfCatalog['landscape_grass'] ?? [];
$sportsG     = $turfCatalog['sports_grass'] ?? [];
$infillS     = $turfCatalog['infill_standards'] ?? [];
$numbering = $settings['numbering'] ?? [];

// Live preview helper
$sep  = $numbering['separator'] ?? '-';
$pad  = max(1, min(6, (int)($numbering['padding'] ?? 3)));
$ym   = date('Ym');
$previewEst  = ($numbering['est_prefix']  ?? 'EST')  . $sep . $ym . $sep . str_pad(1, $pad, '0', STR_PAD_LEFT);
$previewPi   = ($numbering['pi_prefix']   ?? 'PI')   . $sep . $ym . $sep . str_pad(1, $pad, '0', STR_PAD_LEFT);
$previewInv  = ($numbering['inv_prefix']  ?? 'INV')  . $sep . $ym . $sep . str_pad(1, $pad, '0', STR_PAD_LEFT);
$previewBill = ($numbering['bill_prefix'] ?? 'BILL') . $sep . $ym . $sep . str_pad(1, $pad, '0', STR_PAD_LEFT);

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="row g-3">

  <!-- LEFT COLUMN -->
  <div class="col-lg-8">

    <!-- ── Document Numbering ── -->
    <div class="card p-3 mb-3">
      <div class="card-section-title"><span class="accent-dot"></span><i class="fa fa-hashtag me-1"></i>Document Numbering</div>
      <p class="text-muted mb-3" style="font-size:.78rem;">Customise the prefix and format for each document type. Changes apply to new documents only.</p>
      <form method="POST" id="numForm">
        <input type="hidden" name="action" value="numbering">
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <label class="form-label">Estimation Prefix</label>
            <input type="text" name="est_prefix" id="pfxEst" class="form-control form-control-sm" value="<?= e($numbering['est_prefix'] ?? 'EST') ?>" maxlength="6" style="text-transform:uppercase;" oninput="updatePreview()">
          </div>
          <div class="col-md-3">
            <label class="form-label">Proforma Prefix</label>
            <input type="text" name="pi_prefix" id="pfxPi" class="form-control form-control-sm" value="<?= e($numbering['pi_prefix'] ?? 'PI') ?>" maxlength="6" style="text-transform:uppercase;" oninput="updatePreview()">
          </div>
          <div class="col-md-3">
            <label class="form-label">Invoice Prefix</label>
            <input type="text" name="inv_prefix" id="pfxInv" class="form-control form-control-sm" value="<?= e($numbering['inv_prefix'] ?? 'INV') ?>" maxlength="6" style="text-transform:uppercase;" oninput="updatePreview()">
          </div>
          <div class="col-md-3">
            <label class="form-label">Bill Prefix</label>
            <input type="text" name="bill_prefix" id="pfxBill" class="form-control form-control-sm" value="<?= e($numbering['bill_prefix'] ?? 'BILL') ?>" maxlength="6" style="text-transform:uppercase;" oninput="updatePreview()">
          </div>
          <div class="col-md-3">
            <label class="form-label">Separator</label>
            <select name="separator" id="pfxSep" class="form-select form-select-sm" onchange="updatePreview()">
              <?php foreach (['-'=>'Hyphen  ( - )','_'=>'Underscore  ( _ )','/'=> 'Slash  ( / )'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($numbering['separator'] ?? '-') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Number Digits</label>
            <select name="padding" id="pfxPad" class="form-select form-select-sm" onchange="updatePreview()">
              <?php foreach ([2=>'2 digits (01)',3=>'3 digits (001)',4=>'4 digits (0001)'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($numbering['padding'] ?? 3) == $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-9">
            <label class="form-label">Live Preview</label>
            <div id="numPreview" style="display:flex;gap:.5rem;flex-wrap:wrap;padding:.45rem .75rem;background:#f8f9fc;border:1.5px solid var(--border);border-radius:9px;font-size:.8rem;">
              <span class="type-badge type-est" id="prev-est"><?= e($previewEst) ?></span>
              <span class="type-badge type-pi" id="prev-pi"><?= e($previewPi) ?></span>
              <span class="type-badge type-inv" id="prev-inv"><?= e($previewInv) ?></span>
              <span class="type-badge type-bill" id="prev-bill"><?= e($previewBill) ?></span>
            </div>
          </div>
        </div>
        <button class="btn btn-sm btn-primary"><i class="fa fa-save me-1"></i>Save Numbering</button>
      </form>
    </div>

    <!-- ── Tax Rates & Document Defaults ── -->
    <div class="card p-3 mb-3">
      <div class="card-section-title"><span class="accent-dot"></span><i class="fa fa-sliders me-1"></i>Document Defaults</div>
      <p class="text-muted mb-3" style="font-size:.78rem;">Pre-filled values for every new document — can be changed per document.</p>
      <form method="POST">
        <input type="hidden" name="action" value="defaults">

        <div class="row g-3 mb-3">
          <!-- Default doc type -->
          <div class="col-md-4">
            <label class="form-label">Default Document Type</label>
            <div class="d-flex flex-column gap-1">
              <?php foreach (['estimation'=>'Estimation','tax_invoice'=>'Tax Invoice','bill'=>'Bill'] as $v=>$l): ?>
              <label style="display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;background:#f8f9fc;border:1.5px solid <?= ($defaults['doc_type']??'estimation')===$v?'rgba(192,57,43,.4)':'var(--border)' ?>;border-radius:8px;cursor:pointer;">
                <input type="radio" name="doc_type" value="<?= $v ?>" <?= ($defaults['doc_type']??'estimation')===$v?'checked':'' ?> style="accent-color:var(--red);">
                <span style="font-size:.82rem;font-weight:500;"><?= $l ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <!-- Default GST mode -->
          <div class="col-md-4">
            <label class="form-label">Default GST Mode</label>
            <div class="d-flex flex-column gap-1">
              <label style="display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;background:#f8f9fc;border:1.5px solid <?= ($defaults['gst_mode']??'cgst_sgst')==='cgst_sgst'?'rgba(192,57,43,.4)':'var(--border)' ?>;border-radius:8px;cursor:pointer;">
                <input type="radio" name="gst_mode" value="cgst_sgst" <?= ($defaults['gst_mode']??'cgst_sgst')==='cgst_sgst'?'checked':'' ?> style="accent-color:var(--red);">
                <span style="font-size:.82rem;font-weight:500;">CGST + SGST (Intrastate)</span>
              </label>
              <label style="display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;background:#f8f9fc;border:1.5px solid <?= ($defaults['gst_mode']??'')==='igst'?'rgba(192,57,43,.4)':'var(--border)' ?>;border-radius:8px;cursor:pointer;">
                <input type="radio" name="gst_mode" value="igst" <?= ($defaults['gst_mode']??'')==='igst'?'checked':'' ?> style="accent-color:var(--red);">
                <span style="font-size:.82rem;font-weight:500;">IGST (Interstate)</span>
              </label>
            </div>
          </div>
          <!-- Tax rates -->
          <div class="col-md-4">
            <label class="form-label">Default Tax Rates</label>
            <div class="d-flex flex-column gap-2">
              <div class="d-flex align-items-center gap-2">
                <span style="font-size:.78rem;font-weight:600;width:40px;color:var(--muted);">CGST</span>
                <input type="number" name="cgst_rate" class="form-control form-control-sm" value="<?= e($defaults['cgst_rate'] ?? 2.5) ?>" step="0.5" min="0" max="50" style="width:70px;">
                <span style="font-size:.78rem;color:var(--muted);">%</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span style="font-size:.78rem;font-weight:600;width:40px;color:var(--muted);">SGST</span>
                <input type="number" name="sgst_rate" class="form-control form-control-sm" value="<?= e($defaults['sgst_rate'] ?? 2.5) ?>" step="0.5" min="0" max="50" style="width:70px;">
                <span style="font-size:.78rem;color:var(--muted);">%</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span style="font-size:.78rem;font-weight:600;width:40px;color:var(--muted);">IGST</span>
                <input type="number" name="igst_rate" class="form-control form-control-sm" value="<?= e($defaults['igst_rate'] ?? 5) ?>" step="0.5" min="0" max="50" style="width:70px;">
                <span style="font-size:.78rem;color:var(--muted);">%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Default discount + notes -->
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <label class="form-label">Default Discount %</label>
            <div class="input-group input-group-sm">
              <input type="number" name="discount" class="form-control form-control-sm" value="<?= e($defaults['discount'] ?? 0) ?>" min="0" max="100" step="0.5">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-md-9">
            <label class="form-label">Default Notes <small class="text-muted">(shown on every new document)</small></label>
            <input type="text" name="notes" class="form-control form-control-sm" value="<?= e($defaults['notes'] ?? '') ?>" placeholder="e.g. Thank you for your business!">
          </div>
        </div>

        <!-- Default Terms -->
        <div class="mb-3">
          <label class="form-label">Default Terms &amp; Conditions <small class="text-muted">(one per line)</small></label>
          <textarea name="default_terms" class="form-control form-control-sm" rows="5" placeholder="Enter each term on a new line..."><?= e(implode("\n", $defaults['terms'] ?? [])) ?></textarea>
        </div>

        <!-- Default Russea Branding Toggle -->
        <div class="mb-3 p-3 rounded" style="background:#f8f9fc;border:1.5px solid #e2e8f0;">
          <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" name="default_show_russea" value="1" id="switch_default_russea" <?= ($defaults['show_russea'] ?? true) ? 'checked' : '' ?> style="cursor:pointer;">
            <label class="form-check-label fw-bold small text-dark" for="switch_default_russea" style="cursor:pointer;">
              <i class="fa fa-tag text-danger me-1"></i>Enable Russea™ Brand by Default on New Documents
            </label>
          </div>
          <div class="text-muted" style="font-size:.76rem;line-height:1.4;">
            When enabled, the "Authorized Dealer Russea™ Brand" badge and bottom strip will be turned on by default for new Estimations, Tax Invoices, and Bills. You can also turn it on or off for any individual document anytime with 1-click in the document viewer.
          </div>
        </div>

        <button class="btn btn-sm btn-danger"><i class="fa fa-save me-1"></i>Save Defaults</button>
      </form>
    </div>

    <!-- ── Turf, Cricket Pitch & Infill Pricing Manager ── -->
    <div class="card p-3 mb-3" id="turf-pricing" style="border-top:3px solid #16a34a !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="card-section-title mb-0">
          <span class="accent-dot" style="background:#16a34a;"></span>
          <i class="fa fa-seedling me-1 text-success"></i>Turf, Cricket Pitch &amp; Infill Pricing Manager
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.7rem;">
          <i class="fa fa-check-circle me-1"></i>Central Catalog (admin/data/turf_catalog.json)
        </span>
      </div>
      <p class="text-muted mb-3" style="font-size:.78rem;">
        Configure rates and standard formulas for Artificial Grass, Cricket Pitch Turf Strips, DTEX-rated Sports Turf, and Chemical Infill Materials (Silica Sand, SBR Rubber, Seam Tape, and PU Glue). Changes apply immediately to new Estimations, Invoices, and the dynamic Turnkey Estimator.
      </p>

      <form method="POST">
        <input type="hidden" name="action" value="turf_pricing">

        <!-- 1. Center Cricket Pitch Turf Strip -->
        <div class="p-3 mb-3 rounded" style="background:#f8fafc; border:1px solid #cbd5e1;">
          <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="fw-bold text-dark" style="font-size:.84rem;">
              <i class="fa fa-cricket-bat-ball text-warning me-1"></i>1. Center Artificial Cricket Pitch Turf (15mm Curled Wicket Strip)
            </span>
            <span class="badge bg-warning-subtle text-dark border" style="font-size:.68rem;">Roll Width: 6.5 FT (2.0 MTR)</span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">Material Rate (₹ / SFT)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="pitch_rate_per_sft" class="form-control fw-bold" value="<?= e($cricketP['rate_per_sft'] ?? 78.0) ?>">
              </div>
              <div class="text-muted" style="font-size:.68rem;">Full Roll: 540 SFT, Half Roll: 270 SFT</div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">Installation Rate (₹ / SFT)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="pitch_install_rate_per_sft" class="form-control fw-bold" value="<?= e($cricketP['install_rate_per_sft'] ?? 9.0) ?>">
              </div>
              <div class="text-muted" style="font-size:.68rem;">Billed on actual installed area only</div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">Boundary Clearance Offset (FT)</label>
              <div class="input-group input-group-sm">
                <input type="number" step="0.5" name="pitch_boundary_offset_ft" class="form-control fw-bold" value="<?= e($cricketP['boundary_offset_ft'] ?? 3.0) ?>">
                <span class="input-group-text">FT</span>
              </div>
              <div class="text-muted" style="font-size:.68rem;">Offset from end structures (both ends)</div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">Half-Roll Cutoff Court Length</label>
              <div class="input-group input-group-sm">
                <input type="number" step="1" name="pitch_half_roll_cutoff_ft" class="form-control fw-bold" value="<?= e($cricketP['half_roll_cutoff_ft'] ?? 48.0) ?>">
                <span class="input-group-text">FT</span>
              </div>
              <div class="text-muted" style="font-size:.68rem;">&lt; 48 FT uses 270 SFT half roll</div>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label mb-1" style="font-size:.75rem;">Strip Width (FT)</label>
              <input type="number" step="0.1" name="pitch_width_ft" class="form-control form-control-sm" value="<?= e($cricketP['width_ft'] ?? 6.5) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1" style="font-size:.75rem;">Full Roll Area (SFT)</label>
              <input type="number" step="1" name="pitch_full_roll_sft" class="form-control form-control-sm" value="<?= e($cricketP['full_roll_sft'] ?? 540) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1" style="font-size:.75rem;">Half Roll Area (SFT)</label>
              <input type="number" step="1" name="pitch_half_roll_sft" class="form-control form-control-sm" value="<?= e($cricketP['half_roll_sft'] ?? 270) ?>">
            </div>
          </div>
        </div>

        <!-- 2. Landscape Grass Rates & Sliding Scale -->
        <div class="p-3 mb-3 rounded" style="background:#f8fafc; border:1px solid #cbd5e1;">
          <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="fw-bold text-dark" style="font-size:.84rem;">
              <i class="fa fa-leaf text-success me-1"></i>2. Landscape Sports Grass (Material Rates &amp; Sliding Installation)
            </span>
            <span class="badge bg-success-subtle text-dark border" style="font-size:.68rem;">No Infill Required</span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">25mm Grass Rate (₹/SFT)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="landscape_rate_25mm" class="form-control fw-bold" value="<?= e($landGrass['rates']['25mm'] ?? 30.0) ?>">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">30mm Grass Rate (₹/SFT)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="landscape_rate_30mm" class="form-control fw-bold" value="<?= e($landGrass['rates']['30mm'] ?? 35.0) ?>">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">35mm Grass Rate (₹/SFT)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="landscape_rate_35mm" class="form-control fw-bold" value="<?= e($landGrass['rates']['35mm'] ?? 40.0) ?>">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1" style="font-size:.75rem;">40mm Grass Rate (₹/SFT)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="landscape_rate_40mm" class="form-control fw-bold" value="<?= e($landGrass['rates']['40mm'] ?? 45.0) ?>">
              </div>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label mb-1 fw-bold text-dark" style="font-size:.75rem;">Landscape Installation Charges Sliding Scale (₹ / SFT by total court area)</label>
            </div>
            <div class="col-md-4">
              <div class="input-group input-group-sm">
                <span class="input-group-text">&lt; 1,500 SFT</span>
                <input type="number" step="0.5" name="landscape_inst_tier1" class="form-control fw-bold" value="<?= e($landGrass['install_tiers'][0]['rate'] ?? 8.0) ?>">
                <span class="input-group-text">₹/SFT</span>
              </div>
            </div>
            <div class="col-md-4">
              <div class="input-group input-group-sm">
                <span class="input-group-text">1,501 - 3,000 SFT</span>
                <input type="number" step="0.5" name="landscape_inst_tier2" class="form-control fw-bold" value="<?= e($landGrass['install_tiers'][1]['rate'] ?? 6.5) ?>">
                <span class="input-group-text">₹/SFT</span>
              </div>
            </div>
            <div class="col-md-4">
              <div class="input-group input-group-sm">
                <span class="input-group-text">&gt; 3,000 SFT</span>
                <input type="number" step="0.5" name="landscape_inst_tier3" class="form-control fw-bold" value="<?= e($landGrass['install_tiers'][2]['rate'] ?? 5.0) ?>">
                <span class="input-group-text">₹/SFT</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Sports Grasses by DTEX & Non-Infill -->
        <div class="p-3 mb-3 rounded" style="background:#f8fafc; border:1px solid #cbd5e1;">
          <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="fw-bold text-dark" style="font-size:.84rem;">
              <i class="fa fa-futbol text-primary me-1"></i>3. Sports Grasses by DTEX &amp; Non-Infill
            </span>
            <span class="badge bg-primary-subtle text-primary border" style="font-size:.68rem;">Box Cricket &amp; Football</span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label mb-1" style="font-size:.75rem;">30mm Non-Infill Sports Grass Rate</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="sports_30mm_rate" class="form-control fw-bold" value="<?= e($sportsG['30mm_non_infill']['rate'] ?? 60.0) ?>">
                <span class="input-group-text">/ SFT</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label mb-1" style="font-size:.75rem;">30mm Non-Infill Installation Rate</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="sports_30mm_install" class="form-control fw-bold" value="<?= e($sportsG['30mm_non_infill']['install_rate'] ?? 9.0) ?>">
                <span class="input-group-text">/ SFT</span>
              </div>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label mb-1" style="font-size:.75rem;">50mm Turf (12,000 DTEX) Rate</label>
              <div class="input-group input-group-sm mb-1">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="sports_50mm_12k_rate" class="form-control fw-bold" value="<?= e($sportsG['50mm_12000_dtex']['rate'] ?? 58.0) ?>">
              </div>
              <div class="input-group input-group-sm">
                <span class="input-group-text" style="font-size:.7rem;">Install ₹</span>
                <input type="number" step="0.5" name="sports_50mm_12k_install" class="form-control fw-bold" value="<?= e($sportsG['50mm_12000_dtex']['install_rate'] ?? 12.0) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1" style="font-size:.75rem;">50mm Turf (14,000 DTEX) Rate</label>
              <div class="input-group input-group-sm mb-1">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="sports_50mm_14k_rate" class="form-control fw-bold" value="<?= e($sportsG['50mm_14000_dtex']['rate'] ?? 64.0) ?>">
              </div>
              <div class="input-group input-group-sm">
                <span class="input-group-text" style="font-size:.7rem;">Install ₹</span>
                <input type="number" step="0.5" name="sports_50mm_14k_install" class="form-control fw-bold" value="<?= e($sportsG['50mm_14000_dtex']['install_rate'] ?? 12.0) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1" style="font-size:.75rem;">50mm Turf (16,000 DTEX) Rate</label>
              <div class="input-group input-group-sm mb-1">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="sports_50mm_16k_rate" class="form-control fw-bold" value="<?= e($sportsG['50mm_16000_dtex']['rate'] ?? 75.0) ?>">
              </div>
              <div class="input-group input-group-sm">
                <span class="input-group-text" style="font-size:.7rem;">Install ₹</span>
                <input type="number" step="0.5" name="sports_50mm_16k_install" class="form-control fw-bold" value="<?= e($sportsG['50mm_16000_dtex']['install_rate'] ?? 13.0) ?>">
              </div>
            </div>
          </div>
        </div>

        <!-- 4. Chemical Infill Standards & Rates (Derived from EST-031, 032, 033) -->
        <div class="p-3 mb-3 rounded" style="background:#f8fafc; border:1px solid #cbd5e1;">
          <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="fw-bold text-dark" style="font-size:.84rem;">
              <i class="fa fa-flask text-danger me-1"></i>4. Chemical &amp; Infill Material Standards (From EST-202609-031, 032, 033)
            </span>
            <span class="badge bg-danger-subtle text-danger border" style="font-size:.68rem;">Auto-calculated for 50mm Infill Turf</span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label mb-1" style="font-size:.75rem;">Silica Sand Rate (₹ / KG)</label>
              <div class="input-group input-group-sm mb-1">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.1" name="infill_sand_rate" class="form-control fw-bold" value="<?= e($infillS['silica_sand']['rate_per_kg'] ?? 6.50) ?>">
              </div>
              <div class="input-group input-group-sm">
                <span class="input-group-text" style="font-size:.7rem;">Dosage</span>
                <input type="number" step="0.001" name="infill_sand_kg_sft" class="form-control fw-bold" value="<?= e($infillS['silica_sand']['kg_per_sqft'] ?? 2.207) ?>">
                <span class="input-group-text" style="font-size:.7rem;">KG / SFT (~23.75 kg/m²)</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label mb-1" style="font-size:.75rem;">SBR Rubber Granules Rate (₹ / KG)</label>
              <div class="input-group input-group-sm mb-1">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="infill_rubber_rate" class="form-control fw-bold" value="<?= e($infillS['rubber_granules']['rate_per_kg'] ?? 35.00) ?>">
              </div>
              <div class="input-group input-group-sm">
                <span class="input-group-text" style="font-size:.7rem;">Dosage</span>
                <input type="number" step="0.001" name="infill_rubber_kg_sft" class="form-control fw-bold" value="<?= e($infillS['rubber_granules']['kg_per_sqft'] ?? 0.617) ?>">
                <span class="input-group-text" style="font-size:.7rem;">KG / SFT (~6.64 kg/m²)</span>
              </div>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label mb-1" style="font-size:.75rem;">White Joint Seaming Tape Rate (₹ / MTR)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.5" name="infill_tape_rate" class="form-control fw-bold" value="<?= e($infillS['joint_tape']['rate_per_mtr'] ?? 45.00) ?>">
                <span class="input-group-text">/ MTR (~0.0386 m/sft)</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label mb-1" style="font-size:.75rem;">Speb 7 PU Adhesive Glue Rate (₹ / KG)</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="1.0" name="infill_glue_rate" class="form-control fw-bold" value="<?= e($infillS['pu_glue']['rate_per_kg'] ?? 330.00) ?>">
                <span class="input-group-text">/ KG (~0.010 kg/sft)</span>
              </div>
            </div>
          </div>
        </div>

        <button class="btn btn-sm btn-success fw-bold px-3">
          <i class="fa fa-save me-1"></i>Save Turf, Pitch &amp; Infill Pricing
        </button>
      </form>
    </div>

  </div>

  <!-- RIGHT COLUMN: Branding Assets -->
  <div class="col-lg-4">

    <div class="card p-3 mb-3" style="border-top:3px solid var(--red) !important;">
      <div class="card-section-title"><span class="accent-dot"></span><i class="fa fa-palette me-1"></i>Global Branding Assets</div>
      <p class="text-muted mb-3" style="font-size:.75rem;">Default signature, stamp and logo — used when a company doesn't have its own. Manage per-company assets in <a href="companies.php" style="color:var(--red);">Companies</a>.</p>

      <?php foreach ([
        'signature' => ['Signature',    'fa-signature', 'Use a PNG with transparent background'],
        'stamp'     => ['Company Stamp','fa-stamp',     'Circular stamp image works best'],
        'logo'      => ['Company Logo', 'fa-image',     'PNG or WebP, square ratio preferred'],
      ] as $key => [$label, $icon, $hint]):
        $val     = $settings[$key] ?? null;
        $imgPath = $val ? preg_replace('/\?.*$/', '', $val) : null;
        $exists  = $imgPath && file_exists(__DIR__ . '/' . $imgPath);
      ?>
      <div class="mb-3 pb-3" style="border-bottom:1px solid var(--border);">
        <div style="font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:.5rem;">
          <i class="fa <?= $icon ?> me-1"></i><?= $label ?>
        </div>
        <?php if ($exists): ?>
        <div class="d-flex align-items-center gap-2 mb-2">
          <div style="width:64px;height:48px;background:#f8f9fc;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            <img src="<?= e($val) ?>" style="max-width:60px;max-height:44px;object-fit:contain;">
          </div>
          <div style="flex:1;">
            <div style="font-size:.75rem;font-weight:600;color:var(--text);"><?= $label ?> uploaded</div>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="remove_upload">
              <input type="hidden" name="upload_type" value="<?= $key ?>">
              <button class="tbtn tbtn-ghost" style="font-size:.7rem;padding:.2rem .55rem;color:#c0392b;border-color:rgba(192,57,43,.3);margin-top:.25rem;"><i class="fa fa-trash me-1"></i>Remove</button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <div class="text-center py-2 mb-2 rounded" style="background:#f8f9fc;border:2px dashed var(--border);">
          <i class="fa <?= $icon ?> fa-lg" style="opacity:.2;color:var(--muted);"></i>
          <div style="font-size:.72rem;color:var(--muted);margin-top:.25rem;">Not uploaded</div>
        </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="<?= $key ?>">
          <input type="file" name="upload_file" class="form-control form-control-sm mb-1" accept="image/*" required>
          <div style="font-size:.7rem;color:var(--muted);margin-bottom:.4rem;"><?= $hint ?></div>
          <button class="btn btn-sm btn-outline-primary w-100"><i class="fa fa-upload me-1"></i>Upload <?= $label ?></button>
        </form>
      </div>
      <?php endforeach; ?>

      <!-- Last item strip border -->
      <style>.col-lg-4 .card .mb-3:last-of-type{border-bottom:none!important;margin-bottom:0!important;padding-bottom:0!important;}</style>
    </div>

    <!-- Quick links card -->
    <div class="card p-3">
      <div class="card-section-title"><span class="accent-dot"></span><i class="fa fa-arrow-up-right-from-square me-1"></i>Quick Links</div>
      <div class="d-flex flex-column gap-2">
        <a href="companies.php" class="qa-btn qa-btn-red" style="justify-content:flex-start;">
          <i class="fa fa-building"></i> Manage Companies &amp; Bank Details
        </a>
        <a href="security.php" class="qa-btn qa-btn-blue" style="justify-content:flex-start;">
          <i class="fa fa-shield-halved"></i> Security &amp; 2FA Settings
        </a>
        <a href="customers.php" class="qa-btn qa-btn-green" style="justify-content:flex-start;">
          <i class="fa fa-users"></i> Manage Customers
        </a>
        <a href="documents.php" class="qa-btn qa-btn-gray" style="justify-content:flex-start;">
          <i class="fa fa-file-lines"></i> All Documents
        </a>
      </div>
    </div>

  </div>
</div>

<script>
const ym = '<?= date('Ym') ?>';
function updatePreview() {
  const est  = (document.getElementById('pfxEst').value  || 'EST').toUpperCase();
  const inv  = (document.getElementById('pfxInv').value  || 'INV').toUpperCase();
  const bill = (document.getElementById('pfxBill').value || 'BILL').toUpperCase();
  const sep  = document.getElementById('pfxSep').value;
  const pad  = parseInt(document.getElementById('pfxPad').value) || 3;
  const num  = '1'.padStart(pad, '0');
  document.getElementById('prev-est').textContent  = est  + sep + ym + sep + num;
  document.getElementById('prev-inv').textContent  = inv  + sep + ym + sep + num;
  document.getElementById('prev-bill').textContent = bill + sep + ym + sep + num;
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
