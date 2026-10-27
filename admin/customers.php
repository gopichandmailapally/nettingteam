<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAccess('customers','view');

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    deleteCustomer($_POST['id'] ?? '');
    flashSet('success', 'Customer deleted.');
    redirect('customers.php');
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create', 'update'])) {
    $id = ($_POST['action'] === 'update') ? ($_POST['id'] ?? generateCustomerId()) : generateCustomerId();
    saveCustomer([
        'id'       => $id,
        'name'     => trim($_POST['name']     ?? ''),
        'address'  => trim($_POST['address']  ?? ''),
        'gstin'    => strtoupper(trim($_POST['gstin'] ?? '')),
        'phone'    => trim($_POST['phone']    ?? ''),
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'state'    => trim($_POST['state']    ?? ''),
        'country'  => trim($_POST['country']  ?? 'India'),
    ]);
    flashSet('success', 'Customer saved.');
    redirect('customers.php');
}

$customers = getAllCustomers();
$editId    = $_GET['edit'] ?? null;
$editCust  = $editId ? getCustomer($editId) : null;
$showForm  = isset($_GET['action']) && $_GET['action'] === 'new' || $editId;

$pageTitle = 'Customers';
require_once __DIR__ . '/includes/layout.php';
?>

<div class="row g-3">
  <?php if ($showForm): ?>
  <!-- Customer Form -->
  <div class="col-md-5">
    <div class="card border-0 rounded-3 p-3" style="box-shadow:0 2px 12px rgba(0,0,0,.07)">
      <h6 class="fw-bold mb-3"><?= $editId ? 'Edit Customer' : 'New Customer' ?></h6>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
        <?php if ($editId): ?><input type="hidden" name="id" value="<?= e($editId) ?>"><?php endif; ?>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Company / Customer Name *</label>
          <input type="text" name="name" class="form-control form-control-sm" value="<?= e($editCust['name'] ?? '') ?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Address</label>
          <textarea name="address" class="form-control form-control-sm" rows="2"><?= e($editCust['address'] ?? '') ?></textarea>
        </div>
        <div class="row g-2 mb-2">
          <div class="col">
            <label class="form-label small fw-semibold">GSTIN</label>
            <input type="text" name="gstin" class="form-control form-control-sm" value="<?= e($editCust['gstin'] ?? '') ?>" maxlength="15" style="text-transform:uppercase;" placeholder="22AAAAA0000A1Z5">
          </div>
                    <div class="col">
            <label class="form-label small fw-semibold">Country</label>
            <select name="country" class="form-select form-select-sm">
              <?php foreach (getCountriesList() as $cName => $cInfo): ?>
                <option value="<?= e($cName) ?>" <?= ($editCust['country'] ?? 'India') === $cName ? 'selected' : '' ?>><?= e($cName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col">
            <label class="form-label small fw-semibold">State</label>
            <input type="text" name="state" class="form-control form-control-sm" value="<?= e($editCust['state'] ?? '') ?>">
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold"><i class="fa fa-phone text-primary me-1"></i>Mobile Number</label>
            <input type="text" name="phone" id="cp_phone" class="form-control form-control-sm" value="<?= e($editCust['phone'] ?? '') ?>" placeholder="10-digit mobile" maxlength="10">
          </div>
          <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-semibold mb-0"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Number</label>
              <div class="form-check form-check-inline m-0">
                <input class="form-check-input" type="checkbox" id="cp_same_wa" onchange="if(this.checked){document.getElementById('cp_wa').value=document.getElementById('cp_phone').value;}">
                <label class="form-check-label" for="cp_same_wa" style="font-size:0.7rem;cursor:pointer;">Same</label>
              </div>
            </div>
            <input type="text" name="whatsapp" id="cp_wa" class="form-control form-control-sm" value="<?= e($editCust['whatsapp'] ?? ($editCust['phone'] ?? '')) ?>" placeholder="10-digit WhatsApp" maxlength="10">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Email</label>
          <input type="email" name="email" class="form-control form-control-sm" value="<?= e($editCust['email'] ?? '') ?>">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm text-white" style="background:var(--red)"><i class="fa fa-save me-1"></i>Save</button>
          <a href="customers.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= $showForm ? 'col-md-7' : 'col-12' ?>">
    <div class="card table-card">
      <div class="card-header bg-transparent border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">All Customers (<?= count($customers) ?>)</h6>
        <a href="customers.php?action=new" class="btn btn-sm text-white" style="background:var(--red);">
          <i class="fa fa-plus me-1"></i>New Customer
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Name</th><th>GSTIN</th><th>Phone</th><th>State</th><th class="text-center">Actions</th>
          </tr></thead>
          <tbody>
          <?php if (empty($customers)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">
              <i class="fa fa-users fa-2x d-block mb-2 opacity-25"></i>No customers yet.
            </td></tr>
          <?php else: ?>
            <?php foreach ($customers as $c): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($c['name']) ?></div>
                <small class="text-muted"><?= e(substr($c['address'] ?? '', 0, 50)) ?><?= strlen($c['address']??'')>50?'…':'' ?></small>
              </td>
              <td><?= e($c['gstin'] ?? '—') ?></td>
              <td>
                <?php if (!empty($c['phone'])): ?>
                  <div><i class="fa fa-phone text-primary" style="font-size:0.75rem;"></i> <?= e($c['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($c['whatsapp']) && $c['whatsapp'] !== ($c['phone'] ?? '')): ?>
                  <div><i class="fab fa-whatsapp text-success" style="font-size:0.75rem;"></i> <?= e($c['whatsapp']) ?></div>
                <?php endif; ?>
                <?php if (empty($c['phone']) && empty($c['whatsapp'])): ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= e($c['state'] ?? '') ?><?= (!empty($c['state']) && !empty($c['country'])) ? ', ' : '' ?><?= e($c['country'] ?? 'India') ?></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm">
                  <a href="create.php?bt_name=<?= urlencode($c['name']) ?>" class="btn btn-outline-primary" title="New doc for customer"><i class="fa fa-file-plus"></i></a>
                  <a href="customers.php?edit=<?= urlencode($c['id']) ?>" class="btn btn-outline-secondary" title="Edit"><i class="fa fa-pen"></i></a>
                  <button type="button" class="btn btn-outline-danger" title="Delete"
                    onclick="if(confirm('Delete <?= e(addslashes($c['name'])) ?>?')) { document.getElementById('df_<?= e($c['id']) ?>').submit(); }">
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
                <form id="df_<?= e($c['id']) ?>" method="POST" style="display:none;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
