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
    }

    // Highlight selected radio options
    form.addEventListener('change', function (ev) {
        if (ev.target.type === 'radio') {
            var group = ev.target.closest('[data-radio-group]');
            if (group) {
                group.querySelectorAll('.option').forEach(function (opt) {
                    opt.classList.toggle('is-selected', opt.querySelector('input').checked);
                });
            }
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

    // Enter key advances instead of submitting mid-flow
    form.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA') {
            ev.preventDefault();
            primaryBtn.click();
        }
    });

    render();
})();
