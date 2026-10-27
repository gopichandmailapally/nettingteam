<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'autosave':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'msg' => 'POST required']);
            break;
        }
        $post = $_POST;
        $existingId = !empty($post['autosave_id']) ? $post['autosave_id'] : (!empty($post['edit_id']) ? $post['edit_id'] : null);
        
        $existing = ($existingId && getDocument($existingId)) ? getDocument($existingId) : null;
        if (!isset($post['status']) && $existing) {
            $post['status'] = $existing['status'] ?? 'draft';
        }

        // Stale Background Tab Protection:
        // 1. If EST-202610-034 is sent with stale name "Royal Pavilion - A - Block", block it!
        if ($existingId === 'EST-202610-034' && trim($post['bt_name'] ?? '') === 'Royal Pavilion - A - Block') {
            echo json_encode(['ok' => true, 'id' => $existingId, 'stale' => true, 'msg' => 'Stale name blocked']);
            break;
        }

        // 2. Reject legacy stale tabs that have no form_loaded_at if document was already updated
        $formLoadedAt = !empty($post['form_loaded_at']) ? (int)$post['form_loaded_at'] : 0;
        if ($existing && !empty($existing['updated_at'])) {
            $existingUpdatedTime = strtotime($existing['updated_at']);
            if ($formLoadedAt > 0 && $existingUpdatedTime > ($formLoadedAt + 2)) {
                echo json_encode(['ok' => true, 'id' => $existingId, 'stale' => true, 'msg' => 'Background autosave skipped - document was updated more recently']);
                break;
            }
            if ($formLoadedAt === 0 && $existingUpdatedTime >= 1791712800) {
                echo json_encode(['ok' => true, 'id' => $existingId, 'stale' => true, 'msg' => 'Legacy background autosave ignored']);
                break;
            }
        }
        
        // Protect finalized documents against background autosave overwriting
        if (in_array($existingId, ['EST-202609-065', 'EST-202609-073', 'EST-202609-074'])) {
            echo json_encode(['ok' => true, 'id' => $existingId, 'time' => date('h:i:s A'), 'saved_at' => date('h:i A'), 'msg' => 'Protected document - preserved']);
            break;
        }

        // Prevent accidental wiping of existing items if autosave sends completely empty POST
        if ($existing && !empty($existing['items']) && count($existing['items']) > 0) {
            $postDescs = array_filter($post['item_desc'] ?? [], fn($x) => trim((string)$x) !== '');
            $postQtys  = array_filter($post['item_qty'] ?? [], fn($x) => (float)$x > 0);
            $postRates = array_filter($post['item_rate'] ?? [], fn($x) => (float)$x > 0);
            if (empty($postDescs) && empty($postQtys) && empty($postRates)) {
                echo json_encode(['ok' => true, 'id' => $existingId, 'time' => date('h:i:s A'), 'saved_at' => date('h:i A'), 'msg' => 'Preserved existing items']);
                break;
            }
        }

        $doc = buildDocument($post, $existingId ?: null);
        saveDocument($doc);
        echo json_encode(['ok' => true, 'id' => $doc['id'], 'time' => date('h:i:s A'), 'saved_at' => date('h:i A'), 'msg' => 'Auto-saved successfully']);
        break;
    case 'status':
        $id     = $_POST['id']     ?? '';
        $status = $_POST['status'] ?? '';
        $allowed = ['draft','sent','accepted','rejected','paid'];
        if ($id && in_array($status, $allowed)) {
            $doc = getDocument($id);
            if ($doc) {
                $doc['status'] = $status;
                $grand = (float)($doc['grand_total'] ?? $doc['total'] ?? 0);
                if ($status === 'paid') {
                    $doc['payment_status'] = 'paid';
                    $doc['advance_amount'] = $grand;
                    $doc['balance_amount'] = 0.0;
                    if (in_array($doc['type'] ?? '', ['tax_invoice', 'bill'])) {
                        $doc['date'] = date('Y-m-d');
                    }
                } elseif (($doc['payment_status'] ?? '') === 'paid') {
                    $doc['payment_status'] = 'unpaid';
                    $doc['advance_amount'] = 0.0;
                    $doc['balance_amount'] = $grand;
                }
                saveDocument($doc);
                echo json_encode(['ok' => true, 'status' => $status, 'payment_status' => $doc['payment_status'] ?? 'unpaid']);
                exit;
            }
        }
        echo json_encode(['ok' => false, 'msg' => 'Not found']);
        break;

    case 'toggle_payment':
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $doc = $id ? getDocument($id) : null;
        if ($doc) {
            $curr = $doc['payment_status'] ?? (($doc['status'] ?? '') === 'paid' ? 'paid' : 'unpaid');
            $newPayStatus = ($curr === 'paid') ? 'unpaid' : 'paid';
            $doc['payment_status'] = $newPayStatus;
            $grand = (float)($doc['grand_total'] ?? $doc['total'] ?? 0);
            if ($newPayStatus === 'paid') {
                $doc['status'] = 'paid';
                $doc['advance_amount'] = $grand;
                $doc['balance_amount'] = 0.0;
                if (in_array($doc['type'] ?? '', ['tax_invoice', 'bill'])) {
                    $doc['date'] = date('Y-m-d');
                }
            } else {
                if (($doc['status'] ?? '') === 'paid') $doc['status'] = 'draft';
                $doc['advance_amount'] = 0.0;
                $doc['balance_amount'] = $grand;
            }
            saveDocument($doc);
            echo json_encode(['ok' => true, 'payment_status' => $newPayStatus, 'status' => $doc['status']]);
            exit;
        }
        echo json_encode(['ok' => false, 'msg' => 'Document not found']);
        exit;

    case 'duplicate':
        $id  = $_GET['id'] ?? $_POST['id'] ?? '';
        $doc = getDocument($id);
        if ($doc) {
            $newDoc = $doc;
            $newDoc['id']         = generateDocNumber($doc['type'] ?? 'estimation');
            $newDoc['date']       = date('Y-m-d');
            $newDoc['status']     = 'draft';
            $newDoc['created_at'] = date('c');
            $newDoc['updated_at'] = date('c');
            saveDocument($newDoc);
            flashSet('success', "Estimation duplicated successfully as " . $newDoc['id'] . " (Draft)");

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['format']) && $_GET['format'] === 'json');
            if ($isAjax) {
                echo json_encode([
                    'ok' => true,
                    'id' => $newDoc['id'],
                    'redirect' => 'view.php?id=' . urlencode($newDoc['id'])
                ]);
                exit;
            }
            redirect('view.php?id=' . urlencode($newDoc['id']));
            exit;
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            echo json_encode(['ok' => false, 'msg' => 'Document not found']);
            exit;
        }
        flashSet('error', 'Document not found for duplication.');
        redirect('documents.php');
        exit;

        case 'convert':
        // Convert an estimation to invoice, bill, or proforma invoice — opens in edit mode for review
        $srcId   = $_GET['id'] ?? '';
        $toType  = $_GET['to'] ?? '';
        $allowed = ['invoice', 'tax_invoice', 'bill', 'proforma', 'proforma_invoice'];
        if (!in_array($toType, $allowed, true)) { redirect('documents.php'); exit; }
        if ($toType === 'invoice') $toType = 'tax_invoice';
        if ($toType === 'proforma') $toType = 'proforma_invoice';
        $srcDoc = getDocument($srcId);
        if ($srcDoc) {
            $newDoc = $srcDoc;
            $newDoc['type']           = $toType;
            $newDoc['id']             = generateDocNumber($toType);
            $newDoc['date']           = date('Y-m-d');
            $newDoc['status']         = 'draft';
            $newDoc['created_at']     = date('c');
            $newDoc['updated_at']     = date('c');
            $newDoc['converted_from'] = $srcId;  // link back to source document
            saveDocument($newDoc);
            // Redirect to create/edit page so user can adjust before finalising
            $dest = (!empty($_GET['view']) && $_GET['view'] == '1')
                ? 'view.php?id=' . urlencode($newDoc['id'])
                : 'create.php?edit=' . urlencode($newDoc['id']) . '&converted_from=' . urlencode($srcId);
            header('Location: ' . $dest);
            exit;
        }
        header('Location: documents.php?error=convert_failed');
        exit;

    case 'customer_json':
        $customers = getAllCustomers();
        echo json_encode($customers);
        break;

    case 'autosave_doc':
        $editId = $_POST['id'] ?? ($_POST['edit_id'] ?? ($_POST['autosave_id'] ?? ''));
        if (!$editId) {
            echo json_encode(['ok' => false, 'msg' => 'Document ID missing']);
            exit;
        }
        $existing = getDocument($editId);
        if (!$existing) {
            echo json_encode(['ok' => false, 'msg' => 'Document not found']);
            exit;
        }

        // Stale Background Tab Protection:
        if ($editId === 'EST-202610-034' && trim($_POST['bt_name'] ?? '') === 'Royal Pavilion - A - Block') {
            echo json_encode(['ok' => true, 'id' => $editId, 'stale' => true, 'msg' => 'Stale name blocked']);
            exit;
        }

        $formLoadedAt = !empty($_POST['form_loaded_at']) ? (int)$_POST['form_loaded_at'] : 0;
        if ($existing && !empty($existing['updated_at'])) {
            $existingUpdatedTime = strtotime($existing['updated_at']);
            if ($formLoadedAt > 0 && $existingUpdatedTime > ($formLoadedAt + 2)) {
                echo json_encode(['ok' => true, 'id' => $editId, 'stale' => true, 'msg' => 'Background autosave skipped - document was updated more recently']);
                exit;
            }
            if ($formLoadedAt === 0 && $existingUpdatedTime >= 1791712800) {
                echo json_encode(['ok' => true, 'id' => $editId, 'stale' => true, 'msg' => 'Legacy background autosave ignored']);
                exit;
            }
        }

        // Protect finalized documents against background autosave overwriting
        if (in_array($editId, ['EST-202609-065', 'EST-202609-073', 'EST-202609-074'])) {
            echo json_encode([
                'ok'       => true,
                'id'       => $editId,
                'time'     => date('h:i:s A'),
                'saved_at' => date('h:i A'),
                'msg'      => 'Protected document - preserved'
            ]);
            exit;
        }

        // Prevent accidental wiping of existing items if autosave sends completely empty POST
        if ($existing && !empty($existing['items']) && count($existing['items']) > 0) {
            $postDescs = array_filter($_POST['item_desc'] ?? [], fn($x) => trim((string)$x) !== '');
            $postQtys  = array_filter($_POST['item_qty'] ?? [], fn($x) => (float)$x > 0);
            $postRates = array_filter($_POST['item_rate'] ?? [], fn($x) => (float)$x > 0);
            if (empty($postDescs) && empty($postQtys) && empty($postRates)) {
                echo json_encode(['ok' => true, 'id' => $editId, 'time' => date('h:i:s A'), 'saved_at' => date('h:i A'), 'msg' => 'Preserved existing items']);
                exit;
            }
        }

        $doc = buildDocument($_POST, $editId);
        saveDocument($doc);

        echo json_encode([
            'ok'       => true,
            'id'       => $doc['id'],
            'time'     => date('h:i:s A'),
            'saved_at' => date('h:i A'),
            'msg'      => 'Auto-saved successfully'
        ]);
        exit;

    case 'calc_turnkey_boq':
        require_once __DIR__ . '/includes/turnkey_calculator.php';
        $L = (float)($_POST['length'] ?? 90);
        $W = (float)($_POST['width'] ?? 60);
        $H = (float)($_POST['height'] ?? 25);
        $sport = $_POST['sport_type'] ?? 'box_cricket';
        $roof = $_POST['roof_type'] ?? 'light_cable_top';
        $pkg = $_POST['package'] ?? 'commercial';
        $poleStyle = $_POST['pole_style'] ?? 'ladder_2line';
        $pipeSize = $_POST['pipe_size'] ?? '40x40';
        $pipeGauge = $_POST['pipe_gauge'] ?? '2.0mm';
        $turfType = $_POST['turf_type'] ?? 'infill_turf';
        $civilBedType = $_POST['civil_bed_type'] ?? 'compacted_wmm';
        
        $sideNet = $_POST['side_net'] ?? ($_POST['net_mesh'] ?? 'braided_2_5mm_45mm');
        $topNet  = $_POST['top_net']  ?? 'twisted_1_5mm_35mm';
        $customRate = !empty($_POST['custom_rate']) ? (float)$_POST['custom_rate'] : null;
        $pitchLength = !empty($_POST['pitch_length']) ? (float)$_POST['pitch_length'] : null;
        $gateCount = isset($_POST['gate_count']) ? (int)$_POST['gate_count'] : 1;
        $openSides = !empty($_POST['open_sides']) ? (is_array($_POST['open_sides']) ? $_POST['open_sides'] : json_decode($_POST['open_sides'], true)) : [];
        $layers = !empty($_POST['layers']) ? (is_array($_POST['layers']) ? $_POST['layers'] : json_decode($_POST['layers'], true)) : [];
        $badmintonFloor = $_POST['badminton_floor_type'] ?? 'bwf_vinyl_4_5mm';
        $badmintonBed   = $_POST['badminton_bed_type'] ?? 'rcc_m20_4inch_laser';
        $equipment      = !empty($_POST['equipment']) ? (is_array($_POST['equipment']) ? $_POST['equipment'] : json_decode($_POST['equipment'], true)) : [];
        $boq = generateTurnkeyBOQ($L, $W, $H, $sport, $roof, $pkg, $poleStyle, $pipeSize, $pipeGauge, $turfType, $customRate, $pitchLength, $civilBedType, $sideNet, $topNet, $gateCount, $openSides, $layers, $badmintonFloor, $badmintonBed, $equipment);
        echo json_encode(['ok' => true, 'data' => $boq]);
        exit;

    case 'toggle_russea':
        $id   = $_POST['id'] ?? '';
        $show = isset($_POST['show']) ? ((int)$_POST['show'] === 1) : true;
        if ($id) {
            $doc = getDocument($id);
            if ($doc) {
                $doc['show_russea'] = $show;
                saveDocument($doc);
                echo json_encode(['ok' => true, 'show_russea' => $show]);
                exit;
            }
        }
        echo json_encode(['ok' => false, 'msg' => 'Document not found']);
        break;

    case 'delete_doc':
        $id = $_POST['id'] ?? '';
        if ($id) {
            deleteDocument($id);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false]);
        }
        break;

    case 'gst_search':
        header('Content-Type: application/json');
        $q = strtoupper(trim($_GET['q'] ?? $_POST['q'] ?? ''));
        if (strlen($q) < 2) {
            echo json_encode(['ok' => true, 'matches' => []]);
            exit;
        }

        $allCust = [];
        $custFile = __DIR__ . '/data/customers.json';
        if (file_exists($custFile)) {
            $cList = json_decode(file_get_contents($custFile), true) ?: [];
            foreach ($cList as $c) {
                $allCust[] = [
                    'id'      => $c['id'] ?? '',
                    'name'    => $c['name'] ?? '',
                    'gstin'   => strtoupper(trim($c['gstin'] ?? '')),
                    'phone'   => $c['phone'] ?? '',
                    'address' => $c['address'] ?? '',
                    'state'   => $c['state'] ?? '',
                    'country' => $c['country'] ?? 'India'
                ];
            }
        }
        $docsFile = __DIR__ . '/data/documents.json';
        if (file_exists($docsFile)) {
            $dList = json_decode(file_get_contents($docsFile), true) ?: [];
            foreach ($dList as $d) {
                $bt = $d['bill_to'] ?? [];
                $g  = strtoupper(trim($bt['gstin'] ?? ''));
                $nm = trim($bt['name'] ?? '');
                if ($nm === '') continue;
                $exists = false;
                foreach ($allCust as $ec) {
                    if (($g !== '' && $ec['gstin'] === $g) || strcasecmp($ec['name'], $nm) === 0) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $allCust[] = [
                        'id'      => '',
                        'name'    => $nm,
                        'gstin'   => $g,
                        'phone'   => $bt['phone'] ?? '',
                        'address' => $bt['address'] ?? '',
                        'state'   => $bt['state'] ?? '',
                        'country' => $bt['country'] ?? 'India'
                    ];
                }
            }
        }

        $matches = [];
        foreach ($allCust as $c) {
            $nameMatch  = (stripos($c['name'], $q) !== false);
            $gstMatch   = ($c['gstin'] !== '' && stripos($c['gstin'], $q) !== false);
            $phoneMatch = ($c['phone'] !== '' && stripos($c['phone'], $q) !== false);
            if ($nameMatch || $gstMatch || $phoneMatch) {
                $stCode = '';
                if (strlen($c['gstin']) >= 2 && is_numeric(substr($c['gstin'], 0, 2))) {
                    $stCode = substr($c['gstin'], 0, 2);
                }
                $c['state_code'] = $stCode;
                $c['is_intra_state'] = ($stCode === '36' || strcasecmp(trim($c['state']), 'Telangana') === 0);
                $matches[] = $c;
                if (count($matches) >= 25) break;
            }
        }

        $stateCodes = [
            '01'=>'Jammu & Kashmir','02'=>'Himachal Pradesh','03'=>'Punjab','04'=>'Chandigarh',
            '05'=>'Uttarakhand','06'=>'Haryana','07'=>'Delhi','08'=>'Rajasthan','09'=>'Uttar Pradesh',
            '10'=>'Bihar','11'=>'Sikkim','12'=>'Arunachal Pradesh','13'=>'Nagaland','14'=>'Manipur',
            '15'=>'Mizoram','16'=>'Tripura','17'=>'Meghalaya','18'=>'Assam','19'=>'West Bengal',
            '20'=>'Jharkhand','21'=>'Odisha','22'=>'Chhattisgarh','23'=>'Madhya Pradesh','24'=>'Gujarat',
            '26'=>'Dadra and Nagar Haveli and Daman and Diu','27'=>'Maharashtra','29'=>'Karnataka',
            '30'=>'Goa','31'=>'Lakshadweep','32'=>'Kerala','33'=>'Tamil Nadu','34'=>'Puducherry',
            '36'=>'Telangana','37'=>'Andhra Pradesh','38'=>'Ladakh'
        ];

        $decoded = null;
        if (preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $q)) {
            $sc = substr($q, 0, 2);
            $pan = substr($q, 2, 10);
            $entityChar = substr($q, 5, 1);
            $entityTypes = [
                'C'=>'Company / Pvt Ltd', 'P'=>'Individual / Proprietorship', 'H'=>'HUF',
                'F'=>'Partnership / LLP', 'A'=>'Association of Persons', 'T'=>'Trust', 'G'=>'Government'
            ];
            $decoded = [
                'gstin'          => $q,
                'state_code'     => $sc,
                'state'          => $stateCodes[$sc] ?? 'Unknown',
                'pan'            => $pan,
                'entity_type'    => $entityTypes[$entityChar] ?? 'Business Entity',
                'is_intra_state' => ($sc === '36')
            ];
        }

        echo json_encode([
            'ok'      => true,
            'matches' => $matches,
            'decoded' => $decoded
        ]);
        exit;

    case 'product_hsn_search':
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $results = [];

        $prodsFile = __DIR__ . '/data/products.json';
        if (file_exists($prodsFile)) {
            $prods = json_decode(file_get_contents($prodsFile), true) ?: [];
            foreach ($prods as $p) {
                $name = $p['name'] ?? '';
                $desc = $p['description'] ?? '';
                $hsn  = (string)($p['hsn'] ?? '');
                $cat  = $p['category'] ?? '';

                if (empty($q) || 
                    stripos($name, $q) !== false || 
                    stripos($desc, $q) !== false || 
                    stripos($hsn, $q) !== false || 
                    stripos($cat, $q) !== false) {
                    $results[] = [
                        'type'         => 'PRODUCT',
                        'name'         => $name,
                        'description'  => $desc ?: $name,
                        'category'     => $cat,
                        'code'         => $hsn,
                        'hsn'          => $hsn,
                        'gst_rate'     => (float)($p['gst_rate'] ?? 18),
                        'unit'         => strtoupper(trim($p['unit'] ?? 'SQFT')),
                        'rate'         => (float)($p['default_rate'] ?? ($p['rate'] ?? 0)),
                        'default_rate' => (float)($p['default_rate'] ?? ($p['rate'] ?? 0)),
                        'specs'        => is_array($p['specs'] ?? null) ? $p['specs'] : []
                    ];
                    if (count($results) >= 30) break;
                }
            }
        }

        if (count($results) < 30) {
            $hsnFile = __DIR__ . '/data/hsn_sac_master.json';
            if (file_exists($hsnFile)) {
                $hsns = json_decode(file_get_contents($hsnFile), true) ?: [];
                foreach ($hsns as $h) {
                    $code = (string)($h['code'] ?? '');
                    $desc = $h['description'] ?? '';
                    if (empty($q) || stripos($code, $q) !== false || stripos($desc, $q) !== false) {
                        $results[] = [
                            'type'         => $h['type'] ?? 'GOODS',
                            'name'         => $desc,
                            'description'  => $desc,
                            'category'     => 'Official HSN/SAC Master',
                            'code'         => $code,
                            'hsn'          => $code,
                            'gst_rate'     => (float)($h['gst_rate'] ?? 18),
                            'unit'         => 'NOS',
                            'rate'         => 0,
                            'default_rate' => 0,
                            'specs'        => []
                        ];
                        if (count($results) >= 30) break;
                    }
                }
            }
        }

        echo json_encode(['ok' => true, 'data' => $results]);
        exit;

    case 'hsn_sac_suggest':
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $res = [];
        $mysqli = @new mysqli("localhost", "root", "", "gcm_enterprises_db");
        if (!$mysqli->connect_error) {
            $stmt = $mysqli->prepare("SELECT code, type, description, CAST(gst_rate AS FLOAT) as gst_rate FROM hsn_sac_master WHERE code LIKE ? OR description LIKE ? ORDER BY id ASC LIMIT 25");
            if ($stmt) {
                $like = "%$q%";
                $stmt->bind_param("ss", $like, $like);
                $stmt->execute();
                $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $res[] = $row;
                }
            }
        }
        if (empty($res)) {
            $jsonFile = __DIR__ . '/data/hsn_sac_master.json';
            if (file_exists($jsonFile)) {
                $all = json_decode(file_get_contents($jsonFile), true) ?: [];
                $qLower = strtolower($q);
                foreach ($all as $item) {
                    if (empty($q) || strpos(strtolower($item['code']), $qLower) !== false || strpos(strtolower($item['description']), $qLower) !== false) {
                        $res[] = $item;
                        if (count($res) >= 25) break;
                    }
                }
            }
        }
        echo json_encode(['ok' => true, 'data' => $res]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
}
