<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('documents', 'view');

function normalizeHsn($hsn, $desc = '') {
    return function_exists('resolveItemHsn') ? resolveItemHsn($desc, $hsn) : (trim((string)$hsn) ?: '5608');
}

function getHsnDesc($hsn) {
    return function_exists('getOfficialHsnDescription') ? getOfficialHsnDescription($hsn) : 'OTHER OUTWARD SUPPLIES';
}

function getUqcCode($unit) {
    if (function_exists('normalizeUqcUnit') && function_exists('getUqcCodeOnly')) {
        $norm = normalizeUqcUnit($unit);
        $code = getUqcCodeOnly($norm);
        return ['code' => $code, 'label' => $norm];
    }
    return ['code' => 'OTH', 'label' => 'OTH-OTHERS'];
}

// ── All Tax Invoices ─────────────────────────────────────────────────────────
$allDocs = getAllDocuments();
$allTaxInvoices = [];
foreach ($allDocs as $d) {
    if (($d['type'] ?? '') === 'tax_invoice') {
        $allTaxInvoices[] = $d;
    }
}
usort($allTaxInvoices, fn($a, $b) => strnatcasecmp($b['id'] ?? '', $a['id'] ?? ''));

// ── Periods ─────────────────────────────────────────────────────────────────
$period = $_GET['period'] ?? 'Q2_2026';
$periods = [
    'Q2_2026' => ['label' => 'FY 2026-27 Q2 (Jul - Sep 2026) — September Quarter Filing', 'start' => '2026-07-01', 'end' => '2026-09-30'],
    'Q3_2026' => ['label' => 'FY 2026-27 Q3 (Oct - Dec 2026) — December Quarter', 'start' => '2026-10-01', 'end' => '2026-12-31'],
    'Q1_2026' => ['label' => 'FY 2026-27 Q1 (Apr - Jun 2026) — June Quarter', 'start' => '2026-04-01', 'end' => '2026-06-30'],
    'SEP_2026' => ['label' => 'Month: September 2026', 'start' => '2026-09-01', 'end' => '2026-09-30'],
    'AUG_2026' => ['label' => 'Month: August 2026', 'start' => '2026-08-01', 'end' => '2026-08-31'],
    'OCT_2026' => ['label' => 'Month: October 2026', 'start' => '2026-10-01', 'end' => '2026-10-31'],
    'FY_2026'  => ['label' => 'Full Financial Year 2026-27', 'start' => '2026-04-01', 'end' => '2027-03-31'],
];

$startDate = $periods[$period]['start'] ?? '2026-07-01';
$endDate   = $periods[$period]['end']   ?? '2026-09-30';

// ── Determine which invoices are selected ───────────────────────────────────
// Default 8 invoices specifically requested by user for Q2 filing:
$defaultQ2Invoices = [
    'INV-202606-001', // 1st one from June (Teja Abodes)
    'INV-202608-001', // Aug (Teja Abodes)
    'INV-202609-001', // Sep (Teja Abodes)
    'INV-202609-003', // Sep (Ultra Dimensions)
    'INV-202609-004', // Sep (SM Corporation)
    'INV-202609-005', // Sep (Ultra Dimensions)
    'INV-202609-006', // Sep (Ultra Avenues)
    'INV-202610-002', // Oct / Sep 15 (Kollis Enterprises)
    // NOTE: INV-202609-002 is pushed to next quarter, so excluded by default!
];

$userSelectedIds = [];
if (!empty($_GET['invs'])) {
    $userSelectedIds = array_filter(array_map('trim', explode(',', $_GET['invs'])));
} elseif ($period === 'Q2_2026') {
    $userSelectedIds = $defaultQ2Invoices;
} else {
    // Other quarters: select invoices matching date range
    foreach ($allTaxInvoices as $inv) {
        $dt = $inv['date'] ?? '';
        if ($dt >= $startDate && $dt <= $endDate) {
            $userSelectedIds[] = $inv['id'];
        }
    }
}

// ── Compute Selected Invoices & Table 12 HSN Summary (PHP) ──────────────────
$selectedInvoices = [];
$hsnGroups = [];
$totalTaxable = 0;
$totalIgst = 0;
$totalCgst = 0;
$totalSgst = 0;
$totalGrand = 0;
$hsnCorrectionNotes = [];

