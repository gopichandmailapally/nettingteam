<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { redirect('index.php'); }

$token   = $_GET['token'] ?? ($_POST['token'] ?? '');
$error   = '';
$success = '';
$valid   = $token && verifyResetToken($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $new  = $_POST['new_password']  ?? '';
    $conf = $_POST['conf_password'] ?? '';
    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $conf) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = 'Password must contain at least one uppercase letter and one number.';
    } else {
        $settings = getSettings();
        $settings['auth']['password'] = password_hash($new, PASSWORD_DEFAULT);
        saveSettings($settings);
        consumeResetToken($token);
        $success = 'Password reset successfully! You can now sign in.';
        $valid   = false;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NettingTeam Admin — Set New Password</title>
<!-- Favicon -->
<link rel="icon" type="image/png" sizes="512x512" href="/images/logos/favicon.png">
<link rel="shortcut icon" type="image/png" href="/images/logos/favicon.png">
<link rel="apple-touch-icon" href="/images/logos/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--red:#e63946;--muted:rgba(200,210,255,0.55)}
html,body{min-height:100%;font-family:'Inter',sans-serif}
body{background:#06091a;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow-x:hidden;overflow-y:auto;padding:1.5rem 0.75rem}
@media(max-width:480px){body{padding:1rem 0.5rem}.wrap{padding:0.25rem !important}.card-head{padding:1.5rem 1.25rem 1rem !important}.card-body{padding:1.25rem 1rem !important}}
.orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0}
.orb-1{width:400px;height:400px;background:radial-gradient(circle,rgba(192,57,43,.3),transparent 70%);top:-10%;left:-10%}
.orb-2{width:300px;height:300px;background:radial-gradient(circle,rgba(99,60,180,.2),transparent 70%);bottom:-15%;right:-5%}
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.04;background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);background-size:60px 60px}
.wrap{position:relative;z-index:10;width:100%;max-width:460px;padding:20px}
.glass-card{background:rgba(255,255,255,0.06);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.12);border-radius:28px;box-shadow:0 24px 64px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.08);overflow:hidden;animation:slideUp .5s cubic-bezier(.16,1,.3,1) both}
@keyframes slideUp{from{opacity:0;transform:translateY(30px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.card-head{padding:2rem 2rem 1.5rem;text-align:center;border-bottom:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.02)}
.icon-ring{width:64px;height:64px;margin:0 auto 1.2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#c0392b,#e63946);box-shadow:0 0 0 8px rgba(192,57,43,.15),0 0 30px rgba(192,57,43,.3);animation:pulse 2.5s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.04)}}
.card-head h1{color:#fff;font-size:1.45rem;font-weight:800;margin-bottom:.25rem}
.card-head p{color:var(--muted);font-size:.82rem}
.card-body{padding:2rem}
.g-alert{display:flex;align-items:flex-start;gap:.6rem;padding:.8rem 1rem;border-radius:12px;margin-bottom:1.25rem;font-size:.84rem;font-weight:500;animation:fadeIn .3s ease;line-height:1.5}
.g-alert.err{background:rgba(230,57,70,.12);border:1px solid rgba(230,57,70,.3);color:#ff8a8a}
.g-alert.ok{background:rgba(60,200,120,.1);border:1px solid rgba(60,200,120,.3);color:#7cf5b0}
.g-alert.warn{background:rgba(255,180,0,.1);border:1px solid rgba(255,180,0,.3);color:#ffd060}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.field{margin-bottom:1.2rem}
.field label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:.5rem}
.input-wrap{display:flex;align-items:center;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;overflow:hidden;transition:border-color .2s,box-shadow .2s}
.input-wrap:focus-within{border-color:rgba(230,57,70,.6);box-shadow:0 0 0 3px rgba(230,57,70,.1)}
.input-wrap .icon{padding:0 .9rem;color:rgba(200,210,255,.4);font-size:.9rem;flex-shrink:0}
.input-wrap input{flex:1;background:transparent;border:none;outline:none;color:#fff;font-size:.92rem;font-family:'Inter',sans-serif;padding:.82rem .9rem .82rem 0}
.input-wrap input::placeholder{color:rgba(200,210,255,.3)}
.eye-btn{background:none;border:none;padding:0 .9rem;cursor:pointer;color:rgba(200,210,255,.4);font-size:.85rem;flex-shrink:0;transition:color .2s}
.eye-btn:hover{color:rgba(200,210,255,.8)}

/* Strength meter */
.strength-wrap{margin-top:.5rem}
.strength-bar{height:4px;border-radius:2px;background:rgba(255,255,255,.08);overflow:hidden;margin-bottom:.35rem}
.strength-fill{height:100%;border-radius:2px;transition:width .3s,background .3s}
.strength-text{font-size:.72rem;color:var(--muted)}

/* Requirements */
.reqs{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem}
.req{display:flex;align-items:center;gap:.3rem;font-size:.72rem;color:rgba(200,210,255,.4);transition:color .2s}
.req.met{color:#7cf5b0}
.req .fa{font-size:.65rem}

.btn-submit{width:100%;padding:.9rem;border:none;border-radius:12px;cursor:pointer;background:linear-gradient(135deg,#c0392b,#e63946);color:#fff;font-size:1rem;font-weight:700;font-family:'Inter',sans-serif;letter-spacing:.5px;box-shadow:0 4px 20px rgba(192,57,43,.35);transition:all .2s;margin-top:.5rem}
.btn-submit:hover{transform:translateY(-1px);filter:brightness(1.1)}
.btn-submit:disabled{opacity:.5;cursor:default;transform:none;filter:none}
.back-link{display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:1.2rem;color:rgba(200,210,255,.35);font-size:.78rem;text-decoration:none;transition:color .2s}
.back-link:hover{color:var(--red)}
.card-foot{padding:.9rem 2rem;text-align:center;border-top:1px solid rgba(255,255,255,.06);background:rgba(0,0,0,.15)}
.card-foot p{color:rgba(200,210,255,.25);font-size:.72rem}
</style>
<script src="/content-protect.js"></script>
</head>
<body>
<div class="grid-bg"></div>
<div class="orb orb-1"></div><div class="orb orb-2"></div>

<div class="wrap">
<div class="glass-card">
  <div class="card-head">
    <div class="icon-ring"><i class="fa fa-lock-open" style="color:#fff;font-size:1.7rem;"></i></div>
    <h1>Set New Password</h1>
    <p>Choose a strong password for your admin account</p>
  </div>

  <div class="card-body">
    <?php if (!$token || (!$valid && !$success)): ?>
    <div class="g-alert warn"><i class="fa fa-triangle-exclamation"></i> Invalid or expired reset link. Please request a new one.</div>
    <a href="forgot.php?type=password" style="display:block;text-align:center;padding:1rem;background:rgba(192,57,43,.12);border:1px solid rgba(192,57,43,.3);border-radius:12px;color:#ff8a8a;text-decoration:none;font-size:.88rem;font-weight:600;">
      <i class="fa fa-envelope me-1"></i>Request New Reset Link
    </a>
    <?php elseif ($success): ?>
    <div class="g-alert ok"><i class="fa fa-circle-check"></i> <?= e($success) ?></div>
    <div style="text-align:center;padding:1rem 0;">
      <i class="fa fa-check-circle fa-3x" style="color:#7cf5b0;display:block;margin-bottom:1rem;"></i>
      <p style="color:rgba(200,210,255,.6);font-size:.88rem;">Your password has been updated successfully.</p>
    </div>
    <a href="login.php" style="display:block;text-align:center;padding:.9rem;background:linear-gradient(135deg,#c0392b,#e63946);border-radius:12px;color:#fff;text-decoration:none;font-size:.95rem;font-weight:700;margin-top:1rem;">
      <i class="fa fa-right-to-bracket me-2"></i>Sign In Now
    </a>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="g-alert err"><i class="fa fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="resetForm">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label>New Password</label>
        <div class="input-wrap">
          <span class="icon"><i class="fa fa-lock"></i></span>
          <input type="password" name="new_password" id="newPwd" placeholder="Min. 8 characters" required oninput="checkStrength(this.value)">
          <button type="button" class="eye-btn" onclick="togglePwd('newPwd',this)"><i class="fa fa-eye"></i></button>
        </div>
        <div class="strength-wrap">
          <div class="strength-bar"><div class="strength-fill" id="strengthFill" style="width:0%;background:#e63946;"></div></div>
          <div class="strength-text" id="strengthText">Enter password above</div>
        </div>
        <div class="reqs" id="reqs">
          <div class="req" id="req-len"><i class="fa fa-circle"></i> 8+ chars</div>
          <div class="req" id="req-upper"><i class="fa fa-circle"></i> Uppercase</div>
          <div class="req" id="req-num"><i class="fa fa-circle"></i> Number</div>
          <div class="req" id="req-special"><i class="fa fa-circle"></i> Special (!@#)</div>
        </div>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <div class="input-wrap">
          <span class="icon"><i class="fa fa-lock"></i></span>
          <input type="password" name="conf_password" id="confPwd" placeholder="Repeat password" required>
          <button type="button" class="eye-btn" onclick="togglePwd('confPwd',this)"><i class="fa fa-eye"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-submit" id="submitBtn">
        <i class="fa fa-check me-2"></i>Set New Password
      </button>
    </form>
    <?php endif; ?>

    <a href="login.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Sign In</a>
  </div>

  <div class="card-foot">
    <p><i class="fa fa-shield-halved me-1"></i>NettingTeam Admin Panel</p>
  </div>
</div>
</div>

<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  const ico = btn.querySelector('i');
  inp.type = inp.type==='password' ? 'text' : 'password';
  ico.className = inp.type==='password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}
function checkStrength(val) {
  const fill = document.getElementById('strengthFill');
  const text = document.getElementById('strengthText');
  const reqs  = {len: val.length>=8, upper:/[A-Z]/.test(val), num:/[0-9]/.test(val), special:/[!@#$%^&*]/.test(val)};
  const met   = Object.values(reqs).filter(Boolean).length;
  document.getElementById('req-len').classList.toggle('met',reqs.len);
  document.getElementById('req-upper').classList.toggle('met',reqs.upper);
  document.getElementById('req-num').classList.toggle('met',reqs.num);
  document.getElementById('req-special').classList.toggle('met',reqs.special);
  const colors = ['#e63946','#e63946','#f4a261','#52b788','#40c9a2'];
  const labels = ['','Weak','Fair','Good','Strong'];
  fill.style.width   = (met * 25) + '%';
  fill.style.background = colors[met];
  text.textContent   = met ? labels[met] + ' password' : 'Enter password above';
  text.style.color   = colors[met] || 'rgba(200,210,255,.55)';
}
const form = document.getElementById('resetForm');
if (form) form.addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-circle-notch fa-spin me-2"></i>Updating…';
});
</script>
</body>
</html>
