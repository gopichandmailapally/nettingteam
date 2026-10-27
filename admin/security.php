<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Load mail-config so MAIL_* constants are available for the info banner
$mailConfig = __DIR__ . '/../mail-config.php';
if (file_exists($mailConfig) && !defined('MAIL_RECIPIENT')) require_once $mailConfig;

$settings = getSettings();
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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
            redirect('security.php');
        }
    }

    if ($action === 'username') {
        $old = $_POST['old_password_u'] ?? '';
        $new = trim($_POST['new_username'] ?? '');
        if (!password_verify($old, $settings['auth']['password'] ?? '')) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $new)) {
            $errors[] = 'Username must be 3+ characters (letters, numbers, underscore only).';
        } else {
            $settings['auth']['username'] = $new;
            $_SESSION['gcm_user'] = $new;
            saveSettings($settings);
            flashSet('success', 'Username updated to: ' . $new);
            redirect('security.php');
        }
    }

    // Logout individual device
    if ($action === 'revoke_device' || $action === 'logout_device') {
        $token = $_POST['device_token'] ?? '';
        if ($token) {
            logoutDevice($token);
            flashSet('success', 'Device successfully logged out and removed from trusted list.');
        }
        redirect('security.php');
    }

    // Logout all other devices (keep this one active)
    if ($action === 'logout_other_devices') {
        logoutOtherDevices();
        flashSet('success', 'All other devices have been logged out. They will require OTP to log in again.');
        redirect('security.php');
    }

    // Logout ALL devices everywhere
    if ($action === 'logout_all_devices') {
        logoutAllDevices();
        redirect('login.php?all_logged_out=1');
    }
}

$authCfg = $settings['auth'] ?? [];
$devices = getAllConnectedDevices();
$pageTitle = 'Security & Devices';
require_once __DIR__ . '/includes/layout.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-3" style="border-left:4px solid #c0392b;font-size:.9rem;">
  <div style="font-weight:700;margin-bottom:.4rem;"><i class="fa fa-triangle-exclamation me-1"></i> Could not save changes:</div>
  <?php foreach ($errors as $err): ?>
  <div style="padding:.2rem 0;"><i class="fa fa-xmark-circle me-1"></i><?= e($err) ?></div>
  <?php endforeach; ?>
  <div style="margin-top:.5rem;font-size:.8rem;color:#666;">Make sure you enter your <strong>current password</strong> exactly as you use it to log in.</div>
</div>
<?php endif; ?>

