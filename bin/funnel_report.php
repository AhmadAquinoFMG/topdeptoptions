<?php
/**
 * Funnel drop-off report → Slack.
 *
 * Pulls the enrollment funnel from Umami Cloud's funnel report endpoint and posts
 * a per-step drop-off digest to one or more Slack Incoming Webhooks. Designed to
 * run from cron a few times a day (see README/cron note).
 *
 * Two funnels are queried and merged (Umami caps a funnel at 8 steps):
 *   1. FORM   — the 7 on-form steps + event_submit_success (where people abandon).
 *   2. CONVERT — event_submit_success → thank_you_view → call_click (qualify + call).
 * Splitting them keeps "form abandonment" separate from "qualification/decline",
 * which is a routing outcome (Equifax < $10k → offerwall), not a UX drop-off.
 *
 * Secrets (from real env vars or .env/.env.local at the project root):
 *   UMAMI_API_KEY       required — Umami Cloud API key (x-umami-api-key)
 *   SLACK_WEBHOOK_URLS  required — one or more Slack webhook URLs, comma-separated
 *   UMAMI_WEBSITE_ID    optional — defaults to the Top Debt Options website id
 *
 * Usage:  php bin/funnel_report.php
 * Exit:   0 on success, 1 on config error, 2 on Umami error, 3 on Slack error.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
const UMAMI_API_BASE   = 'https://api.umami.is/v1';
const DEFAULT_WEBSITE  = '2ee9bab4-05ac-4d89-84aa-c05fdbdec0dd'; // Top Debt Options
const REPORT_TZ        = 'America/New_York';
const LOW_SAMPLE_MIN   = 15;   // below this many entrants, flag the numbers as noisy
const HTTP_TIMEOUT     = 20;

// The two funnels, as ordered [event_name => human label] maps.
const FORM_STEPS = [
    'event_view_debt_amount'    => 'Debt amount',
    'event_view_payment_status' => 'Payment status',
    'event_view_address'        => 'Address',
    'event_view_dob'            => 'Date of birth',
    'event_view_name_consent'   => 'Name + consent',
    'event_view_email'          => 'Email',
    'event_view_phone_verify'   => 'Phone verify',
    'event_submit_success'      => 'Submitted',
];
const CONVERT_STEPS = [
    'event_submit_success' => 'Submitted',
    'thank_you_view'       => 'Qualified',
    'call_click'           => 'Called',
];

// ---------------------------------------------------------------------------
// Minimal .env loader (no web side effects; real env vars win over files)
// ---------------------------------------------------------------------------
$DOTENV = [];
(function () use (&$DOTENV) {
    $dir = dirname(__DIR__);
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
            $name  = trim($name);
            $value = trim($value);
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[strlen($value) - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if ($name !== '') {
                $DOTENV[$name] = $value; // .env.local naturally overrides .env (loaded 2nd)
            }
        }
    }
})();

function cfg(string $key, ?string $default = null): ?string
{
    global $DOTENV;
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $DOTENV[$key] ?? null;
    }
    return ($v === null || $v === false || $v === '') ? $default : $v;
}

function fail(int $code, string $msg): never
{
    fwrite(STDERR, '[funnel_report] ' . $msg . "\n");
    exit($code);
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------

/** POST JSON to the Umami funnel endpoint; returns the decoded step array. */
function umami_funnel(string $apiKey, string $websiteId, array $steps, string $startIso, string $endIso): array
{
    $stepList = [];
    foreach (array_keys($steps) as $event) {
        $stepList[] = ['type' => 'event', 'value' => $event];
    }
    $body = json_encode([
        'websiteId'  => $websiteId,
        'type'       => 'funnel',
        'filters'    => (object) [],
        'parameters' => [
            'startDate' => $startIso,
            'endDate'   => $endIso,
            'window'    => 1,
            'steps'     => $stepList,
        ],
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init(UMAMI_API_BASE . '/reports/funnel');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-umami-api-key: ' . $apiKey,
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        fail(2, 'Umami request failed: ' . $err);
    }
    if ($code !== 200) {
        fail(2, "Umami funnel returned HTTP $code: $resp");
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        fail(2, 'Umami funnel returned non-array: ' . $resp);
    }
    return $data;
}

/** POST a Slack Block Kit payload to a webhook. Returns [httpCode, body]. */
function slack_post(string $url, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => HTTP_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, (string) $resp];
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------
function pct(float $frac): string { return number_format($frac * 100, 1) . '%'; }

/** Index a funnel step array by event name for easy lookup. */
function by_event(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$r['value']] = $r;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
$apiKey     = cfg('UMAMI_API_KEY');
$websiteId  = cfg('UMAMI_WEBSITE_ID', DEFAULT_WEBSITE);
$webhookRaw = cfg('SLACK_WEBHOOK_URLS');

if (!$apiKey)     { fail(1, 'UMAMI_API_KEY is not set.'); }
if (!$webhookRaw) { fail(1, 'SLACK_WEBHOOK_URLS is not set.'); }

$webhooks = array_values(array_filter(array_map('trim', explode(',', $webhookRaw))));
if (!$webhooks) { fail(1, 'SLACK_WEBHOOK_URLS contained no URLs.'); }

// Window: today since midnight in report TZ → now. Umami accepts UTC ISO (Z).
$tz    = new DateTimeZone(REPORT_TZ);
$now   = new DateTime('now', $tz);
$start = (clone $now)->setTime(0, 0, 0);
$utc   = new DateTimeZone('UTC');
$startIso = (clone $start)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
$endIso   = (clone $now)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');

$windowLabel = 'Today ' . $start->format('g:i a') . ' → ' . $now->format('g:i a T') . ' (' . $now->format('M j') . ')';

// --- Pull both funnels ---
$form    = by_event(umami_funnel($apiKey, $websiteId, FORM_STEPS, $startIso, $endIso));
$convert = by_event(umami_funnel($apiKey, $websiteId, CONVERT_STEPS, $startIso, $endIso));

// --- Derive headline numbers ---
$entered   = (int) ($form['event_view_debt_amount']['visitors'] ?? 0);
$submitted = (int) ($form['event_submit_success']['visitors'] ?? 0);
$qualified = (int) ($convert['thank_you_view']['visitors'] ?? 0);
$called    = (int) ($convert['call_click']['visitors'] ?? 0);
$declined  = max(0, $submitted - $qualified); // submitted but routed to offerwall

$completionPct = $entered   > 0 ? $submitted / $entered   : 0.0;
$qualifiedPct  = $submitted > 0 ? $qualified / $submitted : 0.0;
$callPct       = $qualified > 0 ? $called   / $qualified  : 0.0;

// --- Build the per-step form table + find the biggest single drop ---
$rows      = [];
$biggest   = null; // ['label'=>, 'dropped'=>, 'dropoff'=>]
$prevLabel = null;
foreach (FORM_STEPS as $event => $label) {
    $r        = $form[$event] ?? ['visitors' => 0, 'dropped' => 0, 'dropoff' => null, 'previous' => 0];
    $visitors = (int) $r['visitors'];
    $dropped  = (int) $r['dropped'];
    $dropoff  = $r['dropoff']; // fraction or null (first step)

    if ($dropoff === null) {
        $rows[] = sprintf('%-16s %5d', $label, $visitors);
    } else {
        $rows[] = sprintf('%-16s %5d   ▼ %d (%s)', $label, $visitors, $dropped, pct((float) $dropoff));
        if ($biggest === null || $dropped > $biggest['dropped']) {
            $biggest = ['from' => $prevLabel, 'to' => $label, 'dropped' => $dropped, 'dropoff' => (float) $dropoff];
        }
    }
    $prevLabel = $label;
}
$tableText = "```\n" . implode("\n", $rows) . "\n```";

$lowSample = $entered < LOW_SAMPLE_MIN;

// --- Compose the Slack message (Block Kit) ---
$summaryLines = [
    sprintf('*Entered:* %d   •   *Completed form:* %d (%s)', $entered, $submitted, pct($completionPct)),
    sprintf('*Qualified:* %d (%s of submits)   •   *Offerwall/declined:* %d', $qualified, pct($qualifiedPct), $declined),
    sprintf('*Called:* %d (%s of qualified)', $called, pct($callPct)),
];

$blocks = [
    ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => '📉 Funnel drop-off — Top Debt Options', 'emoji' => true]],
    ['type' => 'context', 'elements' => [['type' => 'mrkdwn', 'text' => $windowLabel]]],
];