foreach ($allTaxInvoices as $inv) {
    if (in_array($inv['id'], $userSelectedIds)) {
        $selectedInvoices[] = $inv;
        $gstMode = $inv['gst_mode'] ?? 'none';
        $totalGrand += (float)($inv['grand_total'] ?? 0);
        
        foreach ($inv['items'] ?? [] as $it) {
            $rawHsn = trim((string)($it['hsn'] ?? ''));
            $desc   = trim((string)($it['description'] ?? ''));
            $normHsn = normalizeHsn($rawHsn, $desc);
            
            if ($rawHsn !== $normHsn && !empty($rawHsn)) {
                $hsnCorrectionNotes[$inv['id']] = "{$inv['id']}: '{$desc}' had HSN '{$rawHsn}', normalized to valid GST portal HSN '{$normHsn}'";
            }
            
            $rate   = (float)($it['gst_rate'] ?? 5);
            $uqcArr = getUqcCode($it['unit'] ?? '');
            $uqc    = $uqcArr['code'];
            $qty    = (float)($it['qty'] ?? 0);
            $amt    = (float)($it['amount'] ?? 0);
            
            if ($gstMode === 'igst') {
                $igst = round($amt * ($rate / 100), 2);
                $cgst = 0.0; $sgst = 0.0;
            } elseif ($gstMode === 'cgst_sgst') {
                $igst = 0.0;
                $half = round($amt * ($rate / 200), 2);
                $cgst = $half; $sgst = $half;
            } else {
                $igst = 0.0; $cgst = 0.0; $sgst = 0.0;
            }
            
            $key = $normHsn . '_' . $uqc . '_' . $rate;
            if (!isset($hsnGroups[$key])) {
                $hsnGroups[$key] = [
                    'hsn'         => $normHsn,
                    'desc'        => getHsnDesc($normHsn),
                    'uqc'         => $uqc,
                    'uqc_label'   => $uqcArr['label'],
                    'rate'        => $rate,
                    'qty'         => 0.0,
                    'taxable'     => 0.0,
                    'igst'        => 0.0,
                    'cgst'        => 0.0,
                    'sgst'        => 0.0,
                    'cess'        => 0.0,
                    'items_count' => 0
                ];
            }
            $hsnGroups[$key]['qty'] += $qty;
            $hsnGroups[$key]['taxable'] += $amt;
            $hsnGroups[$key]['igst'] += $igst;
            $hsnGroups[$key]['cgst'] += $cgst;
            $hsnGroups[$key]['sgst'] += $sgst;
            $hsnGroups[$key]['items_count']++;
            
            $totalTaxable += $amt;
            $totalIgst += $igst;
            $totalCgst += $cgst;
            $totalSgst += $sgst;
        }
    }
}
ksort($hsnGroups);

// Table 13 Stats
$docCount = count($selectedInvoices);
$docFrom = '';
$docTo = '';
if ($docCount > 0) {
    $sortedSeq = $selectedInvoices;
    usort($sortedSeq, fn($a, $b) => strnatcasecmp($a['id'] ?? '', $b['id'] ?? ''));
    $docFrom = $sortedSeq[0]['id'] ?? '';
    $docTo   = $sortedSeq[$docCount - 1]['id'] ?? '';
}

