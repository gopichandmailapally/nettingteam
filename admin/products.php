<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$jsonPath = __DIR__ . '/data/master_product_catalog.json';
$jsPath   = __DIR__ . '/assets/js/master_product_catalog.js';

function getCatalogData($jsonPath) {
    if (!file_exists($jsonPath)) return [];
    $raw = file_get_contents($jsonPath);
    return json_decode($raw, true) ?: [];
}

function saveCatalogData($jsonPath, $jsPath, $data) {
    // Save JSON
    file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // Save JS
    $jsContent = "// GCM Master Product Catalog (Auto-generated from master_product_catalog.json)
"
               . "window.MasterProductCatalog = " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";
"
               . "if (typeof module !== 'undefined' && module.exports) { module.exports = window.MasterProductCatalog; }
";
    file_put_contents($jsPath, $jsContent);
}

$catalog = getCatalogData($jsonPath);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_product') {
        $id = trim($_POST['product_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $rate = (float)($_POST['default_rate'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'SQFT');
        $hsn = trim($_POST['hsn'] ?? '7308');
        $gst = (float)($_POST['gst_rate'] ?? 18);
        $desc = trim($_POST['description'] ?? '');
        $specsRaw = trim($_POST['specs'] ?? '');
        $specs = $specsRaw ? array_values(array_filter(array_map('trim', explode("\n", $specsRaw)))) : [$name];

        $found = false;
        foreach ($catalog as &$p) {
            if ($p['id'] === $id) {
                $p['name'] = $name;
                $p['category'] = $cat;
                $p['default_rate'] = $rate;
                $p['unit'] = $unit;
                $p['hsn'] = $hsn;
                $p['gst_rate'] = $gst;
                $p['description'] = $desc;
                $p['specs'] = $specs;
                $found = true;
                break;
            }
        }
        unset($p);

        if (!$found) {
            // New product
            if (!$id) {
                $id = 'prod_' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', substr($name, 0, 20))) . '_' . time();
            }
            $catalog[] = [
                'id' => $id,
                'category' => $cat,
                'name' => $name,
                'description' => $desc,
                'hsn' => $hsn,
                'gst_rate' => $gst,
                'unit' => $unit,
                'default_rate' => $rate,
                'specs' => $specs
            ];
        }

        saveCatalogData($jsonPath, $jsPath, $catalog);
        flashSet('success', 'Product "' . htmlspecialchars($name) . '" saved successfully and synced with estimations & studio!');
        redirect('products.php');
    }

    if ($action === 'delete_product') {
        $delId = trim($_POST['delete_id'] ?? '');
        $catalog = array_values(array_filter($catalog, function($p) use ($delId) {
            return $p['id'] !== $delId;
        }));
        saveCatalogData($jsonPath, $jsPath, $catalog);
        flashSet('success', 'Product deleted successfully.');
        redirect('products.php');
    }
}

$pageTitle = 'Master Product Catalog & Rates';
require_once __DIR__ . '/layout.php';

// Unique categories
$categories = array_values(array_unique(array_filter(array_column($catalog, 'category'))));
sort($categories);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0 text-dark fw-bold"><i class="fa fa-boxes-stacked me-2 text-danger"></i>Master Products, Rates &amp; Specs</h4>
    <small class="text-muted">Manage all <?= count($catalog) ?> engineering products, rates, GST %, HSN codes &amp; specs used across Normal Estimations, Bills &amp; GCM XTREME</small>
  </div>
  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" onclick="openProductModal()">
      <i class="fa fa-plus me-1"></i>Add New Product
    </button>
    <a href="create.php" class="btn btn-sm btn-outline-danger fw-bold px-3">
      <i class="fa fa-file-invoice me-1"></i>Create Quotation
    </a>
  </div>
</div>

