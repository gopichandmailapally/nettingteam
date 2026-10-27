<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
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

requireLogin();
requireAccess('documents','edit');
require_once __DIR__ . '/includes/turnkey_calculator.php';

$settings  = getSettings();
$customers = getAllCustomers();
$defaults  = $settings['defaults'] ?? [];

// Editing existing document?
$editId  = $_GET['edit'] ?? $_GET['id'] ?? null;
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
        $postH = (float)($_POST['tk_height'] ?? 25);
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
        $postSideNet = $_POST['tk_side_net'] ?? 'braided_2_5mm_45mm';
        $postTopNet = $_POST['tk_top_net'] ?? 'twisted_1_5mm_35mm';
        $postPitch = (float)($_POST['tk_pitch_length'] ?? 66);
        $postGateCount = (int)($_POST['tk_gate_count'] ?? 1);
        $postCustom = !empty($_POST['tk_custom_rate']) ? (float)$_POST['tk_custom_rate'] : null;

        $excludedJson = $_POST['turnkey_excluded_json'] ?? '';
        $excludedItems = !empty($excludedJson) ? (is_array($excludedJson) ? $excludedJson : json_decode($excludedJson, true)) : [];

        require_once __DIR__ . '/includes/turnkey_calculator.php';
        $postedCfg = !empty($_POST['turnkey_config']) ? (is_array($_POST['turnkey_config']) ? $_POST['turnkey_config'] : json_decode($_POST['turnkey_config'], true)) : [];

        // 1. Resolve open sides safely from POST or serialized turnkey_config
        $isTrainingNet = (strpos($postSport, 'training_net') !== false || $postSport === 'cricket_training_net');
        if (!empty($_POST['tk_open_sides'])) {
            $postOpenSides = is_array($_POST['tk_open_sides']) ? $_POST['tk_open_sides'] : json_decode($_POST['tk_open_sides'], true);
        } elseif (!empty($postedCfg['open_sides'])) {
            $postOpenSides = $postedCfg['open_sides'];
        } else {
            $postOpenSides = [
                'front' => $isTrainingNet,
                'left'  => false,
                'right' => false,
                'back'  => false,
                'top'   => ($postRoof === 'open_top'),
            ];
        }

        // Sanitize: If by legacy bug all 5 sides were true, restore correct training net / arena boundary
        if (!empty($postOpenSides['front']) && !empty($postOpenSides['left']) && !empty($postOpenSides['right']) && !empty($postOpenSides['back']) && !empty($postOpenSides['top'])) {
            $postOpenSides = [
                'front' => $isTrainingNet,
                'left'  => false,
                'right' => false,
                'back'  => false,
                'top'   => ($postRoof === 'open_top'),
            ];
        }

        // 2. Resolve layers safely from POST or serialized turnkey_config
        if (!empty($_POST['tk_layers'])) {
            $postLayers = is_array($_POST['tk_layers']) ? $_POST['tk_layers'] : json_decode($_POST['tk_layers'], true);
        } elseif (!empty($postedCfg['layers'])) {
            $postLayers = $postedCfg['layers'];
        } else {
            $postLayers = [
                'structure' => true,
                'netting'   => true,
                'civil'     => ($postCivilBed !== 'client_scope' && $postCivilBed !== 'none'),
                'turf'      => ($postTurfType !== 'no_turf'),
                'pitch'     => ($postPitch > 0),
                'lighting'  => true,
                'gates'     => ($postGateCount > 0),
            ];
        }

        // Resolve equipment suite safely from POST or serialized turnkey_config
        if (!empty($_POST['tk_equipment'])) {
            $postEquipment = is_array($_POST['tk_equipment']) ? $_POST['tk_equipment'] : json_decode($_POST['tk_equipment'], true);
        } elseif (!empty($postedCfg['equipment'])) {
            $postEquipment = $postedCfg['equipment'];
        } else {
            $postEquipment = [
                'cricket_pitch'  => ($postPitch > 0),
                'football_goals' => ($postSport === 'multisport_4in1_100x60'),
                'volleyball_net' => ($postSport === 'multisport_4in1_100x60'),
                'badminton_net'  => ($postSport === 'multisport_4in1_100x60'),
                'court_markings' => true,
            ];
        }

        if ($isTrainingNet || !empty($postOpenSides['front'])) {
            $postGateCount = 0; // Training net / open front already has large opening, zero gates required!
        }

        if (!empty($_POST['item_desc']) && count($_POST['item_desc']) > 0) {
            $allDescs = implode(' ', $_POST['item_desc']);
            $hasPostStructure = (bool)preg_match('/(column|pole|fabrication|ladder|truss|steel|pipe|framework|vertical upright|horizontal tie|purlin)/i', $allDescs);
            $hasPostNetting   = (bool)preg_match('/(net|netting|ball-stop|hdpe|braided|twisted|containment|mesh)/i', $allDescs);
            $hasPostLighting  = (bool)preg_match('/(light|floodlight|stadium light|sports led|lighting fixture|electrical|cabling|panel)/i', $allDescs);
            $hasPostGates     = (bool)preg_match('/(gate|player access door|access door)/i', $allDescs);
            $hasPostCivil     = (bool)preg_match('/(civil|base slab|concrete|pcc|rcc|wmm|sub-base|subbase|drainage|earth|asphalt|bitumen)/i', $allDescs);
            $hasPostTurf      = (bool)preg_match('/(turf|grass|flooring|matting|acrylic|carpet|outfield sports)/i', $allDescs);
            $hasPostPitch     = (bool)preg_match('/(pitch|wicket|batting pitch)/i', $allDescs);

            $postLayers['structure'] = $hasPostStructure && ($postLayers['structure'] ?? true);
            $postLayers['netting']   = $hasPostNetting && ($postLayers['netting'] ?? true);
            $postLayers['lighting']  = $hasPostLighting && ($postLayers['lighting'] ?? true);
            $postLayers['gates']     = $hasPostGates && ($postLayers['gates'] ?? true);
            $postLayers['civil']     = $hasPostCivil && ($postLayers['civil'] ?? true);
            $postLayers['turf']      = $hasPostTurf && ($postLayers['turf'] ?? true);
            $postLayers['pitch']     = $hasPostPitch && ($postLayers['pitch'] ?? true);

            // Sync equipment with active item descriptions if customized in BOQ table
            $postEquipment['cricket_pitch']  = (bool)preg_match('/(cricket pitch|pitch turf|15mm pitch|stumps)/i', $allDescs);
            $postEquipment['football_goals'] = (bool)preg_match('/(football goal|mini football|futsal goal)/i', $allDescs);
            $postEquipment['volleyball_net'] = (bool)preg_match('/(volleyball|court net system)/i', $allDescs);
            $postEquipment['badminton_net']  = (bool)preg_match('/(badminton.*post|badminton.*net|movable.*badminton)/i', $allDescs);
            $postEquipment['court_markings'] = (bool)preg_match('/(court marking|regulation line marking|4-in-1)/i', $allDescs);

            if (!$postLayers['gates']) {
                $postGateCount = 0;
            }
        }

        $serverBOQ = generateTurnkeyBOQ($postL, $postW, $postH, $postSport, $postRoof, $postPkg, $postPoleStyle, $postPipeSize, $postPipeGauge, $postTurfType, $postCustom, $postPitch, $postCivilBed, $postSideNet, $postTopNet, $postGateCount, $postOpenSides, $postLayers, $postEquipment);

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

        $postedGates = !empty($postedCfg['gates']) ? $postedCfg['gates'] : (!empty($_POST['turnkey_gates_json']) ? (is_array($_POST['turnkey_gates_json']) ? $_POST['turnkey_gates_json'] : json_decode($_POST['turnkey_gates_json'], true)) : []);
        if (is_array($postedGates) && count($postedGates) > 0) {
            $postGateCount = count($postedGates);
        }

        $_POST['turnkey_config'] = [
            'length'         => $postL,
            'width'          => $postW,
            'height'         => $postH,
            'pitch_length'   => $postPitch,
            'gate_count'     => $postGateCount,
            'gates'          => $postedGates,
            'sport_type'     => $postSport,
            'roof_type'      => $postRoof,
            'package'        => $postPkg,
            'pole_style'     => $postPoleStyle,
            'pipe_size'      => $postPipeSize,
            'pipe_gauge'     => $postPipeGauge,
            'turf_type'      => $postTurfType,
            'civil_bed_type' => $postCivilBed,
            'badminton_floor_type' => $_POST['mz_badminton_floor'] ?? ($postedCfg['badminton_floor_type'] ?? 'bwf_vinyl_4_5mm'),
            'badminton_bed_type'   => $_POST['mz_badminton_bed'] ?? ($postedCfg['badminton_bed_type'] ?? 'rcc_m20_4inch_laser'),
            'side_net'       => $postSideNet,
            'top_net'        => $postTopNet,
            'custom_rate'    => !empty($postCustom) ? (float)$postCustom : null,
            'target_rate'    => $effectiveTargetRate,
            'target_total'   => $effectiveTargetTotal,
            'open_sides'     => $postOpenSides,
            'layers'         => $postLayers,
            'equipment'      => $postEquipment,
            'zones'          => $postedCfg['zones'] ?? null,
            'show_roof_sheets'=> !empty($postedCfg['show_roof_sheets']) || !empty($_POST['show_roof_sheets']),
            'is_open_front'  => !empty($postOpenSides['front']),
            'is_training_net'=> (strpos($postSport, 'training_net') !== false),
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

        // If items in POST are completely empty, or if 105x170 complex is missing 6 courts flooring (<25 items), sync from serverBOQ
        $nonEmptyDescs = array_filter(array_map('trim', $_POST['item_desc'] ?? []));
        $sportType = $postSport;
        $isExistingCustomDoc = !empty($editDoc);
        $needsRegen105x170 = !$isExistingCustomDoc && ($sportType === 'multisport_complex_105x170' || ($postL == 170 && $postW == 105)) && count($nonEmptyDescs) < 20;
        $needsRegen185x60  = !$isExistingCustomDoc && ($sportType === 'multisport_arena_complex_185x60' || ($postL == 185 && $postW == 60)) && count($nonEmptyDescs) < 20;
        if (empty($nonEmptyDescs) || $needsRegen105x170 || $needsRegen185x60) {
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
            // When converting from estimation to invoice/bill, invoice date is today
            if ($isEstToInv || $isEstToBill) {
                $_POST['date'] = date('Y-m-d');
            }
        }
    }

    // When customer pays total dues / marked as paid, bill date is updated to current date
    if (in_array($targetType, ['tax_invoice', 'bill'])) {
        $payStatus = $_POST['payment_status'] ?? 'unpaid';
        if ($payStatus === 'paid') {
            $dateMonth = substr($_POST['date'] ?? '', 0, 7);
            $curMonth  = date('Y-m');
            if ($dateMonth !== $curMonth || (!empty($editDoc) && ($editDoc['payment_status'] ?? '') !== 'paid')) {
                $_POST['date'] = date('Y-m-d');
            }
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
            'country'  => trim($_POST['bt_country'] ?? 'India'),
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

// For turnkey court estimation, pre-generate full BOQ line items if items are empty or 105x170 needs 28 items
if ($estSubtype === 'turnkey_court') {
    $isItemsEmpty = empty($items) || (count($items) === 1 && empty($items[0]['description']) && empty($items[0]['rate']));
    $sportType = $d['turnkey_config']['sport_type'] ?? '';
    $needsRegen105x170 = ($sportType === 'multisport_complex_105x170' || (($d['turnkey_config']['length'] ?? 0) == 170 && ($d['turnkey_config']['width'] ?? 0) == 105)) && count($items) < 32;
    if ($isItemsEmpty || $needsRegen105x170) {
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
        $tkSideNet   = $d['turnkey_config']['side_net'] ?? 'braided_2_5mm_45mm';
        $tkTopNet    = $d['turnkey_config']['top_net'] ?? 'twisted_1_5mm_35mm';
        $tkGateCount = isset($d['turnkey_config']['gate_count']) ? (int)$d['turnkey_config']['gate_count'] : (strpos($tkSport, 'training_net') !== false ? 0 : 1);
        $tkCustomRate = !empty($d['turnkey_config']['custom_rate']) ? (float)$d['turnkey_config']['custom_rate'] : null;
        $tkPitchL    = isset($d['turnkey_config']['pitch_length']) ? (float)$d['turnkey_config']['pitch_length'] : null;

        $tkOpenSides = $d['turnkey_config']['open_sides'] ?? [];
        $initBOQ = generateTurnkeyBOQ($tkLength, $tkWidth, $tkHeight, $tkSport, $tkRoof, $tkPkg, $tkPoleStyle, $tkPipeSize, $tkPipeGauge, $tkTurfType, $tkCustomRate, $tkPitchL, $tkCivilBed, $tkSideNet, $tkTopNet, $tkGateCount, $tkOpenSides);
        if (!empty($initBOQ['doc_items'])) {
            $items = $initBOQ['doc_items'];
        }
    }
}
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
/* Generous auto-expanding description boxes across all documents */
.items-table td textarea,
.item-desc-field,
textarea[name^="item_desc"] {
  min-height: 84px !important;
  width: 100% !important;
  line-height: 1.45 !important;
  font-size: 0.88rem !important;
  font-family: inherit !important;
  padding: 0.5rem 0.65rem !important;
  border-radius: 6px !important;
  resize: vertical !important;
  overflow-y: hidden !important;
  box-sizing: border-box !important;
  background-color: #fff !important;
  border: 1px solid #cbd5e1 !important;
  color: #1e293b !important;
  white-space: pre-wrap !important;
  word-break: break-word !important;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, height 0.08s ease-out;
}
.items-table td textarea:focus,
.item-desc-field:focus,
textarea[name^="item_desc"]:focus {
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
  background-color: #fff !important;
  overflow-y: auto !important;
}
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

/* ── Mobile & Tablet Viewport Optimizations ── */
.table-swipe-hint {
  display: flex; align-items: center; gap: 0.45rem;
  font-size: 0.75rem; color: #475569; background: #f1f5f9;
  border-radius: 6px; padding: 0.35rem 0.65rem; margin-bottom: 0.5rem;
  border-left: 3px solid #3b82f6;
}
.tk-preset-scroll {
  display: flex; gap: 0.35rem; align-items: center; margin-bottom: 0.35rem;
}
@media (max-width: 768px) {
  .tk-preset-scroll {
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    white-space: nowrap !important;
    -webkit-overflow-scrolling: touch !important;
    padding-bottom: 4px;
  }
  .tk-preset-scroll::-webkit-scrollbar { height: 3px; }
  .tk-preset-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
}

/* Floating Save Footer for Mobile */
.mobile-save-bar {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: rgba(13, 27, 62, 0.96);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  border-top: 2px solid var(--red);
  box-shadow: 0 -4px 20px rgba(0,0,0,0.35);
  padding: 0.6rem 0.85rem; z-index: 1020;
}
.mobile-save-inner {
  display: flex; align-items: center; justify-content: space-between;
  gap: 0.5rem; max-width: 600px; margin: 0 auto;
}
.mobile-save-total { display: flex; flex-direction: column; }
.mobile-save-total .lbl { font-size: 0.62rem; letter-spacing: 0.5px; text-transform: uppercase; color: rgba(255,255,255,0.7); font-weight: 700; }
.mobile-save-total .amt { font-size: 1.15rem; font-weight: 900; color: #4ade80; line-height: 1.1; }
.mobile-save-actions { display: flex; align-items: center; gap: 0.35rem; }

@media (max-width: 991px) {
  body { padding-bottom: 68px !important; }
  .form-section { padding: 1rem 0.85rem !important; margin-bottom: 1rem !important; }
}
@media (max-width: 576px) {
  .btn-group.type-pills .btn { padding: 0.4rem 0.5rem; font-size: 0.78rem; }
  #turnkeyVisualizerBox .d-flex { flex-wrap: wrap !important; }
  #turnkeyVisualizerBox .btn-group { flex-wrap: wrap !important; }
  .items-table td textarea, .item-desc-field { min-height: 60px !important; font-size: 0.82rem !important; }
  .totals-table td { padding: 0.3rem 0.5rem; font-size: 0.82rem; }
}
</style>
<script src="assets/js/three.min.js"></script>
<script src="assets/js/OrbitControls.js"></script>
<script src="assets/js/turnkey_court_viewer_v5.js?v=<?= time() ?>"></script>


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
              <?php foreach (['estimation'=>'Estimation','proforma_invoice'=>'Proforma Invoice','tax_invoice'=>'Invoice','bill'=>'Bill'] as $k=>$lbl): ?>
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
      <div class="col-12" id="estSubtypeRow" <?= !in_array($type, ['estimation', 'proforma_invoice']) ? 'style="display:none;"' : '' ?>>
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
              'multi_block'          => ['label'=>'🏢 Multi-Block / Multi-Option', 'icon'=>'fa-cubes',       'color'=>'info'],
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

      <!-- Row 2: Doc# + Date + Currency -->
      <div class="col-md-4">
        <label class="form-label">Document # <small class="text-muted">(auto)</small></label>
        <input type="text" class="form-control bg-light" value="<?= $editId ? e($editId) : 'Auto-generated on save' ?>" disabled>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($d['date'] ?? date('Y-m-d')) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold text-primary"><i class="fa fa-coins text-warning me-1"></i>Currency</label>
        <select name="currency" id="doc_currency" class="form-select fw-bold border-primary" onchange="onCurrencyChange(this.value)">
          <?php foreach (getCurrenciesList() as $cCode => $cInfo): ?>
            <option value="<?= e($cCode) ?>" data-symbol="<?= e($cInfo['symbol']) ?>" <?= ($d['currency'] ?? 'INR') === $cCode ? 'selected' : '' ?>>
              <?= e($cInfo['name']) ?> (<?= e($cInfo['symbol']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Bill To -->
  <div class="form-section">
    <h5><i class="fa fa-user-tag"></i> Bill To (Customer)</h5>
    <?php
      $prefilledCustId = $d['customer_id'] ?? '';
      $bPhone = trim($d['bill_to']['phone'] ?? '');
      $bName  = trim($d['bill_to']['name'] ?? '');

      // Strict validation: Verify prefilledCustId actually belongs to this document's customer!
      if ($prefilledCustId) {
          $custMatchesDoc = false;
          foreach ($customers as $c) {
              if ($c['id'] === $prefilledCustId) {
                  $cName  = trim($c['name'] ?? '');
                  $cPhone = trim($c['phone'] ?? '');
                  if (($bName !== '' && strcasecmp($cName, $bName) === 0) ||
                      ($bPhone !== '' && $cPhone === $bPhone)) {
                      $custMatchesDoc = true;
                  }
                  break;
              }
          }
          if (!$custMatchesDoc) {
              $prefilledCustId = ''; // Mismatched customer ID (e.g. template/draft artifact) - discard!
          }
      }

      if (!$prefilledCustId && ($bPhone !== '' || $bName !== '')) {
          foreach ($customers as $c) {
              if (($bPhone !== '' && ($c['phone'] ?? '') === $bPhone) ||
                  ($bName !== '' && strcasecmp(trim($c['name'] ?? ''), $bName) === 0)) {
                  $prefilledCustId = $c['id'];
                  break;
              }
          }
      }
    ?>
    <input type="hidden" name="customer_id" id="customer_id" value="<?= e($prefilledCustId) ?>">
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
            data-country="<?= e($c['country'] ?? 'India') ?>"
            data-ship_country="<?= e($c['shipping_country'] ?? 'India') ?>"
            data-phone="<?= e($c['phone'] ?? '') ?>"
            data-whatsapp="<?= e($c['whatsapp'] ?? ($c['phone'] ?? '')) ?>"
            data-ship_name="<?= e($c['shipping_name'] ?? '') ?>"
            data-ship_address="<?= e($c['shipping_address'] ?? '') ?>"
            data-ship_phone="<?= e($c['shipping_phone'] ?? '') ?>"
            data-ship_state="<?= e($c['shipping_state'] ?? '') ?>"
            <?= $prefilledCustId === $c['id'] ? 'selected' : '' ?>>
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
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label fw-semibold mb-0" id="lbl_tax_id"><?= (($d['bill_to']['country'] ?? 'India') === 'India') ? 'GSTIN' : 'TAX ID / TRN / VAT NO' ?></label>
          <span class="small" id="gstQuickStatus"></span>
        </div>
        <div class="input-group">
          <input type="text" name="bt_gstin" id="bt_gstin" class="form-control font-monospace fw-bold" value="<?= e($d['bill_to']['gstin'] ?? '') ?>" placeholder="Tax ID / GSTIN / TRN" maxlength="25" style="text-transform:uppercase;">
          <button type="button" class="btn btn-outline-primary fw-bold" id="btnGstSearch" onclick="searchGstMaster()" title="Search & Auto-fill from GST Registered Directory">
            <i class="fa fa-magnifying-glass me-1"></i>Search GST
          </button>
        </div>
        <div id="gstSearchFeedback" class="small mt-1" style="display:none;"></div>
      </div>

      <!-- Mandatory Customer Mobile & WhatsApp Numbers with Dynamic International Country Codes -->
      <?php
        $valPhone = $d['bill_to']['phone'] ?? '';
        $valWa    = $d['bill_to']['whatsapp'] ?? '';
        if ($valPhone !== '' && $valWa === '') {
            $valWa = $valPhone;
        }
        $isSameMobile = ($valPhone === '' && $valWa === '') || ($valPhone !== '' && $valPhone === $valWa);
        $custCountry = $d['bill_to']['country'] ?? 'India';
        $countryList = getCountriesList();
        $currPhonePrefix = $countryList[$custCountry]['phone_prefix'] ?? '+91';
      ?>
      <div class="col-md-6">
        <label class="form-label fw-semibold"><i class="fa fa-phone text-primary me-1"></i>Customer Mobile / Phone <span class="text-danger">*</span></label>
        <div class="input-group">
          <select name="phone_prefix" id="phone_prefix" class="form-select bg-light fw-bold text-primary" style="max-width:115px;font-size:0.85rem;" onchange="updatePhonePrefix(this.value)">
            <?php foreach ($countryList as $cName => $cInfo): ?>
              <option value="<?= e($cInfo['phone_prefix']) ?>" data-country="<?= e($cName) ?>" <?= $currPhonePrefix === $cInfo['phone_prefix'] ? 'selected' : '' ?>>
                <?= e($cInfo['code']) ?> <?= e($cInfo['phone_prefix']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="bt_phone" id="bt_phone" class="form-control fw-semibold"
            value="<?= e($valPhone) ?>"
            required
            placeholder="Enter mobile number"
            oninput="handlePhoneInput(this, 'mobile')"
            onpaste="handlePhonePaste(event, 'mobile')"
            autocomplete="tel">
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <small class="text-muted" style="font-size:0.72rem;">Accepts international numbers (spaces auto-formatted without truncation)</small>
        </div>
      </div>

      <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label fw-semibold mb-0"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Number <span class="text-danger">*</span></label>
          <div class="form-check form-check-inline m-0">
            <input class="form-check-input" type="checkbox" id="same_as_mobile" <?= $isSameMobile ? 'checked' : '' ?> onchange="toggleSameAsMobile()">
            <label class="form-check-label small fw-bold text-success" for="same_as_mobile" style="cursor:pointer;font-size:0.75rem;">
              <i class="fab fa-whatsapp me-1"></i>Same as Mobile
            </label>
          </div>
        </div>
        <div class="input-group">
          <span class="input-group-text bg-light text-success fw-bold" id="wa_prefix_disp" style="font-size:0.85rem;min-width:85px;"><i class="fab fa-whatsapp"></i>&nbsp;<?= e($currPhonePrefix) ?></span>
          <input type="text" name="bt_whatsapp" id="bt_whatsapp" class="form-control fw-semibold"
            value="<?= e($valWa ?: $valPhone) ?>"
            required
            placeholder="WhatsApp Number"
            oninput="handlePhoneInput(this, 'whatsapp')"
            onpaste="handlePhonePaste(event, 'whatsapp')"
            <?= $isSameMobile ? 'readonly style="background-color:#f8fafc;"' : '' ?>
            autocomplete="tel">
        </div>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <small id="waHint" class="text-muted" style="font-size:0.72rem;"><?= $isSameMobile ? 'Synchronized with Mobile Number' : 'Custom WhatsApp number' ?></small>
        </div>
      </div>

      <!-- Complete Address, State/Province, Country -->
      <div class="col-md-4">
        <label class="form-label fw-semibold"><i class="fa fa-map-marker-alt text-danger me-1"></i>Address (Street / City / ZIP)</label>
        <textarea name="bt_address" id="bt_address" class="form-control" rows="2" placeholder="Street address, city, postal / zip code"><?= e($d['bill_to']['address'] ?? '') ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" id="lbl_bt_state">State / Province / Region</label>
        <input type="text" name="bt_state" id="bt_state" class="form-control" value="<?= e($d['bill_to']['state'] ?? '') ?>" placeholder="e.g. Telangana, Dubai, Masovian, Texas">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold"><i class="fa fa-globe text-primary me-1"></i>Country</label>
        <select name="bt_country" id="bt_country" class="form-select fw-bold border-primary" onchange="onCountryChange(this.value, 'bill')">
          <?php foreach ($countryList as $cName => $cInfo): ?>
            <option value="<?= e($cName) ?>" data-currency="<?= e($cInfo['currency']) ?>" data-prefix="<?= e($cInfo['phone_prefix']) ?>" data-tax="<?= e($cInfo['tax_label']) ?>" <?= $custCountry === $cName ? 'selected' : '' ?>>
              <?= e($cName) ?> (<?= e($cInfo['currency']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
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
        $hasShipData = !empty($shipData['address']) || !empty($shipData['name']);
        $showShipOnDoc = !empty($shipData['show_on_doc']);
      ?>
      <div class="col-12 mt-2 pt-2 border-top">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <label class="form-label fw-bold mb-0 text-primary" style="font-size:0.95rem;">
              <i class="fa fa-truck-ramp-box text-danger me-1"></i> Shipping / Site Delivery Address (SHIP TO)
            </label>
            <div class="form-check form-switch m-0 ms-2">
              <input class="form-check-input" type="checkbox" name="show_shipping_address" id="show_shipping_address" value="1" <?= $showShipOnDoc ? 'checked' : '' ?> onchange="toggleShipAddress()" style="cursor:pointer;">
              <label class="form-check-label small fw-bold text-success" for="show_shipping_address" style="cursor:pointer;">
                <span id="lblShipToggle"><?= $showShipOnDoc ? 'Enabled on Document' : 'Disabled (Click to Enable)' ?></span>
              </label>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="copyBillingToShipping()" style="font-size:0.75rem;padding:3px 10px;">
              <i class="fa fa-copy me-1"></i>Copy from Billing Address
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearShippingAddress()" style="font-size:0.75rem;padding:3px 8px;">
              <i class="fa fa-eraser me-1"></i>Clear
            </button>
          </div>
        </div>

        <div id="shipAddressBox" style="display:<?= $showShipOnDoc ? 'block' : 'none' ?>;background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px;padding:12px;">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">Consignee / Site Name</label>
              <input type="text" name="st_name" id="st_name" class="form-control form-control-sm bg-white" value="<?= e($shipData['name'] ?? '') ?>" placeholder="e.g. Site Office / Project Warehouse">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">Site Contact Person &amp; Phone</label>
              <input type="text" name="st_phone" id="st_phone" class="form-control form-control-sm bg-white" value="<?= e($shipData['phone'] ?? '') ?>" placeholder="e.g. Site Manager (+91 9502944409)">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1"><i class="fa fa-globe text-primary me-1"></i>Destination Country</label>
              <select name="st_country" id="st_country" class="form-select form-select-sm bg-white" onchange="onCountryChange(this.value, 'ship')">
                <?php foreach (getCountriesList() as $cName => $cInfo): ?>
                  <option value="<?= e($cName) ?>" <?= ($shipData['country'] ?? ($d['bill_to']['country'] ?? 'India')) === $cName ? 'selected' : '' ?>>
                    <?= e($cName) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold text-dark mb-1">Site / Delivery Address</label>
              <textarea name="st_address" id="st_address" class="form-control form-control-sm bg-white" rows="2" placeholder="Exact delivery location, landmark, city, postal code"><?= e($shipData['address'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">Destination State / Province</label>
              <input type="text" name="st_state" id="st_state" class="form-control form-control-sm bg-white" value="<?= e($shipData['state'] ?? '') ?>" placeholder="e.g. Andhra Pradesh / Dubai / Warsaw">
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
    <input type="hidden" name="turnkey_gates_json" id="turnkeyGatesJson" value="<?= e(json_encode($d['turnkey_config']['gates'] ?? [])) ?>">
    <input type="hidden" name="turnkey_zones_json" id="turnkeyZonesJson" value="<?= e(json_encode($d['turnkey_config']['zones'] ?? null)) ?>">
    <input type="hidden" name="turnkey_excluded_json" id="turnkeyExcludedJson" value="<?= e(json_encode($d['turnkey_config']['excluded_items'] ?? [])) ?>">
    <input type="hidden" name="tk_open_sides" id="tkOpenSidesInput" value="<?= e(json_encode($d['turnkey_config']['open_sides'] ?? null)) ?>">
    <input type="hidden" name="tk_layers" id="tkLayersInput" value="<?= e(json_encode($d['turnkey_config']['layers'] ?? null)) ?>">
    <input type="hidden" name="tk_equipment" id="tkEquipmentInput" value="<?= e(json_encode($d['turnkey_config']['equipment'] ?? null)) ?>">

    <!-- Row 1: Streamlined Sizing Presets Bar -->
    <div class="mb-3 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <label class="form-label text-dark fw-bold mb-0" style="font-size:8.5pt;">
          <i class="fa fa-ruler-combined text-danger me-1"></i>Quick-Select Standard Sizing Presets
        </label>
        <span class="text-muted" style="font-size:7pt;">Click to apply dimensions &bull; Any side can be removed/added below</span>
      </div>
      
      <div class="tk-preset-scroll mb-1">
        <span class="badge bg-dark flex-shrink-0" style="font-size:7pt;">🏏 PRACTICE NETS:</span>
        <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(50, 10, 10, 'cricket_training_net_50x10', 'light_cable_top', 'non_infill_turf', 'client_scope')">
          50 × 10 × 10' (Single Lane Net)
        </button>
        <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 10, 12, 'cricket_training_net_60x10', 'light_cable_top', 'non_infill_turf', 'client_scope')">
          60 × 10 × 12' (Pro Practice Net)
        </button>
        <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(75, 12, 15, 'cricket_training_net_75x12', 'light_cable_top', 'non_infill_turf', 'client_scope')">
          75 × 12 × 15' (Full Match Lane)
        </button>
        <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 50, 12, 'cricket_training_net_60x50', 'light_cable_top', 'non_infill_turf', 'client_scope')">
          60 × 50 × 12' (Multi-Lane Complex)
        </button>
      </div>

      <div class="tk-preset-scroll mb-1">
        <span class="badge bg-primary flex-shrink-0" style="font-size:7pt;">🏟️ BOX CRICKET:</span>
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(80, 40, 20, 'box_cricket', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
          80 × 40 × 20' (3,200 SFT)
        </button>
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(90, 50, 24, 'box_cricket', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
          90 × 50 × 24' (4,500 SFT)
        </button>
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(100, 60, 25, 'box_cricket', 'light_cable_top', 'non_infill_turf', 'compacted_wmm')">
          100 × 60 × 25' (6,000 SFT)
        </button>
        <button type="button" class="btn btn-xs btn-outline-info text-dark py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;background:#e0f2fe;border-color:#38bdf8;" onclick="applyTurnkeyPreset(100, 60, 30, 'multisport_4in1_100x60', 'metal_sheet_roof', 'sports_30mm_curled', 'client_scope')">
          ★ 🏏⚽🏐🏸 100 × 60 × 30' (6,000 SFT 4-in-1 Ground)
        </button>
      </div>

      <div class="tk-preset-scroll mb-1">
        <span class="badge bg-secondary flex-shrink-0" style="font-size:7pt;">⚽ FOOTBALL &amp; RACKET:</span>
        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 40, 18, 'futsal', 'light_cable_top', 'infill_turf', 'compacted_wmm')">
          60 × 40 × 18' (5-a-Side)
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(80, 50, 20, 'football_7a', 'light_cable_top', 'infill_turf', 'compacted_wmm')">
          80 × 50 × 20' (7-a-Side)
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(30, 60, 25, 'badminton', 'light_cable_top', 'bwf_vinyl_mat', 'concrete_pcc_3inch')">
          30 × 60 × 25' (Badminton)
        </button>
        <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(120, 60, 15, 'outdoor_multi_sports_120x60', 'open_top', 'synthetic_acrylic_8layer', 'asphalt_bitumen')">
          🏸🏀 120 × 60 × 15' (Badminton + Basketball)
        </button>
        <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(170, 105, 35, 'multisport_complex_105x170', 'metal_sheet_roof', 'sports_50mm_12000_dtex', 'pcc_m15_4inch_broom')">
          🏟️ 105 × 170 × 35' (16K Mega Arena: 4 Badminton + 2 Pickleball + Box Cricket)
        </button>
        <button type="button" class="btn btn-xs btn-outline-warning py-1 px-2 tk-preset-btn fw-bold" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(185, 60, 30, 'multisport_arena_complex_185x60', 'metal_sheet_roof', 'curled_multisport_turf', 'concrete_pcc_3inch')">
          🏟️ 185 × 60 × 30' (11K Arena: Cricket + 2 Badminton + Lounge)
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 tk-preset-btn" style="font-size:7.5pt;" onclick="applyTurnkeyPreset(60, 120, 30, 'tennis', 'open_top', 'synthetic_acrylic_8layer', 'concrete_pcc_3inch')">
          60 × 120 × 30' (ITF Tennis)
        </button>
      </div>
    </div>


    <!-- Row 2: Court Dimensions & Sport Type -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <label class="form-label text-danger fw-bold">Length (Feet) *</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_length" name="tk_length" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['length'] ?? 90) ?>" min="15" max="300" step="1" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label text-danger fw-bold">Width (Feet) *</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_width" name="tk_width" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['width'] ?? 60) ?>" min="6" max="200" step="1" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label text-danger fw-bold">Clear Height (Feet) *</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_height" name="tk_height" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['height'] ?? 25) ?>" min="8" max="60" step="1" oninput="onTurnkeyHeightChange(false)" onchange="onTurnkeyHeightChange(true)">
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
          <optgroup label="🏏 Cricket Training Nets (3 Sides + Top Enclosed, Open Front)">
            <option value="cricket_training_net_50x10" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'cricket_training_net_50x10' ? 'selected' : '' ?>>🏏 Training Cricket Net (Single Lane 50×10 / 10'H)</option>
            <option value="cricket_training_net_60x50" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'cricket_training_net_60x50' ? 'selected' : '' ?>>🏏 Training Cricket Net Complex (Multi-Lane 60×50 / 12'H)</option>
            <option value="cricket_training_net_75x12" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'cricket_training_net_75x12' ? 'selected' : '' ?>>🏏 Training Cricket Net Pro (Match Lane 75×12 / 15'H)</option>
            <option value="cricket_training_net" <?= in_array(($d['turnkey_config']['sport_type'] ?? ''), ['cricket_training_net', 'training_net']) ? 'selected' : '' ?>>🎯 Cricket Training Net (Custom Size — 3 Sides + Top)</option>
            <option value="training_net_2lane" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'training_net_2lane' ? 'selected' : '' ?>>🎯 Cricket Training Net (2-Lane 24×70 / 14'H)</option>
            <option value="training_net_3lane" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'training_net_3lane' ? 'selected' : '' ?>>🎯 Cricket Training Net (3-Lane 36×80 / 16'H)</option>
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
          <optgroup label="🏟️ Multi-Sport Arena Complexes (Multi-Zone Layouts)">
            <option value="multisport_4in1_100x60" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'multisport_4in1_100x60' ? 'selected' : '' ?>>★ 🏏⚽🏐🏸 4-in-1 Multi-Sport Ground (100×60 / 30'H — Cricket + Football + Volleyball + Badminton)</option>
            <option value="multisport_complex_105x170" <?= in_array(($d['turnkey_config']['sport_type'] ?? ''), ['multisport_complex_105x170']) ? 'selected' : '' ?>>★ 🏟️ Mega Sports Arena (105×170 / 35'H — 11K SFT Indoor 4 Badminton + 2 Pickleball + 4.5K SFT Outdoor Box Cricket)</option>
            <option value="outdoor_multi_sports_120x60" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'outdoor_multi_sports_120x60' ? 'selected' : '' ?>>★ 🏸🏀 Outdoor Multi Sports Court (120×60 / 15'H - Badminton + Basketball + 8-Layer Acrylic)</option>
            <option value="multisport_arena_complex_185x60" <?= in_array(($d['turnkey_config']['sport_type'] ?? ''), ['multisport_arena_complex_185x60', 'multisport_185x60']) ? 'selected' : '' ?>>★ 🏟️ Multi-Sport Complex (185×60 / 30'H - 100×60 Cricket + 45×60 2 Badminton Courts + 40×60 Remaining Space)</option>
            <option value="hybrid_cricket_football" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'hybrid_cricket_football' ? 'selected' : '' ?>>🏏⚽ Hybrid Arena (Half Cricket + Half Football 120×60 / 25'H)</option>
            <option value="hybrid_badminton_pickleball" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'hybrid_badminton_pickleball' ? 'selected' : '' ?>>🏸🥒 Racket Sports Hub (2 Badminton + 2 Pickleball Courts 90×45 / 25'H)</option>
            <option value="hybrid_tennis_basketball" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'hybrid_tennis_basketball' ? 'selected' : '' ?>>🎾🏀 Hardcourt Complex (Tennis + Basketball 120×60 / 25'H)</option>
            <option value="multi_sport" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'multi_sport' ? 'selected' : '' ?>>🏏⚽ Multi-Sport (Cricket + Football Combo)</option>
            <option value="multi_futsal_basket" <?= ($d['turnkey_config']['sport_type'] ?? '') === 'multi_futsal_basket' ? 'selected' : '' ?>>⚽🏀 Multi-Sport (Futsal + Basketball Combo)</option>
          </optgroup>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-cricket-bat-ball text-warning me-1"></i>Center Cricket Pitch Length</label>
        <div class="input-group input-group-sm">
          <input type="number" id="tk_pitch_length" name="tk_pitch_length" class="form-control fw-bold fs-6" value="<?= e($d['turnkey_config']['pitch_length'] ?? 66) ?>" min="10" max="100" step="0.5" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <span class="input-group-text">FT</span>
        </div>
        <div class="text-muted" style="font-size:7pt;" id="tk_pitch_helper">Width: <strong>6.5 FT</strong> | 3 FT offset each end (Full 540 SFT or Half 270 SFT @ ₹78)</div>
      </div>
    </div>

        <!-- Multi-Zone Arena Complex Dynamic Control Card -->
    <?php
$mzLen1Val = (float)($editDoc["turnkey_config"]["zones"]["cricket"]["length"] ?? 100);
$mzLen2Val = (float)($editDoc["turnkey_config"]["zones"]["badminton"]["length"] ?? ($editDoc["badminton_length"] ?? 45));
$mzLen3Val = (float)($editDoc["turnkey_config"]["zones"]["utility"]["length"] ?? ($editDoc["utility_length"] ?? 40));
?>
    <div id="multiZoneCard" class="card border-0 shadow-sm mb-3" style="display:<?= (($d['turnkey_config']['sport_type'] ?? '') === 'multisport_arena_complex_185x60' || strpos($d['turnkey_config']['sport_type'] ?? '', 'multisport') !== false) ? 'block' : 'none' ?>;background:#f0fdf4;border:1.5px solid #22c55e !important;border-radius:10px;">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <div class="fw-bold text-dark" style="font-size:0.95rem;">
            <i class="fa fa-layer-group text-success me-1"></i> Multi-Zone Arena Builder (Divide into 2, 3, 4 or 5 Custom Sports Zones)
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-success fw-bold py-0 px-2" onclick="addCustomZone()"><i class="fa fa-plus me-1"></i>Add Zone (+)</button>
            <button type="button" class="btn btn-sm btn-outline-danger fw-bold py-0 px-2" onclick="removeLastCustomZone()"><i class="fa fa-minus me-1"></i>Remove Zone (-)</button>
            <div class="badge bg-dark text-white px-2 py-1" id="multiZoneTotalBadge">
              Total Arena: 185 FT (11,100 SFT)
            </div>
          </div>
        </div>

        <!-- Dynamic Zones Container -->
        <div id="dynamicZonesContainer" class="row g-2">
          <!-- Zone 1: Cricket -->
          <div class="col-md-4 zone-col" id="zoneBox_1">
            <div class="p-2 bg-white rounded border border-success h-100 shadow-sm">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-success" style="font-size:0.85rem;"><i class="fa fa-cricket-bat-ball me-1"></i>Zone 1: Cricket Ground</span>
                <span class="badge bg-success" id="z1AreaBadge">6,000 SFT</span>
              </div>
              <div class="badge bg-success-subtle text-success border border-success-subtle w-100 mb-1 py-1 text-start fw-normal" id="z1CostBadge" style="font-size:6.8pt;">
                ₹340.70/SFT (Excl) &bull; <strong>₹391.48/SFT</strong> (Incl) &bull; Total: <strong>₹23.49 Lakh</strong>
              </div>
              <div class="mb-1">
                <label class="form-label small text-muted mb-0" style="font-size:7.5pt;">Length (FT):</label>
                <div class="input-group input-group-sm">
                  <input type="number" id="mz_len_1" class="form-control fw-bold" value="<?= $mzLen1Val ?>" min="20" max="250" step="1" oninput="onMultiZoneChange()">
                  <span class="input-group-text">FT</span>
                </div>
              </div>
              <div class="small text-muted" style="font-size:7pt;">
                Dimensions: <strong id="z1DimText">100 FT × 60 FT</strong><br>
                Pitch: <strong>83 FT Match Pitch (15mm Curled Turf)</strong><br>
                Outfield: <strong id="z1OutfieldText" class="text-success">5,460 SFT 40mm Landscape Grass (Zero Infill)</strong>
              </div>
            </div>
          </div>

          <!-- Zone 2: Badminton -->
          <div class="col-md-4 zone-col" id="zoneBox_2">
            <div class="p-2 bg-white rounded border border-primary h-100 shadow-sm">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-primary" style="font-size:0.85rem;"><i class="fa fa-table-tennis-paddle-ball me-1"></i>Zone 2: Badminton Complex</span>
                <span class="badge bg-primary" id="z2AreaBadge">2,700 SFT</span>
              </div>
              <div class="badge bg-primary-subtle text-primary border border-primary-subtle w-100 mb-1 py-1 text-start fw-normal" id="z2CostBadge" style="font-size:6.8pt;">
                ₹432.42/SFT (Excl) &bull; <strong>₹507.31/SFT</strong> (Incl) &bull; Total: <strong>₹13.70 Lakh</strong> (Per Court: <strong>₹6.85L</strong>)
              </div>
              <div class="mb-1">
                <label class="form-label small text-muted mb-0" style="font-size:7.5pt;">Length (FT):</label>
                <div class="input-group input-group-sm">
                  <input type="number" id="mz_len_2" class="form-control fw-bold" value="<?= $mzLen2Val ?>" min="20" max="250" step="1" oninput="onMultiZoneChange()">
                  <span class="input-group-text">FT</span>
                </div>
              </div>
              <div class="mb-1">
                <label class="form-label small text-muted mb-0" style="font-size:7.5pt;">Badminton Sub-Base Bed System:</label>
                <select id="mz_badminton_bed" name="mz_badminton_bed" class="form-select form-select-sm fw-semibold" style="font-size:7.5pt;" onchange="generateTurnkeyBOQAndPopulate(false)">
                  <option value="rcc_m20_4inch_laser" selected>🧱 4" M20 Laser RCC Slab + 8mm TMT Mesh (~₹75/SFT) [Recommended]</option>
                  <option value="rcc_m20_5inch_tmt">🧱 5" Heavy M20 RCC Slab + Dual TMT Grid (~₹105/SFT)</option>
                  <option value="pcc_m15_4inch_smooth">🧱 4" PCC M15 Plain Concrete Laser Bed (~₹76/SFT)</option>
                  <option value="pcc_m15_3inch_smooth">🧱 3" PCC M15 Plain Concrete Smooth Bed (~₹60/SFT)</option>
                  <option value="client_scope">🏢 Existing Concrete Slab / Client Scope (₹0 in BOQ)</option>
                </select>
              </div>
              <div class="mb-1">
                <label class="form-label small text-muted mb-0" style="font-size:7.5pt;">Badminton Surface / Flooring System:</label>
                <select id="mz_badminton_floor" name="mz_badminton_floor" class="form-select form-select-sm fw-semibold" style="font-size:7.5pt;" onchange="generateTurnkeyBOQAndPopulate(false)">
                  <option value="bwf_vinyl_4_5mm" selected>🏸 BWF Certified 4.5mm Vinyl Sports Mats (~₹95/SFT)</option>
                  <option value="bwf_vinyl_5_0mm_wood">🪵 5.0mm Wooden Texture BWF Vinyl (~₹115/SFT)</option>
                  <option value="acrylic_cushion_8layer">🎨 8-Layer Cushion Acrylic Court (~₹85/SFT)</option>
                  <option value="acrylic_pro_10layer">🏆 10-Layer Pro Cushion Acrylic (~₹115/SFT)</option>
                  <option value="imported_teak_wood">🌲 Premium Teak Wood Sports Floor (~₹245/SFT)</option>
                </select>
              </div>
              <div class="small text-muted" style="font-size:7pt;">
                Dimensions: <strong id="z2DimText">45 FT × 60 FT</strong><br>
                Division: <strong>Court 1 (45×30) + Court 2 (45×30)</strong><br>
                Enclosure: <strong class="text-success">75% Dark Green Shade Net on 4 sides (30'H)</strong>
              </div>
            </div>
          </div>

          <!-- Zone 3: Remaining Space -->
          <div class="col-md-4 zone-col" id="zoneBox_3">
            <div class="p-2 bg-white rounded border border-secondary h-100 shadow-sm">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark" style="font-size:0.85rem;"><i class="fa fa-building-user me-1"></i>Zone 3: Remaining Utility</span>
                <span class="badge bg-secondary" id="z3AreaBadge">2,400 SFT</span>
              </div>
              <div class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle w-100 mb-1 py-1 text-start fw-normal" id="z3CostBadge" style="font-size:6.8pt;">
                ₹317.61/SFT (Excl) &bull; <strong>₹371.83/SFT</strong> (Incl) &bull; Total: <strong>₹8.92 Lakh</strong>
              </div>
              <div class="mb-1">
                <label class="form-label small text-muted mb-0" style="font-size:7.5pt;">Remaining Length (Auto-Balanced):</label>
                <div class="input-group input-group-sm">
                  <input type="number" id="mz_len_3" class="form-control fw-bold bg-light" value="<?= $mzLen3Val ?>" readonly>
                  <span class="input-group-text">FT</span>
                </div>
              </div>
              <div class="small text-muted" style="font-size:7pt;">
                Dimensions: <strong id="z3DimText">40 FT × 60 FT</strong><br>
                Scope: <strong>Reception • Lounge • Cafeteria</strong><br>
                Roof: <strong class="text-teal">Continuous 0.50mm Metal Sheet Roof</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Interactive Proportional Progress Bar -->
        <div class="progress mt-2" style="height: 18px; font-size: 7.5pt; font-weight: bold;" id="mzProgressBar">
          <div id="z1Bar" class="progress-bar bg-success" style="width: 54.1%;">🏏 Cricket (100')</div>
          <div id="z2Bar" class="progress-bar bg-primary" style="width: 24.3%;">🏸 Badminton 2 Courts (45')</div>
          <div id="z3Bar" class="progress-bar bg-secondary" style="width: 21.6%;">🏢 Remaining (40')</div>
        </div>
      </div>
    </div>
    <!-- 1-Click Netsocean-Style Side & Layer Configurator -->
    <div class="row g-2 mb-3">
      <!-- 5-Side 1-Click Quick Selector -->
      <div class="col-md-7">
        <div class="p-2 rounded border h-100" style="background:#f8fafc; border-color:#cbd5e1 !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold text-dark" style="font-size:8.2pt;">
              <i class="fa fa-cube text-primary me-1"></i>1-Click Enclosure Sides (Click any side to open or close)
            </span>
            <span class="badge bg-light text-secondary border" style="font-size:6.8pt;">Instant 3D update &bull; Dynamic BOQ</span>
          </div>
          <div class="d-flex flex-wrap gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-2 py-1" id="btnSideFront" onclick="toggleTurnkeySide('front')">
              <i class="fa fa-door-open me-1"></i>Front: <span id="lblSideFront">OPEN</span>
            </button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-2 py-1" id="btnSideBack" onclick="toggleTurnkeySide('back')">
              <i class="fa fa-shield me-1"></i>Back: <span id="lblSideBack">ENCLOSED</span>
            </button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-2 py-1" id="btnSideLeft" onclick="toggleTurnkeySide('left')">
              <i class="fa fa-arrow-left me-1"></i>Left: <span id="lblSideLeft">ENCLOSED</span>
            </button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-2 py-1" id="btnSideRight" onclick="toggleTurnkeySide('right')">
              <i class="fa fa-arrow-right me-1"></i>Right: <span id="lblSideRight">ENCLOSED</span>
            </button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-2 py-1" id="btnSideTop" onclick="toggleTurnkeySide('top')">
              <i class="fa fa-arrow-up me-1"></i>Roof: <span id="lblSideTop">ENCLOSED</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Layer Configurator -->
      <div class="col-md-5">
        <div class="p-2 rounded border h-100" style="background:#f0fdf4; border-color:#bbf7d0 !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold text-dark" style="font-size:8.2pt;">
              <i class="fa fa-layer-group text-success me-1"></i>Component Layers
            </span>
            <span class="badge bg-white text-success border border-success-subtle" style="font-size:6.8pt;">Include/Exclude</span>
          </div>
          <div class="d-flex flex-wrap gap-1">
            <button type="button" class="btn btn-sm btn-dark fw-bold px-2 py-1" id="btnLayerStructure" onclick="toggleTurnkeyLayer('structure')" title="Toggle Columns and Horizontal Tie Pipes">
              <i class="fa fa-trowel-bricks me-1"></i>Poles: <span id="lblLayerStructure">ON</span>
            </button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-2 py-1" id="btnLayerNetting" onclick="toggleTurnkeyLayer('netting')" title="Toggle Perimeter and Roof Netting">
              <i class="fa fa-border-all me-1"></i>Nets: <span id="lblLayerNetting">ON</span>
            </button>
            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold px-2 py-1" id="btnLayerLighting" onclick="toggleTurnkeyLayer('lighting')" title="Toggle LED Floodlights">
              <i class="fa fa-lightbulb me-1"></i>Lights: <span id="lblLayerLighting">ON</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MULTI-SPORT EQUIPMENT & ACCESSORIES INDIVIDUAL ON / OFF SUITE -->
    <div class="card mb-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%); border-left: 4px solid #0d6efd !important;">
      <div class="card-body p-2 p-md-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div>
            <span class="fw-bold text-dark" style="font-size:0.92rem;">
              <i class="fa fa-futbol text-primary me-1"></i>Multi-Sport Equipment &amp; Games Suite (1-Click Individual ON / OFF)
            </span>
            <div class="text-muted" style="font-size:7.5pt;">
              Toggle any sport or gear ON/OFF. Automatically adds/removes item rows in the BOQ table &amp; updates 3D model &amp; 2D floor plans in real-time.
            </div>
          </div>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-xs btn-outline-success fw-bold py-1 px-2" style="font-size:7.5pt;" onclick="setAllTurnkeyEquipment(true)">
              <i class="fa fa-check-double me-1"></i>Enable All 5
            </button>
            <button type="button" class="btn btn-xs btn-outline-secondary fw-bold py-1 px-2" style="font-size:7.5pt;" onclick="setAllTurnkeyEquipment(false)">
              <i class="fa fa-times me-1"></i>Disable All
            </button>
          </div>
        </div>

        <div class="row g-2">
          <!-- Cricket Pitch -->
          <div class="col-6 col-md-auto flex-grow-1">
            <button type="button" class="btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border" id="btnEqCricketPitch" onclick="toggleTurnkeyEquipment('cricket_pitch')" style="background:#fff; border-color:#e2e8f0; transition:all 0.2s;">
              <span class="d-flex align-items-center gap-1 text-truncate" style="font-size:8pt;">
                <span>🏏</span>
                <span class="text-truncate">Cricket Pitch (15mm + Stumps)</span>
              </span>
              <span class="badge bg-secondary ms-1" id="lblEqCricketPitch">OFF</span>
            </button>
          </div>

          <!-- Football Goal Posts -->
          <div class="col-6 col-md-auto flex-grow-1">
            <button type="button" class="btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border" id="btnEqFootballGoals" onclick="toggleTurnkeyEquipment('football_goals')" style="background:#fff; border-color:#e2e8f0; transition:all 0.2s;">
              <span class="d-flex align-items-center gap-1 text-truncate" style="font-size:8pt;">
                <span>⚽</span>
                <span class="text-truncate">Mini Football Goals (Pair)</span>
              </span>
              <span class="badge bg-secondary ms-1" id="lblEqFootballGoals">OFF</span>
            </button>
          </div>

          <!-- Volleyball Posts & Net -->
          <div class="col-6 col-md-auto flex-grow-1">
            <button type="button" class="btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border" id="btnEqVolleyballNet" onclick="toggleTurnkeyEquipment('volleyball_net')" style="background:#fff; border-color:#e2e8f0; transition:all 0.2s;">
              <span class="d-flex align-items-center gap-1 text-truncate" style="font-size:8pt;">
                <span>🏐</span>
                <span class="text-truncate">Volleyball System</span>
              </span>
              <span class="badge bg-secondary ms-1" id="lblEqVolleyballNet">OFF</span>
            </button>
          </div>

          <!-- Movable Badminton Posts & Net -->
          <div class="col-6 col-md-auto flex-grow-1">
            <button type="button" class="btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border" id="btnEqBadmintonNet" onclick="toggleTurnkeyEquipment('badminton_net')" style="background:#fff; border-color:#e2e8f0; transition:all 0.2s;">
              <span class="d-flex align-items-center gap-1 text-truncate" style="font-size:8pt;">
                <span>🏸</span>
                <span class="text-truncate">Badminton Movable Net</span>
              </span>
              <span class="badge bg-secondary ms-1" id="lblEqBadmintonNet">OFF</span>
            </button>
          </div>

          <!-- 4-in-1 Line Markings -->
          <div class="col-6 col-md-auto flex-grow-1">
            <button type="button" class="btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border" id="btnEqCourtMarkings" onclick="toggleTurnkeyEquipment('court_markings')" style="background:#fff; border-color:#e2e8f0; transition:all 0.2s;">
              <span class="d-flex align-items-center gap-1 text-truncate" style="font-size:8pt;">
                <span>🎨</span>
                <span class="text-truncate">Line Markings (4-in-1)</span>
              </span>
              <span class="badge bg-secondary ms-1" id="lblEqCourtMarkings">OFF</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- DEDICATED BADMINTON SELECTIONS (Prominent 2-Court Bed & Flooring Setup) -->
    <div class="row g-2 mb-2 p-2 bg-primary-subtle rounded border border-primary shadow-sm" id="badmintonSpecsRow" style="<?= (strpos($d['turnkey_config']['sport_type'] ?? '', 'multisport') !== false || strpos($d['turnkey_config']['sport_type'] ?? '', 'badminton') !== false) ? '' : 'display:none;' ?>">
      <div class="col-12 mb-1 d-flex justify-content-between align-items-center">
        <span class="fw-bold text-primary" style="font-size:0.85rem;"><i class="fa fa-table-tennis-paddle-ball me-1"></i>Badminton Arena Systems (Zone 2 - 2 Regulation Courts / 2,700 SFT)</span>
        <span class="badge bg-primary">Two 45×30 FT Courts (100% Weatherproof Indoor Hall)</span>
      </div>
      <div class="col-md-6">
        <label class="form-label text-dark fw-bold mb-0" style="font-size:8pt;"><i class="fa fa-trowel-bricks text-primary me-1"></i>Badminton Sub-Base Bed System</label>
        <select id="main_badminton_bed" class="form-select form-select-sm fw-semibold" onchange="syncBadmintonBed(this.value)">
          <option value="rcc_m20_4inch_laser" <?= ($d['turnkey_config']['badminton_bed_type'] ?? 'rcc_m20_4inch_laser') === 'rcc_m20_4inch_laser' ? 'selected' : '' ?>>🧱 4" M20 Laser RCC Slab + 8mm TMT Mesh (~₹75/SFT) [Recommended Standard]</option>
          <option value="rcc_m20_5inch_tmt" <?= ($d['turnkey_config']['badminton_bed_type'] ?? '') === 'rcc_m20_5inch_tmt' ? 'selected' : '' ?>>🧱 5" Heavy M20 RCC Slab + Dual TMT Grid (~₹105/SFT)</option>
          <option value="pcc_m15_4inch_smooth" <?= ($d['turnkey_config']['badminton_bed_type'] ?? '') === 'pcc_m15_4inch_smooth' ? 'selected' : '' ?>>🧱 4" PCC M15 Plain Concrete Laser Bed (~₹76/SFT)</option>
          <option value="pcc_m15_3inch_smooth" <?= ($d['turnkey_config']['badminton_bed_type'] ?? '') === 'pcc_m15_3inch_smooth' ? 'selected' : '' ?>>🧱 3" PCC M15 Plain Concrete Smooth Bed (~₹60/SFT)</option>
          <option value="client_scope" <?= ($d['turnkey_config']['badminton_bed_type'] ?? '') === 'client_scope' ? 'selected' : '' ?>>🏢 Existing Concrete Slab / Client Scope (₹0 in BOQ)</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label text-dark fw-bold mb-0" style="font-size:8pt;"><i class="fa fa-layer-group text-primary me-1"></i>Badminton Surface / Flooring System</label>
        <select id="main_badminton_floor" class="form-select form-select-sm fw-semibold" onchange="syncBadmintonFloor(this.value)">
          <option value="bwf_vinyl_4_5mm" <?= ($d['turnkey_config']['badminton_floor_type'] ?? 'bwf_vinyl_4_5mm') === 'bwf_vinyl_4_5mm' ? 'selected' : '' ?>>🏸 BWF Certified 4.5mm Vinyl Sports Mats (~₹95/SFT) [Tournament Grade]</option>
          <option value="bwf_vinyl_5_0mm_wood" <?= ($d['turnkey_config']['badminton_floor_type'] ?? '') === 'bwf_vinyl_5_0mm_wood' ? 'selected' : '' ?>>🪵 5.0mm Wooden Texture BWF Vinyl (~₹115/SFT)</option>
          <option value="acrylic_cushion_8layer" <?= ($d['turnkey_config']['badminton_floor_type'] ?? '') === 'acrylic_cushion_8layer' ? 'selected' : '' ?>>🎨 8-Layer Cushion Acrylic Court (~₹85/SFT)</option>
          <option value="acrylic_pro_10layer" <?= ($d['turnkey_config']['badminton_floor_type'] ?? '') === 'acrylic_pro_10layer' ? 'selected' : '' ?>>🏆 10-Layer Pro Cushion Acrylic (~₹115/SFT)</option>
          <option value="imported_teak_wood" <?= ($d['turnkey_config']['badminton_floor_type'] ?? '') === 'imported_teak_wood' ? 'selected' : '' ?>>🌲 Premium Teak Wood Sports Floor (~₹245/SFT)</option>
        </select>
      </div>
    </div>

    <!-- Row 3: Civil Bed System, Turf Infill Type, Roof Support Netting & Pole Style -->
    <div class="row g-2 mb-2">
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-trowel-bricks text-danger me-1"></i>Civil Ground &amp; Bed System</label>
        <select id="tk_civil_bed" name="tk_civil_bed" class="form-select form-select-sm fw-semibold" onchange="generateTurnkeyBOQAndPopulate(false)">
          <optgroup label="🚫 None / Existing Floor (₹0 in BOQ)">
            <option value="client_scope" <?= in_array(($d['turnkey_config']['civil_bed_type'] ?? ''), ['client_scope', 'none']) ? 'selected' : '' ?>>🚫 None / Existing Floor (₹0 in BOQ) - Client Scope / Existing Slab</option>
          </optgroup>
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
        <select id="tk_turf_type" name="tk_turf_type" class="form-select form-select-sm fw-semibold" onchange="generateTurnkeyBOQAndPopulate(false)">
          <optgroup label="🌾 Landscape Sports Grass (Zero Infill)">
            <option value="landscape_25mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'landscape_25mm' ? 'selected' : '' ?>>🌾 25mm Landscape Sports Grass (₹30/SFT)</option>
            <option value="landscape_30mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'landscape_30mm' ? 'selected' : '' ?>>🌾 30mm Landscape Sports Grass (₹35/SFT)</option>
            <option value="landscape_35mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'landscape_35mm' ? 'selected' : '' ?>>🌾 35mm Landscape Sports Grass (₹40/SFT)</option>
            <option value="landscape_40mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'landscape_40mm' ? 'selected' : '' ?>>🌾 40mm Landscape Sports Grass (₹45/SFT)</option>
          </optgroup>
          <optgroup label="🏏 Sports &amp; Box Cricket Turfs (By DTEX &amp; Non-Infill)">
            <option value="sports_30mm_non_infill" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'sports_30mm_non_infill' ? 'selected' : '' ?>>🏏 30mm High-Density Non-Infill Sports Turf (₹60/SFT)</option>
            <option value="sports_50mm_12000_dtex" <?= in_array(($d['turnkey_config']['turf_type'] ?? 'sports_50mm_12000_dtex'), ['sports_50mm_12000_dtex', 'infill_turf', '']) ? 'selected' : '' ?>>⚽ 50mm Football / Box Cricket Turf (12,000 DTEX) (₹58/SFT)</option>
            <option value="sports_50mm_14000_dtex" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'sports_50mm_14000_dtex' ? 'selected' : '' ?>>⚽ 50mm Football / Box Cricket Turf (14,000 DTEX) (₹64/SFT)</option>
            <option value="sports_50mm_16000_dtex" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'sports_50mm_16000_dtex' ? 'selected' : '' ?>>🏆 50mm Tournament Sports Turf (16,000 DTEX) (₹75/SFT)</option>
            <option value="non_infill_turf" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'non_infill_turf' ? 'selected' : '' ?>>🏏 15mm Curled Monofilament Cricket Turf (~₹52/SFT)</option>
            <option value="turf_cricket_20mm" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'turf_cricket_20mm' ? 'selected' : '' ?>>🏏 20mm High-Density Multi-Sport Turf (~₹72/SFT)</option>
            <option value="cricket_dual_turf" <?= ($d['turnkey_config']['turf_type'] ?? '') === 'cricket_dual_turf' ? 'selected' : '' ?>>🏏 Dual: Outfield Turf + Dedicated Center Pitch Roll (~₹76/SFT)</option>
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
          <optgroup label="🏗️ 100% Rainproof &amp; Sun-Heat Protection Metal Roofing">
            <option value="metal_sheet_roof" <?= in_array(($d['turnkey_config']['roof_type'] ?? ''), ['metal_sheet_roof', 'sheet_roof', 'peb_roof']) ? 'selected' : '' ?>>★ 🏗️ 0.50mm Pre-Painted Galvalume (PPGL) / GI Profile Metal Sheet Roof (100% Rainproof &amp; UV Protection)</option>
            <option value="gi_roof_skylight" <?= ($d['turnkey_config']['roof_type'] ?? '') === 'gi_roof_skylight' ? 'selected' : '' ?>>☀️ 0.50mm PPGL GI Metal Roof + Polycarbonate Daylight Sky-Panels</option>
            <option value="puf_panel_roof" <?= ($d['turnkey_config']['roof_type'] ?? '') === 'puf_panel_roof' ? 'selected' : '' ?>>🛡️ Insulated Sandwich PUF Panel Metal Roof (Max Heat Rejection &amp; Rain Sound Insulation)</option>
          </optgroup>
          <optgroup label="🕸️ Overhead Sports Netting &amp; Trusses">
            <option value="truss_top" <?= in_array(($d['turnkey_config']['roof_type'] ?? ''), ['truss_top', 'full_roof']) ? 'selected' : '' ?>>🏗️ Engineered Triangular Trusses (Open Mesh Netting Top)</option>
            <option value="light_cable_top" <?= in_array(($d['turnkey_config']['roof_type'] ?? 'light_cable_top'), ['light_cable_top', 'top_net', '']) ? 'selected' : '' ?>>⚡ Lightweight Cable Net Top (No Steel Top)</option>
            <option value="pipe_top" <?= ($d['turnkey_config']['roof_type'] ?? '') === 'pipe_top' ? 'selected' : '' ?>>📏 Cross Pipes Top Support (Small Courts)</option>
            <option value="open_top" <?= ($d['turnkey_config']['roof_type'] ?? '') === 'open_top' ? 'selected' : '' ?>>☀️ Open Top (Side &amp; Back Nets Only)</option>
          </optgroup>
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
          $curTopNet = $d['turnkey_config']['top_net'] ?? 'none';
        ?>
        <select id="tk_top_net" name="tk_top_net" class="form-select form-select-sm fw-semibold" onchange="updateTurnkeyCalculations(true)">
          <optgroup label="🏗️ 100% Rainproof Metal Roof Sheet Covering (Attached to PEB Trusses)">
            <option value="metal_sheet_roof" <?= in_array($curTopNet, ['metal_sheet_roof', 'sheet_roof']) ? 'selected' : '' ?>>★ 🏗️ 0.50mm Pre-Painted Galvalume (PPGL) Profile Metal Sheet Roof (~₹58/SFT)</option>
            <option value="gi_roof_skylight" <?= $curTopNet === 'gi_roof_skylight' ? 'selected' : '' ?>>☀️ 0.50mm PPGL GI Metal Roof + Polycarbonate Skylight Panels (~₹88/SFT)</option>
            <option value="puf_panel_roof" <?= $curTopNet === 'puf_panel_roof' ? 'selected' : '' ?>>🛡️ 50mm Insulated Sandwich PUF Panel Metal Roof (~₹145/SFT)</option>
          </optgroup>
          <optgroup label="🚫 No Top Net / Open Top">
            <option value="none" <?= in_array($curTopNet, ['none', 'open_top', '']) ? 'selected' : '' ?>>🚫 None / Sheet Roof Only (No Overhead Net)</option>
          </optgroup>
          <optgroup label="Inner Ceiling / Under-Roof & Overhead Protection Nets">
            <option value="twisted_1_5mm_35mm" <?= $curTopNet === 'twisted_1_5mm_35mm' ? 'selected' : '' ?>>1.5mm Twisted (35mm Mesh / 24-Ply - Light / Anti-Bird)</option>
            <option value="braided_2_0mm_45mm" <?= $curTopNet === 'braided_2_0mm_45mm' ? 'selected' : '' ?>>2.0mm Braided (45mm Mesh - Medium Protection)</option>
            <option value="braided_2_5mm_45mm" <?= $curTopNet === 'braided_2_5mm_45mm' ? 'selected' : '' ?>>2.5mm Braided (45mm Mesh - Heavy Overhead Net)</option>
            <option value="braided_2_5mm_40mm" <?= $curTopNet === 'braided_2_5mm_40mm' ? 'selected' : '' ?>>2.5mm Braided (40mm Mesh - Heavy Overhead)</option>
            <option value="braided_2_5mm_35mm" <?= $curTopNet === 'braided_2_5mm_35mm' ? 'selected' : '' ?>>2.5mm Braided (35mm Mesh - Heavy Overhead)</option>
          </optgroup>
          <optgroup label="Uniform Top Net (Same as Side Net)">
            <option value="same_as_side" <?= in_array($curTopNet, ['same_as_side', 'all_sides_same']) ? 'selected' : '' ?>>🎯 Same as Side Net (Uniform 2.5mm Braided All Sides & Top)</option>
          </optgroup>
          <?php if (false): ?>
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
        <label class="form-label text-dark fw-bold"><i class="fa fa-door-open text-danger me-1"></i>Player &amp; Equip. Gates</label>
        <select id="tk_gate_count" name="tk_gate_count" class="form-select form-select-sm fw-semibold" onchange="onGateCountDropdownChange()">
          <option value="0" <?= $curGateCount === 0 ? 'selected' : '' ?>>🚫 0 Gates (Open Bowler Front — Practice Nets)</option>
          <option value="1" <?= $curGateCount === 1 ? 'selected' : '' ?>>🚪 1 Gate (Single Main Door)</option>
          <option value="2" <?= $curGateCount === 2 ? 'selected' : '' ?>>🚪 2 Gates (Dual Sideline Access)</option>
          <option value="3" <?= in_array($curGateCount, [3, null, '']) ? 'selected' : '' ?>>🚪 3 Gates (★ Recommended Multi-Sport: Front + Inter-Zone + Sideline)</option>
          <option value="4" <?= $curGateCount === 4 ? 'selected' : '' ?>>🚪 4 Gates (Quad Access: Front + 2 Inter-Zone + Sideline)</option>
          <option value="5" <?= $curGateCount === 5 ? 'selected' : '' ?>>🚪 5 Gates (Full Arena &amp; Inter-Zone Access)</option>
          <option value="6" <?= $curGateCount === 6 ? 'selected' : '' ?>>🚪 6 Gates (Complete Stadium Access)</option>
        </select>
        <div class="text-muted" style="font-size:7pt;">Length sidelines, width ends &amp; inter-zone doors (0 to 6)</div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-dark fw-bold"><i class="fa fa-pencil text-primary me-1"></i>Custom Target Rate (Optional)</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text fw-bold bg-white text-danger">₹</span>
          <input type="number" id="tk_custom_rate" name="tk_custom_rate" class="form-control fw-bold text-end" placeholder="Leave blank for package rate" step="any" min="0" value="<?= !empty($d['turnkey_config']['custom_rate']) ? e($d['turnkey_config']['custom_rate']) : '' ?>" oninput="updateTurnkeyCalculations(false)" onchange="updateTurnkeyCalculations(true)">
          <button type="button" class="btn btn-outline-secondary px-2" onclick="clearCustomRate()" title="Clear Custom Target Rate"><i class="fa fa-times text-danger"></i></button>
          <span class="input-group-text fw-bold bg-light">/SFT</span>
        </div>
        <div class="text-muted" style="font-size:7pt;">Directly scales all BOQ rates proportionally (leave blank for dynamic package rate)</div>
      </div>
    </div>

    <!-- Interactive Gate Placement & Sizing Suite -->
    <div id="gateManagerCard" class="card border-warning mb-3 shadow-sm" style="background:#fffbeb;border-radius:8px;">
      <div class="card-header bg-warning bg-opacity-25 py-2 d-flex justify-content-between align-items-center">
        <div class="fw-bold text-dark" style="font-size:9pt;">
          <i class="fa fa-door-open text-warning me-1"></i>🚪 Interactive Gate Placement &amp; Sizing Suite (Length, Width &amp; Inter-Zone Access)
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-xs btn-primary py-0 px-2 fw-bold" onclick="addCustomGateRow()" style="font-size:7.5pt;">
            <i class="fa fa-plus me-1"></i>Add Gate (+)
          </button>
        </div>
      </div>
      <div class="card-body p-2">
        <div id="gateRowsContainer" class="d-flex flex-column gap-2">
          <!-- Dynamic gate rows populated by JS -->
        </div>
        <div class="text-muted mt-2 d-flex align-items-center gap-1" style="font-size:7.5pt;">
          <i class="fa fa-info-circle text-primary"></i>
          <span>Configure doors anywhere: <b>Front/Back Widths</b>, <b>Left/Right Lengths</b>, or <b>Internal Inter-Zone Walkways</b> (Cricket ↔ Badminton &amp; Badminton ↔ Lounge).</span>
        </div>
      </div>
    </div>

    <!-- Row 5: Turnkey Quality Package Selection -->
    <?php
      $is120x60 = (($d['turnkey_config']['sport_type'] ?? '') === 'outdoor_multi_sports_120x60');
      $curPkg = $d['turnkey_config']['package'] ?? 'commercial';
      $initEssRate = $is120x60 ? '₹266.58/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹314.56</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' . ($curPkg === 'essential' ? ' ★' : '') . '</span>' : '₹250 / SFT';
      $initComRate = $is120x60 ? '₹302.93/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹357.46</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' . ($curPkg === 'commercial' ? ' ★' : '') . '</span>' : '₹300 / SFT ★';
      $initPremRate = $is120x60 ? '₹360.49/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹425.38</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' . ($curPkg === 'premium_pro' ? ' ★' : '') . '</span>' : '₹350 / SFT';
    ?>
    <div class="row g-2 mb-3">
      <div class="col-md-12">
        <label class="form-label text-dark fw-bold"><i class="fa fa-layer-group text-primary me-1"></i>Select Turnkey Quality Package</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="tk_package" id="tk_pkg_essential" value="essential" <?= ($d['turnkey_config']['package'] ?? '') === 'essential' ? 'checked' : '' ?> onchange="onTurnkeyPackageChange()">
          <label class="btn btn-outline-success btn-sm py-1" for="tk_pkg_essential">
            <div class="fw-bold" style="font-size:8pt;">🟢 Essential Quality Package</div>
            <div id="disp_pkg_rate_essential" style="font-size:.75rem;"><?= $initEssRate ?></div>
          </label>
          <input type="radio" class="btn-check" name="tk_package" id="tk_pkg_commercial" value="commercial" <?= ($d['turnkey_config']['package'] ?? 'commercial') === 'commercial' ? 'checked' : '' ?> onchange="onTurnkeyPackageChange()">
          <label class="btn btn-outline-primary btn-sm py-1" for="tk_pkg_commercial">
            <div class="fw-bold" style="font-size:8pt;">🔵 Commercial Standard Package ★</div>
            <div id="disp_pkg_rate_commercial" style="font-size:.75rem;"><?= $initComRate ?></div>
          </label>
          <input type="radio" class="btn-check" name="tk_package" id="tk_pkg_premium" value="premium_pro" <?= ($d['turnkey_config']['package'] ?? '') === 'premium_pro' ? 'checked' : '' ?> onchange="onTurnkeyPackageChange()">
          <label class="btn btn-outline-danger btn-sm py-1" for="tk_pkg_premium">
            <div class="fw-bold" style="font-size:8pt;">🔴 Premium Pro Club Package</div>
            <div id="disp_pkg_rate_premium" style="font-size:.75rem;"><?= $initPremRate ?></div>
          </label>
        </div>
      </div>
    </div>

    <!-- Turf Roll & Pitch Specification Info -->
    <div class="p-2 mb-2 bg-light border rounded d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size:7.5pt;color:#334155;">
      <div><i class="fa fa-circle-info text-primary me-1"></i><strong>Turf Roll Standard:</strong> 2.0M × 25.0M (6.5 FT × 83 FT = 540 SFT per roll) &bull; <strong>Cricket Pitch Pad:</strong> 6.5 FT (2 MTR) Width</div>
      <div class="text-success fw-bold"><i class="fa fa-check-double me-1"></i>All rates, quantities & items can be edited, added or deleted with instant live update!</div>
    </div>

    <!-- LIVE 3D & 2D ARCHITECTURAL CAD VISUALIZER PANEL -->
    <div class="card shadow-sm border-0 mb-3 overflow-hidden" id="turnkeyVisualizerBox" style="border-radius:10px; background:#0f172a;">
      <!-- Header with View Tabs -->
      <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom border-secondary" style="background:rgba(15, 23, 42, 0.98);">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-danger fw-bold"><i class="fa fa-cube me-1"></i>LIVE VISUALIZER</span>
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-sm btn-primary active px-3" id="btnView3DEdit" onclick="switchVisualizerTab('3d')">
              <i class="fa fa-cube me-1"></i>3D Model
            </button>
            <button type="button" class="btn btn-sm btn-outline-light px-3" id="btnViewFloorEdit" onclick="switchVisualizerTab('floor')">
              <i class="fa fa-draw-polygon me-1"></i>2D Floor Plan
            </button>
            <button type="button" class="btn btn-sm btn-outline-light px-3" id="btnViewSideEdit" onclick="switchVisualizerTab('side')">
              <i class="fa fa-ruler-combined me-1"></i>2D Side Elevation
            </button>
            <button type="button" class="btn btn-sm btn-outline-light px-3" id="btnViewCrossEdit" onclick="switchVisualizerTab('cross')">
              <i class="fa fa-layer-group me-1"></i>2D Cross Elevation
            </button>
          </div>
        </div>
        <div class="d-flex align-items-center gap-1">
          <button type="button" class="btn btn-xs btn-warning text-dark fw-bold py-1 px-2 me-1" id="btnToggleRoofSheets" onclick="toggleRoofSheetsVisualizer()" title="Toggle Roof Sheets (Look Inside)"><i class="fa fa-warehouse me-1"></i>Roof: <span id="roofSheetsStatus">ON</span></button>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-xs btn-info text-dark fw-bold py-1 px-2" id="btnCamFront" onclick="if(window.setCourtCameraAngle) window.setCourtCameraAngle('front');" title="Front Entrance 3/4 View"><i class="fa fa-door-open me-1"></i>Front View</button>
            <button type="button" class="btn btn-xs btn-outline-light py-1 px-2" id="btnCamSide" onclick="if(window.setCourtCameraAngle) window.setCourtCameraAngle('side');" title="Side Elevation View"><i class="fa fa-arrows-left-right me-1"></i>Side</button>
            <button type="button" class="btn btn-xs btn-outline-light py-1 px-2" id="btnCamTop" onclick="if(window.setCourtCameraAngle) window.setCourtCameraAngle('top');" title="Top Bird's Eye View"><i class="fa fa-arrows-up-down me-1"></i>Top</button>
            <button type="button" class="btn btn-xs btn-outline-light py-1 px-2" id="btnCamBack" onclick="if(window.setCourtCameraAngle) window.setCourtCameraAngle('back');" title="Back View"><i class="fa fa-arrow-left me-1"></i>Back</button>
          </div>
          <button type="button" class="btn btn-xs btn-outline-light py-1 px-2 ms-1" onclick="refreshLiveCourtViewer()" title="Reload 3D & 2D Views">
            <i class="fa fa-rotate"></i>
          </button>
        </div>
      </div>

      <!-- Stage Viewport (Height 380px) -->
      <div style="position:relative; width:100%; height:380px; background:#0b1120;">
        <!-- 3D WebGL Canvas -->
        <div id="turnkey3DContainerEdit" style="width:100%; height:100%;"></div>

        <!-- 2D Floor Plan SVG -->
        <div id="turnkeyFloorPlanSvgEdit" style="display:none; width:100%; height:100%; background:#ffffff; overflow:auto; text-align:center;"></div>

        <!-- 2D Longitudinal Side Elevation SVG -->
        <div id="turnkeySideElevationSvgEdit" style="display:none; width:100%; height:100%; background:#ffffff; overflow:auto; text-align:center;"></div>

        <!-- 2D Cross Section Elevation SVG -->
        <div id="turnkeyCrossElevationSvgEdit" style="display:none; width:100%; height:100%; background:#ffffff; overflow:auto; text-align:center;"></div>
      </div>

      <!-- Live Status & Metric Strip -->
      <div class="px-3 py-2 d-flex flex-wrap align-items-center justify-content-between text-white" style="background:#1e293b; font-size:7.8pt;">
        <div class="d-flex align-items-center gap-3">
          <span><i class="fa fa-arrows-alt me-1 text-info"></i><strong id="vDimText">90 × 60 × 24 FT</strong></span>
          <span><i class="fa fa-vector-square me-1 text-success"></i><strong id="vAreaText">5,400 SFT</strong></span>
          <span><i class="fa fa-columns me-1 text-primary"></i><strong id="vPolesText">20 Columns</strong></span>
          <span><i class="fa fa-bars me-1 text-warning"></i><strong id="vTiersText">4 Tiers</strong></span>
          <span><i class="fa fa-shield me-1 text-success"></i><strong id="vEnclosureText">3 Sides + Roof Enclosed</strong></span>
        </div>
        <div>
          <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold" id="vSyncBadge"><i class="fa fa-check-circle me-1"></i>Live Synchronized</span>
        </div>
      </div>
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

  <!-- ── MULTI-BLOCK / MULTI-OPTION ESTIMATION BUILDER PANEL ────────────────────── -->
  <div id="multiBlockPanel" class="form-section" style="<?= ($estSubtype !== 'multi_block') ? 'display:none;' : '' ?>">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h5 class="mb-0"><i class="fa fa-cubes text-info me-2"></i>Multi-Block / Multi-Option Estimations</h5>
        <small class="text-muted">Create separate estimation boxes with independent quantities, specifications, and Grand Totals</small>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" onclick="addNewBlock()">
          <i class="fa fa-plus-circle me-1"></i>Add New Block
        </button>
      </div>
    </div>

    <div class="alert alert-info py-2 px-3 small mb-3 border-info-subtle">
      <i class="fa fa-lightbulb me-1 text-warning"></i>
      <strong>How Multi-Block Works:</strong> Each block below generates its own independent table box with its own <strong>Subtotal, GST, and Grand Total</strong> (like in your quotation PDF). Click <strong>"📋 Duplicate Block"</strong> to instantly clone a block and quickly adjust quantities or thickness!
    </div>

    <!-- Container for dynamic blocks -->
    <div id="multiBlocksContainer"></div>

    <!-- Hidden input to send serialized blocks JSON -->
    <input type="hidden" name="blocks_json" id="blocksJsonInput" value="<?= e(json_encode($d['blocks'] ?? [])) ?>">

    <!-- Bottom summary & Add Block Bar -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 bg-light rounded border mt-3">
      <button type="button" class="btn btn-outline-success fw-bold" onclick="addNewBlock()">
        <i class="fa fa-plus-circle me-1"></i>Add Another Block
      </button>
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="chkShowCombinedTotal" <?= (!isset($d['show_combined_total']) || !empty($d['show_combined_total'])) ? 'checked' : '' ?> onchange="toggleShowCombinedTotal(this.checked)">
        <label class="form-check-label fw-semibold small" for="chkShowCombinedTotal">Show Combined Project Grand Total Summary on Document</label>
        <input type="hidden" name="show_combined_total" id="showCombinedTotalInput" value="<?= (!isset($d['show_combined_total']) || !empty($d['show_combined_total'])) ? '1' : '0' ?>">
      </div>
    </div>
  </div>

  <!-- ── NORMAL SINGLE-TABLE ITEMS SECTION ──────────────────────── -->
  <div id="normalItemsSection" style="<?= ($estSubtype === 'multi_block') ? 'display:none;' : '' ?>">
  <!-- Items -->
  <div class="form-section">
    <h5><i class="fa fa-list"></i> Items / Products</h5>
    <div class="d-md-none table-swipe-hint"><i class="fa fa-arrows-left-right text-primary me-1"></i>Swipe table horizontally to edit Rate, GST% and Amount</div>
    <div class="table-responsive">
      <table class="table items-table border" id="itemsTable" style="min-width:1020px;">
        <thead>
          <tr>
            <th style="width:36%;min-width:300px;">Description</th>
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
            <td><textarea name="item_desc[<?= $i ?>]" class="form-control form-control-sm item-desc-field" rows="3" placeholder="Description of goods/service" required oninput="autoResizeItemDesc(this)"><?= e($item['description']) ?></textarea></td>
            <td><input type="text" name="item_hsn[<?= $i ?>]" class="form-control form-control-sm item-hsn" value="<?= e($item['hsn']) ?>" placeholder="HSN"></td>
            <td><input type="number" name="item_qty[<?= $i ?>]" class="form-control form-control-sm item-qty" oninput="calcTotals()" onchange="calcTotals()" value="<?= e($item['qty']) ?>" step="0.01" min="0" placeholder="0"></td>
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
            <td><input type="number" name="item_rate[<?= $i ?>]" class="form-control form-control-sm item-rate" oninput="calcTotals()" onchange="calcTotals()" value="<?= e($item['rate']) ?>" step="0.01" min="0" placeholder="0.00"></td>
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
  </div><!-- /normalItemsSection -->

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
    $showScope  = true;
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
          <td class="text-end fw-semibold" id="disp_subtotal">₹<?= fmtAmount($d['subtotal'] ?? 0) ?></td>
          <input type="hidden" name="subtotal" id="h_subtotal" value="<?= e($d['subtotal'] ?? 0) ?>">
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
          <td class="text-end text-danger" id="disp_discount_amt"><?= ((float)($d['discount_amount'] ?? 0) > 0) ? '−₹' . fmtAmount($d['discount_amount']) : '₹0.00' ?></td>
        </tr>
        <tr class="table-info">
          <td class="fw-semibold">Taxable Value</td>
          <td class="text-end fw-bold" id="disp_taxable">₹<?= fmtAmount($d['taxable_value'] ?? $d['subtotal'] ?? 0) ?></td>
          <input type="hidden" name="taxable_value" id="h_taxable" value="<?= e($d['taxable_value'] ?? $d['subtotal'] ?? 0) ?>">
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
          <td class="text-end py-2 px-3 fs-6" id="disp_grand">₹<?= fmtAmount($d['grand_total'] ?? 0) ?></td>
          <input type="hidden" name="grand_total" id="h_grand" value="<?= e($d['grand_total'] ?? 0) ?>">
        </tr>
      </tbody>
    </table>

    <!-- Amount in Words -->
    <div style="margin-bottom:.75rem;">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#c0392b;margin-bottom:3px;"><i class="fa fa-spell-check me-1"></i>Amount in Words</div>
      <div id="amountWords" style="font-style:italic;font-size:.85rem;color:#555;background:#f8f9fc;padding:.5rem .75rem;border-radius:6px;border-left:3px solid var(--red);"><?= !empty($d['amount_in_words']) ? e($d['amount_in_words']) : 'Will appear automatically…' ?></div>
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


    <!-- E-Way Bill & Logistics Section (Optional for Invoices & Delivery Challans) -->
    <div id="ewayBillCard" class="card border-0 shadow-sm mb-3" style="display:<?= in_array($type ?? 'estimation', ['tax_invoice', 'bill', 'proforma']) ? 'block' : 'none' ?>;background:#f8fafc;border:1px solid #cbd5e1 !important;border-radius:10px;">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.92rem;">
            <i class="fa fa-truck text-primary me-1"></i> E-Way Bill &amp; Transportation Logistics (GST Rule 138)
          </label>
          <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" name="eway_enabled" id="eway_enabled" value="1" <?= (!empty($d['eway_bill']['enabled']) || !empty($d['eway_bill']['eway_bill_no'])) ? 'checked' : '' ?> onchange="toggleEwayBillBox()" style="cursor:pointer;">
            <label class="form-check-label small fw-bold text-success" for="eway_enabled" style="cursor:pointer;">
              <span id="lblEwayToggle"><?= (!empty($d['eway_bill']['enabled']) || !empty($d['eway_bill']['eway_bill_no'])) ? 'E-Way Bill Active' : 'Enable E-Way Bill Details' ?></span>
            </label>
          </div>
        </div>

        <?php 
          $ew = $d['eway_bill'] ?? [];
          $isEwayActive = (!empty($ew['enabled']) || !empty($ew['eway_bill_no']));
        ?>
        <div id="ewayBillDetailsBox" style="display:<?= $isEwayActive ? 'block' : 'none' ?>;background:#ffffff;border:1.5px solid #0284c7;border-radius:8px;padding:12px;margin-top:8px;">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-dark mb-1">12-Digit E-Way Bill Number</label>
              <input type="text" name="eway_bill_no" id="eway_bill_no" class="form-control form-control-sm fw-bold" value="<?= e($ew['eway_bill_no'] ?? '') ?>" placeholder="e.g. 2418 9032 1145" maxlength="16" style="letter-spacing:1px;font-family:monospace;">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">E-Way Bill Issue Date</label>
              <input type="date" name="eway_bill_date" id="eway_bill_date" class="form-control form-control-sm" value="<?= e($ew['eway_bill_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">Valid Upto Date</label>
              <input type="date" name="eway_valid_upto" id="eway_valid_upto" class="form-control form-control-sm" value="<?= e($ew['valid_upto'] ?? date('Y-m-d', strtotime('+3 days'))) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-dark mb-1">Transport Vehicle Number</label>
              <input type="text" name="vehicle_no" id="vehicle_no" class="form-control form-control-sm fw-bold text-uppercase" value="<?= e($ew['vehicle_no'] ?? '') ?>" placeholder="e.g. AP39TX1234 / TS08UB5678" maxlength="15">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">Transporter / Logistics Name</label>
              <input type="text" name="transporter_name" id="transporter_name" class="form-control form-control-sm" value="<?= e($ew['transporter_name'] ?? '') ?>" placeholder="e.g. VRL Logistics / Navata / Safechem">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-dark mb-1">LR / Bilty / Consignment Doc No.</label>
              <input type="text" name="transporter_doc_no" id="transporter_doc_no" class="form-control form-control-sm" value="<?= e($ew['transporter_doc_no'] ?? '') ?>" placeholder="e.g. LR-984321">
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
            <small class="text-muted" style="font-size:0.75rem;"><i class="fa fa-shield-alt text-success me-1"></i>Official GST compliance details automatically print on the invoice header.</small>
            <a href="https://ewaybillgst.gov.in" target="_blank" class="btn btn-sm btn-outline-info" style="font-size:0.72rem;padding:2px 8px;">
              <i class="fa fa-external-link-alt me-1"></i>Govt E-Way Bill Portal &rarr;
            </a>
          </div>
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
      <input type="hidden" name="form_loaded_at" id="formLoadedAt" value="<?= time() ?>">

      <button type="submit" class="btn text-white fw-bold" style="background:var(--red);">
        <i class="fa fa-floppy-disk me-2"></i><?= $editId ? 'Update Document' : 'Save & Finalize Document' ?>
      </button>
      <?php if ($editId): ?>
      <button type="submit" name="redirect_to_view" value="1" class="btn btn-outline-secondary">
        <i class="fa fa-eye me-2"></i>Save & View Document
      </button>
      <?php endif; ?>
      <a href="<?= e($docsReturnUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </div>

</div><!-- /col-lg-4 -->
</div><!-- /row -->
</form>

<!-- Mobile & Tablet Sticky Save & Action Footer -->
<div class="mobile-save-bar d-lg-none" id="mobileSaveBar">
  <div class="mobile-save-inner">
    <div class="mobile-save-total">
      <span class="lbl">GRAND TOTAL</span>
      <span class="amt" id="mobileGrandTotalDisplay">₹<?= fmtAmount($d['grand_total'] ?? 0) ?></span>
    </div>
    <div class="mobile-save-actions">
      <button type="button" class="btn btn-sm btn-outline-light" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Top / Details">
        <i class="fa fa-arrow-up"></i>
      </button>
      <button type="button" class="btn btn-sm btn-outline-light" onclick="document.getElementById('itemsTable')?.scrollIntoView({behavior:'smooth'})" title="Items">
        <i class="fa fa-list"></i>
      </button>
      <button type="submit" form="docForm" class="btn btn-sm fw-bold text-white shadow" style="background:var(--red); font-size:0.85rem; padding:0.4rem 0.9rem;">
        <i class="fa fa-floppy-disk me-1"></i><?= $editId ? 'Update' : 'Save' ?>
      </button>
    </div>
  </div>
</div>

<script>
const isExistingDocument = <?= !empty($editDoc) ? 'true' : 'false' ?>;
const existingDocId = <?= json_encode($editDoc['id'] ?? '') ?>;
const existingDocType = <?= json_encode($type) ?>;
const existingItemCount = <?= count($items) ?>;
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

  // Auto-fill Shipping / Site Delivery Address if available
  const sAddr  = opt.dataset.ship_address || '';
  const sName  = opt.dataset.ship_name    || '';
  const sPhone = opt.dataset.ship_phone   || '';
  const sState = opt.dataset.ship_state   || '';
  if (sAddr || sName) {
    if (document.getElementById('st_name'))    document.getElementById('st_name').value = sName || opt.dataset.name || '';
    if (document.getElementById('st_address')) document.getElementById('st_address').value = sAddr;
    if (document.getElementById('st_phone'))   document.getElementById('st_phone').value = sPhone || opt.dataset.phone || '';
    if (document.getElementById('st_state'))   document.getElementById('st_state').value = sState || opt.dataset.state || '';
    const sToggle = document.getElementById('show_shipping_address');
    if (sToggle) {
      sToggle.checked = true;
      if (typeof toggleShipAddress === 'function') toggleShipAddress();
    }
  }

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


// Auto-resize description textareas so all matter is clearly visible without internal scrollbars
function autoResizeItemDesc(el) {
  if (!el) return;
  el.style.height = 'auto';
  var scrollH = el.scrollHeight;
  var targetH = Math.max(84, scrollH + 4);
  el.style.height = targetH + 'px';
}

function autoResizeAllItemDescs() {
  document.querySelectorAll('textarea[name^="item_desc"], .item-desc-field, #itemsTbody textarea').forEach(function(el) {
    autoResizeItemDesc(el);
  });
}

document.addEventListener('DOMContentLoaded', function() {
  autoResizeAllItemDescs();
  setTimeout(autoResizeAllItemDescs, 100);
  setTimeout(autoResizeAllItemDescs, 400);
  setTimeout(autoResizeAllItemDescs, 1000);
});

window.addEventListener('load', function() {
  autoResizeAllItemDescs();
});

document.addEventListener('input', function(e) {
  if (e.target && (e.target.name && e.target.name.startsWith('item_desc') || e.target.classList.contains('item-desc-field'))) {
    autoResizeItemDesc(e.target);
  }
});

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
  const sym = getSelectedCurrencySymbol();
  return sym + parseFloat(n || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function fmtNumRaw(n) {
  return parseFloat(n || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}


function getSelectedCurrencySymbol() {
  const sel = document.getElementById('doc_currency');
  if (!sel) return '₹';
  const opt = sel.options[sel.selectedIndex];
  return opt ? (opt.getAttribute('data-symbol') || '₹') : '₹';
}

function onCurrencyChange(curr) {
  const sym = getSelectedCurrencySymbol();
  document.querySelectorAll('.curr-sym').forEach(el => el.textContent = sym);
  calcTotals();
}

function showCustomToast(msg, type = 'danger') {
  let toastContainer = document.getElementById('gcmToastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'gcmToastContainer';
    toastContainer.style.cssText = 'position:fixed;top:24px;right:24px;z-index:999999;display:flex;flex-direction:column;gap:12px;max-width:440px;pointer-events:none;';
    document.body.appendChild(toastContainer);
  }

  const icon = (type === 'success') ? 'fa-check-circle' : (type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-exclamation');
  const borderCol = (type === 'success') ? '#22c55e' : (type === 'warning' ? '#f59e0b' : '#ef4444');
  const bgGrad = (type === 'success') ? 'linear-gradient(135deg, #064e3b, #022c22)' : (type === 'warning' ? 'linear-gradient(135deg, #78350f, #451a03)' : 'linear-gradient(135deg, #1e1b4b, #0f172a)');

  const toast = document.createElement('div');
  toast.style.cssText = `
    background: ${bgGrad};
    color: #fff;
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 14px 38px rgba(0,0,0,0.55), 0 0 0 1.5px ${borderCol}99;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.90rem;
    font-weight: 600;
    pointer-events: auto;
    opacity: 0;
    transform: translateY(-20px) scale(0.96);
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    backdrop-filter: blur(14px);
  `;

  toast.innerHTML = `
    <div style="font-size:1.35rem;color:${borderCol};display:flex;align-items:center;">
      <i class="fa ${icon}"></i>
    </div>
    <div style="flex:1;line-height:1.4;">${msg}</div>
    <button type="button" style="background:transparent;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;padding:0 4px;line-height:1;" onclick="this.parentElement.remove()">
      &times;
    </button>
  `;

  toastContainer.appendChild(toast);
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0) scale(1)';
  });

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-15px) scale(0.96)';
    setTimeout(() => toast.remove(), 350);
  }, 4500);
}

function cleanPhoneString(val) {
  if (!val) return '';
  // Remove spaces, hyphens, brackets, dots
  let cleaned = String(val).replace(/[\s\-\(\)\.]/g, '');
  // If user pasted number with +, extract country prefix if matches
  if (cleaned.startsWith('+')) {
    const prefSel = document.getElementById('phone_prefix');
    if (prefSel) {
      for (let i = 0; i < prefSel.options.length; i++) {
        const p = prefSel.options[i].value;
        if (p && cleaned.startsWith(p)) {
          prefSel.selectedIndex = i;
          updatePhonePrefix(p);
          cleaned = cleaned.substring(p.length);
          break;
        }
      }
    }
  }
  return cleaned;
}

function handlePhoneInput(inputEl, type) {
  const cleaned = cleanPhoneString(inputEl.value);
  inputEl.value = cleaned;
  if (type === 'mobile' && document.getElementById('same_as_mobile')?.checked) {
    const wa = document.getElementById('bt_whatsapp');
    if (wa) wa.value = cleaned;
  }
}

function handlePhonePaste(e, type) {
  e.preventDefault();
  const pasteData = (e.clipboardData || window.clipboardData).getData('text');
  const cleaned = cleanPhoneString(pasteData);
  const target = (type === 'mobile') ? document.getElementById('bt_phone') : document.getElementById('bt_whatsapp');
  if (target) {
    target.value = cleaned;
    if (type === 'mobile' && document.getElementById('same_as_mobile')?.checked) {
      const wa = document.getElementById('bt_whatsapp');
      if (wa) wa.value = cleaned;
    }
  }
}

function updatePhonePrefix(prefix) {
  const waDisp = document.getElementById('wa_prefix_disp');
  if (waDisp) {
    waDisp.innerHTML = '<i class="fab fa-whatsapp"></i>&nbsp;' + prefix;
  }
}

function onCountryChange(country, type) {
  const sel = document.getElementById(type === 'bill' ? 'bt_country' : 'st_country');
  const opt = sel ? sel.options[sel.selectedIndex] : null;
  if (!opt) return;
  const prefix = opt.getAttribute('data-prefix') || '+91';
  const taxLabel = opt.getAttribute('data-tax') || 'TAX ID / TRN / VAT NO';
  const curr = opt.getAttribute('data-currency') || 'INR';
  const isIndia = (country === 'India');

  if (type === 'bill') {
    // 1. Update Dial Code Dropdown & WhatsApp Badge
    const prefSel = document.getElementById('phone_prefix');
    if (prefSel) {
      prefSel.value = prefix;
    }
    updatePhonePrefix(prefix);

    // 2. Update Tax Label
    const taxLblEl = document.getElementById('lbl_tax_id');
    if (taxLblEl) {
      taxLblEl.textContent = isIndia ? 'GSTIN' : taxLabel;
    }

    // 3. Update Currency Dropdown & Trigger Calculation
    const currSel = document.getElementById('doc_currency');
    if (currSel && curr) {
      currSel.value = curr;
      onCurrencyChange(curr);
    }

    // 4. International Tax Handling: Under Indian law, exports are Zero-Rated (0% GST)
    if (!isIndia) {
      const gstNoRadio = document.getElementById('is_gst_no');
      if (gstNoRadio && !gstNoRadio.checked) {
        gstNoRadio.checked = true;
        toggleGST();
      }
      // Set GST % in item rows to 0%
      document.querySelectorAll('.item-gst-rate').forEach(inp => {
        inp.value = 0;
      });
      calcTotals();
    }

    // 5. Update Document Category / GST Preview Label
    const docTypeEl = document.getElementById('docTypePreview');
    if (docTypeEl) {
      if (!isIndia) {
        docTypeEl.innerHTML = 'INTERNATIONAL ' + (document.querySelector('input[name="type"]:checked')?.value.toUpperCase() || 'ESTIMATION');
      } else {
        refreshDocLabel();
      }
    }
  }
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
  const mDisp = document.getElementById('mobileGrandTotalDisplay');
  if (mDisp) mDisp.textContent = '₹' + fmtNum(grand);

  // Live Turnkey Synchronized Totals & Package Rates (100% Matching Grand Total)
  const isTurnkeySubtype = (document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court');
  if (isTurnkeySubtype) {
    const tL = parseFloat(document.getElementById('tk_length')?.value) || 90;
    const tW = parseFloat(document.getElementById('tk_width')?.value) || 60;
    const sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
    let tArea = Math.round(tL * tW);
    if (sport === 'cricket_circular') {
      const r = tL / 2;
      tArea = Math.round(Math.PI * r * r);
    } else if (sport === 'cricket_oval') {
      const a = tL / 2;
      const b = tW / 2;
      tArea = Math.round(Math.PI * a * b);
    }
    tArea = Math.max(1, tArea);

    const rExcl = (subtotal / tArea).toFixed(2);
    const rIncl = (grand / tArea).toFixed(2);

    const elTotal = document.getElementById('disp_tk_total');
    const elRate  = document.getElementById('disp_tk_rate');
    if (elTotal) {
      elTotal.innerHTML = '₹' + fmtNum(subtotal) + ' <span class="fs-6 text-muted fw-normal">(Excl) &bull; ₹' + fmtNum(grand) + ' (Incl GST)</span>';
    }
    if (elRate) {
      elRate.innerHTML = '<strong>₹' + rExcl + ' / SFT (Excl. GST)</strong> &bull; <span class="text-primary-emphasis fw-bold">₹' + rIncl + ' / SFT (Incl. GST)</span>';
    }

    if (window.cachedTurnkeyPackages) {
      updateTurnkeyPackageButtonLabels(window.cachedTurnkeyPackages);
    } else {
      const pkgRadio = document.querySelector('input[name="tk_package"]:checked');
      const curPkg = pkgRadio ? pkgRadio.value : 'commercial';

      const elPkgEss  = document.getElementById('disp_pkg_rate_essential');
      const elPkgCom  = document.getElementById('disp_pkg_rate_commercial');
      const elPkgPrem = document.getElementById('disp_pkg_rate_premium');

      const rExclNum = parseFloat(rExcl);
      const rInclNum = parseFloat(rIncl);

      const essRatio = (curPkg === 'essential') ? 1.0 : ((curPkg === 'commercial') ? 0.88 : 0.74);
      const comRatio = (curPkg === 'commercial') ? 1.0 : ((curPkg === 'essential') ? 1.136 : 0.84);
      const premRatio = (curPkg === 'premium_pro') ? 1.0 : ((curPkg === 'commercial') ? 1.19 : 1.35);

      const essExcl = (rExclNum * essRatio).toFixed(2);
      const essIncl = (rInclNum * essRatio).toFixed(2);
      const comExcl = (rExclNum * comRatio).toFixed(2);
      const comIncl = (rInclNum * comRatio).toFixed(2);
      const premExcl = (rExclNum * premRatio).toFixed(2);
      const premIncl = (rInclNum * premRatio).toFixed(2);

      if (elPkgEss) elPkgEss.innerHTML = '₹' + essExcl + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + essIncl + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' + (curPkg === 'essential' ? ' ★' : '') + '</span>';
      if (elPkgCom) elPkgCom.innerHTML = '₹' + comExcl + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + comIncl + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' + (curPkg === 'commercial' ? ' ★' : '') + '</span>';
      if (elPkgPrem) elPkgPrem.innerHTML = '₹' + premExcl + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + premIncl + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' + (curPkg === 'premium_pro' ? ' ★' : '') + '</span>';
    }
  }

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
  gst:    { estimation:'GST ESTIMATION', proforma_invoice:'PROFORMA INVOICE', tax_invoice:'TAX INVOICE', bill:'GST BILL' },
  nongst: { estimation:'ESTIMATION',     proforma_invoice:'PROFORMA INVOICE', tax_invoice:'INVOICE',     bill:'BILL'     },
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
    <td><textarea name="item_desc[${idx}]" class="form-control form-control-sm item-desc-field" rows="3" placeholder="Description" required oninput="autoResizeItemDesc(this)"></textarea></td>
    <td><input type="text" name="item_hsn[${idx}]" class="form-control form-control-sm item-hsn" placeholder="HSN"></td>
    <td><input type="number" name="item_qty[${idx}]" class="form-control form-control-sm item-qty" oninput="calcTotals()" onchange="calcTotals()" step="0.01" min="0" placeholder="0"></td>
    <td><select name="item_unit[${idx}]" class="form-select form-select-sm item-unit-select">${opts}</select></td>
    <td><input type="number" name="item_rate[${idx}]" class="form-control form-control-sm item-rate" oninput="calcTotals()" onchange="calcTotals()" step="0.01" min="0" placeholder="0.00"></td>
    <td><input type="number" name="item_gst_rate[${idx}]" class="form-control form-control-sm item-gst-rate text-end" oninput="calcTotals()" onchange="calcTotals()" value="${defaultGstRate}" step="0.5" min="0" max="100" list="gstRateList" style="min-width:75px;padding:.3rem .4rem;font-size:0.85rem;" title="GST%"></td>
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
  autoResizeAllItemDescs();
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
    syncLayersWithActiveRows();
    syncEquipmentWithActiveRows();
    if (typeof scheduleAutosave === "function") scheduleAutosave();
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
    if (typeof window.scheduleAutosave === 'function') window.scheduleAutosave();
  }
  if (e.target.name && e.target.name.startsWith('item_desc')) {
    clearTimeout(e.target._specTimer);
    e.target._specTimer = setTimeout(() => autoAddSpecs(e.target), 600);
    if (typeof window.scheduleAutosave === 'function') window.scheduleAutosave();
  }
});

document.getElementById('itemsTbody').addEventListener('change', e => {
  if (e.target.classList.contains('item-qty') || 
      e.target.classList.contains('item-rate') || 
      e.target.classList.contains('item-gst-rate') ||
      (e.target.name && e.target.name.startsWith('item_unit'))) {
    calcTotals();
    if (typeof window.scheduleAutosave === 'function') window.scheduleAutosave();
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
  syncLayersWithActiveRows();
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
    <td><textarea name="item_desc[${idx}]" class="form-control form-control-sm item-desc-field" rows="3" placeholder="Description" required oninput="autoResizeItemDesc(this)">${escapeHtml(item.description||'')}</textarea></td>
    <td><input type="text" name="item_hsn[${idx}]" class="form-control form-control-sm item-hsn" value="${escapeHtml(item.hsn||'')}" placeholder="HSN"></td>
    <td><input type="number" name="item_qty[${idx}]" class="form-control form-control-sm item-qty" oninput="calcTotals()" onchange="calcTotals()" value="${escapeHtml(item.qty||'')}" step="0.01" min="0" placeholder="0"></td>
    <td><select name="item_unit[${idx}]" class="form-select form-select-sm item-unit-select">${opts}</select></td>
    <td><input type="number" name="item_rate[${idx}]" class="form-control form-control-sm item-rate" oninput="calcTotals()" onchange="calcTotals()" value="${escapeHtml(item.rate||'')}" step="0.01" min="0" placeholder="0.00"></td>
    <td><input type="number" name="item_gst_rate[${idx}]" class="form-control form-control-sm item-gst-rate text-end" oninput="calcTotals()" onchange="calcTotals()" value="${escapeHtml(item.gst_rate||'18')}" step="0.5" min="0" max="100" list="gstRateList" style="min-width:75px;padding:.3rem .4rem;font-size:0.85rem;" title="GST%"></td>
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

function detectHsnAndGst(desc) {
  const d = (desc || '').toLowerCase();
  // 1. Structural steel, raw metals, fabrication & erection
  if (/(metal fabrication|welding|structural assembly|fabrication)/i.test(d)) return { code: '9988 71', gst_rate: 18 };
  if (/(beams?|channels?|pillars?|girders?)/i.test(d)) return { code: '7308 90 10', gst_rate: 18 };
  if (/(doors?|windows?|thresholds?|sub-?frames?)/i.test(d)) return { code: '7308 30 00', gst_rate: 18 };
  if (/(truss\s*rods?|bracing|struts?)/i.test(d)) return { code: '7308 90 60', gst_rate: 18 };
  if (/(iron\s*frame|steel\s*frame|fabricated\s*iron|fabricated\s*steel|shed\s*structure)/i.test(d)) return { code: '7308 90 90', gst_rate: 18 };
  if (/(angles?|c-channel|i-beam|heavy\s*section)/i.test(d)) return { code: '7216', gst_rate: 18 };
  if (/(tubes?|pipes?|hollow\s*profile|steel\s*pipe|iron\s*pipe)/i.test(d)) return { code: '7306', gst_rate: 18 };
  if (/(tmt|steel\s*bars?|steel\s*rods?)/i.test(d)) return { code: '7214', gst_rate: 18 };
  if (/(structural\s*erection|erection|works\s*contract)/i.test(d)) return { code: '9954', gst_rate: 18 };
  if (/(pole|column|purlin|gate|steel|iron|frame|structure|truss|shs|ms|gi)/i.test(d)) return { code: '7308', gst_rate: 18 };

  // 2. Industrial Netting, Hardware & Services
  if (/(agriculture\s*net|agro\s*net|shade\s*net|warp\s*knit)/i.test(d)) return { code: '6005 90 00', gst_rate: 5 };
  if (/(safety\s*net|bird\s*net|pigeon\s*net|knotted\s*net|balcony\s*net)/i.test(d)) return { code: '5608 19 00', gst_rate: 5 };
  if (/(fencing|tuflex|plastic\s*mesh|extruded\s*net)/i.test(d)) return { code: '3926 90 99', gst_rate: 18 };
  if (/(ropes?|twines?|cords?)/i.test(d)) return { code: '5607 49 00', gst_rate: 12 };
  if (/(hooks?|eye\s*bolts?|anchors?|fasteners?|hardware)/i.test(d)) return { code: '7318 19 00', gst_rate: 18 };
  if (/(wire\s*ropes?|cables?|ss\s*wire)/i.test(d)) return { code: '7312 10 90', gst_rate: 18 };
  if (/(brackets?|clamps?|fittings?|mountings?)/i.test(d)) return { code: '8302 49 00', gst_rate: 18 };
  if (/(net\s*installation|grill\s*installation|installation\s*labor|rigging)/i.test(d)) return { code: '9987 39', gst_rate: 18 };

  // 3. Sports Equipment, Cricket, Turf & Civil Sub-Bases
  if (/(cricket\s*bats?|leg\s*pads?|protective\s*gear|batting\s*gloves?|helmets?)/i.test(d)) return { code: '9506 99 20', gst_rate: 18 };
  if (/(cricket\s*leather\s*balls?|leather\s*balls?)/i.test(d)) return { code: '9506 69 20', gst_rate: 18 };
  if (/(tennis\s*balls?|rubber\s*balls?|plastic\s*balls?)/i.test(d)) return { code: '9506 69 90', gst_rate: 18 };
  if (/(stumps?|wickets?|bails?|pitch\s*marking)/i.test(d)) return { code: '9506 99 90', gst_rate: 18 };
  if (/(artificial\s*grass|synthetic\s*turf|turf\s*mat|turf\s*roll)/i.test(d)) return { code: '6702 10 90', gst_rate: 18 };
  if (/(pvc\s*flooring|pu\s*flooring|sports\s*vinyl|indoor\s*court\s*mat)/i.test(d)) return { code: '3918 10 90', gst_rate: 18 };
  if (/(acrylic\s*court|acrylic\s*coating|court\s*coating|surfacing\s*compound)/i.test(d)) return { code: '3208 20 10', gst_rate: 18 };
  if (/(rubber\s*shock|epdm|shock\s*pad|gym\s*flooring)/i.test(d)) return { code: '4016 91 00', gst_rate: 18 };
  if (/(bituminous|asphalt|road\s*base)/i.test(d)) return { code: '2715 00 90', gst_rate: 18 };
  if (/(ready-mix|rmc|concrete\s*mix)/i.test(d)) return { code: '3824 50 10', gst_rate: 18 };
  if (/(civil\s*engineering|rcc\s*base|court\s*construction|sub-base\s*construction)/i.test(d)) return { code: '9954 21', gst_rate: 18 };

  // Other general items
  if (/(freight|transport|logistics|carriage|lr\b)/i.test(d)) return { code: '9965', gst_rate: 18 };
  if (/(civil|excavation|sub-base|drainage|labour|labor|installation|erection|fixing|fitting)/i.test(d)) return { code: '9954', gst_rate: 18 };
  if (/(grass|turf|carpet|lawn|matting)/i.test(d)) return { code: '5703', gst_rate: 18 };
  if (/(sand|silica)/i.test(d)) return { code: '2505', gst_rate: 5 };
  if (/(rubber|granule|infill)/i.test(d)) return { code: '4004', gst_rate: 18 };
  if (/(light|flood|lamp|fixture|lux)/i.test(d)) return { code: '9405', gst_rate: 18 };
  if (/(cable|panel|switch|db|earthing|electric)/i.test(d)) return { code: '8537', gst_rate: 18 };
  if (/(goal|marking|pad|stump|crease|accessory|accessories)/i.test(d)) return { code: '9506', gst_rate: 18 };
  if (/(paint|primer|epoxy|coating)/i.test(d)) return { code: '3208', gst_rate: 18 };
  if (/(engineering|consult|supervision|design|inspection)/i.test(d)) return { code: '9983', gst_rate: 18 };
  if (/(custom|stitch|seam|charge)/i.test(d)) return { code: '5608', gst_rate: 5 };
  return { code: '5608', gst_rate: 5 };
}

function detectHsn(desc) {
  return detectHsnAndGst(desc).code;
}
function autoAddSpecs(textarea) {
  const row = textarea.closest('.item-row');
  if (!row) return;
  const uid      = row.dataset.itemUid;
  const idx      = row.dataset.row;
  const desc     = textarea.value;
  const hsnInput = row.querySelector('input[name^="item_hsn"]');
  const gstInput = row.querySelector('input[name^="item_gst_rate"]');
  const det = detectHsnAndGst(desc);
  if (hsnInput && (!hsnInput.value.trim() || hsnInput.value === '5608' || hsnInput.value === '7308')) {
    hsnInput.value = det.code;
  }
  if (gstInput && det.gst_rate) {
    gstInput.value = det.gst_rate;
    if (typeof calcTotals === 'function') calcTotals();
  }
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
  if (e.target.closest('.remove-term')) {
    e.target.closest('.term-row').remove();
    if (typeof scheduleAutosave === 'function') scheduleAutosave();
  }
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
    if (typeof scheduleAutosave === 'function') scheduleAutosave();
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
    const list = document.getElementById('scopeList');
    if (list) list.innerHTML = '';
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
  multi_block: {
    hint: 'Multi-Block / Multi-Option Estimation — Separate independent tables with individual Grand Totals in one document.',
    terms: [
      'Transport charges excluded unless specified',
      '50% Advance payment required to proceed order',
      'Installation charges included as per agreed scope',
      'All other statutory charges included',
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

  // Toggle Multi-Block Panel vs Normal Items Section
  const mbPanel = document.getElementById('multiBlockPanel');
  const normalSec = document.getElementById('normalItemsSection');
  if (mbPanel && normalSec) {
    if (subtype === 'multi_block') {
      mbPanel.style.display = '';
      normalSec.style.display = 'none';
      document.querySelectorAll('#normalItemsSection .item-desc-field').forEach(el => el.removeAttribute('required'));
      if (typeof initMultiBlocksUI === 'function') {
        initMultiBlocksUI();
      }
    } else {
      mbPanel.style.display = 'none';
      normalSec.style.display = '';
      document.querySelectorAll('#normalItemsSection .item-desc-field').forEach(el => el.setAttribute('required', 'required'));
    }
  }

  // Toggle Turnkey Panel
  const tkPanel = document.getElementById('turnkeyPanel');
  if (tkPanel) {
    tkPanel.style.display = (subtype === 'turnkey_court') ? '' : 'none';
    if (subtype === 'turnkey_court') updateTurnkeyCalculations();
  }

  // Update hint
  const hint = document.getElementById('estSubtypeHint');
  if (hint) hint.innerHTML = '<i class="fa fa-circle-info me-1 text-info"></i>' + config.hint;

  // Preserve custom terms if user has added/edited them, otherwise load defaults
  const list = document.getElementById('termsList');
  const existingTermInputs = list.querySelectorAll('input[name="terms[]"]');
  const hasUserCustomTerms = Array.from(existingTermInputs).some(inp => inp.value.trim() !== '');
  if (!hasUserCustomTerms) {
    list.innerHTML = '';
    config.terms.forEach(t => {
      list.insertAdjacentHTML('beforeend',
        `<div class="d-flex align-items-center gap-2 mb-2 term-row">
          <input type="text" name="terms[]" class="form-control form-control-sm" value="${t.replace(/"/g,'&quot;')}">
          <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-term"><i class="fa fa-minus"></i></button>
        </div>`
      );
    });
  }

  // Preserve custom scope items if user has added/edited them
  const scopeListEl = document.getElementById('scopeList');
  const hasUserScope = scopeListEl && scopeListEl.querySelectorAll('.scope-item').length > 0;
  if (!hasUserScope) {
    populateScopeTemplate(subtype);
  }

  // Clear turnkey config if not turnkey_court
  const cfgInp = document.getElementById('turnkeyConfigJson');
  if (cfgInp && subtype !== 'turnkey_court') {
    cfgInp.value = '';
  }

  // Refresh doc type label badge
  refreshDocLabel();

  // Trigger autosave
  if (typeof scheduleAutosave === 'function') {
    scheduleAutosave();
  }
}

// Show/hide sub-type row when doc type changes
document.querySelectorAll('[name=type]').forEach(r => r.addEventListener('change', function() {
  const row = document.getElementById('estSubtypeRow');
  if (row) row.style.display = (this.value === 'estimation' || this.value === 'proforma_invoice') ? '' : 'none';
  refreshDocLabel();
}));

// Init hint on page load
(function() {
  const cur = document.querySelector('[name=estimation_subtype]:checked')?.value || 'normal';
  const hint = document.getElementById('estSubtypeHint');
  const config = EST_SUBTYPE_TERMS[cur];
  if (hint && config) hint.innerHTML = '<i class="fa fa-circle-info me-1 text-info"></i>' + config.hint;
  if (cur === 'multi_block') {
    onEstSubtypeChange('multi_block');
  }
})();

// Init
toggleGST();
refreshDocLabel();
calcTotals();


// ── Turnkey Sports Court Generator Helper Functions ────────────────────────────

// ── Gate Placement & Inter-Zone Access Management ──
<?php
$savedGates = $d['turnkey_config']['gates'] ?? null;
if ($savedGates === null) {
    require_once __DIR__ . '/includes/turnkey_calculator.php';
    $savedGates = getTurnkeyDefaultGates(
        (float)($d['turnkey_config']['length'] ?? 90),
        (float)($d['turnkey_config']['width'] ?? 60),
        (float)($d['turnkey_config']['height'] ?? 25),
        (int)($d['turnkey_config']['gate_count'] ?? 1),
        $d['turnkey_config']['sport_type'] ?? 'box_cricket'
    );
}
?>
let turnkeyGatesList = <?= json_encode($savedGates) ?>;


function serializeFullTurnkeyConfig() {
  const isTurnkey = document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court';
  if (!isTurnkey) return null;

  const L = parseFloat(document.getElementById('tk_length')?.value) || 90;
  const W = parseFloat(document.getElementById('tk_width')?.value)  || 60;
  const H = parseFloat(document.getElementById('tk_height')?.value) || 25;
  const pitchL = parseFloat(document.getElementById('tk_pitch_length')?.value) || 66;
  const sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
  const roof  = document.getElementById('tk_roof_type')?.value  || 'light_cable_top';
  const pkgRadio = document.querySelector('input[name="tk_package"]:checked');
  const pkg   = pkgRadio ? pkgRadio.value : 'commercial';
  const poleStyle = document.getElementById('tk_pole_style')?.value || 'ladder_2line';
  const pipeSize  = document.getElementById('tk_pipe_size')?.value || '40x40';
  const pipeGauge = document.getElementById('tk_pipe_gauge')?.value || '2.0mm';
  const turfType  = document.getElementById('tk_turf_type')?.value || 'infill_turf';
  const civilBed  = document.getElementById('tk_civil_bed')?.value || 'compacted_wmm';
  const badFloor  = document.getElementById('mz_badminton_floor')?.value || 'bwf_vinyl_4_5mm';
  const badBed    = document.getElementById('mz_badminton_bed')?.value || 'rcc_m20_4inch_laser';
  const sideNet   = document.getElementById('tk_side_net')?.value || 'braided_2_5mm_45mm';
  const topNet    = document.getElementById('tk_top_net')?.value  || 'twisted_1_5mm_35mm';
  const customRate = parseFloat(document.getElementById('tk_custom_rate')?.value || 0) || null;
  const gates = (typeof turnkeyGatesList !== 'undefined') ? turnkeyGatesList : [];
  const gateCount = gates.length;
  const excluded = (typeof turnkeyExcludedItems !== 'undefined') ? turnkeyExcludedItems : [];
  const sides = (typeof turnkeySidesState !== 'undefined') ? turnkeySidesState : {};
  const layers = (typeof turnkeyLayersState !== 'undefined') ? turnkeyLayersState : {};
  const showRoof = (typeof isRoofSheetsVisible !== 'undefined') ? isRoofSheetsVisible : false;

  const zones = (sport.includes("multisport") || sport === "multisport_arena_complex_185x60") ? {
    cricket: { length: parseFloat(document.getElementById("mz_len_1")?.value || document.getElementById("mz_cricket_l")?.value) || 100 },
    badminton: { length: parseFloat(document.getElementById("mz_len_2")?.value || document.getElementById("mz_badminton_l")?.value) || 45 },
    utility: { length: parseFloat(document.getElementById("mz_len_3")?.value || document.getElementById("mz_utility_l")?.value) || 40 },
    zone4: document.getElementById("mz_len_4") ? { length: parseFloat(document.getElementById("mz_len_4").value) || 20 } : null,
    zone5: document.getElementById("mz_len_5") ? { length: parseFloat(document.getElementById("mz_len_5").value) || 20 } : null
  } : null;

  const cfgObj = {
    length: L,
    width: W,
    height: H,
    pitch_length: pitchL,
    gate_count: gateCount,
    gates: gates,
    sport_type: sport,
    roof_type: roof,
    package: pkg,
    pole_style: poleStyle,
    pipe_size: pipeSize,
    pipe_gauge: pipeGauge,
    turf_type: turfType,
    civil_bed_type: civilBed,
    badminton_floor_type: badFloor,
    badminton_bed_type: badBed,
    side_net: sideNet,
    top_net: topNet,
    custom_rate: customRate,
    open_sides: sides,
    layers: layers,
    show_roof_sheets: showRoof,
    zones: zones,
    excluded_items: excluded,
  };

  const cfgInput = document.getElementById('turnkeyConfigJson');
  if (cfgInput) cfgInput.value = JSON.stringify(cfgObj);
  const gatesInput = document.getElementById('turnkeyGatesJson');
  if (gatesInput) gatesInput.value = JSON.stringify(gates);
  const exclInput = document.getElementById('turnkeyExcludedJson');
  if (exclInput) exclInput.value = JSON.stringify(excluded);
  const sidesInput = document.getElementById('tkOpenSidesInput');
  if (sidesInput) sidesInput.value = JSON.stringify(sides);
  const layersInput = document.getElementById('tkLayersInput');
  if (layersInput) layersInput.value = JSON.stringify(layers);
  const zonesInput = document.getElementById('turnkeyZonesJson');
  if (zonesInput && zones) zonesInput.value = JSON.stringify(zones);

  return cfgObj;
}

function selectActiveGateForVisualizer(idx) {
  window.selectedTurnkeyGateIdx = idx;
  renderGateManagerRows();
  updateTurnkeyCalculations(true);
}

function renderGateManagerRows() {
  const container = document.getElementById('gateRowsContainer');
  if (!container) return;
  container.innerHTML = '';

  if (!turnkeyGatesList || turnkeyGatesList.length === 0) {
    container.innerHTML = '<div class="text-muted p-2 text-center" style="font-size:8pt;">No doors configured. Click <b>Add Gate (+)</b> to add access doors.</div>';
    return;
  }

  const courtL = parseFloat(document.getElementById('tk_length')?.value) || 90;
  const courtW = parseFloat(document.getElementById('tk_width')?.value) || 60;

  turnkeyGatesList.forEach((gate, idx) => {
    const isSelected = (window.selectedTurnkeyGateIdx === idx);
    const isLengthWall = (gate.wall === 'left_length' || gate.wall === 'right_length' || gate.wall.startsWith('inter_zone'));
    const maxDimension = isLengthWall ? courtL : courtW;
    const posVal = isLengthWall ? (parseFloat(gate.x_ft) || Math.round(courtL * 0.5)) : (parseFloat(gate.z_ft) || Math.round(courtW * 0.5));

    const minPos = 2;
    const maxPos = Math.round(maxDimension - 2);

    const row = document.createElement('div');
    row.className = `p-2 rounded border shadow-sm ${isSelected ? 'border-warning bg-warning bg-opacity-10' : 'bg-white'}`;
    row.style.transition = 'all 0.2s ease';

    row.innerHTML = `
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-xs ${isSelected ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary'} py-0 px-2" onclick="selectActiveGateForVisualizer(${idx})" title="Click to select this gate for 3D/2D visualizer adjustments" style="font-size:7.5pt;">
            ${isSelected ? '🎯 Active Gate #' + (idx + 1) : '🚪 Gate #' + (idx + 1)}
          </button>
          <input type="text" class="form-control form-control-sm fw-bold" style="max-width:180px;font-size:8pt;" value="${gate.name || ('Gate ' + (idx + 1))}" placeholder="Gate Label" onchange="onGatePropChange(${idx}, 'name', this.value)">
        </div>

        <div class="d-flex align-items-center gap-2">
          <select class="form-select form-select-sm fw-semibold" style="max-width:220px;font-size:8pt;" onchange="onGatePropChange(${idx}, 'wall', this.value)">
            <option value="left_length" ${gate.wall === 'left_length' ? 'selected' : ''}>📍 Left Sideline (Length Wall)</option>
            <option value="right_length" ${gate.wall === 'right_length' ? 'selected' : ''}>📍 Right Sideline (Length Wall)</option>
            <option value="front_width" ${gate.wall === 'front_width' ? 'selected' : ''}>📍 Front Endline (Bowler Wall)</option>
            <option value="back_width" ${gate.wall === 'back_width' ? 'selected' : ''}>📍 Back Endline (Batter Wall)</option>
            <option value="inter_zone_1_2" ${gate.wall === 'inter_zone_1_2' ? 'selected' : ''}>🔄 Partition 1 (Cricket ↔ Badminton)</option>
            <option value="inter_zone_2_3" ${gate.wall === 'inter_zone_2_3' ? 'selected' : ''}>🔄 Partition 2 (Badminton ↔ Lounge)</option>
          </select>
          <select class="form-select form-select-sm fw-semibold" style="max-width:160px;font-size:8pt;" onchange="onGatePropChange(${idx}, 'size', this.value)">
            <option value="4x7" ${(gate.width_ft == 4 && gate.height_ft == 7) ? 'selected' : ''}>📏 4 FT × 7 FT (Single)</option>
            <option value="5x7" ${(gate.width_ft == 5 && gate.height_ft == 7) ? 'selected' : ''}>📏 5 FT × 7 FT (Wide Door)</option>
            <option value="6x7" ${(gate.width_ft == 6 && gate.height_ft == 7) ? 'selected' : ''}>📏 6 FT × 7 FT (Equip Gate)</option>
            <option value="6x8" ${(gate.width_ft == 6 && gate.height_ft == 8) ? 'selected' : ''}>📏 6 FT × 8 FT (Tall Equip)</option>
            <option value="8x7" ${(gate.width_ft == 8 && gate.height_ft == 7) ? 'selected' : ''}>📏 8 FT × 7 FT (Double Leaf)</option>
          </select>
          <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeCustomGateRow(${idx})" title="Remove Gate"><i class="fa fa-trash"></i></button>
        </div>
      </div>

      <!-- Live Interactive Placement Slider & Quick Bay Snap Suite -->
      <div class="bg-light p-2 rounded border mt-1">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1" style="font-size:7.5pt;">
          <span class="text-secondary fw-bold">
            <i class="fa fa-sliders text-primary me-1"></i>Wall Position (${isLengthWall ? 'Distance along Length' : 'Distance along Width'}):
          </span>
          <div class="d-flex align-items-center gap-1">
            <span class="badge bg-primary text-white px-2 py-1 fw-bold" id="gatePosDisp_${idx}">${posVal} FT (of ${maxDimension} FT)</span>
            <span class="text-muted" style="font-size:7pt;">(Live 3D & 2D Synced &bull; Horizontal Pipes Cut)</span>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <input type="range" id="gateRange_${idx}" class="form-range flex-grow-1" min="0" max="${maxDimension}" step="0.1" value="${posVal}" oninput="onGateSliderMove(${idx}, '${isLengthWall ? 'x_ft' : 'z_ft'}', this.value)">
          <input type="number" id="gatePosInp_${idx}" class="form-control form-control-sm text-center fw-bold" style="width:85px;font-size:8pt;" min="0" max="${maxDimension}" step="any" value="${posVal}" oninput="onGateSliderMove(${idx}, '${isLengthWall ? 'x_ft' : 'z_ft'}', this.value)" onchange="onGateSliderMove(${idx}, '${isLengthWall ? 'x_ft' : 'z_ft'}', this.value)">
          <span class="text-muted" style="font-size:8pt;">FT</span>
        </div>

        <!-- Quick Snap Bay Buttons -->
        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
          <span class="text-muted fw-bold" style="font-size:7pt;">📍 Quick Position:</span>
          ${isLengthWall ? `
            <button type="button" class="btn btn-xs btn-outline-dark py-0 px-1" style="font-size:7pt;" onclick="onGateSliderMove(${idx}, 'x_ft', Math.round(courtL*0.15))">Front Quarter (${Math.round(courtL*0.15)} FT)</button>
            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size:7pt;" onclick="onGateSliderMove(${idx}, 'x_ft', Math.round(courtL*0.5))">Mid-Court (${Math.round(courtL*0.5)} FT)</button>
            <button type="button" class="btn btn-xs btn-outline-dark py-0 px-1" style="font-size:7pt;" onclick="onGateSliderMove(${idx}, 'x_ft', Math.round(courtL*0.82))">Batter End (${Math.round(courtL*0.82)} FT)</button>
          ` : `
            <button type="button" class="btn btn-xs btn-outline-dark py-0 px-1" style="font-size:7pt;" onclick="onGateSliderMove(${idx}, 'z_ft', Math.round(courtW*0.2))">Left Side (${Math.round(courtW*0.2)} FT)</button>
            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size:7pt;" onclick="onGateSliderMove(${idx}, 'z_ft', Math.round(courtW*0.5))">Centerline (${Math.round(courtW*0.5)} FT)</button>
            <button type="button" class="btn btn-xs btn-outline-dark py-0 px-1" style="font-size:7pt;" onclick="onGateSliderMove(${idx}, 'z_ft', Math.round(courtW*0.8))">Right Side (${Math.round(courtW*0.8)} FT)</button>
          `}
        </div>
      </div>
    `;
    container.appendChild(row);
  });

  const countSel = document.getElementById('tk_gate_count');
  if (countSel && countSel.value !== String(turnkeyGatesList.length)) {
    countSel.value = turnkeyGatesList.length;
  }
}

function onGateSliderMove(idx, prop, val) {
  if (!turnkeyGatesList[idx]) return;
  const numVal = parseFloat(val);
  if (isNaN(numVal)) return;
  const roundedVal = Math.round(numVal * 100) / 100;
  turnkeyGatesList[idx][prop] = roundedVal;
  turnkeyGatesList[idx].position_ft = roundedVal;

  const courtL = parseFloat(document.getElementById('tk_length')?.value) || 90;
  const courtW = parseFloat(document.getElementById('tk_width')?.value) || 60;
  const isLengthWall = (turnkeyGatesList[idx].wall === 'left_length' || turnkeyGatesList[idx].wall === 'right_length' || turnkeyGatesList[idx].wall.startsWith('inter_zone'));
  const maxDim = isLengthWall ? courtL : courtW;

  const disp = document.getElementById(`gatePosDisp_${idx}`);
  if (disp) disp.innerText = `${roundedVal} FT (of ${maxDim} FT)`;

  const numInp = document.getElementById(`gatePosInp_${idx}`);
  if (numInp && document.activeElement !== numInp && parseFloat(numInp.value) !== roundedVal) {
    numInp.value = roundedVal;
  }
  const rangeInp = document.getElementById(`gateRange_${idx}`);
  if (rangeInp && document.activeElement !== rangeInp && parseFloat(rangeInp.value) !== roundedVal) {
    rangeInp.value = roundedVal;
  }

  if (typeof serializeFullTurnkeyConfig === 'function') serializeFullTurnkeyConfig();
  if (typeof triggerAutoSave === 'function') triggerAutoSave();
  updateTurnkeyCalculations(true);
}

function onGatePropChange(idx, prop, val) {
  if (typeof serializeFullTurnkeyConfig === 'function') serializeFullTurnkeyConfig();
  if (!turnkeyGatesList[idx]) return;
  if (prop === 'name') {
    turnkeyGatesList[idx].name = val;
  } else if (prop === 'wall') {
    const courtL = parseFloat(document.getElementById('tk_length')?.value) || 90;
    const courtW = parseFloat(document.getElementById('tk_width')?.value) || 60;
    turnkeyGatesList[idx].wall = val;
    if (val === 'front_width') {
      turnkeyGatesList[idx].x_ft = courtL;
      turnkeyGatesList[idx].z_ft = Math.round(courtW * 0.5);
      turnkeyGatesList[idx].color = '#f97316';
    } else if (val === 'back_width') {
      turnkeyGatesList[idx].x_ft = 0;
      turnkeyGatesList[idx].z_ft = Math.round(courtW * 0.5);
      turnkeyGatesList[idx].color = '#eab308';
    } else if (val === 'left_length') {
      turnkeyGatesList[idx].x_ft = Math.round(courtL * 0.8);
      turnkeyGatesList[idx].z_ft = 0;
      turnkeyGatesList[idx].color = '#22c55e';
    } else if (val === 'right_length') {
      turnkeyGatesList[idx].x_ft = Math.round(courtL * 0.2);
      turnkeyGatesList[idx].z_ft = courtW;
      turnkeyGatesList[idx].color = '#a855f7';
    } else if (val === 'inter_zone_1_2') {
      turnkeyGatesList[idx].x_ft = Math.round(courtL * 0.5);
      turnkeyGatesList[idx].z_ft = Math.round(courtW * 0.5);
      turnkeyGatesList[idx].color = '#06b6d4';
    } else if (val === 'inter_zone_2_3') {
      turnkeyGatesList[idx].x_ft = Math.round(courtL * 0.75);
      turnkeyGatesList[idx].z_ft = Math.round(courtW * 0.5);
      turnkeyGatesList[idx].color = '#3b82f6';
    }
  } else if (prop === 'size') {
    const parts = val.split('x');
    turnkeyGatesList[idx].width_ft = parseFloat(parts[0]) || 4;
    turnkeyGatesList[idx].height_ft = parseFloat(parts[1]) || 7;
  }
  renderGateManagerRows();
  updateTurnkeyCalculations(true);
}

function addCustomGateRow() {
  if (typeof serializeFullTurnkeyConfig === 'function') serializeFullTurnkeyConfig();
  const newIdx = turnkeyGatesList.length + 1;
  turnkeyGatesList.push({
    id: 'gate_' + Date.now(),
    name: 'Access Door #' + newIdx,
    wall: 'inter_zone_1_2',
    x_ft: 100,
    z_ft: -20,
    width_ft: 4,
    height_ft: 7,
    type: 'inter_zone',
    color: '#06b6d4'
  });
  renderGateManagerRows();
  updateTurnkeyCalculations(true);
}

function removeCustomGateRow(idx) {
  if (typeof serializeFullTurnkeyConfig === 'function') serializeFullTurnkeyConfig();
  turnkeyGatesList.splice(idx, 1);
  renderGateManagerRows();
  updateTurnkeyCalculations(true);
}

function onGateCountDropdownChange() {
  if (typeof serializeFullTurnkeyConfig === 'function') serializeFullTurnkeyConfig();
  const count = parseInt(document.getElementById('tk_gate_count')?.value) || 0;
  if (count < turnkeyGatesList.length) {
    turnkeyGatesList = turnkeyGatesList.slice(0, count);
  } else {
    while (turnkeyGatesList.length < count) {
      const idx = turnkeyGatesList.length + 1;
      let wall = 'left_length';
      let name = 'Gate #' + idx;
      let col = '#22c55e';
      if (idx === 1) { wall = 'front_width'; name = 'Main Front Entry (Width)'; col = '#f97316'; }
      else if (idx === 2) { wall = 'inter_zone_1_2'; name = 'Inter-Zone (Cricket ↔ Badminton)'; col = '#06b6d4'; }
      else if (idx === 3) { wall = 'left_length'; name = 'Cricket Sideline Player Door'; col = '#22c55e'; }
      else if (idx === 4) { wall = 'inter_zone_2_3'; name = 'Lounge Access (Badminton ↔ Utility)'; col = '#3b82f6'; }
      else if (idx === 5) { wall = 'right_length'; name = 'Badminton Sideline Door'; col = '#a855f7'; }
      turnkeyGatesList.push({
        id: 'gate_' + idx,
        name: name,
        wall: wall,
        x_ft: 50,
        z_ft: 0,
        width_ft: 4,
        height_ft: 7,
        type: 'player_door',
        color: col
      });
    }
  }
  renderGateManagerRows();
  updateTurnkeyCalculations(true);
}

let _turnkeyDebounceTimer = null;
let _turnkeyInitialized = false;
window.TURNKEY_SPORT_DEFAULTS = <?= json_encode(getTurnkeySportProfiles()) ?>;

function onRoofTypeChange(triggerCalc = true) {
  const topNetSelect = document.getElementById('tk_top_net');
  if (topNetSelect) {
    topNetSelect.disabled = false;
    topNetSelect.classList.remove('bg-light');
    topNetSelect.style.color = '';
    topNetSelect.style.fontWeight = '';
  }
  if (triggerCalc) {
    updateTurnkeyCalculations(true);
  }
}


let currentZoneCount = 3;

function addCustomZone() {
  if (currentZoneCount >= 5) {
    alert('Maximum 5 custom zones allowed in an arena.');
    return;
  }
  currentZoneCount++;
  const container = document.getElementById('dynamicZonesContainer');
  if (!container) return;

  const zoneIdx = currentZoneCount;
  const col = document.createElement('div');
  col.className = 'col-md-4 zone-col';
  col.id = `zoneBox_${zoneIdx}`;

  let sportName = 'Zone ' + zoneIdx + ': Custom Sports Zone';
  let badgeColor = 'bg-info text-dark';
  let borderColor = 'border-info';
  let icon = 'fa-table-tennis-paddle-ball';
  if (zoneIdx === 4) {
    sportName = 'Zone 4: Pickleball / Mini Court';
    badgeColor = 'bg-warning text-dark';
    borderColor = 'border-warning';
    icon = 'fa-medal';
  } else if (zoneIdx === 5) {
    sportName = 'Zone 5: Warm-up & Cafeteria';
    badgeColor = 'bg-dark text-white';
    borderColor = 'border-dark';
    icon = 'fa-mug-hot';
  }

  col.innerHTML = `
    <div class="p-2 bg-white rounded border ${borderColor} h-100 shadow-sm">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="fw-bold text-dark" style="font-size:0.85rem;"><i class="fa ${icon} me-1"></i>${sportName}</span>
        <span class="badge ${badgeColor}" id="z${zoneIdx}AreaBadge">1,200 SFT</span>
      </div>
      <div class="mb-1">
        <label class="form-label small text-muted mb-0" style="font-size:7.5pt;">Length (FT):</label>
        <div class="input-group input-group-sm">
          <input type="number" id="mz_len_${zoneIdx}" class="form-control fw-bold" value="20" min="10" max="150" step="1" oninput="onMultiZoneChange()">
          <span class="input-group-text">FT</span>
        </div>
      </div>
      <div class="small text-muted" style="font-size:7pt;">
        Dimensions: <strong id="z${zoneIdx}DimText">20 FT × 60 FT</strong><br>
        Scope: <strong>Dedicated Custom Sub-Zone</strong><br>
        Roof: <strong class="text-teal">Continuous 0.50mm Galvalume Metal Roof</strong>
      </div>
    </div>
  `;

  const lastZoneBox = document.getElementById('zoneBox_3');
  if (lastZoneBox && lastZoneBox.parentNode === container) {
    container.insertBefore(col, lastZoneBox);
  } else {
    container.appendChild(col);
  }

  onMultiZoneChange();
}

function removeLastCustomZone() {
  if (currentZoneCount <= 2) {
    alert('An arena must contain at least 2 zones (e.g., Primary Ground + Utility / Second Court).');
    return;
  }
  const targetId = `zoneBox_${currentZoneCount}`;
  const el = document.getElementById(targetId);
  if (el) el.remove();
  currentZoneCount--;
  onMultiZoneChange();
}


function syncBadmintonBed(val) {
  const z2Bed = document.getElementById('mz_badminton_bed');
  if (z2Bed) z2Bed.value = val;
  const mainBed = document.getElementById('main_badminton_bed');
  if (mainBed) mainBed.value = val;
  generateTurnkeyBOQAndPopulate(false);
}

function syncBadmintonFloor(val) {
  const z2Floor = document.getElementById('mz_badminton_floor');
  if (z2Floor) z2Floor.value = val;
  const mainFloor = document.getElementById('main_badminton_floor');
  if (mainFloor) mainFloor.value = val;
  generateTurnkeyBOQAndPopulate(false);
}

function onMultiZoneChange() {
  const totalL = parseFloat(document.getElementById('tk_length')?.value) || 185;
  const W = parseFloat(document.getElementById('tk_width')?.value) || 60;

  let usedL = 0;
  for (let i = 1; i <= currentZoneCount; i++) {
    if (i === 3) continue; // Zone 3 is auto-balanced remaining space
    const lenInput = document.getElementById(`mz_len_${i}`);
    if (lenInput) {
      usedL += parseFloat(lenInput.value) || 0;
    }
  }

  const remainingL = Math.max(0, Math.round(totalL - usedL));
  const z3Input = document.getElementById('mz_len_3');
  if (z3Input) z3Input.value = remainingL;

  for (let i = 1; i <= currentZoneCount; i++) {
    const lenInput = document.getElementById(`mz_len_${i}`);
    const zL = (i === 3) ? remainingL : (lenInput ? (parseFloat(lenInput.value) || 0) : 0);
    const zArea = Math.round(zL * W);

    const badge = document.getElementById(`z${i}AreaBadge`);
    if (badge) badge.textContent = `${zArea.toLocaleString('en-IN')} SFT`;

    const dimText = document.getElementById(`z${i}DimText`);
    if (dimText) dimText.textContent = `${zL} FT × ${W} FT`;
  }

  const totalBadge = document.getElementById('multiZoneTotalBadge');
  if (totalBadge) totalBadge.textContent = `Total Arena: ${totalL} FT (${(totalL * W).toLocaleString('en-IN')} SFT)`;

  const pb = document.getElementById('mzProgressBar');
  if (pb) {
    let pbHtml = '';
    const colors = ['bg-success', 'bg-primary', 'bg-secondary', 'bg-warning text-dark', 'bg-dark text-white'];
    const names = ['🏏 Cricket', '🏸 Badminton 2 Courts', '🏢 Remaining Utility', '🏓 Mini Court', '☕ Amenities'];
    for (let i = 1; i <= currentZoneCount; i++) {
      const zL = (i === 3) ? remainingL : (parseFloat(document.getElementById(`mz_len_${i}`)?.value) || 0);
      const pct = totalL > 0 ? ((zL / totalL) * 100).toFixed(1) : 0;
      const c = colors[(i - 1) % colors.length];
      const n = names[(i - 1) % names.length];
      pbHtml += `<div class="progress-bar ${c}" style="width: ${pct}%;">${n} (${zL}')</div>`;
    }
    pb.innerHTML = pbHtml;
  }

  if (typeof refreshLiveCourtViewer === 'function') {
    refreshLiveCourtViewer();
  }
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
    if (profile.default_gates !== undefined && document.getElementById('tk_gate_count')) {
      document.getElementById('tk_gate_count').value = profile.default_gates;
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
    if (sport.includes('cricket') || sport === 'multi_sport' || sport.includes('terrace') || sport.includes('rooftop')) {
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
    const cr = document.getElementById('tk_custom_rate');
    if (cr) cr.value = '';
    updateTurnkeyCalculations(true);
  }
}

function onTurnkeyHeightChange(triggerAjax = false) {
  const cr = document.getElementById('tk_custom_rate');
  if (cr && cr.value) {
    cr.value = '';
  }
  updateTurnkeyCalculations(triggerAjax);
}

function onTurnkeyPackageChange() {
  const pkgRadio = document.querySelector('input[name="tk_package"]:checked');
  const pkg = pkgRadio ? pkgRadio.value : 'commercial';

  const cr = document.getElementById('tk_custom_rate');
  if (cr && cr.value) {
    cr.value = '';
  }



  const statusEl = document.getElementById('turnkeySyncStatus');
  if (statusEl) {
    const pkgLabels = { essential: 'ESSENTIAL', commercial: 'COMMERCIAL', premium_pro: 'PREMIUM PRO' };
    statusEl.innerHTML = '<span class="text-primary fw-bold"><i class="fa fa-spinner fa-spin me-1"></i>Switching to ' + (pkgLabels[pkg] || pkg.toUpperCase()) + ' package & calculating engineering BOQ...</span>';
  }

  generateTurnkeyBOQAndPopulate(false);
}

function clearCustomRate() {
  const cr = document.getElementById('tk_custom_rate');
  if (cr) {
    cr.value = '';
    updateTurnkeyCalculations(true);
  }
}

// ── Turnkey 1-Click Netsocean-Style Sides & Layers State ──────────────────
let turnkeySidesState = {
  front: false,
  back: false,
  left: false,
  right: false,
  top: false
};

let turnkeyLayersState = {
  structure: true,
  netting: true,
  lighting: true
};

let currentVisualizerTab = '3d';

function updateSideButtonsUI() {
  const sides = [
    { key: 'front', openText: 'OPEN (Bowler Portal)', closedText: 'ENCLOSED' },
    { key: 'back',  openText: 'OPEN', closedText: 'ENCLOSED' },
    { key: 'left',  openText: 'OPEN', closedText: 'ENCLOSED' },
    { key: 'right', openText: 'OPEN', closedText: 'ENCLOSED' },
    { key: 'top',   openText: 'OPEN TOP', closedText: 'ENCLOSED' }
  ];

  sides.forEach(s => {
    const btn = document.getElementById('btnSide' + s.key.charAt(0).toUpperCase() + s.key.slice(1));
    const lbl = document.getElementById('lblSide' + s.key.charAt(0).toUpperCase() + s.key.slice(1));
    const isOpen = turnkeySidesState[s.key] === true;
    if (btn && lbl) {
      if (isOpen) {
        btn.className = 'btn btn-sm btn-outline-primary fw-bold px-2 py-1';
        lbl.textContent = s.openText;
      } else {
        btn.className = 'btn btn-sm btn-success fw-bold px-2 py-1';
        lbl.textContent = s.closedText;
      }
    }
  });

  const openCount = Object.values(turnkeySidesState).filter(v => v === true).length;
  const encText = document.getElementById('vEnclosureText');
  if (encText) {
    encText.textContent = (5 - openCount) + ' Sides Enclosed' + (turnkeySidesState.front ? ' (Front Bowler Open)' : '');
  }
}

function toggleTurnkeySide(side) {
  turnkeySidesState[side] = !turnkeySidesState[side];
  const sidesInp = document.getElementById('tkOpenSidesInput');
  if (sidesInp) sidesInp.value = JSON.stringify(turnkeySidesState);
  updateSideButtonsUI();
  refreshLiveCourtViewer();
  updateTurnkeyCalculations(true);
}

function updateLayerButtonsUI() {
  const structBtn = document.getElementById('btnLayerStructure');
  const structLbl = document.getElementById('lblLayerStructure');
  if (structBtn && structLbl) {
    if (turnkeyLayersState.structure) {
      structBtn.className = 'btn btn-sm btn-dark fw-bold px-2 py-1';
      structLbl.textContent = 'ON';
    } else {
      structBtn.className = 'btn btn-sm btn-outline-secondary fw-bold px-2 py-1';
      structLbl.textContent = 'OFF';
    }
  }

  const netBtn = document.getElementById('btnLayerNetting');
  const netLbl = document.getElementById('lblLayerNetting');
  if (netBtn && netLbl) {
    if (turnkeyLayersState.netting) {
      netBtn.className = 'btn btn-sm btn-success fw-bold px-2 py-1';
      netLbl.textContent = 'ON';
    } else {
      netBtn.className = 'btn btn-sm btn-outline-secondary fw-bold px-2 py-1';
      netLbl.textContent = 'OFF';
    }
  }

  const lightBtn = document.getElementById('btnLayerLighting');
  const lightLbl = document.getElementById('lblLayerLighting');
  if (lightBtn && lightLbl) {
    if (turnkeyLayersState.lighting) {
      lightBtn.className = 'btn btn-sm btn-warning text-dark fw-bold px-2 py-1';
      lightLbl.textContent = 'ON';
    } else {
      lightBtn.className = 'btn btn-sm btn-outline-secondary fw-bold px-2 py-1';
      lightLbl.textContent = 'OFF';
    }
  }
}

function toggleTurnkeyLayer(layer) {
  turnkeyLayersState[layer] = !turnkeyLayersState[layer];
  const layersInp = document.getElementById('tkLayersInput');
  if (layersInp) layersInp.value = JSON.stringify(turnkeyLayersState);
  updateLayerButtonsUI();
  refreshLiveCourtViewer();
  updateTurnkeyCalculations(true);
}

function syncLayersWithActiveRows() {
  const isTurnkeySubtype = (document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court') || (document.getElementById('estimation_subtype')?.value === 'turnkey_court');
  if (!isTurnkeySubtype) return;

  const descs = [];
  document.querySelectorAll('#itemsTbody .item-row textarea[name^="item_desc"]').forEach(ta => {
    descs.push(ta.value.toLowerCase());
  });
  if (descs.length === 0) return;

  const fullText = descs.join(' ');
  const hasStructure = /(column|pole|fabrication|ladder|truss|steel|pipe|framework|vertical upright|horizontal tie|purlin)/i.test(fullText);
  const hasNetting   = /(net|netting|ball-stop|hdpe|braided|twisted|containment|mesh)/i.test(fullText);
  const hasLighting  = /(light|floodlight|stadium light|sports led|lighting fixture|electrical|cabling|panel)/i.test(fullText);

  let changed = false;
  if (turnkeyLayersState.structure !== hasStructure) {
    turnkeyLayersState.structure = hasStructure;
    changed = true;
  }
  if (turnkeyLayersState.netting !== hasNetting) {
    turnkeyLayersState.netting = hasNetting;
    changed = true;
  }
  if (turnkeyLayersState.lighting !== hasLighting) {
    turnkeyLayersState.lighting = hasLighting;
    changed = true;
  }

  if (changed) {
    const layersInp = document.getElementById('tkLayersInput');
    if (layersInp) layersInp.value = JSON.stringify(turnkeyLayersState);
    updateLayerButtonsUI();
    refreshLiveCourtViewer();
    updateTurnkeyCalculations(true);
  }
}


let isRoofSheetsVisible = true;
function toggleRoofSheetsVisualizer() {
  isRoofSheetsVisible = !isRoofSheetsVisible;
  const statusEl = document.getElementById("roofSheetsStatus");
  const btnEl = document.getElementById("btnToggleRoofSheets");
  if (statusEl) statusEl.textContent = isRoofSheetsVisible ? "ON" : "OFF (Look Inside)";
  if (btnEl) {
    btnEl.className = isRoofSheetsVisible ? "btn btn-xs btn-warning text-dark fw-bold py-1 px-2 me-1" : "btn btn-xs btn-outline-info fw-bold py-1 px-2 me-1";
  }
  if (window.tkEditViewerInstance && window.tkEditViewerInstance.toggleRoof) {
    window.tkEditViewerInstance.toggleRoof(isRoofSheetsVisible);
  } else if (window.TurnkeyViewer && window.TurnkeyViewer.toggleRoof) {
    window.TurnkeyViewer.toggleRoof(isRoofSheetsVisible);
  } else {
    refreshLiveCourtViewer();
  }
}

// ── Turnkey 1-Click Multi-Sport Equipment & Games Suite State ──────────────────
let turnkeyEquipmentState = {
  cricket_pitch: false,
  football_goals: false,
  volleyball_net: false,
  badminton_net: false,
  court_markings: false
};

const EQUIPMENT_PRODUCTS_MAP = {
  cricket_pitch: {
    key: 'cricket_pitch',
    label: 'Cricket Pitch Strip (15mm Curled Turf + Stumps)',
    regex: /(cricket pitch|pitch turf|15mm pitch|15mm curled|batting pitch|spring-return stumps)/i,
    btnId: 'btnEqCricketPitch',
    lblId: 'lblEqCricketPitch',
    getItemData: function() {
      const pL = parseFloat(document.getElementById('tk_pitch_length')?.value || 66) || 66;
      const sft = Math.round(pL * 6.5);
      return {
        desc: `15mm Curled Cricket Pitch Turf (${pL} FT × 6.5 FT = ${sft} SFT)\nHigh-density non-directional curled yarn with boundary white markings and regulation spring-return wooden stumps set.`,
        hsn: '57033090',
        qty: sft,
        unit: 'SQFT',
        rate: 78,
        gst_rate: 5,
        specs: [
          'Pile Height: 15mm Non-Directional Curled Monofilament',
          `Pitch Dimensions: ${pL} FT Length × 6.5 FT Width (${sft} SFT)`,
          'Stumps: Regulation Spring-Return Wooden Stumps Set Included'
        ]
      };
    }
  },
  football_goals: {
    key: 'football_goals',
    label: 'Mini Football Goal Posts (1 Pair)',
    regex: /(mini football|football goal|futsal goal|goal posts)/i,
    btnId: 'btnEqFootballGoals',
    lblId: 'lblEqFootballGoals',
    getItemData: function() {
      return {
        desc: 'Mini Football / Futsal Heavy-Duty Goal Posts (1 Pair)\nRegulation 8 FT x 6 FT heavy-gauge 3" tubular steel frame, rust-inhibiting PU powder coat with 3.5mm all-weather braided containment net.',
        hsn: '95069990',
        qty: 1,
        unit: 'SET',
        rate: 35000,
        gst_rate: 18,
        specs: [
          'Size: Regulation 8 FT (W) × 6 FT (H) × 4 FT (D)',
          'Pipe: 3" Heavy-Duty Circular Steel Frame with PU Powder Coat',
          'Net: 3.5mm High-Tenacity UV Stabilised Braided Knotless Netting'
        ]
      };
    }
  },
  volleyball_net: {
    key: 'volleyball_net',
    label: 'Volleyball Upright Posts & Net System',
    regex: /(volleyball|volleyball posts)/i,
    btnId: 'btnEqVolleyballNet',
    lblId: 'lblEqVolleyballNet',
    getItemData: function() {
      return {
        desc: 'Volleyball Upright Ground-Sleeve Posts & Regulation Net System\n2.5" heavy steel telescopic posts (ground sleeves + flush caps), tension winch pulley, antenna set, and 3.0mm UV competition net.',
        hsn: '95069990',
        qty: 1,
        unit: 'SET',
        rate: 18500,
        gst_rate: 18,
        specs: [
          'Posts: 2.5" Heavy Tubular Steel with Internal Tension Pulley Winch',
          'Mounting: Removable Ground Sleeve Sockets with Flush Brass Floor Caps',
          'Net: 3.0mm UV Braided Competition Net with Flexible Side Antennas'
        ]
      };
    }
  },
  badminton_net: {
    key: 'badminton_net',
    label: 'Badminton Movable Posts & Tournament Net',
    regex: /(badminton.*post|badminton.*net|movable.*badminton)/i,
    btnId: 'btnEqBadmintonNet',
    lblId: 'lblEqBadmintonNet',
    getItemData: function() {
      return {
        desc: 'Movable Weighted Badminton Posts & BWF Regulation Net Set\nHeavy cast-iron counterweight mobile base with nylon caster wheels, tension pulley ratchet, and BWF tournament net (1.55m height).',
        hsn: '95069990',
        qty: 1,
        unit: 'SET',
        rate: 16500,
        gst_rate: 18,
        specs: [
          'Base: Heavy Cast-Iron Mobile Base with Floor-Protecting Wheels',
          'Height: Official 1.55 Meter Upright with Ratchet Net Tensioner',
          'Net: BWF Approved Tournament Nylon Net with White Headband'
        ]
      };
    }
  },
  court_markings: {
    key: 'court_markings',
    label: 'Multi-Sport Regulation Line Markings (4-in-1)',
    regex: /(multi-sport.*marking|regulation line marking|4-in-1.*marking|court marking)/i,
    btnId: 'btnEqCourtMarkings',
    lblId: 'lblEqCourtMarkings',
    getItemData: function() {
      return {
        desc: 'Multi-Sport Regulation Inlaid / Polyurethane Line Markings (4-in-1)\nPrecision marked boundary lines for Box Cricket, Mini Football, Volleyball, and Badminton in distinct high-visibility regulation colors (White, Yellow, Blue, Orange).',
        hsn: '95069990',
        qty: 1,
        unit: 'JOB',
        rate: 28000,
        gst_rate: 18,
        specs: [
          'Sports Included: Cricket + Mini Football + Volleyball + Badminton',
          'Marking Type: Inlaid Turf Markings / High-Bond Wear-Resistant PU Lines',
          'Color Coding: White (Cricket/Football), Yellow (Volleyball), Blue (Badminton)'
        ]
      };
    }
  }
};

function updateEquipmentButtonsUI() {
  Object.keys(EQUIPMENT_PRODUCTS_MAP).forEach(k => {
    const item = EQUIPMENT_PRODUCTS_MAP[k];
    const btn = document.getElementById(item.btnId);
    const lbl = document.getElementById(item.lblId);
    const isOn = turnkeyEquipmentState[k] === true;
    if (btn && lbl) {
      if (isOn) {
        btn.className = 'btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border border-success bg-success-subtle text-success';
        lbl.className = 'badge bg-success ms-1';
        lbl.textContent = 'ON';
      } else {
        btn.className = 'btn btn-sm w-100 fw-bold d-flex align-items-center justify-content-between p-2 rounded border border-secondary-subtle bg-white text-secondary';
        lbl.className = 'badge bg-secondary ms-1';
        lbl.textContent = 'OFF';
      }
    }
  });
}

function syncEquipmentWithActiveRows() {
  const isTurnkeySubtype = (document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court') || (document.getElementById('estimation_subtype')?.value === 'turnkey_court');
  if (!isTurnkeySubtype) return;

  const descs = [];
  document.querySelectorAll('#itemsTbody .item-row textarea[name^="item_desc"]').forEach(ta => {
    descs.push(ta.value);
  });
  const fullText = descs.join('\n');

  let changed = false;
  Object.keys(EQUIPMENT_PRODUCTS_MAP).forEach(k => {
    const eq = EQUIPMENT_PRODUCTS_MAP[k];
    const present = eq.regex.test(fullText);
    if (turnkeyEquipmentState[k] !== present) {
      turnkeyEquipmentState[k] = present;
      changed = true;
    }
  });

  const eqInp = document.getElementById('tkEquipmentInput');
  if (eqInp) eqInp.value = JSON.stringify(turnkeyEquipmentState);
  updateEquipmentButtonsUI();
  if (changed) {
    refreshLiveCourtViewer();
  }
}

function toggleTurnkeyEquipment(key) {
  const eq = EQUIPMENT_PRODUCTS_MAP[key];
  if (!eq) return;

  const currentlyOn = turnkeyEquipmentState[key] === true;
  if (currentlyOn) {
    removeEquipmentRowFromBOQ(key);
    turnkeyEquipmentState[key] = false;
  } else {
    addEquipmentRowToBOQ(key);
    turnkeyEquipmentState[key] = true;
  }

  const eqInp = document.getElementById('tkEquipmentInput');
  if (eqInp) eqInp.value = JSON.stringify(turnkeyEquipmentState);
  updateEquipmentButtonsUI();
  reindexRows();
  calcTotals();
  refreshLiveCourtViewer();
  if (typeof scheduleAutosave === "function") scheduleAutosave();
}

function setAllTurnkeyEquipment(enableAll) {
  Object.keys(EQUIPMENT_PRODUCTS_MAP).forEach(k => {
    const currentlyOn = turnkeyEquipmentState[k] === true;
    if (enableAll && !currentlyOn) {
      addEquipmentRowToBOQ(k);
      turnkeyEquipmentState[k] = true;
    } else if (!enableAll && currentlyOn) {
      removeEquipmentRowFromBOQ(k);
      turnkeyEquipmentState[k] = false;
    }
  });

  const eqInp = document.getElementById('tkEquipmentInput');
  if (eqInp) eqInp.value = JSON.stringify(turnkeyEquipmentState);
  updateEquipmentButtonsUI();
  reindexRows();
  calcTotals();
  refreshLiveCourtViewer();
  if (typeof scheduleAutosave === "function") scheduleAutosave();
}

function addEquipmentRowToBOQ(key) {
  const eq = EQUIPMENT_PRODUCTS_MAP[key];
  if (!eq) return;

  let alreadyExists = false;
  document.querySelectorAll('#itemsTbody .item-row textarea[name^="item_desc"]').forEach(ta => {
    if (eq.regex.test(ta.value)) alreadyExists = true;
  });
  if (alreadyExists) return;

  const data = eq.getItemData();
  const tbody = document.getElementById('itemsTbody');
  if (!tbody) return;

  const newIdx = rowIdx++;
  const uid = getNextItemUid();
  tbody.insertAdjacentHTML('beforeend', getRowHtml(newIdx, uid));

  const newRow = tbody.querySelector(`.item-row[data-item-uid="${uid}"]`);
  if (!newRow) return;

  const descTa = newRow.querySelector('textarea[name^="item_desc"]');
  if (descTa) descTa.value = data.desc;
  const hsnInp = newRow.querySelector('input[name^="item_hsn"]');
  if (hsnInp) hsnInp.value = data.hsn;
  const qtyInp = newRow.querySelector('input[name^="item_qty"]');
  if (qtyInp) qtyInp.value = data.qty;
  const unitSel = newRow.querySelector('select[name^="item_unit"]');
  if (unitSel) {
    unitSel.innerHTML = getUnitOptions(data.unit);
    unitSel.value = data.unit;
  }
  const rateInp = newRow.querySelector('input[name^="item_rate"]');
  if (rateInp) rateInp.value = data.rate;
  const gstInp = newRow.querySelector('input[name^="item_gst_rate"]');
  if (gstInp) gstInp.value = data.gst_rate;

  if (Array.isArray(data.specs) && data.specs.length > 0) {
    data.specs.forEach(s => {
      newRow.insertAdjacentHTML('afterend', getSpecHtml(uid, newIdx, s, true));
    });
  }

  autoResizeAllItemDescs();
}

function removeEquipmentRowFromBOQ(key) {
  const eq = EQUIPMENT_PRODUCTS_MAP[key];
  if (!eq) return;

  document.querySelectorAll('#itemsTbody .item-row').forEach(row => {
    const desc = row.querySelector('textarea[name^="item_desc"]')?.value || '';
    if (eq.regex.test(desc)) {
      const uid = row.dataset.itemUid;
      document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`).forEach(s => s.remove());
      row.remove();
    }
  });
}

function switchVisualizerTab(tab, keepCamera = false) {
  currentVisualizerTab = tab;
  const c3D = document.getElementById('turnkey3DContainerEdit');
  const cFloor = document.getElementById('turnkeyFloorPlanSvgEdit');
  const cSide = document.getElementById('turnkeySideElevationSvgEdit');
  const cCross = document.getElementById('turnkeyCrossElevationSvgEdit');

  const b3D = document.getElementById('btnView3DEdit');
  const bFloor = document.getElementById('btnViewFloorEdit');
  const bSide = document.getElementById('btnViewSideEdit');
  const bCross = document.getElementById('btnViewCrossEdit');

  if (c3D) c3D.style.display = (tab === '3d') ? 'block' : 'none';
  if (cFloor) cFloor.style.display = (tab === 'floor') ? 'block' : 'none';
  if (cSide) cSide.style.display = (tab === 'side') ? 'block' : 'none';
  if (cCross) cCross.style.display = (tab === 'cross') ? 'block' : 'none';

  if (b3D) b3D.className = (tab === '3d') ? 'btn btn-sm btn-primary active px-3' : 'btn btn-sm btn-outline-light px-3';
  if (bFloor) bFloor.className = (tab === 'floor') ? 'btn btn-sm btn-primary active px-3' : 'btn btn-sm btn-outline-light px-3';
  if (bSide) bSide.className = (tab === 'side') ? 'btn btn-sm btn-primary active px-3' : 'btn btn-sm btn-outline-light px-3';
  if (bCross) bCross.className = (tab === 'cross') ? 'btn btn-sm btn-primary active px-3' : 'btn btn-sm btn-outline-light px-3';

  if (!keepCamera) {
    refreshLiveCourtViewer();
  } else {
    window.dispatchEvent(new Event('resize'));
  }
}

function refreshLiveCourtViewer() {
  const estSubtypeVal = document.querySelector('input[name="estimation_subtype"]:checked')?.value;
  const hasTurnkeyBox = !!document.getElementById('turnkeyVisualizerBox');
  const isTurnkeySubtype = (estSubtypeVal === 'turnkey_court');
  if (!isTurnkeySubtype) return;

  const L = parseFloat(document.getElementById('tk_length')?.value) || 90;
  const W = parseFloat(document.getElementById('tk_width')?.value)  || 60;
  const H = parseFloat(document.getElementById('tk_height')?.value) || 25;
  const sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
  const roof = document.getElementById('tk_roof_type')?.value || 'light_cable_top';
  const pkg = document.querySelector('input[name="tk_package"]:checked')?.value || 'commercial';
  const pole = document.getElementById('tk_pole_style')?.value || 'ladder_2line';
  const turf = document.getElementById('tk_turf_type')?.value || 'infill_turf';
  const civil = document.getElementById('tk_civil_bed')?.value || 'compacted_wmm';
  const pitch = parseFloat(document.getElementById('tk_pitch_length')?.value) || 0;
  const gates = parseInt(document.getElementById('tk_gate_count')?.value) || 1;

  // Extract live floodlight count dynamically from the items table
  let liveLightCount = 8;
  const lightRow = Array.from(document.querySelectorAll('.item-row')).find(row => {
    const desc = (row.querySelector('textarea')?.value || '').toLowerCase();
    return desc.includes('floodlight') || desc.includes('led') || desc.includes('lighting');
  });
  if (lightRow) {
    const q = parseFloat(lightRow.querySelector('.item-qty')?.value);
    if (!isNaN(q) && q >= 0) {
      liveLightCount = Math.round(q);
    }
  } else {
    liveLightCount = (L <= 60) ? 4 : ((L <= 90) ? 6 : 8);
  }

  const elLightsBadge = document.getElementById('disp_tk_lights');
  if (elLightsBadge) {
    elLightsBadge.textContent = liveLightCount + '× LED Floodlights';
  }

  const isTrainingNet = (sport === 'training_net' || sport.includes('cricket_training') || sport.includes('training_net') || (L <= 80 && W <= 20));
  if (isTrainingNet || turnkeySidesState.front) {
    if (document.getElementById('tk_gate_count')) document.getElementById('tk_gate_count').value = '0';
  }

  const tkCfg = {
    length: L,
    width: W,
    height: H,
    sport_type: sport,
    roof_type: roof,
    package: pkg,
    pole_style: pole,
    turf_type: turf,
    civil_bed_type: civil,
    pitch_length: pitch,
    gate_count: gates,
    light_count: liveLightCount,
    gates: (typeof turnkeyGatesList !== 'undefined' && turnkeyGatesList.length > 0) ? turnkeyGatesList : null,
    tiers: <?php echo json_encode($editDoc['turnkey_config']['tiers'] ?? ($editDoc['turnkey_config']['specs']['tiers'] ?? null)); ?>,
    specs_tiers: <?php echo json_encode($editDoc['turnkey_config']['specs']['tiers'] ?? null); ?>,
    is_training_net: isTrainingNet,
    open_front: turnkeySidesState.front,
    open_sides: turnkeySidesState,
    zones: (sport.includes("multisport") || sport === "multisport_arena_complex_185x60") ? {
      cricket: { length: parseFloat(document.getElementById("mz_len_1")?.value || document.getElementById("mz_cricket_l")?.value) || 100 },
      badminton: { length: parseFloat(document.getElementById("mz_len_2")?.value || document.getElementById("mz_badminton_l")?.value) || 45 },
      utility: { length: parseFloat(document.getElementById("mz_len_3")?.value || document.getElementById("mz_utility_l")?.value) || 40 },
      zone4: document.getElementById("mz_len_4") ? { length: parseFloat(document.getElementById("mz_len_4").value) || 20 } : null,
      zone5: document.getElementById("mz_len_5") ? { length: parseFloat(document.getElementById("mz_len_5").value) || 20 } : null
    } : null,
    layers: turnkeyLayersState,
    equipment: turnkeyEquipmentState,
    show_roof_sheets: isRoofSheetsVisible
  };

  const isMZBar = (sport === 'multisport_arena_complex_185x60' || sport.includes('multisport_arena'));
  const vDim = document.getElementById('vDimText');
  if (vDim) vDim.textContent = isMZBar ? '185 × 60 FT (145×60 Metal Frame)' : `${L} × ${W} × ${H} FT`;
  const vArea = document.getElementById('vAreaText');
  if (vArea) vArea.textContent = isMZBar ? '11,100 SFT (8,700 SFT Sports + 2,400 SFT Parking)' : `${(L * W).toLocaleString('en-IN')} SFT`;
  const vPoles = document.getElementById('vPolesText');
  if (vPoles) vPoles.textContent = `${document.getElementById('disp_tk_poles')?.textContent || '20 Columns'}`;
  const vTiers = document.getElementById('vTiersText');
  if (vTiers) vTiers.textContent = `${document.getElementById('disp_tk_tiers')?.textContent || '4 Tiers'}`;

  if (window.TurnkeyViewer) {
    try {
      if (currentVisualizerTab === '3d') {
        window.tkEditViewerInstance = window.TurnkeyViewer.init3D('turnkey3DContainerEdit', tkCfg);
      } else if (currentVisualizerTab === 'floor') {
        window.TurnkeyViewer.renderFloorPlan('turnkeyFloorPlanSvgEdit', tkCfg);
      } else if (currentVisualizerTab === 'side') {
        window.TurnkeyViewer.renderSideElevation('turnkeySideElevationSvgEdit', tkCfg);
      } else if (currentVisualizerTab === 'cross') {
        window.TurnkeyViewer.renderCrossElevation('turnkeyCrossElevationSvgEdit', tkCfg);
      }
    } catch (err) {
      console.warn('Turnkey visualizer live update warning:', err);
    }
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

  const isTraining = (sport && (sport.includes('training_net') || sport.includes('cricket_training'))) || (L <= 80 && W <= 20);
  if (isTraining) {
    if (document.getElementById('tk_gate_count')) document.getElementById('tk_gate_count').value = '0';
    turnkeySidesState.front = true; // Open bowler portal
    turnkeySidesState.back = false;
    turnkeySidesState.left = false;
    turnkeySidesState.right = false;
    turnkeySidesState.top = false;
  } else {
    if (document.getElementById('tk_gate_count') && document.getElementById('tk_gate_count').value === '0') {
      document.getElementById('tk_gate_count').value = '1';
    }
    turnkeySidesState.front = false;
    turnkeySidesState.back = false;
    turnkeySidesState.left = false;
    turnkeySidesState.right = false;
    turnkeySidesState.top = false;
  }

  const cr = document.getElementById('tk_custom_rate');
  if (cr) cr.value = '';

  onRoofTypeChange(false);
  onSportTypeChange(false);
  updateSideButtonsUI();
  refreshLiveCourtViewer();

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

  const sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
  const openMap = (typeof getOpenSidesMap === 'function') ? getOpenSidesMap() : {};
  if (typeof renderGateManagerRows === 'function') {
    renderGateManagerRows();
  }
  const isTrainingNet = (sport === 'cricket_training_net' || sport.includes('training_net') || openMap.front === true);
    const isMultiZone = (sport === 'multisport_arena_complex_185x60' || sport.includes('multisport_arena'));
  const mzCard = document.getElementById('multiZoneCard');
  if (mzCard) mzCard.style.display = isMultiZone ? 'block' : 'none';
  const badRow = document.getElementById('badmintonSpecsRow');
  if (badRow) badRow.style.display = (isMultiZone || sport.includes('badminton')) ? 'flex' : 'none';
  if (isMultiZone) {
    if (document.getElementById('tk_length')) document.getElementById('tk_length').value = 185;
    if (document.getElementById('tk_width'))  document.getElementById('tk_width').value = 60;
    if (document.getElementById('tk_height')) document.getElementById('tk_height').value = 30;
    if (document.getElementById('tk_roof_type')) document.getElementById('tk_roof_type').value = 'metal_sheet_roof';
    if (document.getElementById('tk_pitch_length')) document.getElementById('tk_pitch_length').value = 83;
    onMultiZoneChange();
  }

  const isCircular = (sport === 'cricket_circular');
  const isOval = (sport === 'cricket_oval');
  
  let baseArea = Math.round(L * W);
  let perim    = Math.round(2 * (L + W));

  if (isTrainingNet) {
    perim = Math.round((2 * L) + W); // 3 sides enclosed, open front
  } else if (isCircular) {
    const r = L / 2;
    baseArea = Math.round(Math.PI * r * r);
    perim = Math.round(2 * Math.PI * r);
  } else if (isOval) {
    const a = L / 2;
    const b = W / 2;
    baseArea = Math.round(Math.PI * a * b);
    perim = Math.round(Math.PI * (3 * (a + b) - Math.sqrt((3 * a + b) * (a + 3 * b))));
  }
  
  const nL = Math.max(1, Math.round(L / 15.0));
  const nW = Math.max(1, Math.round(W / 15.0));
  const bayW = Math.round((L / nL) * 10) / 10;
  let totalPoles = 4 + 2 * (nL - 1) + 2 * (nW - 1);
  if (isTrainingNet) {
    totalPoles = 4 + 2 * (nL - 1) + (nW - 1);
  }
  if (isMultiZone) {
    const structL = 145;
    perim = Math.round(2 * (structL + W)); // 410 RFT (145x60 Structure)
    totalPoles = 28; // 10 North + 10 South + 4 West end + 4 East front wall at 145 FT
  }
  
  // Dynamic horizontal tie tier count:
  // For <= 10 FT: 2 tiers only (top perimeter + bottom runner; no middle runner)
  // For > 10 FT: 3+ tiers with middle support runner(s)
  let tierCount = 4;
  if (H <= 10)      tierCount = 2;
  else if (H <= 16) tierCount = 3;
  else if (H <= 20) tierCount = 4;
  else if (H <= 24) tierCount = 5;
  else if (H <= 28) tierCount = 6;
  else if (H <= 32) tierCount = 7;
  else              tierCount = 8;

  let totalPipesRft = Math.round(perim * tierCount);
  if (isMultiZone) {
    totalPipesRft = 2870; // 410 RFT * 7 tiers = 2,870 RFT
  }
  const pipe20ftCount = isMultiZone ? 144 : Math.ceil(totalPipesRft / 20);
  
  let perimNet = Math.round(perim * H * 1.05);
  let topNet   = hasRoof ? Math.round(baseArea * 1.05) : 0;
  let totalNet = perimNet + topNet;
  if (isMultiZone) {
    perimNet = 10080; // Zone A perimeter net
    topNet   = 6000;  // Zone A 1.5mm 40mm overhead mesh
    totalNet = 16080;
  }
  
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
  else if (civilBed === 'client_scope' || civilBed === 'none') civilAdj = -40;

  const isSmallCourt = (baseArea <= 1500) || isTrainingNet;
  const turf = document.getElementById('tk_turf_type')?.value || 'infill_turf';
  const hasTurf = (turf !== 'none' && turf !== 'no_turf' && !turf.includes('none'));

  let pkgBaseRates;
  if (isSmallCourt) {
    if (civilBed === 'none' || civilBed === 'client_scope') {
      pkgBaseRates = {
        essential: hasTurf ? 180 : 140,
        commercial: hasTurf ? 220 : 168,
        premium_pro: hasTurf ? 260 : 205
      };
    } else {
      const baseCom = (hasRoof ? 250 : 230) + civilAdj;
      pkgBaseRates = {
        essential: Math.round(baseCom * 0.82),
        commercial: baseCom,
        premium_pro: Math.round(baseCom * 1.18)
      };
    }
  } else {
    pkgBaseRates = {
      essential: (hasRoof ? 250 : 240) + Math.round(civilAdj * 0.8),
      commercial: (hasRoof ? 300 : 285) + civilAdj,
      premium_pro: (hasRoof ? 350 : 330) + Math.round(civilAdj * 1.2)
    };
  }
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
  
  // Note: sport already declared at top of syncTurnkeyBadgesOnly
  const isCricket = sport.includes('cricket') || sport.includes('terrace') || sport.includes('rooftop') || sport.includes('multi') || turf.includes('cricket') || turf === 'cricket_dual_turf' || (parseFloat(document.getElementById('tk_pitch_length')?.value) > 0);
  let pitchL = parseFloat(document.getElementById('tk_pitch_length')?.value);
  if (isNaN(pitchL) || pitchL <= 0) {
    pitchL = (L < 48) ? Math.min(41.5, Math.max(10, L - 6)) : Math.min(82.0, Math.max(20, L - 6));
    if (document.getElementById('tk_pitch_length') && isCricket) {
      document.getElementById('tk_pitch_length').value = pitchL;
    }
  }
  
  // Dynamic Pitch helper text
  const pitchHelper = document.getElementById('tk_pitch_helper');
  if (pitchHelper && isCricket) {
    const rollTypeStr = (L < 48) ? 'Half Roll: 2.0M × 12.5M (6.5 FT × 41.5 FT = 270 SFT @ ₹78)' : 'Full Roll: 2.0M × 25.0M (6.5 FT × 82 FT = 540 SFT @ ₹78)';
    pitchHelper.innerHTML = `Width: <strong>6.5 FT</strong> | Laid: <strong>${pitchL} FT</strong> (3' setback) | ${rollTypeStr}`;
  }

  // Turf Rate calibration for badge preview
  let turfRateOffset = 0;
  if (turf.startsWith('landscape_25mm')) turfRateOffset = -35;
  else if (turf.startsWith('landscape_30mm')) turfRateOffset = -30;
  else if (turf.startsWith('landscape_35mm')) turfRateOffset = -25;
  else if (turf.startsWith('landscape_40mm')) turfRateOffset = -20;
  else if (turf.startsWith('sports_30mm'))    turfRateOffset = -10;
  else if (turf.startsWith('sports_50mm_12k')) turfRateOffset = -5;
  else if (turf.startsWith('sports_50mm_14k')) turfRateOffset = 0;
  else if (turf.startsWith('sports_50mm_16k')) turfRateOffset = 12;

  let pitchAdjAmt = 0;
  if (isCricket && pitchL > 0) {
    const pitchMatSft = (L < 48) ? 270 : 540;
    const pitchInstArea = pitchL * 6.5;
    pitchAdjAmt = Math.round((pitchMatSft * 78) + (pitchInstArea * 9) - (pitchInstArea * 35));
  }
  
  const dH = isSmallCourt ? Math.max(0, H - 10.0) : (H - 20.0);
  const dT = isSmallCourt ? Math.max(0, tierCount - 2) : Math.max(0, tierCount - 4);
  const targetTotal = Math.round((baseArea * baseRate) + (dH * ftRate) + (dT * tierRate) + pitchAdjAmt);
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
  const turfStyle = document.getElementById('tk_turf_type')?.value || 'landscape_40mm';
  const z1OutfieldEl = document.getElementById('z1OutfieldText');
  if (z1OutfieldEl) {
    const turfSel = document.getElementById('tk_turf_type');
    const turfOptText = turfSel ? (turfSel.options[turfSel.selectedIndex]?.text || 'Landscape Grass') : 'Landscape Grass';
    const isZeroInf = (turfStyle.includes('landscape') || turfStyle.includes('non_infill'));
    z1OutfieldEl.innerText = `5,460 SFT ${turfOptText} ${isZeroInf ? '(Zero Infill — No Sand/Rubber)' : '(Infill Required)'}`;
    z1OutfieldEl.className = isZeroInf ? 'text-success' : 'text-primary';
  }
  const is4Line   = (poleStyle === 'ladder_4line');
  const is2Line   = (poleStyle === 'ladder_2line' || poleStyle === 's3_ladder_truss');

  let poleLabel = totalPoles + ' Single Columns';
  if (is4Line) poleLabel = totalPoles + ' 4-Line Towers';
  else if (is2Line) poleLabel = totalPoles + ' Ladder Columns';

  let roofNetLabel = 'Side + Top Net';
  if (isMultiZone) roofNetLabel = 'Z1: 1.5mm Mesh | Z2: GI Roof';
  else if (roofStyle === 'open_top') roofNetLabel = 'Open Top (Side Only)';
  else if (roofStyle === 'light_cable_top') roofNetLabel = '1.5mm/24ply Cable Net';
  else if (roofStyle === 'pipe_top') roofNetLabel = 'Cross Pipes + Net';
  else if (roofStyle === 'truss_top') roofNetLabel = 'Trusses + Heavy Net';

  if (elArea)   elArea.textContent  = baseArea.toLocaleString('en-IN') + ' SFT';
  if (elPerim)  elPerim.textContent = isMultiZone ? '410 RFT Perim (145×60 Structure)' : (perim.toLocaleString('en-IN') + ' RFT Perim');
  if (elPoles)  elPoles.textContent = poleLabel;
  if (elSpace)  elSpace.textContent = isMultiZone ? '16.1 ft Bay Spacing (1.5\' Rungs)' : (bayW + ' ft Bay Spacing' + ((is2Line || is4Line) ? ' (1.5\' Rungs)' : ''));
  if (elPipes)  elPipes.textContent = totalPipesRft.toLocaleString('en-IN') + ' RFT';
  if (elTiers)  elTiers.textContent = tierCount + ' Tiers (' + pipe20ftCount + ' Pipes)';
  if (elNet)    elNet.textContent   = totalNet.toLocaleString('en-IN') + ' SFT';
  if (elNetDet) elNetDet.textContent = roofNetLabel;
  const gstRatio = 1.160308; // ~16.03% blended GST
  let rateInclGst = Math.round(effectiveRate * gstRatio * 100) / 100;
  let totalInclGst = Math.round(targetTotal * gstRatio);
  if (isMultiZone) {
    targetTotal = 3039207;
    effectiveRate = 273.80;
    rateInclGst = 317.53;
    totalInclGst = 3524566;
  }

  // Only use fallback formula if item table is completely empty
  const hasItemRows = document.querySelectorAll('.item-row').length > 0;
  if (!hasItemRows) {
    if (elTotal)  elTotal.innerHTML = '₹' + targetTotal.toLocaleString('en-IN') + ' <span class="fs-6 text-muted fw-normal">(Excl) &bull; ₹' + totalInclGst.toLocaleString('en-IN') + ' (Incl GST)</span>';
    if (elRate)   elRate.innerHTML  = '<strong>₹' + effectiveRate.toFixed(2) + ' / SFT (Excl. GST)</strong> &bull; <span class="text-primary-emphasis fw-bold">₹' + rateInclGst.toFixed(2) + ' / SFT (Incl. GST)</span>';

    const elPkgEss = document.getElementById('disp_pkg_rate_essential');
    const elPkgCom = document.getElementById('disp_pkg_rate_commercial');
    const elPkgPrem = document.getElementById('disp_pkg_rate_premium');
    
    const essExcl = Math.round(effectiveRate * 0.88 * 100) / 100;
    const essIncl = Math.round(essExcl * gstRatio * 100) / 100;
    const comExcl = Math.round(effectiveRate * 100) / 100;
    const comIncl = Math.round(comExcl * gstRatio * 100) / 100;
    const premExcl = Math.round(effectiveRate * 1.19 * 100) / 100;
    const premIncl = Math.round(premExcl * gstRatio * 100) / 100;

    if (elPkgEss) elPkgEss.innerHTML = '₹' + essExcl.toFixed(2) + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + essIncl.toFixed(2) + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)</span>';
    if (elPkgCom) elPkgCom.innerHTML = '₹' + comExcl.toFixed(2) + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + comIncl.toFixed(2) + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl) ★</span>';
    if (elPkgPrem) elPkgPrem.innerHTML = '₹' + premExcl.toFixed(2) + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + premIncl.toFixed(2) + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)</span>';
  }

  // Also update live multi-zone cost badges if multi-zone is active
  const z1ExclRate = Math.round(effectiveRate * 0.9515 * 100) / 100;
  const z1InclRate = Math.round(z1ExclRate * 1.149 * 100) / 100;
  const z1Area = Math.round((parseFloat(document.getElementById('mz_len_1')?.value) || 100) * W);
  const z1TotalIncl = Math.round(z1InclRate * z1Area);

  const z2ExclRate = Math.round(effectiveRate * 1.2078 * 100) / 100;
  const z2InclRate = Math.round(z2ExclRate * 1.173 * 100) / 100;
  const z2Area = Math.round((parseFloat(document.getElementById('mz_len_2')?.value) || 45) * W);
  const z2TotalIncl = Math.round(z2InclRate * z2Area);

  const z3ExclRate = Math.round(effectiveRate * 0.8871 * 100) / 100;
  const z3InclRate = Math.round(z3ExclRate * 1.180 * 100) / 100;
  const remL = Math.max(0, L - (parseFloat(document.getElementById('mz_len_1')?.value) || 100) - (parseFloat(document.getElementById('mz_len_2')?.value) || 45));
  const z3Area = Math.round(remL * W);
  const z3TotalIncl = Math.round(z3InclRate * z3Area);

  const z1CostEl = document.getElementById('z1CostBadge');
  if (z1CostEl) z1CostEl.innerHTML = `₹${z1ExclRate.toFixed(2)}/SFT (Excl) &bull; <strong>₹${z1InclRate.toFixed(2)}/SFT</strong> (Incl) &bull; Total: <strong>₹${(z1TotalIncl/100000).toFixed(2)}L</strong>`;

  const z2CostEl = document.getElementById('z2CostBadge');
  if (z2CostEl) z2CostEl.innerHTML = `₹${z2ExclRate.toFixed(2)}/SFT (Excl) &bull; <strong>₹${z2InclRate.toFixed(2)}/SFT</strong> (Incl) &bull; Total: <strong>₹${(z2TotalIncl/100000).toFixed(2)}L</strong> (Per Court: <strong>₹${(z2TotalIncl/200000).toFixed(2)}L</strong>)`;

  const z3CostEl = document.getElementById('z3CostBadge');
  if (z3CostEl) z3CostEl.innerHTML = `₹${z3ExclRate.toFixed(2)}/SFT (Excl) &bull; <strong>₹${z3InclRate.toFixed(2)}/SFT</strong> (Incl) &bull; Total: <strong>₹${(z3TotalIncl/100000).toFixed(2)}L</strong>`;
  
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

function getOpenSidesMap() {
  return turnkeySidesState;
}

function onSideEnclosureChange() {
  const frontOpen = !document.getElementById('tk_side_front')?.checked;
  const lbl = document.getElementById('tk_side_front_lbl');
  if (lbl) {
    lbl.innerHTML = frontOpen ? '🏃 Front (Open Bowler Portal)' : 'Front Net (Enclosed)';
    lbl.className = 'form-check-label fw-bold ' + (frontOpen ? 'text-primary' : 'text-dark');
  }
  updateTurnkeyCalculations(true);
}

function updateTurnkeyPackageButtonLabels(packages) {
  if (!packages) return;
  const curPkg = document.querySelector('input[name="tk_package"]:checked')?.value || 'commercial';

  const elPkgEss  = document.getElementById('disp_pkg_rate_essential');
  const elPkgCom  = document.getElementById('disp_pkg_rate_commercial');
  const elPkgPrem = document.getElementById('disp_pkg_rate_premium');

  if (packages.essential && elPkgEss) {
    const rExcl = parseFloat(packages.essential.target_rate || 266.58);
    const rIncl = Math.round(rExcl * 1.18 * 100) / 100;
    elPkgEss.innerHTML = '₹' + rExcl.toFixed(2) + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + rIncl.toFixed(2) + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' + (curPkg === 'essential' ? ' ★' : '') + '</span>';
  }
  if (packages.commercial && elPkgCom) {
    const rExcl = parseFloat(packages.commercial.target_rate || 302.93);
    const rIncl = Math.round(rExcl * 1.18 * 100) / 100;
    elPkgCom.innerHTML = '₹' + rExcl.toFixed(2) + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + rIncl.toFixed(2) + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' + (curPkg === 'commercial' ? ' ★' : '') + '</span>';
  }
  if (packages.premium_pro && elPkgPrem) {
    const rExcl = parseFloat(packages.premium_pro.target_rate || 360.49);
    const rIncl = Math.round(rExcl * 1.18 * 100) / 100;
    elPkgPrem.innerHTML = '₹' + rExcl.toFixed(2) + '/SFT <span style="font-size:7pt;opacity:.85;">(Excl)</span> &bull; <strong>₹' + rIncl.toFixed(2) + '</strong> <span style="font-size:7pt;opacity:.85;">(Incl)' + (curPkg === 'premium_pro' ? ' ★' : '') + '</span>';
  }
}

function generateTurnkeyBOQAndPopulate(isAuto = false) {
  const estSubtypeVal = document.querySelector('input[name="estimation_subtype"]:checked')?.value;
  const hasTurnkeyBox = !!document.getElementById('turnkeyVisualizerBox');
  const isTurnkeySubtype = (estSubtypeVal === 'turnkey_court');
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
  const badFloor  = document.getElementById('mz_badminton_floor')?.value || 'bwf_vinyl_4_5mm';
  const badBed    = document.getElementById('mz_badminton_bed')?.value || 'rcc_m20_4inch_laser';
  fd.append('badminton_floor_type', badFloor);
  fd.append('badminton_bed_type', badBed);
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
  const gateCount = parseInt(document.getElementById('tk_gate_count')?.value || 0);
  fd.append('gate_count', gateCount);
  fd.append('open_sides', JSON.stringify(turnkeySidesState));
  fd.append('layers', JSON.stringify(turnkeyLayersState));
  fd.append('equipment', JSON.stringify(turnkeyEquipmentState));
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
      if (boq.packages) {
        window.cachedTurnkeyPackages = boq.packages;
        updateTurnkeyPackageButtonLabels(boq.packages);
      }
      refreshLiveCourtViewer();
      
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
        gates: (typeof turnkeyGatesList !== 'undefined') ? turnkeyGatesList : [],
        sport_type: sport,
        roof_type: roof,
        package: pkg,
        pole_style: poleStyle,
        pipe_size: pipeSize,
        pipe_gauge: pipeGauge,
        turf_type: turfType,
        civil_bed_type: civilBed,
        open_sides: turnkeySidesState,
    zones: (sport.includes("multisport") || sport === "multisport_arena_complex_185x60") ? {
      cricket: { length: parseFloat(document.getElementById("mz_len_1")?.value || document.getElementById("mz_cricket_l")?.value) || 100 },
      badminton: { length: parseFloat(document.getElementById("mz_len_2")?.value || document.getElementById("mz_badminton_l")?.value) || 45 },
      utility: { length: parseFloat(document.getElementById("mz_len_3")?.value || document.getElementById("mz_utility_l")?.value) || 40 },
      zone4: document.getElementById("mz_len_4") ? { length: parseFloat(document.getElementById("mz_len_4").value) || 20 } : null,
      zone5: document.getElementById("mz_len_5") ? { length: parseFloat(document.getElementById("mz_len_5").value) || 20 } : null
    } : null,
        layers: turnkeyLayersState,
        equipment: turnkeyEquipmentState,
    show_roof_sheets: isRoofSheetsVisible,
        target_rate: boq.selected_pkg?.target_rate,
        target_total: boq.target_total,
        specs: boq.specs,
        excluded_items: turnkeyExcludedItems,
      };
      const cfgInput = document.getElementById('turnkeyConfigJson');
      if (cfgInput) cfgInput.value = JSON.stringify(configObj);
      const sidesInp = document.getElementById('tkOpenSidesInput');
      if (sidesInp) sidesInp.value = JSON.stringify(turnkeySidesState);
      const layersInp = document.getElementById('tkLayersInput');
      if (layersInp) layersInp.value = JSON.stringify(turnkeyLayersState);
      const eqInp = document.getElementById('tkEquipmentInput');
      if (eqInp) eqInp.value = JSON.stringify(turnkeyEquipmentState);
      
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
      // PROTECTION RULE 1: Never overwrite invoices, tax invoices, or bills
      if (existingDocType === 'invoice' || existingDocType === 'tax_invoice' || existingDocType === 'bill' || (existingDocId && (existingDocId.startsWith('INV-') || existingDocId.startsWith('BILL-')))) {
        console.log('Turnkey BOQ population blocked for invoice/bill ' + existingDocId);
        return;
      }



      // PROTECTION: Never wipe table if activeItems is empty
      if (!activeItems || activeItems.length === 0) {
        console.warn('Turnkey BOQ generated 0 active items - aborting table reset to prevent wiping items.');
        return;
      }
      // If editing an existing document and this was an automatic background sync (isAuto === true),
      // preserve existing customized items unless the user explicitly clicked "Re-Populate Turnkey BOQ" button
      const curRows = document.querySelectorAll('#itemsTbody .item-row');
      if (isAuto && isExistingDocument && curRows.length > 0) {
        console.log('Background auto-calculation skipped table overwrite to protect existing document items.');
        return;
      }

      const tbody = document.getElementById('itemsTbody');
      tbody.innerHTML = '';
      rowIdx = 0;
      
      activeItems.forEach((item, idx) => {
        const itemUid = getNextItemUid();
        const trHtml = `
          <tr class="item-row" data-item-uid="${itemUid}" data-row="${idx}">
            <td><textarea name="item_desc[${idx}]" class="form-control form-control-sm item-desc-field" rows="3" required oninput="autoResizeItemDesc(this)">${item.description.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea></td>
            <td><input type="text" name="item_hsn[${idx}]" class="form-control form-control-sm item-hsn" value="${item.hsn}"></td>
            <td><input type="number" name="item_qty[${idx}]" class="form-control form-control-sm item-qty" oninput="calcTotals()" onchange="calcTotals()" value="${item.qty}" step="0.01" min="0"></td>
            <td>
              <select name="item_unit[${idx}]" class="form-select form-select-sm item-unit-select">
                ${getUnitOptions(item.unit)}
              </select>
            </td>
            <td><input type="number" name="item_rate[${idx}]" class="form-control form-control-sm item-rate" oninput="calcTotals()" onchange="calcTotals()" value="${item.rate}" step="0.01" min="0"></td>
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
      syncEquipmentWithActiveRows();
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

<?php
  $initPkgData = null;
  if (!empty($d['turnkey_config'])) {
      require_once __DIR__ . '/includes/turnkey_calculator.php';
      $tc = $d['turnkey_config'];
      $initBOQ = generateTurnkeyBOQ(
          (float)($tc['length'] ?? 90),
          (float)($tc['width'] ?? 60),
          (float)($tc['height'] ?? 25),
          $tc['sport_type'] ?? 'box_cricket',
          $tc['roof_type'] ?? 'light_cable_top',
          $tc['package'] ?? 'commercial'
      );
      if (!empty($initBOQ['packages'])) {
          $initPkgData = $initBOQ['packages'];
      }
  }
?>
window.cachedTurnkeyPackages = <?= json_encode($initPkgData) ?>;

// Initial badge sync & visualizer initialization on load
const _initSubtype = document.querySelector('input[name="estimation_subtype"]:checked')?.value;
const _hasTkBox = !!document.getElementById('turnkeyVisualizerBox');
if (_initSubtype === 'turnkey_court') {
  try {
    const cfgInput = document.getElementById('turnkeyConfigJson');
    if (cfgInput && cfgInput.value) {
      const parsed = JSON.parse(cfgInput.value);
      if (parsed && parsed.open_sides) {
        const s = parsed.open_sides;
        if (s.front && s.left && s.right && s.back && s.top) {
          turnkeySidesState = { front: true, back: false, left: false, right: false, top: false };
        } else {
          turnkeySidesState = Object.assign(turnkeySidesState, parsed.open_sides);
        }
      } else {
        const sp = document.getElementById('tk_sport_type')?.value || '';
        if (sp.includes('training_net') || sp.includes('cricket_training')) {
          turnkeySidesState.front = true;
        }
      }
      if (parsed && parsed.layers) {
        turnkeyLayersState = Object.assign(turnkeyLayersState, parsed.layers);
      }
      if (parsed && parsed.equipment) {
        turnkeyEquipmentState = Object.assign(turnkeyEquipmentState, parsed.equipment);
      }
    }
  } catch (e) {}

  updateSideButtonsUI();
  syncLayersWithActiveRows();
  updateLayerButtonsUI();
  syncEquipmentWithActiveRows();
  updateEquipmentButtonsUI();
  syncTurnkeyBadgesOnly();
  if (window.cachedTurnkeyPackages && typeof updateTurnkeyPackageButtonLabels === 'function') {
    updateTurnkeyPackageButtonLabels(window.cachedTurnkeyPackages);
  }
  setTimeout(refreshLiveCourtViewer, 150);
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
  let formHasChanges = false;
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
    if (document.hidden) return;
    if (!formHasChanges) return;
    isSaving = true;
    if (icon) icon.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i>';
    showBar('Auto-saving draft...', 'yellow');

    reindexRows();
    calcTotals();

    const sc = document.getElementById('same_as_mobile');
    const pi = document.getElementById('bt_phone');
    const wi = document.getElementById('bt_whatsapp');
    if (sc && sc.checked && pi && wi) {
      wi.value = pi.value;
    }

    if (typeof serializeFullTurnkeyConfig === 'function') {
      serializeFullTurnkeyConfig();
    }
    if (typeof syncMultiBlocksToForm === 'function' && document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'multi_block') {
      syncMultiBlocksToForm();
    }

    const form = document.getElementById('docForm');
    if (!form) { isSaving = false; return; }
    const data = new FormData(form);
    const shipChk = document.getElementById('show_shipping_address');
    data.set('show_shipping_address', (shipChk && shipChk.checked) ? '1' : '0');
    data.set('action', 'autosave');
    if (idIn && idIn.value) data.set('autosave_id', idIn.value);

    fetch('ajax.php', { method: 'POST', body: data })
      .then(r => r.json())
      .then(res => {
        isSaving = false;
        if (res.ok) {
          formHasChanges = false;
          if (idIn && res.id) idIn.value = res.id;
          lastSaved = res.saved_at || res.time || new Date().toLocaleTimeString();
          if (icon) icon.innerHTML = '<i class="fa fa-circle-check text-success"></i>';
          showBar('Draft auto-saved at ' + lastSaved + ' &nbsp;<a href="view.php?id=' + encodeURIComponent(res.id || (idIn ? idIn.value : '')) + '" target="_blank" style="color:inherit;font-weight:700;text-decoration:underline;">View estimation →</a>', 'green');
        } else {
          if (icon) icon.innerHTML = '<i class="fa fa-exclamation-triangle text-danger"></i>';
          showBar('Auto-save error: ' + (res.msg || 'Check input fields'), 'red');
        }
      })
      .catch((err) => {
        console.error('Autosave error:', err);
        isSaving = false;
        if (icon) icon.innerHTML = '<i class="fa fa-exclamation-triangle text-warning"></i>';
        showBar('Auto-save network pause. Data is safe in browser.', 'yellow');
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
      if (document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'multi_block') {
        if (typeof syncMultiBlocksToForm === 'function') {
          syncMultiBlocksToForm();
        }
      }
      if (document.querySelector('input[name="estimation_subtype"]:checked')?.value === 'turnkey_court') {
        const sidesInp = document.getElementById('tkOpenSidesInput');
        if (sidesInp) sidesInp.value = JSON.stringify(turnkeySidesState);
        const layersInp = document.getElementById('tkLayersInput');
        if (layersInp) layersInp.value = JSON.stringify(turnkeyLayersState);
        const eqInp = document.getElementById('tkEquipmentInput');
        if (eqInp) eqInp.value = JSON.stringify(turnkeyEquipmentState);
        const cfgInp = document.getElementById('turnkeyConfigJson');
        if (cfgInp && cfgInp.value) {
          try {
            const cur = JSON.parse(cfgInp.value);
            cur.open_sides = turnkeySidesState;
            cur.layers = turnkeyLayersState;
            cur.equipment = turnkeyEquipmentState;
            cfgInp.value = JSON.stringify(cur);
          } catch(err) {}
        }
      }
      if (sameCheckEl && sameCheckEl.checked && phoneInputEl && waInputEl) {
        waInputEl.value = phoneInputEl.value;
      }
      const pVal = phoneInputEl ? phoneInputEl.value.trim() : '';
      const wVal = waInputEl ? waInputEl.value.trim() : '';

      const isDomestic = (document.getElementById('bt_country')?.value || 'India') === 'India';
      const minDigits = isDomestic ? 10 : 5;

      if (!pVal || pVal.length < minDigits) {
        showCustomToast(isDomestic 
          ? 'Please enter a valid 10-digit Customer Mobile Number.' 
          : 'Please enter a valid Customer Mobile / Phone Number (minimum 5 digits).', 'danger');
        if (phoneInputEl) {
          phoneInputEl.focus();
          phoneInputEl.classList.add('is-invalid');
        }
        e.preventDefault();
        return false;
      }

      if (!wVal || wVal.length < minDigits) {
        showCustomToast(isDomestic 
          ? 'Please enter a valid 10-digit WhatsApp Number (or check "Same as Mobile").' 
          : 'Please enter a valid WhatsApp Number (or check "Same as Mobile").', 'danger');
        if (waInputEl) {
          waInputEl.focus();
          waInputEl.classList.add('is-invalid');
        }
        e.preventDefault();
        return false;
      }
    });
  }

  formHasChanges = false;
  function markDirtyAndSchedule() {
    formHasChanges = true;
    scheduleAutosave();
  }

  // Trigger only when user actually inputs/changes data
  const dForm = document.getElementById('docForm');
  if (dForm) {
    dForm.addEventListener('input', markDirtyAndSchedule);
    dForm.addEventListener('change', markDirtyAndSchedule);
  }

  // Reload page if user navigates back using browser cache (bfcache)
  window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
      window.location.reload();
    }
  });

  // Initial re-indexing on page load
  reindexRows();
})();

// ── Real-Time Auto-Save Functionality ──────────────────────────────────────────
let _autoSaveTimer = null;
function triggerAutoSave() {
  if (typeof scheduleAutosave === 'function') {
    scheduleAutosave();
  }
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
    // When marked as Total Paid on invoice/bill, auto-set date to today
    const docType = document.querySelector('input[name=type]:checked')?.value || '';
    if (docType === 'tax_invoice' || docType === 'bill') {
      const dateInp = document.querySelector('input[name=date]');
      if (dateInp) {
        const todayStr = new Date().toISOString().split('T')[0];
        dateInp.value = todayStr;
      }
    }
  } else if (statusSelect && statusSelect.value === 'paid') {
    statusSelect.value = 'draft';
  }
  calcTotals();
}


function toggleEwayBillBox() {
  const isEnabled = document.getElementById('eway_enabled')?.checked;
  const box = document.getElementById('ewayBillDetailsBox');
  const lbl = document.getElementById('lblEwayToggle');
  if (box) box.style.display = isEnabled ? 'block' : 'none';
  if (lbl) lbl.textContent = isEnabled ? 'E-Way Bill Active' : 'Enable E-Way Bill Details';
}

function toggleShipAddress() {
  const isEnabled = document.getElementById('show_shipping_address')?.checked;
  const box = document.getElementById('shipAddressBox');
  const lbl = document.getElementById('lblShipToggle');
  if (box) box.style.display = isEnabled ? 'block' : 'none';
  if (lbl) lbl.textContent = isEnabled ? 'Enabled on Document' : 'Disabled (Click to Enable)';
  if (isEnabled) {
    const addr = document.getElementById('st_address');
    if (addr && !addr.value.trim()) {
      copyBillingToShipping();
    }
  }
  if (typeof scheduleAutosave === 'function') {
    scheduleAutosave();
  }
}

function copyBillingToShipping() {
  const btName = document.getElementById('bt_name')?.value || '';
  const btAddr = document.getElementById('bt_address')?.value || '';
  const btPhone = document.getElementById('bt_phone')?.value || '';
  const btState = document.getElementById('bt_state')?.value || '';

  const stName = document.getElementById('st_name');
  const stAddr = document.getElementById('st_address');
  const stPhone = document.getElementById('st_phone');
  const stState = document.getElementById('st_state');

  if (stName && !stName.value.trim()) stName.value = btName;
  if (stAddr) stAddr.value = btAddr;
  if (stPhone && !stPhone.value.trim()) stPhone.value = btPhone;
  if (stState && !stState.value.trim()) stState.value = btState;

  const toggle = document.getElementById('show_shipping_address');
  if (toggle && !toggle.checked) {
    toggle.checked = true;
    toggleShipAddress();
  }
}

function clearShippingAddress() {
  const stName = document.getElementById('st_name');
  const stAddr = document.getElementById('st_address');
  const stPhone = document.getElementById('st_phone');
  const stState = document.getElementById('st_state');
  if (stName) stName.value = '';
  if (stAddr) stAddr.value = '';
  if (stPhone) stPhone.value = '';
  if (stState) stState.value = '';
}

</script>

<script src="assets/js/hsn_sac_master.js?v=<?= time() ?>"></script>
<script>
// --- GCM Universal Product, HSN & GST Registered Master Engine ---
(function() {
  let activeDropdown = null;
  let activeInput = null;

  function removeDropdown() {
    if (activeDropdown) {
      activeDropdown.remove();
      activeDropdown = null;
      activeInput = null;
    }
  }

  // ── 1. PRODUCT & HSN AUTO-SUGGEST DROPDOWN ──
  function createProductDropdown(inputEl, matches, isHsnField = false) {
    removeDropdown();
    if (!matches || matches.length === 0) return;

    activeInput = inputEl;
    const rect = inputEl.getBoundingClientRect();
    const dd = document.createElement('div');
    dd.className = 'product-suggest-menu shadow-lg border rounded-3';
    dd.style.cssText = `
      position: absolute;
      top: ${rect.bottom + window.scrollY + 2}px;
      left: ${rect.left + window.scrollX}px;
      width: ${Math.max(rect.width, 420)}px;
      max-height: 320px;
      overflow-y: auto;
      background: #ffffff;
      z-index: 99999;
      font-size: 0.83rem;
      border: 1.5px solid #0d1b3e;
      box-shadow: 0 12px 28px rgba(0,0,0,0.22);
    `;

    matches.slice(0, 15).forEach((item, idx) => {
      const opt = document.createElement('div');
      opt.className = 'product-opt d-flex align-items-center justify-content-between p-2 border-bottom';
      opt.style.cssText = 'cursor: pointer; transition: background 0.15s ease;';

      const catBadge = item.category 
        ? `<span class="badge bg-primary-subtle text-primary border border-primary px-1" style="font-size:0.68rem;">${item.category}</span>`
        : '';
      const hsnBadge = item.code 
        ? `<span class="badge bg-dark font-monospace" style="font-size:0.72rem;">HSN ${item.code}</span>` 
        : '';
      const gstBadge = item.gst_rate !== undefined 
        ? `<span class="badge bg-warning text-dark fw-bold" style="font-size:0.7rem;">${item.gst_rate}% GST</span>` 
        : '';
      const rateBadge = (item.rate && item.rate > 0)
        ? `<span class="badge bg-success-subtle text-success border border-success fw-bold" style="font-size:0.7rem;">₹${item.rate} / ${item.unit || 'SQFT'}</span>`
        : '';

      const displayName = item.name || item.description || item.code;
      const displayDesc = (item.description && item.description !== item.name) ? item.description : '';

      opt.innerHTML = `
        <div style="flex:1; padding-right:8px;">
          <div class="d-flex align-items-center flex-wrap gap-1 mb-1">
            ${hsnBadge}
            ${gstBadge}
            ${rateBadge}
            ${catBadge}
          </div>
          <div class="text-dark fw-bold" style="line-height:1.25;">${displayName}</div>
          ${displayDesc ? `<div class="text-muted small mt-1" style="line-height:1.2;font-size:0.74rem;">${displayDesc.substring(0, 95)}...</div>` : ''}
        </div>
        <i class="fa fa-arrow-turn-down text-primary" style="font-size:0.85rem;"></i>
      `;

      opt.addEventListener('mouseenter', () => { opt.style.backgroundColor = '#eff6ff'; });
      opt.addEventListener('mouseleave', () => { opt.style.backgroundColor = '#ffffff'; });

      opt.addEventListener('mousedown', (e) => {
        e.preventDefault();
        applyProductSelection(inputEl, item);
        removeDropdown();
      });

      dd.appendChild(opt);
    });

    document.body.appendChild(dd);
    activeDropdown = dd;
  }

  function applyProductSelection(inputEl, item) {
    const row = inputEl.closest('.item-row');
    if (!row) return;

    // 1. Description
    const descField = row.querySelector('.item-desc-field');
    if (descField) {
      descField.value = item.name || item.description || item.code;
      if (typeof autoResizeItemDesc === 'function') autoResizeItemDesc(descField);
    }

    // 2. HSN
    const hsnInp = row.querySelector('input[name^="item_hsn"]');
    if (hsnInp) hsnInp.value = item.hsn || item.code || '';

    // 3. GST Rate %
    const gstInp = row.querySelector('input[name^="item_gst_rate"]');
    if (gstInp && item.gst_rate !== undefined) {
      gstInp.value = item.gst_rate;
    }

    // 4. Unit
    if (item.unit) {
      const unitSel = row.querySelector('select[name^="item_unit"]');
      if (unitSel) {
        const u = item.unit.toUpperCase();
        let found = false;
        for (let i = 0; i < unitSel.options.length; i++) {
          if (unitSel.options[i].value.toUpperCase() === u) {
            unitSel.selectedIndex = i;
            found = true;
            break;
          }
        }
        if (!found) {
          const opt = new Option(u, u, true, true);
          unitSel.add(opt);
        }
      }
    }

    // 5. Rate
    const rateInp = row.querySelector('input[name^="item_rate"]');
    if (rateInp) {
      const defaultRate = item.default_rate !== undefined ? item.default_rate : (item.rate !== undefined ? item.rate : null);
      if (defaultRate && (!rateInp.value || parseFloat(rateInp.value) === 0)) {
        rateInp.value = defaultRate;
      }
    }

    // 6. Quantity (default to 1 if empty or 0)
    const qtyInp = row.querySelector('input[name^="item_qty"]');
    if (qtyInp && (!qtyInp.value || parseFloat(qtyInp.value) === 0)) {
      qtyInp.value = 1;
    }

    // 7. Auto-populate specifications if item has specs
    if (Array.isArray(item.specs) && item.specs.length > 0) {
      const uid = row.dataset.itemUid;
      const idx = row.dataset.row;
      // Clear empty spec rows for this row
      document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`).forEach(s => {
        const inp = s.querySelector('.spec-input');
        if (!inp || !inp.value.trim()) s.remove();
      });
      let lastEl = row;
      const curSpecs = document.querySelectorAll(`#itemsTbody .spec-row[data-parent-uid="${uid}"]`);
      if (curSpecs.length > 0) lastEl = curSpecs[curSpecs.length - 1];
      item.specs.forEach(sp => {
        if (typeof getSpecHtml === 'function') {
          lastEl.insertAdjacentHTML('afterend', getSpecHtml(uid, idx));
          const added = lastEl.nextElementSibling;
          if (added) {
            const sInp = added.querySelector('.spec-input');
            if (sInp) sInp.value = sp;
            lastEl = added;
          }
        }
      });
      if (typeof reindexRows === 'function') reindexRows();
    }

    // Recalculate totals
    if (typeof calcTotals === 'function') calcTotals();
    inputEl.focus();
  }

  function searchCatalog(query) {
    if (!query) return [];
    const q = query.toLowerCase().trim();
    if (window.HsnSacMaster && Array.isArray(window.HsnSacMaster)) {
      return window.HsnSacMaster.filter(m => {
        const codeMatch = (m.code || '').toLowerCase().includes(q);
        const nameMatch = (m.name || '').toLowerCase().includes(q);
        const descMatch = (m.description || '').toLowerCase().includes(q);
        const catMatch  = (m.category || '').toLowerCase().includes(q);
        return codeMatch || nameMatch || descMatch || catMatch;
      });
    }
    return [];
  }

  // Description input listener
  document.addEventListener('input', function(e) {
    if (e.target && e.target.classList.contains('item-desc-field')) {
      const val = e.target.value.trim();
      if (val.length >= 2) {
        const matches = searchCatalog(val);
        createProductDropdown(e.target, matches, false);
      } else {
        removeDropdown();
      }
    }
    if (e.target && e.target.classList.contains('item-hsn')) {
      const val = e.target.value.trim();
      if (val.length >= 2) {
        const matches = searchCatalog(val);
        createProductDropdown(e.target, matches, true);
      } else {
        removeDropdown();
      }
    }
  });

  document.addEventListener('click', function(e) {
    if (activeDropdown && !activeDropdown.contains(e.target) && e.target !== activeInput) {
      removeDropdown();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') removeDropdown();
  });

  // ── 2. DASHBOARD GST REGISTERED ENTITY SEARCH & AUTO-FILL ──
  const btName = document.getElementById('bt_name');
  const btGstin = document.getElementById('bt_gstin');

  function createCustomerDropdown(inputEl, matches) {
    removeDropdown();
    if (!matches || matches.length === 0) return;

    activeInput = inputEl;
    const rect = inputEl.getBoundingClientRect();
    const dd = document.createElement('div');
    dd.className = 'gst-suggest-menu shadow-lg border rounded-3';
    dd.style.cssText = `
      position: absolute;
      top: ${rect.bottom + window.scrollY + 2}px;
      left: ${rect.left + window.scrollX}px;
      width: ${Math.max(rect.width, 380)}px;
      max-height: 280px;
      overflow-y: auto;
      background: #ffffff;
      z-index: 99999;
      font-size: 0.83rem;
      border: 1.5px solid #2563eb;
      box-shadow: 0 12px 28px rgba(0,0,0,0.2);
    `;

    matches.forEach(c => {
      const opt = document.createElement('div');
      opt.className = 'p-2 border-bottom customer-opt';
      opt.style.cssText = 'cursor: pointer; transition: background 0.15s ease;';

      const gstBadge = c.gstin 
        ? `<span class="badge bg-success-subtle text-success border border-success fw-bold font-monospace" style="font-size:0.72rem;">GSTIN: ${c.gstin}</span>`
        : '<span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem;">Unregistered</span>';

      const stateBadge = c.state ? `<span class="badge bg-light text-dark border px-1" style="font-size:0.68rem;">${c.state}</span>` : '';
      const phoneBadge = c.phone ? `<span class="badge bg-primary-subtle text-primary border border-primary px-1" style="font-size:0.68rem;"><i class="fa fa-phone me-1"></i>${c.phone}</span>` : '';

      opt.innerHTML = `
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="fw-bold text-dark">${c.name}</span>
          ${gstBadge}
        </div>
        <div class="d-flex align-items-center flex-wrap gap-1">
          ${phoneBadge}
          ${stateBadge}
          ${c.address ? `<span class="text-muted small" style="font-size:0.72rem;">${c.address.substring(0, 45)}...</span>` : ''}
        </div>
      `;

      opt.addEventListener('mouseenter', () => { opt.style.backgroundColor = '#eff6ff'; });
      opt.addEventListener('mouseleave', () => { opt.style.backgroundColor = '#ffffff'; });

      opt.addEventListener('mousedown', (e) => {
        e.preventDefault();
        applyCustomerSelection(c);
        removeDropdown();
      });

      dd.appendChild(opt);
    });

    document.body.appendChild(dd);
    activeDropdown = dd;
  }

  function applyCustomerSelection(c) {
    if (btName && c.name) btName.value = c.name;
    if (btGstin && c.gstin) btGstin.value = c.gstin;

    const phoneIn = document.getElementById('bt_phone');
    if (phoneIn && c.phone) phoneIn.value = c.phone;

    const waIn = document.getElementById('bt_whatsapp');
    if (waIn && (c.whatsapp || c.phone)) waIn.value = c.whatsapp || c.phone;

    const addrIn = document.getElementById('bt_address');
    if (addrIn && c.address) addrIn.value = c.address;

    const stateIn = document.getElementById('bt_state');
    if (stateIn && c.state) stateIn.value = c.state;

    // Auto-select intra-state vs inter-state
    const isTelangana = (c.state_code === '36') || 
                        (c.gstin && c.gstin.startsWith('36')) || 
                        (c.state && c.state.toLowerCase().includes('telangana'));
    
    if (isTelangana) {
      const gmCs = document.getElementById('gm_cs');
      if (gmCs) gmCs.checked = true;
    } else if (c.gstin || c.state) {
      const gmIg = document.getElementById('gm_ig');
      if (gmIg) gmIg.checked = true;
    }

    const fb = document.getElementById('gstSearchFeedback');
    if (fb) {
      fb.style.display = 'block';
      fb.innerHTML = `<span class="badge bg-success text-white py-1 px-2"><i class="fa fa-check-circle me-1"></i>Found Registered Customer: <strong>${c.name}</strong> (${c.state || 'India'}) &mdash; Tax Mode: ${isTelangana ? 'CGST+SGST (Intra-State)' : 'IGST (Inter-State)'}</span>`;
    }

    if (typeof calcTotals === 'function') calcTotals();
  }

  // Live search when typing customer name
  if (btName) {
    btName.addEventListener('input', function() {
      const val = this.value.trim();
      if (val.length >= 2) {
        fetch(`ajax.php?action=gst_search&q=${encodeURIComponent(val)}`)
          .then(r => r.json())
          .then(res => {
            if (res.ok && res.matches && res.matches.length > 0) {
              createCustomerDropdown(btName, res.matches);
            } else {
              removeDropdown();
            }
          })
          .catch(() => removeDropdown());
      } else {
        removeDropdown();
      }
    });
  }

  // Global search GST function (called by button or paste)
  window.searchGstMaster = function() {
    const gVal = (btGstin ? btGstin.value.trim() : '') || (btName ? btName.value.trim() : '');
    const fb = document.getElementById('gstSearchFeedback');
    const btn = document.getElementById('btnGstSearch');

    if (!gVal) {
      if (fb) {
        fb.style.display = 'block';
        fb.innerHTML = '<span class="text-danger fw-semibold"><i class="fa fa-circle-exclamation me-1"></i>Please enter a GST Number or Company Name to search.</span>';
      }
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Searching...';
    }

    fetch(`ajax.php?action=gst_search&q=${encodeURIComponent(gVal)}`)
      .then(r => r.json())
      .then(res => {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-magnifying-glass me-1"></i>Search GST';
        }
        if (res.ok && res.matches && res.matches.length > 0) {
          const match = res.matches[0];
          applyCustomerSelection(match);
          if (res.matches.length > 1) {
            createCustomerDropdown(btGstin || btName, res.matches);
          }
        } else if (res.ok && res.decoded) {
          // Decoded valid GSTIN from checksum/structure
          const d = res.decoded;
          const stateIn = document.getElementById('bt_state');
          if (stateIn && d.state) stateIn.value = d.state;

          if (d.is_intra_state) {
            const gmCs = document.getElementById('gm_cs');
            if (gmCs) gmCs.checked = true;
          } else {
            const gmIg = document.getElementById('gm_ig');
            if (gmIg) gmIg.checked = true;
          }

          if (fb) {
            fb.style.display = 'block';
            fb.innerHTML = `<span class="badge bg-info text-dark py-1 px-2"><i class="fa fa-id-card me-1"></i>Valid GSTIN (${d.entity_type}): State: ${d.state} (${d.state_code}) &mdash; PAN: ${d.pan} &mdash; ${d.is_intra_state ? 'CGST+SGST' : 'IGST'}</span>`;
          }
          if (typeof calcTotals === 'function') calcTotals();
        } else {
          if (fb) {
            fb.style.display = 'block';
            fb.innerHTML = '<span class="text-warning fw-semibold"><i class="fa fa-circle-info me-1"></i>No matching registered customer found in dashboard. You can enter details manually.</span>';
          }
        }
      })
      .catch(err => {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-magnifying-glass me-1"></i>Search GST';
        }
        if (fb) {
          fb.style.display = 'block';
          fb.innerHTML = '<span class="text-danger">Search error. Please try again.</span>';
        }
      });
  };

  // Search automatically on Enter key in bt_gstin
  if (btGstin) {
    btGstin.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchGstMaster();
      }
    });
    // Search on paste if 15 chars
    btGstin.addEventListener('paste', function() {
      setTimeout(() => {
        if (this.value.trim().length >= 15) {
          searchGstMaster();
        }
      }, 100);
    });
  }

})();
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
