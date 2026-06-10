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
