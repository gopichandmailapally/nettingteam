<?php
/**
 * NettingTeam — Real-Time Visitor Radar & Analytics
 * Live human tracking, entry pages, active pages, unique/repeated visitor counts.
 * Comprehensive time filters: 5m, 30m, 1h, 6h, 24h, today, 7d, 30d, 3m, 6m, 1y, 5y, all time.
 */

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Initial page data load
require_once dirname(__DIR__) . '/includes/db.php';
$pdo = getGcmDb();

$liveCount       = 0;
$rangeUnique     = 0;
$rangeNew        = 0;
$rangeRepeat     = 0;
$rangeViews      = 0;
$allTimeUnique   = 0;
$allTimeViews    = 0;
$liveSessions    = [];
$recentVisitors  = [];
$topPages        = [];
$sources         = [];
$devices         = [];

// ── Time Range & Pagination Resolution ────────────────────────
$rawRange = strtolower(trim($_GET['range'] ?? 'today'));
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = max(10, min(500, (int)($_GET['limit'] ?? 25)));
$offset   = ($page - 1) * $limit;

$normalizedRange = match($rawRange) {
    '5m', '5mins', 'last_5mins'       => '5m',
    '30m', '30mins', 'last_30mins'   => '30m',
    '1h', '1hr', 'last_1hr', '1hour'  => '1h',
    '6h', '6hrs', 'last_6hrs'        => '6h',
    '24h', '24hrs', 'last_24hrs'     => '24h',
    'today', 'todays'                => 'today',
    '7d', '7days', 'last_7days'      => '7d',
    '30d', '30days', 'last_30days'   => '30d',
    '3m', '3months', 'last_3months'  => '3m',
    '6m', '6months', 'last_6months'  => '6m',
    '1y', '1year', 'last_1year'      => '1y',
    '5y', '5years', 'last_5years'    => '5y',
    'all', 'alltime', 'total'        => 'all',
    default                          => 'today'
};

$rangeLabels = [
    '5m'    => 'Last 5 Minutes',
    '30m'   => 'Last 30 Minutes',
    '1h'    => 'Last 1 Hour',
    '6h'    => 'Last 6 Hours',
    '24h'   => 'Last 24 Hours',
    'today' => 'Today (Since 12 AM)',
    '7d'    => 'Last 7 Days',
    '30d'   => 'Last 30 Days',
    '3m'    => 'Last 3 Months',
    '6m'    => 'Last 6 Months',
    '1y'    => 'Last 1 Year',
    '5y'    => 'Last 5 Years',
    'all'   => 'All Time (Total)',
];

$vWhere = match($normalizedRange) {
    '5m'    => 'last_seen >= NOW() - INTERVAL 5 MINUTE',
    '30m'   => 'last_seen >= NOW() - INTERVAL 30 MINUTE',
    '1h'    => 'last_seen >= NOW() - INTERVAL 1 HOUR',
    '6h'    => 'last_seen >= NOW() - INTERVAL 6 HOUR',
    '24h'   => 'last_seen >= NOW() - INTERVAL 24 HOUR',
    'today' => 'last_seen >= DATE_SUB(DATE(DATE_ADD(NOW(), INTERVAL 330 MINUTE)), INTERVAL 330 MINUTE)',
    '7d'    => 'last_seen >= NOW() - INTERVAL 7 DAY',
    '30d'   => 'last_seen >= NOW() - INTERVAL 30 DAY',
    '3m'    => 'last_seen >= NOW() - INTERVAL 90 DAY',
    '6m'    => 'last_seen >= NOW() - INTERVAL 180 DAY',
    '1y'    => 'last_seen >= NOW() - INTERVAL 365 DAY',
    '5y'    => 'last_seen >= NOW() - INTERVAL 1825 DAY',
    'all'   => '1=1',
};

$pWhere = match($normalizedRange) {
    '5m'    => 'viewed_at >= NOW() - INTERVAL 5 MINUTE',
    '30m'   => 'viewed_at >= NOW() - INTERVAL 30 MINUTE',
    '1h'    => 'viewed_at >= NOW() - INTERVAL 1 HOUR',
    '6h'    => 'viewed_at >= NOW() - INTERVAL 6 HOUR',
    '24h'   => 'viewed_at >= NOW() - INTERVAL 24 HOUR',
    'today' => 'viewed_at >= DATE_SUB(DATE(DATE_ADD(NOW(), INTERVAL 330 MINUTE)), INTERVAL 330 MINUTE)',
    '7d'    => 'viewed_at >= NOW() - INTERVAL 7 DAY',
    '30d'   => 'viewed_at >= NOW() - INTERVAL 30 DAY',
    '3m'    => 'viewed_at >= NOW() - INTERVAL 90 DAY',
    '6m'    => 'viewed_at >= NOW() - INTERVAL 180 DAY',
    '1y'    => 'viewed_at >= NOW() - INTERVAL 365 DAY',
    '5y'    => 'viewed_at >= NOW() - INTERVAL 1825 DAY',
    'all'   => '1=1',
};

