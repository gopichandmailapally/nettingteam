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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ════════════════════════════════════════════════════════════════════
   NETTINGTEAM — ADMIN PANEL  |  Futuristic Glass Design System
   ════════════════════════════════════════════════════════════════════ */
:root {
  --red:         #c0392b;
  --red2:        #e63946;
  --red-glow:    rgba(192,57,43,.25);
  --navy:        #0d1b3e;
  --navy-deep:   #060c1f;
  --blue:        #2563eb;
  --sidebar-w:   260px;

  --bg:          #f0f3fb;
  --card:        #ffffff;
  --border:      #e4e8f0;
  --text:        #1e2235;
  --muted:       #6b7280;

  --shadow-sm:   0 1px 4px rgba(0,0,0,.06), 0 2px 12px rgba(0,0,0,.04);
  --shadow-md:   0 4px 16px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.04);
  --shadow-lg:   0 8px 32px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.06);
  --radius:      14px;
  --radius-sm:   8px;
}

*,*::before,*::after { box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
  font-family:'Inter',system-ui,sans-serif;
  background:var(--bg); margin:0; color:var(--text);
  font-size:.9rem; line-height:1.5;
}

/* ── Scrollbar ─────────────────────────────────────────────────── */
::-webkit-scrollbar { width:5px; height:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:rgba(0,0,0,.15); border-radius:10px; }
::-webkit-scrollbar-thumb:hover { background:rgba(0,0,0,.25); }

/* ══════════════════════════════════════════════════════════════════
   SIDEBAR
   ══════════════════════════════════════════════════════════════════ */