<!-- Search & Category Filter Toolbar -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-2 px-3 bg-white rounded">
    <div class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0"><i class="fa fa-magnifying-glass text-muted"></i></span>
          <input type="text" id="catSearch" class="form-control border-start-0" placeholder="Search by name, spec, brand, HSN or ID..." oninput="filterCatalogTable()">
          <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('catSearch').value='';filterCatalogTable();"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <div class="col-md-4">
        <select id="catFilter" class="form-select form-select-sm" onchange="filterCatalogTable()">
          <option value="">-- All Categories (<?= count($catalog) ?> Items) --</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 text-end text-muted small">
        Showing <strong id="matchCount" class="text-danger"><?= count($catalog) ?></strong> of <?= count($catalog) ?> items
      </div>
    </div>
  </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm">
  <div class="table-responsive" style="max-height: calc(100vh - 240px); overflow-y:auto;">
    <table class="table table-hover table-striped align-middle mb-0" id="productsTable" style="font-size:12.5px;">
      <thead class="table-dark sticky-top">
        <tr>
          <th style="width:50px;">#</th>
          <th style="min-width:260px;">Product Name &amp; Category</th>
          <th style="min-width:280px;">Engineering Specifications</th>
          <th style="width:90px;" class="text-center">HSN</th>
          <th style="width:80px;" class="text-center">GST %</th>
          <th style="width:90px;" class="text-center">Unit</th>
          <th style="width:120px;" class="text-end">Default Rate (₹)</th>
          <th style="width:100px;" class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($catalog as $idx => $p): ?>
          <tr class="product-row" data-cat="<?= htmlspecialchars($p['category'] ?? '') ?>" data-search="<?= htmlspecialchars(strtolower(($p['name'] ?? '') . ' ' . ($p['description'] ?? '') . ' ' . ($p['category'] ?? '') . ' ' . ($p['hsn'] ?? '') . ' ' . implode(' ', $p['specs'] ?? []))) ?>">
            <td class="text-muted small text-center"><?= $idx + 1 ?></td>
            <td>
              <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['name'] ?? '') ?></div>
              <span class="badge bg-secondary-subtle text-secondary border py-0 px-2" style="font-size:10px;">
                <i class="fa fa-tag me-1"></i><?= htmlspecialchars($p['category'] ?? 'General') ?>
              </span>
              <span class="text-muted font-monospace ms-1" style="font-size:9.5px;">#<?= htmlspecialchars($p['id'] ?? '') ?></span>
            </td>
            <td>
              <div class="text-muted small mb-1" style="line-height:1.35;"><?= htmlspecialchars($p['description'] ?? '') ?></div>
              <?php if (!empty($p['specs']) && is_array($p['specs'])): ?>
                <ul class="mb-0 ps-3 small text-secondary" style="font-size:11px;">
                  <?php foreach (array_slice($p['specs'], 0, 3) as $sp): ?>
                    <li><?= htmlspecialchars($sp) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </td>
            <td class="text-center font-monospace fw-bold text-secondary"><?= htmlspecialchars($p['hsn'] ?? '7308') ?></td>
            <td class="text-center"><span class="badge bg-info-subtle text-info border"><?= (float)($p['gst_rate'] ?? 18) ?>%</span></td>
            <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['unit'] ?? 'SQFT') ?></span></td>
            <td class="text-end fw-bold text-success" style="font-size:13px;">₹<?= number_format((float)($p['default_rate'] ?? 0), 2) ?></td>
            <td class="text-center">
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary py-1 px-2" title="Edit Product" onclick='editProduct(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                  <i class="fa fa-pen"></i>
                </button>
                <button type="button" class="btn btn-outline-danger py-1 px-2" title="Delete Product" onclick="confirmDeleteProduct('<?= htmlspecialchars($p['id']) ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                  <i class="fa fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add / Edit Product -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg border-0">
      <form method="POST" action="products.php">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id" id="mProdId" value="">
        <div class="modal-header bg-dark text-white py-2 px-3">
          <h5 class="modal-title fs-6 fw-bold"><i class="fa fa-box-open me-2 text-warning"></i><span id="mModalTitle">Edit Product</span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3" style="font-size:12.5px;">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label text-dark fw-bold mb-1">Product Title / Name: *</label>
              <input type="text" name="name" id="mProdName" class="form-control form-control-sm fw-bold" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-dark fw-bold mb-1">Category: *</label>
              <input type="text" name="category" id="mProdCat" list="categoryList" class="form-control form-control-sm" required>
              <datalist id="categoryList">
                <?php foreach ($categories as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-12">
              <label class="form-label text-dark fw-bold mb-1">Full Description &amp; Engineering Scope:</label>
              <textarea name="description" id="mProdDesc" rows="3" class="form-control form-control-sm"></textarea>
            </div>
            <div class="col-md-3">
              <label class="form-label text-dark fw-bold mb-1">Default Rate (₹): *</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" step="any" min="0" name="default_rate" id="mProdRate" class="form-control form-control-sm fw-bold text-success text-end" required>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label text-dark fw-bold mb-1">Measurement Unit: *</label>
              <select name="unit" id="mProdUnit" class="form-select form-select-sm" required>
                <option value="SQFT">SQFT (Square Feet)</option>
                <option value="RFT">RFT (Running Feet)</option>
                <option value="NOS">NOS (Numbers / Units)</option>
                <option value="SET">SET (Complete Set)</option>
                <option value="KG">KG (Kilograms)</option>
                <option value="SQM">SQM (Square Meters)</option>
                <option value="MTR">MTR (Meters)</option>
                <option value="LOT">LOT (Lumpsum Lot)</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-dark fw-bold mb-1">HSN Code:</label>
              <input type="text" name="hsn" id="mProdHsn" class="form-control form-control-sm" value="7308">
            </div>
            <div class="col-md-3">
              <label class="form-label text-dark fw-bold mb-1">GST Rate (%):</label>
              <select name="gst_rate" id="mProdGst" class="form-select form-select-sm">
                <option value="18">18% (Standard Engineering)</option>
                <option value="12">12%</option>
                <option value="5">5%</option>
                <option value="28">28%</option>
                <option value="0">0% (Exempt)</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label text-dark fw-bold mb-1">Quotation Bullet Points (One specification per line):</label>
              <textarea name="specs" id="mProdSpecs" rows="3" class="form-control form-control-sm" placeholder="e.g. 50mm Pile Height&#10;12,000 DTEX Bi-color Monofilament&#10;Includes 25mm EPDM Washers &amp; Fasteners"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success fw-bold px-4"><i class="fa fa-check me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Delete Product Confirmation -->
<form id="deleteForm" method="POST" action="products.php" style="display:none;">
  <input type="hidden" name="action" value="delete_product">
  <input type="hidden" name="delete_id" id="deleteId" value="">
</form>

<script>
function filterCatalogTable() {
  var q = document.getElementById('catSearch').value.toLowerCase().trim();
  var cat = document.getElementById('catFilter').value;
  var rows = document.querySelectorAll('.product-row');
  var matches = 0;

  rows.forEach(function(r) {
    var rCat = r.getAttribute('data-cat') || '';
    var rText = r.getAttribute('data-search') || '';
    var matchCat = !cat || rCat === cat;
    var matchText = !q || rText.indexOf(q) !== -1;

    if (matchCat && matchText) {
      r.style.display = '';
      matches++;
    } else {
      r.style.display = 'none';
    }
  });

  document.getElementById('matchCount').textContent = matches;
}

function openProductModal() {
  document.getElementById('mModalTitle').textContent = 'Add New Master Product';
  document.getElementById('mProdId').value = '';
  document.getElementById('mProdName').value = '';
  document.getElementById('mProdCat').value = 'Roofing & Canopy';
  document.getElementById('mProdDesc').value = '';
  document.getElementById('mProdRate').value = '';
  document.getElementById('mProdUnit').value = 'SQFT';
  document.getElementById('mProdHsn').value = '7308';
  document.getElementById('mProdGst').value = '18';
  document.getElementById('mProdSpecs').value = '';
  var m = new bootstrap.Modal(document.getElementById('productModal'));
  m.show();
}

function editProduct(p) {
  document.getElementById('mModalTitle').textContent = 'Edit Product: ' + (p.name || '');
  document.getElementById('mProdId').value = p.id || '';
  document.getElementById('mProdName').value = p.name || '';
  document.getElementById('mProdCat').value = p.category || '';
  document.getElementById('mProdDesc').value = p.description || '';
  document.getElementById('mProdRate').value = p.default_rate || p.rate || 0;
  document.getElementById('mProdUnit').value = p.unit || 'SQFT';
  document.getElementById('mProdHsn').value = p.hsn || '7308';
  document.getElementById('mProdGst').value = p.gst_rate || 18;
  document.getElementById('mProdSpecs').value = (p.specs && Array.isArray(p.specs)) ? p.specs.join('\n') : (p.name || '');
  var m = new bootstrap.Modal(document.getElementById('productModal'));
  m.show();
}

function confirmDeleteProduct(id, name) {
  if (confirm('Are you sure you want to delete this master product?\n\n' + name + '\n\nThis will remove it from future quotation suggestions.')) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteForm').submit();
  }
}
</script>
