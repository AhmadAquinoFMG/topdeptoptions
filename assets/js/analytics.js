/**
 * TDO analytics core — a thin convention layer over Umami.
 *
 * Every event carries two stitching props:
 *   - session_id : a per-visit UUID kept in sessionStorage, reused across every
 *                  page of the visit so the whole journey is one flow.
 *   - funnel_id  : a constant naming the funnel, for dashboard filtering.
 *
 * Two transports are exposed:
 *   - track(name, data)       in-page events, via umami.track().
 *   - trackBeacon(name, data) unload/navigation-safe events, via navigator.sendBeacon
 *                             with a fetch({keepalive:true}) fallback.
 *
 * Everything is try/catch wrapped: a tracking failure must never block the funnel.
 *
 * Loaded synchronously (no defer) from header.php so window.TDOAnalytics exists
 * before any page's inline scripts run. The Umami script itself is deferred, so
 * in-page track() calls must fire at/after DOMContentLoaded (when umami is ready);
 * beacon calls POST directly and don't depend on umami being loaded.
 */
(function (w) {
    'use strict';

    var WEBSITE_ID = '2ee9bab4-05ac-4d89-84aa-c05fdbdec0dd';
    var ENDPOINT   = 'https://cloud.umami.is/api/send';
    var FUNNEL_ID  = 'enrollment';
    var SID_KEY    = 'umami_funnel_sid';

    // QA/test traffic flag. Landing with ?test=fmg_true marks the whole visit as
    // test traffic so it can be filtered out of (or isolated in) the dashboard.
    var TEST_KEY   = 'umami_test_mode';
    var TEST_PARAM = 'test';
    var TEST_VALUE = 'fmg_true';

    function uuid() {
        try {
            if (w.crypto && typeof w.crypto.randomUUID === 'function') {
                return w.crypto.randomUUID();
            }
        } catch (e) {}
        // RFC-4122-ish fallback for older browsers.
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    // One UUID per visit, generated once and reused across every page.
    function sessionId() {
        try {
            var id = w.sessionStorage.getItem(SID_KEY);
            if (!id) {
                id = uuid();
                w.sessionStorage.setItem(SID_KEY, id);
            }
            return id;
        } catch (e) {
            // sessionStorage blocked (private mode / cookies off): degrade gracefully.
            return uuid();
        }
    }

    // The active test-mode value for this visit, or null. Persisted so it sticks
    // across every page even after the ?test= query string is gone.
    function testMode() {
        try { return w.sessionStorage.getItem(TEST_KEY) || null; } catch (e) { return null; }
    }

    // Detect ?test=fmg_true on this load and latch it for the rest of the visit.
    // Returns true only the first time it's seen (so the marker event fires once).
    function detectTestMode() {
        try {
            if (w.sessionStorage.getItem(TEST_KEY)) return false; // already latched this visit
            var qp = new URLSearchParams(w.location.search);
            if (qp.get(TEST_PARAM) === TEST_VALUE) {
                w.sessionStorage.setItem(TEST_KEY, 'fmg');
                return true;
            }
        } catch (e) {}
        return false;
    }

    var newlyDetectedTest = detectTestMode();

    // Stamp every event with the stitching props, then merge caller data.
    function withBase(data) {
        var out = { session_id: sessionId(), funnel_id: FUNNEL_ID };
        var tm = testMode();
        if (tm) out.test_mode = tm;
        if (data) {
            for (var k in data) {
                if (Object.prototype.hasOwnProperty.call(data, k) && data[k] !== undefined) {
                    out[k] = data[k];
                }
            }
        }
        return out;
    }

    // In-page transport. No-ops silently if umami hasn't loaded yet.
    function track(name, data) {
        try {
            if (w.umami && typeof w.umami.track === 'function') {
                w.umami.track(name, withBase(data));
            }
        } catch (e) {}
    }

    // Unload/navigation-safe transport: sendBeacon first, fetch keepalive fallback.
    function trackBeacon(name, data) {
        try {
            var body = JSON.stringify({
                type: 'event',
                payload: {
                    website:  WEBSITE_ID,
                    name:     name,
                    data:     withBase(data),
                    hostname: w.location.hostname,
                    url:      w.location.pathname + w.location.search,
                    referrer: w.document.referrer || '',
                    title:    w.document.title || '',
                    language: (w.navigator && w.navigator.language) || '',
                    screen:   (w.screen ? w.screen.width + 'x' + w.screen.height : '')
                }
            });

            if (w.navigator && typeof w.navigator.sendBeacon === 'function') {
                var blob = new Blob([body], { type: 'application/json' });
                if (w.navigator.sendBeacon(ENDPOINT, blob)) return;
            }

            // Fallback when sendBeacon is unavailable or refused the payload.
            if (typeof w.fetch === 'function') {
                w.fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: body,
                    keepalive: true,
                    mode: 'no-cors'
                }).catch(function () {});
            }
        } catch (e) {}
    }

    w.TDOAnalytics = {
        funnelId:    FUNNEL_ID,
        sessionId:   sessionId,
        testMode:    testMode,
        track:       track,
        trackBeacon: trackBeacon
    };

    // Fire the one-time test-mode marker once umami is ready (it's deferred, so it
    // has loaded by DOMContentLoaded). Every other event already carries test_mode.
    if (newlyDetectedTest) {
        if (w.document.readyState === 'loading') {
            w.document.addEventListener('DOMContentLoaded', function () {
                track('test_mode_detected', {});
            });
        } else {
            track('test_mode_detected', {});
        }
    }
})(window);
