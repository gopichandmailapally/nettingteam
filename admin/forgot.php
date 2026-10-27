<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { redirect('index.php'); }

$type    = $_GET['type'] ?? 'password'; // password | username
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'forgot_password') {
        $settings = getSettings();
        $email    = $settings['auth']['admin_email'] ?? '';
        $input    = trim($_POST['email'] ?? '');
        // Don't reveal if email matches — just send if it does
        if ($email && strtolower($input) === strtolower($email)) {
            $token  = generateResetToken();
            $result = sendPasswordResetEmail($token);
            if (!$result['ok']) {
                $error = 'Could not send email: ' . ($result['msg'] ?? 'Unknown error');
            } else {
                $success = 'Password reset link sent! Check your email inbox (and spam folder).';
            }
        } else {
            // Same message to avoid email enumeration
            $success = 'If that email is registered, a reset link has been sent.';
        }
    }

    if ($action === 'forgot_username') {
        $settings = getSettings();
        $email    = $settings['auth']['admin_email'] ?? '';
        $input    = trim($_POST['email'] ?? '');
        if ($email && strtolower($input) === strtolower($email)) {
            $result = sendUsernameEmail();
            if (!$result['ok']) {
                $error = 'Could not send email: ' . ($result['msg'] ?? 'Unknown error');
            } else {
                $success = 'Your username has been sent to your email!';
            }
        } else {
            $success = 'If that email is registered, your username has been sent.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NettingTeam Admin — <?= $type==='username'?'Forgot Username':'Reset Password' ?></title>
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
.orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;animation:floatOrb 12s ease-in-out infinite}
.orb-1{width:450px;height:450px;background:radial-gradient(circle,rgba(192,57,43,.3),transparent 70%);top:-15%;left:-10%}
.orb-2{width:350px;height:350px;background:radial-gradient(circle,rgba(99,60,180,.2),transparent 70%);bottom:-20%;right:-5%;animation-delay:-5s}
@keyframes floatOrb{0%,100%{transform:translate(0,0)}50%{transform:translate(20px,-15px)}}
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.04;background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);background-size:60px 60px}
.wrap{position:relative;z-index:10;width:100%;max-width:460px;padding:20px}
.glass-card{background:rgba(255,255,255,0.06);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.12);border-radius:28px;box-shadow:0 24px 64px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.08);overflow:hidden;animation:slideUp .5s cubic-bezier(.16,1,.3,1) both}
@keyframes slideUp{from{opacity:0;transform:translateY(30px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.card-head{padding:2rem 2rem 1.5rem;text-align:center;border-bottom:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.02)}
.icon-ring{width:64px;height:64px;margin:0 auto 1.2rem;border-radius:50%;display:flex;align-items:center;justify-content:center;animation:pulse 2.5s ease-in-out infinite}
.icon-ring.key{background:linear-gradient(135deg,#c0392b,#e63946);box-shadow:0 0 0 8px rgba(192,57,43,.15),0 0 30px rgba(192,57,43,.3)}
.icon-ring.user{background:linear-gradient(135deg,#1a3a7c,#2e5fbc);box-shadow:0 0 0 8px rgba(46,95,188,.15),0 0 30px rgba(46,95,188,.3)}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.04)}}
.card-head h1{color:#fff;font-size:1.45rem;font-weight:800;letter-spacing:1px;margin-bottom:.25rem}
.card-head p{color:var(--muted);font-size:.82rem}
.card-body{padding:2rem}

/* Type toggle */
.type-toggle{display:flex;gap:.5rem;background:rgba(0,0,0,.3);border-radius:12px;padding:4px;margin-bottom:1.5rem}
.type-btn{flex:1;padding:.55rem;border:none;background:transparent;color:rgba(200,210,255,.5);font-size:.8rem;font-weight:600;font-family:'Inter',sans-serif;border-radius:9px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:.4rem}
.type-btn.active{background:rgba(255,255,255,.1);color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.3)}

.g-alert{display:flex;align-items:flex-start;gap:.6rem;padding:.8rem 1rem;border-radius:12px;margin-bottom:1.25rem;font-size:.84rem;font-weight:500;animation:fadeIn .3s ease;line-height:1.5}
.g-alert.err{background:rgba(230,57,70,.12);border:1px solid rgba(230,57,70,.3);color:#ff8a8a}
.g-alert.ok{background:rgba(60,200,120,.1);border:1px solid rgba(60,200,120,.3);color:#7cf5b0}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

.field{margin-bottom:1.25rem}
.field label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:.5rem}
.input-wrap{display:flex;align-items:center;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;overflow:hidden;transition:border-color .2s,box-shadow .2s}
.input-wrap:focus-within{border-color:rgba(230,57,70,.6);box-shadow:0 0 0 3px rgba(230,57,70,.1)}
.input-wrap .icon{padding:0 .9rem;color:rgba(200,210,255,.4);font-size:.9rem;flex-shrink:0}
.input-wrap input{flex:1;background:transparent;border:none;outline:none;color:#fff;font-size:.92rem;font-family:'Inter',sans-serif;padding:.82rem .9rem .82rem 0}
.input-wrap input::placeholder{color:rgba(200,210,255,.3)}
.btn-submit{width:100%;padding:.9rem;border:none;border-radius:12px;cursor:pointer;color:#fff;font-size:1rem;font-weight:700;font-family:'Inter',sans-serif;letter-spacing:.5px;transition:all .2s}
.btn-submit.red{background:linear-gradient(135deg,#c0392b,#e63946);box-shadow:0 4px 20px rgba(192,57,43,.35)}
.btn-submit.blue{background:linear-gradient(135deg,#1a3a7c,#2e5fbc);box-shadow:0 4px 20px rgba(46,95,188,.35)}
.btn-submit:hover{transform:translateY(-1px);filter:brightness(1.1)}
.back-link{display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:1.2rem;color:rgba(200,210,255,.35);font-size:.78rem;text-decoration:none;transition:color .2s}
.back-link:hover{color:var(--red)}
.card-foot{padding:.9rem 2rem;text-align:center;border-top:1px solid rgba(255,255,255,.06);background:rgba(0,0,0,.15)}
.card-foot p{color:rgba(200,210,255,.25);font-size:.72rem}
.hint{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:.8rem 1rem;margin-bottom:1.25rem;font-size:.8rem;color:rgba(200,210,255,.5);line-height:1.6}
</style>
<script src="/content-protect.js"></script>
</head>
<body>
<div class="grid-bg"></div>
<div class="orb orb-1"></div><div class="orb orb-2"></div>

<div class="wrap">
<div class="glass-card">
  <div class="card-head">
    <?php if ($type==='username'): ?>
    <div class="icon-ring user"><i class="fa fa-circle-question" style="color:#fff;font-size:1.7rem;"></i></div>
    <h1>Forgot Username?</h1>
    <p>We'll email your admin username to you</p>
    <?php else: ?>
    <div class="icon-ring key"><i class="fa fa-key" style="color:#fff;font-size:1.7rem;"></i></div>
    <h1>Reset Password</h1>
    <p>We'll send a secure reset link to your email</p>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <!-- Toggle -->
    <div class="type-toggle">
      <button type="button" class="type-btn <?= $type==='password'?'active':'' ?>" onclick="switchType('password')">
        <i class="fa fa-key"></i> Forgot Password
      </button>
      <button type="button" class="type-btn <?= $type==='username'?'active':'' ?>" onclick="switchType('username')">
        <i class="fa fa-user"></i> Forgot Username
      </button>
    </div>

    <?php if ($error): ?>
    <div class="g-alert err"><i class="fa fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="g-alert ok"><i class="fa fa-circle-check"></i> <?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="hint">
      <i class="fa fa-circle-info me-1"></i>
      Enter the email address configured in your admin panel security settings.
      <?php if ($type==='password'): ?>
      A secure reset link will be sent that expires in 1 hour.
      <?php else: ?>
      Your admin username will be sent immediately.
      <?php endif; ?>
    </div>

    <form method="POST" id="forgotForm">
      <input type="hidden" name="action" id="formAction" value="<?= $type==='username'?'forgot_username':'forgot_password' ?>">
      <div class="field">
        <label><i class="fa fa-envelope me-1"></i>Admin Email Address</label>
        <div class="input-wrap">
          <span class="icon"><i class="fa fa-envelope"></i></span>
          <input type="email" name="email" placeholder="your@email.com" required autofocus>
        </div>
      </div>
      <button type="submit" class="btn-submit <?= $type==='username'?'blue':'red' ?>" id="submitBtn">
        <?php if ($type==='username'): ?>
        <i class="fa fa-paper-plane me-2"></i>Send My Username
        <?php else: ?>
        <i class="fa fa-envelope-open-text me-2"></i>Send Reset Link
        <?php endif; ?>
      </button>
    </form>
    <?php else: ?>
    <div style="text-align:center;padding:1rem 0;">
      <i class="fa fa-envelope-circle-check fa-3x" style="color:#7cf5b0;margin-bottom:1rem;display:block;"></i>
      <p style="color:rgba(200,210,255,.6);font-size:.88rem;line-height:1.7;">Check your inbox and spam folder.<br>The email should arrive within a minute.</p>
    </div>
    <?php endif; ?>

    <a href="login.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Sign In</a>
  </div>

  <div class="card-foot">
    <p><i class="fa fa-shield-halved me-1"></i>NettingTeam Admin Panel &bull; Secure Recovery</p>
  </div>
</div>
</div>

<script>
function switchType(type) {
  window.location.href = 'forgot.php?type=' + type;
}
const form = document.getElementById('forgotForm');
if (form) {
  form.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-circle-notch fa-spin me-2"></i>Sending…';
  });
}
</script>
</body>
</html>
