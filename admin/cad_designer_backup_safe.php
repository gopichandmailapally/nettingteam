<?php
/**
 * NETTINGTEAM — ADVANCED VISUAL AUTOCAD & AUTODESK REVIT BIM DESIGN STUDIO
 * 
 * Interactive Web-based CAD/BIM Drawing Board & Parametric Object Specification Engine:
 *  - 2D Precision Drafting Grid with interactive wall stretching and dimension handles
 *  - 3D WebGL BIM Model with Orbit Controls, Raycasting & Direct Click-to-Edit on ANY element
 *  - Universal Component Specification & Product Modifier Modal (Brand, Gauge, Rate, HSN, Bullets)
 *  - Master Product Catalog (281 real engineering products across Roofing, Steel, Netting, Turf, Vinyl, Lighting, Civil)
 *  - Bi-Directional Associativity: Modify ANY item in 3D, 2D Plan, Elevations, or Admin Panel -> updates everywhere
 *  - Specific Side Customizer (North, South, East, West, Roof, Floor)
 *  - Multi-Sport Complex (Box Cricket, Indoor Badminton, Amenities) with Zone Demarcations
 *  - Live Bill of Quantities (BOQ) with Itemized Breakdown, 18% GST & Grand Total
 *  - One-Click Save to Formal Quotation & AutoCAD .DXF Export
 */

// Prevent browser caching of dynamic CAD designer
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('documents','edit');
require_once __DIR__ . '/includes/turnkey_calculator.php';

$settings  = getSettings();
$customers = getAllCustomers();
$defaults  = $settings['defaults'] ?? [];

// Edit document ID
$editId  = $_GET['edit'] ?? $_GET['id'] ?? null;

$editDoc = $editId ? getDocument($editId) : null;
$tkCfg   = $editDoc['turnkey_config'] ?? [];

// Initial values from existing document or intelligent defaults
$initL = (float)($tkCfg['length'] ?? ($editDoc['length'] ?? 100));
$initW = (float)($tkCfg['width'] ?? ($editDoc['width'] ?? 60));
$initH = (float)($tkCfg['height'] ?? ($editDoc['height'] ?? 25));
$initSport = $tkCfg['sport_type'] ?? ($editDoc['sport_type'] ?? 'box_cricket');
$initRoof  = $tkCfg['roof_type'] ?? ($editDoc['roof_type'] ?? 'curved_truss');
$initPkg   = $tkCfg['package'] ?? 'commercial';
$initPitch = (float)($tkCfg['pitch_length'] ?? 66);
$initGates = $tkCfg['gates'] ?? [['wall' => 'front_width', 'position' => 50, 'width_ft' => 6, 'height_ft' => 7]];
$initOpenSides = $tkCfg['open_sides'] ?? [];
$initLayers    = $tkCfg['layers'] ?? [];
$initZones     = $tkCfg['zones'] ?? null;
$initCustomSpecs = $tkCfg['custom_specs'] ?? null;

// Check if top roof / netting was explicitly removed/excluded
$isTopExcluded = !empty($initOpenSides['top']);
if (!empty($tkCfg['excluded_items']) && is_array($tkCfg['excluded_items'])) {
    foreach ($tkCfg['excluded_items'] as $ex) {
        $desc = strtolower($ex['description'] ?? '');
        if (str_contains($desc, 'roof') || str_contains($desc, 'top net') || str_contains($desc, 'overhead')) {
            $isTopExcluded = true;
        }
    }
}

// Wall and roof claddings
$initCladding = $tkCfg['claddings'] ?? [
    'roof'  => $isTopExcluded ? 'open' : ($initRoof === 'curved_truss' ? 'ppgl_sheet' : ($initRoof === 'light_cable_top' ? 'netting' : 'open')),
    'front' => !empty($initOpenSides['front']) ? 'open' : (!empty($tkCfg['has_all_sides_gi_sheets']) ? 'ppgl_sheet' : 'netting'),
    'back'  => !empty($initOpenSides['back']) ? 'open' : (!empty($tkCfg['has_all_sides_gi_sheets']) ? 'ppgl_sheet' : 'netting'),
    'left'  => !empty($initOpenSides['left']) ? 'open' : (!empty($tkCfg['has_all_sides_gi_sheets']) ? 'ppgl_sheet' : 'netting'),
    'right' => !empty($initOpenSides['right']) ? 'open' : (!empty($tkCfg['has_all_sides_gi_sheets']) ? 'ppgl_sheet' : 'netting'),
    'floor' => ($initSport === 'badminton') ? 'vinyl_mat' : 'sports_turf'
];
if ($isTopExcluded) {
    $initCladding['roof'] = 'open';
}

