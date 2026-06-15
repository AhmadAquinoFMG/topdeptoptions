<?php

/**
 * Equifax soft-pull integration (Equifax Developer Platform, OAuth2).
 *
 * Performs a no-SSN soft inquiry (prequalification) from the lead's name, address
 * and date of birth, and reads back a verified total-debt figure used for program
 * qualification. Mirrors leadprosper.php conventions: nothing here throws — a failed
 * pull must never block the user, so the caller logs the result and falls back to the
 * upstream softpull_returned flag.
 *
 * The exact product endpoint, request shape and the response attribute that carries
 * total debt are contract-specific. Endpoints, account identifiers and the response
 * dot-path are all configurable via env (see config.php / .env.example) so they can be
 * confirmed against a sandbox response without code changes.
 */

declare(strict_types=1);

/**
 * True when Equifax credentials are configured. When false the caller skips the pull.
 */
function equifax_configured(): bool
{
    return EQUIFAX_API_KEY !== '' && EQUIFAX_API_SECRET !== '';
}

/**
 * Low-level JSON HTTP request. cURL when available, stream-context fallback otherwise.
 * Returns [status, body, error] — never throws.
 *
 * @param array<int,string> $headers
 * @return array{0:int,1:?string,2:?string}
 */
function equifax_http(string $method, string $url, string $body, array $headers): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($errno !== 0) {
            return [0, null, 'cURL error ' . $errno];
        }
        return [$status, is_string($response) ? $response : null, null];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return [0, null, 'HTTP request failed.'];
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return [$status, $response, null];
}

/**
 * Fetch (and per-request cache) an OAuth2 access token via the client-credentials
 * grant. Returns the bearer token string, or null on failure.
 *
 * On failure, $debug (if passed) is populated with the token endpoint's HTTP status,
 * transport error and response body so the caller can log *why* auth failed — an opaque
 * "token request failed" is useless for diagnosing invalid_scope / invalid_client.
 *
 * The `scope` parameter is sent only when EQUIFAX_SCOPE is non-empty: this account's
 * token endpoint rejects any explicit scope and issues a token only when it's omitted.
 *
 * @param array{status:int,error:?string,response:?string}|null $debug
 */
function equifax_access_token(?array &$debug = null): ?string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $url    = EQUIFAX_API_BASE . EQUIFAX_TOKEN_PATH;
    $params = ['grant_type' => 'client_credentials'];
    if (EQUIFAX_SCOPE !== '') {
        $params['scope'] = EQUIFAX_SCOPE;
    }
    $body  = http_build_query($params);
    $basic = base64_encode(EQUIFAX_API_KEY . ':' . EQUIFAX_API_SECRET);
    $headers = [
        'Authorization: Basic ' . $basic,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ];

    [$status, $resp, $err] = equifax_http('POST', $url, $body, $headers);
    $debug = ['status' => $status, 'error' => $err, 'response' => is_string($resp) ? $resp : null];
    if ($err !== null || $status < 200 || $status >= 300) {
        return null;
    }
    $decoded = json_decode((string) $resp, true);
    $token = is_array($decoded) ? ($decoded['access_token'] ?? null) : null;
    if (!is_string($token) || $token === '') {
        return null;
    }
    $cached = $token;
    return $token;
}

/**
 * Build the Consumer Credit Report request payload from the validated lead.
 *
 * Account identifiers (member number, security code, customer code, ECOA inquiry type,
 * multiple-report indicator) go under customerConfiguration.equifaxUSConsumerCreditReport.
 * Identity is keyed on name + address + DOB; SSN is included only if the lead carries one
 * (the funnel form does not collect it), so matching relies on name/address/DOB.
 *
 * @param array $data Validated lead fields from submit.php.
 * @return array
 */
