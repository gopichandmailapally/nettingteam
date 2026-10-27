/**
 * NettingTeam - Real-Time User Analytics Beacon
 * Privacy-friendly, zero front-end UI footprint, asynchronous non-blocking tracking.
 */
(function () {
    'use strict';

    // Fast bot rejection on client side
    var ua = navigator.userAgent || '';
    if (/bot|googlebot|bingbot|crawler|spider|slurp|headless|lighthouse|ptst/i.test(ua)) {
        return;
    }

    // Helper: UUID generator
    function generateId(prefix) {
        return (prefix || 'id') + '_' + Math.random().toString(36).substring(2, 10) + '_' + Date.now().toString(36);
    }

    var VID_KEY   = 'gcm_vid';
    var SID_KEY   = 'gcm_sid';
    var COUNT_KEY = 'gcm_vcount';
    var ENTRY_KEY = 'gcm_entry_page';

    // 1. Persistent Visitor ID (Stored in localStorage, survives restarts)
    var visitorId = '';
    try {
        visitorId = localStorage.getItem(VID_KEY);
        if (!visitorId) {
            visitorId = generateId('v');
            localStorage.setItem(VID_KEY, visitorId);
        }
    } catch (e) {
        visitorId = generateId('v');
    }

    // 2. Session ID (Per browser session / tab)
    var sessionId = '';
    var isNewSession = false;
    try {
        sessionId = sessionStorage.getItem(SID_KEY);
        if (!sessionId) {
            sessionId = generateId('s');
            sessionStorage.setItem(SID_KEY, sessionId);
            isNewSession = true;
        }
    } catch (e) {
        sessionId = generateId('s');
    }

    // 3. Visit Count (Tracks New vs Returning / Repeated customer)
    var visitCount = 1;
    try {
        var prevCount = parseInt(localStorage.getItem(COUNT_KEY) || '0', 10);
        if (isNewSession) {
            visitCount = prevCount + 1;
            localStorage.setItem(COUNT_KEY, visitCount);
        } else {
            visitCount = prevCount || 1;
        }
    } catch (e) {
        visitCount = 1;
    }

    // 4. Entry Landing Page (Where the visitor entered the site)
    var entryPage = '';
    try {
        entryPage = sessionStorage.getItem(ENTRY_KEY);
        if (!entryPage) {
            entryPage = window.location.pathname + window.location.search;
            sessionStorage.setItem(ENTRY_KEY, entryPage);
        }
    } catch (e) {
        entryPage = window.location.pathname;
    }

    // 5. Detect Device, OS & Browser
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
    var isTablet = /(iPad|tablet|(android(?!.*mobile))|(windows(?!.*phone)(.*touch))|kindle|playbook|silk)/i.test(ua);
    var device   = isTablet ? 'Tablet' : (isMobile ? 'Mobile' : 'Desktop');

    var os = 'Other';
    if (/Android/i.test(ua)) os = 'Android';
    else if (/iPhone|iPad|iPod/i.test(ua)) os = 'iOS';
    else if (/Windows/i.test(ua)) os = 'Windows';
    else if (/Macintosh|Mac OS X/i.test(ua)) os = 'macOS';
    else if (/Linux/i.test(ua)) os = 'Linux';

    var browser = 'Other';
    if (/Edg/i.test(ua)) browser = 'Edge';
    else if (/Chrome|CriOS/i.test(ua)) browser = 'Chrome';
    else if (/Safari/i.test(ua) && !/Chrome|CriOS/i.test(ua)) browser = 'Safari';
    else if (/Firefox|FxiOS/i.test(ua)) browser = 'Firefox';

    // 6. Transmit Payload via sendBeacon or fetch keepalive
    function sendTracking(action, extra) {
        var payload = {
            action: action || 'pageview',
            visitor_id: visitorId,
            session_id: sessionId,
            page_url: window.location.pathname + window.location.search,
            page_title: document.title || '',
            entry_page: entryPage,
            referrer: document.referrer || '',
            visit_count: visitCount,
            device: device,
            os: os,
            browser: browser,
            screen: (window.screen ? window.screen.width + 'x' + window.screen.height : '')
        };

        if (extra && typeof extra === 'object') {
            for (var k in extra) {
                if (extra.hasOwnProperty(k)) payload[k] = extra[k];
            }
        }

        var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/track.php', blob);
        } else {
            try {
                fetch('/api/track.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    keepalive: true
                }).catch(function () {});
            } catch (err) {}
        }
    }

    // A. Send initial Pageview
    sendTracking('pageview');

    // B. Send Live Heartbeat every 25 seconds while tab is active
    var startTime = Date.now();
    var heartbeatInterval = setInterval(function () {
        if (document.visibilityState === 'visible') {
            var duration = Math.round((Date.now() - startTime) / 1000);
            sendTracking('heartbeat', { time_spent: duration });
        }
    }, 25000);

    // C. Detect Leave / Page Hide
    window.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            var duration = Math.round((Date.now() - startTime) / 1000);
            sendTracking('heartbeat', { time_spent: duration });
        }
    });

    window.addEventListener('pagehide', function () {
        var duration = Math.round((Date.now() - startTime) / 1000);
        sendTracking('leave', { time_spent: duration });
    });

})();
