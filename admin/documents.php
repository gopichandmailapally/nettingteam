<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('documents','view');

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = $_POST['id'] ?? '';
    if ($id) {
        deleteDocument($id);
        flashSet('success', 'Document deleted.');
    }
    redirect('documents.php');
}

// Handle duplicate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate') {
    $id = $_POST['id'] ?? '';
    $doc = $id ? getDocument($id) : null;
    if ($doc) {
        $newDoc = $doc;
        $newDoc['id']         = generateDocNumber($doc['type'] ?? 'estimation');
        $newDoc['date']       = date('Y-m-d');
        $newDoc['status']     = 'draft';
        $newDoc['created_at'] = date('c');
        $newDoc['updated_at'] = date('c');
        saveDocument($newDoc);
        flashSet('success', "Document duplicated successfully as " . $newDoc['id'] . " (Draft)");
        redirect('view.php?id=' . urlencode($newDoc['id']));
    } else {
        flashSet('error', 'Document not found.');
        redirect('documents.php');
    }
}

// Clear filter request
if (isset($_GET['clear'])) {
    unset($_SESSION['doc_filter_type'], $_SESSION['doc_filter_search']);
    redirect('documents.php');
}

// Persist and restore filter type across navigation
if (isset($_GET['type'])) {
    $filter = trim($_GET['type']);
    $_SESSION['doc_filter_type'] = $filter;
} elseif (isset($_SESSION['doc_filter_type'])) {
    $filter = $_SESSION['doc_filter_type'];
} else {
    $filter = '';
}

// Persist and restore search query
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['doc_filter_search'] = $search;
} elseif (isset($_SESSION['doc_filter_search'])) {
    $search = $_SESSION['doc_filter_search'];
} else {
    $search = '';
}

$docs = getAllDocuments();

if ($filter) {
    if ($filter === 'proforma_invoice' || $filter === 'proforma') {
        $docs = array_filter($docs, fn($d) => in_array($d['type'] ?? '', ['proforma_invoice', 'proforma']) || strpos($d['id'] ?? '', 'PI-') === 0);
    } else {
        $docs = array_filter($docs, fn($d) => ($d['type'] ?? '') === $filter);
    }
}
if ($search) $docs = array_filter($docs, fn($d) =>
    stripos($d['id'] ?? '', $search) !== false ||
    stripos($d['bill_to']['name'] ?? '', $search) !== false ||
    stripos($d['bill_to']['phone'] ?? '', $search) !== false ||
    stripos($d['bill_to']['whatsapp'] ?? '', $search) !== false
);
usort($docs, function($a, $b) use ($filter) {
    // When filtering by document type (Invoices, Estimations, Bills, Proformas):
    // Show strictly latest number to old number (descending sequence)
    if ($filter && in_array($filter, ['tax_invoice', 'invoice', 'estimation', 'bill', 'proforma_invoice', 'proforma'])) {
        return strnatcasecmp($b['id'] ?? '', $a['id'] ?? '');
    }

    // For All Documents (mixed types):
    // 1. Group by Year-Month (e.g. 202610 before 202609)
    $ymA = '';
    $ymB = '';
    if (preg_match('/-(\d{6})-/', $a['id'] ?? '', $mA)) $ymA = $mA[1];
    else $ymA = str_replace('-', '', substr($a['date'] ?? '', 0, 7));

    if (preg_match('/-(\d{6})-/', $b['id'] ?? '', $mB)) $ymB = $mB[1];
    else $ymB = str_replace('-', '', substr($b['date'] ?? '', 0, 7));

    if ($ymA !== $ymB) {
        return strcmp($ymB, $ymA);
    }

    // 2. If same prefix, sort latest number to oldest number
    $prefixA = explode('-', $a['id'] ?? '')[0] ?? '';
    $prefixB = explode('-', $b['id'] ?? '')[0] ?? '';
    if ($prefixA === $prefixB) {
        return strnatcasecmp($b['id'] ?? '', $a['id'] ?? '');
    }

    // 3. Across different types in the same month, compare date descending
    $dateA = $a['date'] ?? '';
    $dateB = $b['date'] ?? '';
    if ($dateA !== $dateB) {
        return strcmp($dateB, $dateA);
    }

    return strcmp($b['id'] ?? '', $a['id'] ?? '');
});
$docs = array_values($docs);

