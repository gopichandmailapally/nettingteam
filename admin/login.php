<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { redirect('index.php'); }

$error   = '';
$info    = '';
$success = '';

// Check query param notices
$timeoutParam  = $_GET['timeout'] ?? '';
$allLoggedOut  = !empty($_GET['all_logged_out']);
$revokedParam  = !empty($_GET['revoked']);

if ($timeoutParam === '2hr') {
    $info = 'You were logged out due to 2 hours of inactivity. Please enter your password to sign in again. (OTP not required on this trusted device).';
} elseif ($allLoggedOut) {
    $success = 'All devices logged out successfully. OTP will be required on your next login from any device.';
} elseif ($revokedParam) {
    $error = 'Your session was ended remotely from another device. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user  = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $trust = !empty($_POST['trust_device']);

    $result = doLogin($user, $pass, $trust);

    if ($result === 'ok') {
        if ($trust) {
            trustThisDevice();
        }
        redirect('index.php');
    } elseif ($result === 'otp_sent') {
        redirect('otp.php');
    } elseif (str_starts_with($result, 'otp_fail:')) {
        // OTP send failed — allow bypass with warning (email not configured)
        $msg = substr($result, 9);
        completeLogin($trust);
        flashSet('warning', 'OTP email failed (' . $msg . '). Logged in without OTP — configure email in Settings → Security.');
        redirect('index.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NettingTeam Admin — Sign In</title>
<link rel="icon" type="image/png" sizes="512x512" href="/images/logos/favicon.png">
<link rel="shortcut icon" type="image/png" href="/images/logos/favicon.png">
<link rel="apple-touch-icon" href="/images/logos/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --red:    #e63946;
  --navy:   #0d1b3e;
  --glass:  rgba(255,255,255,0.06);
  --border: rgba(255,255,255,0.12);
  --text:   #f0f4ff;
  --muted:  rgba(200,210,255,0.55);
}
html,body { min-height:100%; font-family:'Inter',sans-serif; }
body {
  background: #06091a;
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  overflow-x:hidden; overflow-y:auto; position:relative;
  padding: 1.5rem 0.75rem;
}
@media (max-width: 480px) {
  body { padding: 1rem 0.5rem; }
  .login-wrap { padding: 0.25rem !important; }
  .card-head { padding: 1.75rem 1.25rem 1.25rem !important; }
  .card-head h1 { font-size: 1.25rem !important; }
  .card-body { padding: 1.25rem 1rem !important; }
  .card-foot { padding: 0.75rem 1rem !important; }
  .trust-login-box { padding: 0.65rem 0.75rem !important; }
  .btn-signin { padding: 0.8rem !important; font-size: 0.92rem !important; }
}

/* ── Animated orbs ────────────────────────────────────────────────────── */
.orb {
  position:fixed; border-radius:50%; filter:blur(90px);
  pointer-events:none; z-index:0;
}
.orb-1 { width:450px; height:450px; background:radial-gradient(circle,#c0392b44,#e6394611); top:-100px; left:-100px; animation:drift1 14s ease-in-out infinite alternate; }
.orb-2 { width:400px; height:400px; background:radial-gradient(circle,#1a3a7c55,#0d1b3e22); bottom:-80px; right:-80px; animation:drift2 18s ease-in-out infinite alternate; }
.orb-3 { width:300px; height:300px; background:radial-gradient(circle,#c0392b22,transparent); top:40%; right:15%; animation:drift1 22s ease-in-out infinite alternate-reverse; }
.orb-4 { width:250px; height:250px; background:radial-gradient(circle,#2e5fbc33,transparent); bottom:20%; left:10%; animation:drift2 16s ease-in-out infinite alternate-reverse; }

@keyframes drift1 { from { transform:translate(0,0) scale(1); } to { transform:translate(40px,30px) scale(1.08); } }
@keyframes drift2 { from { transform:translate(0,0) scale(1); } to { transform:translate(-30px,-40px) scale(0.95); } }

/* ── Grid background ─────────────────────────────────────────────────── */
.grid-bg {
  position:fixed; inset:0;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size:48px 48px;
  pointer-events:none; z-index:0;
}

/* ── Login card wrap ─────────────────────────────────────────────────── */
.login-wrap {
  position:relative; z-index:1;
  width:100%; max-width:440px; padding:1.25rem;
}
.glass-card {
  background:rgba(13,27,62,.65);
  backdrop-filter:blur(28px);
  -webkit-backdrop-filter:blur(28px);
  border:1px solid var(--border);
  border-radius:24px;
  box-shadow:0 24px 64px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.06), inset 0 1px 0 rgba(255,255,255,.15);
  overflow:hidden;
}

/* ── Header ──────────────────────────────────────────────────────────── */
.card-head {
  padding:2.4rem 2rem 1.6rem; text-align:center;
  border-bottom:1px solid rgba(255,255,255,.07);
  background:linear-gradient(180deg,rgba(255,255,255,.04) 0%,transparent 100%);
}
.logo-ring {
  width:72px; height:72px; margin:0 auto 1.1rem;
  background:linear-gradient(135deg,#c0392b,#e63946);
  border-radius:20px;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 0 0 8px rgba(192,57,43,.18), 0 0 32px rgba(192,57,43,.4);
  animation:pulse 3.5s ease-in-out infinite;
  padding:10px;
}
.logo-ring img {
  width:100%; height:100%; object-fit:contain;
}
@keyframes pulse {
  0%,100% { box-shadow:0 0 0 8px rgba(192,57,43,.15),0 0 30px rgba(192,57,43,.35); }
  50%      { box-shadow:0 0 0 14px rgba(192,57,43,.08),0 0 45px rgba(192,57,43,.45); }
}
.card-head h1 { color:#fff; font-size:1.65rem; font-weight:900; letter-spacing:2px; margin-bottom:.25rem; }
.card-head p  { color:var(--muted); font-size:.82rem; font-weight:500; }

/* ── Body ─────────────────────────────────────────────────────────────── */
.card-body { padding:2rem 2rem 1.5rem; }

/* ── Alert ───────────────────────────────────────────────────────────── */
.g-alert {
  display:flex; align-items:flex-start; gap:.75rem;
  padding:.85rem 1rem; border-radius:12px; margin-bottom:1.25rem;
  font-size:.84rem; font-weight:500; line-height:1.45;
  animation: fadeIn .3s ease;
}
.g-alert.err  { background:rgba(230,57,70,.14); border:1px solid rgba(230,57,70,.35); color:#ff8a8a; }
.g-alert.info { background:rgba(99,180,255,.12); border:1px solid rgba(99,180,255,.35); color:#90c8ff; }
.g-alert.ok   { background:rgba(34,197,94,.12);  border:1px solid rgba(34,197,94,.35);  color:#86efac; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

/* ── Form fields ─────────────────────────────────────────────────────── */
.field { margin-bottom:1.25rem; }
.field label { display:block; font-size:.78rem; font-weight:600; color:var(--muted); letter-spacing:.5px; text-transform:uppercase; margin-bottom:.5rem; }
.input-wrap {
  position:relative; display:flex; align-items:center;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.1);
  border-radius:12px; overflow:hidden;
  transition:border-color .2s, box-shadow .2s;
}
.input-wrap:focus-within {
  border-color:rgba(230,57,70,.6);
  box-shadow:0 0 0 3px rgba(230,57,70,.1), 0 0 20px rgba(230,57,70,.08);
}
.input-wrap .icon {
  padding:0 .9rem; color:rgba(200,210,255,.4); font-size:.9rem; flex-shrink:0;
}
.input-wrap input {
  flex:1; background:transparent; border:none; outline:none;
  color:#fff; font-size:.92rem; font-family:'Inter',sans-serif;
  padding:.82rem .9rem .82rem 0; width:100%;
}
.input-wrap input::placeholder { color:rgba(200,210,255,.3); }
.eye-btn {
  background:none; border:none; padding:0 .9rem; cursor:pointer;
  color:rgba(200,210,255,.4); font-size:.85rem; flex-shrink:0;
  transition:color .2s;
}
.eye-btn:hover { color:rgba(200,210,255,.8); }

/* ── Trust 30-day row ────────────────────────────────────────────────── */
.trust-login-box {
  display:flex; align-items:center; gap:.75rem;
  padding:.8rem .95rem; border-radius:12px;
  background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08);
  margin-bottom:1.25rem; cursor:pointer; transition:background .2s;
}
.trust-login-box:hover { background:rgba(255,255,255,.06); }
.trust-login-box input {
  width:18px; height:18px; accent-color:#e63946; cursor:pointer;
}
.trust-login-box .txt { flex:1; font-size:.8rem; line-height:1.35; }
.trust-login-box .txt strong { color:#fff; display:block; font-size:.82rem; }
.trust-login-box .txt span { color:var(--muted); font-size:.73rem; }

/* ── Submit btn ──────────────────────────────────────────────────────── */
.btn-signin {
  width:100%; padding:.92rem; border:none; border-radius:12px; cursor:pointer;
  background:linear-gradient(135deg,#c0392b,#e63946);
  color:#fff; font-size:1rem; font-weight:700; font-family:'Inter',sans-serif;
  letter-spacing:.5px;
  box-shadow:0 4px 20px rgba(192,57,43,.35);
  transition:all .2s; position:relative; overflow:hidden; margin-top:.25rem;
}
.btn-signin::before {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.15),transparent);
  opacity:0; transition:opacity .2s;
}
.btn-signin:hover { transform:translateY(-1px); box-shadow:0 6px 28px rgba(192,57,43,.5); }
.btn-signin:hover::before { opacity:1; }
.btn-signin:active { transform:translateY(0); }

/* ── Links ───────────────────────────────────────────────────────────── */
.links-row {
  display:flex; justify-content:space-between; align-items:center;
  margin-top:1.2rem; flex-wrap:wrap; gap:.5rem;
}
.links-row a {
  color:rgba(200,210,255,.5); font-size:.78rem; text-decoration:none;
  transition:color .2s; display:flex; align-items:center; gap:.35rem;
}
.links-row a:hover { color:#e63946; }

/* ── Footer ──────────────────────────────────────────────────────────── */
.card-foot {
  padding:.9rem 2rem;
  text-align:center;
  border-top:1px solid rgba(255,255,255,.06);
  background:rgba(0,0,0,.15);
}
.card-foot p { color:rgba(200,210,255,.3); font-size:.73rem; letter-spacing:.3px; }
</style>
<script src="/content-protect.js"></script>
</head>
<body>

<!-- Background -->
<div class="grid-bg"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
<div class="orb orb-4"></div>

<div class="login-wrap">
  <div class="glass-card">

    <!-- Header -->
    <div class="card-head">
      <div class="logo-ring">
        <img src="/images/logos/logo.png" alt="NettingTeam">
      </div>
      <h1>NETTINGTEAM</h1>
      <p>Admin Billing &amp; Management Panel</p>
    </div>

    <!-- Body -->
    <div class="card-body">

      <?php if ($error): ?>
      <div class="g-alert err">
        <i class="fa fa-circle-exclamation mt-1"></i>
        <div><?= e($error) ?></div>
      </div>
      <?php endif; ?>

      <?php if ($info): ?>
      <div class="g-alert info">
        <i class="fa fa-clock mt-1"></i>
        <div><?= e($info) ?></div>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="g-alert ok">
        <i class="fa fa-circle-check mt-1"></i>
        <div><?= e($success) ?></div>
      </div>
      <?php endif; ?>

      <form method="POST" id="loginForm" autocomplete="off">
        <div class="field">
          <label for="username"><i class="fa fa-user me-1"></i>Username</label>
          <div class="input-wrap">
            <span class="icon"><i class="fa fa-at"></i></span>
            <input type="text" name="username" id="username" placeholder="Enter username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
          </div>
        </div>

        <div class="field">
          <label for="password"><i class="fa fa-lock me-1"></i>Password</label>
          <div class="input-wrap">
            <span class="icon"><i class="fa fa-lock"></i></span>
            <input type="password" name="password" id="password" placeholder="••••••••••" required>
            <button type="button" class="eye-btn" onclick="togglePwd(this)" tabindex="-1">
              <i class="fa fa-eye"></i>
            </button>
          </div>
        </div>

        <!-- 30-Day Device Trust Option -->
        <label class="trust-login-box" for="trust_login">
          <input type="checkbox" name="trust_device" id="trust_login" value="1" checked>
          <div class="txt">
            <strong><i class="fa fa-laptop me-1"></i>Trust this device for 30 days</strong>
            <span>No OTP required for 30 days &bull; Auto-logout after 2 hours inactivity</span>
          </div>
        </label>

        <button type="submit" class="btn-signin" id="signinBtn">
          <i class="fa fa-right-to-bracket me-2"></i>Sign In Securely
        </button>
      </form>

      <div class="links-row">
        <a href="forgot.php?type=password">
          <i class="fa fa-key"></i> Forgot Password?
        </a>
        <a href="forgot.php?type=username">
          <i class="fa fa-circle-question"></i> Forgot Username?
        </a>
      </div>
    </div>

    <!-- Footer -->
    <div class="card-foot">
      <p><i class="fa fa-shield-halved me-1"></i>Protected by 30-Day Device Trust &bull; 2-Hour Inactivity Auto-Logout</p>
    </div>
  </div>
</div>

<script>
function togglePwd(btn) {
  const inp = btn.closest('.input-wrap').querySelector('input');
  const ico = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fa fa-eye';
  }
}
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('signinBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-circle-notch fa-spin me-2"></i>Signing in…';
});
</script>
</body>
</html>
