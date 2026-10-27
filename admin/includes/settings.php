<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$settings = getSettings();
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'company') {
        $settings['company'] = [
            'name'             => trim($_POST['name']        ?? ''),
            'proprietor'       => trim($_POST['proprietor']  ?? ''),
            'address'          => trim($_POST['address']      ?? ''),
            'maps_url'         => trim($_POST['maps_url']     ?? ''),
            'phone'            => trim($_POST['phone']        ?? ''),
            'phone2'           => trim($_POST['phone2']       ?? ''),
            'gstin'            => strtoupper(trim($_POST['gstin'] ?? '')),
            'email'            => trim($_POST['email']        ?? ''),
            'state'            => trim($_POST['state']        ?? ''),
            'state_code'       => trim($_POST['state_code']   ?? ''),
            'bank_name'        => trim($_POST['bank_name']    ?? ''),
            'bank_account'     => trim($_POST['bank_account'] ?? ''),
            'bank_ifsc'        => strtoupper(trim($_POST['bank_ifsc'] ?? '')),
            'bank_branch'      => trim($_POST['bank_branch']  ?? ''),
            'bank_account_type'=> trim($_POST['bank_account_type'] ?? 'Current Account'),
        ];
        $settings['defaults']['cgst_rate'] = (float)($_POST['cgst_rate'] ?? 2.5);
        $settings['defaults']['sgst_rate'] = (float)($_POST['sgst_rate'] ?? 2.5);
        $settings['defaults']['igst_rate'] = (float)($_POST['igst_rate'] ?? 5);

        $terms = array_filter(array_map('trim', explode("\n", $_POST['default_terms'] ?? '')));
        $settings['defaults']['terms'] = array_values($terms);

        saveSettings($settings);
        flashSet('success', 'Company settings saved.');
        redirect('settings.php');
    }

    if ($action === 'password') {
        $old  = $_POST['old_password']  ?? '';
        $new  = $_POST['new_password']  ?? '';
        $conf = $_POST['conf_password'] ?? '';
        if (!password_verify($old, $settings['auth']['password'] ?? '')) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $conf) {
            $errors[] = 'Passwords do not match.';
        } else {
            $settings['auth']['password'] = password_hash($new, PASSWORD_DEFAULT);
            saveSettings($settings);
            flashSet('success', 'Password changed successfully.');
            redirect('settings.php#security');
        }
    }

    if ($action === 'username') {
        $old  = $_POST['old_password_u'] ?? '';
        $new  = trim($_POST['new_username'] ?? '');
        if (!password_verify($old, $settings['auth']['password'] ?? '')) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $new)) {
            $errors[] = 'Username must be 3+ characters (letters, numbers, underscore only).';
        } else {
            $settings['auth']['username'] = $new;
            $_SESSION['gcm_user'] = $new;
            saveSettings($settings);
            flashSet('success', 'Username updated to: ' . $new);
            redirect('settings.php#security');
        }
    }

    if ($action === 'security_email') {
        $email = trim($_POST['admin_email'] ?? '');
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } else {
            $settings['auth']['admin_email'] = $email;
            saveSettings($settings);
            flashSet('success', 'Admin email saved. OTP and reset links will be sent here.');
            redirect('settings.php#security');
        }
    }

    if ($action === 'revoke_device') {
        $token = $_POST['device_token'] ?? '';
        if ($token) revokeTrustedDevice($token);
        flashSet('success', 'Device removed from trusted list.');
        redirect('settings.php#security');
    }

    if ($action === 'logout_all_devices') {
        logoutAllDevices();
        flashSet('success', 'All trusted devices removed. OTP will be required on next login from every device.');
        redirect('settings.php#security');
    }

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
                $path = __DIR__ . '/../' . preg_replace('/\?.*$/', '', $old);
                if (file_exists($path)) unlink($path);
            }
            $settings[$type] = null;
            saveSettings($settings);
            flashSet('success', ucfirst($type) . ' removed.');
        }
        redirect('settings.php');
    }
}

