<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn())    { redirect('index.php'); }
if (!isPendingOTP()) { redirect('login.php'); }

$error   = '';
$success = '';
$expiry  = getOTPExpiry();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify') {
        $code  = trim(implode('', $_POST['otp'] ?? []));
        $trust = !empty($_POST['trust_device']);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            $error = 'Please enter the 6-digit code.';
        } elseif (!verifyOTP($code)) {
            $error = 'Invalid or expired OTP. Request a new one.';
        } else {
            completeLogin($trust);
            redirect('index.php');
        }
    }

    if ($action === 'resend') {
        $code   = generateOTP();
        $result = sendOTPEmail($code);
        if ($result['ok']) {
            $success = 'A new OTP has been sent to your email.';
            $expiry  = 600;
        } else {
            $error = 'Could not send email: ' . ($result['msg'] ?? 'Unknown error');
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NettingTeam Admin — Verify OTP</title>
<!-- Favicon -->
<link rel="icon" type="image/png" sizes="512x512" href="/images/logos/favicon.png">
<link rel="shortcut icon" type="image/png" href="/images/logos/favicon.png">
<link rel="apple-touch-icon" href="/images/logos/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --red:   #e63946;
  --navy:  #0d1b3e;
  --glass: rgba(255,255,255,0.06);
  --border:rgba(255,255,255,0.12);
  --text:  #f0f4ff;
  --muted: rgba(200,210,255,0.55);
}
html,body { min-height:100%; font-family:'Inter',sans-serif; }
body {
  background:#06091a;
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  overflow-x:hidden; overflow-y:auto; position:relative;
  padding: 1.5rem 0.75rem;
}
@media (max-width: 480px) {
  body { padding: 1rem 0.5rem; }
  .wrap { padding: 0.25rem !important; }
  .card-head { padding: 1.5rem 1.25rem 1rem !important; }
  .card-body { padding: 1.25rem 1rem !important; }
}
.grid-bg { position:fixed; inset:0; background-image:linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px, transparent 1px); background-size:48px 48px; pointer-events:none; }
.orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; }
.orb-1 { width:400px; height:400px; background:radial-gradient(circle,#1a3a7c55,transparent); top:-60px; left:-60px; }
.orb-2 { width:350px; height:350px; background:radial-gradient(circle,#c0392b44,transparent); bottom:-60px; right:-60px; }
.orb-3 { width:250px; height:250px; background:radial-gradient(circle,#2e5fbc33,transparent); top:40%; right:15%; }

.wrap { position:relative; z-index:1; width:100%; max-width:440px; padding:1.25rem; }
.glass-card {
  background:rgba(13,27,62,.7);
  backdrop-filter:blur(28px); -webkit-backdrop-filter:blur(28px);
  border:1px solid var(--border); border-radius:24px;
  box-shadow:0 24px 64px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.15);
  overflow:hidden;
}

.card-head {
  padding:2.2rem 2rem 1.4rem; text-align:center;
  border-bottom:1px solid rgba(255,255,255,.07);
  background:linear-gradient(180deg,rgba(255,255,255,.04) 0%,transparent 100%);
}
.shield-icon {
  width:64px; height:64px; margin:0 auto 1rem;
  background:linear-gradient(135deg,#1a3a7c,#2e5fbc);
  border-radius:18px; display:flex; align-items:center; justify-content:center;
  box-shadow:0 0 0 8px rgba(46,95,188,.2), 0 0 28px rgba(46,95,188,.35);
}
.card-head h1 { color:#fff; font-size:1.45rem; font-weight:800; margin-bottom:.3rem; }
.card-head p  { color:var(--muted); font-size:.82rem; }

.card-body { padding:1.8rem 2rem 1.4rem; }
.g-alert { display:flex; align-items:center; gap:.6rem; padding:.75rem 1rem; border-radius:12px; margin-bottom:1.25rem; font-size:.84rem; font-weight:500; animation:fadeIn .3s ease; }
.g-alert.err  { background:rgba(230,57,70,.12); border:1px solid rgba(230,57,70,.3); color:#ff8a8a; }
.g-alert.ok   { background:rgba(60,200,120,.1);  border:1px solid rgba(60,200,120,.3); color:#7cf5b0; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

/* OTP boxes */
.otp-row { display:flex; gap:.6rem; justify-content:center; margin:1.5rem 0; }
.otp-box {
  width:52px; height:64px; background:rgba(255,255,255,.05);
  border:1.5px solid rgba(255,255,255,.12); border-radius:12px;
  color:#fff; font-size:1.6rem; font-weight:800; font-family:'Inter',sans-serif;
  text-align:center; outline:none;
  transition:border-color .2s, box-shadow .2s, background .2s;
}
.otp-box:focus {
  border-color:rgba(46,95,188,.8);
  background:rgba(46,95,188,.1);
  box-shadow:0 0 0 3px rgba(46,95,188,.2);
}
.otp-box.filled { border-color:rgba(60,200,120,.5); background:rgba(60,200,120,.08); }

/* Timer */
.timer-wrap { text-align:center; margin-bottom:1.25rem; }
.timer { font-size:.82rem; color:var(--muted); }
.timer span { color:#e63946; font-weight:700; font-variant-numeric:tabular-nums; }
.timer.expired span { color:#888; }

/* Trust checkbox */
.trust-row { display:flex; align-items:center; gap:.75rem; padding:.9rem 1rem; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08); border-radius:12px; margin-bottom:1.25rem; cursor:pointer; transition:background .2s; }
.trust-row:hover { background:rgba(255,255,255,.06); }
.trust-row input { display:none; }
.trust-check { width:20px; height:20px; background:rgba(255,255,255,.08); border:1.5px solid rgba(255,255,255,.2); border-radius:6px; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all .2s; }
.trust-check .fa-check { color:#fff; font-size:.65rem; opacity:0; transition:opacity .15s; }
.trust-row input:checked ~ .trust-check { background:#2e5fbc; border-color:#2e5fbc; box-shadow:0 0 10px rgba(46,95,188,.4); }
.trust-row input:checked ~ .trust-check .fa-check { opacity:1; }
.trust-text { flex:1; }
.trust-text strong { color:#fff; font-size:.86rem; display:block; }
.trust-text span { color:var(--muted); font-size:.75rem; }

.btn-verify { width:100%; padding:.9rem; border:none; border-radius:12px; cursor:pointer; background:linear-gradient(135deg,#1a3a7c,#2e5fbc); color:#fff; font-size:1rem; font-weight:700; font-family:'Inter',sans-serif; letter-spacing:.5px; box-shadow:0 4px 20px rgba(46,95,188,.35); transition:all .2s; }
.btn-verify:hover { transform:translateY(-1px); box-shadow:0 6px 28px rgba(46,95,188,.5); }
.btn-verify:disabled { opacity:.6; cursor:default; transform:none; }
.resend-row { text-align:center; margin-top:1.2rem; }
.resend-row button { background:none; border:none; cursor:pointer; color:rgba(200,210,255,.4); font-size:.8rem; font-family:'Inter',sans-serif; transition:color .2s; }
.resend-row button:hover { color:var(--red); }
.back-link { display:flex; align-items:center; justify-content:center; gap:.4rem; margin-top:1rem; color:rgba(200,210,255,.35); font-size:.78rem; text-decoration:none; transition:color .2s; }
.back-link:hover { color:var(--red); }
.card-foot { padding:.9rem 2rem; text-align:center; border-top:1px solid rgba(255,255,255,.06); background:rgba(0,0,0,.15); }
.card-foot p { color:rgba(200,210,255,.25); font-size:.72rem; }
</style>
<script src="/content-protect.js"></script>
</head>
<body>
<div class="grid-bg"></div>
<div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div>

<div class="wrap">
<div class="glass-card">
  <div class="card-head">
    <div class="shield-icon"><i class="fa fa-shield-halved" style="color:#fff;font-size:1.8rem;"></i></div>
    <h1>Verify Your Identity</h1>
    <p>Enter the 6-digit OTP sent to your admin email</p>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
    <div class="g-alert err"><i class="fa fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="g-alert ok"><i class="fa fa-circle-check"></i> <?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
      <input type="hidden" name="action" value="verify">

      <!-- OTP boxes -->
      <div class="otp-row" id="otpBoxes">
        <?php for ($i=0;$i<6;$i++): ?>
        <input type="text" name="otp[]" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" required>
        <?php endfor; ?>
      </div>

      <!-- Countdown timer -->
      <div class="timer-wrap">
        <div class="timer" id="timerDisplay">
          Code expires in <span id="countdown"><?= gmdate('i:s', min($expiry, 600)) ?></span>
        </div>
      </div>

      <!-- Trust device for 30 days -->
      <label class="trust-row" for="trust_chk">
        <input type="checkbox" name="trust_device" id="trust_chk" value="1" <?= (!empty($_SESSION['gcm_pending_trust']) || !isset($_SESSION['gcm_pending_trust'])) ? 'checked' : '' ?>>
        <div class="trust-check"><i class="fa fa-check"></i></div>
        <div class="trust-text">
          <strong><i class="fa fa-laptop me-1"></i>Trust this device for 30 days</strong>
          <span>No OTP needed for 30 days &bull; Auto-logout after 2 hours inactivity</span>
        </div>
      </label>

      <button type="submit" class="btn-verify" id="verifyBtn">
        <i class="fa fa-shield-check me-2"></i>Verify &amp; Sign In
      </button>
    </form>

    <div class="resend-row">
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="resend">
        <button type="submit" id="resendBtn"><i class="fa fa-rotate me-1"></i>Didn't receive it? Resend OTP</button>
      </form>
    </div>

    <a href="login.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Sign In</a>
  </div>

  <div class="card-foot">
    <p><i class="fa fa-clock me-1"></i>OTP is valid for 10 minutes &bull; Do not share with anyone</p>
  </div>
</div>
</div>

<script>
// Auto-advance OTP boxes
const boxes = document.querySelectorAll('.otp-box');
boxes.forEach((box, i) => {
  box.addEventListener('input', e => {
    const val = e.target.value.replace(/\D/g,'');
    e.target.value = val.slice(-1);
    if (val && i < boxes.length - 1) boxes[i+1].focus();
    updateFilled();
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
    if (e.key === 'ArrowRight' && i < boxes.length-1) boxes[i+1].focus();
    if (e.key === 'ArrowLeft'  && i > 0)              boxes[i-1].focus();
  });
  box.addEventListener('paste', e => {
    e.preventDefault();
    const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
    paste.split('').forEach((ch, idx) => { if (boxes[idx]) boxes[idx].value = ch; });
    updateFilled();
    if (paste.length >= 6) boxes[5].focus();
  });
});
function updateFilled() {
  boxes.forEach(b => b.classList.toggle('filled', b.value !== ''));
}

// Countdown timer
let secs = <?= min((int)$expiry, 600) ?>;
const timerEl  = document.getElementById('countdown');
const timerWrap = document.getElementById('timerDisplay');
function tick() {
  if (secs <= 0) {
    timerEl.textContent = '00:00';
    timerWrap.classList.add('expired');
    timerWrap.innerHTML = '<span style="color:#888;">OTP expired — please request a new one</span>';
    return;
  }
  const m = String(Math.floor(secs/60)).padStart(2,'0');
  const s = String(secs % 60).padStart(2,'0');
  timerEl.textContent = m + ':' + s;
  secs--;
  setTimeout(tick, 1000);
}
tick();

// Submit loading
document.getElementById('otpForm').addEventListener('submit', function() {
  const btn = document.getElementById('verifyBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-circle-notch fa-spin me-2"></i>Verifying…';
});

// Focus first box
if (boxes[0]) boxes[0].focus();
</script>
</body>
</html>
