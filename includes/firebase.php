<?php

/**
 * Minimal, dependency-free Firebase ID token verification.
 *
 * Verifies a Firebase Authentication ID token (RS256 JWT) against Google's
 * public x509 certificates and checks the standard claims. Returns the decoded
 * claims on success, or null on any failure.
 */

declare(strict_types=1);

const FIREBASE_CERTS_URL =
    'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

function firebase_b64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? '' : $decoded;
}

function firebase_http_get(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($res !== false && $code === 200) ? (string) $res : '';
    }
    $res = @file_get_contents($url);
    return $res === false ? '' : (string) $res;
}

/**
 * Fetch Google's secure-token x509 certs, cached on disk for an hour.
 *
 * @return array<string,string> kid => PEM certificate
 */
function firebase_fetch_public_certs(bool $force = false): array
{
    $cacheFile = sys_get_temp_dir() . '/firebase_securetoken_certs.json';

    if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached) {
            return $cached;
        }
    }

    $body = firebase_http_get(FIREBASE_CERTS_URL);
    $certs = $body !== '' ? json_decode($body, true) : null;
    if (!is_array($certs) || !$certs) {
        return [];
    }
    @file_put_contents($cacheFile, json_encode($certs), LOCK_EX);
    return $certs;
}

/**
 * Verify a Firebase ID token. Returns decoded claims on success, else null.
 *
 * @return array<string,mixed>|null
 */
function firebase_verify_id_token(string $idToken, string $projectId): ?array
{
    if ($idToken === '' || $projectId === '') {
        return null;
    }

    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;

    $header    = json_decode(firebase_b64url_decode($h), true);
    $claims    = json_decode(firebase_b64url_decode($p), true);
    $signature = firebase_b64url_decode($s);

    if (!is_array($header) || !is_array($claims) || $signature === '') {
        return null;
    }
    if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        return null;
    }

    // --- Claim checks ---
    $now = time();
    if (($claims['aud'] ?? '') !== $projectId) {
        return null;
    }
    if (($claims['iss'] ?? '') !== 'https://securetoken.google.com/' . $projectId) {
        return null;
    }
    if (empty($claims['sub'])) {
        return null;
    }
    if ((int) ($claims['exp'] ?? 0) <= $now) {
        return null;
    }
    if ((int) ($claims['iat'] ?? PHP_INT_MAX) > $now + 300) { // allow small clock skew
        return null;
    }

    // --- Signature check ---
    $kid = $header['kid'];
    $certs = firebase_fetch_public_certs();
    if (!isset($certs[$kid])) {
        // Key may have rotated since the cache was written — refetch once.
        $certs = firebase_fetch_public_certs(true);
    }
    if (!isset($certs[$kid])) {
        return null;
    }

    $pubKey = openssl_pkey_get_public($certs[$kid]);
    if ($pubKey === false) {
        return null;
    }
    $ok = openssl_verify($h . '.' . $p, $signature, $pubKey, OPENSSL_ALGO_SHA256);
    if (function_exists('openssl_free_key') && is_resource($pubKey)) {
        @openssl_free_key($pubKey);
    }

    return $ok === 1 ? $claims : null;
}
