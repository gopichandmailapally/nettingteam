<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$docs      = getAllDocuments();
$customers = getAllCustomers();

$totalDocs   = count($docs);
$totalCust   = count($customers);
$totalValue  = array_sum(array_column($docs, 'grand_total'));
$draftCount  = count(array_filter($docs, fn($d) => ($d['status'] ?? 'draft') === 'draft'));
$recentDocs  = array_slice($docs, 0, 8);

$typeStats = ['estimation' => 0, 'proforma_invoice' => 0, 'tax_invoice' => 0, 'bill' => 0];
foreach ($docs as $d) { $typeStats[$d['type'] ?? 'estimation']++; }

// Visitor analytics
require_once dirname(__DIR__) . '/includes/db.php';
$liveVisitorsCount = 0;
$todayVisitorsCount = 0;
$gcmPdo = getGcmDb();
if ($gcmPdo) {
    try {
        $liveVisitorsCount = (int)$gcmPdo->query("SELECT COUNT(*) FROM gcm_live_sessions WHERE is_online = 1 AND last_heartbeat >= NOW() - INTERVAL 3 MINUTE")->fetchColumn();
        $todayVisitorsCount = (int)$gcmPdo->query("SELECT COUNT(DISTINCT visitor_id) FROM gcm_visitors WHERE last_seen >= CURDATE()")->fetchColumn();
    } catch(Exception $e){}
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/layout.php';
?>

<!-- Live Visitor Radar Banner on Dashboard -->
<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg, #091a3c 0%, #0d2858 100%);color:#fff;border-radius:var(--radius);overflow:hidden;">
  <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.18);display:flex;align-items:center;justify-content:center;color:#34d399;font-size:1.4rem;">
        <i class="fa fa-satellite-dish"></i>
      </div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <span style="width:10px;height:10px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 10px #10b981;"></span>
          <h6 class="mb-0 fw-bold text-white">Live Website Visitors Radar</h6>
          <span class="badge bg-success rounded-pill px-2 py-1" style="font-size:0.75rem;"><?= number_format($liveVisitorsCount) ?> Active Online</span>
        </div>
        <div class="small text-white-50 mt-1">
          Tracking real human customer sessions across <strong>www.nettingteam.com</strong> &bull; <strong><?= number_format($todayVisitorsCount) ?></strong> unique visitors active today
        </div>
      </div>
    </div>
    <div>
      <a href="<?= $base ?>/visitors.php" class="btn btn-sm btn-light fw-bold px-3 py-2 text-primary shadow-sm">
        <i class="fa fa-eye me-1"></i> Open Live Radar &amp; Visitor Tracking &rarr;
      </a>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card sc-red">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-red"><i class="fa fa-file-invoice"></i></div>
        <div>
          <div class="stat-label">Total Documents</div>
          <div class="stat-value"><?= $totalDocs ?></div>
          <div class="stat-sub">All time</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card sc-green">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-green"><i class="fa fa-indian-rupee-sign"></i></div>
        <div>
          <div class="stat-label">Total Value</div>
          <div class="stat-value">₹<?= $totalValue >= 100000 ? number_format($totalValue/100000,2) . 'L' : fmtAmount($totalValue) ?></div>
          <div class="stat-sub">Gross billed</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card sc-blue">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-blue"><i class="fa fa-users"></i></div>
        <div>
          <div class="stat-label">Customers</div>
          <div class="stat-value"><?= $totalCust ?></div>
          <div class="stat-sub">Saved records</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card sc-orange">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon si-orange"><i class="fa fa-pen-to-square"></i></div>
        <div>
          <div class="stat-label">Drafts</div>
          <div class="stat-value"><?= $draftCount ?></div>
          <div class="stat-sub">Pending finalise</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Doc type breakdown -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="card-section-title"><span class="accent-dot"></span>Document Breakdown</div>
        <?php foreach ([
          'estimation'       => ['Estimations',       '#f59e0b', 'type-est'],
          'proforma_invoice' => ['Proforma Invoices', '#9333ea', 'type-pi'],
          'tax_invoice'      => ['Tax Invoices',      '#3b82f6', 'type-inv'],
          'bill'             => ['Bills',             '#10b981', 'type-bill'],
        ] as $k => [$lbl, $col, $cls]):
          $pct = $totalDocs > 0 ? round($typeStats[$k] / $totalDocs * 100) : 0; ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="type-badge <?= $cls ?>"><?= $lbl ?></span>
            <strong style="font-size:.82rem;color:var(--text);"><?= $typeStats[$k] ?></strong>
          </div>
          <div style="height:5px;background:#f0f3fb;border-radius:3px;overflow:hidden;">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $col ?>;border-radius:3px;transition:width .5s ease;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if ($totalDocs === 0): ?>
        <p class="text-muted text-center py-2 mb-0" style="font-size:.8rem;">No documents yet</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent docs -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-body pb-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="card-section-title mb-0"><span class="accent-dot"></span>Recent Documents</div>
          <a href="documents.php" class="tbtn tbtn-ghost" style="font-size:.75rem;padding:.3rem .7rem;">View All</a>
        </div>
      </div>
      <?php if (empty($recentDocs)): ?>
      <div class="text-center text-muted py-4 px-3">
        <i class="fa fa-file-circle-plus fa-2x mb-2 d-block" style="opacity:.2;"></i>
        No documents yet. <a href="create.php" style="color:var(--red);">Create your first</a>.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr>
            <th>Doc #</th><th>Customer</th><th>Type</th><th class="text-end">Amount</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($recentDocs as $d):
            $t = $d['type'] ?? 'estimation';
            $isGst = $d['is_gst'] ?? true;
            $tCls = $t === 'estimation' ? 'type-est' : ($t === 'tax_invoice' ? 'type-inv' : 'type-bill');
          ?>
          <tr>
            <td style="white-space:nowrap;"><a href="view.php?id=<?= urlencode($d['id']) ?>" style="color:var(--red);font-weight:700;font-size:.82rem;text-decoration:none;"><?= e($d['id']) ?></a></td>
            <td style="font-size:.83rem;"><?= e($d['bill_to']['name'] ?? '—') ?></td>
            <td><span class="type-badge <?= $tCls ?>"><?= docTypeLabel($t, $isGst) ?></span></td>
            <td class="text-end" style="font-weight:700;font-size:.83rem;">₹<?= fmtAmount($d['grand_total'] ?? 0) ?></td>
            <td><?= statusBadge($d['status'] ?? 'draft') ?></td>
            <td>
              <a href="view.php?id=<?= urlencode($d['id']) ?>" class="tbtn tbtn-ghost" style="padding:.25rem .55rem;font-size:.72rem;"><i class="fa fa-eye"></i></a>
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

<!-- Quick Actions -->
<div class="card">
  <div class="card-body">
    <div class="card-section-title"><span class="accent-dot"></span>Quick Actions</div>
    <div class="d-flex flex-wrap gap-2">
      <a href="create.php?type=estimation" class="qa-btn qa-btn-red">
        <i class="fa fa-file-pen"></i> New Estimation
      </a>
      <a href="create.php?type=proforma_invoice" class="qa-btn" style="background:#fdf4ff;color:#9333ea;border:1.5px solid #e9d5ff;">
        <i class="fa fa-file-invoice-dollar"></i> New Proforma Invoice
      </a>
      <a href="create.php?type=tax_invoice" class="qa-btn qa-btn-blue">
        <i class="fa fa-file-invoice"></i> New Tax Invoice
      </a>
      <a href="create.php?type=bill" class="qa-btn qa-btn-green">
        <i class="fa fa-receipt"></i> New Bill
      </a>
      <a href="customers.php?action=new" class="qa-btn qa-btn-gray">
        <i class="fa fa-user-plus"></i> Add Customer
      </a>
      <a href="settings.php" class="qa-btn qa-btn-gray">
        <i class="fa fa-signature"></i> Upload Signature
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
