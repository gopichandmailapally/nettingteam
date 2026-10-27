<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (!isAdmin()) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;text-align:center;padding:4rem;"><h2 style="color:#dc2626;">Admin Only</h2><p>Only the administrator can manage employees.</p><a href="index.php">← Dashboard</a></div>');
}

$employees = getEmployees();
$errors    = [];
$success   = '';

// ─── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id       = trim($_POST['emp_id'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $active   = isset($_POST['active']) ? true : false;

        // Permissions
        $perms = [];
        foreach (['documents','companies','customers','settings','seo_stats'] as $mod) {
            $v = $_POST['perm_' . $mod] ?? 'none';
            if (in_array($v, ['none','view','edit','full'])) $perms[$mod] = $v;
        }

        // Limits
        $limits = [
            'max_docs_per_day' => max(0, (int)($_POST['max_docs_per_day'] ?? 0)),
            'can_delete'       => isset($_POST['can_delete']),
            'can_export'       => isset($_POST['can_export']),
        ];

        if (!$name)     $errors[] = 'Name is required.';
        if (!$username) $errors[] = 'Username is required.';

        // Check username uniqueness
        foreach ($employees as $e) {
            if ($e['username'] === $username && $e['id'] !== $id) {
                $errors[] = 'Username already exists.';
                break;
            }
        }

        if (empty($errors)) {
            if ($action === 'create') {
                if (!$password) { $errors[] = 'Password is required for new employees.'; }
                else {
                    $emp = [
                        'id'          => 'emp_' . uniqid(),
                        'name'        => $name,
                        'username'    => $username,
                        'email'       => $email,
                        'password'    => password_hash($password, PASSWORD_DEFAULT),
                        'permissions' => $perms,
                        'limits'      => $limits,
                        'active'      => $active,
                        'created_at'  => date('c'),
                    ];
                    addEmployee($emp);
                    flashSet('success', "Employee '{$name}' created successfully.");
                    redirect('employees.php');
                }
            } else {
                $data = compact('name','username','email','active','perms','limits');
                $data['permissions'] = $perms;
                $data['limits']      = $limits;
                unset($data['perms']);
                if ($password) $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                updateEmployee($id, $data);
                flashSet('success', "Employee '{$name}' updated.");
                redirect('employees.php');
            }
        }
    }

    if ($action === 'delete') {
        $id = trim($_POST['emp_id'] ?? '');
        $emp = getEmployee($id);
        if ($emp) {
            deleteEmployee($id);
            flashSet('success', "Employee deleted.");
        }
        redirect('employees.php');
    }

    if ($action === 'toggle') {
        $id  = trim($_POST['emp_id'] ?? '');
        $emp = getEmployee($id);
        if ($emp) {
            updateEmployee($id, ['active' => !($emp['active'] ?? true)]);
            flashSet('success', "Employee " . (($emp['active'] ?? true) ? 'deactivated' : 'activated') . ".");
        }
        redirect('employees.php');
    }
}

// ─── Render ───────────────────────────────────────────────────────────────────
$pageTitle = 'Employees';
require_once __DIR__ . '/includes/layout.php';

$employees = getEmployees(); // reload after any changes

$modules = [
    'documents' => ['label'=>'Documents','icon'=>'fa-file-lines'],
    'companies' => ['label'=>'Companies','icon'=>'fa-building'],
    'customers' => ['label'=>'Customers','icon'=>'fa-users'],
    'settings'  => ['label'=>'Settings','icon'=>'fa-gear'],
    'seo_stats' => ['label'=>'SEO Stats','icon'=>'fa-chart-bar'],
];

