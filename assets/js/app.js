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

        // If a step gates the Next button behind a disclosure that fits without
        // scrolling, it's already fully visible — mark it read. Then sync the button.
        var box = steps[current].querySelector('[data-disclosure-scroll]');
        if (box && box.scrollHeight <= box.clientHeight + 4) { markRead(box); }
        applyGate();
    }

    // --- Disclosure gate: Next stays disabled until the text is read in full ---
    function isGateSatisfied(step) {
        var gate = step.querySelector('[data-disclosure]');
        return !gate || gate.classList.contains('is-read');
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
        if (current < total - 1) { current++; render(); }
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
            current++;
            render();
        } else {
            form.submit();
        }
    });

    backBtn.addEventListener('click', function () {
        if (current > 0) { current--; render(); }
    });

    // Simulated phone verification code
    var sendBtn = document.getElementById('send-code');
    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            var phone = document.getElementById('phone');
            if (!phone.value.trim()) {
                showError(steps[current], 'Enter your phone number first.');
                return;
            }
            document.getElementById('code-field').hidden = false;
            document.getElementById('code-note').textContent = 'A verification code has been sent to your phone.';
            sendBtn.textContent = 'Resend Code';
        });
    }

    // --- Date of birth: Flatpickr calendar (keeps Y-m-d in the real field) ---
    function initDobPicker() {
        var dob = document.getElementById('dob');
        if (!dob || typeof flatpickr === 'undefined') return;
        var max = new Date();
        max.setFullYear(max.getFullYear() - 18);
        flatpickr(dob, {
            dateFormat: 'Y-m-d',       // value submitted to the server
            altInput: true,            // friendly display, hidden real field
            altFormat: 'F j, Y',
            maxDate: max,              // cannot pick a date younger than 18
            allowInput: true,
            disableMobile: true        // use Flatpickr UI on mobile too
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
            var zip = document.getElementById('zip');
            if (city) city.value = fillComponent(c, 'locality') ||
                fillComponent(c, 'sublocality') || fillComponent(c, 'postal_town');
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

    initDobPicker();
    loadGoogleMaps();

    // Enter key advances instead of submitting mid-flow
    form.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA') {
            ev.preventDefault();
            primaryBtn.click();
        }
    });

    render();
})();