.sidebar {
  position:fixed; top:0; left:0; height:100vh; width:var(--sidebar-w);
  background: linear-gradient(175deg, #080e24 0%, #0d1b3e 55%, #0a1628 100%);
  display:flex; flex-direction:column; z-index:1000; overflow-y:auto;
  box-shadow: 4px 0 40px rgba(0,0,0,.5), inset -1px 0 0 rgba(255,255,255,.04);
}

/* Animated background mesh for sidebar */
.sidebar::before {
  content:''; position:absolute; inset:0; pointer-events:none; z-index:0;
  background-image:
    radial-gradient(circle at 20% 20%, rgba(192,57,43,.12) 0%, transparent 50%),
    radial-gradient(circle at 80% 70%, rgba(37,99,235,.08) 0%, transparent 50%),
    linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
  background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
}

.sidebar-brand {
  position:relative; z-index:1;
  padding:1.4rem 1.2rem 1.1rem;
  border-bottom:1px solid rgba(255,255,255,.06);
}
.brand-inner {
  display:flex; align-items:center; gap:.8rem;
}
.brand-logo {
  width:48px; height:48px; flex-shrink:0;
  border-radius:8px;
  display:flex; align-items:center; justify-content:center;
  overflow:hidden; background:rgba(255,255,255,.07);
}
.brand-logo img {
  width:100%; height:100%; object-fit:contain;
}
.brand-logo.icon-only {
  background:linear-gradient(135deg,#c0392b,#e63946);
  box-shadow:0 0 0 3px rgba(192,57,43,.2), 0 4px 16px rgba(192,57,43,.35);
  font-size:.95rem; color:#fff;
  animation:brandPulse 3s ease-in-out infinite;
}
@keyframes brandPulse {
  0%,100% { box-shadow:0 0 0 3px rgba(192,57,43,.2),0 4px 16px rgba(192,57,43,.35); }
  50%     { box-shadow:0 0 0 6px rgba(192,57,43,.1),0 4px 24px rgba(192,57,43,.5); }
}
.brand-text { min-width:0; }
.brand-text h1 {
  color:#fff; font-weight:900; font-size:1.05rem; letter-spacing:2.5px;
  margin:0; line-height:1.1;
}
.brand-text span {
  color:rgba(255,255,255,.35); font-size:.67rem; letter-spacing:.8px;
  text-transform:uppercase; display:block; margin-top:.15rem;
}

/* Nav sections */
.sidebar-nav { padding:.6rem 0; flex:1; position:relative; z-index:1; }
.nav-section {
  color:rgba(255,255,255,.22); font-size:.6rem; letter-spacing:2px;
  text-transform:uppercase; padding:.8rem 1.2rem .3rem;
  font-weight:700;
}
.sidebar a.nav-link {
  color:rgba(255,255,255,.6);
  padding:.62rem 1rem .62rem 1.1rem;
  border-radius:10px; margin:.08rem .7rem;
  display:flex; align-items:center; gap:.65rem;
  font-size:.84rem; font-weight:500;
  transition:all .18s ease;
  position:relative; overflow:hidden;
  text-decoration:none;
}
.sidebar a.nav-link .ni { /* nav icon */
  width:30px; height:30px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  border-radius:8px; font-size:.82rem;
  background:rgba(255,255,255,.05);
  transition:all .18s ease;
}
.sidebar a.nav-link:hover {
  color:#fff;
  background:rgba(255,255,255,.07);
}
.sidebar a.nav-link:hover .ni {
  background:rgba(255,255,255,.12);
}
.sidebar a.nav-link.active {
  color:#fff;
  background:linear-gradient(90deg, rgba(192,57,43,.3) 0%, rgba(192,57,43,.1) 100%);
  box-shadow:inset 2px 0 0 #e63946;
}
.sidebar a.nav-link.active .ni {
  background:linear-gradient(135deg,#c0392b,#e63946);
  box-shadow:0 0 12px rgba(192,57,43,.5);
  color:#fff;
}
.sidebar a.nav-link.logout-link { color:rgba(255,100,80,.6); }
.sidebar a.nav-link.logout-link:hover { color:rgba(255,120,100,1); background:rgba(255,70,50,.08); }
.sidebar a.nav-link.logout-link .ni { background:rgba(255,70,50,.1); }

/* Sidebar footer — user card */
.sidebar-footer {
  position:relative; z-index:1;
  padding:.9rem 1rem;
  border-top:1px solid rgba(255,255,255,.06);
  background:rgba(0,0,0,.2);
}
.user-card {
  display:flex; align-items:center; gap:.65rem;
  padding:.55rem .65rem; border-radius:10px;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.06);
}
.user-av {
  width:32px; height:32px; flex-shrink:0;
  background:linear-gradient(135deg,#c0392b,#e63946);
  border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:.72rem; font-weight:800; color:#fff;
  box-shadow:0 0 0 2px rgba(192,57,43,.3);
}
.user-info { min-width:0; }
.user-info .uname {
  font-size:.78rem; font-weight:700; color:rgba(255,255,255,.9);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;
}
.user-info .urole {
  font-size:.64rem; color:rgba(255,255,255,.3); display:block; margin-top:.05rem;
}
.online-dot {
  width:7px; height:7px; background:#27ae60; border-radius:50%; flex-shrink:0;
  box-shadow:0 0 6px #27ae60; animation:blink 2s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ══════════════════════════════════════════════════════════════════
   TOPBAR
   ══════════════════════════════════════════════════════════════════ */
.main-content { margin-left:var(--sidebar-w); min-height:100vh; }
.topbar {
  background:rgba(255,255,255,.92);
  backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
  padding:.7rem 1.5rem; display:flex; align-items:center;
  justify-content:space-between;
  box-shadow:0 1px 0 rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
  position:sticky; top:0; z-index:100;
  border-bottom:1px solid rgba(228,232,240,.8);
}
.topbar-left { display:flex; align-items:center; gap:.8rem; }
.topbar-title {
  font-weight:800; font-size:1.05rem; color:var(--text);
  letter-spacing:-.2px;
}
.page-tag {
  font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.8px;
  padding:.25em .7em; border-radius:20px;
  background:linear-gradient(135deg,rgba(192,57,43,.1),rgba(230,57,70,.05));
  color:var(--red); border:1px solid rgba(192,57,43,.15);
}
.topbar-right { display:flex; align-items:center; gap:.5rem; }

/* Topbar buttons */
.tbtn {
  display:inline-flex; align-items:center; gap:.4rem;
  padding:.45rem .9rem; border-radius:9px; font-size:.8rem; font-weight:600;
  border:none; cursor:pointer; transition:all .15s; text-decoration:none;
  font-family:'Inter',sans-serif; white-space:nowrap;
}
.tbtn-ghost {
  background:transparent; color:var(--muted);
  border:1px solid var(--border);
}
.tbtn-ghost:hover { background:var(--bg); color:var(--text); }
.tbtn-red {
  background:linear-gradient(135deg,#c0392b,#e63946);
  color:#fff;
  box-shadow:0 3px 12px rgba(192,57,43,.35);
}
.tbtn-red:hover { transform:translateY(-1px); box-shadow:0 5px 18px rgba(192,57,43,.45); color:#fff; }
.tbtn-red:active { transform:translateY(0); }
.tbtn i { font-size:.75rem; }

/* Hamburger */
.ham-btn {
  display:none; background:none; border:1px solid var(--border);
  border-radius:8px; padding:.4rem .55rem; cursor:pointer; color:var(--muted);
  transition:all .15s;
}
.ham-btn:hover { background:var(--bg); color:var(--text); }

/* ══════════════════════════════════════════════════════════════════
   PAGE BODY
   ══════════════════════════════════════════════════════════════════ */
.page-body { padding:1.4rem 1.5rem; }

/* ── Flash alerts ────────────────────────────────────────────────── */
.alert {
  border:none; border-radius:var(--radius-sm);
  font-size:.85rem; font-weight:500;
  display:flex; align-items:center; gap:.5rem;
  padding:.75rem 1rem;
  animation:alertIn .3s ease;
}
.alert-success { background:#f0fdf4; color:#166534; border-left:3px solid #22c55e; }
.alert-danger  { background:#fef2f2; color:#991b1b; border-left:3px solid #ef4444; }
.alert-warning { background:#fffbeb; color:#92400e; border-left:3px solid #f59e0b; }
@keyframes alertIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ══════════════════════════════════════════════════════════════════
   CARDS
   ══════════════════════════════════════════════════════════════════ */
.card {
  border:1px solid var(--border) !important;
  border-radius:var(--radius) !important;
  box-shadow:var(--shadow-sm) !important;
  background:var(--card);
  transition:box-shadow .2s;
}
.card:hover { box-shadow:var(--shadow-md) !important; }

/* Stat cards */
.stat-card {
  border:none !important;
  border-radius:var(--radius) !important;
  overflow:hidden;
  box-shadow:var(--shadow-sm) !important;
  background:var(--card);
  transition:all .2s;
  position:relative;
}
.stat-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.stat-card.sc-red::before   { background:linear-gradient(90deg,#c0392b,#e63946); }
.stat-card.sc-green::before { background:linear-gradient(90deg,#059669,#10b981); }
.stat-card.sc-blue::before  { background:linear-gradient(90deg,#2563eb,#60a5fa); }
.stat-card.sc-orange::before{ background:linear-gradient(90deg,#d97706,#fbbf24); }
.stat-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-md) !important; }
.stat-card .card-body { padding:1.2rem 1.35rem; }
.stat-icon {
  width:48px; height:48px; border-radius:12px;
  display:flex; align-items:center; justify-content:center; font-size:1.25rem;
}
.stat-icon.si-red    { background:rgba(192,57,43,.1);  color:#c0392b; }
.stat-icon.si-green  { background:rgba(5,150,105,.1);  color:#059669; }
.stat-icon.si-blue   { background:rgba(37,99,235,.1);  color:#2563eb; }
.stat-icon.si-orange { background:rgba(217,119,6,.1);  color:#d97706; }
.stat-label  { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.6px; color:var(--muted); }
.stat-value  { font-size:1.55rem; font-weight:800; color:var(--text); line-height:1.1; margin:.15rem 0 .05rem; }
.stat-sub    { font-size:.72rem; color:var(--muted); }

/* ══════════════════════════════════════════════════════════════════
   TABLES
   ══════════════════════════════════════════════════════════════════ */
.table-card {
  border:1px solid var(--border) !important;
  border-radius:var(--radius) !important;
  box-shadow:var(--shadow-sm) !important;
  overflow:hidden; background:var(--card);
}
.table {
  margin:0; font-size:.84rem; color:var(--text);
}
.table thead tr {
  background:linear-gradient(135deg,#f8f9fc,#f0f3fb);
}
.table thead th {
  font-size:.68rem; text-transform:uppercase; letter-spacing:1px;
  font-weight:700; color:var(--muted);
  border-bottom:1px solid var(--border) !important;
  padding:.7rem 1rem; white-space:nowrap;
}
.table tbody td {
  padding:.65rem 1rem; border-bottom:1px solid rgba(228,232,240,.5);
  vertical-align:middle;
}
.table tbody tr:last-child td { border-bottom:none; }
.table tbody tr { transition:background .12s; }
.table tbody tr:hover { background:rgba(192,57,43,.03); }

/* ══════════════════════════════════════════════════════════════════
   BADGES
   ══════════════════════════════════════════════════════════════════ */
.type-badge {
  font-size:.65rem; font-weight:700; letter-spacing:.6px; text-transform:uppercase;
  padding:.3em .75em; border-radius:20px; display:inline-block; white-space:nowrap;
}
.type-est  { background:rgba(245,158,11,.12); color:#b45309;  border:1px solid rgba(245,158,11,.25); }
.type-inv  { background:rgba(37,99,235,.1);   color:#1d4ed8;  border:1px solid rgba(37,99,235,.2); }
.type-bill { background:rgba(5,150,105,.1);   color:#065f46;  border:1px solid rgba(5,150,105,.2); }
.type-pi   { background:rgba(147,51,234,.12); color:#7e22ce;  border:1px solid rgba(147,51,234,.25); }
.type-gst  { background:rgba(139,92,246,.1);  color:#5b21b6;  border:1px solid rgba(139,92,246,.2); }

.status-badge {
  font-size:.65rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase;
  padding:.28em .7em; border-radius:20px; display:inline-block;
}
.s-draft    { background:rgba(107,114,128,.1); color:#374151; border:1px solid rgba(107,114,128,.2); }
.s-final    { background:rgba(5,150,105,.1);   color:#065f46; border:1px solid rgba(5,150,105,.2); }
.s-paid     { background:rgba(37,99,235,.1);   color:#1d4ed8; border:1px solid rgba(37,99,235,.2); }
.s-cancelled{ background:rgba(239,68,68,.1);   color:#991b1b; border:1px solid rgba(239,68,68,.2); }

/* ══════════════════════════════════════════════════════════════════
   FORMS (Global override for cleaner look)
   ══════════════════════════════════════════════════════════════════ */
.form-control, .form-select {
  border:1.5px solid #e4e8f0; border-radius:9px; font-size:.84rem;
  font-family:'Inter',sans-serif; color:var(--text);
  background:#fff; padding:.5rem .75rem;
  transition:border-color .15s, box-shadow .15s;
}
.form-control:focus, .form-select:focus {
  border-color:rgba(192,57,43,.5);
  box-shadow:0 0 0 3px rgba(192,57,43,.1);
  outline:none;
}
.form-control-sm, .form-select-sm {
  padding:.38rem .65rem; border-radius:7px; font-size:.8rem;
}
.form-label { font-size:.78rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.input-group-text {
  background:#f8f9fc; border:1.5px solid #e4e8f0;
  color:var(--muted); font-size:.84rem; border-radius:9px;
}
textarea.form-control { resize:vertical; }

/* ══════════════════════════════════════════════════════════════════
   BUTTONS
   ══════════════════════════════════════════════════════════════════ */
.btn {
  font-family:'Inter',sans-serif; font-weight:600; border-radius:9px;
  font-size:.84rem; transition:all .15s;
}
.btn-sm { font-size:.78rem; border-radius:7px; }
.btn-primary {
  background:linear-gradient(135deg,#2563eb,#3b82f6);
  border:none; color:#fff;
  box-shadow:0 2px 8px rgba(37,99,235,.3);
}
.btn-primary:hover { filter:brightness(1.08); box-shadow:0 4px 14px rgba(37,99,235,.4); color:#fff; }
.btn-danger, [class*="bg:var(--red)"] {
  background:linear-gradient(135deg,#c0392b,#e63946);
  border:none; color:#fff;
  box-shadow:0 2px 8px rgba(192,57,43,.3);
}
.btn-danger:hover { filter:brightness(1.08); box-shadow:0 4px 14px rgba(192,57,43,.4); color:#fff; }
.btn-success {
  background:linear-gradient(135deg,#059669,#10b981);
  border:none; color:#fff; box-shadow:0 2px 8px rgba(5,150,105,.3);
}
.btn-success:hover { filter:brightness(1.08); color:#fff; }
.btn-warning {
  background:linear-gradient(135deg,#d97706,#f59e0b);
  border:none; color:#fff; box-shadow:0 2px 8px rgba(217,119,6,.3);
}
.btn-warning:hover { filter:brightness(1.08); color:#fff; }
.btn-outline-danger {
  border:1.5px solid rgba(192,57,43,.4); color:#c0392b; background:transparent;
}
.btn-outline-danger:hover { background:rgba(192,57,43,.08); border-color:#c0392b; color:#c0392b; }
.btn-outline-primary {
  border:1.5px solid rgba(37,99,235,.4); color:#2563eb; background:transparent;
}
.btn-outline-primary:hover { background:rgba(37,99,235,.08); color:#2563eb; }
.btn-outline-secondary {
  border:1.5px solid var(--border); color:var(--muted); background:transparent;
}
.btn-outline-secondary:hover { background:var(--bg); color:var(--text); border-color:#c9cfd9; }
.btn-light {
  background:#f0f3fb; border:1.5px solid var(--border); color:var(--muted);
}
.btn-light:hover { background:#e8ecf5; color:var(--text); }

/* ══════════════════════════════════════════════════════════════════
   SECTION HEADERS inside cards
   ══════════════════════════════════════════════════════════════════ */
.card-section-title {
  font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px;
  color:var(--muted); padding-bottom:.6rem; margin-bottom:.8rem;
  border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.5rem;
}
.card-section-title .accent-dot {
  width:6px; height:6px; border-radius:50%; background:var(--red); display:inline-block;
}

/* Quick-action buttons on dashboard */
.qa-btn {
  display:inline-flex; align-items:center; gap:.45rem;
  padding:.5rem 1rem; border-radius:9px; font-size:.8rem; font-weight:600;
  border:1.5px solid; cursor:pointer; transition:all .15s; text-decoration:none;
}
.qa-btn-red    { color:#c0392b; border-color:rgba(192,57,43,.3); background:rgba(192,57,43,.06); }
.qa-btn-red:hover   { background:rgba(192,57,43,.12); color:#c0392b; }
.qa-btn-blue   { color:#2563eb; border-color:rgba(37,99,235,.3);  background:rgba(37,99,235,.06); }
.qa-btn-blue:hover  { background:rgba(37,99,235,.12); color:#2563eb; }
.qa-btn-green  { color:#059669; border-color:rgba(5,150,105,.3);  background:rgba(5,150,105,.06); }
.qa-btn-green:hover { background:rgba(5,150,105,.12); color:#059669; }
.qa-btn-gray   { color:#374151; border-color:var(--border); background:var(--bg); }
.qa-btn-gray:hover  { background:var(--border); color:var(--text); }

/* ══════════════════════════════════════════════════════════════════
   RESPONSIVE & MOBILE / TABLET OPTIMIZATIONS
   ══════════════════════════════════════════════════════════════════ */
.sidebar-overlay {
  display:none; position:fixed; inset:0; background:rgba(6,12,31,.72);
  backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px);
  z-index:999; opacity:0; transition:opacity .25s ease;
  pointer-events:none;
}
.sidebar-overlay.active {
  display:block; opacity:1; pointer-events:auto;
}

@media (max-width:991px) {
  .sidebar {
    transform:translateX(-100%);
    transition:transform .28s cubic-bezier(.4,0,.2,1);
    box-shadow:4px 0 32px rgba(0,0,0,.6);
    z-index:1005;
  }
  .sidebar.open {
    transform:translateX(0);
  }
  .main-content {
    margin-left:0 !important;
    max-width:100% !important;
    overflow-x:hidden;
  }
  .ham-btn {
    display:inline-flex !important;
    align-items:center;
    justify-content:center;
  }
  .page-body {
    padding:1.1rem 1rem;
  }
  .topbar {
    padding:.6rem 1rem;
  }
  .tbtn span {
    display:none;
  }
}

@media (max-width:576px) {
  .page-body {
    padding:0.75rem 0.5rem;
  }
  .topbar {
    padding:0.5rem 0.65rem;
    gap:0.4rem;
  }
  .topbar-title {
    font-size:0.92rem;
    max-width:150px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .topbar-left {
    gap:0.4rem;
    min-width:0;
  }
  .topbar-right {
    gap:0.3rem;
  }
  .tbtn {
    padding:0.38rem 0.55rem;
    font-size:0.75rem;
    border-radius:7px;
  }
  .card {
    border-radius:10px !important;
  }
  .card-body {
    padding:0.85rem !important;
  }
  .stat-card .card-body {
    padding:0.85rem !important;
  }
  .stat-value {
    font-size:1.25rem !important;
  }
  .stat-icon {
    width:40px !important;
    height:40px !important;
    font-size:1.05rem !important;
    border-radius:9px !important;
  }
}

/* ══════════════════════════════════════════════════════════════════
   UTILITY
   ══════════════════════════════════════════════════════════════════ */
.fw-800 { font-weight:800 !important; }
.text-red { color:var(--red) !important; }
.bg-red   { background:var(--red) !important; }
.gap-section { margin-bottom:1.25rem; }

/* ── Page header with breadcrumb ─────────────────────────────────── */
.page-header {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem;
}
.page-header h2 {
  font-size:1.2rem; font-weight:800; color:var(--text); margin:0;
  display:flex; align-items:center; gap:.6rem;
}
.page-header h2 .ph-icon {
  width:34px; height:34px; border-radius:10px;
  background:linear-gradient(135deg,rgba(192,57,43,.15),rgba(192,57,43,.06));
  display:flex; align-items:center; justify-content:center;
  color:var(--red); font-size:.85rem; flex-shrink:0;
}
</style>
<script src="/content-protect.js"></script>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-inner">
      <div class="brand-logo">
        <img src="/images/logos/logo.png" alt="NettingTeam">
      </div>
      <div class="brand-text">
        <h1>GCM</h1>
        <span>Billing Admin Panel</span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="<?= $base ?>/index.php" class="nav-link <?= navActive('index.php') ?>">
      <span class="ni"><i class="fa fa-gauge-high"></i></span> Dashboard
    </a>

    <?php if (canAccess('documents','view')): ?>
    <div class="nav-section">Documents</div>
    <a href="<?= $base ?>/documents.php" class="nav-link <?= navActive('documents.php') ?>">
      <span class="ni"><i class="fa fa-file-lines"></i></span> All Documents
    </a>
    <?php if (canAccess('documents','edit')): ?>
    <a href="<?= $base ?>/create.php" class="nav-link <?= navActive('create.php') ?>">
      <span class="ni"><i class="fa fa-circle-plus"></i></span> New Document
    </a>
    <?php endif; ?>
    <a href="<?= $base ?>/gstr1.php" class="nav-link <?= navActive('gstr1.php') ?>" style="<?= navActive('gstr1.php') ? '' : 'color:#38bdf8;' ?>">
      <span class="ni"><i class="fa fa-file-invoice-dollar text-info"></i></span> GSTR-1 HSN Helper
    </a>
    <?php endif; ?>

    <?php if (canAccess('companies','view') || canAccess('customers','view')): ?>
    <div class="nav-section">Records</div>
    <?php if (canAccess('companies','view')): ?>
    <a href="<?= $base ?>/companies.php" class="nav-link <?= navActive('companies.php') ?>">
      <span class="ni"><i class="fa fa-building"></i></span> Companies
    </a>
    <?php endif; ?>
    <?php if (canAccess('customers','view')): ?>
    <a href="<?= $base ?>/customers.php" class="nav-link <?= navActive('customers.php') ?>">
      <span class="ni"><i class="fa fa-users"></i></span> Customers
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <div class="nav-section">Analytics & Traffic</div>
    <a href="<?= $base ?>/visitors.php" class="nav-link <?= navActive('visitors.php') ?>" style="<?= navActive('visitors.php') ? '' : 'color:#10b981;' ?>">
      <span class="ni"><i class="fa fa-satellite-dish text-success"></i></span>
      <span class="d-flex align-items-center justify-content-between flex-grow-1">
        <span>Live Visitors</span>
        <span class="badge bg-success rounded-pill px-2 py-1" style="font-size:0.68rem;letter-spacing:0.5px;">LIVE</span>
      </span>
    </a>

    <div class="nav-section">System</div>
    <?php if (canAccess('seo_stats','view')): ?>
    <a href="<?= $base ?>/seo-stats.php" class="nav-link <?= navActive('seo-stats.php') ?>" style="<?= navActive('seo-stats.php') ? '' : 'color:rgba(255,200,50,.85)' ?>">
      <span class="ni"><i class="fa fa-chart-bar"></i></span> SEO Statistics
    </a>
    <?php endif; ?>
    <?php if (isAdmin()): ?>
    <a href="<?= $base ?>/employees.php" class="nav-link <?= navActive('employees.php') ?>">
      <span class="ni"><i class="fa fa-user-tie"></i></span> Employees
    </a>
    <a href="<?= $base ?>/settings.php" class="nav-link <?= navActive('settings.php') ?>">
      <span class="ni"><i class="fa fa-gear"></i></span> Settings
    </a>
    <a href="<?= $base ?>/security.php" class="nav-link <?= navActive('security.php') ?>">
      <span class="ni"><i class="fa fa-shield-halved"></i></span> Security &amp; Devices
    </a>
    <?php endif; ?>
    <div style="margin-top:0.4rem;padding-top:0.4rem;border-top:1px solid rgba(255,255,255,.07);">
      <a href="<?= $base ?>/logout.php" class="nav-link logout-link" title="Logout from this device">
        <span class="ni"><i class="fa fa-right-from-bracket"></i></span> Logout
      </a>
      <a href="<?= $base ?>/logout.php?all=1" class="nav-link logout-all-link" style="color:rgba(239,68,68,0.75);font-size:0.75rem;padding:0.4rem 0.85rem;" onclick="return confirm('Logout from ALL devices everywhere? You will need OTP on your next login.');" title="Logout from all devices">
        <span class="ni" style="background:rgba(239,68,68,0.12);color:#ef4444;width:24px;height:24px;font-size:0.7rem;"><i class="fa fa-power-off"></i></span> Logout All Devices
      </a>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-av"><?= strtoupper(substr(currentEmployeeName(), 0, 1)) ?></div>
      <div class="user-info">
        <span class="uname"><?= e(currentEmployeeName()) ?></span>
        <span class="urole"><?= isAdmin() ? 'Administrator' : 'Employee' ?></span>
      </div>
      <div class="online-dot ms-auto"></div>
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

<!-- Main Content -->
<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="ham-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
        <i class="fa fa-bars fa-sm"></i>
      </button>
      <span class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
      <span class="page-tag d-none d-sm-inline"><?= e($company['name'] ?? 'NettingTeam') ?></span>
    </div>
    <div class="topbar-right">
      <a href="/<?= trim(str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', dirname(dirname(__FILE__))), '/') ?>/" class="tbtn tbtn-ghost" target="_blank">
        <i class="fa fa-arrow-up-right-from-square"></i><span>View Site</span>
      </a>
      <a href="<?= $base ?>/create.php" class="tbtn tbtn-red">
        <i class="fa fa-plus"></i><span>New Doc</span>
      </a>
    </div>
  </div>

  <!-- Page Body -->
  <div class="page-body">
<?php
$flashSuccess = flashGet('success');
$flashError   = flashGet('error');
$flashWarning = flashGet('warning');
if ($flashSuccess): ?>
  <div class="alert alert-success alert-dismissible mb-3">
    <i class="fa fa-circle-check me-1"></i><?= e($flashSuccess) ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="padding:.4rem .5rem;"></button>
  </div>
<?php endif;
if ($flashError): ?>
  <div class="alert alert-danger alert-dismissible mb-3">
    <i class="fa fa-circle-exclamation me-1"></i><?= e($flashError) ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="padding:.4rem .5rem;"></button>
  </div>
<?php endif;
if ($flashWarning): ?>
  <div class="alert alert-warning alert-dismissible mb-3">
    <i class="fa fa-triangle-exclamation me-1"></i><?= e($flashWarning) ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="padding:.4rem .5rem;"></button>
  </div>
<?php endif; ?>