function permBadge($p) {
    $map = ['none'=>['','None'],'view'=>['primary','View'],'edit'=>['warning','Edit'],'full'=>['success','Full']];
    [$cls,$lbl] = $map[$p] ?? ['','None'];
    return $cls ? "<span class='badge bg-{$cls}'>{$lbl}</span>" : "<span class='text-muted small'>None</span>";
}
?>
<style>
.emp-card { background:#fff; border-radius:14px; padding:1.5rem; box-shadow:0 2px 12px rgba(0,0,0,.06); border:1px solid #e8ecf0; }
.perm-table td, .perm-table th { vertical-align:middle; font-size:.85rem; }
.emp-avatar { width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#dc2626,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem;flex-shrink:0; }
.modal-xl { max-width:780px; }
.perm-select { font-size:.8rem; padding:.2rem .4rem; }
</style>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h4 mb-0 fw-800">Employees</h1>
    <p class="text-muted small mb-0">Manage employee accounts, access permissions and limits</p>
  </div>
  <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalCreate">
    <i class="fa fa-plus me-1"></i> Add Employee
  </button>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><i class="fa fa-circle-xmark me-1"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<!-- Employee List -->
<?php if (empty($employees)): ?>
<div class="emp-card text-center py-5">
  <i class="fa fa-user-tie fa-3x text-muted mb-3"></i>
  <h5 class="text-muted">No employees yet</h5>
  <p class="text-muted small">Click "Add Employee" to create the first employee account.</p>
</div>
<?php else: ?>
<div class="emp-card p-0">
  <table class="table table-hover mb-0">
    <thead>
      <tr>
        <th style="width:50px">#</th>
        <th>Employee</th>
        <th>Username</th>
        <th>Permissions</th>
        <th>Limits</th>
        <th>Status</th>
        <th style="width:140px">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($employees as $i => $emp): ?>
    <tr>
      <td class="text-muted small"><?= $i+1 ?></td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="emp-avatar"><?= strtoupper(substr($emp['name']??'E',0,1)) ?></div>
          <div>
            <div class="fw-600"><?= htmlspecialchars($emp['name']??'') ?></div>
            <div class="text-muted small"><?= htmlspecialchars($emp['email']??'') ?></div>
          </div>
        </div>
      </td>
      <td><code><?= htmlspecialchars($emp['username']??'') ?></code></td>
      <td>
        <?php $perms = $emp['permissions'] ?? []; ?>
        <div class="d-flex flex-wrap gap-1">
        <?php foreach ($modules as $key=>$m): ?>
          <?php $p=$perms[$key]??'none'; if($p!=='none'): ?>
          <span class="badge bg-light text-dark border" title="<?= $m['label'] ?>">
            <i class="fa <?= $m['icon'] ?> me-1 text-muted"></i><?= $m['label'] ?>: <?php echo permBadge($p); ?>
          </span>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty(array_filter($perms, fn($p)=>$p!=='none'))): ?>
          <span class="text-muted small">No access</span>
        <?php endif; ?>
        </div>
      </td>
      <td>
        <?php $lim=$emp['limits']??[]; ?>
        <div class="small">
          <?php if(($lim['max_docs_per_day']??0)>0): ?><div>Max docs/day: <?= $lim['max_docs_per_day'] ?></div><?php endif; ?>
          <?php if($lim['can_delete']??false): ?><div><i class="fa fa-check text-success"></i> Can delete</div><?php endif; ?>
          <?php if($lim['can_export']??false): ?><div><i class="fa fa-check text-success"></i> Can export</div><?php endif; ?>
        </div>
      </td>
      <td>
        <?php if($emp['active']??true): ?>
          <span class="badge bg-success">Active</span>
        <?php else: ?>
          <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
      </td>
      <td>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-primary" onclick="openEdit(<?= htmlspecialchars(json_encode($emp),ENT_QUOTES) ?>)" title="Edit">
            <i class="fa fa-pen"></i>
          </button>
          <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="emp_id" value="<?= htmlspecialchars($emp['id']??'') ?>">
            <button class="btn btn-sm btn-outline-<?= ($emp['active']??true)?'warning':'success' ?>" title="<?= ($emp['active']??true)?'Deactivate':'Activate' ?>">
              <i class="fa fa-<?= ($emp['active']??true)?'ban':'check' ?>"></i>
            </button>
          </form>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete employee <?= htmlspecialchars($emp['name']??'',ENT_QUOTES) ?>? This cannot be undone.')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="emp_id" value="<?= htmlspecialchars($emp['id']??'') ?>">
            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa fa-trash"></i></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ─── Create Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalCreate" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-user-plus me-2 text-danger"></i> Add New Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php include __DIR__ . '/includes/emp_form.php'; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fa fa-save me-1"></i> Create Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ─── Edit Modal ───────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST" id="editForm">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="emp_id" id="editId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-pen me-2 text-primary"></i> Edit Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="editBody">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const MODULES = <?= json_encode($modules) ?>;
const PERM_OPTS = {none:'None',view:'View Only',edit:'View & Edit',full:'Full Access'};

function buildForm(emp) {
    const perms = emp.permissions || {};
    const lim   = emp.limits || {};
    let permRows = '';
    for (const [key, m] of Object.entries(MODULES)) {
        const cur = perms[key] || 'none';
        let opts = Object.entries(PERM_OPTS).map(([v,l]) => `<option value="${v}"${cur===v?' selected':''}>${l}</option>`).join('');
        permRows += `<tr>
          <td><i class="fa ${m.icon} me-2 text-muted"></i>${m.label}</td>
          <td><select name="perm_${key}" class="form-select perm-select">${opts}</select></td>
        </tr>`;
    }
    return `
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label fw-600">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="${e(emp.name||'')}">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-600">Username <span class="text-danger">*</span></label>
        <input type="text" name="username" class="form-control" required value="${e(emp.username||'')}">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-600">Email</label>
        <input type="email" name="email" class="form-control" value="${e(emp.email||'')}">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-600">Password <small class="text-muted">(leave blank to keep existing)</small></label>
        <input type="password" name="password" class="form-control" autocomplete="new-password">
      </div>
      <div class="col-12">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="active" id="editActive" ${(emp.active!==false)?'checked':''}>
          <label class="form-check-label" for="editActive">Account Active</label>
        </div>
      </div>
    </div>
    <h6 class="fw-700 mb-2"><i class="fa fa-lock me-2 text-warning"></i>Module Permissions</h6>
    <div class="emp-card p-0 mb-3">
      <table class="table perm-table mb-0">
        <thead><tr><th>Module</th><th style="width:180px">Access Level</th></tr></thead>
        <tbody>${permRows}</tbody>
      </table>
    </div>
    <h6 class="fw-700 mb-2"><i class="fa fa-sliders me-2 text-info"></i>Limits & Controls</h6>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label small">Max Documents per Day</label>
        <input type="number" name="max_docs_per_day" class="form-control form-control-sm" value="${lim.max_docs_per_day||0}" min="0">
        <div class="form-text">0 = unlimited</div>
      </div>
      <div class="col-md-4">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" name="can_delete" id="editCanDel" ${lim.can_delete?'checked':''}>
          <label class="form-check-label small" for="editCanDel">Can Delete Records</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" name="can_export" id="editCanExp" ${lim.can_export?'checked':''}>
          <label class="form-check-label small" for="editCanExp">Can Export / Print</label>
        </div>
      </div>
    </div>`;
}
function e(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
function openEdit(emp) {
    document.getElementById('editId').value = emp.id;
    document.getElementById('editBody').innerHTML = buildForm(emp);
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
// Also use buildForm for the create modal
document.querySelector('#modalCreate .modal-body').innerHTML = buildForm({active:true});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
