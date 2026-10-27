<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('companies','view');

// POST always wins — prevents GET ?action=new overriding POST action=create
$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['action'] ?? '')
    : ($_GET['action'] ?? '');
$editId = $_GET['edit']   ?? null;

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (in_array($action, ['create', 'update'])) {
        $id      = ($action === 'update') ? ($_POST['id'] ?? null) : null;
        $company = buildCompanyFromPost($_POST, $id);

        // Handle file uploads
        foreach (['signature', 'stamp', 'logo'] as $ftype) {
            if (!empty($_FILES[$ftype]['name'])) {
                $result = uploadCompanyFile($ftype, $company['id'], $ftype);
                if ($result['ok']) {
                    $company[$ftype] = $result['path'];
                    // Clean up old file
                    $old = $company['existing_' . $ftype] ?? null;
                    if ($old) {
                        $oldPath = __DIR__ . '/' . preg_replace('/\?.*$/', '', $old);
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }
                }
            }
        }

        saveCompany($company);
        flashSet('success', 'Company saved: ' . $company['name']);
        redirect('companies.php');
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) { deleteCompany($id); flashSet('success', 'Company deleted.'); }
        redirect('companies.php');
    }

    if ($action === 'set_default') {
        $id   = $_POST['id'] ?? '';
        $list = loadData('companies');
        foreach ($list as &$c) { $c['is_default'] = ($c['id'] === $id); }
        unset($c);
        saveData('companies', $list);
        flashSet('success', 'Default company updated.');
        redirect('companies.php');
    }

    if ($action === 'remove_img') {
        $id    = $_POST['id']    ?? '';
        $ftype = $_POST['ftype'] ?? '';
        if ($id && in_array($ftype, ['signature','stamp','logo'])) {
            $comp = getCompany($id);
            if ($comp) {
                $old = $comp[$ftype] ?? null;
                if ($old) { $p = __DIR__ . '/' . preg_replace('/\?.*$/','',$old); if(file_exists($p)) @unlink($p); }
                $comp[$ftype] = null;
                saveCompany($comp);
                flashSet('success', ucfirst($ftype) . ' removed.');
            }
        }
        redirect('companies.php?edit=' . urlencode($id));
    }
}

$companies = getAllCompanies();
$editComp  = $editId ? getCompany($editId) : null;
$showForm  = ($action === 'new') || $editId;