// ── CSV Export ──────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="GSTR1_Table12_HSN_Summary_' . $period . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['HSN', 'Description', 'UQC', 'Total Quantity', 'Total Taxable Value', 'Rate (%)', 'Integrated Tax (₹)', 'Central Tax (₹)', 'State/UT Tax (₹)', 'Cess (₹)', 'Total Tax (₹)']);
    foreach ($hsnGroups as $g) {
        $totTax = $g['igst'] + $g['cgst'] + $g['sgst'];
        fputcsv($out, [
            $g['hsn'],
            $g['desc'],
            $g['uqc'],
            number_format($g['qty'], 2, '.', ''),
            number_format($g['taxable'], 2, '.', ''),
            $g['rate'],
            number_format($g['igst'], 2, '.', ''),
            number_format($g['cgst'], 2, '.', ''),
            number_format($g['sgst'], 2, '.', ''),
            '0.00',
            number_format($totTax, 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'GSTR-1 & Table 12 HSN Summary Assistant';
require_once __DIR__ . '/includes/layout.php';
?>

<style>
.copy-pill {
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: all;
  font-family: monospace;
}
.copy-pill:hover {
  filter: brightness(0.92);
  transform: translateY(-1px);
}
.copy-pill:active {
  transform: translateY(0);
}
.table-portal th {
  background: #1a3a7c !important;
  color: #ffffff !important;
  font-size: 0.78rem;
  letter-spacing: 0.5px;
  vertical-align: middle;
}
.table-portal td {
  vertical-align: middle;
  font-size: 0.84rem;
}
.inv-row-selected {
  background-color: rgba(220, 252, 231, 0.45) !important;
}
.inv-row-unselected {
  opacity: 0.65;
}
</style>

<div class="container-fluid px-0 px-md-3">

  <!-- Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h3 class="fw-bold mb-1 text-navy" style="color:#1a3a7c;">
        <i class="fa fa-file-invoice-dollar text-primary me-2"></i>GSTR-1 Table 12 HSN Summary Assistant
      </h3>
      <p class="text-muted small mb-0">
        Select your filing quarter, customize included/excluded invoices with 1-click checkboxes, and view instant live <strong>Table 12</strong> &amp; <strong>Table 13</strong> calculations.
      </p>
    </div>
    <div class="d-flex gap-2">
      <a id="btnDownloadCsv" href="?period=<?= urlencode($period) ?>&export=csv&invs=<?= implode(',', $userSelectedIds) ?>" class="btn btn-outline-success fw-bold">
        <i class="fa fa-file-excel me-1"></i>Download Table 12 CSV
      </a>
      <button onclick="window.print()" class="btn btn-outline-secondary fw-bold no-print">
        <i class="fa fa-print me-1"></i>Print Summary
      </button>
    </div>
  </div>

  <!-- Quarter Selection Bar -->
  <div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body py-2">
      <form method="GET" id="periodForm" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
          <div class="d-flex align-items-center gap-2">
            <label class="form-label small fw-bold text-muted text-nowrap mb-0">Filing Quarter:</label>
            <select name="period" class="form-select form-select-sm fw-bold text-navy" onchange="onQuarterChange(this.value)">
              <?php foreach ($periods as $k => $p): ?>
              <option value="<?= $k ?>" <?= $period === $k ? 'selected' : '' ?>><?= e($p['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-12 col-md-7 text-md-end d-flex flex-wrap justify-content-md-end gap-2 align-items-center">
          <span class="badge bg-primary px-3 py-2 fs-7" id="liveSelectedBadge">
            <i class="fa fa-receipt me-1"></i><span id="lblSelectedCount"><?= count($selectedInvoices) ?></span> Invoices Selected for Filing
          </span>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetToQ2Recommended()">
            <i class="fa fa-rotate-left me-1"></i>Reset to User's 8 Invoices
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Top Metric Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="text-muted small fw-bold text-uppercase">B2B Invoices</div>
        <div class="fs-4 fw-black text-navy" id="cardDocCount" style="color:#1a3a7c;"><?= $docCount ?> Invoices</div>
        <div class="small text-muted" id="cardDocRange"><?= e($docFrom) ?> to <?= e($docTo) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="text-muted small fw-bold text-uppercase">Total Taxable Value</div>
        <div class="fs-4 fw-black text-success" id="cardTaxable">₹<?= fmtAmount($totalTaxable) ?></div>
        <div class="small text-muted">Table 12 Taxable Total</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="text-muted small fw-bold text-uppercase">Total GST Tax</div>
        <div class="fs-4 fw-black text-primary" id="cardTax">₹<?= fmtAmount($totalIgst + $totalCgst + $totalSgst) ?></div>
        <div class="small text-muted" id="cardTaxBreakdown">IGST: ₹<?= fmtAmount($totalIgst) ?> | CGST+SGST: ₹<?= fmtAmount($totalCgst + $totalSgst) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="text-muted small fw-bold text-uppercase">Total Invoice Value</div>
        <div class="fs-4 fw-black text-dark" id="cardGrand">₹<?= fmtAmount($totalGrand) ?></div>
        <div class="small text-muted">Table 4 B2B Invoice Total</div>
      </div>
    </div>
  </div>

  <!-- TABLE 12 CARD: Exact GST Portal Table 12 Entry Fields -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="fw-bold mb-0 text-navy" style="color:#1a3a7c;">
          <span class="badge bg-danger me-2">TABLE 12</span> 12 - HSN - wise summary of outward supplies
        </h5>
        <div class="text-muted small mt-1">
          Values dynamically calculated from your checked invoices below. Click any badge to copy directly to clipboard.
        </div>
      </div>
      <div>
        <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="copyAllTable12()">
          <i class="fa fa-copy me-1"></i>Copy All Rows as Tabular Text
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-portal mb-0 align-middle" id="table12El">
        <thead>
          <tr class="text-center">
            <th style="width:90px;">HSN *</th>
            <th style="min-width:180px;">Description</th>
            <th style="width:140px;">UQC *</th>
            <th style="width:130px;">Total Quantity *</th>
            <th style="width:150px;">Total Taxable Value (₹) *</th>
            <th style="width:90px;">Rate (%) *</th>
            <th style="width:130px;">Integrated Tax (₹)</th>
            <th style="width:120px;">Central Tax (₹)</th>
            <th style="width:120px;">State/UT Tax (₹)</th>
            <th style="width:70px;">Cess (₹)</th>
            <th style="width:90px;" class="no-print">Action</th>
          </tr>
        </thead>
        <tbody id="table12Body">
          <?php $rowIdx = 0; ?>
          <?php foreach ($hsnGroups as $k => $g): ?>
          <?php $rowIdx++; ?>
          <tr>
            <td class="text-center">
              <span class="badge bg-primary copy-pill px-2 py-1 fs-7" onclick="copyText('<?= e($g['hsn']) ?>', this)" title="Click to copy HSN">
                <?= e($g['hsn']) ?> <i class="fa fa-copy ms-1 small"></i>
              </span>
            </td>
            <td>
              <span class="fw-semibold small text-dark d-block"><?= e($g['desc']) ?></span>
              <span class="text-muted" style="font-size:0.75rem;">(<?= $g['items_count'] ?> invoice items)</span>
            </td>
            <td class="text-center">
              <span class="badge bg-secondary copy-pill px-2 py-1 fs-7" onclick="copyText('<?= e($g['uqc']) ?>', this)" title="Click to copy UQC code">
                <?= e($g['uqc_label']) ?>
              </span>
            </td>
            <td class="text-end">
              <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('<?= number_format($g['qty'], 2, '.', '') ?>', this)" title="Click to copy Quantity">
                <?= number_format($g['qty'], 2) ?> <i class="fa fa-copy ms-1 text-muted"></i>
              </span>
            </td>
            <td class="text-end">
              <span class="badge bg-success-subtle text-success-emphasis border border-success copy-pill px-2 py-1 fs-7 fw-black" onclick="copyText('<?= number_format($g['taxable'], 2, '.', '') ?>', this)" title="Click to copy Taxable Value">
                ₹<?= number_format($g['taxable'], 2) ?> <i class="fa fa-copy ms-1 text-muted"></i>
              </span>
            </td>
            <td class="text-center">
              <span class="badge bg-warning text-dark copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('<?= $g['rate'] ?>', this)" title="Click to copy Rate">
                <?= $g['rate'] ?>%
              </span>
            </td>
            <td class="text-end">
              <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('<?= number_format($g['igst'], 2, '.', '') ?>', this)" title="Click to copy IGST">
                ₹<?= number_format($g['igst'], 2) ?>
              </span>
            </td>
            <td class="text-end">
              <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('<?= number_format($g['cgst'], 2, '.', '') ?>', this)" title="Click to copy CGST">
                ₹<?= number_format($g['cgst'], 2) ?>
              </span>
            </td>
            <td class="text-end">
              <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('<?= number_format($g['sgst'], 2, '.', '') ?>', this)" title="Click to copy SGST">
                ₹<?= number_format($g['sgst'], 2) ?>
              </span>
            </td>
            <td class="text-center text-muted">0.00</td>
            <td class="text-center no-print">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyRow(<?= $rowIdx ?>)" title="Copy entire row to clipboard">
                <i class="fa fa-copy"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot id="table12Foot" style="background:#f8fafc;font-weight:bold;">
          <tr>
            <td colspan="3" class="text-center text-uppercase">TOTALS (Table 12)</td>
            <td class="text-end" id="footQty"><?= number_format(array_sum(array_column($hsnGroups, 'qty')), 2) ?></td>
            <td class="text-end text-success" id="footTaxable">₹<?= number_format($totalTaxable, 2) ?></td>
            <td class="text-center">—</td>
            <td class="text-end" id="footIgst">₹<?= number_format($totalIgst, 2) ?></td>
            <td class="text-end" id="footCgst">₹<?= number_format($totalCgst, 2) ?></td>
            <td class="text-end" id="footSgst">₹<?= number_format($totalSgst, 2) ?></td>
            <td class="text-center">0.00</td>
            <td class="text-center no-print">
              <span class="badge bg-dark" id="footTotTax">Total Tax: ₹<?= number_format($totalIgst + $totalCgst + $totalSgst, 2) ?></span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- TABLE 13: Documents Issued Card -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3">
      <h5 class="fw-bold mb-0 text-navy" style="color:#1a3a7c;">
        <span class="badge bg-danger me-2">TABLE 13</span> 13 - Documents Issued
      </h5>
      <div class="text-muted small mt-1">
        Enter these details in Table 13 on the GST Portal to eliminate the "Please enter details in Table 13" error.
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nature of Document</th>
              <th class="text-center">Sr. No. From *</th>
              <th class="text-center">Sr. No. To *</th>
              <th class="text-center">Total Number *</th>
              <th class="text-center">Cancelled</th>
              <th class="text-center">Net Issued *</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="fw-bold">1. Invoices for outward supply</td>
              <td class="text-center">
                <span class="badge bg-primary copy-pill px-2 py-1 fs-7" id="t13From" onclick="copyText(this.innerText.trim(), this)">
                  <?= e($docFrom) ?> <i class="fa fa-copy ms-1 small"></i>
                </span>
              </td>
              <td class="text-center">
                <span class="badge bg-primary copy-pill px-2 py-1 fs-7" id="t13To" onclick="copyText(this.innerText.trim(), this)">
                  <?= e($docTo) ?> <i class="fa fa-copy ms-1 small"></i>
                </span>
              </td>
              <td class="text-center fw-black fs-6 text-dark" id="t13Count"><?= $docCount ?></td>
              <td class="text-center text-muted">0</td>
              <td class="text-center fw-black fs-6 text-success" id="t13Net"><?= $docCount ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- INVOICE SELECTION CHECKLIST CARD -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="fw-bold mb-0 text-navy" style="color:#1a3a7c;">
          <i class="fa fa-list-check me-2 text-primary"></i>Select &amp; Customize Invoices for this Filing
        </h5>
        <div class="text-muted small mt-1">
          Check or uncheck ANY invoice below. Calculations above update <strong>instantly</strong> in real time.
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllInvoices(true)">
          <i class="fa fa-check-double me-1"></i>Select All
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllInvoices(false)">
          <i class="fa fa-square me-1"></i>Deselect All
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="invoicesTable">
        <thead class="table-light">
          <tr>
            <th style="width:45px;" class="text-center">
              <input type="checkbox" class="form-check-input" id="checkMaster" checked onchange="toggleAllInvoices(this.checked)">
            </th>
            <th>Invoice No</th>
            <th>Date</th>
            <th>Customer Name</th>
            <th>GSTIN</th>
            <th>Mode</th>
            <th class="text-end">Taxable (₹)</th>
            <th class="text-end">GST Tax (₹)</th>
            <th class="text-end">Total (₹)</th>
            <th class="text-center">Status / Note</th>
            <th class="text-center no-print">View</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allTaxInvoices as $inv): ?>
          <?php 
            $invId = $inv['id'];
            $isSelected = in_array($invId, $userSelectedIds);
            $dt = $inv['date'] ?? '';
            $inRange = ($dt >= $startDate && $dt <= $endDate);
            $itms = $inv['items'] ?? [];
            $invTaxable = array_sum(array_column($itms, 'amount'));
            $invMode = $inv['gst_mode'] ?? 'none';
            $invGrand = (float)($inv['grand_total'] ?? 0);
            $invTax = max(0, $invGrand - $invTaxable);

            // Special notes for key invoices
            $specialTag = '';
            if ($invId === 'INV-202609-002') {
                $specialTag = '<span class="badge bg-warning text-dark border"><i class="fa fa-clock me-1"></i>Pushed to Next Quarter</span>';
            } elseif ($invId === 'INV-202606-001') {
                $specialTag = '<span class="badge bg-info-subtle text-info-emphasis border border-info"><i class="fa fa-star me-1"></i>1st Inv (June)</span>';
            } elseif ($invId === 'INV-202610-002') {
                $specialTag = '<span class="badge bg-secondary-subtle text-secondary-emphasis border"><i class="fa fa-link me-1"></i>Kollis (Sep 15 advance)</span>';
            } elseif ($inRange) {
                $specialTag = '<span class="badge bg-light text-dark border">Quarter Inv</span>';
            } else {
                $specialTag = '<span class="badge bg-light text-muted border">Out of Quarter</span>';
            }
          ?>
          <tr id="row_<?= e($invId) ?>" class="<?= $isSelected ? 'inv-row-selected' : 'inv-row-unselected' ?>">
            <td class="text-center">
              <input type="checkbox" class="form-check-input inv-checkbox" value="<?= e($invId) ?>" <?= $isSelected ? 'checked' : '' ?> onchange="onInvoiceSelectionChange()">
            </td>
            <td>
              <a href="view.php?id=<?= urlencode($invId) ?>" class="fw-bold text-decoration-none" target="_blank">
                <?= e($invId) ?>
              </a>
            </td>
            <td><?= date('d M Y', strtotime($dt)) ?></td>
            <td class="fw-semibold"><?= e($inv['bill_to']['name'] ?? '') ?></td>
            <td>
              <?php if (!empty($inv['bill_to']['gstin'])): ?>
                <code><?= e($inv['bill_to']['gstin']) ?></code>
              <?php else: ?>
                <span class="badge bg-secondary">Unregistered</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($invMode === 'igst'): ?>
                <span class="badge bg-info-subtle text-info-emphasis border border-info">IGST (Interstate)</span>
              <?php else: ?>
                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary">CGST+SGST (Local)</span>
              <?php endif; ?>
            </td>
            <td class="text-end fw-bold">₹<?= fmtAmount($invTaxable) ?></td>
            <td class="text-end fw-bold text-muted">₹<?= fmtAmount($invTax) ?></td>
            <td class="text-end fw-black text-dark">₹<?= fmtAmount($invGrand) ?></td>
            <td class="text-center"><?= $specialTag ?></td>
            <td class="text-center no-print">
              <a href="view.php?id=<?= urlencode($invId) ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="View Full Invoice">
                <i class="fa fa-arrow-up-right-from-square"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- How to Enter Step-by-Step Guide -->
  <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
    <div class="card-body p-4">
      <h6 class="fw-bold text-navy mb-2" style="color:#1a3a7c;">
        <i class="fa fa-circle-info text-info me-1"></i>How to Complete Your GSTR-1 on the GST Portal:
      </h6>
      <ol class="small mb-0 ps-3 text-secondary lh-lg">
        <li>Open <strong>Table 12 (HSN-wise summary of outward supplies)</strong> on the GST Portal.</li>
        <li>For each row shown above in <strong>Table 12</strong>, click <strong>Add Details</strong>.</li>
        <li>Select the <strong>HSN</strong> (e.g. <code>5608</code>, <code>5703</code>, <code>3506</code>, <code>7610</code>). In description, select from dropdown.</li>
        <li>Select <strong>UQC</strong> (e.g. <code>SQF - SQUARE FEET</code>, <code>LTR - LITRES</code>, <code>OTH - OTHERS</code>).</li>
        <li>Click our copy badges to paste <strong>Total Quantity</strong>, <strong>Taxable Value</strong>, <strong>Rate</strong>, and <strong>Tax (IGST / CGST / SGST)</strong>, then click <strong>Add / Save</strong>.</li>
        <li>Return to GSTR-1 Dashboard, open <strong>Table 13 (Documents Issued)</strong>, enter Sr. No. <code id="guideFrom"><?= e($docFrom) ?></code> to <code id="guideTo"><?= e($docTo) ?></code>, Total: <code id="guideCount"><?= $docCount ?></code>, Net: <code id="guideNet"><?= $docCount ?></code>, and Save.</li>
        <li>Now click <strong>Generate Summary</strong> &rarr; <strong>Proceed to File</strong> without any errors!</li>
      </ol>
    </div>
  </div>

</div>

<!-- Embedded Invoices JSON for Instant Client-Side Recalculations -->
<script>
const allInvoicesData = <?php echo json_encode($allTaxInvoices); ?>;
const defaultQ2List = <?php echo json_encode($defaultQ2Invoices); ?>;

const hsnMapDesc = {
  '5608': 'KNOTTED NETTING OF TWINE, CORDAGE OR ROPE; MADE UP FISHING NETS AND OTHER MADE UP NETS, OF TEXTILE MATERIALS',
  '5703': 'CARPETS AND OTHER TEXTILE FLOOR COVERINGS, TUFTED',
  '3506': 'PREPARED GLUES AND OTHER PREPARED ADHESIVES, NOT ELSEWHERE SPECIFIED OR INCLUDED',
  '7308': 'STRUCTURES AND PARTS OF STRUCTURES OF IRON OR STEEL (COLUMNS, TRUSSES, SHEDS)',
  '7312': 'STRANDED WIRE, ROPES, CABLES OF IRON OR STEEL (INVISIBLE GRILL WIRE CABLE)',
  '7610': 'ALUMINIUM STRUCTURES AND PARTS THEREOF (INVISIBLE GRILL TRACKS & PROFILES)',
  '7323': 'TABLE, KITCHEN OR OTHER HOUSEHOLD ARTICLES OF IRON OR STEEL (CLOTH DRYING HANGERS)',
  '7210': 'FLAT-ROLLED PRODUCTS OF IRON OR NON-ALLOY STEEL, PLATED OR COATED WITH ZINC (GI SHEETS)',
  '2505': 'NATURAL SANDS OF ALL KINDS, WHETHER OR NOT COLOURED (SILICA SAND INFILL)',
  '4004': 'WASTE, PARINGS AND SCRAP OF RUBBER (SBR INFILL GRANULES)',
  '9405': 'LUMINAIRES AND LIGHTING FITTINGS (LED FLOOD LIGHTS)',
  '8537': 'BOARDS, PANELS, CONSOLES FOR ELECTRIC CONTROL (ELECTRICAL PANEL & CABLING)',
  '9506': 'ARTICLES AND EQUIPMENT FOR GENERAL PHYSICAL CULTURE, GYMNASTICS OR ATHLETICS',
  '9954': 'GENERAL CONSTRUCTION SERVICES / BUILDING INSTALLATION AND COMPLETION SERVICES',
  '9965': 'GOODS TRANSPORT SERVICES (FREIGHT & LOGISTICS)',
  '9983': 'OTHER PROFESSIONAL, TECHNICAL AND BUSINESS SERVICES (ENGINEERING & SUPERVISION)',
  '6810': 'ARTICLES OF CEMENT, OF CONCRETE OR OF ARTIFICIAL STONE (PAVER BLOCKS)',
  '3918': 'FLOOR COVERINGS OF PLASTICS (SYNTHETIC / VINYL FLOORING)',
  '3208': 'PAINTS AND VARNISHES (ACRYLIC COURT COATING)'
};

function normalizeHsnJs(hsn, desc) {
  hsn = (hsn || '').toString().trim();
  desc = (desc || '').toString().toLowerCase();

  // Normalize legacy / erroneous codes
  if (hsn === '5' || hsn === '6702' || desc.includes('glue') || desc.includes('adhesive') || desc.includes('speb')) {
    if (desc.includes('glue') || desc.includes('adhesive') || desc.includes('speb')) return '3506';
    if (desc.includes('grass') || desc.includes('turf')) return '5703';
    return '3506';
  }

  // Installation & Civil Works Contract
  if (/(installation|fixing|erection|fitting|laying|civil|preparation|preperation|excavation|sub-?base|subbase|wmm|gsb|saucer\s*drain|drainage|asphalt|bitumin|acrylic\s*floor|cc\s*floor|concrete\s*base|concrete\s*floor|works\s*contract|labour|labor)/i.test(desc)) {
    return '9954';
  }

  if (hsn && hsn !== '—' && hsn !== '-' && /^[0-9]{4,8}$/.test(hsn)) {
    return hsn;
  }

  if (/(grass|turf|carpet)/i.test(desc)) return '5703';
  if (/(net|netting|twine|rope)/i.test(desc)) return '5608';
  if (/(invisible\s*grill|wire\s*rope)/i.test(desc)) return '7312';
  if (/(cloth\s*hanger)/i.test(desc)) return '7323';
  if (/(gi\s*sheet|ppgl)/i.test(desc)) return '7210';
  if (/(structure|pipe|pole|column|purlin|truss|beam)/i.test(desc)) return '7308';
  if (/(sand|silica)/i.test(desc)) return '2505';
  if (/(rubber|granule)/i.test(desc)) return '4004';
  if (/(light|flood)/i.test(desc)) return '9405';
  if (/(freight|transport)/i.test(desc)) return '9965';

  return hsn || '5608';
}

function getUqcJs(unit) {
  const u = (unit || '').toString().toUpperCase().trim();
  if (!u) return { code: 'SQF', label: 'SQF-SQUARE FEET' };
  if (['SQFT', 'SFT', 'SQ.FT', 'SQ FT', 'SQ. FT', 'SQUARE FEET', 'SQF'].includes(u) || u.startsWith('SQF')) return { code: 'SQF', label: 'SQF-SQUARE FEET' };
  if (['LTR', 'LTRS', 'LITER', 'LITERS', 'LITRE', 'LITRES', 'L'].includes(u) || u.startsWith('LTR')) return { code: 'LTR', label: 'LTR-LITRES' };
  if (['KG', 'KGS', 'KILOGRAM', 'KILOGRAMS'].includes(u) || u.startsWith('KGS')) return { code: 'KGS', label: 'KGS-KILOGRAMS' };
  if (['MTR', 'MTRS', 'METER', 'METERS', 'RFT', 'RUNNING FEET'].includes(u) || u.startsWith('MTR')) return { code: 'MTR', label: 'MTR-METERS' };
  if (['NOS', 'PCS', 'NO', 'NUMBERS', 'NUMBER', 'PIECES'].includes(u) || u.startsWith('NOS')) return { code: 'NOS', label: 'NOS-NUMBERS' };
  if (['SET', 'SETS'].includes(u) || u.startsWith('SET')) return { code: 'SET', label: 'SET-SETS' };
  if (['ROL', 'ROLL', 'ROLLS'].includes(u) || u.startsWith('ROL')) return { code: 'ROL', label: 'ROL-ROLLS' };
  if (['BOX', 'BOXES'].includes(u) || u.startsWith('BOX')) return { code: 'BOX', label: 'BOX-BOX' };
  if (['BAG', 'BAGS'].includes(u) || u.startsWith('BAG')) return { code: 'BAG', label: 'BAG-BAGS' };
  if (['BDL', 'BUNDLE', 'BUNDLES'].includes(u) || u.startsWith('BDL')) return { code: 'BDL', label: 'BDL-BUNDLES' };
  if (['SQM', 'SQMT', 'SQ.MT', 'SQUARE METERS'].includes(u) || u.startsWith('SQM')) return { code: 'SQM', label: 'SQM-SQUARE METERS' };
  if (['SQY', 'SQYD', 'SQ.YD', 'SQUARE YARDS'].includes(u) || u.startsWith('SQY')) return { code: 'SQY', label: 'SQY-SQUARE YARDS' };
  if (['TON', 'TONS', 'TONNES'].includes(u) || u.startsWith('TON')) return { code: 'TON', label: 'TON-TONNES' };
  if (['MTS', 'METRIC TON', 'MT'].includes(u) || u.startsWith('MTS')) return { code: 'MTS', label: 'MTS-METRIC TON' };
  if (['PAC', 'PACK', 'PACKS', 'PKT'].includes(u) || u.startsWith('PAC')) return { code: 'PAC', label: 'PAC-PACKS' };
  if (['PRS', 'PAIR', 'PAIRS'].includes(u) || u.startsWith('PRS')) return { code: 'PRS', label: 'PRS-PAIRS' };
  if (['CBM', 'CUBIC METERS'].includes(u) || u.startsWith('CBM')) return { code: 'CBM', label: 'CBM-CUBIC METERS' };
  if (['CMS', 'CENTIMETERS'].includes(u) || u.startsWith('CMS')) return { code: 'CMS', label: 'CMS-CENTIMETERS' };
  if (['OTH', 'OTHERS', 'LOT', 'LOTS', 'JOB'].includes(u) || u.startsWith('OTH')) return { code: 'OTH', label: 'OTH-OTHERS' };
  
  const code = u.includes('-') ? u.split('-')[0] : (u.substring(0, 3) || 'OTH');
  return { code: code, label: u };
}

function fmtNum(n, decimals = 2) {
  return Number(n).toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function onInvoiceSelectionChange() {
  const checkedBoxes = Array.from(document.querySelectorAll('.inv-checkbox:checked'));
  const selectedIds = checkedBoxes.map(cb => cb.value);

  // Update row visual styling
  document.querySelectorAll('.inv-checkbox').forEach(cb => {
    const tr = document.getElementById('row_' + cb.value);
    if (tr) {
      if (cb.checked) {
        tr.classList.add('inv-row-selected');
        tr.classList.remove('inv-row-unselected');
      } else {
        tr.classList.remove('inv-row-selected');
        tr.classList.add('inv-row-unselected');
      }
    }
  });

  // Filter selected invoice objects
  const activeInvoices = allInvoicesData.filter(inv => selectedIds.includes(inv.id));

  // Recalculate Table 12
  const hsnGroups = {};
  let totalTaxable = 0;
  let totalIgst = 0;
  let totalCgst = 0;
  let totalSgst = 0;
  let totalGrand = 0;

  activeInvoices.forEach(inv => {
    totalGrand += parseFloat(inv.grand_total || 0);
    const gstMode = inv.gst_mode || 'none';
    (inv.items || []).forEach(it => {
      const normHsn = normalizeHsnJs(it.hsn, it.description);
      const uqcObj = getUqcJs(it.unit);
      const rate = parseFloat(it.gst_rate || 5);
      const qty = parseFloat(it.qty || 0);
      const amt = parseFloat(it.amount || 0);

      let igst = 0, cgst = 0, sgst = 0;
      if (gstMode === 'igst') {
        igst = Math.round(amt * (rate / 100) * 100) / 100;
      } else if (gstMode === 'cgst_sgst') {
        const half = Math.round(amt * (rate / 200) * 100) / 100;
        cgst = half;
        sgst = half;
      }

      const key = `${normHsn}_${uqcObj.code}_${rate}`;
      if (!hsnGroups[key]) {
        hsnGroups[key] = {
          hsn: normHsn,
          desc: hsnMapDesc[normHsn.substring(0, 4)] || 'Other Outward Supplies',
          uqc: uqcObj.code,
          uqc_label: uqcObj.label,
          rate: rate,
          qty: 0,
          taxable: 0,
          igst: 0,
          cgst: 0,
          sgst: 0,
          items_count: 0
        };
      }
      hsnGroups[key].qty += qty;
      hsnGroups[key].taxable += amt;
      hsnGroups[key].igst += igst;
      hsnGroups[key].cgst += cgst;
      hsnGroups[key].sgst += sgst;
      hsnGroups[key].items_count++;

      totalTaxable += amt;
      totalIgst += igst;
      totalCgst += cgst;
      totalSgst += sgst;
    });
  });

  // Render Table 12 Body
  const tbody = document.getElementById('table12Body');
  const sortedKeys = Object.keys(hsnGroups).sort();
  let tbodyHtml = '';
  let rowIdx = 0;
  let sumQty = 0;

  sortedKeys.forEach(k => {
    rowIdx++;
    const g = hsnGroups[k];
    sumQty += g.qty;
    tbodyHtml += `
      <tr>
        <td class="text-center">
          <span class="badge bg-primary copy-pill px-2 py-1 fs-7" onclick="copyText('${g.hsn}', this)" title="Click to copy HSN">
            ${g.hsn} <i class="fa fa-copy ms-1 small"></i>
          </span>
        </td>
        <td>
          <span class="fw-semibold small text-dark d-block">${g.desc}</span>
          <span class="text-muted" style="font-size:0.75rem;">(${g.items_count} invoice items)</span>
        </td>
        <td class="text-center">
          <span class="badge bg-secondary copy-pill px-2 py-1 fs-7" onclick="copyText('${g.uqc}', this)" title="Click to copy UQC code">
            ${g.uqc_label}
          </span>
        </td>
        <td class="text-end">
          <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('${g.qty.toFixed(2)}', this)" title="Click to copy Quantity">
            ${fmtNum(g.qty)} <i class="fa fa-copy ms-1 text-muted"></i>
          </span>
        </td>
        <td class="text-end">
          <span class="badge bg-success-subtle text-success-emphasis border border-success copy-pill px-2 py-1 fs-7 fw-black" onclick="copyText('${g.taxable.toFixed(2)}', this)" title="Click to copy Taxable Value">
            ₹${fmtNum(g.taxable)} <i class="fa fa-copy ms-1 text-muted"></i>
          </span>
        </td>
        <td class="text-center">
          <span class="badge bg-warning text-dark copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('${g.rate}', this)" title="Click to copy Rate">
            ${g.rate}%
          </span>
        </td>
        <td class="text-end">
          <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('${g.igst.toFixed(2)}', this)" title="Click to copy IGST">
            ₹${fmtNum(g.igst)}
          </span>
        </td>
        <td class="text-end">
          <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('${g.cgst.toFixed(2)}', this)" title="Click to copy CGST">
            ₹${fmtNum(g.cgst)}
          </span>
        </td>
        <td class="text-end">
          <span class="badge bg-light text-dark border copy-pill px-2 py-1 fs-7 fw-bold" onclick="copyText('${g.sgst.toFixed(2)}', this)" title="Click to copy SGST">
            ₹${fmtNum(g.sgst)}
          </span>
        </td>
        <td class="text-center text-muted">0.00</td>
        <td class="text-center no-print">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyRow(${rowIdx})" title="Copy entire row to clipboard">
            <i class="fa fa-copy"></i>
          </button>
        </td>
      </tr>
    `;
  });

  if (sortedKeys.length === 0) {
    tbodyHtml = `<tr><td colspan="11" class="text-center py-4 text-muted">No invoices selected. Check at least one invoice below.</td></tr>`;
  }
  tbody.innerHTML = tbodyHtml;

  // Update Table 12 Footers
  document.getElementById('footQty').innerText = fmtNum(sumQty);
  document.getElementById('footTaxable').innerText = '₹' + fmtNum(totalTaxable);
  document.getElementById('footIgst').innerText = '₹' + fmtNum(totalIgst);
  document.getElementById('footCgst').innerText = '₹' + fmtNum(totalCgst);
  document.getElementById('footSgst').innerText = '₹' + fmtNum(totalSgst);
  const totTaxVal = totalIgst + totalCgst + totalSgst;
  document.getElementById('footTotTax').innerText = 'Total Tax: ₹' + fmtNum(totTaxVal);

  // Update Table 13 Stats
  const docCount = activeInvoices.length;
  let docFrom = '—';
  let docTo = '—';
  if (docCount > 0) {
    const sortedActive = [...activeInvoices].sort((a,b) => a.id.localeCompare(b.id, undefined, {numeric:true}));
    docFrom = sortedActive[0].id;
    docTo = sortedActive[docCount - 1].id;
  }
  document.getElementById('t13From').innerHTML = `${docFrom} <i class="fa fa-copy ms-1 small"></i>`;
  document.getElementById('t13To').innerHTML = `${docTo} <i class="fa fa-copy ms-1 small"></i>`;
  document.getElementById('t13Count').innerText = docCount;
  document.getElementById('t13Net').innerText = docCount;

  document.getElementById('guideFrom').innerText = docFrom;
  document.getElementById('guideTo').innerText = docTo;
  document.getElementById('guideCount').innerText = docCount;
  document.getElementById('guideNet').innerText = docCount;

  // Update Top Metric Cards
  document.getElementById('cardDocCount').innerText = `${docCount} Invoices`;
  document.getElementById('cardDocRange').innerText = `${docFrom} to ${docTo}`;
  document.getElementById('cardTaxable').innerText = '₹' + fmtNum(totalTaxable);
  document.getElementById('cardTax').innerText = '₹' + fmtNum(totTaxVal);
  document.getElementById('cardTaxBreakdown').innerText = `IGST: ₹${fmtNum(totalIgst)} | CGST+SGST: ₹${fmtNum(totalCgst + totalSgst)}`;
  document.getElementById('cardGrand').innerText = '₹' + fmtNum(totalGrand);
  document.getElementById('lblSelectedCount').innerText = docCount;

  // Update CSV Download Link
  const currentPeriod = document.querySelector('select[name="period"]').value;
  document.getElementById('btnDownloadCsv').href = `?period=${encodeURIComponent(currentPeriod)}&export=csv&invs=${selectedIds.join(',')}`;
}

function toggleAllInvoices(check) {
  document.querySelectorAll('.inv-checkbox').forEach(cb => cb.checked = check);
  document.getElementById('checkMaster').checked = check;
  onInvoiceSelectionChange();
}

function resetToQ2Recommended() {
  document.querySelectorAll('.inv-checkbox').forEach(cb => {
    cb.checked = defaultQ2List.includes(cb.value);
  });
  onInvoiceSelectionChange();
}

function onQuarterChange(val) {
  window.location.href = `?period=${encodeURIComponent(val)}`;
}

function copyText(txt, el) {
  navigator.clipboard.writeText(txt).then(() => {
    const original = el.innerHTML;
    el.innerHTML = '<i class="fa fa-check me-1"></i>Copied!';
    el.classList.add('bg-success');
    setTimeout(() => {
      el.innerHTML = original;
      el.classList.remove('bg-success');
    }, 1200);
  });
}

function copyRow(idx) {
  const tr = document.querySelectorAll('#table12Body tr')[idx - 1];
  if (!tr) return;
  const cells = Array.from(tr.querySelectorAll('td')).slice(0, 10).map(td => td.innerText.replace(/₹|,/g, '').replace(/\n.*$/g, '').trim());
  const rowText = cells.join('\t');
  navigator.clipboard.writeText(rowText).then(() => {
    alert('Row ' + idx + ' copied to clipboard! (Tab-separated for Excel/Portal)');
  });
}

function copyAllTable12() {
  const rows = Array.from(document.querySelectorAll('#table12Body tr')).map(tr => {
    return Array.from(tr.querySelectorAll('td')).slice(0, 10).map(td => td.innerText.replace(/₹|,/g, '').replace(/\n.*$/g, '').trim()).join('\t');
  });
  const allText = ['HSN\tDescription\tUQC\tQuantity\tTaxable Value\tRate\tIGST\tCGST\tSGST\tCess', ...rows].join('\n');
  navigator.clipboard.writeText(allText).then(() => {
    alert('All Table 12 rows copied to clipboard!\nYou can paste directly into Excel or your offline records.');
  });
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
