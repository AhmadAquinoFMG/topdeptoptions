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