// AJAX Save Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save_cad') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid JSON payload']);
        exit;
    }

    $docId = (!empty($payload['id']) && $payload['id'] !== 'NEW-ESTIMATION' && strpos($payload['id'], 'NEW-') !== 0) ? $payload['id'] : (!empty($editId) ? $editId : generateDocNumber('estimation'));
    $doc = getDocument($docId) ?? [
        'id'               => $docId,
        'type'             => 'estimation',
        'date'             => date('Y-m-d'),
        'status'           => 'draft',
        'payment_status'   => 'unpaid',
        'estimation_subtype' => 'turnkey_court',
        'created_at'       => date('c')
    ];

    $doc['updated_at'] = date('c');
    $doc['estimation_subtype'] = 'turnkey_court';

    $L = (float)($payload['length'] ?? 100);
    $W = (float)($payload['width'] ?? 60);
    $H = (float)($payload['height'] ?? 25);
    $sport = $payload['sport_type'] ?? 'box_cricket';
    $roof  = ($payload['claddings']['roof'] ?? '') === 'ppgl_sheet' ? 'curved_truss' : 'light_cable_top';
    $pkg   = $payload['package'] ?? 'commercial';

    // Compile customized item specifications into official proposal items
    $compiledItems = [];
    if (!empty($payload['components']) && is_array($payload['components'])) {
        foreach ($payload['components'] as $cId => $c) {
            if (empty($c['included'])) continue;
            $qty = (float)($c['qty'] ?? 1);
            $rate = (float)($c['rate'] ?? 0);
            $amt = round($qty * $rate, 2);
            $specsList = [];
            if (!empty($c['brand'])) $specsList[] = "Brand / Manufacturer: " . $c['brand'];
            if (!empty($c['gauge'])) $specsList[] = "Material Specification: " . $c['gauge'];
            if (!empty($c['bullets']) && is_array($c['bullets'])) {
                $specsList = array_merge($specsList, $c['bullets']);
            }
            $compiledItems[] = [
                'description' => ($c['name'] ?? $cId) . "\n" . ($c['spec'] ?? ''),
                'hsn'         => $c['hsn'] ?? '7308',
                'qty'         => $qty,
                'unit'        => $c['unit'] ?? 'SQFT',
                'rate'        => $rate,
                'amount'      => $amt,
                'specs'       => $specsList,
                'gst_rate'    => (float)($c['gst_rate'] ?? 18)
            ];
        }
    }

    if (!empty($compiledItems)) {
        $doc['items'] = $compiledItems;
        $doc['subtotal'] = (float)($payload['subtotal'] ?? 0);
    } else {
        // Fallback to standard Turnkey BOQ generator
        $boqData = generateTurnkeyBOQ($L, $W, $H, $sport, $roof, $pkg);
        $doc['items'] = $boqData['doc_items'] ?? ($boqData['items'] ?? []);
        $doc['subtotal'] = (float)($payload['subtotal'] ?? ($boqData['boq_subtotal'] ?? 0));
    }

    $doc['taxable_value'] = $doc['subtotal'];
    $doc['is_gst'] = !empty($payload['is_gst']);
    $doc['gst_mode'] = 'cgst_sgst';
    $gstRate = $doc['is_gst'] ? 18.0 : 0.0;
    $gstAmount = round($doc['taxable_value'] * ($gstRate / 100), 2);
    $doc['cgst_rate'] = $doc['is_gst'] ? 9.0 : 0.0;
    $doc['sgst_rate'] = $doc['is_gst'] ? 9.0 : 0.0;
    $doc['cgst_amount'] = round($gstAmount / 2, 2);
    $doc['sgst_amount'] = round($gstAmount / 2, 2);
    $doc['grand_total'] = $doc['taxable_value'] + $gstAmount;
    $doc['amount_in_words'] = convertAmountToIndianWords($doc['grand_total']);

    // Turnkey configuration with custom specs
    $doc['turnkey_config'] = array_merge($doc['turnkey_config'] ?? [], [
        'length'                 => $L,
        'width'                  => $W,
        'height'                 => $H,
        'sport_type'             => $sport,
        'roof_type'              => $roof,
        'package'                => $pkg,
        'pitch_length'           => (float)($payload['pitch_length'] ?? 66),
        'gate_count'             => count($payload['gates'] ?? [1]),
        'gates'                  => $payload['gates'] ?? [],
        'claddings'              => $payload['claddings'] ?? [],
        'has_all_sides_gi_sheets'=> ($payload['claddings']['front'] ?? '') === 'ppgl_sheet' && ($payload['claddings']['back'] ?? '') === 'ppgl_sheet' && ($payload['claddings']['left'] ?? '') === 'ppgl_sheet' && ($payload['claddings']['right'] ?? '') === 'ppgl_sheet',
        'zones'                  => $payload['zones'] ?? null,
        'custom_specs'           => $payload['components'] ?? null
    ]);

    saveDocument($doc);
    echo json_encode([
        'ok' => true,
        'id' => $doc['id'],
        'redirect' => 'view.php?id=' . urlencode($doc['id'])
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GCM XTREME &bull; Visual CAD &amp; Revit BIM Studio — <?= htmlspecialchars($editId ?? 'New Project') ?></title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --cad-bg: #090d16;
      --cad-panel: #0f172a;
      --cad-card: #1e293b;
      --cad-border: #334155;
      --cad-cyan: #38bdf8;
      --cad-blue: #0284c7;
      --cad-green: #22c55e;
      --cad-yellow: #f59e0b;
      --cad-red: #ef4444;
      --font-main: 'Inter', sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: var(--cad-bg);
      color: #f8fafc;
      font-family: var(--font-main);
      height: 100vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    /* Scrollbars */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
    ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #64748b; }

    /* Helper button sizes */
    .btn-xs {
      padding: 3px 8px;
      font-size: 11px;
      line-height: 1.3;
      border-radius: 4px;
    }

    /* Top Navigation Ribbon */
    .cad-header {
      background: #090d16;
      border-bottom: 1px solid var(--cad-border);
      padding: 8px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      min-height: 54px;
      height: 54px;
      flex-shrink: 0;
      z-index: 100;
      gap: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
      overflow-x: auto;
      overflow-y: hidden;
      white-space: nowrap;
    }
    .cad-header::-webkit-scrollbar { height: 3px; }
    .cad-header::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

    .cad-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }
    .cad-brand h1 {
      font-size: 12.5px;
      font-weight: 800;
      margin: 0;
      color: var(--cad-cyan);
      letter-spacing: 0.5px;
      text-transform: uppercase;
      line-height: 1.2;
    }

    /* Main 3-Column Studio Workspace */
    .cad-studio-body {
      flex: 1;
      display: flex;
      overflow: hidden;
      position: relative;
    }

    /* Left Sidebar: Tools & Material Catalog */
    .cad-sidebar-left {
      width: 320px;
      background: var(--cad-panel);
      border-right: 1px solid var(--cad-border);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      z-index: 50;
    }

    /* Center Stage: Visual Drawing Board */
    .cad-stage {
      flex: 1;
      position: relative;
      background: var(--cad-bg);
      overflow: hidden;
      display: flex;
      flex-direction: row;
    }

    /* Right Sidebar: Facade Customizer & Live BOQ */
    .cad-sidebar-right {
      width: 380px;
      background: var(--cad-panel);
      border-left: 1px solid var(--cad-border);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      z-index: 50;
    }

    /* Tool Buttons */
    .cad-tool-btn {
      background: var(--cad-card);
      border: 1px solid var(--cad-border);
      color: #e2e8f0;
      font-size: 11px;
      font-weight: 600;
      padding: 6px 10px;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.15s ease;
      text-align: left;
    }
    .cad-tool-btn:hover {
      background: #334155;
      border-color: var(--cad-cyan);
      color: #ffffff;
    }
    .cad-tool-btn.active {
      background: #0284c7;
      border-color: #38bdf8;
      color: #ffffff;
    }

    /* Material Card in Catalog */
    .mat-card {
      background: var(--cad-card);
      border: 1px solid var(--cad-border);
      border-radius: 6px;
      padding: 8px 10px;
      margin-bottom: 8px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .mat-card:hover {
      border-color: var(--cad-cyan);
      transform: translateY(-1px);
    }
    .mat-card.selected-brush {
      border-color: var(--cad-yellow);
      background: rgba(245, 158, 11, 0.1);
      box-shadow: 0 0 0 1px var(--cad-yellow);
    }

    /* Status Bar */
    .cad-status-bar {
      height: 28px;
      background: #020617;
      border-top: 1px solid var(--cad-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 16px;
      font-size: 10.5px;
      font-family: var(--font-mono);
      color: #94a3b8;
      flex-shrink: 0;
      z-index: 100;
    }

    /* Interactive Grips on 2D Board */
    .cad-grip {
      cursor: pointer;
      fill: #38bdf8;
      stroke: #ffffff;
      stroke-width: 2;
      transition: r 0.15s ease;
    }
    .cad-grip:hover {
      r: 10;
      fill: #f59e0b;
    }

    .filter-chip {
      cursor: pointer;
      font-size: 10px;
      padding: 3px 8px;
      border-radius: 12px;
      transition: all 0.15s ease;
    }
    .filter-chip.active {
      background: var(--cad-blue) !important;
      color: #ffffff !important;
    }

    /* BIM Component Item Card */
    .bim-comp-card {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 6px;
      padding: 8px 10px;
      margin-bottom: 8px;
      transition: all 0.15s ease;
    }
    .bim-comp-card:hover {
      border-color: var(--cad-cyan);
      background: #243248;
    }
  </style>
</head>
<body>

  <!-- Top CAD / Revit Ribbon Header -->
  <header class="cad-header">
    <!-- Brand & Project Info -->
    <div class="cad-brand">
      <div style="background:linear-gradient(135deg, #0284c7, #38bdf8); color:#fff; width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; box-shadow:0 2px 8px rgba(56, 189, 248, 0.4); flex-shrink:0;">
        <i class="fa fa-compass-drafting"></i>
      </div>
      <div style="line-height:1.2;">
        <div style="display:flex; align-items:center; gap:6px;">
          <h1 style="margin:0; font-size:14px; font-weight:900; letter-spacing:0.8px; color:#ffffff;">GCM <span style="color:#38bdf8;">XTREME</span></h1>
          <span class="badge" style="background:rgba(2,132,199,0.25); color:#38bdf8; border:1px solid #0284c7; font-size:8.5px; font-weight:800; padding:2px 5px; border-radius:4px; letter-spacing:0.5px;">CAD &amp; REVIT BIM</span>
        </div>
        <div style="font-size:10px; color:#94a3b8;">
          Project: <strong class="text-light" id="dispDocId"><?= htmlspecialchars($editId ?? 'NEW-ESTIMATION') ?></strong> &bull; <span class="badge bg-secondary" style="font-size:8.5px; padding:1px 4px;">Precision Studio v2026</span>
        </div>
      </div>
    </div>

    <!-- View Mode Selector -->
    <div class="btn-group btn-group-sm flex-shrink-0" role="group">
      <button type="button" class="btn btn-sm btn-primary active px-2.5 py-1" id="tab2dBtn" onclick="switchStudioView('2d')" style="font-size:11.5px;">
        <i class="fa fa-draw-polygon me-1"></i>2D CAD Board
      </button>
      <button type="button" class="btn btn-sm btn-outline-light px-2.5 py-1" id="tab3dBtn" onclick="switchStudioView('3d')" style="font-size:11.5px;">
        <i class="fa fa-cube me-1"></i>3D BIM Model
      </button>
      <button type="button" class="btn btn-sm btn-outline-light px-2.5 py-1" id="tabSplitBtn" onclick="switchStudioView('split')" style="font-size:11.5px;">
        <i class="fa fa-columns me-1"></i>2D+3D Split
      </button>
      <button type="button" class="btn btn-sm btn-outline-info px-2.5 py-1" id="tabQuadBtn" onclick="switchStudioView('quad')" style="font-size:11.5px;">
        <i class="fa fa-table-cells me-1"></i>Revit Quad (WT)
      </button>
    </div>

    <!-- Units Selector & Actions -->
    <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
      <!-- Precision Drafting Units -->
      <div class="btn-group btn-group-sm me-1" role="group">
        <button type="button" class="btn btn-xs btn-primary fw-bold py-1 px-2 unit-btn active" data-unit="FT_IN" onclick="setStudioUnit('FT_IN')">FT-IN</button>
        <button type="button" class="btn btn-xs btn-outline-secondary text-light py-1 px-2 unit-btn" data-unit="FT" onclick="setStudioUnit('FT')">FT</button>
        <button type="button" class="btn btn-xs btn-outline-secondary text-light py-1 px-2 unit-btn" data-unit="M" onclick="setStudioUnit('M')">M</button>
        <button type="button" class="btn btn-xs btn-outline-secondary text-light py-1 px-2 unit-btn" data-unit="MM" onclick="setStudioUnit('MM')">MM</button>
      </div>

      <!-- Action Buttons -->
      <button type="button" class="btn btn-xs btn-outline-warning fw-bold py-1 px-2" onclick="showHowToWorkModal()" title="View Visual CAD Studio Guide">
        <i class="fa fa-circle-question me-1"></i>Guide
      </button>
      <button type="button" class="btn btn-xs btn-warning fw-bold text-dark py-1 px-2 shadow-sm" onclick="attachBadmintonToCricket()" title="Attach 60 FT Indoor Badminton Arena (2 BWF Courts) to your Cricket Ground">
        <i class="fa fa-plus-circle me-1"></i>+ Attach Badminton
      </button>
      <button type="button" class="btn btn-xs btn-info fw-bold text-dark py-1 px-2" onclick="presetBadmintonOnlyIndoor()" title="Convert this project to an Indoor-Only Badminton Arena">
        <i class="fa fa-warehouse me-1"></i>Indoor Badminton
      </button>
      <button type="button" class="btn btn-xs btn-outline-light fw-bold py-1 px-2" onclick="wrapAllSidesGiSheets()" title="Wrap All 4 Sides and Curved Roof in 0.50mm PPGL GI Sheets">
        <i class="fa fa-shield-halved me-1"></i>Enclose GI Sheets
      </button>
      <button type="button" class="btn btn-xs btn-outline-info fw-bold py-1 px-2" onclick="exportStudioDxf()" title="Download Production AutoCAD Drawing (.DXF R12/2000)">
        <i class="fa fa-download me-1"></i>AutoCAD .DXF
      </button>
      <button type="button" class="btn btn-sm btn-success fw-bold py-1 px-3 shadow-sm" onclick="saveStudioProject()" id="btnSaveStudio" style="font-size:12px;">
        <i class="fa fa-floppy-disk me-1"></i>Save &amp; Generate Estimation
      </button>
      <a href="documents.php" class="btn btn-sm btn-outline-secondary text-light py-1 px-2 ms-1" title="Exit Studio"><i class="fa fa-xmark"></i></a>
    </div>
  </header>

  <!-- 3-Column Studio Workspace -->
  <div class="cad-studio-body">

    <!-- LEFT COLUMN: Tools & Material Catalog -->
    <aside class="cad-sidebar-left">
      <!-- Tabs: Drafting Tools vs Material Catalog -->
      <div class="p-2 border-bottom border-secondary d-flex gap-1">
        <button type="button" class="btn btn-xs btn-primary flex-fill fw-bold py-1" id="btnTabMaterials" onclick="switchLeftTab('materials')">
          <i class="fa fa-swatchbook me-1"></i>Material Catalog
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill fw-bold py-1" id="btnTabCourts" onclick="switchLeftTab('courts')">
          <i class="fa fa-basketball me-1"></i>Sports &amp; Zones
        </button>
      </div>

      <!-- TAB 1: Material Search & Product Catalog (Revit Material Browser) -->
      <div id="panelMaterials" style="flex:1; overflow-y:auto; padding:10px;">
        <div class="mb-2">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa fa-search"></i></span>
            <input type="text" class="form-control bg-dark text-light border-secondary" id="searchMatInput" placeholder="Search 281+ products (PPGL, Net, Turf, Vinyl)..." oninput="filterMaterials(this.value)">
          </div>
        </div>

        <!-- Filter Chips -->
        <div class="d-flex gap-1 flex-wrap mb-2">
          <button type="button" class="badge bg-primary border-0 filter-chip active" onclick="filterCategory('all', this)">All</button>
          <button type="button" class="badge bg-dark border border-secondary filter-chip" onclick="filterCategory('roof', this)">Roofing</button>
          <button type="button" class="badge bg-dark border border-secondary filter-chip" onclick="filterCategory('wall', this)">Walls &amp; Nets</button>
          <button type="button" class="badge bg-dark border border-secondary filter-chip" onclick="filterCategory('turf', this)">Flooring</button>
          <button type="button" class="badge bg-dark border border-secondary filter-chip" onclick="filterCategory('steel', this)">Structural Steel</button>
        </div>

        <div style="font-size:10.5px; color:#94a3b8; margin-bottom:8px;">
          <i class="fa fa-circle-info text-info me-1"></i>Click a material to select brush, or click <strong>[Edit Full Specs]</strong> to customize!
        </div>

        <!-- Active Material Banner -->
        <div id="activeBrushBanner" style="background:#0c4a6e; border:1px solid #38bdf8; border-radius:6px; padding:6px 10px; margin-bottom:10px; font-size:11px; display:flex; align-items:center; justify-content:space-between;">
          <div>Active Brush: <strong class="text-light" id="activeBrushTitle">0.50mm PPGL GI Sheet</strong></div>
          <span class="badge bg-info text-dark">Ready</span>
        </div>

        <!-- Material Cards Container (Live catalog items) -->
        <div id="matCardsList"></div>
      </div>

      <!-- TAB 2: Sports Facility Dropper Presets & Zones -->
      <div id="panelCourts" style="display:none; flex:1; overflow-y:auto; padding:10px;">
        <div style="font-size:11px; color:#94a3b8; margin-bottom:10px;">
          Select any preset, 2-in-1 dual game, or attach extra courts with custom clearances:
        </div>

        <!-- 1. STANDALONE SPORTS PRESETS -->
        <div class="fw-bold text-info mb-1" style="font-size:11px;"><i class="fa fa-trophy me-1"></i>Master Sports Facilities:</div>
        <button type="button" class="cad-tool-btn w-100 mb-1 py-1 px-2 text-start" onclick="presetBoxCricket()">
          <i class="fa fa-baseball-bat-ball text-warning fs-5"></i>
          <div>
            <div class="fw-bold" style="font-size:12px;">Box Cricket Ground (100×60 FT)</div>
            <div class="small text-secondary" style="font-size:10px;">66 FT Pitch, Turf &amp; Ball-Stop Netting</div>
          </div>
        </button>

        <button type="button" class="cad-tool-btn w-100 mb-1 py-1 px-2 text-start" onclick="presetBadmintonArena(2)">
          <i class="fa fa-table-tennis-paddle-ball text-success fs-5"></i>
          <div>
            <div class="fw-bold" style="font-size:12px;">Indoor Badminton (60×40 FT - 2 Courts)</div>
            <div class="small text-secondary" style="font-size:10px;">30 FT Eaves, 2 BWF Courts, Full PPGL Cladding</div>
          </div>
        </button>

        <button type="button" class="cad-tool-btn w-100 mb-1 py-1 px-2 text-start" onclick="presetFootballTurf('5_side')">
          <i class="fa fa-futbol text-primary fs-5"></i>
          <div>
            <div class="fw-bold" style="font-size:12px;">5-A-Side Football Turf (100×60 FT)</div>
            <div class="small text-secondary" style="font-size:10px;">FIFA 50mm Infill Turf &amp; Goal Posts</div>
          </div>
        </button>

        <button type="button" class="cad-tool-btn w-100 mb-1 py-1 px-2 text-start" onclick="presetPickleballArena(2)">
          <i class="fa fa-table-tennis-paddle-ball text-warning fs-5"></i>
          <div>
            <div class="fw-bold" style="font-size:12px;">Pro Pickleball Arena (60×44 FT - 2 Courts)</div>
            <div class="small text-secondary" style="font-size:10px;">Synthetic Acrylic Cushion Court &amp; USAPA Markings</div>
          </div>
        </button>

        <button type="button" class="cad-tool-btn w-100 mb-2 py-1 px-2 text-start" onclick="presetMultiSport160()">
          <i class="fa fa-building-circle-check text-info fs-5"></i>
          <div>
            <div class="fw-bold" style="font-size:12px;">160×60 Multi-Sport Complex</div>
            <div class="small text-secondary" style="font-size:10px;">Cricket + Indoor Badminton + Amenities</div>
          </div>
        </button>

        <!-- 2. TWO GAMES IN ONE COURT (DUAL-GAME COMBINATIONS) -->
        <div class="p-2 mb-2 rounded" style="background:#1e1b4b; border:1px solid #818cf8;">
          <div class="fw-bold text-light mb-1" style="font-size:11px;"><i class="fa fa-layer-group text-warning me-1"></i>2 Games in 1 Single Court:</div>
          <div class="d-flex flex-column gap-1">
            <button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="presetDualGame('badminton_basketball')">
              <i class="fa fa-basketball text-warning me-1"></i>Badminton + Basketball (Dual Lines &amp; Hoops)
            </button>
            <button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="presetDualGame('football_basketball')">
              <i class="fa fa-futbol text-primary me-1"></i>Football + Basketball (Turf &amp; Hoop)
            </button>
            <button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="presetDualGame('badminton_pickleball')">
              <i class="fa fa-table-tennis-paddle-ball text-success me-1"></i>Badminton + Pickleball (Dual Racket)
            </button>
            <button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="presetDualGame('cricket_pickleball')">
              <i class="fa fa-baseball-bat-ball text-warning me-1"></i>Cricket + Pickleball (Hybrid Outfield)
            </button>
          </div>
        </div>

        <!-- 3. ATTACH EXTRA COURT WITH DISTANCE (0 FT, 2 FT, 1 METER) -->
        <div class="p-2 mb-2 rounded" style="background:#0c4a6e; border:1px solid #38bdf8;">
          <div class="fw-bold text-light mb-1" style="font-size:11px;"><i class="fa fa-plus-circle text-warning me-1"></i>Attach Extra Court to Facility:</div>
          
          <div class="mb-1" style="font-size:10px; color:#bae6fd;">Select Clearance Gap:</div>
          <div class="d-flex gap-1 mb-2">
            <input type="radio" class="btn-check" name="attachGapRadio" id="gap0" value="0" checked>
            <label class="btn btn-xs btn-outline-light flex-fill py-0" for="gap0" style="font-size:9.5px;">Attached (0 FT)</label>

            <input type="radio" class="btn-check" name="attachGapRadio" id="gap2" value="2">
            <label class="btn btn-xs btn-outline-light flex-fill py-0" for="gap2" style="font-size:9.5px;">2 FT Gap</label>

            <input type="radio" class="btn-check" name="attachGapRadio" id="gap1m" value="3.28">
            <label class="btn btn-xs btn-outline-light flex-fill py-0" for="gap1m" style="font-size:9.5px;">1 Meter Walkway</label>
          </div>

          <div class="d-flex flex-column gap-1">
            <button type="button" class="btn btn-xs btn-warning text-dark fw-bold text-start py-1" onclick="var g=parseFloat(document.querySelector('input[name=attachGapRadio]:checked').value)||0; attachSportCourt('badminton', g);">
              <i class="fa fa-table-tennis-paddle-ball me-1"></i>+ Attach Badminton Court (60 FT)
            </button>
            <button type="button" class="btn btn-xs btn-info text-dark fw-bold text-start py-1" onclick="var g=parseFloat(document.querySelector('input[name=attachGapRadio]:checked').value)||0; attachSportCourt('pickleball', g);">
              <i class="fa fa-table-tennis-paddle-ball me-1"></i>+ Attach Pickleball Court (50 FT)
            </button>
            <button type="button" class="btn btn-xs btn-primary fw-bold text-start py-1" onclick="var g=parseFloat(document.querySelector('input[name=attachGapRadio]:checked').value)||0; attachSportCourt('football', g);">
              <i class="fa fa-futbol me-1"></i>+ Attach Football Turf (80 FT)
            </button>
            <button type="button" class="btn btn-xs btn-danger fw-bold text-start py-1" onclick="var g=parseFloat(document.querySelector('input[name=attachGapRadio]:checked').value)||0; attachSportCourt('basketball', g);">
              <i class="fa fa-basketball me-1"></i>+ Attach Basketball Court (50 FT)
            </button>
          </div>
        </div>

        <!-- 4. ACCESS GATES MANAGER -->
        <div class="p-2 mb-2 rounded bg-dark border border-secondary" style="font-size:10.5px;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="fw-bold text-danger"><i class="fa fa-door-open me-1"></i>Access Gates:</div>
            <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="addStudioGate()">
              <i class="fa fa-plus me-1"></i>Add Gate
            </button>
          </div>
          <div style="font-size:9.5px; color:#94a3b8; margin-bottom:4px;">
            <i class="fa fa-hand-pointer text-warning me-1"></i>Drag white circular grip on gate to slide anywhere along wall or onto other walls!
          </div>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill py-0" onclick="if(cadState.gates&&cadState.gates[0]){cadState.gates[0].wall='front';render2dDrawingBoard();}">Front</button>
            <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill py-0" onclick="if(cadState.gates&&cadState.gates[0]){cadState.gates[0].wall='back';render2dDrawingBoard();}">Back</button>
            <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill py-0" onclick="if(cadState.gates&&cadState.gates[0]){cadState.gates[0].wall='left';render2dDrawingBoard();}">Left</button>
            <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill py-0" onclick="if(cadState.gates&&cadState.gates[0]){cadState.gates[0].wall='right';render2dDrawingBoard();}">Right</button>
          </div>
        </div>

        <!-- 5. ENCLOSURE QUICK TOGGLES -->
        <div class="p-2 mt-2 bg-dark border border-secondary rounded" style="font-size:10.5px;">
          <div class="fw-bold text-info mb-1"><i class="fa fa-wand-magic-sparkles me-1"></i>Quick Adjustments:</div>
          <div class="d-flex flex-column gap-1">
            <button type="button" class="btn btn-xs btn-outline-warning text-start py-1" onclick="toggleAllSidesCladding('ppgl_sheet')"><i class="fa fa-sheet-plastic text-warning me-1"></i>Wrap All 4 Sides with PPGL Sheets</button>
            <button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="toggleAllSidesCladding('netting')"><i class="fa fa-border-all text-info me-1"></i>Set All 4 Sides to Safety Netting</button>
            <button type="button" class="btn btn-xs btn-outline-danger text-start py-1" onclick="setRoofOpen()"><i class="fa fa-trash me-1"></i>Remove Roof (Make Open-to-Sky)</button>
          </div>
        </div>
      </div>
    </aside>

    <!-- CENTER STAGE: Visual CAD Drawing Canvas -->
    <main class="cad-stage">
      <!-- On-Screen CAD Tool HUD -->
      <div style="position:absolute; top:10px; left:16px; z-index:20; display:flex; gap:8px; align-items:center;">
        <div style="background:rgba(15,23,42,0.92); border:1px solid #0284c7; border-radius:6px; padding:4px 12px; font-size:11px; font-weight:700; color:#38bdf8;">
          <i class="fa fa-compass-drafting text-warning me-1"></i><span id="canvasModeLabel">GCM XTREME &bull; 2D CAD DRAWING BOARD: Click ANY element to modify its full specifications!</span>
        </div>
        <button type="button" class="btn btn-xs btn-dark border-secondary text-light py-1 px-2" onclick="showHowToWorkModal()" style="font-size:10.5px;">
          <i class="fa fa-lightbulb text-warning me-1"></i>Guide
        </button>
      </div>

      <!-- 2D CAD Drawing Canvas Container -->
      <div id="cad2dCanvasContainer" style="width:100%; height:100%; background:#090d16; position:relative;">
        <svg id="cadSvg" width="100%" height="100%" viewBox="0 0 1400 850" style="width:100%; height:100%; display:block;" preserveAspectRatio="xMidYMid meet">
          <defs>
            <!-- Background Grid Pattern -->
            <pattern id="cadGrid" width="20" height="20" patternUnits="userSpaceOnUse">
              <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#1e293b" stroke-width="0.5" />
            </pattern>
            <pattern id="cadMajorGrid" width="100" height="100" patternUnits="userSpaceOnUse">
              <rect width="100" height="100" fill="url(#cadGrid)" />
              <path d="M 100 0 L 0 0 0 100" fill="none" stroke="#334155" stroke-width="1.2" />
            </pattern>
            <marker id="cadArr" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
              <path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#38bdf8"/>
            </marker>
          </defs>

          <!-- Grid Background -->
          <rect width="100%" height="100%" fill="url(#cadMajorGrid)" />

          <!-- Dynamic CAD Group rendered via JS -->
          <g id="cadRootGroup"></g>
        </svg>
      </div>

      <!-- 3D BIM WebGL Canvas Container -->
      <div id="cad3dCanvasContainer" style="display:none; position:relative; width:100%; height:100%; background:#0b1120;">
        <div id="cad3dMount" style="width:100%; height:100%;"></div>
      </div>

      <!-- Quad Split Container (Revit 4-View Tile) -->
      <div id="cadQuadStudioContainer" style="display:none; position:relative; width:100%; height:100%; grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr; gap:6px; padding:6px; background:#020617;">
        <div id="quad3dMount" style="position:relative; background:#0f172a; border:1px solid #334155; border-radius:4px; overflow:hidden; cursor:pointer;" onclick="openComponentSpecEditor('columns_structure')"></div>
        <div id="quadPlanMount" style="position:relative; background:#0f172a; border:1px solid #334155; border-radius:4px; overflow:hidden; cursor:pointer;" onclick="openComponentSpecEditor('sports_flooring')"></div>
        <div id="quadSideMount" style="position:relative; background:#0f172a; border:1px solid #334155; border-radius:4px; overflow:hidden; cursor:pointer;" onclick="openComponentSpecEditor('wall_enclosures')"></div>
        <div id="quadCrossMount" style="position:relative; background:#0f172a; border:1px solid #334155; border-radius:4px; overflow:hidden; cursor:pointer;" onclick="openComponentSpecEditor('roof_canopy')"></div>
      </div>
    </main>

    <!-- RIGHT COLUMN: BIM Component Object Tree & Live BOQ -->
    <aside class="cad-sidebar-right">
      <!-- Right Sidebar Nav Tabs -->
      <div class="p-2 border-bottom border-secondary d-flex gap-1" style="background:#0f172a;">
        <button type="button" class="btn btn-xs btn-primary flex-fill fw-bold py-1" id="btnTabBimSpecs" onclick="switchRightTab('specs')">
          <i class="fa fa-cubes me-1"></i>BIM Specs (10)
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill fw-bold py-1" id="btnTabFacade" onclick="switchRightTab('facade')">
          <i class="fa fa-layer-group me-1"></i>6 Surfaces
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary text-light flex-fill fw-bold py-1" id="btnTabBoq" onclick="switchRightTab('boq')">
          <i class="fa fa-calculator me-1"></i>Live BOQ
        </button>
      </div>

      <div style="flex:1; overflow-y:auto; padding:12px;">

        <!-- 0. ARENA DIMENSION STEPPERS (Always visible at top of right panel) -->
        <div class="mb-3 p-2 rounded" style="background:#1e293b; border:1px solid #334155;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-uppercase fw-bold text-info" style="font-size:10px;"><i class="fa fa-ruler-combined me-1"></i>Arena Dimensions &amp; Geometry</span>
            <span class="badge bg-primary" id="boqAreaBadgeTop" style="font-size:9.5px;">6,000 SFT</span>
          </div>

          <!-- Length Stepper -->
          <div class="d-flex align-items-center justify-content-between mb-1" style="font-size:11px;">
            <span style="font-weight:600;"><i class="fa fa-arrows-left-right text-info me-1"></i>Length:</span>
            <div class="d-flex align-items-center gap-1">
              <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 fw-bold" onclick="adjustStudioDim('length', -5)">-5'</button>
              <input type="number" id="numInputLength" class="form-control form-control-sm bg-dark text-light border-secondary text-center py-0 px-1" style="width:64px; font-size:11px; font-weight:bold;" value="<?= (int)$initL ?>" onchange="setStudioDimValue('length', this.value)">
              <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="adjustStudioDim('length', 5)">+5'</button>
            </div>
          </div>

          <!-- Width Stepper -->
          <div class="d-flex align-items-center justify-content-between mb-1" style="font-size:11px;">
            <span style="font-weight:600;"><i class="fa fa-arrows-up-down text-success me-1"></i>Width:</span>
            <div class="d-flex align-items-center gap-1">
              <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 fw-bold" onclick="adjustStudioDim('width', -5)">-5'</button>
              <input type="number" id="numInputWidth" class="form-control form-control-sm bg-dark text-light border-secondary text-center py-0 px-1" style="width:64px; font-size:11px; font-weight:bold;" value="<?= (int)$initW ?>" onchange="setStudioDimValue('width', this.value)">
              <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="adjustStudioDim('width', 5)">+5'</button>
            </div>
          </div>

          <!-- Height Stepper -->
          <div class="d-flex align-items-center justify-content-between mb-1" style="font-size:11px;">
            <span style="font-weight:600;"><i class="fa fa-arrow-up-from-bracket text-warning me-1"></i>Clear Eaves:</span>
            <div class="d-flex align-items-center gap-1">
              <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 fw-bold" onclick="adjustStudioDim('height', -2)">-2'</button>
              <input type="number" id="numInputHeight" class="form-control form-control-sm bg-dark text-light border-secondary text-center py-0 px-1" style="width:64px; font-size:11px; font-weight:bold;" value="<?= (int)$initH ?>" onchange="setStudioDimValue('height', this.value)">
              <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="adjustStudioDim('height', 2)">+2'</button>
            </div>
          </div>

          <!-- Pitch Length -->
          <div class="d-flex align-items-center justify-content-between" style="font-size:11px;">
            <span style="font-weight:600;"><i class="fa fa-baseball-bat-ball text-warning me-1"></i>Pitch Length:</span>
            <div class="d-flex align-items-center gap-1">
              <input type="number" id="numInputPitch" class="form-control form-control-sm bg-dark text-light border-secondary text-center py-0 px-1" style="width:64px; font-size:11px; font-weight:bold;" value="<?= (int)$initPitch ?>" onchange="setStudioDimValue('pitch', this.value)">
              <span class="text-secondary" style="font-size:10px;">FT</span>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL VIEW 1: All 10 BIM Components & Full Specs Modifier -->
        <div id="panelBimSpecs">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-uppercase fw-bold text-info" style="font-size:10px;"><i class="fa fa-cubes me-1"></i>All Items &amp; Products Specifications</span>
            <span class="badge bg-dark border border-secondary" style="font-size:9px;">Click to Edit Any Item</span>
          </div>

          <div id="bimComponentsList"></div>
        </div>

        <!-- RIGHT PANEL VIEW 2: 6-Surface Specific Side Customizer -->
        <div id="panelFacadeCustomizer" style="display:none;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-uppercase fw-bold text-info" style="font-size:10px;"><i class="fa fa-layer-group me-1"></i>6 Surfaces Quick Toggles</span>
            <span class="badge bg-dark border border-secondary" style="font-size:9px;">Perimeter Sheets</span>
          </div>

          <!-- Roof Surface -->
          <div class="p-2 rounded mb-2" style="background:#1e293b; border:1px solid #334155;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span style="font-size:11px; font-weight:700;"><i class="fa fa-warehouse text-warning me-1"></i>Roof Canopy</span>
              <span class="badge bg-info text-dark" id="lblMatRoof">PPGL Sheet</span>
            </div>
            <div class="btn-group btn-group-sm w-100" role="group">
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('roof', 'ppgl_sheet')">PPGL</button>
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('roof', 'netting')">Net</button>
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('roof', 'polycarbonate')">Polycarb</button>
              <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="applySideMat('roof', 'open')">Open</button>
            </div>
          </div>

          <!-- Front Wall -->
          <div class="p-2 rounded mb-2" style="background:#1e293b; border:1px solid #334155;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span style="font-size:11px; font-weight:700;"><i class="fa fa-door-open text-success me-1"></i>Front Wall (Entrance)</span>
              <span class="badge bg-info text-dark" id="lblMatFront">PPGL Sheet</span>
            </div>
            <div class="btn-group btn-group-sm w-100" role="group">
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('front', 'ppgl_sheet')">GI Sheet</button>
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('front', 'netting')">Net</button>
              <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="applySideMat('front', 'open')">Open</button>
              <button type="button" class="btn btn-xs btn-outline-success py-0" onclick="placeGateOnActiveWall(event)">+ Gate</button>
            </div>
          </div>

          <!-- Back Wall -->
          <div class="p-2 rounded mb-2" style="background:#1e293b; border:1px solid #334155;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span style="font-size:11px; font-weight:700;"><i class="fa fa-arrows-left-right text-info me-1"></i>Back Wall</span>
              <span class="badge bg-info text-dark" id="lblMatBack">PPGL Sheet</span>
            </div>
            <div class="btn-group btn-group-sm w-100" role="group">
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('back', 'ppgl_sheet')">GI Sheet</button>
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('back', 'netting')">Net</button>
              <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="applySideMat('back', 'open')">Open</button>
            </div>
          </div>

          <!-- Left Wall -->
          <div class="p-2 rounded mb-2" style="background:#1e293b; border:1px solid #334155;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span style="font-size:11px; font-weight:700;"><i class="fa fa-arrow-left text-primary me-1"></i>Left Wall (North)</span>
              <span class="badge bg-info text-dark" id="lblMatLeft">PPGL Sheet</span>
            </div>
            <div class="btn-group btn-group-sm w-100" role="group">
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('left', 'ppgl_sheet')">GI Sheet</button>
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('left', 'netting')">Net</button>
              <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="applySideMat('left', 'open')">Open</button>
            </div>
          </div>

          <!-- Right Wall -->
          <div class="p-2 rounded mb-2" style="background:#1e293b; border:1px solid #334155;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span style="font-size:11px; font-weight:700;"><i class="fa fa-arrow-right text-primary me-1"></i>Right Wall (South)</span>
              <span class="badge bg-info text-dark" id="lblMatRight">PPGL Sheet</span>
            </div>
            <div class="btn-group btn-group-sm w-100" role="group">
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('right', 'ppgl_sheet')">GI Sheet</button>
              <button type="button" class="btn btn-xs btn-outline-light py-0" onclick="applySideMat('right', 'netting')">Net</button>
              <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="applySideMat('right', 'open')">Open</button>
            </div>
          </div>

          <!-- Ground Floor -->
          <div class="p-2 rounded mb-2" style="background:#1e293b; border:1px solid #334155;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span style="font-size:11px; font-weight:700;"><i class="fa fa-seedling text-success me-1"></i>Ground Flooring</span>
              <span class="badge bg-success text-dark" id="lblMatFloor">50mm Turf</span>
            </div>
            <div class="btn-group btn-group-sm w-100" role="group">
              <button type="button" class="btn btn-xs btn-outline-success py-0" onclick="applySideMat('floor', 'sports_turf')">50mm Turf</button>
              <button type="button" class="btn btn-xs btn-outline-warning py-0" onclick="applySideMat('floor', 'vinyl_mat')">Vinyl Mat</button>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL VIEW 3: LIVE BOQ & ESTIMATION SUMMARY -->
        <div id="panelLiveBoq" style="display:none;">
          <div class="p-2 rounded mb-3" style="background:#0f172a; border:1px solid #0284c7;">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-secondary">
              <span class="text-uppercase fw-bold text-warning" style="font-size:10px;"><i class="fa fa-calculator me-1"></i>Live Material Takeoff</span>
              <span class="badge bg-primary" id="boqAreaBadge">6,000 SFT</span>
            </div>

            <div class="d-flex flex-column gap-1" style="font-size:10.5px;">
              <div class="d-flex justify-content-between text-secondary">
                <span>Civil Foundation &amp; Base:</span>
                <strong class="text-light" id="boqCivilCost">₹2,40,000</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Structural Steel Columns:</span>
                <strong class="text-light" id="boqSteelCost">₹13,40,000</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Wall Cladding &amp; Netting:</span>
                <strong class="text-light" id="boqWallCost">₹8,80,000</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Roof Truss &amp; Canopy:</span>
                <strong class="text-light" id="boqRoofCost">₹15,55,200</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Sports Turf &amp; Pitch:</span>
                <strong class="text-light" id="boqTurfCost">₹9,30,000</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Doors &amp; Accessories:</span>
                <strong class="text-light" id="boqGateCost">₹37,000</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Stadium Lighting (IP66):</span>
                <strong class="text-light" id="boqLightCost">₹52,000</strong>
              </div>

              <div class="pt-2 mt-1 border-top border-secondary d-flex justify-content-between">
                <span class="text-light fw-bold">Taxable Subtotal:</span>
                <strong class="text-info fs-6" id="boqSubtotal">₹47,42,200</strong>
              </div>
              <div class="d-flex justify-content-between text-secondary" style="font-size:9.5px;">
                <span>GST (18%):</span>
                <span id="boqGstAmount">₹8,53,596</span>
              </div>
              <div class="pt-1 mt-1 border-top border-secondary d-flex justify-content-between align-items-center">
                <span class="fw-bold text-warning" style="font-size:12px;">GRAND TOTAL:</span>
                <strong class="text-success fs-5 fw-bold" id="boqGrandTotal">₹55,95,796</strong>
              </div>
              <div style="font-size:9px; color:#64748b;" id="boqAmountWords">Fifty-Five Lakh Ninety-Five Thousand Seven Hundred Ninety-Six Rupees Only</div>
            </div>
          </div>
        </div>

        <!-- ACTIONS (Always visible at bottom of right sidebar) -->
        <div class="d-flex flex-column gap-2 mt-3">
          <button type="button" class="btn btn-success fw-bold w-100 py-2 shadow" onclick="saveStudioProject()">
            <i class="fa fa-floppy-disk me-2"></i>Save &amp; Generate Estimation
          </button>
          <button type="button" class="btn btn-outline-info fw-bold w-100 py-1" onclick="exportStudioDxf()">
            <i class="fa fa-file-arrow-down me-2"></i>Download AutoCAD .DXF
          </button>
        </div>
      </div>
    </aside>
  </div>

  <!-- Bottom Status Bar -->
  <footer class="cad-status-bar">
    <div id="statCoords">X: 100'-0" &bull; Y: 60'-0" &bull; Z: 25'-0"</div>
    <div><i class="fa fa-crosshairs text-danger me-1"></i>SNAP: ON &bull; ORTHO: ON &bull; SCALE: 1:100 &bull; AUTOCAD &amp; REVIT BIM COMPATIBLE</div>
    <div id="statArea" class="text-success fw-bold">6,000 SFT (557 SQM)</div>
  </footer>

  <!-- ═══════════════════════════════════════════════════════════════════════════ -->
  <!-- UNIVERSAL BIM COMPONENT SPECIFICATION & PRODUCT MODIFIER MODAL            -->
  <!-- ═══════════════════════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="compSpecEditorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-info shadow-lg">
        <div class="modal-header border-secondary" style="background:#0f172a;">
          <div>
            <h5 class="modal-title text-info fw-bold mb-0">
              <i class="fa fa-sliders me-2"></i><span id="modalCompTitle">Component Specification</span>
            </h5>
            <div style="font-size:11px; color:#94a3b8;">
              BIM Category: <span class="badge bg-secondary" id="modalCompCatBadge">Structural Framework</span> &bull; Click-to-Edit Synchronized with 3D/2D Model
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body" style="font-size:12px; line-height:1.5;">
          <!-- Item Inclusion / Exclusion Control Banner -->
          <div class="d-flex align-items-center justify-content-between p-2 mb-3 rounded border" id="modalInclusionBanner" style="background:#1e293b; border-color:#38bdf8 !important;">
            <div class="d-flex align-items-center gap-2">
              <span class="text-secondary fw-bold" style="font-size:11px;">ITEM STATUS:</span>
              <span class="badge bg-success fw-bold px-2 py-1" id="modalInclusionStatusBadge">
                <i class="fa fa-check-circle me-1"></i>INCLUDED IN ESTIMATION &amp; 3D MODEL
              </span>
            </div>
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-xs btn-outline-danger fw-bold py-1 px-2" id="btnModalExcludeAction" onclick="toggleCurrentModalInclusion(false)" title="Remove / Exclude this item from 3D model, 2D plan, and calculation">
                <i class="fa fa-trash me-1"></i>Delete / Exclude Item
              </button>
              <button type="button" class="btn btn-xs btn-outline-success fw-bold py-1 px-2" id="btnModalIncludeAction" onclick="toggleCurrentModalInclusion(true)" title="Include this item in project & calculation">
                <i class="fa fa-check me-1"></i>Include in Project
              </button>
            </div>
          </div>

          <!-- Quick Selector from Master Product Catalog -->
          <div class="p-2 mb-3 rounded" style="background:#1e293b; border:1px solid #38bdf8;">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label mb-0 fw-bold text-warning" style="font-size:11px;">
                <i class="fa fa-magnifying-glass me-1"></i>Quick Select from Master Product Catalog (281 Items):
              </label>
              <span class="badge bg-info text-dark" style="font-size:9px;">Auto-fill Specs &amp; Rates</span>
            </div>
            <select class="form-select form-select-sm bg-dark text-light border-secondary" id="modalCatalogSelector" onchange="onCatalogProductSelected(this.value)">
              <option value="">-- Choose Product to Auto-Fill Specifications &amp; Rates --</option>
            </select>
          </div>

          <div class="row g-2">
            <!-- Product Title -->
            <div class="col-md-7">
              <label class="form-label text-secondary mb-1">Product Title / Line Item Name:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fw-bold" id="modalInputName">
            </div>

            <!-- Brand / Manufacturer -->
            <div class="col-md-5">
              <label class="form-label text-secondary mb-1">Brand / Manufacturer:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="modalInputBrand" placeholder="e.g. Tata Bluescope, AMNS, Garware, Apollo">
            </div>

            <!-- Technical Specification -->
            <div class="col-12">
              <label class="form-label text-secondary mb-1">Detailed Technical Engineering Specification:</label>
              <textarea class="form-control form-control-sm bg-dark text-light border-secondary" id="modalInputSpec" rows="2"></textarea>
            </div>

            <!-- Material Gauge / Thickness / DTEX -->
            <div class="col-md-6">
              <label class="form-label text-secondary mb-1">Material Gauge / Thickness / Height:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="modalInputGauge" placeholder="e.g. 0.50mm AZ-150 / 14 Gauge (2.0mm)">
            </div>

            <!-- HSN Code -->
            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">HSN Code:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="modalInputHsn">
            </div>

            <!-- GST Rate -->
            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">GST Rate (%):</label>
              <select class="form-select form-select-sm bg-dark text-light border-secondary" id="modalInputGst">
                <option value="18">18% (Standard)</option>
                <option value="12">12%</option>
                <option value="5">5% (Sports Net/Turf)</option>
                <option value="0">0% (Exempt)</option>
              </select>
            </div>

            <!-- Quantity, Unit, Rate, Amount -->
            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">Quantity:</label>
              <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary text-center fw-bold" id="modalInputQty" oninput="recalcModalAmount()">
            </div>

            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">Unit:</label>
              <select class="form-select form-select-sm bg-dark text-light border-secondary text-center" id="modalInputUnit">
                <option value="SQFT">SQFT</option>
                <option value="RFT">RFT</option>
                <option value="NOS">NOS</option>
                <option value="SET">SET</option>
                <option value="MTR">MTR</option>
                <option value="KG">KG</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">Unit Rate (₹):</label>
              <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary text-center fw-bold text-success" id="modalInputRate" oninput="recalcModalAmount()">
            </div>

            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">Total Amount (₹):</label>
              <input type="text" class="form-control form-control-sm bg-secondary bg-opacity-25 text-warning border-secondary text-center fw-bold fs-6" id="modalInputAmount" readonly>
            </div>

            <!-- Proposal Bullet Points -->
            <div class="col-12 mt-2">
              <label class="form-label text-secondary mb-1">Customer Proposal Bullets (one specification per line):</label>
              <textarea class="form-control form-control-sm bg-dark text-light border-secondary" id="modalInputBullets" rows="3" placeholder="Enter bulleted specifications..."></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer border-secondary justify-content-between" style="background:#0f172a;">
          <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="deleteCurrentComponentFromAllViews()">
            <i class="fa fa-trash me-1"></i>Delete Item from 3D &amp; All Views
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary text-light" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-4" onclick="applyComponentEdit()">
              <i class="fa fa-check-circle me-1"></i>Apply &amp; Update Estimation
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- HOW TO WORK MODAL -->
  <div class="modal fade" id="howToWorkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-info"><i class="fa fa-compass-drafting me-2"></i>How to Work on Your Project (CAD &amp; Revit Guide)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="font-size:13px; line-height:1.6;">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="p-3 rounded bg-secondary bg-opacity-25 h-100 border border-secondary">
                <h6 class="text-warning"><i class="fa fa-ruler-combined me-2"></i>1. Adjust Court Dimensions</h6>
                <ul class="ps-3 mb-0 small text-light">
                  <li><strong>Drag in Canvas:</strong> Click &amp; drag the blue circular handles on the right (Length) or bottom (Width).</li>
                  <li><strong>Quick Steppers:</strong> Click <code>[-5 FT]</code> or <code>[+5 FT]</code> buttons in the right panel or directly on the canvas grips.</li>
                  <li><strong>Type Exact Number:</strong> Directly type <code>160</code> or <code>60</code> into the Length/Width input box.</li>
                </ul>
              </div>
            </div>

            <div class="col-md-6">
              <div class="p-3 rounded bg-secondary bg-opacity-25 h-100 border border-secondary">
                <h6 class="text-info"><i class="fa fa-plus-circle me-2"></i>2. Attach Badminton to Cricket</h6>
                <ul class="ps-3 mb-0 small text-light">
                  <li>Open the <strong>"Sports &amp; Zones"</strong> tab in the left sidebar.</li>
                  <li>Click <strong>"+ Attach Indoor Badminton"</strong>.</li>
                  <li>It instantly expands length to 160 FT, creates Zone 1 (Cricket) + Zone 2 (2 BWF Badminton Courts), sets green vinyl floor, and calculates price!</li>
                  <li>Click <strong>"Make Badminton Section Fully Indoor"</strong> to wrap all sides with GI sheets!</li>
                </ul>
              </div>
            </div>

            <div class="col-md-6">
              <div class="p-3 rounded bg-secondary bg-opacity-25 h-100 border border-secondary">
                <h6 class="text-success"><i class="fa fa-cubes me-2"></i>3. Modify ANY Item Specification</h6>
                <ul class="ps-3 mb-0 small text-light">
                  <li><strong>Click in 3D or 2D:</strong> Click ANY column, roof, wall, turf, pitch, or gate in 3D or 2D plan to open its full specification editor!</li>
                  <li><strong>Catalog Quick Select:</strong> Pick from 281 real products in the catalog (0.50mm PPGL, 4.0mm Netting, 50mm Turf, etc.).</li>
                  <li><strong>Custom Rates &amp; Brands:</strong> Modify brand (Tata Bluescope, AMNS, Apollo, Garware), thickness, rate, and proposal bullets.</li>
                </ul>
              </div>
            </div>

            <div class="col-md-6">
              <div class="p-3 rounded bg-secondary bg-opacity-25 h-100 border border-secondary">
                <h6 class="text-primary"><i class="fa fa-cube me-2"></i>4. 3D BIM Model &amp; Revit Quad View</h6>
                <ul class="ps-3 mb-0 small text-light">
                  <li><strong>3D BIM Model:</strong> Click <code>3D BIM Model</code> in top ribbon to inspect in 3D WebGL with mouse rotation.</li>
                  <li><strong>Split Screen:</strong> Click <code>2D + 3D Split Screen</code> to design in 2D while watching 3D update live!</li>
                  <li><strong>Revit Quad View:</strong> View all 4 views tiled simultaneously (3D + Plan + Front + Side).</li>
                  <li><strong>Save &amp; Generate:</strong> Click the green <code>Save &amp; Generate Estimation</code> button to update the quotation!</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it, let's start designing!</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="assets/js/three.min.js"></script>
  <script src="assets/js/OrbitControls.js"></script>
  <script src="assets/js/turnkey_court_viewer_v5.js?v=<?= time() ?>"></script>
  <script src="assets/js/gcm_cad_revit_engine.js?v=<?= time() ?>"></script>
  <script src="assets/js/master_product_catalog.js?v=<?= time() ?>"></script>
  <script src="assets/js/gcm_xtreme_bim_engine.js?v=<?= time() ?>"></script>
  <script src="assets/js/gcm_notify.js?v=<?= time() ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Global Studio State & Parametric BIM Components
    var cadState = window.cadState = {
      id: <?= json_encode($editId ?: '') ?>,
      length: <?= json_encode($initL) ?>,
      width: <?= json_encode($initW) ?>,
      height: <?= json_encode($initH) ?>,
      sport_type: <?= json_encode($initSport) ?>,
      package: <?= json_encode($initPkg) ?>,
      pitch_length: <?= json_encode($initPitch) ?>,
      gates: <?= json_encode($initGates) ?>,
      claddings: <?= json_encode($initCladding) ?>,
      zones: <?= json_encode($initZones) ?>,
      currentUnit: 'FT_IN',
      activeBrush: { id: 'ppgl_sheet', name: '0.50mm Tata Bluescope PPGL GI Sheet', rate: 110 },
      isDraggingWall: false,
      activeDragHandle: null,
      currentView: '2d',
      activeEditingComponentId: null,

      // Parametric BIM Components Model (Every single item can be modified)
      components: <?= json_encode($initCustomSpecs ?? [
        'civil_foundation' => [
          'id' => 'civil_foundation',
          'name' => 'Engineered Porous WMM Base & Foundation Footings',
          'category' => 'Civil Works',
          'brand' => 'M20 Concrete & Compacted WMM',
          'spec' => '100mm (4") compacted Wet Mix Macadam (WMM) @ 98% Proctor density + 25mm stone dust screening + M20 block footings with anchor bolts',
          'gauge' => '100mm WMM + M20 Footings',
          'unit' => 'SQFT',
          'rate' => 40,
          'hsn' => '9954',
          'gst_rate' => 18,
          'qty' => $initL * $initW,
          'included' => true,
          'bullets' => ['100mm compacted WMM base with 8-10 ton vibratory roller', '25mm stone dust leveling layer for sub-surface rainwater drainage', 'M20 grade concrete block foundations with high-tensile anchor bolts']
        ],
        'columns_structure' => [
          'id' => 'columns_structure',
          'name' => 'Structural Steel Columns & Tie Framework (YST-310)',
          'category' => 'Structural Framework',
          'brand' => 'Tata Structura / Apollo YST-310',
          'spec' => 'Two-Lines Ladder Type Column (40mm × 40mm Square Pipe, 2.0mm / 14 Gauge) with welded ladder rungs @ 1.5ft c/c & base plates',
          'gauge' => '2.0mm Wall Thickness (14 Gauge)',
          'unit' => 'NOS',
          'rate' => round((2 * ceil($initL / 15) + 2 * ceil($initW / 15)) > 0 ? (1340000 / (2 * ceil($initL / 15) + 2 * ceil($initW / 15))) : 37110),
          'hsn' => '7308',
          'gst_rate' => 18,
          'qty' => 2 * ceil($initL / 15) + 2 * ceil($initW / 15),
          'included' => true,
          'bullets' => ['Two-Lines ladder structural stanchions with climbing rungs @ 1.5ft c/c', '40mm × 40mm MS/GI Hollow Structural Sections @ 2.0mm (14G)', 'Heavy M20 base plates anchored with 16mm chemical expansion bolts']
        ],
        'roof_canopy' => [
          'id' => 'roof_canopy',
          'included' => !$isTopExcluded,
          'name' => 'Curved Arch Tubular Steel Roof Truss & Canopy',
          'category' => 'Roofing & Canopy',
          'brand' => 'Tata Bluescope Colorbond AZ-150',
          'spec' => '0.50mm Pre-Painted Galvalume (AZ-150 / 550 MPa) trapezoidal profile sheets on curved tubular trusses with daylighting bays & gutters',
          'gauge' => '0.50mm TCT (AZ-150 / 550 MPa)',
          'unit' => 'SQFT',
          'rate' => 240,
          'hsn' => '7210',
          'gst_rate' => 18,
          'qty' => round($initL * $initW * 1.08),
          'included' => true,
          'bullets' => ['0.50mm Tata Bluescope Colorbond PPGL roofing sheets', 'Curved arch tubular steel roof trusses with purlins and sag rods', 'Integrated daylighting polycarbonate sheets along center ridge', 'Galvalume rainwater gutters with PVC downpipes']
        ],
        'wall_enclosures' => [
          'id' => 'wall_enclosures',
          'name' => 'Perimeter Wall Enclosures & Ball-Stop Netting',
          'category' => 'Wall Enclosures & Netting',
          'brand' => 'AMNS PPGL / Garware Technical Fibres',
          'spec' => '0.50mm PPGL GI Profile Sheets on lower tiers + 2.5mm / 4.0mm UV-Stabilized Braided HDPE Ball-Stop Netting with 6mm wire ropes',
          'gauge' => '0.50mm Sheet + 2.5mm HDPE Net (40mm Mesh)',
          'unit' => 'SQFT',
          'rate' => 110,
          'hsn' => '5608',
          'gst_rate' => 18,
          'qty' => 2 * ($initL + $initW) * $initH,
          'included' => true,
          'bullets' => ['Pre-painted Galvalume AZ-150 profile wall cladding on selected sides', 'High-tenacity UV-treated braided HDPE containment netting', '6mm PVC-coated galvanized wire rope grid with heavy turnbuckles']
        ],
        'sports_flooring' => [
          'id' => 'sports_flooring',
          'name' => '50mm UV Artificial Sports Grass Turf with Infill',
          'category' => 'Sports Flooring',
          'brand' => 'CCGrass / Bellinturf FIFA Quality',
          'spec' => '50mm Diamond Spine Monofilament Polyethylene grass, 12,000 DTEX, dual PP backing, filled with graded silica sand (25kg/sqm)',
          'gauge' => '50mm Pile Height • 12,000 DTEX',
          'unit' => 'SQFT',
          'rate' => 145,
          'hsn' => '5703',
          'gst_rate' => 18,
          'qty' => $initL * $initW,
          'included' => true,
          'bullets' => ['50mm UV-resistant diamond spine monofilament artificial grass', 'Kiln-dried rounded silica sand infill @ 25 kg/sqm', 'Seamless thermal seam tape with high-strength PU adhesive']
        ],
        'cricket_pitch' => [
          'id' => 'cricket_pitch',
          'name' => 'Central Competition Cricket Pitch Pad',
          'category' => 'Sports Flooring',
          'brand' => 'TurfPro 15mm Curled Wicket',
          'spec' => '15mm High-Density Curled Polypropylene Cricket Wicket Mat with regulation popping and bowling crease markings and wicket stumps',
          'gauge' => '15mm High Density Curled Yarn',
          'unit' => 'SQFT',
          'rate' => 78,
          'hsn' => '5703',
          'gst_rate' => 18,
          'qty' => round($initPitch * 6.5),
          'included' => true,
          'bullets' => ['15mm curled polypropylene wicket turf with optimal bounce', 'Permanent white tufted crease markings (batting, bowling, return)', 'Engineered sub-base pad with shock attenuation']
        ],
        'badminton_arena' => [
          'id' => 'badminton_arena',
          'name' => 'BWF Certified Indoor Badminton Court Flooring',
          'category' => 'Sports Flooring',
          'brand' => 'Gerflor Taraflex / Enlio BWF Approved',
          'spec' => '4.5mm Multi-Layer Anti-Slip Sand-Surface Vinyl Sports Mat with high-density foam backing, tournament boundary lines and tournament posts & nets',
          'gauge' => '4.5mm BWF Certified Anti-Slip Vinyl',
          'unit' => 'SQFT',
          'rate' => 185,
          'hsn' => '3918',
          'gst_rate' => 18,
          'qty' => 1760,
          'included' => ($initSport === 'badminton' || ($initZones && !empty($initZones['badminton']))),
          'bullets' => ['4.5mm BWF approved competition sand-surface vinyl sports mat', 'High-density closed-cell PVC foam backing for shock absorption', 'Official BWF boundary line markings with tournament post & net sets']
        ],
        'access_gates' => [
          'id' => 'access_gates',
          'name' => 'Tubular Steel Structural Access Gate (6×7 FT)',
          'category' => 'Gates & Hardware',
          'brand' => 'GCM Structural Fabrication',
          'spec' => '6 FT wide × 7 FT high heavy tubular steel gate (40×40 YST-310 frame) with industrial barrel hinges, padlock hasp, ground latch and side infill',
          'gauge' => '40×40mm YST-310 Frame (2.0mm)',
          'unit' => 'NOS',
          'rate' => 18500,
          'hsn' => '7308',
          'gst_rate' => 18,
          'qty' => 2,
          'included' => true,
          'bullets' => ['Heavy 40×40mm square tubular steel gate frame with anti-sag diagonal brace', 'Heavy-duty steel barrel hinges, padlock eyes & center drop bolt', 'Finished with zinc-chromate primer and weather-resistant enamel paint']
        ],
        'stadium_lighting' => [
          'id' => 'stadium_lighting',
          'name' => 'High-Output Asymmetric Stadium LED Floodlights',
          'category' => 'Sports Lighting',
          'brand' => 'Philips Sports / Osram Stadium Optic IP66',
          'spec' => '200W IP66 die-cast aluminum high-output stadium floodlights with toughened glass, asymmetric glare control optics delivering 350-500 Lux, with Polycab cabling & MCB DB',
          'gauge' => '200W IP66 • 350-500 Lux Target',
          'unit' => 'NOS',
          'rate' => 6500,
          'hsn' => '9405',
          'gst_rate' => 18,
          'qty' => 8,
          'included' => true,
          'bullets' => ['200W high-output asymmetric floodlights with toughened glass lens', 'IP66/IP67 rated waterproof driver and aluminum die-cast housing', 'Includes Polycab 3-core copper armored cable, MCB DB panel & timer switch']
        ],
        'amenities_dugout' => [
          'id' => 'amenities_dugout',
          'name' => 'Player Dugout Benches, Reception & Court Accessories',
          'category' => 'Amenities & Civil Rooms',
          'brand' => 'GCM Arena Fixtures',
          'spec' => 'Tubular steel shaded player dugout seating benches, equipment storage box, perimeter boundary rope & umpire high chair',
          'gauge' => 'Heavy Tubular Steel & HDPE Slats',
          'unit' => 'SET',
          'rate' => 45000,
          'hsn' => '9403',
          'gst_rate' => 18,
          'qty' => 1,
          'included' => true,
          'bullets' => ['Dual team shaded player dugout benches (10-seater capacity each)', 'Heavy-duty lockable sports equipment storage chest', 'Match umpire elevated chair and perimeter boundary demarcation']
        ]
      ]) ?>
    };

    // Initialize Drawing Board & BIM Components
    document.addEventListener('DOMContentLoaded', function() {
      syncInputValues();
      renderBimComponentsList();
      renderCatalogProducts();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
    });

    function syncInputValues() {
      var inL = document.getElementById('numInputLength');
      var inW = document.getElementById('numInputWidth');
      var inH = document.getElementById('numInputHeight');
      var inP = document.getElementById('numInputPitch');
      if (inL) inL.value = Math.round(cadState.length);
      if (inW) inW.value = Math.round(cadState.width);
      if (inH) inH.value = Math.round(cadState.height);
      if (inP) inP.value = Math.round(cadState.pitch_length);

      // Sync labels
      ['roof', 'front', 'back', 'left', 'right', 'floor'].forEach(function(s) {
        var lbl = document.getElementById('lblMat' + s.charAt(0).toUpperCase() + s.slice(1));
        if (lbl) {
          var names = { ppgl_sheet: 'PPGL Sheet', netting: 'Netting', open: 'Open-to-Sky', sports_turf: '50mm Turf', vinyl_mat: 'Vinyl Mat', polycarbonate: 'Polycarb' };
          lbl.textContent = names[cadState.claddings[s]] || cadState.claddings[s];
        }
      });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. BIM BUILDING COMPONENTS & FULL SPECIFICATIONS PALETTE (Admin Panel)
    // ──────────────────────────────────────────────────────────────────────────
    function renderBimComponentsList() {
      var container = document.getElementById('bimComponentsList');
      if (!container) return;
      container.innerHTML = '';

      var icons = {
        civil_foundation: 'fa-trowel-bricks text-secondary',
        columns_structure: 'fa-monument text-info',
        roof_canopy: 'fa-warehouse text-warning',
        wall_enclosures: 'fa-sheet-plastic text-warning',
        sports_flooring: 'fa-seedling text-success',
        cricket_pitch: 'fa-baseball-bat-ball text-info',
        badminton_arena: 'fa-table-tennis-paddle-ball text-success',
        access_gates: 'fa-door-open text-danger',
        stadium_lighting: 'fa-lightbulb text-warning',
        amenities_dugout: 'fa-couch text-primary'
      };

      Object.keys(cadState.components).forEach(function(cId) {
        var comp = cadState.components[cId];
        var card = document.createElement('div');
        card.className = 'bim-comp-card';
        card.dataset.id = cId;

        var lineTotal = Math.round((comp.qty || 1) * (comp.rate || 0));
        var isInc = (comp.included !== false);

        card.style.opacity = isInc ? '1' : '0.65';
        card.style.borderColor = isInc ? '#334155' : '#ef4444';

        var priceBadge = isInc 
          ? '<span class="badge bg-success" style="font-size:9.5px;">₹' + lineTotal.toLocaleString('en-IN') + '</span>'
          : '<span class="badge bg-danger bg-opacity-75" style="font-size:9.5px;">EXCLUDED • ₹0</span>';

        var incBtn = isInc
          ? '<button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 fw-bold" onclick="event.stopPropagation(); toggleComponentInclusion(\'' + cId + '\', false)" title="Delete / Exclude this item from 3D model, 2D plan, and calculation"><i class="fa fa-trash me-1"></i>Exclude</button>'
          : '<button type="button" class="btn btn-xs btn-success py-0 px-2 fw-bold" onclick="event.stopPropagation(); toggleComponentInclusion(\'' + cId + '\', true)" title="Re-include this item in project & calculation"><i class="fa fa-plus me-1"></i>Include</button>';

        card.innerHTML = 
          '<div class="d-flex justify-content-between align-items-center mb-1">' +
            '<div class="fw-bold text-light" style="font-size:11px;">' +
              '<i class="fa ' + (icons[cId] || 'fa-cube text-info') + ' me-1"></i>' + comp.name.split('(')[0] +
            '</div>' +
            priceBadge +
          '</div>' +
          '<div style="font-size:10px; color:' + (isInc ? '#38bdf8' : '#94a3b8') + ';" class="mb-1">' +
            '<i class="fa fa-tag me-1"></i><strong>' + (comp.brand || 'Standard') + '</strong> &bull; ' + (comp.gauge || comp.spec.substring(0, 35)) +
          '</div>' +
          '<div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top border-secondary">' +
            '<span style="font-size:9.5px; color:#94a3b8;">' + (comp.qty || 1).toLocaleString('en-IN') + ' ' + (comp.unit || 'SQFT') + ' @ ₹' + (comp.rate || 0) + '/' + (comp.unit || 'SQFT') + '</span>' +
            '<div class="d-flex gap-1">' +
              incBtn +
              '<button type="button" class="btn btn-xs btn-outline-info py-0 px-2 fw-bold" onclick="openComponentSpecEditor(\'' + cId + '\')">' +
                '<i class="fa fa-pen me-1"></i>Modify Specs' +
              '</button>' +
            '</div>' +
          '</div>';

        container.appendChild(card);
      });
    }

    // Open Universal Component Specification Editor Modal
    function openComponentSpecEditor(cId) {
      var comp = cadState.components[cId];
      if (!comp) return;

      cadState.activeEditingComponentId = cId;

      document.getElementById('modalCompTitle').textContent = comp.name;
      document.getElementById('modalCompCatBadge').textContent = comp.category || 'Building Component';

      document.getElementById('modalInputName').value = comp.name || '';
      document.getElementById('modalInputBrand').value = comp.brand || '';
      document.getElementById('modalInputSpec').value = comp.spec || '';
      document.getElementById('modalInputGauge').value = comp.gauge || '';
      document.getElementById('modalInputHsn').value = comp.hsn || '7308';
      document.getElementById('modalInputGst').value = String(comp.gst_rate || 18);
      document.getElementById('modalInputQty').value = comp.qty || 1;
      document.getElementById('modalInputUnit').value = comp.unit || 'SQFT';
      document.getElementById('modalInputRate').value = comp.rate || 0;

      var bulletsText = (comp.bullets && comp.bullets.length) ? comp.bullets.join('\n') : '';
      document.getElementById('modalInputBullets').value = bulletsText;

      // Update inclusion status in modal
      updateModalInclusionDisplay(comp.included !== false);

      recalcModalAmount();
      populateCatalogSelector(comp.category);

      var modalEl = document.getElementById('compSpecEditorModal');
      var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.show();
    }

    function recalcModalAmount() {
      var qty = parseFloat(document.getElementById('modalInputQty').value) || 0;
      var rate = parseFloat(document.getElementById('modalInputRate').value) || 0;
      var total = Math.round(qty * rate);
      document.getElementById('modalInputAmount').value = '₹' + total.toLocaleString('en-IN');
    }
    function updateModalInclusionDisplay(isIncluded) {
      var badge = document.getElementById('modalInclusionStatusBadge');
      var btnExclude = document.getElementById('btnModalExcludeAction');
      var btnInclude = document.getElementById('btnModalIncludeAction');
      var banner = document.getElementById('modalInclusionBanner');

      if (!badge) return;

      if (isIncluded) {
        badge.className = 'badge bg-success fw-bold px-2 py-1';
        badge.innerHTML = '<i class="fa fa-check-circle me-1"></i>INCLUDED IN ESTIMATION &amp; 3D MODEL';
        if (btnExclude) btnExclude.style.display = 'inline-block';
        if (btnInclude) btnInclude.style.display = 'none';
        if (banner) {
          banner.style.borderColor = '#22c55e';
          banner.style.background = 'rgba(34, 197, 94, 0.1)';
        }
      } else {
        badge.className = 'badge bg-danger fw-bold px-2 py-1';
        badge.innerHTML = '<i class="fa fa-ban me-1"></i>EXCLUDED (COST IS ₹0 &amp; HIDDEN FROM 3D)';
        if (btnExclude) btnExclude.style.display = 'none';
        if (btnInclude) btnInclude.style.display = 'inline-block';
        if (banner) {
          banner.style.borderColor = '#ef4444';
          banner.style.background = 'rgba(239, 68, 68, 0.15)';
        }
      }
    }

    function toggleCurrentModalInclusion(isIncluded) {
      var cId = cadState.activeEditingComponentId;
      if (!cId || !cadState.components[cId]) return;
      toggleComponentInclusion(cId, isIncluded);
      updateModalInclusionDisplay(isIncluded);
    }

    function deleteCurrentComponentFromAllViews() {
      var cId = cadState.activeEditingComponentId;
      if (!cId || !cadState.components[cId]) return;
      var comp = cadState.components[cId];

      if (confirm('Delete / Exclude "' + comp.name + '" from 3D model, 2D plan, elevations, and estimation calculation?')) {
        toggleComponentInclusion(cId, false);
        var modalEl = document.getElementById('compSpecEditorModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
    }

    function toggleComponentInclusion(cId, isIncluded) {
      var comp = cadState.components[cId];
      if (!comp) return;
      comp.included = !!isIncluded;

      // Bi-directional synchronizations
      if (cId === 'roof_canopy') {
        cadState.claddings.roof = comp.included ? 'ppgl_sheet' : 'open';
      } else if (cId === 'wall_enclosures') {
        if (!comp.included) {
          cadState.claddings.front = 'open';
          cadState.claddings.back  = 'open';
          cadState.claddings.left  = 'open';
          cadState.claddings.right = 'open';
        } else {
          cadState.claddings.front = 'ppgl_sheet';
          cadState.claddings.back  = 'ppgl_sheet';
          cadState.claddings.left  = 'ppgl_sheet';
          cadState.claddings.right = 'ppgl_sheet';
        }
      } else if (cId === 'cricket_pitch') {
        cadState.pitch_length = comp.included ? 66 : 0;
      } else if (cId === 'badminton_arena') {
        if (!comp.included) {
          if (cadState.zones) delete cadState.zones.badminton;
        } else {
          if (!cadState.zones) {
            attachBadmintonToCricket();
            return;
          }
        }
      }

      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();

      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }

      var msg = isIncluded ? ('Included "' + comp.name + '" in project & estimation!') : ('Excluded "' + comp.name + '" from 3D model, 2D plan, and calculation!');
      showNotificationToast(msg, isIncluded ? 'success' : 'danger');
    }

    function showNotificationToast(msg, type) {
      var toastContainer = document.getElementById('cadToastContainer');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'cadToastContainer';
        toastContainer.style.cssText = 'position:fixed; bottom:20px; right:20px; z-index:99999; display:flex; flex-direction:column; gap:8px;';
        document.body.appendChild(toastContainer);
      }

      var toast = document.createElement('div');
      var bgClass = (type === 'success') ? '#059669' : ((type === 'danger') ? '#dc2626' : '#0284c7');
      toast.style.cssText = 'background:' + bgClass + '; color:#ffffff; padding:10px 16px; border-radius:6px; font-size:12px; font-weight:600; box-shadow:0 4px 12px rgba(0,0,0,0.4); display:flex; align-items:center; gap:8px; animation:fadeIn 0.2s ease;';
      toast.innerHTML = '<i class="fa ' + (type === 'success' ? 'fa-check-circle' : 'fa-info-circle') + '"></i><span>' + msg + '</span>';
      toastContainer.appendChild(toast);

      setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
      }, 3500);
    }


    function populateCatalogSelector(category) {
      var select = document.getElementById('modalCatalogSelector');
      select.innerHTML = '<option value="">-- Choose Product to Auto-Fill Specifications &amp; Rates --</option>';

      if (!window.MasterProductCatalog || !window.MasterProductCatalog.length) return;

      var catLower = (category || '').toLowerCase();
      var filtered = window.MasterProductCatalog.filter(function(p) {
        var pCat = (p.category || '').toLowerCase();
        if (catLower.indexOf('roof') !== -1) return pCat.indexOf('roof') !== -1;
        if (catLower.indexOf('wall') !== -1 || catLower.indexOf('net') !== -1) return pCat.indexOf('wall') !== -1 || pCat.indexOf('net') !== -1;
        if (catLower.indexOf('floor') !== -1 || catLower.indexOf('turf') !== -1) return pCat.indexOf('floor') !== -1;
        if (catLower.indexOf('struct') !== -1 || catLower.indexOf('column') !== -1) return pCat.indexOf('struct') !== -1;
        if (catLower.indexOf('light') !== -1) return pCat.indexOf('light') !== -1;
        if (catLower.indexOf('gate') !== -1) return pCat.indexOf('gate') !== -1;
        if (catLower.indexOf('civil') !== -1) return pCat.indexOf('civil') !== -1;
        return true;
      });

      if (!filtered.length) filtered = window.MasterProductCatalog.slice(0, 30);

      filtered.forEach(function(prod) {
        var opt = document.createElement('option');
        opt.value = prod.id;
        opt.textContent = prod.name + ' (₹' + prod.default_rate + '/' + prod.unit + ')';
        select.appendChild(opt);
      });
    }

    function onCatalogProductSelected(prodId) {
      if (!prodId || !window.MasterProductCatalog) return;
      var prod = window.MasterProductCatalog.find(function(p) { return p.id === prodId; });
      if (!prod) return;

      document.getElementById('modalInputName').value = prod.name;
      document.getElementById('modalInputSpec').value = prod.description;
      document.getElementById('modalInputRate').value = prod.default_rate;
      document.getElementById('modalInputUnit').value = prod.unit;
      document.getElementById('modalInputHsn').value = prod.hsn || '7308';
      document.getElementById('modalInputGst').value = String(prod.gst_rate || 18);

      if (prod.specs && prod.specs.length) {
        document.getElementById('modalInputBullets').value = prod.specs.join('\n');
      }

      recalcModalAmount();
    }

    function applyComponentEdit() {
      var cId = cadState.activeEditingComponentId;
      if (!cId || !cadState.components[cId]) return;

      var comp = cadState.components[cId];
      comp.name = document.getElementById('modalInputName').value.trim();
      comp.brand = document.getElementById('modalInputBrand').value.trim();
      comp.spec = document.getElementById('modalInputSpec').value.trim();
      comp.gauge = document.getElementById('modalInputGauge').value.trim();
      comp.hsn = document.getElementById('modalInputHsn').value.trim();
      comp.gst_rate = parseFloat(document.getElementById('modalInputGst').value) || 18;
      comp.qty = parseFloat(document.getElementById('modalInputQty').value) || 1;
      comp.unit = document.getElementById('modalInputUnit').value;
      comp.rate = parseFloat(document.getElementById('modalInputRate').value) || 0;

      var bulletsRaw = document.getElementById('modalInputBullets').value.trim();
      comp.bullets = bulletsRaw ? bulletsRaw.split('\n').map(function(s) { return s.trim(); }).filter(Boolean) : [];

      // Bi-directional synchronizations
      if (cId === 'roof_canopy') {
        if (comp.name.toLowerCase().indexOf('net') !== -1) {
          cadState.claddings.roof = 'netting';
        } else if (comp.name.toLowerCase().indexOf('open') !== -1) {
          cadState.claddings.roof = 'open';
        } else {
          cadState.claddings.roof = 'ppgl_sheet';
        }
      }
      if (cId === 'wall_enclosures') {
        if (comp.name.toLowerCase().indexOf('net') !== -1 && comp.name.toLowerCase().indexOf('sheet') === -1) {
          cadState.claddings.front = 'netting';
          cadState.claddings.back = 'netting';
          cadState.claddings.left = 'netting';
          cadState.claddings.right = 'netting';
        } else if (comp.name.toLowerCase().indexOf('ppgl') !== -1 || comp.name.toLowerCase().indexOf('sheet') !== -1) {
          cadState.claddings.front = 'ppgl_sheet';
          cadState.claddings.back = 'ppgl_sheet';
          cadState.claddings.left = 'ppgl_sheet';
          cadState.claddings.right = 'ppgl_sheet';
        }
      }
      if (cId === 'sports_flooring') {
        cadState.claddings.floor = (comp.name.toLowerCase().indexOf('vinyl') !== -1) ? 'vinyl_mat' : 'sports_turf';
      }

      // Close modal
      var modalEl = document.getElementById('compSpecEditorModal');
      var modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();

      // Live re-renders
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();

      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }

      GcmNotify.success('Component Updated', 'Updated ' + comp.name + ' specifications! Live 3D, 2D plan, and BOQ synchronized.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. 2D CAD DRAWING BOARD ENGINE (SVG Precision Drafting Canvas)
    // ──────────────────────────────────────────────────────────────────────────
    function render2dDrawingBoard() {
      var root = document.getElementById('cadRootGroup');
      if (!root) return;
      root.innerHTML = '';

      var L = parseFloat(cadState.length) || 100;
      var W = parseFloat(cadState.width) || 60;

      // Coordinate mapping: center court in 1400×850 viewBox
      var scale = Math.min(1000 / L, 550 / W, 6.5);
      var courtPixW = L * scale;
      var courtPixH = W * scale;
      var startX = 700 - courtPixW / 2;
      var startY = 425 - courtPixH / 2;

      var st = String(cadState.sport_type || 'box_cricket');
      var isMultiSport = (st.indexOf('multisport') !== -1 || (L >= 140 && W >= 50));
      var isBadmintonOnly = (st === 'badminton');

      if (isMultiSport) {
        // MULTI-SPORT ARENA: Zone 1 (Cricket) + Zone 2 (Badminton) + Zone 3 (Amenities)
        var cricketL = 100;
        var badmL = Math.min(60, L - cricketL);
        var amenL = Math.max(0, L - (cricketL + badmL));

        var ckW_px = cricketL * scale;
        var bdW_px = badmL * scale;
        var amW_px = amenL * scale;

        // ZONE 1: Box Cricket Ground (0 to 100 FT)
        var ckTColor = (cadState.claddings.floor === 'vinyl_mat') ? '#059669' : '#15803d';
        var ckGround = createSvgElem('rect', {
          x: startX, y: startY, width: ckW_px, height: courtPixH,
          fill: ckTColor, stroke: '#334155', 'stroke-width': 1.5, cursor: 'pointer'
        });
        ckGround.onclick = function() { openComponentSpecEditor('sports_flooring'); };
        root.appendChild(ckGround);

        // Cricket boundary
        var ckBound = createSvgElem('rect', {
          x: startX + 10, y: startY + 10, width: ckW_px - 20, height: courtPixH - 20,
          fill: 'none', stroke: '#ffffff', 'stroke-width': 1.8, 'stroke-dasharray': '6,4'
        });
        root.appendChild(ckBound);

        // Cricket Pitch
        var pPixW = (cadState.pitch_length || 66) * scale;
        var pPixH = 6.5 * scale;
        var pX = startX + (ckW_px - pPixW) / 2;
        var pY = startY + (courtPixH - pPixH) / 2;

        var pitchRect = createSvgElem('rect', {
          x: pX, y: pY, width: pPixW, height: pPixH,
          fill: '#0284c7', stroke: '#38bdf8', 'stroke-width': 1.5, rx: 2, cursor: 'pointer'
        });
        pitchRect.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('cricket_pitch'); };
        root.appendChild(pitchRect);

        // Crease lines & wickets
        root.appendChild(createSvgElem('line', { x1: pX + 8, y1: pY, x2: pX + 8, y2: pY + pPixH, stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('line', { x1: pX + pPixW - 8, y1: pY, x2: pX + pPixW - 8, y2: pY + pPixH, stroke: '#ffffff', 'stroke-width': 1.5 }));

        var ckText = createSvgElem('text', {
          x: startX + ckW_px / 2, y: pY - 8, 'text-anchor': 'middle', fill: '#ffffff',
          'font-size': '11px', 'font-weight': 'bold', 'font-family': 'Inter', cursor: 'pointer'
        });
        ckText.textContent = 'ZONE 1: BOX CRICKET GROUND [' + cricketL + '×' + W + ' FT] (Click to Edit Specs)';
        ckText.onclick = function() { openComponentSpecEditor('cricket_pitch'); };
        root.appendChild(ckText);

        // ZONE 2: Indoor Badminton Arena
        var bdStartX = startX + ckW_px;
        var bdGround = createSvgElem('rect', {
          x: bdStartX, y: startY, width: bdW_px, height: courtPixH,
          fill: '#047857', stroke: '#334155', 'stroke-width': 1.5, cursor: 'pointer'
        });
        bdGround.onclick = function() { openComponentSpecEditor('badminton_arena'); };
        root.appendChild(bdGround);

        // Divider Partition Line
        var divLine = createSvgElem('line', {
          x1: bdStartX, y1: startY, x2: bdStartX, y2: startY + courtPixH,
          stroke: '#f59e0b', 'stroke-width': 4, 'stroke-dasharray': '10,4'
        });
        root.appendChild(divLine);

        // Badminton Court 1 & Court 2 markings
        var bCourtW = 44 * scale;
        var bCourtH = 20 * scale;
        var bcX = bdStartX + (bdW_px - bCourtW) / 2;
        var bcY1 = startY + (courtPixH / 2 - bCourtH - 8);
        var bcY2 = startY + (courtPixH / 2 + 8);

        // Court 1
        var c1 = createSvgElem('rect', { x: bcX, y: bcY1, width: bCourtW, height: bCourtH, fill: '#059669', stroke: '#ffffff', 'stroke-width': 1.5, cursor: 'pointer' });
        c1.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('badminton_arena'); };
        root.appendChild(c1);
        root.appendChild(createSvgElem('line', { x1: bcX + bCourtW / 2, y1: bcY1, x2: bcX + bCourtW / 2, y2: bcY1 + bCourtH, stroke: '#ef4444', 'stroke-width': 2 }));

        // Court 2
        var c2 = createSvgElem('rect', { x: bcX, y: bcY2, width: bCourtW, height: bCourtH, fill: '#059669', stroke: '#ffffff', 'stroke-width': 1.5, cursor: 'pointer' });
        c2.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('badminton_arena'); };
        root.appendChild(c2);
        root.appendChild(createSvgElem('line', { x1: bcX + bCourtW / 2, y1: bcY2, x2: bcX + bCourtW / 2, y2: bcY2 + bCourtH, stroke: '#ef4444', 'stroke-width': 2 }));

        var bdText = createSvgElem('text', {
          x: bdStartX + bdW_px / 2, y: startY + 22, 'text-anchor': 'middle', fill: '#facc15',
          'font-size': '11px', 'font-weight': 'bold', 'font-family': 'Inter', cursor: 'pointer'
        });
        bdText.textContent = 'ZONE 2: INDOOR BADMINTON (2 BWF COURTS - Click to Edit)';
        bdText.onclick = function() { openComponentSpecEditor('badminton_arena'); };
        root.appendChild(bdText);

        // ZONE 3: Amenities & Dugout (if any)
        if (amenL > 0) {
          var amStartX = bdStartX + bdW_px;
          var amGround = createSvgElem('rect', {
            x: amStartX, y: startY, width: amW_px, height: courtPixH,
            fill: '#1e293b', stroke: '#334155', 'stroke-width': 1.5, cursor: 'pointer'
          });
          amGround.onclick = function() { openComponentSpecEditor('amenities_dugout'); };
          root.appendChild(amGround);

          var amText = createSvgElem('text', {
            x: amStartX + amW_px / 2, y: startY + courtPixH / 2, 'text-anchor': 'middle', fill: '#94a3b8',
            'font-size': '10px', 'font-weight': 'bold', 'font-family': 'Inter', cursor: 'pointer'
          });
          amText.textContent = 'DUGOUT & RECEPTION';
          amText.onclick = function() { openComponentSpecEditor('amenities_dugout'); };
          root.appendChild(amText);
        }

      } else if (isBadmintonOnly) {
        // BADMINTON ARENA ONLY
        var bGround = createSvgElem('rect', {
          x: startX, y: startY, width: courtPixW, height: courtPixH,
          fill: '#047857', stroke: '#334155', 'stroke-width': 2, cursor: 'pointer'
        });
        bGround.onclick = function() { openComponentSpecEditor('badminton_arena'); };
        root.appendChild(bGround);

        // 2 BWF Courts
        var bcW = 44 * scale;
        var bcH = 20 * scale;
        var bcX = startX + (courtPixW - bcW) / 2;
        var bcY1 = startY + (courtPixH / 2 - bcH - 6);
        var bcY2 = startY + (courtPixH / 2 + 6);

        root.appendChild(createSvgElem('rect', { x: bcX, y: bcY1, width: bcW, height: bcH, fill: '#059669', stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('line', { x1: bcX + bcW / 2, y1: bcY1, x2: bcX + bcW / 2, y2: bcY1 + bcH, stroke: '#ef4444', 'stroke-width': 2 }));

        root.appendChild(createSvgElem('rect', { x: bcX, y: bcY2, width: bcW, height: bcH, fill: '#059669', stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('line', { x1: bcX + bcW / 2, y1: bcY2, x2: bcX + bcW / 2, y2: bcY2 + bcH, stroke: '#ef4444', 'stroke-width': 2 }));

        var bText = createSvgElem('text', {
          x: 700, y: startY + 24, 'text-anchor': 'middle', fill: '#ffffff',
          'font-size': '12px', 'font-weight': 'bold', 'font-family': 'Inter', cursor: 'pointer'
        });
        bText.textContent = 'INDOOR BADMINTON ARENA [' + L + '×' + W + ' FT]';
        bText.onclick = function() { openComponentSpecEditor('badminton_arena'); };
        root.appendChild(bText);

      } else {
        // STANDARD BOX CRICKET GROUND
        var groundColor = (cadState.claddings.floor === 'vinyl_mat') ? '#059669' : '#15803d';
        var groundRect = createSvgElem('rect', {
          x: startX, y: startY, width: courtPixW, height: courtPixH,
          fill: groundColor, stroke: '#334155', 'stroke-width': 2, cursor: 'pointer'
        });
        groundRect.onclick = function() { openComponentSpecEditor('sports_flooring'); };
        root.appendChild(groundRect);

        // Boundary Lines
        var bLine = createSvgElem('rect', {
          x: startX + 15, y: startY + 15, width: courtPixW - 30, height: courtPixH - 30,
          fill: 'none', stroke: '#ffffff', 'stroke-width': 2, 'stroke-dasharray': '6,3'
        });
        root.appendChild(bLine);

        // Cricket Pitch
        var pitchPixW = (cadState.pitch_length || 66) * scale;
        var pitchPixH = 6.5 * scale;
        var pitchX = 700 - pitchPixW / 2;
        var pitchY = 425 - pitchPixH / 2;

        var pitchRect = createSvgElem('rect', {
          x: pitchX, y: pitchY, width: pitchPixW, height: pitchPixH,
          fill: '#0284c7', stroke: '#38bdf8', 'stroke-width': 1.5, rx: 3, cursor: 'pointer'
        });
        pitchRect.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('cricket_pitch'); };
        root.appendChild(pitchRect);

        // Creases
        root.appendChild(createSvgElem('line', { x1: pitchX + 10, y1: pitchY, x2: pitchX + 10, y2: pitchY + pitchPixH, stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('line', { x1: pitchX + pitchPixW - 10, y1: pitchY, x2: pitchX + pitchPixW - 10, y2: pitchY + pitchPixH, stroke: '#ffffff', 'stroke-width': 1.5 }));

        var pitchText = createSvgElem('text', {
          x: 700, y: 425 + 4, 'text-anchor': 'middle', fill: '#ffffff',
          'font-size': '11px', 'font-weight': 'bold', 'font-family': 'Inter', cursor: 'pointer'
        });
        pitchText.textContent = 'CRICKET PITCH (' + (cadState.pitch_length || 66) + "' × 6.5' - Click to Edit)";
        pitchText.onclick = function() { openComponentSpecEditor('cricket_pitch'); };
        root.appendChild(pitchText);
      }

      // Walls with Cladding Colors & Thickness
      var walls = [
        { id: 'left',  x1: startX, y1: startY, x2: startX, y2: startY + courtPixH, label: 'North Wall (L)' },
        { id: 'right', x1: startX + courtPixW, y1: startY, x2: startX + courtPixW, y2: startY + courtPixH, label: 'South Wall (R)' },
        { id: 'front', x1: startX, y1: startY + courtPixH, x2: startX + courtPixW, y2: startY + courtPixH, label: 'Front Wall (Entrance)' },
        { id: 'back',  x1: startX, y1: startY, x2: startX + courtPixW, y2: startY, label: 'Back Wall' }
      ];

      walls.forEach(function(w) {
        var mat = cadState.claddings[w.id] || 'netting';
        var wallColor = (mat === 'ppgl_sheet') ? '#f59e0b' : ((mat === 'open') ? '#64748b' : '#38bdf8');
        var wallStroke = (mat === 'open') ? 2 : 6;
        var dash = (mat === 'open') ? '4,4' : 'none';

        var wallLine = createSvgElem('line', {
          x1: w.x1, y1: w.y1, x2: w.x2, y2: w.y2,
          stroke: wallColor, 'stroke-width': wallStroke, 'stroke-dasharray': dash,
          cursor: 'pointer'
        });

        // Hover tooltip
        var titleElem = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        titleElem.textContent = w.label + ': ' + mat.toUpperCase().replace('_', ' ') + ' (Click to Edit Wall Specifications)';
        wallLine.appendChild(titleElem);

        wallLine.addEventListener('click', function(e) {
          openComponentSpecEditor('wall_enclosures');
        });
        root.appendChild(wallLine);
      });

      // 1. Civil Foundation Curb Outline (Perimeter Ring)
      var curbComp = cadState.components.civil_foundation;
      var isCurbInc = curbComp ? (curbComp.included !== false) : true;
      if (isCurbInc) {
        var curbRect = createSvgElem('rect', {
          x: startX - 8, y: startY - 8, width: courtPixW + 16, height: courtPixH + 16,
          fill: 'none', stroke: '#64748b', 'stroke-width': 1.5, 'stroke-dasharray': '5,3',
          cursor: 'pointer'
        });
        var curbTitle = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        curbTitle.textContent = "Civil Foundation Curb (100mm WMM + M20 Footings) - Click to Edit Specs";
        curbRect.appendChild(curbTitle);
        curbRect.onclick = function() { openComponentSpecEditor('civil_foundation'); };
        root.appendChild(curbRect);
      }

      // 2. Structural Steel Columns (Stanchions every 15-20 FT along perimeter)
      var colComp = cadState.components.columns_structure;
      var isColInc = colComp ? (colComp.included !== false) : true;
      var colSpacingFt = 16;
      var numColsX = Math.max(3, Math.ceil(L / colSpacingFt));
      var actualColDistX = courtPixW / numColsX;

      for (var ci = 0; ci <= numColsX; ci++) {
        var cx = startX + ci * actualColDistX;
        // Top and bottom columns
        [startY, startY + courtPixH].forEach(function(cy, rowIdx) {
          var colRect = createSvgElem('rect', {
            x: cx - 4, y: cy - 4, width: 8, height: 8,
            fill: isColInc ? '#38bdf8' : '#64748b', stroke: '#0f172a', 'stroke-width': 1.5,
            cursor: 'pointer'
          });
          var colTitle = document.createElementNS('http://www.w3.org/2000/svg', 'title');
          colTitle.textContent = "Column " + (rowIdx === 0 ? 'N' : 'S') + (ci + 1) + ": Apollo YST-310 (Click to Edit Specs)";
          colRect.appendChild(colTitle);
          colRect.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('columns_structure'); };
          root.appendChild(colRect);
        });

        // 3. Roof Truss Lines (connecting top and bottom columns)
        var roofComp = cadState.components.roof_canopy;
        var isRoofInc = roofComp ? (roofComp.included !== false) : true;
        if (isRoofInc && ci > 0 && ci < numColsX) {
          var trussLine = createSvgElem('line', {
            x1: cx, y1: startY, x2: cx, y2: startY + courtPixH,
            stroke: '#f59e0b', 'stroke-width': 1.2, 'stroke-dasharray': '4,3',
            opacity: 0.6, cursor: 'pointer'
          });
          var trussTitle = document.createElementNS('http://www.w3.org/2000/svg', 'title');
          trussTitle.textContent = "Roof Truss T" + ci + ": Curved Arch Tubular Steel (Click to Edit Specs)";
          trussLine.appendChild(trussTitle);
          trussLine.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('roof_canopy'); };
          root.appendChild(trussLine);
        }
      }

      // 4. Stadium Floodlight Poles (Corners & Sides)
      var lightComp = cadState.components.stadium_lighting;
      var isLightInc = lightComp ? (lightComp.included !== false) : true;
      if (isLightInc) {
        var lightPositions = [
          { x: startX + 10, y: startY - 14, name: 'L1' },
          { x: startX + courtPixW / 2, y: startY - 14, name: 'L2' },
          { x: startX + courtPixW - 10, y: startY - 14, name: 'L3' },
          { x: startX + 10, y: startY + courtPixH + 14, name: 'L4' },
          { x: startX + courtPixW / 2, y: startY + courtPixH + 14, name: 'L5' },
          { x: startX + courtPixW - 10, y: startY + courtPixH + 14, name: 'L6' }
        ];

        lightPositions.forEach(function(lp) {
          var lpG = createSvgElem('g', { cursor: 'pointer' });
          var lpCirc = createSvgElem('circle', {
            cx: lp.x, cy: lp.y, r: 6, fill: '#facc15', stroke: '#ca8a04', 'stroke-width': 1.5
          });
          var lpTxt = createSvgElem('text', {
            x: lp.x, y: lp.y + 3.5, 'text-anchor': 'middle', fill: '#0f172a',
            'font-size': '8px', 'font-weight': 'bold', 'font-family': 'Inter'
          });
          lpTxt.textContent = lp.name;
          lpG.appendChild(lpCirc);
          lpG.appendChild(lpTxt);

          var lpTitle = document.createElementNS('http://www.w3.org/2000/svg', 'title');
          lpTitle.textContent = lp.name + ": 200W Stadium LED Floodlight (Click to Edit Specs)";
          lpG.appendChild(lpTitle);

          lpG.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('stadium_lighting'); };
          root.appendChild(lpG);
        });
      }

      // Dual-game and sport-specific floor markings
      var isBasketball = cadState.sport_type && String(cadState.sport_type || '').indexOf('basketball') !== -1;
      var isPickleball = cadState.sport_type && String(cadState.sport_type || '').indexOf('pickleball') !== -1;
      var isFootball = cadState.sport_type && String(cadState.sport_type || '').indexOf('football') !== -1;

      if (isBasketball) {
        var bbW = 50 * scale;
        var bbH = 30 * scale;
        var bbx = startX + (courtPixW - bbW) / 2;
        var bby = startY + (courtPixH - bbH) / 2;
        root.appendChild(createSvgElem('rect', { x: bbx, y: bby, width: bbW, height: bbH, fill: 'none', stroke: '#ea580c', 'stroke-width': 1.8 }));
        root.appendChild(createSvgElem('circle', { cx: bbx + bbW/2, cy: bby + bbH/2, r: 6 * scale, fill: 'none', stroke: '#ea580c', 'stroke-width': 1.8 }));
        root.appendChild(createSvgElem('line', { x1: bbx + bbW/2, y1: bby, x2: bbx + bbW/2, y2: bby + bbH, stroke: '#ea580c', 'stroke-width': 1.8 }));
        root.appendChild(createSvgElem('rect', { x: bbx, y: bby + bbH/2 - 6*scale, width: 10*scale, height: 12*scale, fill: 'none', stroke: '#ea580c', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('rect', { x: bbx + bbW - 10*scale, y: bby + bbH/2 - 6*scale, width: 10*scale, height: 12*scale, fill: 'none', stroke: '#ea580c', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('line', { x1: bbx + 2*scale, y1: bby + bbH/2 - 3*scale, x2: bbx + 2*scale, y2: bby + bbH/2 + 3*scale, stroke: '#ef4444', 'stroke-width': 3 }));
        root.appendChild(createSvgElem('line', { x1: bbx + bbW - 2*scale, y1: bby + bbH/2 - 3*scale, x2: bbx + bbW - 2*scale, y2: bby + bbH/2 + 3*scale, stroke: '#ef4444', 'stroke-width': 3 }));
        var bbTxt = createSvgElem('text', { x: bbx + bbW/2, y: bby + bbH - 6, 'text-anchor': 'middle', fill: '#f97316', 'font-size': '10px', 'font-weight': 'bold', 'font-family': 'Inter' });
        bbTxt.textContent = 'FIBA BASKETBALL COURT (DUAL OVERLAY)';
        root.appendChild(bbTxt);
      }

      if (isPickleball) {
        var pbW = 44 * scale;
        var pbH = 20 * scale;
        var pbx = startX + (courtPixW - pbW) / 2;
        var pby = startY + (courtPixH - pbH) / 2;
        root.appendChild(createSvgElem('rect', { x: pbx, y: pby, width: pbW, height: pbH, fill: 'none', stroke: '#eab308', 'stroke-width': 1.8 }));
        root.appendChild(createSvgElem('line', { x1: pbx + pbW/2, y1: pby, x2: pbx + pbW/2, y2: pby + pbH, stroke: '#ffffff', 'stroke-width': 2.5 }));
        root.appendChild(createSvgElem('line', { x1: pbx + pbW/2 - 7*scale, y1: pby, x2: pbx + pbW/2 - 7*scale, y2: pby + pbH, stroke: '#eab308', 'stroke-width': 1.5, 'stroke-dasharray': '5,3' }));
        root.appendChild(createSvgElem('line', { x1: pbx + pbW/2 + 7*scale, y1: pby, x2: pbx + pbW/2 + 7*scale, y2: pby + pbH, stroke: '#eab308', 'stroke-width': 1.5, 'stroke-dasharray': '5,3' }));
        var pbTxt = createSvgElem('text', { x: pbx + pbW/2, y: pby + pbH - 6, 'text-anchor': 'middle', fill: '#fde047', 'font-size': '10px', 'font-weight': 'bold', 'font-family': 'Inter' });
        pbTxt.textContent = 'PRO PICKLEBALL COURT [44×20 FT]';
        root.appendChild(pbTxt);
      }

      if (isFootball) {
        root.appendChild(createSvgElem('circle', { cx: startX + courtPixW/2, cy: startY + courtPixH/2, r: 8 * scale, fill: 'none', stroke: '#ffffff', 'stroke-width': 1.8 }));
        root.appendChild(createSvgElem('line', { x1: startX + courtPixW/2, y1: startY, x2: startX + courtPixW/2, y2: startY + courtPixH, stroke: '#ffffff', 'stroke-width': 1.8 }));
        root.appendChild(createSvgElem('rect', { x: startX, y: startY + courtPixH/2 - 12*scale, width: 14*scale, height: 24*scale, fill: 'none', stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('rect', { x: startX + courtPixW - 14*scale, y: startY + courtPixH/2 - 12*scale, width: 14*scale, height: 24*scale, fill: 'none', stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('rect', { x: startX - 6, y: startY + courtPixH/2 - 6*scale, width: 6, height: 12*scale, fill: '#ef4444', stroke: '#ffffff', 'stroke-width': 1.5 }));
        root.appendChild(createSvgElem('rect', { x: startX + courtPixW, y: startY + courtPixH/2 - 6*scale, width: 6, height: 12*scale, fill: '#ef4444', stroke: '#ffffff', 'stroke-width': 1.5 }));
      }

      // Render Draggable Interactive Access Gates
      renderInteractiveGates(root, startX, startY, courtPixW, courtPixH, scale);

      // Dimension Lines (Clicking dimensions opens Column / Framework Specs)
      renderDimensionString(root, startX, startY - 30, startX + courtPixW, startY - 30, 'LENGTH: ' + formatUnit(L), function() { openComponentSpecEditor('columns_structure'); });
      renderDimensionString(root, startX - 30, startY, startX - 30, startY + courtPixH, 'WIDTH: ' + formatUnit(W), function() { openComponentSpecEditor('columns_structure'); });

      // Interactive Drag Grips on Right (Length) and Bottom (Width)
      renderDragGrip(root, startX + courtPixW, 425, 'length', scale);
      renderDragGrip(root, 700, startY + courtPixH, 'width', scale);
    }

    function renderDimensionString(parent, x1, y1, x2, y2, text, clickHandler) {
      var line = createSvgElem('line', {
        x1: x1, y1: y1, x2: x2, y2: y2, stroke: '#38bdf8', 'stroke-width': 1.5,
        'marker-start': 'url(#cadArr)', 'marker-end': 'url(#cadArr)', cursor: clickHandler ? 'pointer' : 'default'
      });
      if (clickHandler) line.onclick = clickHandler;
      parent.appendChild(line);

      var midX = (x1 + x2) / 2;
      var midY = (y1 + y2) / 2 - 8;
      var txt = createSvgElem('text', {
        x: midX, y: midY, 'text-anchor': 'middle', fill: '#38bdf8',
        'font-size': '12px', 'font-weight': 'bold', 'font-family': 'Inter', cursor: clickHandler ? 'pointer' : 'default'
      });
      txt.textContent = text;
      if (clickHandler) txt.onclick = clickHandler;
      parent.appendChild(txt);
    }

    function renderDragGrip(parent, x, y, dimType, scale) {
      var g = createSvgElem('g', { cursor: (dimType === 'length' ? 'ew-resize' : 'ns-resize') });

      var circle = createSvgElem('circle', {
        cx: x, cy: y, r: 9, class: 'cad-grip'
      });

      // Quick stepper buttons beside grip
      var minusBtn = createSvgElem('text', {
        x: (dimType === 'length' ? x + 20 : x - 30), y: (dimType === 'length' ? y - 10 : y + 22),
        fill: '#ef4444', 'font-size': '12px', 'font-weight': 'bold', cursor: 'pointer'
      });
      minusBtn.textContent = '[-5 FT]';
      minusBtn.onclick = function(e) { e.stopPropagation(); adjustStudioDim(dimType, -5); };

      var plusBtn = createSvgElem('text', {
        x: (dimType === 'length' ? x + 20 : x + 15), y: (dimType === 'length' ? y + 15 : y + 22),
        fill: '#22c55e', 'font-size': '12px', 'font-weight': 'bold', cursor: 'pointer'
      });
      plusBtn.textContent = '[+5 FT]';
      plusBtn.onclick = function(e) { e.stopPropagation(); adjustStudioDim(dimType, 5); };

      g.appendChild(circle);
      g.appendChild(minusBtn);
      g.appendChild(plusBtn);

      // Mouse drag setup
      g.addEventListener('mousedown', function(e) {
        startDragging(dimType, e.clientX, e.clientY);
      });

      parent.appendChild(g);
    }

    function createSvgElem(tag, attrs) {
      var elem = document.createElementNS('http://www.w3.org/2000/svg', tag);
      for (var k in attrs) {
        elem.setAttribute(k, attrs[k]);
      }
      return elem;
    }

    // Dragging Logic
    var dragStartX = 0, dragStartY = 0, dragOrigVal = 0, activeDim = null;

    function startDragging(dim, clientX, clientY) {
      activeDim = dim;
      dragStartX = clientX;
      dragStartY = clientY;
      dragOrigVal = (dim === 'length') ? cadState.length : cadState.width;

      window.addEventListener('mousemove', onDragging);
      window.addEventListener('mouseup', stopDragging);
    }

    function onDragging(e) {
      if (!activeDim) return;
      var delta = (activeDim === 'length') ? (e.clientX - dragStartX) : (e.clientY - dragStartY);
      var deltaFeet = Math.round(delta / 6); // scale approx 6px per ft

      var newVal = Math.max(30, Math.min(300, dragOrigVal + deltaFeet));
      if (activeDim === 'length') {
        cadState.length = newVal;
      } else {
        cadState.width = newVal;
      }

      // Update quantities in components
      updateComponentQuantities();

      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
    }

    function stopDragging() {
      activeDim = null;
      window.removeEventListener('mousemove', onDragging);
      window.removeEventListener('mouseup', stopDragging);
    }

    function adjustStudioDim(dim, delta) {
      if (dim === 'length') {
        cadState.length = Math.max(30, Math.min(300, cadState.length + delta));
      } else if (dim === 'width') {
        cadState.width = Math.max(20, Math.min(200, cadState.width + delta));
      } else {
        cadState.height = Math.max(12, Math.min(60, cadState.height + delta));
      }

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();

      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function setStudioDimValue(dim, val) {
      var num = parseFloat(val);
      if (isNaN(num)) return;
      if (dim === 'length') cadState.length = Math.max(30, Math.min(300, num));
      if (dim === 'width') cadState.width = Math.max(20, Math.min(200, num));
      if (dim === 'height') cadState.height = Math.max(12, Math.min(60, num));
      if (dim === 'pitch') cadState.pitch_length = Math.max(30, Math.min(75, num));

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();

      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function updateComponentQuantities() {
      var L = cadState.length;
      var W = cadState.width;
      var H = cadState.height;
      var area = L * W;
      var perim = 2 * (L + W);

      if (cadState.components.civil_foundation) cadState.components.civil_foundation.qty = area;
      if (cadState.components.columns_structure) cadState.components.columns_structure.qty = 2 * Math.ceil(L / 15) + 2 * Math.ceil(W / 15);
      if (cadState.components.roof_canopy) cadState.components.roof_canopy.qty = Math.round(area * 1.08);
      if (cadState.components.wall_enclosures) cadState.components.wall_enclosures.qty = perim * H;
      if (cadState.components.sports_flooring) cadState.components.sports_flooring.qty = area;
      if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.qty = Math.round((cadState.pitch_length || 66) * 6.5);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. MATERIAL BRUSH & SPECIFIC SIDE APPLIER
    // ──────────────────────────────────────────────────────────────────────────
    function renderCatalogProducts() {
      var container = document.getElementById('matCardsList');
      if (!container) return;
      container.innerHTML = '';

      var defaultItems = [
        { id: 'ppgl_sheet', cat: 'wall roof', name: '0.50mm PPGL GI Profile Sheet', sub: 'Tata Bluescope / AMNS Colorbond AZ-150', rate: 110, icon: 'fa-sheet-plastic text-warning', compId: 'wall_enclosures' },
        { id: 'netting', cat: 'wall net roof', name: 'HDPE Sports Ball-Stop Netting', sub: '40mm Mesh 2.5mm Braided Nylon UV Treated', rate: 14, icon: 'fa-border-all text-secondary', compId: 'wall_enclosures' },
        { id: 'sports_turf', cat: 'turf', name: '50mm UV Artificial Sports Turf', sub: 'Diamond Spine 12,000 Dtex Monofilament Grass', rate: 145, icon: 'fa-seedling text-success', compId: 'sports_flooring' },
        { id: 'vinyl_mat', cat: 'turf', name: '4.5mm BWF Vinyl Sports Mat', sub: 'Official Anti-Slip Sand Surface Badminton Flooring', rate: 185, icon: 'fa-table-tennis-paddle-ball text-warning', compId: 'badminton_arena' },
        { id: 'curved_truss', cat: 'roof steel', name: 'Curved Tubular Steel Roof Truss', sub: 'High-Pitch Arch Steel Trusses with Purlins & Gutters', rate: 240, icon: 'fa-warehouse text-info', compId: 'roof_canopy' },
        { id: 'polycarbonate', cat: 'roof', name: 'Polycarbonate Daylighting Sheets', sub: 'UV-Coated Multiwall Transparent Skylight Panels', rate: 165, icon: 'fa-sun text-warning', compId: 'roof_canopy' },
        { id: 'stadium_led', cat: 'steel', name: '200W Stadium LED Floodlight Fixture', sub: 'IP66 Waterproof Asymmetric Optics 350-500 Lux', rate: 6500, icon: 'fa-lightbulb text-warning', compId: 'stadium_lighting' },
        { id: 'access_gate', cat: 'wall steel', name: 'Tubular Steel Access Gate (6×7 FT)', sub: 'Heavy 40×40 YST-310 Frame with Padlock Hasp', rate: 18500, icon: 'fa-door-open text-danger', compId: 'access_gates' }
      ];

      defaultItems.forEach(function(item) {
        var card = document.createElement('div');
        card.className = 'mat-card' + (item.id === 'ppgl_sheet' ? ' selected-brush' : '');
        card.dataset.cat = item.cat;
        card.dataset.id = item.id;
        card.onclick = function() { selectMaterialBrush(item.id, item.name, item.rate); };

        card.innerHTML = 
          '<div class="d-flex justify-content-between align-items-center">' +
            '<strong style="font-size:11px; color:#38bdf8;"><i class="fa ' + item.icon + ' me-1"></i>' + item.name + '</strong>' +
            '<span class="badge bg-success" style="font-size:9px;">₹' + item.rate + '</span>' +
          '</div>' +
          '<div style="font-size:9.5px; color:#94a3b8; margin-top:2px;">' + item.sub + '</div>' +
          '<div class="d-flex gap-1 mt-1 justify-content-between align-items-center">' +
            '<span style="font-size:9px; color:#64748b;">Click to set brush</span>' +
            '<button type="button" class="btn btn-xs btn-outline-info py-0 px-2 fw-bold" onclick="event.stopPropagation(); openComponentSpecEditor(\'' + item.compId + '\')">Modify Specs &rarr;</button>' +
          '</div>';

        container.appendChild(card);
      });
    }

    function selectMaterialBrush(matId, name, rate) {
      cadState.activeBrush = { id: matId, name: name, rate: rate };

      document.getElementById('activeBrushTitle').textContent = name;
      document.querySelectorAll('.mat-card').forEach(function(c) {
        if (c.dataset.id === matId) {
          c.classList.add('selected-brush');
        } else {
          c.classList.remove('selected-brush');
        }
      });
    }

    function applySideMat(sideId, matId) {
      cadState.claddings[sideId] = matId;
      syncInputValues();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function applyMaterialToTarget(matId, target, e) {
      if (e) e.stopPropagation();
      if (target === 'all_walls') {
        cadState.claddings.front = matId;
        cadState.claddings.back  = matId;
        cadState.claddings.left  = matId;
        cadState.claddings.right = matId;
      } else {
        cadState.claddings[target] = matId;
      }

      syncInputValues();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function toggleAllSidesCladding(matId) {
      applyMaterialToTarget(matId, 'all_walls', null);
    }

    function setRoofOpen() {
      applySideMat('roof', 'open');
    }

    function placeGateOnActiveWall(e, wall) {
      if (e) e.stopPropagation();
      wall = wall || 'front';
      if (!cadState.gates) cadState.gates = [];
      cadState.gates.push({
        id: 'gate_' + (cadState.gates.length + 1),
        wall: wall,
        position_pct: 50,
        width_ft: 6,
        height_ft: 7
      });
      render2dDrawingBoard();
      recalcStudioBoq();
      if (window.GcmNotify) {
        GcmNotify.success('Gate Placed', 'Placed 6×7 FT Access Gate on ' + wall.toUpperCase() + ' Wall! Drag handle on 2D drawing board to adjust.');
      }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. SPORTS FACILITY PRESETS & ATTACH BADMINTON
    // ──────────────────────────────────────────────────────────────────────────
    function presetBoxCricket() {
      cadState.length = 100;
      cadState.width  = 60;
      cadState.height = 25;
      cadState.sport_type = 'box_cricket';
      cadState.pitch_length = 66;
      cadState.claddings = { roof: 'netting', front: 'netting', back: 'netting', left: 'netting', right: 'netting', floor: 'sports_turf' };
      cadState.zones = null;

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function presetBadmintonArena() {
      cadState.length = 60;
      cadState.width  = 40;
      cadState.height = 30;
      cadState.sport_type = 'badminton';
      cadState.claddings = { roof: 'ppgl_sheet', front: 'ppgl_sheet', back: 'ppgl_sheet', left: 'ppgl_sheet', right: 'ppgl_sheet', floor: 'vinyl_mat' };
      cadState.zones = null;

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function presetMultiSport160() {
      cadState.length = 160;
      cadState.width  = 60;
      cadState.height = 30;
      cadState.sport_type = 'multisport_arena_complex_185x60';
      cadState.pitch_length = 66;
      cadState.claddings = { roof: 'ppgl_sheet', front: 'ppgl_sheet', back: 'ppgl_sheet', left: 'ppgl_sheet', right: 'ppgl_sheet', floor: 'sports_turf' };
      cadState.zones = {
        cricket: { length: 100, width: 60, floor: 'sports_turf' },
        badminton: { length: 60, width: 60, floor: 'vinyl_mat', courts: 2 }
      };

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }
    }

    function attachBadmintonToCricket() {
      if (cadState.length < 160) {
        cadState.length = 160;
      }
      if (cadState.width < 60) {
        cadState.width = 60;
      }
      cadState.sport_type = 'multisport_arena_complex_185x60';
      cadState.height = Math.max(30, cadState.height);
      cadState.zones = {
        cricket: { length: 100, width: cadState.width, floor: 'sports_turf', name: 'Box Cricket Ground' },
        badminton: { length: 60, width: cadState.width, floor: 'vinyl_mat', courts: 2, name: 'Indoor Badminton Arena' }
      };

      if (cadState.components.badminton_arena) {
        cadState.components.badminton_arena.included = true;
      }

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }

      GcmNotify.success('Arena Attached', 'Attached 60 FT Indoor Badminton Arena (2 BWF Courts) to your Cricket Ground! Total Length is now ' + cadState.length + ' FT.');
    }

    function encloseBadmintonArea() {
      wrapAllSidesGiSheets();
    }

    function presetBadmintonOnlyIndoor() {
      cadState.length = 88;
      cadState.width  = 44;
      cadState.height = 30;
      cadState.sport_type = 'badminton';
      cadState.pitch_length = 0;
      cadState.claddings = {
        roof: 'ppgl_sheet',
        front: 'ppgl_sheet',
        back: 'ppgl_sheet',
        left: 'ppgl_sheet',
        right: 'ppgl_sheet',
        floor: 'vinyl_mat'
      };
      cadState.zones = null;

      if (cadState.components.cricket_pitch) {
        cadState.components.cricket_pitch.included = false;
      }
      if (cadState.components.badminton_arena) {
        cadState.components.badminton_arena.included = true;
      }
      if (cadState.components.wall_enclosures) {
        cadState.components.wall_enclosures.included = true;
        cadState.components.wall_enclosures.name = '0.50mm Pre-Painted Galvalume (PPGL) Profile Wall Cladding (AZ-150 / 550 MPa)';
        cadState.components.wall_enclosures.brand = 'Tata Bluescope / AMNS';
        cadState.components.wall_enclosures.rate = 125;
      }
      if (cadState.components.roof_canopy) {
        cadState.components.roof_canopy.included = true;
        cadState.components.roof_canopy.name = 'Curved Arch Tubular Steel Roof Truss & Canopy (0.50mm PPGL)';
        cadState.components.roof_canopy.brand = 'Tata Bluescope Colorbond AZ-150';
        cadState.components.roof_canopy.rate = 240;
      }
      if (cadState.components.sports_flooring) {
        cadState.components.sports_flooring.included = true;
        cadState.components.sports_flooring.name = 'BWF Competition Sand-Surface Vinyl Sports Flooring (4.5mm)';
        cadState.components.sports_flooring.brand = 'Gerflor Taraflex / Enlio';
        cadState.components.sports_flooring.rate = 185;
      }

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }

      showNotificationToast('Configured 100% Indoor-Only Badminton Arena (88×44 FT, Fully Clad with PPGL Sheets & Roof Truss)!', 'success');
    }

    function wrapAllSidesGiSheets() {
      cadState.claddings.roof  = 'ppgl_sheet';
      cadState.claddings.front = 'ppgl_sheet';
      cadState.claddings.back  = 'ppgl_sheet';
      cadState.claddings.left  = 'ppgl_sheet';
      cadState.claddings.right = 'ppgl_sheet';

      if (cadState.components.wall_enclosures) {
        cadState.components.wall_enclosures.included = true;
        cadState.components.wall_enclosures.name = '0.50mm Pre-Painted Galvalume (PPGL) Profile Wall Cladding (AZ-150 / 550 MPa)';
        cadState.components.wall_enclosures.brand = 'AMNS PPGL / Tata Bluescope';
        cadState.components.wall_enclosures.gauge = '0.50mm TCT (AZ-150 / 550 MPa)';
        cadState.components.wall_enclosures.rate = 125;
      }
      if (cadState.components.roof_canopy) {
        cadState.components.roof_canopy.included = true;
        cadState.components.roof_canopy.name = 'Curved Arch Tubular Steel Roof Truss & 0.50mm Galvalume Canopy';
        cadState.components.roof_canopy.brand = 'Tata Bluescope Colorbond AZ-150';
        cadState.components.roof_canopy.gauge = '0.50mm TCT (AZ-150 / 550 MPa)';
        cadState.components.roof_canopy.rate = 240;
      }

      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') {
        init3dStudioView('cad3dMount');
      }
      if (cadState.currentView === 'quad') {
        renderQuadView();
      }

      showNotificationToast('Wrapped all 4 perimeter sides and curved roof in 0.50mm Tata Bluescope / AMNS PPGL GI Sheets!', 'success');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. LIVE BOQ & ESTIMATION CALCULATOR
    // ──────────────────────────────────────────────────────────────────────────
    function recalcStudioBoq() {
      var L = parseFloat(cadState.length) || 100;
      var W = parseFloat(cadState.width) || 60;
      var H = parseFloat(cadState.height) || 25;
      var area = L * W;

      // Sum amounts from components
      var totalSubtotal = 0;
      var civilCost = 0, steelCost = 0, roofCost = 0, wallCost = 0, floorCost = 0, gateCost = 0, lightCost = 0;

      Object.keys(cadState.components).forEach(function(cId) {
        var c = cadState.components[cId];
        if (!c || !c.included) return;
        var amt = (c.qty || 1) * (c.rate || 0);
        totalSubtotal += amt;

        if (cId === 'civil_foundation') civilCost += amt;
        else if (cId === 'columns_structure') steelCost += amt;
        else if (cId === 'roof_canopy') roofCost += amt;
        else if (cId === 'wall_enclosures') wallCost += amt;
        else if (cId === 'sports_flooring' || cId === 'cricket_pitch' || cId === 'badminton_arena') floorCost += amt;
        else if (cId === 'access_gates' || cId === 'amenities_dugout') gateCost += amt;
        else if (cId === 'stadium_lighting') lightCost += amt;
      });

      var gst = Math.round(totalSubtotal * 0.18);
      var grand = Math.round(totalSubtotal + gst);

      // Update UI
      var areaTxt = Math.round(area).toLocaleString('en-IN') + ' SFT';
      document.getElementById('boqAreaBadge').textContent = areaTxt;
      var badgeTop = document.getElementById('boqAreaBadgeTop');
      if (badgeTop) badgeTop.textContent = areaTxt;

      document.getElementById('boqCivilCost').textContent = '₹' + Math.round(civilCost).toLocaleString('en-IN');
      document.getElementById('boqSteelCost').textContent = '₹' + Math.round(steelCost).toLocaleString('en-IN');
      document.getElementById('boqWallCost').textContent = '₹' + Math.round(wallCost).toLocaleString('en-IN');
      document.getElementById('boqRoofCost').textContent = '₹' + Math.round(roofCost).toLocaleString('en-IN');
      document.getElementById('boqTurfCost').textContent = '₹' + Math.round(floorCost).toLocaleString('en-IN');
      document.getElementById('boqGateCost').textContent = '₹' + Math.round(gateCost).toLocaleString('en-IN');
      document.getElementById('boqLightCost').textContent = '₹' + Math.round(lightCost).toLocaleString('en-IN');

      document.getElementById('boqSubtotal').textContent = '₹' + Math.round(totalSubtotal).toLocaleString('en-IN');
      document.getElementById('boqGstAmount').textContent = '₹' + gst.toLocaleString('en-IN');
      document.getElementById('boqGrandTotal').textContent = '₹' + grand.toLocaleString('en-IN');

      cadState.calculatedSubtotal = Math.round(totalSubtotal);
      cadState.calculatedGrand = grand;
    }

    function updateStudioHud() {
      var L = parseFloat(cadState.length) || 100;
      var W = parseFloat(cadState.width) || 60;
      var H = parseFloat(cadState.height) || 25;
      var sft = L * W;
      var sqm = Math.round(sft * 0.092903);

      var hud = document.getElementById('statCoords');
      if (hud) hud.innerHTML = 'X: ' + formatUnit(L) + ' &bull; Y: ' + formatUnit(W) + ' &bull; Z: ' + formatUnit(H);

      var areaHud = document.getElementById('statArea');
      if (areaHud) areaHud.textContent = Math.round(sft).toLocaleString('en-IN') + ' SFT (' + sqm + ' SQM)';
    }

    function formatUnit(val) {
      val = parseFloat(val) || 0;
      if (cadState.currentUnit === 'FT_IN') {
        var ft = Math.floor(val);
        var inch = Math.round((val - ft) * 12);
        return ft + "'-" + (inch < 10 ? '0' : '') + inch + '"';
      }
      if (cadState.currentUnit === 'M') return (val * 0.3048).toFixed(2) + ' m';
      if (cadState.currentUnit === 'MM') return Math.round(val * 304.8).toLocaleString('en-IN') + ' mm';
      return val.toFixed(2) + ' FT';
    }

    function setStudioUnit(u) {
      cadState.currentUnit = u;
      document.querySelectorAll('.unit-btn').forEach(function(b) {
        if (b.dataset.unit === u) b.classList.add('active', 'btn-primary');
        else b.classList.remove('active', 'btn-primary');
      });
      render2dDrawingBoard();
      updateStudioHud();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. VIEW SWITCHER
    // ──────────────────────────────────────────────────────────────────────────
    function switchStudioView(mode) {
      cadState.currentView = mode;
      var c2d = document.getElementById('cad2dCanvasContainer');
      var c3d = document.getElementById('cad3dCanvasContainer');
      var cQuad = document.getElementById('cadQuadStudioContainer');

      document.querySelectorAll('#tab2dBtn, #tab3dBtn, #tabSplitBtn, #tabQuadBtn').forEach(function(b) {
        b.classList.remove('btn-primary', 'active');
        b.classList.add('btn-outline-light');
      });

      if (mode === '2d') {
        c2d.style.display = 'block';
        c2d.style.width = '100%';
        c2d.style.borderRight = 'none';
        c3d.style.display = 'none';
        cQuad.style.display = 'none';

        document.getElementById('tab2dBtn').classList.add('btn-primary', 'active');
        document.getElementById('canvasModeLabel').textContent = 'GCM XTREME &bull; 2D CAD DRAWING BOARD: Click ANY element to modify its full specifications!';
        render2dDrawingBoard();
      } else if (mode === '3d') {
        c2d.style.display = 'none';
        c3d.style.display = 'block';
        c3d.style.width = '100%';
        cQuad.style.display = 'none';

        document.getElementById('tab3dBtn').classList.add('btn-primary', 'active');
        document.getElementById('canvasModeLabel').textContent = '3D BIM MODEL: Click ANY component in 3D to modify its full specifications!';
        init3dStudioView('cad3dMount');
      } else if (mode === 'split') {
        c2d.style.display = 'block';
        c2d.style.width = '50%';
        c2d.style.borderRight = '2px solid #334155';

        c3d.style.display = 'block';
        c3d.style.width = '50%';
        cQuad.style.display = 'none';

        document.getElementById('tabSplitBtn').classList.add('btn-primary', 'active');
        document.getElementById('canvasModeLabel').textContent = '2D + 3D SPLIT SCREEN: Live synchronization between drafting and 3D model';
        render2dDrawingBoard();
        init3dStudioView('cad3dMount');
      } else if (mode === 'quad') {
        c2d.style.display = 'none';
        c3d.style.display = 'none';
        cQuad.style.display = 'grid';

        document.getElementById('tabQuadBtn').classList.add('btn-primary', 'active');
        document.getElementById('canvasModeLabel').textContent = 'REVIT QUAD VIEW (WT): 3D Model + Floor Plan + Side Elevation + Cross Section';
        renderQuadView();
      }
    }

    function getViewerConfig() {
      var roofComp = cadState.components.roof_canopy;
      var isRoofInc = roofComp ? (roofComp.included !== false) : true;
      var wallComp = cadState.components.wall_enclosures;
      var isWallInc = wallComp ? (wallComp.included !== false) : true;
      var pitchComp = cadState.components.cricket_pitch;
      var isPitchInc = pitchComp ? (pitchComp.included !== false) : true;
      var floorComp = cadState.components.sports_flooring;
      var isFloorInc = floorComp ? (floorComp.included !== false) : true;
      var badmComp = cadState.components.badminton_arena;
      var isBadmInc = badmComp ? (badmComp.included !== false) : true;
      var gateComp = cadState.components.access_gates;
      var isGateInc = gateComp ? (gateComp.included !== false) : true;

      var isOpenRoof = (cadState.claddings.roof === 'open') || !isRoofInc;
      var isTruss = isRoofInc && (cadState.claddings.roof === 'ppgl_sheet' || cadState.claddings.roof === 'curved_truss');
      var roofType = isOpenRoof ? 'open_top' : (isTruss ? 'curved_truss' : 'light_cable_top');

      var allSidesPpgl = isWallInc && (cadState.claddings.front === 'ppgl_sheet' &&
                          cadState.claddings.back === 'ppgl_sheet' &&
                          cadState.claddings.left === 'ppgl_sheet' &&
                          cadState.claddings.right === 'ppgl_sheet');

      var openSides = {
        front: (!isWallInc || cadState.claddings.front === 'open'),
        back: (!isWallInc || cadState.claddings.back === 'open'),
        left: (!isWallInc || cadState.claddings.left === 'open'),
        right: (!isWallInc || cadState.claddings.right === 'open'),
        top: isOpenRoof
      };

      var effectivePitchL = isPitchInc ? (parseFloat(cadState.pitch_length) || 66) : 0;
      var effectiveSport = (isBadmInc && !isPitchInc && String(cadState.sport_type || '').indexOf('badminton') !== -1) ? 'badminton' : cadState.sport_type;

      return {
        length: cadState.length,
        width: cadState.width,
        height: cadState.height,
        sport_type: effectiveSport,
        roof_type: roofType,
        package: cadState.package,
        pitch_length: effectivePitchL,
        turf_type: (!isFloorInc ? 'none' : (cadState.claddings.floor === 'vinyl_mat' ? 'vinyl_mat_badminton' : 'sports_50mm_12000_dtex')),
        gates: isGateInc ? cadState.gates : [],
        has_all_sides_gi_sheets: allSidesPpgl,
        open_sides: openSides,
        claddings: cadState.claddings,
        zones: (isBadmInc && cadState.zones && cadState.zones.badminton) ? cadState.zones : null,
        custom_specs: cadState.components
      };
    }

    function init3dStudioView(mountId) {
      if (!window.TurnkeyViewer || !window.TurnkeyViewer.init3D) return;
      var mount = document.getElementById(mountId);
      if (!mount) return;
      var viewer = window.TurnkeyViewer.init3D(mountId, getViewerConfig());
      window.cad3DViewer = viewer;
      window.tkViewerInstance = viewer;

      // Attach 3D direct click listener
      attach3dRaycasterListener(mount, viewer);
    }

    function attach3dRaycasterListener(container, viewer) {
      if (!container || !viewer || !window.THREE) return;

      var pStartX = 0, pStartY = 0;
      container.addEventListener('pointerdown', function(e) {
        pStartX = e.clientX;
        pStartY = e.clientY;
      });

      container.addEventListener('click', function(e) {
        if (Math.hypot(e.clientX - pStartX, e.clientY - pStartY) > 8) return; // Orbit drag
        var rect = container.getBoundingClientRect();
        var mouse = new THREE.Vector2();
        mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

        var raycaster = new THREE.Raycaster();
        raycaster.setFromCamera(mouse, viewer.camera);
        var intersects = raycaster.intersectObjects(viewer.scene.children, true);
        if (!intersects || !intersects.length) return;

        for (var i = 0; i < intersects.length; i++) {
          var obj = intersects[i].object;
          if (!obj || !obj.visible) continue;
          if (obj.type === 'AmbientLight' || obj.type === 'DirectionalLight' || obj.type === 'GridHelper') continue;

          var pt = intersects[i].point;
          var H_units = (cadState.height || 25) * 0.1;

          // Check roof
          if (pt.y >= (H_units - 0.35)) {
            openComponentSpecEditor('roof_canopy');
            return;
          }
          // Check ground / turf / pitch
          if (pt.y <= 0.08) {
            if (Math.abs(pt.z) <= 0.45 && Math.abs(pt.x) <= 3.3) {
              openComponentSpecEditor('cricket_pitch');
            } else if (cadState.zones && cadState.zones.badminton) {
              openComponentSpecEditor('badminton_arena');
            } else {
              openComponentSpecEditor('sports_flooring');
            }
            return;
          }
          // Check walls
          if (obj.geometry && (obj.geometry.type === 'PlaneGeometry' || obj.geometry.type === 'BoxGeometry')) {
            openComponentSpecEditor('wall_enclosures');
            return;
          }
          // Default columns / frame
          openComponentSpecEditor('columns_structure');
          return;
        }
      });
    }

    function renderQuadView() {
      var cfg = getViewerConfig();
      if (!window.TurnkeyViewer) return;

      // Quad 1: 3D
      var q3d = document.getElementById('quad3dMount');
      if (q3d) {
        q3d.innerHTML = '<div style="position:absolute; top:6px; left:6px; z-index:10; background:rgba(0,0,0,0.7); padding:2px 8px; border-radius:4px; font-size:10px; color:#38bdf8; font-weight:bold;"><i class="fa fa-cube me-1"></i>1. 3D ISOMETRIC BIM VIEW (Click to Edit)</div><div id="quad3dSubMount" style="width:100%; height:100%;"></div>';
        var viewer = window.TurnkeyViewer.init3D('quad3dSubMount', cfg);
        attach3dRaycasterListener(document.getElementById('quad3dSubMount'), viewer);
      }

      // Quad 2: Plan
      var qPlan = document.getElementById('quadPlanMount');
      if (qPlan) {
        qPlan.innerHTML = '<div style="position:absolute; top:6px; left:6px; z-index:10; background:rgba(0,0,0,0.7); padding:2px 8px; border-radius:4px; font-size:10px; color:#22c55e; font-weight:bold;"><i class="fa fa-draw-polygon me-1"></i>2. ARCHITECTURAL FLOOR PLAN (Click to Edit)</div><div id="quadPlanSubMount" style="width:100%; height:100%; overflow:hidden;"></div>';
        if (window.TurnkeyViewer.renderFloorPlan) {
          window.TurnkeyViewer.renderFloorPlan('quadPlanSubMount', cfg);
        }
      }

      // Quad 3: Side Elevation with interactive elevation component action badges
      var qSide = document.getElementById('quadSideMount');
      if (qSide) {
        qSide.innerHTML = 
          '<div style="position:absolute; top:6px; left:6px; right:6px; z-index:10; display:flex; justify-content:space-between; align-items:center; background:rgba(15,23,42,0.85); padding:3px 8px; border-radius:4px; font-size:10px; border:1px solid #334155;">' +
            '<span style="color:#f59e0b; font-weight:bold;"><i class="fa fa-arrows-left-right me-1"></i>3. SIDE ELEVATION</span>' +
            '<div class="d-flex gap-1">' +
              '<button type="button" class="btn btn-xs btn-outline-warning py-0 px-1" onclick="openComponentSpecEditor(\'roof_canopy\')">Edit Roof</button>' +
              '<button type="button" class="btn btn-xs btn-outline-info py-0 px-1" onclick="openComponentSpecEditor(\'columns_structure\')">Edit Columns</button>' +
              '<button type="button" class="btn btn-xs btn-outline-success py-0 px-1" onclick="openComponentSpecEditor(\'wall_enclosures\')">Edit Sheets/Nets</button>' +
              '<button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="openComponentSpecEditor(\'civil_foundation\')">Edit Footings</button>' +
            '</div>' +
          '</div>' +
          '<div id="quadSideSubMount" style="width:100%; height:100%; overflow:hidden;"></div>';
        if (window.TurnkeyViewer.renderSideElevation) {
          window.TurnkeyViewer.renderSideElevation('quadSideSubMount', cfg);
        }
      }

      // Quad 4: Cross Elevation with interactive elevation component action badges
      var qCross = document.getElementById('quadCrossMount');
      if (qCross) {
        qCross.innerHTML = 
          '<div style="position:absolute; top:6px; left:6px; right:6px; z-index:10; display:flex; justify-content:space-between; align-items:center; background:rgba(15,23,42,0.85); padding:3px 8px; border-radius:4px; font-size:10px; border:1px solid #334155;">' +
            '<span style="color:#ec4899; font-weight:bold;"><i class="fa fa-up-down-left-right me-1"></i>4. CROSS ELEVATION</span>' +
            '<div class="d-flex gap-1">' +
              '<button type="button" class="btn btn-xs btn-outline-warning py-0 px-1" onclick="openComponentSpecEditor(\'roof_canopy\')">Edit Curved Arch</button>' +
              '<button type="button" class="btn btn-xs btn-outline-info py-0 px-1" onclick="openComponentSpecEditor(\'columns_structure\')">Edit Tie Beams</button>' +
              '<button type="button" class="btn btn-xs btn-outline-success py-0 px-1" onclick="openComponentSpecEditor(\'sports_flooring\')">Edit Turf &amp; Pitch</button>' +
            '</div>' +
          '</div>' +
          '<div id="quadCrossSubMount" style="width:100%; height:100%; overflow:hidden;"></div>';
        if (window.TurnkeyViewer.renderCrossElevation) {
          window.TurnkeyViewer.renderCrossElevation('quadCrossSubMount', cfg);
        }
      }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. SAVE & EXPORT
    // ──────────────────────────────────────────────────────────────────────────
    function saveStudioProject() {
      var btn = document.getElementById('btnSaveStudio');
      btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Saving...';
      btn.disabled = true;

      fetch('cad_designer.php?action=save_cad', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: cadState.id,
          length: cadState.length,
          width: cadState.width,
          height: cadState.height,
          sport_type: cadState.sport_type,
          package: cadState.package,
          pitch_length: cadState.pitch_length,
          gates: cadState.gates,
          claddings: cadState.claddings,
          zones: cadState.zones,
          components: cadState.components,
          subtotal: cadState.calculatedSubtotal,
          is_gst: true
        })
      })
      .then(function(r) {
        return r.text().then(function(text) {
          try {
            return JSON.parse(text);
          } catch(e) {
            console.error('Non-JSON response from server:', text);
            if (text.indexOf('login.php') !== -1 || text.indexOf('Admin Login') !== -1 || r.status === 401 || r.status === 302) {
              throw new Error('SESSION_TIMEOUT');
            }
            throw new Error('Unexpected server response format');
          }
        });
      })
      .then(function(res) {
        if (res.ok) {
          if (window.GcmNotify) {
            GcmNotify.success('Estimation ' + res.id + ' Saved', 'All custom architectural specifications, 3D model, and BOQ items recorded successfully!', 4000);
          }
          setTimeout(function() {
            window.location.href = res.redirect;
          }, 800);
        } else {
          if (window.GcmNotify) {
            GcmNotify.error('Save Notice', res.msg || 'Unable to save estimation.');
          }
          btn.innerHTML = '<i class="fa fa-floppy-disk me-1"></i>Save &amp; Generate Estimation';
          btn.disabled = false;
        }
      })
      .catch(function(err) {
        console.error('Save error details:', err);
        if (err.message === 'SESSION_TIMEOUT' && window.GcmNotify) {
          GcmNotify.modal({
            type: 'warning',
            title: 'Session Timeout',
            message: "Your administrator session has expired, but your CAD design is safely preserved in memory!\n\nPlease click below to re-login in a separate tab, then click Save again.",
            confirmText: 'Open Login Tab (Preserve Design)',
            onConfirm: function() {
              window.open('login.php', '_blank');
              btn.innerHTML = '<i class="fa fa-floppy-disk me-1"></i>Retry Save Estimation';
              btn.disabled = false;
            }
          });
        } else {
          if (window.GcmNotify) {
            GcmNotify.error('Save Error', 'Connection or server error while saving: ' + (err.message || 'Please check your connection and retry.'));
          }
          btn.innerHTML = '<i class="fa fa-floppy-disk me-1"></i>Save &amp; Generate Estimation';
          btn.disabled = false;
        }
      });
    }

    
    // ═════════════════════════════════════════════════════════════════════════
    // GCM XTREME DRAGGABLE GATES & INTERACTIVE SYSTEM
    // ═════════════════════════════════════════════════════════════════════════
    function renderInteractiveGates(parent, startX, startY, courtPixW, courtPixH, scale) {
      if (!cadState.gates || !Array.isArray(cadState.gates) || !cadState.gates.length) {
        cadState.gates = [{ id: 'gate_1', wall: 'front', position_pct: 45, width_ft: 6, height_ft: 7 }];
      }

      cadState.gates.forEach(function(g, gi) {
        var wall = g.wall || 'front';
        var pct = (typeof g.position_pct === 'number') ? g.position_pct : 45;
        var wFt = g.width_ft || 6;
        var hFt = g.height_ft || 7;
        var gatePxW = wFt * scale;

        var gx = 0, gy = 0, rot = 0;
        var labelX = 0, labelY = 0;

        if (wall === 'front') {
          gx = startX + (courtPixW * pct / 100);
          gy = startY + courtPixH;
          labelX = gx;
          labelY = gy + 22;
        } else if (wall === 'back') {
          gx = startX + (courtPixW * pct / 100);
          gy = startY;
          labelX = gx;
          labelY = gy - 16;
        } else if (wall === 'left') {
          gx = startX;
          gy = startY + (courtPixH * pct / 100);
          rot = 90;
          labelX = gx - 36;
          labelY = gy + 4;
        } else if (wall === 'right') {
          gx = startX + courtPixW;
          gy = startY + (courtPixH * pct / 100);
          rot = 90;
          labelX = gx + 36;
          labelY = gy + 4;
        }

        var gateGroup = createSvgElem('g', { class: 'cad-gate-item', 'data-gate-idx': gi });

        // Gate frame rect
        var gateRect = createSvgElem('rect', {
          x: (rot === 90 ? gx - 6 : gx - gatePxW / 2),
          y: (rot === 90 ? gy - gatePxW / 2 : gy - 6),
          width: (rot === 90 ? 12 : gatePxW),
          height: (rot === 90 ? gatePxW : 12),
          fill: '#ef4444',
          stroke: '#ffffff',
          'stroke-width': 1.8,
          rx: 3,
          cursor: 'pointer'
        });
        gateRect.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('access_gates'); };
        var title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        title.textContent = 'Gate ' + (gi + 1) + ' (' + wFt + '×' + hFt + ' FT) on ' + wall.toUpperCase() + ' Wall at ' + Math.round(pct) + '% - Click to Edit Specs';
        gateRect.appendChild(title);
        gateGroup.appendChild(gateRect);

        // Circular Drag Handle
        var grip = createSvgElem('circle', {
          cx: gx,
          cy: gy,
          r: 9,
          fill: '#ffffff',
          stroke: '#ef4444',
          'stroke-width': 3,
          cursor: (rot === 90 ? 'ns-resize' : 'ew-resize')
        });
        var gripTitle = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        gripTitle.textContent = 'DRAG ME to slide Gate ' + (gi + 1) + ' anywhere along this wall or onto other walls!';
        grip.appendChild(gripTitle);

        grip.addEventListener('mousedown', function(e) {
          startGateDrag(gi, e);
        });
        gateGroup.appendChild(grip);

        // Label
        var lbl = createSvgElem('text', {
          x: labelX,
          y: labelY,
          'text-anchor': 'middle',
          fill: '#ef4444',
          'font-size': '10px',
          'font-weight': 'bold',
          'font-family': 'Inter',
          cursor: 'pointer'
        });
        lbl.textContent = 'GATE ' + (gi + 1) + ' [' + wFt + '×' + hFt + "'] @" + Math.round(pct) + '%';
        lbl.onclick = function(e) { e.stopPropagation(); openComponentSpecEditor('access_gates'); };
        gateGroup.appendChild(lbl);

        parent.appendChild(gateGroup);
      });
    }

    var activeGateDragIdx = null;

    function startGateDrag(gi, e) {
      e.stopPropagation();
      e.preventDefault();
      activeGateDragIdx = gi;
      window.addEventListener('mousemove', onGateDragging);
      window.addEventListener('mouseup', stopGateDragging);
    }

    function onGateDragging(e) {
      if (activeGateDragIdx === null) return;
      var svg = document.getElementById('cadSvg');
      if (!svg) return;
      var pt = svg.createSVGPoint();
      pt.x = e.clientX;
      pt.y = e.clientY;
      var svgP = pt.matrixTransform(svg.getScreenCTM().inverse());

      var L = parseFloat(cadState.length) || 100;
      var W = parseFloat(cadState.width) || 60;
      var scale = Math.min(1000 / L, 550 / W, 6.5);
      var courtPixW = L * scale;
      var courtPixH = W * scale;
      var startX = 700 - courtPixW / 2;
      var startY = 425 - courtPixH / 2;

      var g = cadState.gates[activeGateDragIdx];
      if (!g) return;

      var distFront = Math.abs(svgP.y - (startY + courtPixH));
      var distBack  = Math.abs(svgP.y - startY);
      var distLeft  = Math.abs(svgP.x - startX);
      var distRight = Math.abs(svgP.x - (startX + courtPixW));

      var minDist = Math.min(distFront, distBack, distLeft, distRight);
      if (minDist < 80) {
        if (minDist === distFront) g.wall = 'front';
        else if (minDist === distBack) g.wall = 'back';
        else if (minDist === distLeft) g.wall = 'left';
        else g.wall = 'right';
      }

      var pct = 50;
      if (g.wall === 'front' || g.wall === 'back') {
        pct = ((svgP.x - startX) / courtPixW) * 100;
      } else {
        pct = ((svgP.y - startY) / courtPixH) * 100;
      }

      g.position_pct = Math.max(5, Math.min(95, Math.round(pct)));
      render2dDrawingBoard();
      updateStudioHud();
    }

    function stopGateDragging() {
      if (activeGateDragIdx !== null && cadState.gates && cadState.gates[activeGateDragIdx]) {
        var g = cadState.gates[activeGateDragIdx];
        var wallName = (g.wall || 'front').toUpperCase();
        var L = parseFloat(cadState.length) || 100;
        var W = parseFloat(cadState.width) || 60;
        var wallLen = (g.wall === 'front' || g.wall === 'back') ? L : W;
        var posFeet = Math.round(wallLen * g.position_pct / 100);

        if (window.GcmNotify) {
          GcmNotify.success('Gate Position Updated', 'Gate ' + (activeGateDragIdx + 1) + ' placed at ' + posFeet + ' FT on ' + wallName + ' Wall (' + g.position_pct + '%)');
        }
        recalcStudioBoq();
        if (cadState.currentView === '3d' || cadState.currentView === 'split') {
          init3dStudioView('cad3dMount');
        }
        if (cadState.currentView === 'quad') {
          renderQuadView();
        }
      }
      activeGateDragIdx = null;
      window.removeEventListener('mousemove', onGateDragging);
      window.removeEventListener('mouseup', stopGateDragging);
    }

    function addStudioGate() {
      if (!cadState.gates) cadState.gates = [];
      var newIdx = cadState.gates.length + 1;
      cadState.gates.push({
        id: 'gate_' + newIdx,
        wall: (newIdx % 2 === 0 ? 'back' : 'front'),
        position_pct: (newIdx * 25) % 80 + 10,
        width_ft: 6,
        height_ft: 7
      });
      render2dDrawingBoard();
      recalcStudioBoq();
      if (window.GcmNotify) {
        GcmNotify.success('Gate Added', 'Added Gate ' + newIdx + ' (6×7 FT). Drag handle on 2D drawing board to adjust position!');
      }
    }

    function removeStudioGate(idx) {
      if (!cadState.gates || cadState.gates.length <= 1) {
        if (window.GcmNotify) {
          GcmNotify.warning('Access Gate', 'Facility must have at least one access gate.');
        }
        return;
      }
      cadState.gates.splice(idx, 1);
      render2dDrawingBoard();
      recalcStudioBoq();
      if (window.GcmNotify) {
        GcmNotify.info('Gate Removed', 'Removed access gate.');
      }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // GCM XTREME SPORTS COMBINATIONS & ATTACHMENT SUITE
    // ═════════════════════════════════════════════════════════════════════════
    function presetFootballTurf(size) {
      var is7 = (size === '7_side');
      cadState.length = is7 ? 140 : 100;
      cadState.width  = is7 ? 80 : 60;
      cadState.height = 28;
      cadState.sport_type = 'football';
      cadState.pitch_length = 0;
      cadState.claddings = { roof: 'netting', front: 'netting', back: 'netting', left: 'netting', right: 'netting', floor: 'sports_turf' };
      cadState.zones = null;

      if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.included = false;
      if (cadState.components.badminton_arena) cadState.components.badminton_arena.included = false;

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') init3dStudioView('cad3dMount');
      if (cadState.currentView === 'quad') renderQuadView();

      if (window.GcmNotify) {
        GcmNotify.ai('GCM XTREME Turf Arena', 'Configured ' + (is7 ? '7-A-Side (140×80 FT)' : '5-A-Side (100×60 FT)') + ' Football Turf with FIFA-quality grass and goal posts!');
      }
    }

    function presetPickleballArena(courts) {
      courts = courts || 2;
      cadState.length = 60;
      cadState.width  = 44;
      cadState.height = 28;
      cadState.sport_type = 'pickleball';
      cadState.pitch_length = 0;
      cadState.claddings = { roof: 'ppgl_sheet', front: 'ppgl_sheet', back: 'ppgl_sheet', left: 'ppgl_sheet', right: 'ppgl_sheet', floor: 'synthetic_acrylic' };
      cadState.zones = null;

      if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.included = false;
      if (cadState.components.badminton_arena) cadState.components.badminton_arena.included = false;

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') init3dStudioView('cad3dMount');
      if (cadState.currentView === 'quad') renderQuadView();

      if (window.GcmNotify) {
        GcmNotify.ai('GCM XTREME Pickleball Arena', 'Configured Pro Pickleball Arena (2 Courts, 60×44 FT) with multi-layer acrylic cushion flooring!');
      }
    }

    function presetDualGame(combo) {
      if (combo === 'badminton_basketball') {
        cadState.length = 60;
        cadState.width  = 40;
        cadState.height = 30;
        cadState.sport_type = 'dual_badminton_basketball';
        cadState.pitch_length = 0;
        cadState.claddings = { roof: 'ppgl_sheet', front: 'ppgl_sheet', back: 'ppgl_sheet', left: 'ppgl_sheet', right: 'ppgl_sheet', floor: 'vinyl_mat' };
        cadState.zones = null;

        if (cadState.components.badminton_arena) cadState.components.badminton_arena.included = true;
        if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.included = false;

        if (window.GcmNotify) {
          GcmNotify.ai('GCM XTREME 2-in-1 Arena', 'Configured Badminton + Basketball Dual-Game Court! White BWF lines + Orange FIBA basketball markings with regulation hoops.');
        }
      } else if (combo === 'football_basketball') {
        cadState.length = 100;
        cadState.width  = 60;
        cadState.height = 28;
        cadState.sport_type = 'dual_football_basketball';
        cadState.pitch_length = 0;
        cadState.claddings = { roof: 'netting', front: 'netting', back: 'netting', left: 'netting', right: 'netting', floor: 'sports_turf' };
        cadState.zones = null;

        if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.included = false;

        if (window.GcmNotify) {
          GcmNotify.ai('GCM XTREME 2-in-1 Arena', 'Configured 5-A-Side Football Turf + Basketball Dual-Game Arena with integrated goal posts and basketball hoops!');
        }
      } else if (combo === 'badminton_pickleball') {
        cadState.length = 60;
        cadState.width  = 44;
        cadState.height = 28;
        cadState.sport_type = 'dual_badminton_pickleball';
        cadState.pitch_length = 0;
        cadState.claddings = { roof: 'ppgl_sheet', front: 'ppgl_sheet', back: 'ppgl_sheet', left: 'ppgl_sheet', right: 'ppgl_sheet', floor: 'vinyl_mat' };
        cadState.zones = null;

        if (cadState.components.badminton_arena) cadState.components.badminton_arena.included = true;
        if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.included = false;

        if (window.GcmNotify) {
          GcmNotify.ai('GCM XTREME Dual Racket Court', 'Configured Badminton + Pickleball Dual Court with BWF white lines & USAPA yellow non-volley kitchen markings!');
        }
      } else if (combo === 'cricket_pickleball') {
        cadState.length = 120;
        cadState.width  = 60;
        cadState.height = 25;
        cadState.sport_type = 'dual_cricket_pickleball';
        cadState.pitch_length = 66;
        cadState.claddings = { roof: 'netting', front: 'netting', back: 'netting', left: 'netting', right: 'netting', floor: 'sports_turf' };
        cadState.zones = {
          cricket: { length: 100, width: 60, floor: 'sports_turf', name: 'Box Cricket Ground' },
          pickleball: { length: 20, width: 44, floor: 'synthetic_acrylic', name: 'Dedicated Pickleball Half' }
        };

        if (cadState.components.cricket_pitch) cadState.components.cricket_pitch.included = true;

        if (window.GcmNotify) {
          GcmNotify.ai('GCM XTREME Hybrid Arena', 'Configured Box Cricket Ground + Pickleball Outfield Court with synchronized dual turf & acrylic specifications!');
        }
      }

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') init3dStudioView('cad3dMount');
      if (cadState.currentView === 'quad') renderQuadView();
    }

    function attachSportCourt(sport, gapFt) {
      gapFt = parseFloat(gapFt) || 0;
      var curL = parseFloat(cadState.length) || 100;
      var W = parseFloat(cadState.width) || 60;

      var addL = 60;
      var sportName = 'Indoor Badminton Arena';
      var floorType = 'vinyl_mat';

      if (sport === 'badminton') {
        addL = 60;
        sportName = 'Indoor Badminton Arena (2 BWF Courts)';
        floorType = 'vinyl_mat';
        if (cadState.components.badminton_arena) cadState.components.badminton_arena.included = true;
      } else if (sport === 'pickleball') {
        addL = 50;
        sportName = 'Pro Pickleball Arena (2 Courts)';
        floorType = 'synthetic_acrylic';
      } else if (sport === 'football') {
        addL = 80;
        sportName = '5-A-Side Football Turf';
        floorType = 'sports_turf';
      } else if (sport === 'basketball') {
        addL = 50;
        sportName = 'FIBA Basketball Court';
        floorType = 'synthetic_acrylic';
      }

      var newTotalL = curL + addL + gapFt;
      cadState.length = newTotalL;
      cadState.sport_type = 'multisport_arena_complex_185x60';
      cadState.height = Math.max(30, cadState.height);

      if (!cadState.zones) {
        cadState.zones = {
          primary: { length: curL, width: W, floor: cadState.claddings.floor || 'sports_turf', name: 'Primary Ground' }
        };
      }

      var zKey = sport + '_' + Date.now();
      cadState.zones[zKey] = {
        length: addL,
        width: W,
        gap_distance: gapFt,
        floor: floorType,
        name: sportName
      };

      updateComponentQuantities();
      syncInputValues();
      renderBimComponentsList();
      render2dDrawingBoard();
      recalcStudioBoq();
      updateStudioHud();
      if (cadState.currentView === '3d' || cadState.currentView === 'split') init3dStudioView('cad3dMount');
      if (cadState.currentView === 'quad') renderQuadView();

      var gapText = (gapFt === 0) ? 'Directly Attached' : (gapFt === 3.28 ? '1 Meter Walkway Clearance' : gapFt + ' FT Gap Clearance');
      if (window.GcmNotify) {
        GcmNotify.ai('GCM XTREME Campus', 'Attached ' + sportName + ' (' + addL + ' FT) with ' + gapText + '! Total facility expanded to ' + newTotalL + ' FT.');
      }
    }

    function exportStudioDxf() {
      window.turnkeyViewConfig = getViewerConfig();
      if (window.GcmCadRevit && window.GcmCadRevit.exportAutoCADDXF) {
        window.GcmCadRevit.exportAutoCADDXF();
      } else {
        GcmNotify.info('AutoCAD Engine', 'Compiling and downloading AutoCAD .DXF file...');
      }
    }

    function switchLeftTab(tab) {
      var pMat = document.getElementById('panelMaterials');
      var pCrt = document.getElementById('panelCourts');
      var bMat = document.getElementById('btnTabMaterials');
      var bCrt = document.getElementById('btnTabCourts');

      if (tab === 'materials') {
        pMat.style.display = 'block';
        pCrt.style.display = 'none';
        bMat.className = 'btn btn-xs btn-primary flex-fill fw-bold py-1';
        bCrt.className = 'btn btn-xs btn-outline-secondary text-light flex-fill fw-bold py-1';
      } else {
        pMat.style.display = 'none';
        pCrt.style.display = 'block';
        bCrt.className = 'btn btn-xs btn-primary flex-fill fw-bold py-1';
        bMat.className = 'btn btn-xs btn-outline-secondary text-light flex-fill fw-bold py-1';
      }
    }

    function switchRightTab(tab) {
      var pSpecs = document.getElementById('panelBimSpecs');
      var pFacade = document.getElementById('panelFacadeCustomizer');
      var pBoq = document.getElementById('panelLiveBoq');

      var bSpecs = document.getElementById('btnTabBimSpecs');
      var bFacade = document.getElementById('btnTabFacade');
      var bBoq = document.getElementById('btnTabBoq');

      [pSpecs, pFacade, pBoq].forEach(function(el) { if (el) el.style.display = 'none'; });
      [bSpecs, bFacade, bBoq].forEach(function(b) {
        b.className = 'btn btn-xs btn-outline-secondary text-light flex-fill fw-bold py-1';
      });

      if (tab === 'specs') {
        if (pSpecs) pSpecs.style.display = 'block';
        bSpecs.className = 'btn btn-xs btn-primary flex-fill fw-bold py-1';
      } else if (tab === 'facade') {
        if (pFacade) pFacade.style.display = 'block';
        bFacade.className = 'btn btn-xs btn-primary flex-fill fw-bold py-1';
      } else if (tab === 'boq') {
        if (pBoq) pBoq.style.display = 'block';
        bBoq.className = 'btn btn-xs btn-primary flex-fill fw-bold py-1';
      }
    }

    function filterMaterials(q) {
      q = (q || '').toLowerCase();
      document.querySelectorAll('.mat-card').forEach(function(card) {
        var txt = card.textContent.toLowerCase();
        card.style.display = (txt.indexOf(q) !== -1) ? 'block' : 'none';
      });
    }

    function filterCategory(cat, btn) {
      document.querySelectorAll('.filter-chip').forEach(function(c) {
        c.classList.remove('active', 'bg-primary');
        c.classList.add('bg-dark');
      });
      if (btn) {
        btn.classList.add('active', 'bg-primary');
        btn.classList.remove('bg-dark');
      }

      document.querySelectorAll('.mat-card').forEach(function(card) {
        if (cat === 'all') {
          card.style.display = 'block';
        } else {
          var c = card.dataset.cat || '';
          card.style.display = (c.indexOf(cat) !== -1) ? 'block' : 'none';
        }
      });
    }

    function showHowToWorkModal() {
      if (window.GcmNotify) {
        GcmNotify.modal({
          type: 'ai',
          title: 'GCM XTREME Studio Workflow Guide',
          message: "1. 2D Drafting Grid: Drag length/width handles or gate handles freely along any wall!\n2. Multi-Sport & 2-in-1: Use 'Sports & Zones' to add Badminton, Basketball, Football, or Pickleball (Attached, 2 FT Gap, or 1M Walkway).\n3. Click ANY Element in 3D, 2D Plan, or Elevations to edit its full specifications & rates!\n4. Save & Generate Estimation creates formal quotes and synced BOQ with one click.",
          confirmText: "Got It, Let's Design!"
        });
      } else {
        var modalEl = document.getElementById('howToWorkModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          modal.show();
        }
      }
    }
  </script>
</body>
</html>