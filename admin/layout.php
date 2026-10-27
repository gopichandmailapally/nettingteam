<?php
// $pageTitle must be set before including this file
$settings = getSettings();
$company  = $settings['company'];
$base     = adminBase();
$cur      = basename($_SERVER['PHP_SELF']);
function navActive($pages) {
    global $cur;
    $pages = (array)$pages;
    return in_array($cur, $pages) ? 'active' : '';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — NettingTeam</title>
<!-- Favicon -->
<link rel="icon" type="image/png" sizes="512x512" href="/images/logos/favicon.png">
<link rel="shortcut icon" type="image/png" href="/images/logos/favicon.png">
<link rel="apple-touch-icon" href="/images/logos/favicon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --sidebar-bg:#0d1b3e;
  --sidebar-w:260px;
  --red:#c0392b;
  --red-light:#e74c3c;
  --accent:#1a3a5c;
  --text-muted-sidebar:rgba(255,255,255,.5);
}
* { box-sizing:border-box; }
body { font-family:'Segoe UI',system-ui,sans-serif; background:#f0f2f7; margin:0; }

/* ── Sidebar ── */
.sidebar {
  position:fixed; top:0; left:0; height:100vh; width:var(--sidebar-w);
  background:var(--sidebar-bg); display:flex; flex-direction:column;
  z-index:1000; overflow-y:auto;
  box-shadow:4px 0 20px rgba(0,0,0,.3);
}
.sidebar-brand {
  padding:1.5rem 1.25rem 1rem;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.sidebar-brand h1 { color:var(--red); font-weight:900; font-size:1.15rem; letter-spacing:2px; margin:0; line-height:1.2; }
.sidebar-brand span { color:rgba(255,255,255,.5); font-size:.72rem; display:block; margin-top:.2rem; }
.sidebar-nav { padding:.75rem 0; flex:1; }
.nav-section { color:rgba(255,255,255,.3); font-size:.65rem; letter-spacing:1.5px; text-transform:uppercase; padding:.6rem 1.25rem .3rem; }
.sidebar a.nav-link {
  color:rgba(255,255,255,.7); padding:.6rem 1.25rem; border-radius:8px; margin:.1rem .75rem;
  display:flex; align-items:center; gap:.6rem; font-size:.88rem; transition:all .2s;
}
.sidebar a.nav-link:hover { color:#fff; background:rgba(255,255,255,.08); }
.sidebar a.nav-link.active { color:#fff; background:var(--red); box-shadow:0 4px 12px rgba(192,57,43,.35); }
.sidebar a.nav-link i { width:18px; text-align:center; opacity:.8; }
.sidebar-footer {
  padding:1rem 1.25rem;
  border-top:1px solid rgba(255,255,255,.08);
  font-size:.8rem; color:rgba(255,255,255,.4);
}

/* ── Main ── */
.main-content { margin-left:var(--sidebar-w); min-height:100vh; }
.topbar {
  background:#fff; padding:.75rem 1.5rem; display:flex; align-items:center;
  justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,.06); position:sticky; top:0; z-index:100;
}
.topbar-title { font-weight:700; font-size:1.15rem; color:#1a1a2e; }
.page-body { padding:1.5rem; }

/* ── Cards ── */
.stat-card { border:none; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.07); }
.stat-card .card-body { padding:1.25rem 1.5rem; }
.stat-icon { width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }

/* ── Tables ── */
.table-card { border:none; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; }
.table thead th { background:#f8f9fc; font-size:.78rem; text-transform:uppercase; letter-spacing:.8px; color:#666; border-bottom:2px solid #e9ecef; }
.table tbody tr:hover { background:#fafbfe; }

/* ── Badges ── */
.type-badge {
  font-size:.7rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase;
  padding:.25em .6em; border-radius:4px;
}
.type-est  { background:#fff3cd; color:#856404; }
.type-inv  { background:#cff4fc; color:#0c5460; }
.type-bill { background:#d1e7dd; color:#0f5132; }

/* ── Responsive & Overlay ── */
.sidebar-overlay {
  display:none; position:fixed; inset:0; background:rgba(6,12,31,.72);
  backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px);
  z-index:999; opacity:0; transition:opacity .25s ease;
  pointer-events:none;
}
.sidebar-overlay.active { display:block; opacity:1; pointer-events:auto; }

@media (max-width: 991px) {
  .sidebar { transform:translateX(-100%); transition:transform .28s cubic-bezier(.4,0,.2,1); z-index:1005; box-shadow:4px 0 32px rgba(0,0,0,.6); }
  .sidebar.open { transform:translateX(0); }
  .main-content { margin-left:0 !important; max-width:100% !important; overflow-x:hidden; }
  .page-body { padding:1.1rem 1rem; }
}
@media (max-width: 576px) {
  .page-body { padding:0.75rem 0.5rem; }
  .topbar { padding:0.5rem 0.65rem; gap:0.4rem; }
  .topbar-title { font-size:0.92rem; max-width:150px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
}

/* ── Scrollbar ── */
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:#ccc; border-radius:3px; }
</style>
<script src="/content-protect.js"></script>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <h1><i class="fa fa-building me-2"></i>GCM</h1>
    <span>Billing Admin Panel</span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="<?= $base ?>/index.php" class="nav-link <?= navActive('index.php') ?>">
      <i class="fa fa-gauge-high"></i> Dashboard
    </a>
    <div class="nav-section">Documents</div>
    <a href="<?= $base ?>/documents.php" class="nav-link <?= navActive(['documents.php']) ?>">
      <i class="fa fa-file-lines"></i> All Documents
    </a>
    <a href="<?= $base ?>/create.php" class="nav-link <?= navActive('create.php') ?>">
      <i class="fa fa-plus-circle"></i> New Document
    </a>
    <a href="<?= $base ?>/gstr1.php" class="nav-link <?= navActive('gstr1.php') ?>" style="<?= navActive('gstr1.php') ? '' : 'color:#38bdf8;' ?>">
      <i class="fa fa-file-invoice-dollar text-info"></i> GSTR-1 HSN Helper
    </a>
    <div class="nav-section">Records</div>
    <a href="<?= $base ?>/companies.php" class="nav-link <?= navActive('companies.php') ?>">
      <i class="fa fa-building"></i> Companies
    </a>
    <a href="<?= $base ?>/customers.php" class="nav-link <?= navActive('customers.php') ?>">
      <i class="fa fa-users"></i> Customers
    </a>
    <div class="nav-section">System</div>
    <a href="<?= $base ?>/settings.php" class="nav-link <?= navActive('settings.php') ?>">
      <i class="fa fa-gear"></i> Settings
    </a>
    <a href="<?= $base ?>/settings.php#security" class="nav-link">
      <i class="fa fa-shield-halved"></i> Security
    </a>
    <a href="<?= $base ?>/logout.php" class="nav-link" style="color:rgba(255,120,100,.7);">
      <i class="fa fa-right-from-bracket"></i> Logout
    </a>
  </nav>
  <div class="sidebar-footer">
    <div style="display:flex;align-items:center;gap:.5rem;">
      <div style="width:28px;height:28px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-user" style="color:#fff;font-size:.65rem;"></i>
      </div>
      <div style="min-width:0;">
        <div style="font-size:.75rem;font-weight:700;color:rgba(255,255,255,.8);line-height:1.1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($_SESSION['gcm_user'] ?? 'Admin') ?></div>
        <div style="font-size:.65rem;color:rgba(255,255,255,.3);">Administrator</div>
      </div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
<script>
function toggleSidebar(forceState) {
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('sidebarOverlay');
  if (!sb) return;
  var isOpen = (typeof forceState === 'boolean') ? forceState : !sb.classList.contains('open');
  if (isOpen) {
    sb.classList.add('open');
    if (ov) ov.classList.add('active');
    document.body.style.overflow = 'hidden';
  } else {
    sb.classList.remove('open');
    if (ov) ov.classList.remove('active');
    document.body.style.overflow = '';
  }
}
</script>

<!-- Main -->
<div class="main-content">
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button type="button" class="btn btn-sm btn-light d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation">
        <i class="fa fa-bars"></i>
      </button>
      <span class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="/<?= trim(str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__FILE__))), '/') ?>/" class="btn btn-sm btn-outline-secondary" target="_blank">
        <i class="fa fa-globe me-1"></i>View Site
      </a>
      <a href="<?= $base ?>/create.php" class="btn btn-sm text-white" style="background:var(--red);">
        <i class="fa fa-plus me-1"></i>New Doc
      </a>
    </div>
  </div>
  <div class="page-body">
<?php
// Show flash messages
$flashSuccess = flashGet('success');
$flashError   = flashGet('error');
$flashWarning = flashGet('warning');
if ($flashSuccess): ?>
  <div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle me-2"></i><?= e($flashSuccess) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif;
if ($flashError): ?>
  <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle me-2"></i><?= e($flashError) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif;
if ($flashWarning): ?>
  <div class="alert alert-warning alert-dismissible"><i class="fa fa-triangle-exclamation me-2"></i><?= e($flashWarning) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