function equifax_request_payload(array $data): array
{
    // OneView expects DOB as MMDDYYYY; the funnel collects it as YYYY-MM-DD.
    $dobRaw = (string) ($data['dob'] ?? '');
    $dobTs  = $dobRaw !== '' ? strtotime($dobRaw) : false;

    $consumers = [
        'name' => [[
            'identifier' => 'current',
            'firstName'  => (string) ($data['first_name'] ?? ''),
            'lastName'   => (string) ($data['last_name'] ?? ''),
        ]],
        'addresses' => [[
            'identifier' => 'current',
            'streetName' => trim((string) ($data['address'] ?? '')),
            'city'       => (string) ($data['city'] ?? ''),
            'state'      => strtoupper((string) ($data['state'] ?? '')),
            'zip'        => (string) ($data['zip'] ?? ''),
        ]],
        'dateOfBirth' => $dobTs !== false ? date('mdY', $dobTs) : '',
    ];

    // SSN is optional for this funnel; include it only when present.
    $ssn = preg_replace('/\D/', '', (string) ($data['ssn'] ?? ''));
    if ($ssn !== '') {
        $consumers['socialNum'] = [[
            'identifier' => 'current',
            'number'     => $ssn,
        ]];
    }

    $creditReportConfig = array_filter([
        'memberNumber'            => EQUIFAX_MEMBER_NUMBER,
        'securityCode'            => EQUIFAX_SECURITY_CODE,
        'customerCode'            => EQUIFAX_CUSTOMER_CODE,
        'ECOAInquiryType'         => EQUIFAX_ECOA_INQUIRY_TYPE,
        'multipleReportIndicator' => EQUIFAX_MULTIPLE_REPORT_INDICATOR,
        'codeDescriptionRequired' => true,
    ], static function ($v) {
        return $v !== '' && $v !== null;
    });

    // OneView requires the entitled credit-model id under .models; omit when unset.
    if (EQUIFAX_MODEL_ID !== '') {
        $creditReportConfig['models'] = [['identifier' => EQUIFAX_MODEL_ID]];
    }

    return [
        'consumers' => $consumers,
        'customerReferenceIdentifier' => (string) ($data['email'] ?? ''),
        'customerConfiguration' => [
            'equifaxUSConsumerCreditReport' => $creditReportConfig,
        ],
    ];
}

/**
 * Redact account secrets from a request payload before it's logged. The member number,
 * security code and customer code identify our Equifax account and must never be
 * persisted with the lead; everything else (the consumer identity we sent) is kept so
 * the audit log shows what was submitted.
 *
 * @param array $payload Output of equifax_request_payload().
 * @return array
 */
function equifax_redact_payload(array $payload): array
{
    $creditReport = $payload['customerConfiguration']['equifaxUSConsumerCreditReport'] ?? null;
    if (is_array($creditReport)) {
        foreach (['memberNumber', 'securityCode', 'customerCode'] as $secret) {
            if (isset($creditReport[$secret]) && $creditReport[$secret] !== '') {
                $creditReport[$secret] = '***';
            }
        }
        $payload['customerConfiguration']['equifaxUSConsumerCreditReport'] = $creditReport;
    }
    return $payload;
}

/**
 * Read a value out of a decoded JSON response by dot-path (e.g. "a.b.0.c").
 * Returns null if any segment is missing.
 *
 * @param mixed $data
 * @return mixed
 */
function equifax_dig($data, string $path)
{
    foreach (explode('.', $path) as $segment) {
        if (is_array($data) && array_key_exists($segment, $data)) {
            $data = $data[$segment];
        } else {
            return null;
        }
    }
    return $data;
}

/**
 * Recursively sum trade-line balances anywhere in the decoded response. The Consumer
 * Credit Report nests trades under consumers.equifaxUSConsumerCreditReports[].trades[],
 * so we walk the whole tree: whenever we hit a list under a "trades"/"tradelines"/
 * "accounts" key, add up each entry's balance. Returns [sum, foundAny].
 *
 * @return array{0:float,1:bool}
 */
function equifax_sum_trade_balances($node): array
{
    $sum = 0.0;
    $found = false;
    if (!is_array($node)) {
        return [$sum, $found];
    }
    foreach ($node as $key => $value) {
        if (is_string($key) && in_array(strtolower($key), ['trades', 'tradelines', 'accounts'], true) && is_array($value)) {
            foreach ($value as $trade) {
                if (!is_array($trade)) {
                    continue;
                }
                $balance = $trade['balanceAmount'] ?? ($trade['balance'] ?? ($trade['currentBalance'] ?? null));
                if (is_numeric($balance)) {
                    $sum += (float) $balance;
                    $found = true;
                }
            }
        } elseif (is_array($value)) {
            [$childSum, $childFound] = equifax_sum_trade_balances($value);
            $sum += $childSum;
            $found = $found || $childFound;
        }
    }
    return [$sum, $found];
}