$pageTitle = 'All Documents';
require_once __DIR__ . '/includes/layout.php';
?>

<!-- Filter Bar -->
<div class="card border-0 rounded-3 mb-3" style="box-shadow:0 2px 12px rgba(0,0,0,.07)">
  <div class="card-body py-2">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center filter-form">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by doc # or customer…" value="<?= e($search) ?>" style="max-width:220px;">
      <select name="type" class="form-select form-select-sm" style="max-width:160px;" onchange="this.form.submit()">
        <option value="">All Types</option>
        <option value="estimation"       <?= $filter==='estimation'       ? 'selected':'' ?>>Estimations</option>
        <option value="proforma_invoice" <?= in_array($filter, ['proforma_invoice','proforma']) ? 'selected':'' ?>>Proforma Invoices</option>
        <option value="tax_invoice"      <?= $filter==='tax_invoice'      ? 'selected':'' ?>>Tax Invoices</option>
        <option value="bill"             <?= $filter==='bill'             ? 'selected':'' ?>>Bills</option>
      </select>
      <button class="btn btn-sm btn-primary">Search</button>
      <?php if ($search !== '' || $filter !== ''): ?>
      <a href="documents.php?clear=1" class="btn btn-sm btn-outline-secondary">Clear</a>
      <?php endif; ?>
      <div class="ms-auto d-flex gap-2">
        <a href="gstr1.php" class="btn btn-sm btn-outline-primary fw-bold">
          <i class="fa fa-file-invoice-dollar me-1"></i>Table 12 HSN Helper
        </a>
        <a href="create.php" class="btn btn-sm text-white" style="background:var(--red);">
          <i class="fa fa-plus me-1"></i>New Document
        </a>
      </div>
    </form>
  </div>
</div>

<style>
  .col-doc-id, th.col-doc-id, td.col-doc-id {
    white-space: nowrap !important;
  }
  .col-type, th.col-type, td.col-type {
    white-space: nowrap !important;
  }
  .col-date, th.col-date, td.col-date {
    white-space: nowrap !important;
  }
  .type-badge {
    white-space: nowrap !important;
  }
  @media (max-width: 576px) {
    .filter-form input[name="q"] { max-width: 100% !important; flex: 1 1 100% !important; }
    .filter-form select[name="type"] { max-width: 100% !important; flex: 1 1 calc(50% - 0.5rem) !important; }
    .filter-form .btn-primary { flex: 1 1 calc(50% - 0.5rem) !important; }
    .filter-form .ms-auto { margin-left: 0 !important; width: 100% !important; margin-top: 0.25rem !important; }
    .filter-form .ms-auto a { width: 100% !important; text-align: center !important; }
  }
</style>

