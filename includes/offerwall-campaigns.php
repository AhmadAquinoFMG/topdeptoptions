<?php

/**
 * Decline-offerwall campaign config.
 *
 * Shown on /offerwall.php to applicants who don't qualify for the main debt-relief
 * program — a wall of alternative offers they can still act on.
 *
 * Link routing by affid (resolved in offerwall.php from the `affid` URL param,
 * falling back to the first-touch affid captured in the session):
 *   - affid === '995'  -> Koji link
 *   - everything else (incl. '1071' or missing affid) -> Organic link (default fallback)
 * Resolution happens via offerwall_resolve_cta() below.
 *
 * Each campaign's `ctaLinks` map: { '995': <Koji>, default: <Organic / fallback> }.
 *
 * Logos live in /assets/img/offers/<id>/<id>.webp.
 *
 * TODO (placeholders): Lexington Law and First Premier Lending were added
 * 2026-06-13 with placeholder description/benefits — refine the copy when
 * final creative lands.
 */
const OFFERWALL_CAMPAIGNS = [
    [
        'id'          => 'lending_for_bad_credit',
        'name'        => 'Lending For Bad Credit',
        'description' => 'Personal loans from $100 to $40,000 for all credit types.',
        'logo'        => '/assets/img/offers/lending_for_bad_credit/lending_for_bad_credit.webp',
        'category'    => 'Personal Loan',
        'icon'        => 'money',
        'accent'      => '#d97706',
        'benefits'    => [
            'Bad credit? You may still qualify',
            'Quick online request',
            'Funds as soon as the next business day',
        ],
        'ctaText'  => 'See If I Qualify',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/5Q9RM9/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/5Q9RM9/',
        ],
    ],
    [
        'id'          => 'experian',
        'name'        => 'Experian',
        'description' => 'Get your FICO Score for free and start improving your credit.',
        'logo'        => '/assets/img/offers/experian/experian.webp',
        'category'    => 'Credit Score',
        'icon'        => 'card',
        'accent'      => '#2563eb',
        'benefits'    => [
            'See what impacts your credit score',
            'Free FICO Score and monitoring',
            'Alerts included at no cost',
        ],
        'ctaText'  => 'View Options',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/27DQ3QC/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/27DQ3QC/',
        ],
    ],
    [
        'id'          => 'upstart',
        'name'        => 'Upstart',
        'description' => 'Most borrowers are approved instantly.',
        'logo'        => '/assets/img/offers/upstart/upstart.webp',
        'category'    => 'Quick Loan',
        'icon'        => 'bolt',
        'accent'      => '#7c3aed',
        'benefits'    => [
            'Verify your details in minutes',
            'Next-day funding',
            "Won't affect your credit score",
        ],
        'ctaText'  => 'Check My Rate',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/DMBKXN/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/DMBKXN/',
        ],
    ],
    [
        // TODO: placeholder copy — refine when final creative lands
        'id'          => 'lexington_law',
        'name'        => 'Lexington Law',
        'description' => 'Work toward repairing your credit with professional help.',
        'logo'        => '/assets/img/offers/lexington_law/lexington_law.webp',
        'category'    => 'Credit Repair',
        'icon'        => 'shield',
        'accent'      => '#4f46e5',
        'benefits'    => [
            'Challenge questionable items on your report',
            'Personalized credit repair plan',
            'Free credit assessment',
        ],
        'ctaText'  => 'View Options',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/2498FD4/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/2498FD4/',
        ],
    ],
    [
        // TODO: placeholder copy — refine when final creative lands
        'id'          => 'first_premier_lending',
        'name'        => 'First Premier Lending',
        'description' => 'Lending options designed to help you move forward.',
        'logo'        => '/assets/img/offers/first_premier_lending/first_premier_lending.webp',
        'category'    => 'Lending',
        'icon'        => 'bank',
        'accent'      => '#0d9488',
        'benefits'    => [
            'Quick online application',
            'Options for less-than-perfect credit',
            'Fast decision',
        ],
        'ctaText'  => 'Check My Rate',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/27JWPZ6/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/27JWPZ6/',
        ],
    ],
    [
        'id'          => 'usa_grants',
        'name'        => 'USA Grants',
        'description' => 'Explore grant money you may be eligible to claim.',
        'logo'        => '/assets/img/offers/usa_grants/usa_grants.webp',
        'category'    => 'Grant',
        'icon'        => 'award',
        'accent'      => '#e11d48',
        'benefits'    => [
            'See if you qualify for grant money',
            'Free to check eligibility',
            'Billions in grants available',
        ],
        'ctaText'  => 'View Options',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/7XDN21/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/7XDN21/',
        ],
        'sponsored' => true,
    ],
    [
        'id'          => 'usa_assistance_guide',
        'name'        => 'USA Assistance Guide',
        'description' => 'Find cash assistance programs available in your area.',
        'logo'        => '/assets/img/offers/usa_assistance_guide/usa_assistance_guide.webp',
        'category'    => 'Assistance',
        'icon'        => 'hand',
        'accent'      => '#059669',
        'benefits'    => [
            'Financial assistance for everyday expenses',
            'Free to check what you qualify for',
            'Funds may be available quickly',
        ],
        'ctaText'  => 'View Options',
        'ctaLinks' => [
            '995'     => 'https://www.f0cg2trk.com/2L5SCFN/7NG8BZ/',
            'default' => 'https://www.f0cg2trk.com/2PLFG38/7NG8BZ/',
        ],
        'sponsored' => true,
    ],
];

/**
 * Resolve a campaign's CTA link for the current affid.
 * affid '995' -> Koji link; anything else (incl. missing) -> default/Organic.
 */
function offerwall_resolve_cta(array $ctaLinks, string $affid): string
{
    return $ctaLinks[$affid] ?? $ctaLinks['default'];
}

/**
 * Intent-based inline SVG icon set (stroke-style, inherits currentColor) keyed by
 * the campaign `icon`. Falls back to a neutral tag icon for unknown keys.
 */
function offerwall_icon(string $key): string
{
    $icons = [
        // Personal loan / lending — banknote with center mark
        'money'  => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        // Credit score — credit card
        'card'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        // Quick loan — lightning bolt
        'bolt'   => '<path d="M13 2 3 14h7l-1 8 10-12h-7z"/>',
        // Credit repair — shield with check
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        // Lending / bank — columned building
        'bank'   => '<path d="M3 21h18M3 10h18M12 3l9 7H3zM5 10v11M9 10v11M15 10v11M19 10v11"/>',
        // Grant — award medal
        'award'  => '<circle cx="12" cy="8" r="6"/><path d="m8.5 13-1.5 8 5-3 5 3-1.5-8"/>',
        // Assistance — life buoy / support
        'hand'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="m4.9 4.9 4.6 4.6m5 5 4.6 4.6M19.1 4.9l-4.6 4.6m-5 5-4.6 4.6"/>',
    ];
    $path = $icons[$key] ?? '<path d="M20.6 3.4A2 2 0 0 0 19.2 3H12L3 12l9 9 9-9V4.8a2 2 0 0 0-.4-1.4z"/><circle cx="7.5" cy="7.5" r="1.5"/>';
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