if ($lowSample) {
    $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn',
        'text' => sprintf(':warning: *Low sample* — only %d entered the funnel; percentages are noisy.', $entered)]];
}

$blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Form steps*\n" . $tableText]];

if ($biggest) {
    $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn',
        'text' => sprintf(':small_red_triangle_down: *Biggest drop:* %s → %s  (−%d, %s)',
            $biggest['from'], $biggest['to'], $biggest['dropped'], pct($biggest['dropoff']))]];
}

$blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => implode("\n", $summaryLines)]];
$blocks[] = ['type' => 'context', 'elements' => [['type' => 'mrkdwn',
    'text' => '<https://cloud.umami.is/websites/' . $websiteId . '|Open in Umami>  •  enrollment funnel']]];

$payload = [
    'text'   => sprintf('Funnel drop-off: %d entered → %d submitted (%s) → %d called', $entered, $submitted, pct($completionPct), $called),
    'blocks' => $blocks,
];

// --- Deliver to every webhook ---
$failures = 0;
foreach ($webhooks as $i => $url) {
    [$code, $resp] = slack_post($url, $payload);
    $tag = 'webhook #' . ($i + 1);
    if ($code === 200) {
        fwrite(STDOUT, "[funnel_report] $tag: posted OK\n");
    } else {
        $failures++;
        fwrite(STDERR, "[funnel_report] $tag: HTTP $code — $resp\n");
    }
}

exit($failures > 0 ? 3 : 0);