/**
 * Extract the verified total debt from a decoded response. Uses the configured dot-path
 * first (if EQUIFAX_TOTAL_DEBT_PATH is set and your product returns a precomputed total);
 * otherwise sums trade-line balances across the report. Returns null when nothing usable
 * is found.
 */
function equifax_extract_total_debt($decoded): ?int
{
    if (!is_array($decoded)) {
        return null;
    }

    if (EQUIFAX_TOTAL_DEBT_PATH !== '') {
        $value = equifax_dig($decoded, EQUIFAX_TOTAL_DEBT_PATH);
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }
    }

    [$sum, $found] = equifax_sum_trade_balances($decoded);
    if ($found) {
        return (int) round($sum);
    }

    return null;
}

/**
 * Perform the Equifax soft pull for a lead. Returns a structured result; never throws.
 *
 * On success, `total_debt` is the verified integer debt figure (>= 0). On any failure
 * (not configured, auth failure, HTTP error, unparseable response) `ok` is false and
 * `total_debt` is null — the caller treats that as an unverified lead.
 *
 * Mirrors leadprosper_submit()'s audit shape so the logger shows what we sent and what
 * came back: `sent` is the request payload with account secrets redacted (never persist
 * the member/security/customer codes). The raw `response` is logged only on failure —
 * a successful pull returns a full consumer credit report (heavy PII) we deliberately
 * keep out of the lead log, having already extracted the one figure we need.
 *
 * @param array $data Validated lead fields from submit.php.
 * @return array{ok:bool, skipped:bool, status:int, total_debt:?int, transaction_id:?string, error:?string, sent:array, response:?string}
 */
function equifax_softpull(array $data): array
{
    $result = [
        'ok'             => false,
        'skipped'        => false,
        'status'         => 0,
        'total_debt'     => null,
        'transaction_id' => null,
        'error'          => null,
        'sent'           => [],
        'response'       => null,
    ];

    if (!equifax_configured()) {
        $result['skipped'] = true;
        $result['error']   = 'Equifax not configured (EQUIFAX_CLIENT_ID / EQUIFAX_CLIENT_SECRET empty).';
        return $result;
    }

    $tokenDebug = null;
    $token = equifax_access_token($tokenDebug);
    if ($token === null) {
        $result['status']   = (int) ($tokenDebug['status'] ?? 0);
        $result['response'] = is_string($tokenDebug['response'] ?? null) ? $tokenDebug['response'] : null;
        $reason = $tokenDebug['error'] ?? null;
        if ($reason === null && !empty($tokenDebug['status'])) {
            $reason = 'HTTP ' . $tokenDebug['status'];
        }
        $result['error'] = 'Equifax OAuth token request failed' . ($reason ? ': ' . $reason : '') . '.';
        return $result;
    }

    $payload = equifax_request_payload($data);
    // Stored for the audit log with account identifiers redacted — never persist secrets.
    $result['sent'] = equifax_redact_payload($payload);

    $url     = EQUIFAX_API_BASE . EQUIFAX_PRODUCT_PATH;
    $body    = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    [$status, $resp, $err] = equifax_http('POST', $url, (string) $body, $headers);
    $result['status'] = $status;
    if ($err !== null) {
        $result['error'] = $err;
        return $result;
    }
    if ($status < 200 || $status >= 300) {
        // Non-2xx bodies are error diagnostics, not credit data — safe to log.
        $result['response'] = is_string($resp) ? $resp : null;
        $result['error'] = 'Equifax returned HTTP ' . $status;
        return $result;
    }

    $decoded = json_decode((string) $resp, true);
    if (!is_array($decoded)) {
        $result['response'] = is_string($resp) ? $resp : null;
        $result['error'] = 'Equifax response was not valid JSON.';
        return $result;
    }

    // Pull through a transaction/reference id when the response carries one.
    foreach (['transactionId', 'reference', 'consumerReferralCode'] as $idKey) {
        $tid = equifax_dig($decoded, $idKey);
        if (is_string($tid) && $tid !== '') {
            $result['transaction_id'] = $tid;
            break;
        }
    }

    $totalDebt = equifax_extract_total_debt($decoded);
    if ($totalDebt === null) {
        $result['error'] = 'Equifax response did not contain a readable total-debt value.';
        return $result;
    }

    $result['ok']         = true;
    $result['total_debt'] = max(0, $totalDebt);
    return $result;
}
