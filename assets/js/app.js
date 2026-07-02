(function () {
    'use strict';

    var form = document.getElementById('qualify-form');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
    var total = steps.length;
    var current = 0;

    var stepLabel = document.getElementById('step-label');
    var fill = document.getElementById('progress-fill');
    var backBtn = document.getElementById('btn-back');
    var primaryBtn = document.getElementById('btn-primary');

    // --- Funnel analytics ---------------------------------------------------
    // One readable slug per step, baked into the event name so each step is a
    // distinct dashboard event. Keyed by 1-based step number.
    var STAGE_SLUG = {
        1: 'debt_amount',
        2: 'payment_status',
        3: 'address',
        4: 'dob',
        5: 'name_consent',
        6: 'email',
        7: 'phone_verify'
    };
    var A = window.TDOAnalytics;

    var stepStart = 0;        // performance.now() when the current step mounted
    var funnelDone = false;   // true once the form is submitted — suppresses abandon
    var abandoned = false;    // true once abandon fired for the current active stretch

    function nowMs() { try { return performance.now(); } catch (e) { return 0; } }
    function stepNo() { return current + 1; }
    function stageSlug() { return STAGE_SLUG[stepNo()] || ('step_' + stepNo()); }
    function durationMs() { return Math.round(nowMs() - stepStart); }

    // Fired when a step mounts. Starts the time-on-step clock and re-arms abandon.
    function trackView() {
        stepStart = nowMs();
        abandoned = false;
        if (!A) return;
        A.track('event_view_' + stageSlug(), { step: stepNo(), stage: stageSlug() });
    }

    // Fired on a successful advance OUT of a step (parameterized by the step left).
    function trackFillup(leftStep, leftStage, duration) {
        if (!A) return;
        A.track('event_fillup_' + leftStage, { step: leftStep, stage: leftStage, duration_ms: duration });
    }

    // Fired via beacon during unload/navigation, before submit, at most once per
    // active stretch. Re-armed by trackView() / a resume.
    function trackAbandon(reason) {
        if (funnelDone || abandoned) return;
        abandoned = true;
        if (!A) return;
        A.trackBeacon('event_abandon_' + stageSlug(), {
            step: stepNo(), stage: stageSlug(), reason: reason, duration_ms: durationMs()
        });
    }

    // Fired when the tab returns to visible after an abandon; re-arms the detector
    // so a benign tab-switch nets out (a lone abandon = a true drop-off).
    function trackResume() {
        if (funnelDone || !abandoned) return;
        abandoned = false;
        if (!A) return;
        A.track('event_resume_' + stageSlug(), { step: stepNo(), stage: stageSlug() });
    }

    function trackSubmitSuccess() {
        if (!A) return;
        var debt = (document.getElementById('debt_amount') || {}).value || '';
        var pay = (document.getElementById('payment_status') || {}).value || '';
        // Fires during navigation away to submit.php — use the unload-safe transport.
        A.trackBeacon('event_submit_success', {
            step: stepNo(),
            stage: stageSlug(),
            duration_ms: durationMs(),
            value: debt,
            debt_amount: debt,
            payment_status: pay
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            trackAbandon('visibility_hidden');
        } else if (document.visibilityState === 'visible') {
            trackResume();
        }
    });
    window.addEventListener('pagehide', function () { trackAbandon('pagehide'); });

    function primaryLabel(index) {
        if (index === 0) return 'Continue';
        if (index === total - 1) return 'Submit';
        return 'Next';
    }

    function render() {
        steps.forEach(function (step, i) {
            step.classList.toggle('is-active', i === current);
        });
        var pct = Math.round(((current + 1) / total) * 100);
        stepLabel.textContent = 'Step ' + (current + 1) + ' of ' + total;
        fill.style.width = pct + '%';
        backBtn.hidden = current === 0;
        primaryBtn.textContent = primaryLabel(current);

        var firstField = steps[current].querySelector('input, select');
        if (firstField) { try { firstField.focus(); } catch (e) {} }

        // The authorization disclosure stays visible, but reading it is no longer a
        // scroll-gated requirement: mark it read on mount so the consent is captured
        // by the affirmative Next/Submit click without forcing the user to scroll.
        var box = steps[current].querySelector('[data-disclosure-scroll]');
        if (box) { markRead(box); }
        applyGate();

        trackView();
    }

    // --- Button gates ---
    function isGateSatisfied(step) {
        // Step 5: disclosure must be fully read.
        var gate = step.querySelector('[data-disclosure]');
        if (gate && !gate.classList.contains('is-read')) return false;

        // Verification step: phone OTP must be verified. Contact consent is captured
        // via the disclosure + submit action, so there's no checkbox to gate on.
        var verified = step.querySelector('#phone_verified');
        if (verified && verified.value !== '1') return false;
        return true;
    }

    function applyGate() {
        primaryBtn.disabled = !isGateSatisfied(steps[current]);
    }

    function markRead(box) {
        var gate = box.closest('[data-disclosure]');
        if (!gate || gate.classList.contains('is-read')) return;
        gate.classList.add('is-read');
        var hint = gate.querySelector('[data-disclosure-hint]');
        if (hint) hint.hidden = true;
        var consent = gate.parentNode.querySelector('#credit_consent');
        if (consent) consent.value = '1';
        applyGate();
    }

    form.querySelectorAll('[data-disclosure-scroll]').forEach(function (box) {
        box.addEventListener('scroll', function () {
            if (box.scrollTop + box.clientHeight >= box.scrollHeight - 4) { markRead(box); }
        });
    });

    // Re-evaluate the button gate when fields change (e.g. consent checkbox).
    form.addEventListener('change', applyGate);

    // Selectable choice buttons: set hidden input, highlight, and advance
    form.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-choice-group] .option--choice');
        if (!btn) return;
        var group = btn.closest('[data-choice-group]');
        var input = document.getElementById(group.getAttribute('data-choice-for'));
        if (input) input.value = btn.getAttribute('data-value');
        group.querySelectorAll('.option--choice').forEach(function (opt) {
            opt.classList.toggle('is-selected', opt === btn);
        });
        clearErrors(steps[current]);
        if (current < total - 1) {
            trackFillup(stepNo(), stageSlug(), durationMs());
            current++;
            render();
        }
    });

    function clearErrors(step) {
        step.querySelectorAll('[data-error]').forEach(function (el) {
            el.classList.remove('is-shown');
        });
    }

    function showError(step, message) {
        var box = step.querySelector('[data-error]');
        if (box) {
            if (message) box.textContent = message;
            box.classList.add('is-shown');
        }
    }

    function validateStep(step) {
        clearErrors(step);

        var radios = step.querySelectorAll('input[type="radio"][data-required]');
        if (radios.length) {
            var name = radios[0].name;
            if (!step.querySelector('input[name="' + name + '"]:checked')) {
                showError(step);
                return false;
            }
        }

        var checks = step.querySelectorAll('input[type="checkbox"][data-required]');
        for (var c = 0; c < checks.length; c++) {
            if (!checks[c].checked) { showError(step); return false; }
        }

        var fields = step.querySelectorAll('input[data-required]:not([type="radio"]):not([type="checkbox"]), select[data-required]');
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            var val = (f.value || '').trim();
            if (!val) { showError(step); f.focus(); return false; }

            if (f.hasAttribute('data-pattern')) {
                var re = new RegExp(f.getAttribute('data-pattern'));
                if (!re.test(val)) {
                    showError(step, f.getAttribute('data-error-text') || 'Please check this field.');
                    f.focus();
                    return false;
                }
            }

            if (f.hasAttribute('data-adult')) {
                var dob = new Date(val);
                var min = new Date();
                min.setFullYear(min.getFullYear() - 18);
                if (isNaN(dob.getTime()) || dob > min) {
                    showError(step);
                    f.focus();
                    return false;
                }
            }
        }
        return true;
    }

    primaryBtn.addEventListener('click', function () {
        if (!validateStep(steps[current])) return;
        if (current < total - 1) {
            trackFillup(stepNo(), stageSlug(), durationMs());
            current++;
            render();
        } else {
            // Final step: funnel completion. Stamp it before navigating away, and
            // mark the funnel done so the unload handlers don't log a false abandon.
            funnelDone = true;
            trackSubmitSuccess();
            // Show progress and lock the button to prevent double-submits.
            primaryBtn.disabled = true;
            primaryBtn.textContent = 'Submitting…';
            form.submit();
        }
    });

    backBtn.addEventListener('click', function () {
        if (current > 0) { current--; render(); }
    });

    // --- Phone verification via Firebase Phone Auth (invisible reCAPTCHA) ---
    (function initPhoneAuth() {
        var sendBtn = document.getElementById('send-code');
        if (!sendBtn) return;

        var phoneInput = document.getElementById('phone');
        var codeField = document.getElementById('code-field');
        var codeInput = document.getElementById('verify_code');
        var verifyBtn = document.getElementById('verify-code');
        var note = document.getElementById('code-note');
        var verifiedFlag = document.getElementById('phone_verified');
        var tokenField = document.getElementById('firebase_token');

        var cfg = (window.APP_CONFIG && window.APP_CONFIG.firebase) || {};
        var firebaseReady = !!(cfg.apiKey && window.firebase);
        var confirmationResult = null;
        var recaptcha = null;

        function setNote(msg, ok) {
            note.textContent = msg;
            note.style.color = ok ? 'var(--green)' : 'var(--muted)';
        }

        // Normalise a US number to E.164 (+1XXXXXXXXXX); pass through other intl forms.
        function toE164(raw) {
            var trimmed = (raw || '').trim();
            var digits = trimmed.replace(/\D/g, '');
            if (trimmed.charAt(0) === '+') return '+' + digits;
            if (digits.length === 11 && digits.charAt(0) === '1') return '+' + digits;
            if (digits.length === 10) return '+1' + digits;
            return digits ? '+' + digits : '';
        }

        function markVerified(token) {
            verifiedFlag.value = '1';
            tokenField.value = token || '';
            codeInput.disabled = true;
            verifyBtn.disabled = true;
            sendBtn.disabled = true;
            setNote('✓ Phone number verified.', true);
            applyGate(); // may enable Submit if consent is also ticked
        }

        if (firebaseReady && !firebase.apps.length) {
            firebase.initializeApp({
                apiKey: cfg.apiKey,
                authDomain: cfg.authDomain,
                projectId: cfg.projectId,
                appId: cfg.appId,
                messagingSenderId: cfg.messagingSenderId
            });
        }

        function getRecaptcha() {
            // Created lazily on first send, when step 7 is visible.
            if (!recaptcha) {
                recaptcha = new firebase.auth.RecaptchaVerifier('recaptcha-container', { size: 'invisible' });
            }
            return recaptcha;
        }

        sendBtn.addEventListener('click', function () {
            var phone = (phoneInput.value || '').trim();
            if (!phone) { showError(steps[current], 'Enter your phone number first.'); return; }

            if (!firebaseReady) {
                // Dev fallback when Firebase isn't configured: no SMS, stub verification.
                codeField.hidden = false;
                setNote('Verification is not configured. Enter any 6-digit code to continue.');
                sendBtn.textContent = 'Resend Code';
                codeInput.focus();
                return;
            }

            var e164 = toE164(phone);
            sendBtn.disabled = true;
            setNote('Sending verification code…');
            firebase.auth().signInWithPhoneNumber(e164, getRecaptcha())
                .then(function (result) {
                    confirmationResult = result;
                    codeField.hidden = false;
                    setNote('A 6-digit code was sent to ' + e164 + '.');
                    sendBtn.textContent = 'Resend Code';
                    sendBtn.disabled = false;
                    codeInput.focus();
                })
                .catch(function (err) {
                    sendBtn.disabled = false;
                    setNote('Could not send code: ' + ((err && err.message) || 'please try again.'));
                    if (recaptcha && recaptcha.clear) { try { recaptcha.clear(); } catch (e) {} recaptcha = null; }
                });
        });

        verifyBtn.addEventListener('click', function () {
            var code = (codeInput.value || '').trim();
            if (!/^\d{4,8}$/.test(code)) { setNote('Enter the code you received.'); return; }

            if (!firebaseReady) {
                markVerified(''); // dev fallback
                return;
            }
            if (!confirmationResult) { setNote('Please request a code first.'); return; }

            verifyBtn.disabled = true;
            setNote('Verifying…');
            confirmationResult.confirm(code)
                .then(function (cred) { return cred.user.getIdToken(); })
                .then(function (token) { markVerified(token); })
                .catch(function () {
                    verifyBtn.disabled = false;
                    setNote('Invalid or expired code. Please try again.');
                });
        });
    })();

    // --- Date of birth: Flatpickr calendar (keeps Y-m-d in the real field) ---
    function initDobPicker() {
        var dob = document.getElementById('dob');
        if (!dob || typeof flatpickr === 'undefined') return;
        var max = new Date();
        max.setFullYear(max.getFullYear() - 18);   // must be 18+
        var min = new Date();
        min.setFullYear(min.getFullYear() - 100);   // bounds the year field
        flatpickr(dob, {
            dateFormat: 'Y-m-d',            // value submitted to the server
            altInput: true,                 // friendly display, hidden real field
            altFormat: 'F j, Y',
            maxDate: max,
            minDate: min,
            monthSelectorType: 'dropdown',  // pick the month from a dropdown, not arrows
            allowInput: true,               // users can also just type the date
            disableMobile: true,            // use Flatpickr UI on mobile too
            onOpen: function (selectedDates, dateStr, inst) {
                // Open near a typical birth year (~40) so users aren't stranded at the
                // 18-years-ago edge and clicking back through decades of months.
                if (!selectedDates.length) {
                    inst.jumpToDate(new Date(max.getFullYear() - 22, 0, 1));
                }
            }
        });
    }

    // --- Address: Google Places Autocomplete ---
    function fillComponent(components, type, useShort) {
        for (var i = 0; i < components.length; i++) {
            if (components[i].types.indexOf(type) !== -1) {
                return useShort ? components[i].short_name : components[i].long_name;
            }
        }
        return '';
    }

    function initAddressAutocomplete() {
        var addr = document.querySelector('[data-address-autocomplete]');
        if (!addr || !window.google || !google.maps || !google.maps.places) return;

        var ac = new google.maps.places.Autocomplete(addr, {
            types: ['address'],
            componentRestrictions: { country: ['us'] },
            fields: ['address_components']
        });

        ac.addListener('place_changed', function () {
            var place = ac.getPlace();
            var c = place && place.address_components;
            if (!c) return;

            var streetNumber = fillComponent(c, 'street_number');
            var route = fillComponent(c, 'route');
            addr.value = (streetNumber + ' ' + route).trim();

            var city = document.getElementById('city');
            var state = document.getElementById('state');
            var zip = document.getElementById('zip');
            if (city) city.value = fillComponent(c, 'locality') ||
                fillComponent(c, 'sublocality') || fillComponent(c, 'postal_town');
            if (state) state.value = fillComponent(c, 'administrative_area_level_1', true);
            if (zip) zip.value = fillComponent(c, 'postal_code');

            clearErrors(steps[current]);
        });

        // Prevent the Enter-to-advance handler from firing while a suggestion is open
        addr.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && document.querySelector('.pac-container:not([style*="display: none"])')) {
                ev.stopPropagation();
            }
        });
    }

    function loadGoogleMaps() {
        var key = (window.APP_CONFIG && window.APP_CONFIG.gmapsKey) || '';
        if (!key) return; // no key configured — field stays a plain input
        if (window.google && window.google.maps && window.google.maps.places) {
            initAddressAutocomplete();
            return;
        }
        window.__initGmaps = initAddressAutocomplete;
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
            '&libraries=places&callback=__initGmaps&loading=async';
        s.async = true;
        document.head.appendChild(s);
    }

    // --- Lead certification: TrustedForm + Jornaya (LeadiD) ---
    function loadCompliance() {
        var cfg = window.APP_CONFIG || {};

        function inject(src) {
            var s = document.createElement('script');
            s.type = 'text/javascript';
            s.async = true;
            s.src = src;
            (document.body || document.head).appendChild(s);
        }

        // Best-effort client IP into hid_ip_address (server also records REMOTE_ADDR).
        var ipField = document.getElementById('hid_ip_address');
        if (ipField && !ipField.value) {
            try {
                fetch('https://api.ipify.org?format=json')
                    .then(function (r) { return r.json(); })
                    .then(function (d) { if (d && d.ip) ipField.value = d.ip; })
                    .catch(function () {});
            } catch (e) {}
        }

        // Analytics session id into a hidden field, so the server can carry it onto the
        // thank-you/offerwall URL and match CallGrid call reports back to this visit.
        var sidField = document.getElementById('session_id');
        if (sidField && !sidField.value && A && A.sessionId) {
            try { sidField.value = A.sessionId(); } catch (e) {}
        }

        var https = document.location.protocol === 'https:';

        // TrustedForm — populates xxTrustedFormCertUrl / xxTrustedFormPingUrl by name.
        if (cfg.trustedformEnabled) {
            inject((https ? 'https' : 'http') +
                '://api.trustedform.com/trustedform.js?field=xxTrustedFormCertUrl' +
                '&ping_field=xxTrustedFormPingUrl&l=' + Date.now() + Math.random());
        }

        // Jornaya / LeadiD — populates #leadid_token (name="universal_leadid").
        if (cfg.jornayaCampaignId) {
            var lj = document.createElement('script');
            lj.id = 'LeadiDscript_campaign';
            lj.type = 'text/javascript';
            lj.async = true;
            lj.src = (https ? 'https://' : 'http://') +
                'create.lidstatic.com/campaign/' + encodeURIComponent(cfg.jornayaCampaignId) +
                '.js?snippet_version=2';
            (document.body || document.head).appendChild(lj);
        }

        // Everflow — register the visit, then read attribution from EF's
        // first-party cookie into hid_ef_tid (and hid_affid for organic visits).
        var ef = cfg.everflow || {};
        var efField = document.getElementById('hid_ef_tid');
        var affidField = document.getElementById('hid_affid');
        if (ef.domain && efField && !efField.value) {
            var offer = String(ef.offerId || '');
            var setEfTid = function (tid) { if (tid && efField && !efField.value) efField.value = tid; };

            // Read a query-string param — prefer EF's own helper once loaded.
            var getParam = function (name) {
                if (window.EF && typeof EF.urlParameter === 'function') {
                    try { return EF.urlParameter(name) || ''; } catch (e) {}
                }
                try { return new URLSearchParams(location.search).get(name) || ''; } catch (e) { return ''; }
            };

            // Locate EF's click cookie for this offer. Paid/affiliate visits use
            // ef_tid_c_a_<offer>; organic visits use ef_tid_c_o_<offer>. With no
            // configured offer we match the prefix; paid wins over organic.
            var readEfCookie = function () {
                var jar = document.cookie ? document.cookie.split(/;\s*/) : [];
                var paid = null, organic = null;
                for (var i = 0; i < jar.length; i++) {
                    var eq = jar[i].indexOf('=');
                    if (eq < 0) continue;
                    var k = jar[i].slice(0, eq);
                    var v = function () { return decodeURIComponent(jar[i].slice(eq + 1)); };
                    if (paid === null && (offer ? k === 'ef_tid_c_a_' + offer : k.indexOf('ef_tid_c_a_') === 0)) paid = v();
                    else if (organic === null && (offer ? k === 'ef_tid_c_o_' + offer : k.indexOf('ef_tid_c_o_') === 0)) organic = v();
                }
                if (paid) return { organic: false, value: paid };
                if (organic) return { organic: true, value: organic };
                return null;
            };

            // Register the visit so EF drops/refreshes its first-party cookie.
            var registerClick = function () {
                if (!window.EF || typeof EF.click !== 'function') return;
                try {
                    EF.click({
                        offer_id: getParam('oid') || offer,
                        affiliate_id: getParam('affid'),
                        source_id: getParam('source_id'),
                        sub1: getParam('sub1'), sub2: getParam('sub2'), sub3: getParam('sub3'),
                        sub4: getParam('sub4'), sub5: getParam('sub5'),
                        uid: getParam('uid'),
                        transaction_id: getParam('transaction_id')
                    });
                } catch (e) {}
            };

            // Poll the cookie until a tid stabilises (same value twice — EF may
            // rewrite it mid-flight), then commit. The last pipe segment is always
            // the transaction id; for organic visits the first segment is the
            // EF-assigned affiliate id, harvested into hid_affid (numeric-guarded)
            // so the lead still attributes to an EF source. Caps at ~20s.
            var lastTid = null, attempts = 0, poll;
            var tick = function () {
                if (efField.value || attempts++ > 40) { clearInterval(poll); return; }
                var c = readEfCookie();
                if (!c || !c.value) return;
                var parts = c.value.split('|');
                var tid = parts[parts.length - 1];
                if (!tid) return;
                if (tid !== lastTid) { lastTid = tid; return; } // debounce: confirm next tick
                setEfTid(tid);
                if (c.organic && affidField && !affidField.value && /^\d+$/.test(parts[0])) {
                    affidField.value = parts[0];
                }
                clearInterval(poll);
            };

            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://' + ef.domain.replace(/^https?:\/\//, '').replace(/\/$/, '') +
                '/scripts/sdk/everflow.js';
            s.onload = function () {
                registerClick();
                poll = setInterval(tick, 500);
                tick();
            };
            document.head.appendChild(s);
        }
    }

    initDobPicker();
    loadGoogleMaps();
    loadCompliance();

    // Enter key advances instead of submitting mid-flow
    form.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA') {
            ev.preventDefault();
            primaryBtn.click();
        }
    });

    render();
})();
