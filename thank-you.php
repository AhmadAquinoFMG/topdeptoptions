<?php
require __DIR__ . '/includes/config.php';

// One-time flash set by submit.php after a successful submission.
$lead = $_SESSION['lead_success'] ?? null;
unset($_SESSION['lead_success']);

$first   = $lead['first_name'] ?? '';
$telHref = '+' . preg_replace('/\D/', '', SUPPORT_PHONE);

/** Rough debt midpoint + "save up to" figure from the selected range. */
function tdo_savings_estimate(?string $bucket): array
{
    if (!$bucket) {
        return [0, 0];
    }
    if ($bucket === '100000+') {
        $mid = 120000;
    } else {
        $parts = explode('-', $bucket);
        $low   = (int) ($parts[0] ?? 0);
        $high  = (int) ($parts[1] ?? $low);
        $mid   = (int) (round((($low + $high) / 2) / 1000) * 1000); // nearest $1k
    }
    $savings = (int) (round(($mid * 0.5) / 100) * 100); // up to ~50%
    return [$mid, $savings];
}

[$debtMid, $savings] = tdo_savings_estimate($lead['debt_amount'] ?? null);
$specialists = 1;

$pageTitle = 'You\'re Pre-Qualified — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="funnel prequal-page">
    <div class="container">
        <div class="prequal">
            <div class="prequal__hero reveal">
                <div class="prequal__check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                </div>
                <h1 class="prequal__title">You&rsquo;re Pre-Qualified for<br>a Debt Relief Program</h1>
                <p class="prequal__sub">
                    <?= $first !== '' ? e($first) . ', based' : 'Based' ?> on your profile, you could
                    reduce your debt and lower your monthly payments.
                </p>
            </div>

            <?php if ($savings > 0): ?>
                <div class="savings reveal">
                    <div class="savings__item">
                        <span class="savings__label">Your estimated debt</span>
                        <span class="savings__value">$<?= number_format($debtMid) ?></span>
                    </div>
                    <div class="savings__arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </div>
                    <div class="savings__item savings__item--hl">
                        <span class="savings__label">You could save up to</span>
                        <span class="savings__value savings__value--hl">$<?= number_format($savings) ?></span>
                    </div>
                </div>
                <p class="savings__note reveal">Based on a potential reduction of up to 50%. Your specialist will confirm your exact savings.</p>
            <?php endif; ?>

            <div class="prequal__assigned reveal">
                <span class="prequal__assigned-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="7" r="3.2" />
                        <path d="M3.5 20a5.5 5.5 0 0 1 11 0" />
                        <circle cx="18" cy="9" r="2.2" />
                        <path d="m18 4 .9 1.6 1.8.3-1.3 1.3.3 1.8L18 9.4l-1.6.9.3-1.8-1.3-1.3 1.8-.3z" />
                    </svg>
                </span>
                <div>
                    <h2 class="prequal__assigned-title">A Certified Debt Specialist Has Been Assigned to You</h2>
                    <p class="prequal__assigned-text">
                        Your specialist is reviewing your information and is ready to walk you
                        through your best options for becoming debt free.
                    </p>
                </div>
            </div>

            <hr class="prequal__divider">

            <div class="cta-card reveal">
                <span class="cta-card__badge"><span class="pulse-dot" aria-hidden="true"></span> Limited availability</span>
                <h2 class="cta-card__title">Speak With Your Assigned Debt Specialist Now</h2>
                <p class="cta-card__sub">Your savings estimate is reserved, but availability is limited.</p>

                <p class="cta-card__live"><span class="live-dot" aria-hidden="true"></span> <span>A <strong>specialist</strong> is available right now!</span></p>

                <a class="cta-card__call" href="tel:<?= e($telHref) ?>">
                    <span class="cta-card__call-row">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2z" />
                        </svg>
                        CALL NOW
                    </span>
                    <span class="cta-card__call-num"><?= e(SUPPORT_PHONE) ?></span>
                </a>

                <ul class="cta-card__trust">
                    <li>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                        Takes less than 10 minutes
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="m8.5 12 2.5 2.5 4.5-5" />
                        </svg>
                        No obligation
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="11" width="16" height="9" rx="2" />
                            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                        </svg>
                        Free consultation
                    </li>
                </ul>

                <div class="hold" id="hold">
                    <span class="hold__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="13" r="8" />
                            <path d="M12 9v4l2.5 2.5M9 2h6" />
                        </svg>
                    </span>
                    <p class="hold__label">Your specialist is<br>holding your file for:</p>
                    <div class="hold__time" role="timer" aria-live="off">
                        <span class="hold__box"><strong id="cd-min">0<?= (int) FILE_HOLD_MINUTES ?></strong><small>MIN</small></span>
                        <span class="hold__colon">:</span>
                        <span class="hold__box"><strong id="cd-sec">00</strong><small>SEC</small></span>
                    </div>
                    <p class="hold__note">
                        After this time, you may need to re-qualify and wait for the next available specialist.
                    </p>
                </div>
            </div>

            <div class="prequal__stats reveal">
                <div class="stat"><strong>12,000+</strong><span>Clients helped</span></div>
                <div class="stat"><strong>4.9 / 5</strong><span>Average rating</span></div>
                <div class="stat"><strong>$50M+</strong><span>Debt resolved</span></div>
            </div>

            <div class="secure-panel reveal">
                <p class="secure-panel__head">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="11" width="16" height="9" rx="2" />
                        <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                    </svg>
                    Your information was submitted securely.
                </p>
                <div class="secure-panel__grid">
                    <div class="secure-panel__col">
                        <h3 class="secure-panel__title">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="5" y="3" width="14" height="18" rx="2" />
                                <path d="M9 7h6M9 11h6M9 15h4" />
                            </svg>
                            What&rsquo;s next?
                        </h3>
                        <p class="secure-panel__text">
                            Your assigned specialist will call you within 2 hours to discuss your
                            debt relief options.
                        </p>
                    </div>
                    <div class="secure-panel__col">
                        <h3 class="secure-panel__title">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                            Your data
                        </h3>
                        <p class="secure-panel__text">
                            Encrypted, protected, and never shared. CFPB &amp; FTC compliant.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    (function() {
        // --- Countdown ---
        var minEl = document.getElementById('cd-min');
        var secEl = document.getElementById('cd-sec');
        var hold = document.getElementById('hold');
        if (minEl && secEl) {
            var total = <?= (int) FILE_HOLD_MINUTES * 60 ?>;
            var timer;
            var pad = function(n) {
                return (n < 10 ? '0' : '') + n;
            };
            var tick = function() {
                if (total < 0) total = 0;
                minEl.textContent = pad(Math.floor(total / 60));
                secEl.textContent = pad(total % 60);
                if (hold) hold.classList.toggle('is-urgent', total <= 60);
                if (total > 0) {
                    total--;
                } else {
                    clearInterval(timer);
                }
            };
            tick();
            timer = setInterval(tick, 1000);
        }
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>