<?php

/**
 * LeadProsper direct-post integration.
 *
 * Maps a validated lead to LeadProsper's field schema and posts it to the
 * direct_post ingestion endpoint. Nothing here throws — a submission failure must
 * never block the user, so the caller logs the returned result and moves on.
 */

declare(strict_types=1);

/**
 * The Equifax-verified total debt used for program qualification and posted as
 * LeadProsper's `total_debt`.
 *
 * When the server-side Equifax soft pull succeeds, submit.php sets
 * `verified_total_debt` on the lead — that real figure wins. Otherwise we fall back to
 * the upstream `softpull_returned` flag: when it's truthy a pull happened elsewhere and
 * we use the self-assessed bucket amount; when it's empty/falsey there is no verified
 * figure and we return 0.
 *
 * @param array $data     Validated lead fields from submit.php (carries verified_total_debt).
 * @param array $tracking First-touch attribution params (carries softpull_returned).
 */
function lead_total_debt(array $data, array $tracking): int
{
    if (isset($data['verified_total_debt']) && is_numeric($data['verified_total_debt'])) {
        return max(0, (int) $data['verified_total_debt']);
    }
    $softpull = strtolower((string) ($tracking['softpull_returned'] ?? ''));
    $softpullOk = $softpull !== '' && !in_array($softpull, ['0', 'false', 'no'], true);
    return $softpullOk ? debt_bucket_amount((string) ($data['debt_amount'] ?? '')) : 0;
}

/**
 * Build the LeadProsper payload from the internal lead $data plus first-touch
 * attribution. Empty values are dropped so optional fields aren't posted blank.
 *
 * @param array $data     Validated lead fields from submit.php.
 * @param array $tracking First-touch attribution params (from the session).
 * @return array Flat payload keyed by LeadProsper field name.
 */
function leadprosper_payload(array $data, array $tracking): array
{
    // Phone → 10 digits: strip formatting and a leading US country code.
    $phone = preg_replace('/\D/', '', (string) ($data['phone'] ?? ''));
    if (strlen($phone) === 11 && $phone[0] === '1') {
        $phone = substr($phone, 1);
    }

    // `total_debt` is the Equifax-verified figure; `self_assessed_debt` is what the
    // user entered. A failed soft pull zeroes total_debt (see lead_total_debt()).
    $debt = debt_bucket_amount((string) ($data['debt_amount'] ?? ''));
    $totalDebt = lead_total_debt($data, $tracking);

    $payload = [
        'lp_campaign_id'       => LP_CAMPAIGN_ID,
        'lp_supplier_id'       => LP_SUPPLIER_ID,
        'lp_key'               => LP_KEY,
        'first_name'           => $data['first_name'] ?? '',
        'last_name'            => $data['last_name'] ?? '',
        'email'                => $data['email'] ?? '',
        'phone'                => $phone,
        'date_of_birth'        => $data['dob'] ?? '',
        'address'              => $data['address'] ?? '',
        'city'                 => $data['city'] ?? '',
        'state'                => strtoupper((string) ($data['state'] ?? '')),
        'zip_code'             => $data['zip'] ?? '',
        'ip_address'           => $data['ip'] ?? '',
        'total_debt'           => $totalDebt,
        'self_assessed_debt'   => $debt,
        'behind_payment'       => $data['payment_status'] ?? '',
        'trustedform_cert_url' => $data['trustedform_cert_url'] ?? '',
        'jornaya_leadid'       => $data['jornaya_leadid'] ?? '',
        'tcpa_text'            => $data['tcpa_text'] ?? '',
        'user_agent'           => $data['user_agent'] ?? '',
        'landing_page_url'     => $tracking['landing_page_url'] ?? '',
    ];

    if (LP_TEST_MODE) {
        $payload['lp_action'] = 'test';
    }

    // Merge first-touch attribution (affid, utm_*, gclid, lp_subid*, adv*, etc.).
    foreach (TRACKING_PARAMS as $key) {
        if (!empty($tracking[$key])) {
            $payload[$key] = $tracking[$key];
        }
    }

    return array_filter($payload, static function ($v) {
        return $v !== '' && $v !== null;
    });
}

/**
 * Post a lead to LeadProsper. Returns a structured result; never throws.
 *
 * @return array{ok:bool, skipped:bool, status:int, response:?string, error:?string, sent:array}
 */
function leadprosper_submit(array $data, array $tracking): array
{
    $result = [
        'ok'       => false,
        'skipped'  => false,
        'status'   => 0,
        'response' => null,
        'error'    => null,
        'sent'     => [],
    ];

    if (LP_CAMPAIGN_ID === '' || LP_KEY === '') {
        $result['skipped'] = true;
        $result['error']   = 'LeadProsper not configured (LP_CAMPAIGN_ID / LP_KEY empty).';
        return $result;
    }

    $payload = leadprosper_payload($data, $tracking);
    // Stored for the audit log with the key redacted — never persist the secret.
    $result['sent'] = array_merge($payload, ['lp_key' => '***']);

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];

    if (function_exists('curl_init')) {
        $ch = curl_init(LP_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
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
            $result['error'] = 'cURL error ' . $errno;
            return $result;
        }
        $result['status']   = $status;
        $result['response'] = is_string($response) ? $response : null;
    } else {
        // Fallback for hosts without the cURL extension.
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => 20,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents(LP_ENDPOINT, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($response === false) {
            $result['error'] = 'HTTP request failed.';
            return $result;
        }
        $result['status']   = $status;
        $result['response'] = $response;
    }

    // LeadProsper returns JSON; a 2xx with an accepted/success status is a good lead.
    $decoded = json_decode((string) $result['response'], true);
    $accepted = is_array($decoded)
        && (
            (isset($decoded['result']) && strtolower((string) $decoded['result']) === 'accepted')
            || (isset($decoded['status']) && in_array(strtolower((string) $decoded['status']), ['accepted', 'success', 'ok'], true))
            || !empty($decoded['success'])
        );
    $result['ok'] = $result['status'] >= 200 && $result['status'] < 300 && ($accepted || $decoded === null);

    return $result;
}