$pageTitle = 'Companies';
require_once __DIR__ . '/includes/layout.php';
?>
<style>
.company-card { border:none; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); overflow:hidden; transition:box-shadow .2s; }
.company-card:hover { box-shadow:0 4px 24px rgba(0,0,0,.14); }
.company-card .card-header { background:var(--navy); color:#fff; padding:.8rem 1.1rem; }
.company-card .card-header h6 { margin:0; font-size:1rem; font-weight:800; letter-spacing:1.5px; }
.default-badge { background:#27ae60; color:#fff; font-size:.65rem; font-weight:700; padding:.2em .6em; border-radius:4px; letter-spacing:.5px; }
.bank-box { background:#f0f7ff; border:1px solid #c7d9f5; border-radius:8px; padding:.75rem 1rem; }
.upload-preview { width:100%; background:#f8f9fc; border:2px dashed #d0d8e8; border-radius:8px; padding:.75rem; text-align:center; min-height:60px; display:flex; align-items:center; justify-content:center; }
.upload-preview img { max-height:55px; max-width:130px; object-fit:contain; }
.form-section-hdr { font-weight:700; font-size:.8rem; text-transform:uppercase; letter-spacing:1px; color:var(--red); margin:1rem 0 .5rem; border-bottom:2px solid #f0f2f7; padding-bottom:.3rem; }
</style>

<?php if ($showForm): ?>
<!-- ── Company Form ─────────────────────────────────────────────────────── -->
<div class="row g-3">
<div class="col-lg-7">
<div class="card border-0 rounded-3" style="box-shadow:0 2px 16px rgba(0,0,0,.1);">
  <div class="card-header" style="background:var(--navy);color:#fff;padding:1rem 1.5rem;">
    <h6 class="mb-0 fw-bold"><i class="fa fa-building me-2"></i><?= $editId ? 'Edit Company' : 'Add New Company' ?></h6>
  </div>
  <div class="card-body p-3">
  <form method="POST" action="companies.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
    <?php if ($editId): ?><input type="hidden" name="id" value="<?= e($editId) ?>"><?php endif; ?>
    <?php foreach (['signature','stamp','logo'] as $f): ?>
    <input type="hidden" name="existing_<?= $f ?>" value="<?= e($editComp[$f] ?? '') ?>">
    <?php endforeach; ?>

    <p class="form-section-hdr"><i class="fa fa-building me-1"></i>Company Identity</p>
    <div class="row g-2">
      <div class="col-md-8 mb-2">
        <label class="form-label small fw-semibold">Company Name *</label>
        <input type="text" name="name" class="form-control form-control-sm" value="<?= e($editComp['name'] ?? '') ?>" required placeholder="ABC ENTERPRISES" style="text-transform:uppercase;">
      </div>
      <div class="col-md-4 mb-2">
        <label class="form-label small fw-semibold">GSTIN</label>
        <input type="text" name="gstin" class="form-control form-control-sm" value="<?= e($editComp['gstin'] ?? '') ?>" maxlength="15" style="text-transform:uppercase;" placeholder="22AAAAA0000A1Z5">
      </div>
      <div class="col-md-6 mb-2">
        <label class="form-label small fw-semibold">Proprietor / Owner Name</label>
        <input type="text" name="proprietor" class="form-control form-control-sm" value="<?= e($editComp['proprietor'] ?? '') ?>">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label small fw-semibold">Primary Phone</label>
        <input type="text" name="phone" class="form-control form-control-sm" value="<?= e($editComp['phone'] ?? '') ?>">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label small fw-semibold">Secondary Phone</label>
        <input type="text" name="phone2" class="form-control form-control-sm" value="<?= e($editComp['phone2'] ?? '') ?>">
      </div>
      <div class="col-md-9 mb-2">
        <label class="form-label small fw-semibold">Full Address</label>
        <textarea name="address" class="form-control form-control-sm" rows="2"><?= e($editComp['address'] ?? '') ?></textarea>
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label small fw-semibold">Email</label>
        <input type="email" name="email" class="form-control form-control-sm" value="<?= e($editComp['email'] ?? '') ?>">
      </div>
            <div class="col-md-4 mb-2">
        <label class="form-label small fw-semibold">Country</label>
        <select name="country" class="form-select form-select-sm">
          <?php foreach (getCountriesList() as $cName => $cInfo): ?>
            <option value="<?= e($cName) ?>" <?= ($editComp['country'] ?? 'India') === $cName ? 'selected' : '' ?>><?= e($cName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-2">
        <label class="form-label small fw-semibold">State</label>
        <input type="text" name="state" class="form-control form-control-sm" value="<?= e($editComp['state'] ?? '') ?>">
      </div>
      <div class="col-md-2 mb-2">
        <label class="form-label small fw-semibold">State Code</label>
        <input type="text" name="state_code" class="form-control form-control-sm" value="<?= e($editComp['state_code'] ?? '') ?>" maxlength="2">
      </div>
    </div>

    <p class="form-section-hdr"><i class="fa fa-landmark me-1"></i>Bank Details</p>
    <div class="row g-2 bank-box mb-3">
      <div class="col-md-6 mb-2">
        <label class="form-label small fw-semibold">Bank Name</label>
        <input type="text" name="bank_name" class="form-control form-control-sm" value="<?= e($editComp['bank_name'] ?? '') ?>" placeholder="e.g. State Bank of India">
      </div>
      <div class="col-md-6 mb-2">
        <label class="form-label small fw-semibold">Account Number</label>
        <input type="text" name="bank_account" class="form-control form-control-sm" value="<?= e($editComp['bank_account'] ?? '') ?>" placeholder="0000000000000">
      </div>
      <div class="col-md-4 mb-2">
        <label class="form-label small fw-semibold">IFSC Code</label>
        <input type="text" name="bank_ifsc" class="form-control form-control-sm" value="<?= e($editComp['bank_ifsc'] ?? '') ?>" placeholder="SBIN0001234" style="text-transform:uppercase;">
      </div>
      <div class="col-md-5 mb-2">
        <label class="form-label small fw-semibold">Branch</label>
        <input type="text" name="bank_branch" class="form-control form-control-sm" value="<?= e($editComp['bank_branch'] ?? '') ?>" placeholder="Karmanghat, Hyderabad">
      </div>
      <div class="col-md-3 mb-2">
        <label class="form-label small fw-semibold">Account Type</label>
        <select name="bank_account_type" class="form-select form-select-sm">
          <?php foreach (['Current Account','Savings Account','OD Account','CC Account'] as $t): ?>
          <option <?= ($editComp['bank_account_type'] ?? 'Current Account') === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <p class="form-section-hdr"><i class="fa fa-star me-1"></i>Branding & Preferences</p>
    <div class="mb-3 form-check">
      <input type="checkbox" class="form-check-input" name="is_default" id="is_default" value="1" <?= !empty($editComp['is_default']) ? 'checked' : '' ?>>
      <label class="form-check-label small fw-semibold" for="is_default">Set as Default Company (pre-selected on new documents)</label>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-sm text-white px-4" style="background:var(--red)"><i class="fa fa-save me-1"></i>Save Company</button>
      <a href="companies.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
    </div>
  </form>
  </div>
</div>
</div>

<!-- RIGHT: Upload assets for company -->
<div class="col-lg-5">
  <div class="card border-0 rounded-3 p-3" style="box-shadow:0 2px 12px rgba(0,0,0,.07)">
    <h6 class="fw-bold mb-3"><i class="fa fa-image me-2 text-danger"></i>Company Assets</h6>
    <p class="small text-muted">Upload logo, signature and stamp for <strong><?= $editId ? e($editComp['name'] ?? 'this company') : 'new company' ?></strong>. These appear on printed documents.</p>
    <?php if (!$editId): ?>
    <div class="alert alert-info py-2 small"><i class="fa fa-info-circle me-1"></i>Save the company first, then upload assets from the Edit page.</div>
    <?php else: ?>
    <?php foreach (['logo'=>['Logo','fa-image'],'signature'=>['Signature','fa-signature'],'stamp'=>['Company Stamp','fa-stamp']] as $ftype=>[$flabel,$ficon]): ?>
    <?php $val = $editComp[$ftype] ?? null; $fPath = $val ? preg_replace('/\?.*$/','',$val) : null; ?>
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="small fw-semibold"><i class="fa <?= $ficon ?> me-1 text-danger"></i><?= $flabel ?></label>
        <?php if ($fPath && file_exists(__DIR__ . '/' . $fPath)): ?>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="remove_img">
          <input type="hidden" name="id" value="<?= e($editId) ?>">
          <input type="hidden" name="ftype" value="<?= $ftype ?>">
          <button class="btn btn-xs btn-outline-danger py-0 px-1" style="font-size:.7rem;" onclick="return confirm('Remove this <?= $flabel ?>?')"><i class="fa fa-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
      <div class="upload-preview mb-1">
        <?php if ($fPath && file_exists(__DIR__ . '/' . $fPath)): ?>
        <img src="<?= e($val) ?>" alt="<?= e($flabel) ?>">
        <?php else: ?>
        <span class="text-muted small opacity-50"><i class="fa <?= $ficon ?> fa-lg d-block mb-1"></i>Not uploaded</span>
        <?php endif; ?>
      </div>
      <form method="POST" enctype="multipart/form-data" class="d-flex gap-1">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id"     value="<?= e($editId) ?>">
        <?php foreach (['signature','stamp','logo'] as $ff): ?>
        <input type="hidden" name="existing_<?= $ff ?>" value="<?= e($editComp[$ff] ?? '') ?>">
        <?php endforeach; ?>
        <!-- Copy all other fields as hidden so form is valid -->
        <?php foreach (['name','proprietor','address','phone','phone2','gstin','email','state','state_code','country','bank_name','bank_account','bank_ifsc','bank_branch','bank_account_type'] as $ff): ?>
        <input type="hidden" name="<?= $ff ?>" value="<?= e($editComp[$ff] ?? '') ?>">
        <?php endforeach; ?>
        <?php if (!empty($editComp['is_default'])): ?><input type="hidden" name="is_default" value="1"><?php endif; ?>
        <input type="file" name="<?= $ftype ?>" class="form-control form-control-sm" accept="image/*" required style="font-size:.78rem;">
        <button class="btn btn-sm btn-outline-primary px-2" style="white-space:nowrap;"><i class="fa fa-upload"></i></button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</div>

<?php else: ?>
<!-- ── Company List ──────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="fw-bold mb-0">All Companies</h5>
    <small class="text-muted">Switch between companies when creating documents</small>
  </div>
  <a href="companies.php?action=new" class="btn text-white" style="background:var(--red);">
    <i class="fa fa-plus me-1"></i>Add New Company
  </a>
</div>

<div class="row g-3">
<?php if (empty($companies)): ?>
<div class="col-12 text-center py-5 text-muted">
  <i class="fa fa-building fa-3x d-block mb-3 opacity-25"></i>
  No companies yet. <a href="companies.php?action=new">Add your first company</a>.
</div>
<?php else: ?>
<?php foreach ($companies as $comp): ?>
<div class="col-md-6 col-xl-4">
  <div class="card company-card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h6><?= e($comp['name']) ?></h6>
        <small style="opacity:.7;font-size:.7rem;"><?= e($comp['id']) ?></small>
      </div>
      <div class="d-flex align-items-center gap-1">
        <?php if (!empty($comp['is_default'])): ?>
        <span class="default-badge"><i class="fa fa-star me-1"></i>Default</span>
        <?php endif; ?>
        <?php if (!empty($comp['logo'])): ?>
        <img src="<?= e($comp['logo']) ?>" alt="logo" style="height:28px;border-radius:3px;background:#fff;padding:2px;">
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body p-3">
      <div class="row g-1 mb-2" style="font-size:.8rem;">
        <div class="col-12 d-flex gap-2 mb-1">
          <i class="fa fa-user text-muted" style="width:14px;margin-top:2px;"></i>
          <span><?= e($comp['proprietor'] ?? '—') ?></span>
        </div>
        <div class="col-12 d-flex gap-2 mb-1">
          <i class="fa fa-phone text-muted" style="width:14px;margin-top:2px;"></i>
          <span><?= e($comp['phone'] ?? '—') ?><?= $comp['phone2'] ? ' / '.e($comp['phone2']) : '' ?></span>
        </div>
        <div class="col-12 d-flex gap-2 mb-1">
          <i class="fa fa-id-card text-muted" style="width:14px;margin-top:2px;"></i>
          <span class="fw-semibold"><?= e($comp['gstin'] ?? '—') ?></span>
        </div>
        <div class="col-12 d-flex gap-2 mb-1">
          <i class="fa fa-map-marker-alt text-muted" style="width:14px;margin-top:2px;"></i>
          <span class="text-muted"><?= e(substr($comp['address'] ?? '', 0, 55)) ?><?= strlen($comp['address']??'')>55?'…':'' ?></span>
        </div>
      </div>

      <!-- Bank mini preview -->
      <?php if (!empty($comp['bank_name'])): ?>
      <div class="bank-box mb-2" style="font-size:.78rem;">
        <div class="d-flex gap-2">
          <i class="fa fa-landmark text-primary" style="margin-top:2px;width:14px;"></i>
          <div>
            <div class="fw-semibold"><?= e($comp['bank_name']) ?></div>
            <div class="text-muted">A/C: <?= e($comp['bank_account']) ?> &bull; IFSC: <?= e($comp['bank_ifsc']) ?></div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="text-muted mb-2" style="font-size:.75rem;"><i class="fa fa-exclamation-triangle me-1 text-warning"></i>No bank details yet</div>
      <?php endif; ?>

      <!-- Assets row -->
      <div class="d-flex gap-2 mb-2">
        <?php foreach (['signature'=>'Sig','stamp'=>'Stamp','logo'=>'Logo'] as $fk=>$fl): ?>
        <span class="badge <?= $comp[$fk] ? 'bg-success' : 'bg-light text-muted' ?>" style="font-size:.65rem;">
          <?= $comp[$fk] ? '✓' : '✗' ?> <?= $fl ?>
        </span>
        <?php endforeach; ?>
      </div>

      <!-- Actions -->
      <div class="d-flex gap-1 flex-wrap">
        <a href="create.php?company_id=<?= urlencode($comp['id']) ?>" class="btn btn-sm text-white flex-grow-1" style="background:var(--red);font-size:.78rem;">
          <i class="fa fa-file-plus me-1"></i>New Doc
        </a>
        <a href="companies.php?edit=<?= urlencode($comp['id']) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-pen"></i>
        </a>
        <?php if (empty($comp['is_default'])): ?>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="set_default">
          <input type="hidden" name="id" value="<?= e($comp['id']) ?>">
          <button class="btn btn-sm btn-outline-success" title="Set as default"><i class="fa fa-star"></i></button>
        </form>
        <?php endif; ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= e(addslashes($comp['name'])) ?>? All documents using this company will still show old data.');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= e($comp['id']) ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