<div class="card table-card">
  <div class="d-md-none px-3 py-2 bg-light border-bottom text-muted small" style="font-size:0.75rem;">
    <i class="fa fa-arrows-left-right text-primary me-1"></i>Swipe table sideways to view Total, Status &amp; Actions
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th class="col-doc-id">Doc #</th>
          <th class="col-type">Type</th>
          <th class="col-date">Date</th>
          <th>Customer</th>
          <th>Items</th>
          <th class="text-end">Grand Total</th>
          <th>Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($docs)): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">
          <i class="fa fa-folder-open fa-2x d-block mb-2 opacity-25"></i>
          No documents found. <a href="create.php">Create one now</a>.
        </td></tr>
      <?php else: ?>
        <?php foreach ($docs as $d): ?>
        <tr>
          <td class="col-doc-id"><a href="view.php?id=<?= urlencode($d['id']) ?>" class="fw-semibold text-primary text-decoration-none" style="white-space:nowrap;"><?= e($d['id']) ?></a></td>
          <td class="col-type">
            <?php $t = $d['type'] ?? 'estimation'; ?>
            <span class="type-badge type-<?= ($t==='estimation'?'est':($t==='tax_invoice'?'inv':(in_array($t,['proforma_invoice','proforma'])?'pi':'bill'))) ?>" style="white-space:nowrap;">
              <?= docTypeLabel($t, $d['is_gst'] ?? true) ?>
            </span>
            <?php if (($d['estimation_subtype'] ?? '') === 'multi_block'): ?>
            <div style="margin-top:2px;"><span class="badge bg-info-subtle text-info border border-info" style="font-size:0.65rem;font-weight:700;"><i class="fa fa-cubes me-1"></i>Multi-Block</span></div>
            <?php endif; ?>
          </td>
          <td class="col-date" style="white-space:nowrap;"><?= e(date('d M Y', strtotime($d['date'] ?? 'today'))) ?></td>
          <td>
            <div class="fw-semibold"><?= e($d['bill_to']['name'] ?? '—') ?></div>
            <?php if (!empty($d['bill_to']['phone'])): ?>
            <div style="font-size:0.75rem;color:#0284c7;font-weight:600;"><i class="fa fa-phone me-1"></i><?= e($d['bill_to']['phone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($d['bill_to']['gstin'])): ?>
            <small class="text-muted"><?= e($d['bill_to']['gstin']) ?></small>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-light text-dark"><?= count($d['items'] ?? []) ?> item(s)</span></td>
          <td class="text-end">
            <div class="fw-bold">₹<?= fmtAmount($d['grand_total'] ?? 0) ?></div>
            <?php if (in_array($d['type'] ?? '', ['tax_invoice', 'bill', 'proforma'])): ?>
            <?php
            $ps = $d['payment_status'] ?? (($d['status'] ?? '') === 'paid' ? 'paid' : 'unpaid');
            $gt = (float)($d['grand_total'] ?? 0);
            $adv = (float)($d['advance_amount'] ?? 0);
            $due = max(0, round($gt - $adv, 2));
            ?>
            <?php if ($ps === 'paid'): ?>
              <button type="button" class="badge bg-success text-white border-0 shadow-sm" style="font-size:0.7rem;cursor:pointer;padding:3px 8px;" onclick="togglePaymentDirect('<?= e($d['id']) ?>', this)" title="Click to switch to Unpaid">✓ Paid</button>
            <?php elseif ($ps === 'partial'): ?>
              <span class="badge bg-warning-subtle text-warning-emphasis border border-warning" style="font-size:0.68rem;" title="Adv: ₹<?= fmtAmount($adv) ?> | Due: ₹<?= fmtAmount($due) ?>">Adv: ₹<?= fmtAmount($adv) ?> | Due: ₹<?= fmtAmount($due) ?></span>
            <?php else: ?>
              <button type="button" class="badge bg-danger text-white border-0 shadow-sm" style="font-size:0.7rem;cursor:pointer;padding:3px 8px;" onclick="togglePaymentDirect('<?= e($d['id']) ?>', this)" title="Click to switch to Paid">⏳ Unpaid</button>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="id" value="<?= e($d['id']) ?>">
              <select name="new_status" class="form-select form-select-sm border-0 bg-transparent" style="font-size:.78rem;padding:0;" onchange="updateStatus(this)">
                <?php foreach (['draft','sent','accepted','rejected','paid'] as $s): ?>
                <option value="<?= $s ?>" <?= ($d['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="text-center">
            <div class="btn-group btn-group-sm">
              <a href="view.php?id=<?= urlencode($d['id']) ?>" class="btn btn-outline-primary" title="View"><i class="fa fa-eye"></i></a>
              <a href="create.php?edit=<?= urlencode($d['id']) ?>" class="btn btn-outline-secondary" title="Edit Form"><i class="fa fa-pen"></i></a>
              <?php if (($d['type'] ?? "") === "estimation" || ($d['estimation_subtype'] ?? "") === "turnkey_court"): ?>
              <a href="cad_designer.php?edit=<?= urlencode($d['id']) ?>" class="btn btn-outline-info text-info" title="Edit in GCM XTREME CAD & Revit Studio"><i class="fa fa-compass-drafting"></i></a>
              <?php endif; ?>
              <button type="button" class="btn btn-outline-info" title="Duplicate" onclick="confirmDuplicate('<?= e($d['id']) ?>', '<?= e(addslashes($d['bill_to']['name'] ?? '—')) ?>', '<?= fmtAmount($d['grand_total'] ?? 0) ?>')"><i class="fa fa-copy"></i></button>
              <button type="button" class="btn btn-outline-danger" title="Delete"
                onclick="confirmDelete('<?= e($d['id']) ?>')"><i class="fa fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Duplicate Modal -->
<div class="modal fade" id="dupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="fa fa-copy text-info me-2"></i>Duplicate Document</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-3">
          <i class="fa fa-clone fa-3x text-info"></i>
        </div>
        <h5 class="fw-bold mb-1" id="dupDocId"></h5>
        <p class="text-muted mb-3" id="dupDocClient"></p>
        <p class="mb-0 text-secondary" style="font-size:.9rem;">
          Duplicate this document as a new <strong>Draft</strong> with a fresh document number?
        </p>
      </div>
      <div class="modal-footer justify-content-center bg-light">
        <form method="POST" id="dupForm">
          <input type="hidden" name="action" value="duplicate">
          <input type="hidden" name="id" id="dupId">
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info text-white fw-bold px-4"><i class="fa fa-copy me-1"></i>Duplicate Now</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="delModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Delete Document</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body text-center">
        <i class="fa fa-triangle-exclamation fa-2x text-danger mb-2 d-block"></i>
        Delete <strong id="delDocId"></strong>? This cannot be undone.
      </div>
      <div class="modal-footer justify-content-center">
        <form method="POST" id="delForm">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="delId">
          <button class="btn btn-danger">Delete</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDuplicate(id, name, amount) {
  document.getElementById('dupDocId').textContent = id;
  document.getElementById('dupDocClient').textContent = (name || '—') + (amount ? ' • ₹' + amount : '');
  document.getElementById('dupId').value = id;
  new bootstrap.Modal(document.getElementById('dupModal')).show();
}

function confirmDelete(id) {
  document.getElementById('delDocId').textContent = id;
  document.getElementById('delId').value = id;
  new bootstrap.Modal(document.getElementById('delModal')).show();
}
function updateStatus(sel) {
  const id  = sel.closest('form').querySelector('[name=id]').value;
  const val = sel.value;
  fetch('ajax.php?action=status', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'id='+encodeURIComponent(id)+'&status='+encodeURIComponent(val)
  }).then(r=>r.json()).then(d=>{
    if (!d.ok) {
      alert('Failed to update status.');
    } else {
      location.reload();
    }
  });
}

function togglePaymentDirect(docId, btn) {
  btn.disabled = true;
  btn.style.opacity = '0.5';
  fetch('ajax.php?action=toggle_payment', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id=' + encodeURIComponent(docId)
  }).then(r => r.json()).then(res => {
    if (res.ok) {
      location.reload();
    } else {
      alert('Error updating payment status: ' + (res.msg || 'Unknown error'));
      btn.disabled = false;
      btn.style.opacity = '1';
    }
  }).catch(err => {
    alert('Network error while updating payment status');
    btn.disabled = false;
    btn.style.opacity = '1';
  });
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