$company  = $settings['company']  ?? [];
$defaults = $settings['defaults'] ?? [];
$authCfg  = $settings['auth']     ?? [];
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/layout.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <?php foreach ($errors as $err): ?><div><i class="fa fa-exclamation-circle me-1"></i><?= e($err) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Company Info -->
  <div class="col-lg-8">
    <div class="card border-0 rounded-3 p-3 mb-3" style="box-shadow:0 2px 12px rgba(0,0,0,.07)">
      <h6 class="fw-bold mb-3"><i class="fa fa-building me-2 text-danger"></i>Company Details</h6>
      <form method="POST">
        <input type="hidden" name="action" value="company">
        <div class="row g-2">
          <div class="col-md-6 mb-2">
            <label class="form-label small fw-semibold">Company Name *</label>
            <input type="text" name="name" class="form-control form-control-sm" value="<?= e($company['name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label small fw-semibold">Proprietor Name</label>
            <input type="text" name="proprietor" class="form-control form-control-sm" value="<?= e($company['proprietor'] ?? '') ?>">
          </div>
          <div class="col-12 mb-2">
            <label class="form-label small fw-semibold">Full Address</label>
            <textarea name="address" class="form-control form-control-sm" rows="2"><?= e($company['address'] ?? '') ?></textarea>
          </div>
          <div class="col-12 mb-2">
            <label class="form-label small fw-semibold">Google Maps URL <small class="text-muted">(shown on website &amp; contact page)</small></label>
            <input type="url" name="maps_url" class="form-control form-control-sm" value="<?= e($company['maps_url'] ?? '') ?>" placeholder="https://share.google/...">
            <div class="form-text text-muted" style="font-size:.75rem;"><i class="fa fa-circle-info me-1"></i>Open Google Maps → Share → Copy link. Paste here. This updates the address and directions button everywhere on the website.</div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">Primary Phone</label>
            <input type="text" name="phone" class="form-control form-control-sm" value="<?= e($company['phone'] ?? '') ?>">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">Secondary Phone</label>
            <input type="text" name="phone2" class="form-control form-control-sm" value="<?= e($company['phone2'] ?? '') ?>">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control form-control-sm" value="<?= e($company['email'] ?? '') ?>">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">GSTIN *</label>
            <input type="text" name="gstin" class="form-control form-control-sm" value="<?= e($company['gstin'] ?? '') ?>" maxlength="15" style="text-transform:uppercase;">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">State</label>
            <input type="text" name="state" class="form-control form-control-sm" value="<?= e($company['state'] ?? '') ?>">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">State Code</label>
            <input type="text" name="state_code" class="form-control form-control-sm" value="<?= e($company['state_code'] ?? '') ?>" maxlength="2">
          </div>
        </div>
        <hr>
        <h6 class="fw-bold mb-2"><i class="fa fa-landmark me-2 text-danger"></i>Bank Details</h6>
        <div class="row g-2 mb-3" style="background:#f0f7ff;border:1px solid #c7d9f5;border-radius:8px;padding:.75rem .5rem .25rem;">
          <div class="col-md-6 mb-2">
            <label class="form-label small fw-semibold">Bank Name</label>
            <input type="text" name="bank_name" class="form-control form-control-sm" value="<?= e($company['bank_name'] ?? '') ?>" placeholder="State Bank of India">
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label small fw-semibold">Account Number</label>
            <input type="text" name="bank_account" class="form-control form-control-sm" value="<?= e($company['bank_account'] ?? '') ?>" placeholder="0000000000000">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label small fw-semibold">IFSC Code</label>
            <input type="text" name="bank_ifsc" class="form-control form-control-sm" value="<?= e($company['bank_ifsc'] ?? '') ?>" placeholder="SBIN0001234" style="text-transform:uppercase;">
          </div>
          <div class="col-md-5 mb-2">
            <label class="form-label small fw-semibold">Branch</label>
            <input type="text" name="bank_branch" class="form-control form-control-sm" value="<?= e($company['bank_branch'] ?? '') ?>" placeholder="Karmanghat, Hyderabad">
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label small fw-semibold">Account Type</label>
            <select name="bank_account_type" class="form-select form-select-sm">
              <?php foreach (['Current Account','Savings Account','OD Account','CC Account'] as $t): ?>
              <option <?= ($company['bank_account_type'] ?? 'Current Account') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <hr>
        <h6 class="fw-bold mb-2">Default Tax Rates</h6>
        <div class="row g-2 mb-3">
          <div class="col-4">
            <label class="form-label small fw-semibold">CGST %</label>
            <input type="number" name="cgst_rate" class="form-control form-control-sm" value="<?= e($defaults['cgst_rate'] ?? 2.5) ?>" step="0.5" min="0">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">SGST %</label>
            <input type="number" name="sgst_rate" class="form-control form-control-sm" value="<?= e($defaults['sgst_rate'] ?? 2.5) ?>" step="0.5" min="0">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">IGST %</label>
            <input type="number" name="igst_rate" class="form-control form-control-sm" value="<?= e($defaults['igst_rate'] ?? 5) ?>" step="0.5" min="0">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Default Terms (one per line)</label>
          <textarea name="default_terms" class="form-control form-control-sm" rows="5"><?= e(implode("\n", $defaults['terms'] ?? [])) ?></textarea>
        </div>
        <button class="btn btn-sm text-white" style="background:var(--red)"><i class="fa fa-save me-1"></i>Save Company Settings</button>
      </form>
    </div>

    <!-- Security Panel -->
    <div class="card border-0 rounded-3 p-3" style="box-shadow:0 2px 12px rgba(0,0,0,.07);scroll-margin-top:80px;" id="security">
      <h6 class="fw-bold mb-1"><i class="fa fa-shield-halved me-2 text-danger"></i>Security &amp; Authentication</h6>
      <p class="text-muted mb-3" style="font-size:.78rem;">OTP email, trusted devices, username &amp; password management</p>

      <!-- Admin Email only -->
      <div style="background:#f8f9fc;border-radius:10px;padding:1rem;margin-bottom:1rem;">
        <div class="fw-bold mb-1" style="font-size:.82rem;"><i class="fa fa-envelope me-2 text-primary"></i>OTP Delivery Email</div>
        <p class="text-muted mb-2" style="font-size:.76rem;">OTP codes and password reset links are sent to this address. SMTP is handled automatically via <code>mail-config.php</code>.</p>
        <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
          <input type="hidden" name="action" value="security_email">
          <div style="flex:1;min-width:240px;">
            <label class="form-label small fw-semibold">Admin Email <span class="text-danger">*</span></label>
            <input type="email" name="admin_email" class="form-control form-control-sm" value="<?= e($authCfg['admin_email'] ?? '') ?>" placeholder="yourname@gmail.com" required>
          </div>
          <div>
            <button class="btn btn-sm btn-primary"><i class="fa fa-save me-1"></i>Save</button>
          </div>
        </form>
        <div class="mt-2 p-2 rounded" style="background:#e8f0fe;border:1px solid #c7d9f5;font-size:.75rem;color:#1a56db;">
          <i class="fa fa-circle-info me-1"></i>
          Using SMTP: <strong><?= defined('MAIL_USERNAME') ? e(MAIL_USERNAME) : 'gcmsafetynets@gmail.com' ?></strong> — configured in <code>mail-config.php</code>
        </div>
      </div>

      <!-- Change Username -->
      <div style="background:#f8f9fc;border-radius:10px;padding:1rem;margin-bottom:1rem;">
        <div class="fw-bold mb-2" style="font-size:.82rem;"><i class="fa fa-at me-2 text-success"></i>Change Username</div>
        <form method="POST">
          <input type="hidden" name="action" value="username">
          <div class="row g-2 align-items-end">
            <div class="col-md-4 mb-2">
              <label class="form-label small fw-semibold">Current Username</label>
              <input type="text" class="form-control form-control-sm bg-light" value="<?= e($authCfg['username'] ?? 'admin') ?>" disabled>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small fw-semibold">New Username</label>
              <input type="text" name="new_username" class="form-control form-control-sm" placeholder="e.g. gcmadmin" pattern="[a-zA-Z0-9_]+" minlength="3" required>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small fw-semibold">Confirm with Password</label>
              <input type="password" name="old_password_u" class="form-control form-control-sm" placeholder="Current password" required>
            </div>
          </div>
          <button class="btn btn-sm btn-success"><i class="fa fa-user-pen me-1"></i>Update Username</button>
        </form>
      </div>

      <!-- Change Password -->
      <div style="background:#f8f9fc;border-radius:10px;padding:1rem;margin-bottom:1rem;">
        <div class="fw-bold mb-2" style="font-size:.82rem;"><i class="fa fa-key me-2 text-warning"></i>Change Password</div>
        <form method="POST">
          <input type="hidden" name="action" value="password">
          <div class="row g-2">
            <div class="col-md-4 mb-2">
              <label class="form-label small fw-semibold">Current Password</label>
              <input type="password" name="old_password" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small fw-semibold">New Password (min 8 chars)</label>
              <input type="password" name="new_password" class="form-control form-control-sm" required minlength="8">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label small fw-semibold">Confirm New Password</label>
              <input type="password" name="conf_password" class="form-control form-control-sm" required>
            </div>
          </div>
          <button class="btn btn-sm btn-warning"><i class="fa fa-lock me-1"></i>Change Password</button>
        </form>
      </div>

      <!-- Trusted Devices -->
      <?php $devices = getAllTrustedDevices(); ?>
      <div style="background:#f8f9fc;border-radius:10px;padding:1rem;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-bold" style="font-size:.82rem;"><i class="fa fa-laptop me-2 text-danger"></i>Trusted Devices <span class="badge bg-secondary"><?= count($devices) ?></span></div>
          <?php if ($devices): ?>
          <form method="POST" onsubmit="return confirm('Remove ALL trusted devices? Every device will need OTP on next login.')">
            <input type="hidden" name="action" value="logout_all_devices">
            <button class="btn btn-xs btn-outline-danger py-1 px-2" style="font-size:.72rem;"><i class="fa fa-power-off me-1"></i>Logout All Devices</button>
          </form>
          <?php endif; ?>
        </div>
        <?php if (empty($devices)): ?>
        <p class="text-muted mb-0" style="font-size:.8rem;"><i class="fa fa-circle-info me-1"></i>No trusted devices. Devices are added when you choose "Trust this device" during OTP verification.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0" style="font-size:.8rem;">
            <thead><tr><th>Device</th><th>IP</th><th>Added</th><th>Last Used</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($devices as $dev): ?>
            <tr>
              <td><i class="fa fa-laptop me-1 text-muted"></i><?= e($dev['label'] ?? 'Unknown') ?></td>
              <td class="text-muted"><?= e($dev['ip'] ?? '') ?></td>
              <td class="text-muted"><?= date('d M y', strtotime($dev['created_at'] ?? 'now')) ?></td>
              <td class="text-muted"><?= date('d M y', strtotime($dev['last_used'] ?? 'now')) ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="revoke_device">
                  <input type="hidden" name="device_token" value="<?= e($dev['token']) ?>">
                  <button class="btn btn-xs btn-outline-danger py-0 px-1" style="font-size:.7rem;" title="Remove"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Uploads -->
  <div class="col-lg-4">
    <?php foreach (['signature'=>'Signature','stamp'=>'Company Stamp','logo'=>'Company Logo'] as $key=>$label): ?>
    <?php $val = $settings[$key] ?? null; $imgPath = $val ? preg_replace('/\?.*$/','',$val) : null; ?>
    <div class="card border-0 rounded-3 p-3 mb-3" style="box-shadow:0 2px 12px rgba(0,0,0,.07)">
      <h6 class="fw-bold mb-2"><i class="fa <?= $key==='logo'?'fa-image':($key==='stamp'?'fa-stamp':'fa-signature') ?> me-2 text-danger"></i><?= $label ?></h6>

      <?php if ($imgPath && file_exists(__DIR__ . '/../' . $imgPath)): ?>
      <div class="text-center mb-2 p-2 bg-light rounded">
        <img src="../<?= e($val) ?>" alt="<?= e($label) ?>" style="max-height:80px;max-width:160px;object-fit:contain;">
      </div>
      <form method="POST" class="mb-2">
        <input type="hidden" name="action" value="remove_upload">
        <input type="hidden" name="upload_type" value="<?= $key ?>">
        <button class="btn btn-sm btn-outline-danger w-100"><i class="fa fa-trash me-1"></i>Remove <?= $label ?></button>
      </form>
      <?php else: ?>
      <div class="text-center p-3 bg-light rounded mb-2" style="border:2px dashed #ddd;">
        <i class="fa <?= $key==='logo'?'fa-image':($key==='stamp'?'fa-stamp':'fa-signature') ?> fa-2x text-muted mb-2 d-block opacity-50"></i>
        <small class="text-muted">No <?= strtolower($label) ?> uploaded</small>
      </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="upload_type" value="<?= $key ?>">
        <div class="mb-2">
          <input type="file" name="upload_file" class="form-control form-control-sm" accept="image/*" required>
          <small class="text-muted">JPEG, PNG, WebP — max 2 MB</small>
        </div>
        <button class="btn btn-sm btn-outline-primary w-100">
          <i class="fa fa-upload me-1"></i>Upload <?= $label ?>
        </button>
      </form>

      <?php if ($key === 'signature'): ?>
      <div class="mt-2 p-2 bg-info bg-opacity-10 rounded" style="font-size:.78rem;color:#555;">
        <i class="fa fa-circle-info me-1"></i>Upload a PNG with transparent background for best results.
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
