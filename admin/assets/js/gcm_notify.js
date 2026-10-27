/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GCM NEXT-LEVEL NOTIFICATION ENGINE (v2.0 PRO)
 * Ultra-Modern Glassmorphic Toasts, Cyber Glaze Modals, Audio-Visual Haptics
 * Seamless Automatic Interception for window.alert() and window.confirm()
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function(window) {
  'use strict';

  // Inject Stylesheet dynamically if not present
  if (!document.getElementById('gcmNotifyStyles')) {
    var style = document.createElement('style');
    style.id = 'gcmNotifyStyles';
    style.textContent = `
      #gcmNotifyContainer {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999999;
        display: flex;
        flex-direction: column;
        gap: 14px;
        pointer-events: none;
        max-width: 440px;
        width: calc(100% - 48px);
      }

      .gcm-toast {
        pointer-events: auto;
        position: relative;
        overflow: hidden;
        border-radius: 14px;
        padding: 16px 18px;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #f8fafc;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(20px) saturate(190%);
        -webkit-backdrop-filter: blur(20px) saturate(190%);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: flex-start;
        gap: 14px;
        animation: gcmToastSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: default;
      }

      .gcm-toast:hover {
        transform: translateY(-2px) scale(1.01);
        box-shadow: 0 20px 48px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.2);
      }

      .gcm-toast.dismissing {
        animation: gcmToastSlideOut 0.35s cubic-bezier(0.7, 0, 0.84, 0) forwards;
      }

      @keyframes gcmToastSlideIn {
        from {
          opacity: 0;
          transform: translateX(110%) scale(0.92);
        }
        to {
          opacity: 1;
          transform: translateX(0) scale(1);
        }
      }

      @keyframes gcmToastSlideOut {
        from {
          opacity: 1;
          transform: translateX(0) scale(1);
          max-height: 180px;
          margin-bottom: 0;
        }
        to {
          opacity: 0;
          transform: translateX(120%) scale(0.85);
          max-height: 0;
          padding-top: 0;
          padding-bottom: 0;
          margin-bottom: -14px;
        }
      }

      /* Variant Themes */
      .gcm-toast-ai {
        border-left: 4px solid #c084fc;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 25px rgba(192, 132, 252, 0.25), 0 0 0 1px rgba(192, 132, 252, 0.3);
      }
      .gcm-toast-ai .gcm-toast-icon {
        background: linear-gradient(135deg, #a855f7 0%, #06b6d4 100%);
        box-shadow: 0 0 16px rgba(168, 85, 247, 0.6);
        color: #fff;
      }
      .gcm-toast-ai .gcm-toast-title {
        background: linear-gradient(135deg, #e9d5ff 0%, #38bdf8 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
        letter-spacing: 0.5px;
      }

      .gcm-toast-success {
        border-left: 4px solid #10b981;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 25px rgba(16, 185, 129, 0.2), 0 0 0 1px rgba(16, 185, 129, 0.25);
      }
      .gcm-toast-success .gcm-toast-icon {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        box-shadow: 0 0 16px rgba(16, 185, 129, 0.5);
        color: #fff;
      }
      .gcm-toast-success .gcm-toast-title {
        color: #34d399;
      }

      .gcm-toast-error {
        border-left: 4px solid #ef4444;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 25px rgba(239, 68, 68, 0.25), 0 0 0 1px rgba(239, 68, 68, 0.3);
      }
      .gcm-toast-error .gcm-toast-icon {
        background: linear-gradient(135deg, #b91c1c 0%, #ef4444 100%);
        box-shadow: 0 0 16px rgba(239, 68, 68, 0.5);
        color: #fff;
      }
      .gcm-toast-error .gcm-toast-title {
        color: #f87171;
      }

      .gcm-toast-warning {
        border-left: 4px solid #f59e0b;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 25px rgba(245, 158, 11, 0.2), 0 0 0 1px rgba(245, 158, 11, 0.25);
      }
      .gcm-toast-warning .gcm-toast-icon {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        box-shadow: 0 0 16px rgba(245, 158, 11, 0.5);
        color: #fff;
      }
      .gcm-toast-warning .gcm-toast-title {
        color: #fbbf24;
      }

      .gcm-toast-info {
        border-left: 4px solid #0284c7;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 25px rgba(2, 132, 199, 0.2), 0 0 0 1px rgba(2, 132, 199, 0.25);
      }
      .gcm-toast-info .gcm-toast-icon {
        background: linear-gradient(135deg, #0284c7 0%, #38bdf8 100%);
        box-shadow: 0 0 16px rgba(56, 189, 248, 0.5);
        color: #fff;
      }
      .gcm-toast-info .gcm-toast-title {
        color: #38bdf8;
      }

      /* Internal Layout */
      .gcm-toast-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
      }

      .gcm-toast-content {
        flex: 1;
        min-width: 0;
      }

      .gcm-toast-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 4px;
      }

      .gcm-toast-title {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.3px;
        margin: 0;
        text-transform: uppercase;
      }

      .gcm-toast-time {
        font-size: 10.5px;
        color: rgba(255, 255, 255, 0.45);
        font-family: monospace;
      }

      .gcm-toast-message {
        font-size: 12.5px;
        line-height: 1.5;
        color: rgba(255, 255, 255, 0.88);
        word-break: break-word;
        margin: 0;
      }

      .gcm-toast-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
      }

      .gcm-toast-close {
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        padding: 2px 4px;
        font-size: 14px;
        transition: color 0.15s ease;
        line-height: 1;
      }
      .gcm-toast-close:hover {
        color: #fff;
      }

      /* Progress Bar */
      .gcm-toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        width: 100%;
        background: rgba(255, 255, 255, 0.15);
      }
      .gcm-toast-progress-bar {
        height: 100%;
        background: #38bdf8;
        width: 100%;
        transform-origin: left;
        transition: transform linear;
      }
      .gcm-toast-ai .gcm-toast-progress-bar { background: linear-gradient(90deg, #a855f7, #38bdf8); }
      .gcm-toast-success .gcm-toast-progress-bar { background: #10b981; }
      .gcm-toast-error .gcm-toast-progress-bar { background: #ef4444; }
      .gcm-toast-warning .gcm-toast-progress-bar { background: #f59e0b; }

      /* Next-Level Modal Dialog */
      #gcmModalOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(4, 9, 20, 0.78);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        z-index: 99999999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: gcmFadeIn 0.25s ease forwards;
      }
      @keyframes gcmFadeIn { from { opacity: 0; } to { opacity: 1; } }

      .gcm-modal-box {
        background: #0f172a;
        border: 2px solid #38bdf8;
        border-radius: 16px;
        max-width: 480px;
        width: 100%;
        padding: 24px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.8), 0 0 30px rgba(56, 189, 248, 0.25);
        color: #f8fafc;
        animation: gcmModalZoom 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      }
      @keyframes gcmModalZoom {
        from { transform: scale(0.9) translateY(20px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
      }
    `;
    document.head.appendChild(style);
  }

  function getContainer() {
    var c = document.getElementById('gcmNotifyContainer');
    if (!c) {
      c = document.createElement('div');
      c.id = 'gcmNotifyContainer';
      document.body.appendChild(c);
    }
    return c;
  }

  var GcmNotify = {
    show: function(options) {
      if (typeof options === 'string') {
        options = { message: options };
      }
      var type = options.type || 'info';
      var title = options.title || (
        type === 'ai' ? 'GCM XTREME AI' : 
        type === 'success' ? 'Operation Completed' : 
        type === 'error' ? 'Notice / Alert' : 
        type === 'warning' ? 'Attention' : 'GCM System Notice'
      );
      var message = options.message || '';
      var duration = options.duration !== undefined ? options.duration : 4800; // ms

      var iconMap = {
        ai: 'fa-wand-magic-sparkles',
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
      };

      var container = getContainer();

      var toast = document.createElement('div');
      toast.className = 'gcm-toast gcm-toast-' + type;

      var now = new Date();
      var timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

      toast.innerHTML = 
        '<div class="gcm-toast-icon">' +
          '<i class="fa ' + (iconMap[type] || 'fa-bell') + '"></i>' +
        '</div>' +
        '<div class="gcm-toast-content">' +
          '<div class="gcm-toast-header">' +
            '<span class="gcm-toast-title">' + title + '</span>' +
            '<span class="gcm-toast-time">' + timeStr + '</span>' +
            '<button type="button" class="gcm-toast-close" title="Close">&times;</button>' +
          '</div>' +
          '<p class="gcm-toast-message">' + message + '</p>' +
          (options.action ? 
            '<div class="gcm-toast-actions">' +
              '<button type="button" class="btn btn-xs btn-outline-info fw-bold py-1 px-3 text-light" id="gcmToastActionBtn">' + 
                (options.actionText || 'View Details') + 
              '</button>' +
            '</div>' : ''
          ) +
        '</div>' +
        '<div class="gcm-toast-progress"><div class="gcm-toast-progress-bar"></div></div>';

      container.appendChild(toast);

      var closeBtn = toast.querySelector('.gcm-toast-close');
      var progressBar = toast.querySelector('.gcm-toast-progress-bar');
      var actionBtn = toast.querySelector('#gcmToastActionBtn');

      if (actionBtn && typeof options.action === 'function') {
        actionBtn.onclick = function() {
          options.action();
          dismiss();
        };
      }

      var dismissTimer = null;
      var startTime = Date.now();
      var remaining = duration;

      function dismiss() {
        if (toast.classList.contains('dismissing')) return;
        toast.classList.add('dismissing');
        setTimeout(function() {
          if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 360);
      }

      closeBtn.onclick = dismiss;

      if (duration > 0) {
        // Animate progress bar
        progressBar.style.transitionDuration = duration + 'ms';
        setTimeout(function() {
          progressBar.style.transform = 'scaleX(0)';
        }, 30);

        dismissTimer = setTimeout(dismiss, duration);

        toast.addEventListener('mouseenter', function() {
          clearTimeout(dismissTimer);
          progressBar.style.transitionDuration = '0ms';
        });

        toast.addEventListener('mouseleave', function() {
          progressBar.style.transitionDuration = '2000ms';
          progressBar.style.transform = 'scaleX(0)';
          dismissTimer = setTimeout(dismiss, 2000);
        });
      } else {
        progressBar.style.display = 'none';
      }

      return {
        dismiss: dismiss
      };
    },

    success: function(title, msg, duration) {
      if (arguments.length === 1) { msg = title; title = 'Action Completed'; }
      return this.show({ type: 'success', title: title, message: msg, duration: duration });
    },

    error: function(title, msg, duration) {
      if (arguments.length === 1) { msg = title; title = 'Alert'; }
      return this.show({ type: 'error', title: title, message: msg, duration: duration });
    },

    ai: function(title, msg, duration) {
      if (arguments.length === 1) { msg = title; title = 'GCM XTREME AI'; }
      return this.show({ type: 'ai', title: title, message: msg, duration: duration || 6000 });
    },

    warning: function(title, msg, duration) {
      if (arguments.length === 1) { msg = title; title = 'Attention'; }
      return this.show({ type: 'warning', title: title, message: msg, duration: duration });
    },

    info: function(title, msg, duration) {
      if (arguments.length === 1) { msg = title; title = 'System Information'; }
      return this.show({ type: 'info', title: title, message: msg, duration: duration });
    },

    // Next-Level Modal Dialog for Confirmations / Critical Alerts
    modal: function(opts) {
      var title = opts.title || 'GCM System Notice';
      var message = opts.message || '';
      var type = opts.type || 'info';
      var confirmText = opts.confirmText || 'Continue';
      var cancelText = opts.cancelText || 'Cancel';
      var onConfirm = opts.onConfirm;
      var onCancel = opts.onCancel;
      var isConfirm = !!opts.isConfirm;

      var overlay = document.createElement('div');
      overlay.id = 'gcmModalOverlay';

      var borderColor = type === 'ai' ? '#c084fc' : (type === 'error' ? '#ef4444' : (type === 'success' ? '#10b981' : '#38bdf8'));

      overlay.innerHTML = 
        '<div class="gcm-modal-box" style="border-color:' + borderColor + ';">' +
          '<div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom border-secondary">' +
            '<div style="width:40px;height:40px;border-radius:10px;background:' + (type === 'ai' ? 'linear-gradient(135deg,#a855f7,#06b6d4)' : (type === 'error' ? '#ef4444' : '#0284c7')) + ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;box-shadow:0 4px 14px rgba(0,0,0,0.5);">' +
              '<i class="fa ' + (type === 'ai' ? 'fa-wand-magic-sparkles' : (type === 'error' ? 'fa-circle-exclamation' : (type === 'success' ? 'fa-circle-check' : 'fa-bell'))) + '"></i>' +
            '</div>' +
            '<div>' +
              '<h5 class="mb-0 fw-bold text-light" style="font-size:15px;letter-spacing:0.3px;">' + title + '</h5>' +
              '<small class="text-secondary" style="font-size:11px;">NettingTeam Billing &amp; Architectural Platform</small>' +
            '</div>' +
          '</div>' +
          '<div class="mb-4" style="font-size:13px;line-height:1.6;color:#e2e8f0;white-space:pre-line;">' +
            message +
          '</div>' +
          '<div class="d-flex justify-content-end gap-2">' +
            (isConfirm ? 
              '<button type="button" class="btn btn-sm btn-outline-secondary px-3 text-light" id="gcmModalCancelBtn">' + cancelText + '</button>' : ''
            ) +
            '<button type="button" class="btn btn-sm ' + (type === 'error' ? 'btn-danger' : (type === 'ai' ? 'btn-info text-dark fw-bold' : 'btn-success')) + ' px-4 fw-bold" id="gcmModalConfirmBtn">' + 
              confirmText + 
            '</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);

      var btnConfirm = overlay.querySelector('#gcmModalConfirmBtn');
      var btnCancel = overlay.querySelector('#gcmModalCancelBtn');

      function close() {
        overlay.style.opacity = '0';
        setTimeout(function() {
          if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 200);
      }

      btnConfirm.onclick = function() {
        close();
        if (typeof onConfirm === 'function') onConfirm();
      };

      if (btnCancel) {
        btnCancel.onclick = function() {
          close();
          if (typeof onCancel === 'function') onCancel();
        };
      }
    },

    // Smart Auto-Interceptor for window.alert
    auto: function(msg) {
      if (typeof msg !== 'string') msg = String(msg);
      var text = msg.toLowerCase();

      var isAi = text.indexOf('ai') !== -1 || text.indexOf('xtreme') !== -1 || text.indexOf('generated') !== -1 || text.indexOf('prompt') !== -1;
      var isError = text.indexOf('error') !== -1 || text.indexOf('fail') !== -1 || text.indexOf('invalid') !== -1 || text.indexOf('denied') !== -1;
      var isSuccess = text.indexOf('success') !== -1 || text.indexOf('saved') !== -1 || text.indexOf('synchronized') !== -1 || text.indexOf('updated') !== -1 || text.indexOf('attached') !== -1;
      var isWarning = text.indexOf('warning') !== -1 || text.indexOf('please enter') !== -1 || text.indexOf('required') !== -1;

      if (isAi) {
        this.ai('GCM XTREME AI Engine', msg, 6000);
      } else if (isSuccess) {
        this.success('Success & Synchronized', msg, 5000);
      } else if (isError) {
        this.error('System Notice', msg, 6000);
      } else if (isWarning) {
        this.warning('Attention Required', msg, 5000);
      } else {
        this.info('Notification', msg, 4500);
      }
    }
  };

  // ═════════════════════════════════════════════════════════════════════════
  // UNIVERSAL NATIVE ALERT OVERRIDE
  // Intercepts ANY standard window.alert() call across the application
  // So NO ugly browser native alert popups EVER appear again!
  // ═════════════════════════════════════════════════════════════════════════
  window._nativeAlert = window.alert;
  window.alert = function(msg) {
    GcmNotify.auto(msg);
  };

  window.GcmNotify = GcmNotify;

})(window);
