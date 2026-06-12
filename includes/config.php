<?php
/**
 * Global configuration and helpers.
 */

declare(strict_types=1);

session_start();

const SITE_NAME = 'Top Debt Options';
const BRAND_SHORT = 'TOP DEBT';
const BRAND_TAGLINE = 'Personalized Debt Relief';
const SITE_TAGLINE = 'See If You Qualify For Debt Relief';
const CONTACT_EMAIL = 'support@topdebtoptions.com';
const SUPPORT_PHONE = '1-833-491-8671';
const FILE_HOLD_MINUTES = 5; // countdown shown on the thank-you page

/**
 * Load environment variables from .env then .env.local (local overrides base).
 * Lightweight, no external dependency.
 */
function load_env(string $dir): void
{
    foreach (['.env', '.env.local'] as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Strip matching surrounding quotes, if present.
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[strlen($value) - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if ($name !== '') {
                $_ENV[$name] = $value;
                // Some managed hosts disable putenv() via disable_functions; $_ENV is enough.
                if (function_exists('putenv')) {
                    putenv($name . '=' . $value);
                }
            }
        }
    }
}

/**
 * Read an environment variable, with an optional default.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? ($_SERVER[$key] ?? (function_exists('getenv') ? getenv($key) : false));
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

load_env(dirname(__DIR__));

// Google Maps JavaScript API key (Places library enabled) for address autocomplete.
// Set GOOGLE_MAPS_API_KEY in .env or .env.local. Empty disables autocomplete (field still works).
define('GOOGLE_MAPS_API_KEY', (string) env('GOOGLE_MAPS_API_KEY', ''));

// Firebase web config for phone-number verification (Phone Auth).
// Set these in .env / .env.local. If FIREBASE_PROJECT_ID is empty, server-side
// verification is skipped (dev), and the client falls back to a no-SMS stub.
define('FIREBASE_API_KEY', (string) env('FIREBASE_API_KEY', ''));
define('FIREBASE_AUTH_DOMAIN', (string) env('FIREBASE_AUTH_DOMAIN', ''));
define('FIREBASE_PROJECT_ID', (string) env('FIREBASE_PROJECT_ID', ''));
define('FIREBASE_APP_ID', (string) env('FIREBASE_APP_ID', ''));
define('FIREBASE_MESSAGING_SENDER_ID', (string) env('FIREBASE_MESSAGING_SENDER_ID', ''));

// LeadProsper lead ingestion (direct-post API). Set these in .env / .env.local.
// If LP_CAMPAIGN_ID or LP_KEY is empty, the outbound post is skipped and the lead
// is still stored locally.
define('LP_CAMPAIGN_ID', (string) env('LP_CAMPAIGN_ID', ''));
define('LP_SUPPLIER_ID', (string) env('LP_SUPPLIER_ID', ''));
define('LP_KEY', (string) env('LP_KEY', ''));
define('LP_ENDPOINT', 'https://api.leadprosper.io/direct_post');
// When truthy, leads are posted with lp_action=test so LeadProsper treats them as
// test submissions (not billed, not delivered). Flip to false for live traffic.
define('LP_TEST_MODE', filter_var(env('LP_TEST_MODE', 'true'), FILTER_VALIDATE_BOOLEAN));

// Lead-certification scripts. TrustedForm needs no account key (cert URL is created
// per page load). Jornaya requires a campaign GUID; empty disables the Jornaya script.
define('TRUSTEDFORM_ENABLED', filter_var(env('TRUSTEDFORM_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN));
define('JORNAYA_CAMPAIGN_ID', (string) env('JORNAYA_CAMPAIGN_ID', ''));

// Everflow client-side tracking. The SDK is loaded from your account's tracking
// domain and registers the visit via EF.click(); the JS then reads EF's first-party
// cookie for this offer, writing the transaction id into hid_ef_tid (and, for organic
// visits, the EF-assigned affiliate id into hid_affid). Empty domain disables the SDK.
define('EVERFLOW_DOMAIN', (string) env('EVERFLOW_DOMAIN', ''));
define('EVERFLOW_OFFER_ID', (string) env('EVERFLOW_OFFER_ID', ''));

// TCPA consent language forwarded to LeadProsper (must match the on-page checkbox).
const TCPA_TEXT = 'By checking this box, I agree to be contacted by ' . SITE_NAME
    . ' and its partners at the number provided, including by automated dialing and'
    . ' prerecorded messages, even if my number is on a Do Not Call list. Consent is'
    . ' not a condition of purchase. Message and data rates may apply.';

// Attribution params captured from the landing URL (first-touch) and forwarded to
// LeadProsper. Kept in sync with the LeadProsper field schema.
const TRACKING_PARAMS = [
    'affid', 'oid', 'source_id', 'ef_transaction_id', 'event_id',
    'gclid', 'fbclid', 'gbraid', 'fbp', 'softpull_returned',
    'utm_campaign', 'utm_source', 'utm_medium', 'utm_term', 'utm_content',
    'utm_creative', 'utm_placement', 'utm_adgroup', 'utm_matchtype',
    'lp_subid1', 'lp_subid2', 'lp_subid3', 'lp_subid4', 'lp_subid5',
    'adv1', 'adv2', 'adv3', 'adv4', 'adv5',
];

// Alternate query-param spellings that map to a canonical tracking key, so traffic
// sources using a different name (e.g. Everflow's {transaction_id} arriving as
// _ef_transaction_id) still populate the same field. The canonical key wins if present.
const TRACKING_ALIASES = [
    'ef_transaction_id' => ['_ef_transaction_id', 'transaction_id', 'tid'],
];

// US states (+ DC) for the address step and LeadProsper's 2-letter `state` field.
const US_STATES = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
    'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
    'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
    'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
    'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
    'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
    'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
    'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
    'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
];

/**
 * Convert a debt-range bucket (e.g. "10000-14999" or "100000+") to a single
 * representative dollar amount (range midpoint) for LeadProsper's numeric fields.
 */
