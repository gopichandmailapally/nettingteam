  </div><!-- /page-body -->
</div><!-- /main-content -->

<!-- Close sidebar on outside click (mobile) -->
<script>
document.addEventListener('click', function(e) {
  var sb = document.getElementById('sidebar');
  if (sb && sb.classList.contains('open') && !sb.contains(e.target) && !e.target.closest('.ham-btn')) {
    if (typeof toggleSidebar === 'function') {
      toggleSidebar(false);
    } else {
      sb.classList.remove('open');
    }
  }
});
</script>

<!-- Admin 2-Hour Inactivity Auto-Logout Guardian -->
<script>
(function() {
  const TIMEOUT_MS = 2 * 60 * 60 * 1000; // 2 hours = 7,200,000 ms
  const WARNING_MS = 5 * 60 * 1000;      // 5 minutes warning before logout
  const PING_INTERVAL_MS = 5 * 60 * 1000; // Keepalive ping every 5 minutes of active usage
  
  let lastActive = Date.now();
  let lastPing = Date.now();
  let isWarningOpen = false;

  function showIdleWarning() {
    if (isWarningOpen || document.getElementById('gcm-inactivity-toast')) return;
    isWarningOpen = true;

    const banner = document.createElement('div');
    banner.id = 'gcm-inactivity-toast';
    banner.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:999999;background:rgba(15,23,42,0.95);backdrop-filter:blur(16px);border:2px solid #eab308;box-shadow:0 12px 36px rgba(0,0,0,0.6);border-radius:14px;padding:16px 20px;color:#fff;max-width:390px;font-family:Inter,sans-serif;display:flex;align-items:center;gap:14px;animation:fadeIn .3s ease;';
    banner.innerHTML = `
      <div style="width:40px;height:40px;background:rgba(234,179,8,0.18);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#facc15;font-size:1.25rem;flex-shrink:0;">
        <i class="fa fa-clock"></i>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:0.88rem;color:#fef08a;">Inactivity Auto-Logout Warning</div>
        <div style="font-size:0.77rem;color:#cbd5e1;margin-top:2px;">You will be logged out in <span id="gcm-idle-timer" style="font-weight:700;color:#fff;">5:00</span> due to 2 hours of inactivity.</div>
      </div>
      <button type="button" id="gcm-idle-resume-btn" style="background:linear-gradient(135deg,#eab308,#ca8a04);color:#0f172a;border:none;font-weight:800;font-size:0.76rem;padding:7px 13px;border-radius:8px;cursor:pointer;flex-shrink:0;box-shadow:0 2px 10px rgba(234,179,8,0.3);">Stay Active</button>
    `;
    document.body.appendChild(banner);

    document.getElementById('gcm-idle-resume-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      recordUserActivity(true);
    });
  }

  function hideIdleWarning() {
    const el = document.getElementById('gcm-inactivity-toast');
    if (el) el.remove();
    isWarningOpen = false;
  }

  function recordUserActivity(forcePing = false) {
    lastActive = Date.now();
    hideIdleWarning();

    // Ping server if more than 5 minutes since last ping
    if (forcePing || (Date.now() - lastPing > PING_INTERVAL_MS)) {
      lastPing = Date.now();
      fetch('<?= $base ?>/api/keepalive.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
          if (data && !data.ok && data.redirect) {
            window.location.href = data.redirect;
          }
        })
        .catch(() => {});
    }
  }

  // Monitor user activity events
  ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
    window.addEventListener(evt, () => recordUserActivity(false), { passive: true });
  });

  // Check inactivity every 5 seconds
  setInterval(function() {
    const idleMs = Date.now() - lastActive;

    if (idleMs >= TIMEOUT_MS) {
      // 2 Hours exceeded! Auto-logout!
      window.location.href = '<?= $base ?>/login.php?timeout=2hr';
      return;
    }

    if (idleMs >= (TIMEOUT_MS - WARNING_MS)) {
      // Last 5 minutes warning
      showIdleWarning();
      const remainSecs = Math.max(0, Math.floor((TIMEOUT_MS - idleMs) / 1000));
      const m = Math.floor(remainSecs / 60);
      const s = String(remainSecs % 60).padStart(2, '0');
      const timerEl = document.getElementById('gcm-idle-timer');
      if (timerEl) timerEl.textContent = m + ':' + s;
    } else {
      hideIdleWarning();
    }
  }, 5000);
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/content-protect.js"></script>
</body>
</html>
