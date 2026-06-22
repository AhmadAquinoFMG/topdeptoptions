<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/offerwall-campaigns.php';

// Resolve affid: explicit URL param wins, else first-touch affid captured in session.
$affid = trim((string) ($_GET['affid'] ?? ($_SESSION['tracking']['affid'] ?? '')));

$first = trim((string) ($_SESSION['lead_offerwall']['first_name'] ?? ''));

$pageTitle = 'Options For You — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="funnel offerwall-page">
    <div class="container">
        <div class="offerwall__intro reveal">
            <span class="offerwall__eyebrow">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
                Vetted partner matches
            </span>
            <h1 class="offerwall__title">
                <?= $first !== '' ? e($first) . ', compare' : 'Compare' ?> Your Path to Debt Relief
            </h1>
            <p class="offerwall__sub">
                Based on your profile, we&rsquo;ve lined up these vetted partners side by side. Review the
                benefits of each, then choose what works for you &mdash; comparing is always free, and
                checking your options won&rsquo;t affect your credit score.
            </p>
        </div>

        <div class="offerwall">
            <?php foreach (OFFERWALL_CAMPAIGNS as $c): ?>
                <?php $href = offerwall_resolve_cta($c['ctaLinks'], $affid); ?>
                <article class="ow-card reveal" style="--accent: <?= e($c['accent'] ?? '#0a1330') ?>">
                    <div class="ow-card__body">
                        <div class="ow-card__brand">
                            <img class="ow-card__logo" src="<?= e($c['logo']) ?>" alt="<?= e($c['name']) ?> logo"
                                loading="lazy" onerror="this.style.display='none'">
                            <?php if (!empty($c['sponsored'])): ?>
                                <span class="ow-card__sponsored">Sponsored</span>
                            <?php endif; ?>
                        </div>

                        <div class="ow-card__main">
                            <?php if (!empty($c['category'])): ?>
                                <span class="ow-card__cat">
                                    <span class="ow-card__cat-icon" aria-hidden="true"><?= offerwall_icon($c['icon'] ?? '') ?></span>
                                    <?= e($c['category']) ?>
                                </span>
                            <?php endif; ?>
                            <h2 class="ow-card__name"><?= e($c['name']) ?></h2>
                            <p class="ow-card__desc"><?= e($c['description']) ?></p>
                        </div>

                        <div class="ow-card__benefits">
                            <p class="ow-card__benefits-head">Key Benefits</p>
                            <ul class="ow-card__benefits-list">
                                <?php foreach ($c['benefits'] as $b): ?>
                                    <li>
                                        <span class="ow-card__check" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6 9 17l-5-5" />
                                            </svg>
                                        </span>
                                        <span><?= e($b) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="ow-card__foot">
                        <span class="ow-card__note">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            1 minute to complete the form. See your results.
                        </span>
                        <a class="ow-card__cta" href="<?= e($href) ?>" target="_blank" rel="noopener sponsored nofollow">
                            <?= e($c['ctaText']) ?>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="offerwall__dots" id="ow-dots" role="tablist" aria-label="Choose an option"></div>

        <p class="secure-note">🔒 Your information is encrypted and secure. Top Debt Options may be compensated by the partners above.</p>
    </div>
</section>

<script>
    (function() {
        // Mobile carousel pagination. The track itself is a CSS scroll-snap slider;
        // this only adds the dot indicators + click-to-scroll (progressive enhancement).
        var track = document.querySelector('.offerwall');
        var dotsWrap = document.getElementById('ow-dots');
        if (!track || !dotsWrap) return;
        var cards = Array.prototype.slice.call(track.querySelectorAll('.ow-card'));
        if (cards.length < 2) return;

        var dots = cards.map(function(card, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'offerwall__dot';
            dot.setAttribute('role', 'tab');
            dot.setAttribute('aria-label', 'Go to option ' + (i + 1));
            dot.addEventListener('click', function() {
                card.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            });
            dotsWrap.appendChild(dot);
            return dot;
        });

        function syncActive() {
            var trackRect = track.getBoundingClientRect();
            var center = trackRect.left + trackRect.width / 2;
            var best = 0, bestDist = Infinity;
            cards.forEach(function(card, i) {
                var r = card.getBoundingClientRect();
                var dist = Math.abs((r.left + r.width / 2) - center);
                if (dist < bestDist) { bestDist = dist; best = i; }
            });
            dots.forEach(function(dot, i) {
                var active = i === best;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        var raf;
        track.addEventListener('scroll', function() {
            if (raf) cancelAnimationFrame(raf);
            raf = requestAnimationFrame(syncActive);
        }, { passive: true });
        window.addEventListener('resize', syncActive);
        syncActive();
    })();

    // --- Analytics: decline offerwall (post-funnel page) --------------------
    // Deferred to DOMContentLoaded so the (deferred) Umami script is ready.
    document.addEventListener('DOMContentLoaded', function() {
        var A = window.TDOAnalytics;
        if (!A) return;
        var PAGE = 'decline_offerwall';

        function offerOf(card) {
            var name = card && card.querySelector('.ow-card__name');
            return name ? (name.textContent || '').trim() : '';
        }

        // Page landing.
        A.track(PAGE + '_view', {});

        var cards = Array.prototype.slice.call(document.querySelectorAll('.ow-card'));

        // Offer/card scrolls into view — fire once per card.
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(en) {
                    if (!en.isIntersecting) return;
                    var card = en.target;
                    io.unobserve(card);
                    var i = cards.indexOf(card);
                    A.track(PAGE + '_offer_impression', { offer: offerOf(card), position: i + 1 });
                });
            }, { threshold: 0.5 });
            cards.forEach(function(card) { io.observe(card); });
        }

        // Outbound CTA click — beacon so it survives the navigation/new tab.
        document.addEventListener('click', function(ev) {
            var cta = ev.target.closest && ev.target.closest('.ow-card__cta');
            if (!cta) return;
            var card = cta.closest('.ow-card');
            A.trackBeacon(PAGE + '_offer_click', {
                offer: offerOf(card),
                position: cards.indexOf(card) + 1,
                href: cta.getAttribute('href') || ''
            });
        });

        // Any disclosure/accordion expanded (none on the wall today; wired for
        // when one is added — <details> or [data-disclosure]).
        document.addEventListener('toggle', function(ev) {
            var d = ev.target;
            if (d && d.tagName === 'DETAILS' && d.open) {
                A.track(PAGE + '_why_expand', { offer: offerOf(d.closest('.ow-card')) });
            }
        }, true);
    });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>