if ($pdo) {
    try {
        $liveCount     = (int)$pdo->query("SELECT COUNT(*) FROM gcm_live_sessions WHERE is_online = 1 AND last_heartbeat >= NOW() - INTERVAL 3 MINUTE")->fetchColumn();
        $allTimeUnique = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM gcm_visitors")->fetchColumn();
        $allTimeViews  = (int)$pdo->query("SELECT COUNT(*) FROM gcm_pageviews")->fetchColumn();

        $rangeUnique   = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_id) FROM gcm_visitors WHERE {$vWhere}")->fetchColumn();
        $rangeNew      = (int)$pdo->query("SELECT COUNT(*) FROM gcm_visitors WHERE visit_count = 1 AND {$vWhere}")->fetchColumn();
        $rangeRepeat   = (int)$pdo->query("SELECT COUNT(*) FROM gcm_visitors WHERE visit_count > 1 AND {$vWhere}")->fetchColumn();
        $rangeViews    = (int)$pdo->query("SELECT COUNT(*) FROM gcm_pageviews WHERE {$pWhere}")->fetchColumn();

        $stmtSessions = $pdo->query("
            SELECT 
                s.session_id, s.visitor_id, s.ip_address, s.city, s.state, s.device, s.os, s.browser,
                s.entry_page, s.current_page, s.page_title, s.referrer, s.traffic_source,
                s.started_at, s.last_heartbeat, s.time_spent_seconds, s.pageview_count,
                TIMESTAMPDIFF(SECOND, s.last_heartbeat, NOW()) as seconds_ago,
                v.visit_count
            FROM gcm_live_sessions s
            LEFT JOIN gcm_visitors v ON s.visitor_id = v.visitor_id
            WHERE s.is_online = 1 AND s.last_heartbeat >= NOW() - INTERVAL 5 MINUTE
            ORDER BY s.last_heartbeat DESC
            LIMIT 50
        ");
        $liveSessions = $stmtSessions->fetchAll();

        $totalPages = max(1, (int)ceil($rangeUnique / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = max(0, ($page - 1) * $limit);
        }

        $stmtRecent = $pdo->prepare("
            SELECT 
                visitor_id, city, state, device, os, browser,
                first_landing_page, first_referrer, traffic_source,
                first_seen,
                DATE_FORMAT(DATE_ADD(last_seen, INTERVAL 330 MINUTE), '%Y-%m-%d %h:%i:%s %p') as last_seen,
                visit_count, total_pageviews,
                TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
            FROM gcm_visitors
            WHERE {$vWhere}
            ORDER BY last_seen DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmtRecent->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtRecent->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtRecent->execute();
        $recentVisitors = $stmtRecent->fetchAll();

        $stmtTop = $pdo->query("
            SELECT page_url, COALESCE(NULLIF(page_title,''), page_url) as title, COUNT(*) as views
            FROM gcm_pageviews
            WHERE {$pWhere}
            GROUP BY page_url
            ORDER BY views DESC
            LIMIT 10
        ");
        $topPages = $stmtTop->fetchAll();

        $stmtSources = $pdo->query("
            SELECT traffic_source as name, COUNT(*) as count 
            FROM gcm_visitors 
            WHERE {$vWhere}
            GROUP BY traffic_source 
            ORDER BY count DESC 
            LIMIT 6
        ");
        $sources = $stmtSources->fetchAll();

        $stmtDevices = $pdo->query("
            SELECT device as name, COUNT(*) as count 
            FROM gcm_visitors 
            WHERE {$vWhere}
            GROUP BY device 
            ORDER BY count DESC
        ");
        $devices = $stmtDevices->fetchAll();

    } catch (Exception $e) {
        error_log("Visitors load error: " . $e->getMessage());
    }
}

$pageTitle = 'Live Visitors & Analytics';
require_once __DIR__ . '/includes/layout.php';
?>

<style>
/* Radar Pulse Animation */
.radar-pulse {
  width: 12px; height: 12px;
  background-color: #10b981;
  border-radius: 50%;
  display: inline-block;
  position: relative;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulse-green 1.8s infinite;
}
@keyframes pulse-green {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Glassy Stat Card */
.sc-radar {
  background: linear-gradient(135deg, rgba(6, 78, 59, 0.08) 0%, rgba(16, 185, 129, 0.12) 100%);
  border: 1px solid rgba(16, 185, 129, 0.25) !important;
}
.stat-icon.si-radar {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
}
.sc-radar::before {
  background: linear-gradient(90deg, #10b981, #34d399) !important;
}

.table-glass {
  background: #ffffff;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  overflow: hidden;
}
.table-glass th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border);
}
.table-glass td {
  padding: 0.85rem 1rem;
  vertical-align: middle;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.88rem;
}
.table-glass tr:last-child td {
  border-bottom: none;
}
.table-glass tr:hover td {
  background: #f8fafc;
}

.url-badge {
  display: inline-block;
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: monospace;
  font-size: 0.82rem;
  color: #1e293b;
  text-decoration: none;
}
.url-badge:hover {
  color: #2563eb;
  text-decoration: underline;
}

.device-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  background: #f1f5f9;
  color: #475569;
}

/* Time Range Filter Bar */
.filter-ribbon {
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 10px 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.filter-btn-group {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}
.filter-btn {
  font-size: 0.8rem;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease-in-out;
  text-decoration: none;
  white-space: nowrap;
}
.filter-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.filter-btn.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 2px 6px rgba(37,99,235,0.3);
}
.filter-divider {
  width: 1px;
  height: 20px;
  background: #cbd5e1;
  margin: 0 4px;
}
.badge-time-range {
  font-size: 0.8rem;
  font-weight: 700;
  background: rgba(37,99,235,0.1);
  color: #2563eb;
  border: 1px solid rgba(37,99,235,0.2);
  padding: 4px 10px;
  border-radius: 6px;
}
/* Pagination Styling */
.pagination {
  margin: 0;
  display: flex;
  align-items: center;
}
.pagination .page-item .page-link {
  border-radius: 6px;
  margin: 0 2px;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-weight: 600;
  font-size: 0.8rem;
  padding: 5px 10px;
  transition: all 0.15s ease-in-out;
  cursor: pointer;
  text-decoration: none;
}
.pagination .page-item.active .page-link {
  background-color: #2563eb;
  border-color: #2563eb;
  color: #ffffff;
  box-shadow: 0 2px 5px rgba(37,99,235,0.25);
}
.pagination .page-item.disabled .page-link {
  color: #94a3b8;
  background-color: #f8fafc;
  border-color: #e2e8f0;
  cursor: not-allowed;
  pointer-events: none;
}
.pagination .page-item:not(.active):not(.disabled) .page-link:hover {
  background-color: #f1f5f9;
  color: #0f172a;
  border-color: #cbd5e1;
}
</style>

<!-- Top Bar Controls -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <h4 class="mb-1 fw-bold d-flex align-items-center gap-2">
      <span class="radar-pulse"></span>
      Real-Time Visitor Radar &amp; Human Traffic
    </h4>
    <p class="text-muted mb-0 small">
      Real human visitor tracking across <strong>www.nettingteam.com</strong> &bull; Zero bot noise &bull; 100% hidden from website visitors
    </p>
  </div>
  
  <div class="d-flex align-items-center gap-2">
    <div class="form-check form-switch d-flex align-items-center gap-2 mb-0 me-2">
      <input class="form-check-input" type="checkbox" role="switch" id="autoRefreshSwitch" checked>
      <label class="form-check-label small fw-semibold" for="autoRefreshSwitch">Live Polling (5s)</label>
    </div>
    <span class="badge bg-light text-secondary border small py-2 px-3" id="syncStatus">
      <i class="fa fa-rotate text-muted me-1" id="syncIcon"></i> Synced: <span id="lastSyncTime">Just now</span>
    </span>
    <button class="btn btn-sm btn-outline-primary" onclick="pollLiveVisitors(true)">
      <i class="fa fa-sync-alt"></i> Refresh
    </button>
  </div>
</div>

<!-- COMPREHENSIVE TIME RANGE FILTER TOOLBAR -->
<div class="filter-ribbon mb-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-2">
      <span class="small fw-bold text-dark"><i class="fa fa-filter text-primary me-1"></i>Time Period:</span>
      <span class="badge-time-range" id="activeRangeBadge"><?= htmlspecialchars($rangeLabels[$normalizedRange] ?? 'Custom') ?></span>
    </div>
    
    <div class="filter-btn-group">
      <!-- Short-term real-time intervals -->
      <button type="button" class="filter-btn <?= $normalizedRange === '5m' ? 'active' : '' ?>" onclick="setTimeRange('5m')">5 Mins</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '30m' ? 'active' : '' ?>" onclick="setTimeRange('30m')">30 Mins</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '1h' ? 'active' : '' ?>" onclick="setTimeRange('1h')">1 Hour</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '6h' ? 'active' : '' ?>" onclick="setTimeRange('6h')">6 Hours</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '24h' ? 'active' : '' ?>" onclick="setTimeRange('24h')">24 Hours</button>

      <div class="filter-divider"></div>

      <!-- Standard calendar days & months -->
      <button type="button" class="filter-btn <?= $normalizedRange === 'today' ? 'active' : '' ?>" onclick="setTimeRange('today')">Today</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '7d' ? 'active' : '' ?>" onclick="setTimeRange('7d')">Last 7 Days</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '30d' ? 'active' : '' ?>" onclick="setTimeRange('30d')">Last 30 Days</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '3m' ? 'active' : '' ?>" onclick="setTimeRange('3m')">Last 3 Months</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '6m' ? 'active' : '' ?>" onclick="setTimeRange('6m')">Last 6 Months</button>

      <div class="filter-divider"></div>

      <!-- Long-term years & all time -->
      <button type="button" class="filter-btn <?= $normalizedRange === '1y' ? 'active' : '' ?>" onclick="setTimeRange('1y')">Last 1 Year</button>
      <button type="button" class="filter-btn <?= $normalizedRange === '5y' ? 'active' : '' ?>" onclick="setTimeRange('5y')">Last 5 Years</button>
      <button type="button" class="filter-btn <?= $normalizedRange === 'all' ? 'active' : '' ?>" onclick="setTimeRange('all')">All Time</button>
    </div>
  </div>
</div>

<!-- KPI Cards Row -->
<div class="row g-3 mb-4">
  <!-- 1. Live Right Now -->
  <div class="col-6 col-xl-3">
    <div class="stat-card sc-radar">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-radar"><i class="fa fa-satellite-dish"></i></div>
        <div>
          <div class="stat-label">Live Active Right Now</div>
          <div class="stat-value text-success d-flex align-items-center gap-2">
            <span id="kpi-live-count"><?= $liveCount ?></span>
            <span class="radar-pulse"></span>
          </div>
          <div class="stat-sub text-muted">Active in last 3 mins</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Unique Human Visitors -->
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-primary"><i class="fa fa-users"></i></div>
        <div>
          <div class="stat-label" id="kpi-unique-label">Unique Human Visitors</div>
          <div class="stat-value text-dark" id="kpi-unique-count"><?= number_format($rangeUnique) ?></div>
          <div class="stat-sub text-muted">
            <span id="kpi-unique-period"><?= htmlspecialchars($rangeLabels[$normalizedRange] ?? 'In selected period') ?></span>
            &bull; All-Time: <strong><?= number_format($allTimeUnique) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Repeated Customers -->
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-warning"><i class="fa fa-arrows-rotate"></i></div>
        <div>
          <div class="stat-label">Repeated Customers</div>
          <div class="stat-value text-warning-emphasis" id="kpi-repeated-count"><?= number_format($rangeRepeat) ?></div>
          <div class="stat-sub text-muted" id="kpi-repeated-sub">
            Returning visitors (&gt;1 visit) &bull; <?= $rangeUnique > 0 ? round(($rangeRepeat / $rangeUnique) * 100, 1) : 0 ?>%
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 4. New Customers & Pageviews -->
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-success"><i class="fa fa-user-plus"></i></div>
        <div>
          <div class="stat-label">New Customers (1st Visit)</div>
          <div class="stat-value text-success" id="kpi-new-count"><?= number_format($rangeNew) ?></div>
          <div class="stat-sub text-muted" id="kpi-views-sub">
            Pageviews: <strong id="kpi-range-views"><?= number_format($rangeViews) ?></strong>
            &bull; Total: <?= number_format($allTimeViews) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SECTION 1: LIVE ACTIVE SESSIONS -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:var(--radius);overflow:hidden;">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <span class="radar-pulse"></span>
      <h6 class="mb-0 fw-bold">Live Customers Active on Website Pages Right Now</h6>
      <span class="badge bg-success ms-2" id="badge-online-count"><?= count($liveSessions) ?> Online</span>
    </div>
    <span class="text-muted small">Updated in real-time</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-glass mb-0">
      <thead>
        <tr>
          <th>Status</th>
          <th>Customer Type</th>
          <th>Current Active Page (Right Now)</th>
          <th>Entry Page (Where They Entered)</th>
          <th>Location</th>
          <th>Source</th>
          <th>Device / Browser</th>
          <th>Time On Site</th>
          <th>Last Active</th>
        </tr>
      </thead>
      <tbody id="live-sessions-tbody">
        <?php if (empty($liveSessions)): ?>
          <tr id="empty-live-row">
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="fa fa-satellite text-muted mb-2" style="font-size:2rem;opacity:0.4;"></i>
              <div>No live visitors on the website this exact second. As soon as a visitor opens a page, they will appear here instantly!</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($liveSessions as $s): ?>
            <tr>
              <td>
                <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                  <span class="radar-pulse" style="width:7px;height:7px;"></span> Active
                </span>
              </td>
              <td>
                <?php if ((int)$s['visit_count'] > 1): ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                    <i class="fa fa-arrows-rotate me-1"></i> Repeated Customer (<?= (int)$s['visit_count'] ?> visits)
                  </span>
                <?php else: ?>
                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                    <i class="fa fa-user-plus me-1"></i> New Customer
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?= htmlspecialchars($s['current_page'] ?: '/') ?>" target="_blank" class="url-badge fw-bold text-success">
                  <?= htmlspecialchars($s['current_page'] ?: '/') ?>
                </a>
                <?php if (!empty($s['page_title'])): ?>
                  <div class="small text-muted text-truncate" style="max-width:260px;"><?= htmlspecialchars($s['page_title']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?= htmlspecialchars($s['entry_page'] ?: '/') ?>" target="_blank" class="url-badge text-muted">
                  <?= htmlspecialchars($s['entry_page'] ?: '/') ?>
                </a>
              </td>
              <td>
                <i class="fa fa-location-dot text-danger me-1"></i>
                <strong><?= htmlspecialchars($s['city'] ?: 'Hyderabad') ?></strong>
                <?php if (!empty($s['state'])): ?><span class="text-muted small">, <?= htmlspecialchars($s['state']) ?></span><?php endif; ?>
              </td>
              <td>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($s['traffic_source'] ?: 'Direct') ?></span>
              </td>
              <td>
                <span class="device-chip">
                  <i class="fa <?= $s['device'] === 'Mobile' ? 'fa-mobile-screen' : ($s['device'] === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop') ?>"></i>
                  <?= htmlspecialchars($s['device']) ?> &bull; <?= htmlspecialchars($s['browser']) ?>
                </span>
              </td>
              <td class="fw-semibold text-primary">
                <?= gmdate("i:s", (int)$s['time_spent_seconds']) ?>
              </td>
              <td class="small text-muted">
                <?= (int)$s['seconds_ago'] < 60 ? (int)$s['seconds_ago'] . 's ago' : floor((int)$s['seconds_ago'] / 60) . 'm ago' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- SECTION 2: TOP LANDING PAGES & SOURCES -->
<div class="row g-4 mb-4">
  <!-- Top Pages in selected range -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100" style="border-radius:var(--radius);overflow:hidden;">
      <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold">
          <i class="fa fa-fire text-danger me-2"></i>Most Visited Landing Pages (<span id="top-pages-range-label"><?= htmlspecialchars($rangeLabels[$normalizedRange] ?? 'Selected Period') ?></span>)
        </h6>
        <span class="badge bg-light text-secondary border">Top 10</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-glass mb-0">
            <thead>
              <tr>
                <th style="width:40px;">#</th>
                <th>Page Link</th>
                <th class="text-end">Visits (<span id="th-range-name"><?= htmlspecialchars($normalizedRange) ?></span>)</th>
              </tr>
            </thead>
            <tbody id="top-pages-tbody">
              <?php if (empty($topPages)): ?>
                <tr><td colspan="3" class="text-center py-4 text-muted">No page hits recorded in this time range.</td></tr>
              <?php else: ?>
                <?php foreach ($topPages as $idx => $p): ?>
                  <tr>
                    <td class="fw-bold text-muted"><?= $idx + 1 ?></td>
                    <td>
                      <a href="<?= htmlspecialchars($p['page_url']) ?>" target="_blank" class="fw-semibold text-dark text-decoration-none">
                        <?= htmlspecialchars($p['page_url']) ?>
                      </a>
                      <?php if (!empty($p['title']) && $p['title'] !== $p['page_url']): ?>
                        <div class="small text-muted text-truncate" style="max-width:350px;"><?= htmlspecialchars($p['title']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end fw-bold text-primary">
                      <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">
                        <?= number_format($p['views']) ?>
                      </span>
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

  <!-- Traffic Sources & Devices in selected range -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:var(--radius);">
      <div class="card-header bg-white py-3 px-4 border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa fa-share-nodes text-primary me-2"></i>Traffic Sources (<span id="sources-range-label"><?= htmlspecialchars($normalizedRange) ?></span>)</h6>
      </div>
      <div class="card-body px-4 py-3" id="sources-container">
        <?php if (empty($sources)): ?>
          <p class="text-muted small mb-0">No traffic sources recorded in this time range.</p>
        <?php else: ?>
          <?php foreach ($sources as $src): ?>
            <?php $pct = $rangeUnique > 0 ? round(($src['count'] / $rangeUnique) * 100, 1) : 0; ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between small fw-semibold mb-1">
                <span><?= htmlspecialchars($src['name']) ?></span>
                <span class="text-muted"><?= $src['count'] ?> (<?= $pct ?>%)</span>
              </div>
              <div class="progress" style="height:6px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:var(--radius);">
      <div class="card-header bg-white py-3 px-4 border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa fa-mobile-screen text-success me-2"></i>Device Share (<span id="devices-range-label"><?= htmlspecialchars($normalizedRange) ?></span>)</h6>
      </div>
      <div class="card-body px-4 py-3" id="devices-container">
        <?php if (empty($devices)): ?>
          <p class="text-muted small mb-0">No device records in this time range.</p>
        <?php else: ?>
          <?php foreach ($devices as $dev): ?>
            <?php $pct = $rangeUnique > 0 ? round(($dev['count'] / $rangeUnique) * 100, 1) : 0; ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between small fw-semibold mb-1">
                <span>
                  <i class="fa <?= $dev['name'] === 'Mobile' ? 'fa-mobile-screen' : ($dev['name'] === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop') ?> me-1"></i>
                  <?= htmlspecialchars($dev['name']) ?>
                </span>
                <span class="text-muted"><?= $dev['count'] ?> (<?= $pct ?>%)</span>
              </div>
              <div class="progress" style="height:6px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- SECTION 3: RECENT VISITORS HISTORY WITH LIVE SEARCH & LIMIT FILTER -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:var(--radius);overflow:hidden;">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
        <i class="fa fa-clock-rotate-left text-secondary"></i>
        Recent Human Visitors Log (<span id="log-range-label"><?= htmlspecialchars($rangeLabels[$normalizedRange] ?? 'Custom') ?></span>)
      </h6>
      <span class="text-muted small" id="log-count-subtitle">Showing <?= count($recentVisitors) ?> unique human visitors in this time period</span>
    </div>
    
    <div class="d-flex align-items-center gap-2">
      <!-- Search filter -->
      <div class="input-group input-group-sm" style="width:220px;">
        <span class="input-group-text bg-light border-end-0"><i class="fa fa-search text-muted"></i></span>
        <input type="text" class="form-control border-start-0" id="visitorSearchInput" placeholder="Filter city, source, url..." onkeyup="filterVisitorRows()">
      </div>

      <!-- Limit selector -->
      <select class="form-select form-select-sm" id="limitSelect" style="width:110px;" onchange="changeLimit(this.value)">
        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 rows</option>
        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 rows</option>
        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 rows</option>
        <option value="250" <?= $limit == 250 ? 'selected' : '' ?>>250 rows</option>
        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500 rows</option>
      </select>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-glass mb-0" id="recent-visitors-table">
      <thead>
        <tr>
          <th>Visitor</th>
          <th>Customer Status</th>
          <th>Initial Landing Page</th>
          <th>City / State</th>
          <th>Source</th>
          <th>Device</th>
          <th>Total Pages</th>
          <th><i class="fa fa-clock text-primary me-1"></i>Last Seen (IST)</th>
        </tr>
      </thead>
      <tbody id="recent-visitors-tbody">
        <?php if (empty($recentVisitors)): ?>
          <tr id="empty-recent-row"><td colspan="8" class="text-center py-4 text-muted">No visitors recorded in this selected time range. Try choosing a wider time filter above.</td></tr>
        <?php else: ?>
          <?php foreach ($recentVisitors as $v): ?>
            <tr class="visitor-row">
              <td>
                <code class="small text-muted"><?= substr($v['visitor_id'], 0, 14) ?>...</code>
              </td>
              <td>
                <?php if ((int)$v['visit_count'] > 1): ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                    Repeated Customer (<?= (int)$v['visit_count'] ?> visits)
                  </span>
                <?php else: ?>
                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                    New Customer (1st visit)
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <a href="<?= htmlspecialchars($v['first_landing_page'] ?: '/') ?>" target="_blank" class="url-badge text-primary">
                  <?= htmlspecialchars($v['first_landing_page'] ?: '/') ?>
                </a>
              </td>
              <td>
                <i class="fa fa-location-dot text-danger me-1"></i>
                <strong><?= htmlspecialchars($v['city'] ?: 'Hyderabad') ?></strong>
                <?php if (!empty($v['state'])): ?><span class="text-muted small">, <?= htmlspecialchars($v['state']) ?></span><?php endif; ?>
              </td>
              <td>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['traffic_source'] ?: 'Direct') ?></span>
              </td>
              <td>
                <span class="device-chip">
                  <i class="fa <?= $v['device'] === 'Mobile' ? 'fa-mobile-screen' : ($v['device'] === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop') ?>"></i>
                  <?= htmlspecialchars($v['device']) ?>
                </span>
              </td>
              <td class="fw-semibold text-secondary">
                <?= (int)$v['total_pageviews'] ?> views
              </td>
              <td class="small text-muted">
                <div class="fw-semibold text-dark"><?= htmlspecialchars($v['last_seen']) ?></div>
                <div class="text-muted" style="font-size:0.75rem;">
                  <?= (int)$v['seconds_ago'] < 60 ? (int)$v['seconds_ago'] . 's ago' : floor((int)$v['seconds_ago'] / 60) . 'm ago' ?> &bull; <span class="badge bg-light text-secondary border">IST</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Modern Pagination Bar -->
  <div class="card-footer bg-white py-3 px-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-3" id="visitors-pagination-container">
    <div class="text-muted small" id="pagination-info">
      Showing <span class="fw-bold text-dark" id="page-start"><?= $rangeUnique > 0 ? $offset + 1 : 0 ?></span>
      to <span class="fw-bold text-dark" id="page-end"><?= min($offset + count($recentVisitors), $rangeUnique) ?></span>
      of <span class="fw-bold text-dark" id="page-total"><?= number_format($rangeUnique) ?></span> human visitors
    </div>
    <nav aria-label="Visitors pagination">
      <ul class="pagination pagination-sm mb-0 gap-1" id="pagination-nav">
        <!-- Pagination controls dynamically updated via JS / server-rendered initially -->
        <?php if ($totalPages > 1): ?>
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(1)" title="First Page">&laquo;</a>
          </li>
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(<?= max(1, $page - 1) ?>)" title="Previous Page">&lsaquo;</a>
          </li>
          <?php 
            $startP = max(1, $page - 2);
            $endP   = min($totalPages, $page + 2);
            if ($endP - $startP < 4) {
                if ($startP === 1) $endP = min($totalPages, $startP + 4);
                elseif ($endP === $totalPages) $startP = max(1, $endP - 4);
            }
            if ($startP > 1): 
          ?>
            <li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(1)">1</a></li>
            <?php if ($startP > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $startP; $i <= $endP; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="javascript:void(0)" onclick="changePage(<?= $i ?>)"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($endP < $totalPages): ?>
            <?php if ($endP < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(<?= $totalPages ?>)"><?= $totalPages ?></a></li>
          <?php endif; ?>

          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(<?= min($totalPages, $page + 1) ?>)" title="Next Page">&rsaquo;</a>
          </li>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="javascript:void(0)" onclick="changePage(<?= $totalPages ?>)" title="Last Page">&raquo;</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</div>

<!-- REAL-TIME POLLING & FILTER SCRIPT -->
<script>
var currentRange = '<?= $normalizedRange ?>';
var currentLimit = <?= $limit ?>;
var currentPage  = <?= $page ?>;
var totalPages   = <?= $totalPages ?>;
var totalRecords = <?= $rangeUnique ?>;
var pollingTimer = null;

var rangeNameMap = {
    '5m': 'Last 5 Minutes',
    '30m': 'Last 30 Minutes',
    '1h': 'Last 1 Hour',
    '6h': 'Last 6 Hours',
    '24h': 'Last 24 Hours',
    'today': 'Today (Since 12 AM)',
    '7d': 'Last 7 Days',
    '30d': 'Last 30 Days',
    '3m': 'Last 3 Months',
    '6m': 'Last 6 Months',
    '1y': 'Last 1 Year',
    '5y': 'Last 5 Years',
    'all': 'All Time (Total)'
};

function formatSeconds(sec) {
    if (sec < 60) return sec + 's ago';
    var mins = Math.floor(sec / 60);
    if (mins < 60) return mins + 'm ago';
    var hrs = Math.floor(mins / 60);
    return hrs + 'h ago';
}

function setTimeRange(range) {
    currentRange = range;
    currentPage  = 1;

    // Update active button state
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').indexOf("'" + range + "'") !== -1) {
            btn.classList.add('active');
        }
    });

    var label = rangeNameMap[range] || range;
    document.getElementById('activeRangeBadge').innerText = label;
    document.getElementById('kpi-unique-period').innerText = label;
    document.getElementById('top-pages-range-label').innerText = label;
    document.getElementById('th-range-name').innerText = range;
    document.getElementById('sources-range-label').innerText = range;
    document.getElementById('devices-range-label').innerText = range;
    document.getElementById('log-range-label').innerText = label;

    // Update browser URL without reloading
    if (window.history && window.history.pushState) {
        var newUrl = window.location.pathname + '?range=' + range + '&limit=' + currentLimit + '&page=' + currentPage;
        window.history.pushState({ range: range, limit: currentLimit, page: currentPage }, '', newUrl);
    }

    pollLiveVisitors(true);
}

function changeLimit(limit) {
    currentLimit = parseInt(limit, 10);
    currentPage  = 1;
    if (window.history && window.history.pushState) {
        var newUrl = window.location.pathname + '?range=' + currentRange + '&limit=' + currentLimit + '&page=' + currentPage;
        window.history.pushState({ range: currentRange, limit: currentLimit, page: currentPage }, '', newUrl);
    }
    pollLiveVisitors(true);
}

function changePage(p) {
    p = parseInt(p, 10);
    if (isNaN(p) || p < 1 || p > totalPages || p === currentPage) return;
    currentPage = p;

    if (window.history && window.history.pushState) {
        var newUrl = window.location.pathname + '?range=' + currentRange + '&limit=' + currentLimit + '&page=' + currentPage;
        window.history.pushState({ range: currentRange, limit: currentLimit, page: currentPage }, '', newUrl);
    }

    pollLiveVisitors(true);

    var tableEl = document.getElementById('recent-visitors-table');
    if (tableEl) {
        tableEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function filterVisitorRows() {
    var query = document.getElementById('visitorSearchInput').value.toLowerCase().trim();
    var rows = document.querySelectorAll('.visitor-row');
    var visibleCount = 0;

    rows.forEach(function(row) {
        var text = row.innerText.toLowerCase();
        if (text.indexOf(query) !== -1) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('log-count-subtitle').innerText = 'Showing ' + visibleCount + ' matching visitors in ' + (rangeNameMap[currentRange] || currentRange);
}

function pollLiveVisitors(isManual) {
    var syncIcon = document.getElementById('syncIcon');
    if (syncIcon) syncIcon.classList.add('fa-spin');

    var apiUrl = '/admin/api/visitors_live.php?range=' + encodeURIComponent(currentRange) + '&limit=' + currentLimit + '&page=' + currentPage;

    fetch(apiUrl)
      .then(function(res) {
          if (!res.ok) throw new Error('API error: ' + res.status);
          return res.json();
      })
      .then(function(data) {
          if (syncIcon) syncIcon.classList.remove('fa-spin');
          if (data.status !== 'ok') return;

          // 1. Update KPI numbers
          document.getElementById('kpi-live-count').innerText = data.live_count;
          document.getElementById('badge-online-count').innerText = data.live_count + ' Online';
          document.getElementById('kpi-unique-count').innerText = Number(data.range_unique).toLocaleString();
          document.getElementById('kpi-repeated-count').innerText = Number(data.range_repeat).toLocaleString();
          document.getElementById('kpi-new-count').innerText = Number(data.range_new).toLocaleString();
          document.getElementById('kpi-range-views').innerText = Number(data.range_views).toLocaleString();

          var repPct = data.range_unique > 0 ? ((data.range_repeat / data.range_unique) * 100).toFixed(1) : 0;
          document.getElementById('kpi-repeated-sub').innerText = 'Returning visitors (>1 visit) • ' + repPct + '%';

          document.getElementById('lastSyncTime').innerText = new Date().toLocaleTimeString();

          // 2. Render Live Sessions Table
          var liveTbody = document.getElementById('live-sessions-tbody');
          if (data.live_sessions && data.live_sessions.length > 0) {
              var liveHtml = '';
              data.live_sessions.forEach(function(s) {
                  var custBadge = s.visit_count > 1 
                      ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="fa fa-arrows-rotate me-1"></i> Repeated Customer (' + s.visit_count + ' visits)</span>'
                      : '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle"><i class="fa fa-user-plus me-1"></i> New Customer</span>';

                  var devIcon = s.device === 'Mobile' ? 'fa-mobile-screen' : (s.device === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop');
                  var duration = Math.floor(s.time_spent_seconds / 60) + 'm ' + (s.time_spent_seconds % 60) + 's';

                  liveHtml += '<tr>' +
                    '<td><span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1"><span class="radar-pulse" style="width:7px;height:7px;"></span> Active</span></td>' +
                    '<td>' + custBadge + '</td>' +
                    '<td><a href="' + escapeHtml(s.current_page || '/') + '" target="_blank" class="url-badge fw-bold text-success">' + escapeHtml(s.current_page || '/') + '</a>' +
                    (s.page_title ? '<div class="small text-muted text-truncate" style="max-width:260px;">' + escapeHtml(s.page_title) + '</div>' : '') + '</td>' +
                    '<td><a href="' + escapeHtml(s.entry_page || '/') + '" target="_blank" class="url-badge text-muted">' + escapeHtml(s.entry_page || '/') + '</a></td>' +
                    '<td><i class="fa fa-location-dot text-danger me-1"></i><strong>' + escapeHtml(s.city || 'Hyderabad') + '</strong>' + (s.state ? '<span class="text-muted small">, ' + escapeHtml(s.state) + '</span>' : '') + '</td>' +
                    '<td><span class="badge bg-light text-dark border">' + escapeHtml(s.traffic_source || 'Direct') + '</span></td>' +
                    '<td><span class="device-chip"><i class="fa ' + devIcon + '"></i> ' + escapeHtml(s.device) + ' &bull; ' + escapeHtml(s.browser) + '</span></td>' +
                    '<td class="fw-semibold text-primary">' + duration + '</td>' +
                    '<td class="small text-muted">' + formatSeconds(s.seconds_ago) + '</td>' +
                  '</tr>';
              });
              liveTbody.innerHTML = liveHtml;
          } else {
              liveTbody.innerHTML = '<tr id="empty-live-row"><td colspan="9" class="text-center py-5 text-muted">' +
                '<i class="fa fa-satellite text-muted mb-2" style="font-size:2rem;opacity:0.4;"></i>' +
                '<div>No live visitors on the website this exact second. As soon as a visitor opens a page, they will appear here instantly!</div></td></tr>';
          }

          // 3. Render Top Pages Table
          var topTbody = document.getElementById('top-pages-tbody');
          if (data.top_pages && data.top_pages.length > 0) {
              var topHtml = '';
              data.top_pages.forEach(function(p, idx) {
                  topHtml += '<tr>' +
                    '<td class="fw-bold text-muted">' + (idx + 1) + '</td>' +
                    '<td><a href="' + escapeHtml(p.page_url) + '" target="_blank" class="fw-semibold text-dark text-decoration-none">' + escapeHtml(p.page_url) + '</a>' +
                    (p.title && p.title !== p.page_url ? '<div class="small text-muted text-truncate" style="max-width:350px;">' + escapeHtml(p.title) + '</div>' : '') + '</td>' +
                    '<td class="text-end fw-bold text-primary"><span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">' + Number(p.views).toLocaleString() + '</span></td>' +
                  '</tr>';
              });
              topTbody.innerHTML = topHtml;
          } else {
              topTbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No page hits recorded in this time range.</td></tr>';
          }

          // 4. Render Traffic Sources
          var srcBox = document.getElementById('sources-container');
          if (data.sources && data.sources.length > 0) {
              var srcHtml = '';
              data.sources.forEach(function(src) {
                  var pct = data.range_unique > 0 ? ((src.count / data.range_unique) * 100).toFixed(1) : 0;
                  srcHtml += '<div class="mb-3">' +
                    '<div class="d-flex justify-content-between small fw-semibold mb-1">' +
                      '<span>' + escapeHtml(src.name) + '</span>' +
                      '<span class="text-muted">' + src.count + ' (' + pct + '%)</span>' +
                    '</div>' +
                    '<div class="progress" style="height:6px;">' +
                      '<div class="progress-bar bg-primary" role="progressbar" style="width: ' + pct + '%"></div>' +
                    '</div>' +
                  '</div>';
              });
              srcBox.innerHTML = srcHtml;
          } else {
              srcBox.innerHTML = '<p class="text-muted small mb-0">No traffic sources recorded in this time range.</p>';
          }

          // 5. Render Devices
          var devBox = document.getElementById('devices-container');
          if (data.devices && data.devices.length > 0) {
              var devHtml = '';
              data.devices.forEach(function(dev) {
                  var pct = data.range_unique > 0 ? ((dev.count / data.range_unique) * 100).toFixed(1) : 0;
                  var devIcon = dev.name === 'Mobile' ? 'fa-mobile-screen' : (dev.name === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop');
                  devHtml += '<div class="mb-3">' +
                    '<div class="d-flex justify-content-between small fw-semibold mb-1">' +
                      '<span><i class="fa ' + devIcon + ' me-1"></i> ' + escapeHtml(dev.name) + '</span>' +
                      '<span class="text-muted">' + dev.count + ' (' + pct + '%)</span>' +
                    '</div>' +
                    '<div class="progress" style="height:6px;">' +
                      '<div class="progress-bar bg-success" role="progressbar" style="width: ' + pct + '%"></div>' +
                    '</div>' +
                  '</div>';
              });
              devBox.innerHTML = devHtml;
          } else {
              devBox.innerHTML = '<p class="text-muted small mb-0">No device records in this time range.</p>';
          }

          // 6. Render Recent Visitors Log Table
          var recTbody = document.getElementById('recent-visitors-tbody');
          document.getElementById('log-count-subtitle').innerText = 'Showing ' + (data.recent_visitors ? data.recent_visitors.length : 0) + ' unique human visitors in ' + (data.range_label || currentRange);

          if (data.recent_visitors && data.recent_visitors.length > 0) {
              var recHtml = '';
              data.recent_visitors.forEach(function(v) {
                  var custBadge = v.visit_count > 1 
                      ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Repeated Customer (' + v.visit_count + ' visits)</span>'
                      : '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">New Customer (1st visit)</span>';

                  var devIcon = v.device === 'Mobile' ? 'fa-mobile-screen' : (v.device === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-laptop');

                  recHtml += '<tr class="visitor-row">' +
                    '<td><code class="small text-muted">' + escapeHtml(v.visitor_id.substring(0, 14)) + '...</code></td>' +
                    '<td>' + custBadge + '</td>' +
                    '<td><a href="' + escapeHtml(v.first_landing_page || '/') + '" target="_blank" class="url-badge text-primary">' + escapeHtml(v.first_landing_page || '/') + '</a></td>' +
                    '<td><i class="fa fa-location-dot text-danger me-1"></i><strong>' + escapeHtml(v.city || 'Hyderabad') + '</strong>' + (v.state ? '<span class="text-muted small">, ' + escapeHtml(v.state) + '</span>' : '') + '</td>' +
                    '<td><span class="badge bg-light text-dark border">' + escapeHtml(v.traffic_source || 'Direct') + '</span></td>' +
                    '<td><span class="device-chip"><i class="fa ' + devIcon + '"></i> ' + escapeHtml(v.device) + '</span></td>' +
                    '<td class="fw-semibold text-secondary">' + v.total_pageviews + ' views</td>' +
                    '<td class="small text-muted"><div class="fw-semibold text-dark">' + escapeHtml(v.last_seen) + '</div><div class="text-muted" style="font-size:0.75rem;">' + formatSeconds(v.seconds_ago) + ' &bull; <span class="badge bg-light text-secondary border">IST</span></div></td>' +
                  '</tr>';
              });
              recTbody.innerHTML = recHtml;
              filterVisitorRows();
          } else {
              recTbody.innerHTML = '<tr id="empty-recent-row"><td colspan="8" class="text-center py-4 text-muted">No visitors recorded in this selected time range. Try choosing a wider time filter above.</td></tr>';
          }

          // 7. Update Pagination Bar
          renderPagination(data.pagination);
      })
      .catch(function(err) {
          if (syncIcon) syncIcon.classList.remove('fa-spin');
          console.error("Live polling error:", err);
      });
}

function renderPagination(p) {
    if (!p) return;
    currentPage  = p.page;
    currentLimit = p.limit;
    totalRecords = p.total_records;
    totalPages   = p.total_pages;

    var pageStart = document.getElementById('page-start');
    var pageEnd   = document.getElementById('page-end');
    var pageTotal = document.getElementById('page-total');
    if (pageStart) pageStart.innerText = p.start.toLocaleString();
    if (pageEnd)   pageEnd.innerText   = p.end.toLocaleString();
    if (pageTotal) pageTotal.innerText = p.total_records.toLocaleString();

    var nav = document.getElementById('pagination-nav');
    if (!nav) return;

    if (p.total_pages <= 1) {
        nav.innerHTML = '';
        return;
    }

    var html = '';

    // First & Prev buttons
    var prevDisabled = p.page <= 1 ? ' disabled' : '';
    html += '<li class="page-item' + prevDisabled + '"><a class="page-link" href="javascript:void(0)" onclick="changePage(1)" title="First Page">&laquo;</a></li>';
    html += '<li class="page-item' + prevDisabled + '"><a class="page-link" href="javascript:void(0)" onclick="changePage(' + (p.page - 1) + ')" title="Previous Page">&lsaquo;</a></li>';

    // Page window calculation
    var startP = Math.max(1, p.page - 2);
    var endP   = Math.min(p.total_pages, p.page + 2);
    if (endP - startP < 4) {
        if (startP === 1) endP = Math.min(p.total_pages, startP + 4);
        else if (endP === p.total_pages) startP = Math.max(1, endP - 4);
    }

    if (startP > 1) {
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(1)">1</a></li>';
        if (startP > 2) {
            html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }

    for (var i = startP; i <= endP; i++) {
        var activeClass = i === p.page ? ' active' : '';
        html += '<li class="page-item' + activeClass + '"><a class="page-link" href="javascript:void(0)" onclick="changePage(' + i + ')">' + i + '</a></li>';
    }

    if (endP < p.total_pages) {
        if (endP < p.total_pages - 1) {
            html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        html += '<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="changePage(' + p.total_pages + ')">' + p.total_pages + '</a></li>';
    }

    // Next & Last buttons
    var nextDisabled = p.page >= p.total_pages ? ' disabled' : '';
    html += '<li class="page-item' + nextDisabled + '"><a class="page-link" href="javascript:void(0)" onclick="changePage(' + (p.page + 1) + ')" title="Next Page">&rsaquo;</a></li>';
    html += '<li class="page-item' + nextDisabled + '"><a class="page-link" href="javascript:void(0)" onclick="changePage(' + p.total_pages + ')" title="Last Page">&raquo;</a></li>';

    nav.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Auto-refresh control
document.getElementById('autoRefreshSwitch').addEventListener('change', function(e) {
    if (e.target.checked) {
        startPolling();
    } else {
        stopPolling();
    }
});

function startPolling() {
    stopPolling();
    pollingTimer = setInterval(function() { pollLiveVisitors(false); }, 5000);
}

function stopPolling() {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

// Start polling on page load
startPolling();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