<!-- Top Policy Status Cards -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card p-3 h-100" style="border-left:3px solid #10b981 !important;">
      <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:rgba(16,185,129,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#10b981;font-size:1.15rem;flex-shrink:0;">
          <i class="fa fa-calendar-check"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:var(--text);">30-Day Device Trust</div>
          <div style="font-size:.76rem;color:var(--muted);">No OTP required for 30 days once trusted on this browser.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card p-3 h-100" style="border-left:3px solid #f59e0b !important;">
      <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:rgba(245,158,11,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#f59e0b;font-size:1.15rem;flex-shrink:0;">
          <i class="fa fa-clock"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:var(--text);">2-Hour Inactivity Lock</div>
          <div style="font-size:.76rem;color:var(--muted);">Auto-logs out after 2 hours idle. Quick re-login (no OTP).</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card p-3 h-100" style="border-left:3px solid #2563eb !important;">
      <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:rgba(37,99,235,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#2563eb;font-size:1.15rem;flex-shrink:0;">
          <i class="fa fa-shield-halved"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:var(--text);">Remote Device Logout</div>
          <div style="font-size:.76rem;color:var(--muted);">Instantly terminate any device session or all devices.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Left Column: Credentials & 2FA -->
  <div class="col-lg-6">

    <!-- OTP Email info banner -->
    <div class="card p-3 mb-3" style="border-left:3px solid #2563eb !important;">
      <div class="d-flex align-items-start gap-3">
        <div style="width:38px;height:38px;background:rgba(37,99,235,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa fa-shield-halved" style="color:#2563eb;font-size:.95rem;"></i>
        </div>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:var(--text);margin-bottom:.2rem;">Two-Factor Authentication (Email OTP)</div>
          <div style="font-size:.78rem;color:var(--muted);line-height:1.6;">
            Logins from new or untrusted devices send a 6-digit OTP to <strong><?= defined('MAIL_RECIPIENT') ? e(MAIL_RECIPIENT) : 'gcmsafetynets@gmail.com' ?></strong>
            via SMTP account <strong><?= defined('MAIL_FROM_EMAIL') ? e(MAIL_FROM_EMAIL) : 'gcmsafetynets@gmail.com' ?></strong>.
            Trusted devices bypass OTP for 30 days.
          </div>
        </div>
      </div>
    </div>

    <!-- Change Username -->
    <div class="card p-3 mb-3">
      <div class="card-section-title"><span class="accent-dot"></span><i class="fa fa-at me-1"></i>Change Username</div>
      <p class="text-muted mb-2" style="font-size:.78rem;"><i class="fa fa-circle-info me-1"></i>Enter your new username and confirm with the password you use to log in.</p>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="username">
        <div class="row g-2 align-items-end">
          <div class="col-md-4 mb-2">
            <label class="form-label">Current Username</label>
            <input type="text" class="form-control form-control-sm" value="<?= e($authCfg['username'] ?? 'admin') ?>" disabled style="background:#f8f9fc;">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">New Username</label>
            <input type="text" name="new_username" class="form-control form-control-sm" placeholder="e.g. gcmadmin" pattern="[a-zA-Z0-9_]+" minlength="3" required>
            <div class="form-text">Letters, numbers, underscore only</div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">Confirm with <strong>Current</strong> Password</label>
            <div class="input-group input-group-sm">
              <input type="password" name="old_password_u" id="upwd_u" class="form-control form-control-sm" placeholder="Your current login password" required autocomplete="current-password">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="togglePwd('upwd_u',this)" tabindex="-1"><i class="fa fa-eye"></i></button>
            </div>
          </div>
        </div>
        <button class="btn btn-sm btn-success"><i class="fa fa-user-pen me-1"></i>Update Username</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="card p-3 mb-3">
      <div class="card-section-title"><span class="accent-dot"></span><i class="fa fa-key me-1"></i>Change Password</div>
      <p class="text-muted mb-2" style="font-size:.78rem;"><i class="fa fa-circle-info me-1"></i>Enter your current login password first, then set and confirm your new password.</p>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="password">
        <div class="row g-2">
          <div class="col-md-4 mb-2">
            <label class="form-label">Current Password</label>
            <div class="input-group input-group-sm">
              <input type="password" name="old_password" id="cpwd_old" class="form-control form-control-sm" placeholder="Your current password" required autocomplete="current-password">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="togglePwd('cpwd_old',this)" tabindex="-1"><i class="fa fa-eye"></i></button>
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">New Password <small class="text-muted">(min 8 chars)</small></label>
            <div class="input-group input-group-sm">
              <input type="password" name="new_password" id="newPwd" class="form-control form-control-sm" required minlength="8" autocomplete="new-password" oninput="checkPwdStrength(this.value)">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="togglePwd('newPwd',this)" tabindex="-1"><i class="fa fa-eye"></i></button>
            </div>
            <div style="height:3px;border-radius:2px;background:#e4e8f0;margin-top:.35rem;overflow:hidden;">
              <div id="pwdBar" style="height:100%;width:0%;border-radius:2px;transition:all .3s;"></div>
            </div>
            <div id="pwdLabel" class="form-text"></div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">Confirm New Password</label>
            <div class="input-group input-group-sm">
              <input type="password" name="conf_password" id="cpwd_conf" class="form-control form-control-sm" required autocomplete="new-password">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="togglePwd('cpwd_conf',this)" tabindex="-1"><i class="fa fa-eye"></i></button>
            </div>
          </div>
        </div>
        <button class="btn btn-sm btn-warning"><i class="fa fa-lock me-1"></i>Change Password</button>
      </form>
    </div>

  </div>

  <!-- Right Column: Connected & Trusted Devices -->
  <div class="col-lg-6">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="card-section-title mb-0">
          <span class="accent-dot"></span><i class="fa fa-laptop me-1"></i>Logged In &amp; Trusted Devices
          <span class="ms-1 px-2 py-0" style="background:rgba(37,99,235,.1);color:#2563eb;border-radius:20px;font-size:.72rem;font-weight:700;"><?= count($devices) ?></span>
        </div>

        <div class="d-flex gap-2">
          <?php if (count($devices) > 1): ?>
          <form method="POST" onsubmit="return confirm('Logout ALL other devices? They will be signed out immediately and will require OTP on their next login.')">
            <input type="hidden" name="action" value="logout_other_devices">
            <button type="submit" class="btn btn-sm btn-outline-warning" style="font-size:.75rem;padding:.3rem .65rem;">
              <i class="fa fa-arrow-right-from-bracket me-1"></i>Logout Other Devices
            </button>
          </form>
          <?php endif; ?>

          <?php if (!empty($devices)): ?>
          <form method="POST" onsubmit="return confirm('Logout ALL devices everywhere (including this browser)? You will be signed out and will need OTP on your next login.')">
            <input type="hidden" name="action" value="logout_all_devices">
            <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:.75rem;padding:.3rem .65rem;">
              <i class="fa fa-power-off me-1"></i>Logout All Devices
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($devices)): ?>
      <div class="text-center py-4">
        <i class="fa fa-laptop-code fa-2x mb-2 d-block" style="opacity:.2;color:var(--muted);"></i>
        <p class="text-muted mb-0" style="font-size:.8rem;">No active trusted devices recorded.</p>
        <p class="text-muted" style="font-size:.75rem;">When signing in, check "Trust this device for 30 days" to save your device.</p>
      </div>
      <?php else: ?>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($devices as $dev): ?>
        <?php
          $isCurr = !empty($dev['is_current']);
          $cardBg = $isCurr ? 'rgba(16,185,129,0.06)' : '#f8f9fc';
          $cardBorder = $isCurr ? '1.5px solid #10b981' : '1px solid var(--border)';
        ?>
        <div style="background:<?= $cardBg ?>;border:<?= $cardBorder ?>;border-radius:12px;padding:.85rem 1rem;display:flex;align-items:center;gap:.85rem;box-shadow:0 1px 4px rgba(0,0,0,0.03);">
          <div style="width:42px;height:42px;background:<?= $isCurr ? 'rgba(16,185,129,0.15)' : 'rgba(37,99,235,0.08)' ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="<?= strpos($dev['label'], 'iPhone') !== false || strpos($dev['label'], 'Android') !== false ? 'fa fa-mobile-screen' : 'fa fa-laptop' ?>" style="color:<?= $isCurr ? '#10b981' : '#2563eb' ?>;font-size:1.1rem;"></i>
          </div>

          <div style="flex:1;min-width:0;">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
              <span style="font-weight:700;font-size:.84rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= e($dev['label'] ?? 'Unknown Browser') ?>
              </span>
              <?php if ($isCurr): ?>
                <span class="badge" style="background:#10b981;color:#fff;font-size:.65rem;padding:.25rem .55rem;border-radius:20px;">
                  <i class="fa fa-circle-check me-1"></i>THIS DEVICE (ACTIVE NOW)
                </span>
              <?php endif; ?>
              <?php if (!empty($dev['is_trusted'])): ?>
                <span class="badge" style="background:rgba(37,99,235,0.1);color:#2563eb;font-size:.65rem;padding:.25rem .5rem;border-radius:20px;">
                  <i class="fa fa-shield-check me-1"></i>30-Day Trust (<?= (int)$dev['days_remaining'] ?>d left)
                </span>
              <?php endif; ?>
            </div>

            <div style="font-size:.74rem;color:var(--muted);display:flex;gap:.8rem;flex-wrap:wrap;">
              <span><i class="fa fa-location-dot me-1"></i>IP: <?= e($dev['ip'] ?? '') ?></span>
              <span><i class="fa fa-clock me-1"></i>Last active: <?= date('d M Y, h:i A', strtotime($dev['last_used'] ?? 'now')) ?></span>
            </div>
          </div>

          <?php if (!$isCurr): ?>
          <form method="POST" onsubmit="return confirm('Logout this device (<?= e($dev['label']) ?>)? Its session will end immediately and OTP will be required on its next login.')">
            <input type="hidden" name="action" value="logout_device">
            <input type="hidden" name="device_token" value="<?= e($dev['token'] ?? '') ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:.35rem .65rem;font-size:.74rem;" title="Logout this device immediately">
              <i class="fa fa-power-off me-1"></i>Logout Device
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="mt-3 p-3 rounded" style="background:rgba(37,99,235,.05);border:1px solid rgba(37,99,235,.15);font-size:.78rem;color:#1e40af;line-height:1.55;">
        <div style="font-weight:700;margin-bottom:.2rem;"><i class="fa fa-circle-info me-1"></i>How Security &amp; Device Trust Works:</div>
        <div>&bull; <strong>30-Day Trust:</strong> When checked during login, this device skips OTP for 30 days.</div>
        <div>&bull; <strong>2-Hour Auto-Logout:</strong> If you are inactive for 2 hours (120 min), you will be asked to enter your password again. On trusted devices, no OTP is needed to resume!</div>
        <div>&bull; <strong>Logout All Devices:</strong> Instantly kicks out all devices and requires OTP verification on next login.</div>
      </div>
    </div>
  </div>
</div>

<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  if (!inp) return;
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.innerHTML = show ? '<i class="fa fa-eye-slash"></i>' : '<i class="fa fa-eye"></i>';
}

function checkPwdStrength(val) {
  const bar = document.getElementById('pwdBar');
  const lbl = document.getElementById('pwdLabel');
  if (!bar) return;
  const checks = [val.length >= 8, /[A-Z]/.test(val), /[0-9]/.test(val), /[^A-Za-z0-9]/.test(val)];
  const score  = checks.filter(Boolean).length;
  const colors = ['','#ef4444','#f59e0b','#10b981','#2563eb'];
  const labels = ['','Weak','Fair','Good','Strong'];
  bar.style.width     = (score * 25) + '%';
  bar.style.background = colors[score] || '#e4e8f0';
  lbl.textContent      = val ? labels[score] + ' password' : '';
  lbl.style.color      = colors[score] || '';
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