function debt_bucket_amount(string $bucket): int
{
    if ($bucket === '') {
        return 0;
    }
    if (substr($bucket, -1) === '+') {
        return (int) rtrim($bucket, '+');
    }
    $parts = explode('-', $bucket);
    $low = (int) ($parts[0] ?? 0);
    $high = (int) ($parts[1] ?? $low);
    return (int) round(($low + $high) / 2);
}

/**
 * Capture attribution params from the current request into the session on first
 * touch (existing values win, preserving the original landing attribution). Also
 * records the first landing URL. Safe to call on every page load.
 */
function capture_tracking(): void
{
    if (!isset($_SESSION['tracking']) || !is_array($_SESSION['tracking'])) {
        $_SESSION['tracking'] = [];
    }
    foreach (TRACKING_PARAMS as $key) {
        if (!isset($_SESSION['tracking'][$key]) && isset($_GET[$key]) && $_GET[$key] !== '') {
            $_SESSION['tracking'][$key] = substr(trim((string) $_GET[$key]), 0, 255);
        }
    }
    // Fill canonical keys from alternate param spellings (first non-empty alias wins).
    foreach (TRACKING_ALIASES as $canonical => $aliases) {
        if (!empty($_SESSION['tracking'][$canonical])) {
            continue;
        }
        foreach ($aliases as $alias) {
            if (isset($_GET[$alias]) && $_GET[$alias] !== '') {
                $_SESSION['tracking'][$canonical] = substr(trim((string) $_GET[$alias]), 0, 255);
                break;
            }
        }
    }
    if (empty($_SESSION['tracking']['landing_page_url'])) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $_SESSION['tracking']['landing_page_url'] = $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '');
        }
    }
}

/**
 * Escape a string for safe HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate (or reuse) a per-session CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Validate a submitted CSRF token.
 */
function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

// Record landing attribution as early as possible, on whichever page is hit first.
capture_tracking();
