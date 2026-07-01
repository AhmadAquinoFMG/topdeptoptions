<?php
/**
 * MySQL persistence for leads.
 *
 * Leads are dual-written: the DB is the primary store, and submit.php also
 * appends every record to storage/leads.jsonl as a durable fallback. Every
 * function here fails soft (returns false, logs via error_log) so a database
 * outage degrades gracefully and never blocks a lead from being routed.
 */

declare(strict_types=1);

/**
 * Shared MySQL PDO connection, or null if the database is unconfigured or
 * unreachable. Memoized per request — a failed attempt is cached as null and
 * not retried. Never throws.
 */
function db(): ?PDO
{
    static $pdo = false; // false = not attempted; null = unconfigured/failed
    if ($pdo !== false) {
        return $pdo;
    }
    if (DB_NAME === '' || DB_USER === '') {
        return $pdo = null;
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $ex) {
        error_log('[db] connection failed: ' . $ex->getMessage());
        $pdo = null;
    }
    return $pdo;
}

/**
 * Insert a lead record (the same array written to leads.jsonl). Core fields go
 * into `leads`; the Equifax and LeadProsper result sub-arrays are split into
 * their own child tables. All writes run in one transaction, so the lead and
 * its integration rows land together or not at all. Returns true on success,
 * false if the DB is unavailable or the write fails.
 */
function db_insert_lead(array $r): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }
    $leadId = (string)($r['lead_id'] ?? '');
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO leads
                (lead_id, first_name, last_name, email, phone, verified_phone,
                 debt_amount, payment_status, address, city, state, zip, dob,
                 credit_consent, contact_consent, phone_verified, verified_total_debt,
                 outcome, debt_qualified, ip, user_agent, trustedform_cert_url, jornaya_leadid, tcpa_text,
                 tracking, submitted_at)
             VALUES
                (:lead_id, :first_name, :last_name, :email, :phone, :verified_phone,
                 :debt_amount, :payment_status, :address, :city, :state, :zip, :dob,
                 :credit_consent, :contact_consent, :phone_verified, :verified_total_debt,
                 :outcome, :debt_qualified, :ip, :user_agent, :trustedform_cert_url, :jornaya_leadid, :tcpa_text,
                 :tracking, :submitted_at)'
        );
        $stmt->execute([
            ':lead_id'             => $leadId,
            ':first_name'          => (string)($r['first_name'] ?? ''),
            ':last_name'           => (string)($r['last_name'] ?? ''),
            ':email'               => (string)($r['email'] ?? ''),
            ':phone'               => (string)($r['phone'] ?? ''),
            ':verified_phone'      => $r['verified_phone'] ?? null,
            ':debt_amount'         => (string)($r['debt_amount'] ?? ''),
            ':payment_status'      => (string)($r['payment_status'] ?? ''),
            ':address'             => (string)($r['address'] ?? ''),
            ':city'                => (string)($r['city'] ?? ''),
            ':state'               => (string)($r['state'] ?? ''),
            ':zip'                 => (string)($r['zip'] ?? ''),
            ':dob'                 => ($r['dob'] ?? '') !== '' ? $r['dob'] : null,
            ':credit_consent'      => !empty($r['credit_consent']) ? 1 : 0,
            ':contact_consent'     => !empty($r['contact_consent']) ? 1 : 0,
            ':phone_verified'      => !empty($r['phone_verified']) ? 1 : 0,
            ':verified_total_debt' => $r['verified_total_debt'] ?? null,
            ':outcome'             => (string)($r['outcome'] ?? ''),
            ':debt_qualified'      => !empty($r['debt_qualified']) ? 1 : 0,
            ':ip'                  => (string)($r['ip'] ?? ''),
            ':user_agent'          => (string)($r['user_agent'] ?? ''),
            ':trustedform_cert_url' => (string)($r['trustedform_cert_url'] ?? ''),
            ':jornaya_leadid'      => (string)($r['jornaya_leadid'] ?? ''),
            ':tcpa_text'           => $r['tcpa_text'] ?? null,
            ':tracking'            => isset($r['tracking']) ? json_encode($r['tracking'], JSON_UNESCAPED_SLASHES) : null,
            ':submitted_at'        => isset($r['submitted_at'])
                ? date('Y-m-d H:i:s', strtotime((string)$r['submitted_at']) ?: time())
                : date('Y-m-d H:i:s'),
        ]);

        if (isset($r['equifax']) && is_array($r['equifax'])) {
            db_insert_equifax($pdo, $leadId, $r['equifax']);
        }
        if (isset($r['leadprosper']) && is_array($r['leadprosper'])) {
            db_insert_leadprosper($pdo, $leadId, $r['leadprosper']);
        }
        if (isset($r['tracking']) && is_array($r['tracking'])) {
            db_insert_google_ads($pdo, $leadId, $r['tracking']);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[db] insert lead failed: ' . $ex->getMessage());
        return false;
    }
}

