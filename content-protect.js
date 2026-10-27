(function () {
  'use strict';

  // 1. CSS Protection
  var style = document.createElement('style');
  style.textContent = [
    '*, ::before, ::after {',
    '  -webkit-user-select: none !important;',
    '  -moz-user-select: none !important;',
    '  -ms-user-select: none !important;',
    '  user-select: none !important;',
    '}',
    'input, textarea, select {',
    '  -webkit-user-select: text !important;',
    '  -moz-user-select: text !important;',
    '  user-select: text !important;',
    '}',
    'img { -webkit-user-drag: none; user-drag: none; pointer-events: none; }'
  ].join('\n');
  if (document.head) document.head.appendChild(style);

  // 2. Right Click Disable
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
    showToast();
    return false;
  }, { capture: true });

  // 3. Selection & Drag Disable
  document.addEventListener('selectstart', function (e) {
    if (!isInput(e.target)) { e.preventDefault(); return false; }
  }, { capture: true });

  document.addEventListener('dragstart', function (e) {
    e.preventDefault();
    return false;
  }, { capture: true });

  // 4. Copy / Cut Disable
  document.addEventListener('copy', function (e) {
    if (!isInput(e.target)) { e.preventDefault(); showToast(); return false; }
  }, { capture: true });

  document.addEventListener('cut', function (e) {
    if (!isInput(e.target)) { e.preventDefault(); showToast(); return false; }
  }, { capture: true });

  // 5. Block Developer Keys & Shortcuts
  document.addEventListener('keydown', function (e) {
    var k = (e.key || '').toLowerCase();
    var code = e.keyCode || e.which;
    var ctrl = e.ctrlKey || e.metaKey;
    var shift = e.shiftKey;

    // F12
    if (code === 123) {
      e.preventDefault();
      showToast();
      return false;
    }

    // Ctrl/Cmd shortcuts
    if (ctrl) {
      // U: View Source, S: Save, P: Print
      if (k === 'u' || k === 's' || k === 'p') {
        e.preventDefault();
        showToast();
        return false;
      }
      // Ctrl+Shift+I / J / C / K (DevTools)
      if (shift && (k === 'i' || k === 'j' || k === 'c' || k === 'k')) {
        e.preventDefault();
        showToast();
        return false;
      }
    }
  }, { capture: true });

  // 6. Anti-Debugging DevTools Trap
  function devtoolsTrap() {
    var start = Date.now();
    debugger;
    if (Date.now() - start > 100) {
      document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#050b14;color:#fff;font-family:sans-serif;text-align:center;"><h2>⚠️ Developer tools are disabled on this site.</h2></div>';
    }
  }
  setInterval(devtoolsTrap, 1000);

  // 7. Clear Console
  try {
    console.log = function() {};
    console.warn = function() {};
    console.error = function() {};
    console.debug = function() {};
  } catch(err) {}

  function isInput(el) {
    if (!el) return false;
    var tag = (el.tagName || '').toUpperCase();
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
  }

  var toastTimer = null;
  function showToast() {
    var existing = document.getElementById('_sec_toast');
    if (existing) { clearTimeout(toastTimer); existing.remove(); }
    var t = document.createElement('div');
    t.id = '_sec_toast';
    t.textContent = '🔒 Content & Code Protected. Actions are disabled.';
    t.style.cssText = [
      'position:fixed', 'bottom:24px', 'left:50%',
      'transform:translateX(-50%) translateY(20px)',
      'background:rgba(10,22,44,.95)', 'color:#fff',
      'font-family:system-ui,-apple-system,sans-serif', 'font-size:13px', 'font-weight:600',
      'padding:12px 24px', 'border-radius:50px',
      'border:1px solid rgba(255,255,255,.12)',
      'box-shadow:0 8px 32px rgba(0,0,0,.4)',
      'z-index:999999', 'pointer-events:none',
      'opacity:0', 'transition:opacity .25s ease, transform .25s ease'
    ].join(';');
    if (document.body) {
      document.body.appendChild(t);
      requestAnimationFrame(function () {
        t.style.opacity = '1';
        t.style.transform = 'translateX(-50%) translateY(0)';
      });
      toastTimer = setTimeout(function () {
        t.style.opacity = '0';
        t.style.transform = 'translateX(-50%) translateY(10px)';
        setTimeout(function () { if (t.parentNode) t.remove(); }, 300);
      }, 2000);
    }
  }
})();