/** Insert the Equifax soft-pull result for a lead. Assumes an open transaction. */
function db_insert_equifax(PDO $pdo, string $leadId, array $e): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO lead_equifax
            (lead_id, ok, skipped, status, total_debt, transaction_id, error, sent, response)
         VALUES
            (:lead_id, :ok, :skipped, :status, :total_debt, :transaction_id, :error, :sent, :response)'
    );
    $stmt->execute([
        ':lead_id'        => $leadId,
        ':ok'             => !empty($e['ok']) ? 1 : 0,
        ':skipped'        => !empty($e['skipped']) ? 1 : 0,
        ':status'         => (int)($e['status'] ?? 0),
        ':total_debt'     => $e['total_debt'] ?? null,
        ':transaction_id' => $e['transaction_id'] ?? null,
        ':error'          => $e['error'] ?? null,
        ':sent'           => isset($e['sent']) ? json_encode($e['sent'], JSON_UNESCAPED_SLASHES) : null,
        ':response'       => $e['response'] ?? null,
    ]);
}

/** Insert the LeadProsper direct-post result for a lead. Assumes an open transaction. */
function db_insert_leadprosper(PDO $pdo, string $leadId, array $lp): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO lead_leadprosper
            (lead_id, ok, skipped, status, error, sent, response)
         VALUES
            (:lead_id, :ok, :skipped, :status, :error, :sent, :response)'
    );
    $stmt->execute([
        ':lead_id'  => $leadId,
        ':ok'       => !empty($lp['ok']) ? 1 : 0,
        ':skipped'  => !empty($lp['skipped']) ? 1 : 0,
        ':status'   => (int)($lp['status'] ?? 0),
        ':error'    => $lp['error'] ?? null,
        ':sent'     => isset($lp['sent']) ? json_encode($lp['sent'], JSON_UNESCAPED_SLASHES) : null,
        ':response' => $lp['response'] ?? null,
    ]);
}

/**
 * Insert the Google Ads attribution for a lead from the first-touch tracking
 * array. Skips entirely when no Google Ads field is present (organic / direct /
 * non-Google traffic), so those leads don't get empty rows. Assumes an open
 * transaction.
 */
function db_insert_google_ads(PDO $pdo, string $leadId, array $t): void
{
    $fields = GOOGLE_ADS_FIELDS;
    $vals = [];
    $hasData = false;
    foreach ($fields as $f) {
        $v = (string)($t[$f] ?? '');
        $vals[':' . $f] = $v;
        if ($v !== '') {
            $hasData = true;
        }
    }
    if (!$hasData) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO lead_google_ads
            (lead_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content,
             keyword, matchtype, network, device, gclid, gbraid, wbraid)
         VALUES
            (:lead_id, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content,
             :keyword, :matchtype, :network, :device, :gclid, :gbraid, :wbraid)'
    );
    $stmt->execute([':lead_id' => $leadId] + $vals);
}

/**
 * Record the CallGrid-assigned tracking number for a lead in lead_callgrid,
 * matched by lead_id. Upserts, so a re-assignment (e.g. page refresh) overwrites
 * the prior number. Returns true on success, false if the DB is unavailable or
 * the write fails (e.g. no matching lead row, via the foreign key).
 */
function db_update_callgrid(string $leadId, string $number, string $ip = '', string $sessionId = ''): bool
{
    $pdo = db();
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO lead_callgrid (lead_id, session_id, phone_number, ip, assigned_at)
             VALUES (:lead_id, :session_id, :num, :ip, NOW())
             ON DUPLICATE KEY UPDATE
                session_id   = VALUES(session_id),
                phone_number = VALUES(phone_number),
                ip           = VALUES(ip),
                assigned_at  = NOW()'
        );
        $stmt->execute([':lead_id' => $leadId, ':session_id' => $sessionId, ':num' => $number, ':ip' => $ip]);
        return true;
    } catch (Throwable $ex) {
        error_log('[db] update callgrid failed: ' . $ex->getMessage());
        return false;
    }
}
