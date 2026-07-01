<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/firebase.php';
require __DIR__ . '/includes/equifax.php';
require __DIR__ . '/includes/leadprosper.php';
require __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$errors = [];

if (!csrf_verify($_POST['csrf'] ?? null)) {
    $errors[] = 'Your session expired. Please start over.';
}

/** Pull and trim a posted field. */
function field(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

/**
 * Resolve the real client IP. Behind Varnish/Cloudways, REMOTE_ADDR is the proxy,
 * so prefer the client-fetched public IP, then X-Forwarded-For, then REMOTE_ADDR.
 */
function client_ip(): string
{
    $candidates = [field('hid_ip_address')];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $candidates[] = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($candidates as $ip) {
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

$data = [
    'debt_amount'    => field('debt_amount'),
    'payment_status' => field('payment_status'),
    'address'        => field('address'),
    'city'           => field('city'),
    'state'          => strtoupper(field('state')),
    'zip'            => field('zip'),
    'dob'            => field('dob'),
    'first_name'     => field('first_name'),
    'last_name'      => field('last_name'),
    'email'          => field('email'),
    'phone'          => field('phone'),
    'credit_consent' => (($_POST['credit_consent'] ?? '') === '1'),
    'contact_consent'=> isset($_POST['contact_consent']),
    'phone_verified' => false,
    // Lead-certification tokens injected client-side by TrustedForm / Jornaya.
    'trustedform_cert_url' => field('xxTrustedFormCertUrl'),
    'jornaya_leadid'       => field('universal_leadid'),
    'tcpa_text'            => TCPA_TEXT,
    'user_agent'           => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
];

// --- Server-side validation ---
$allowedDebt = ['0-4999','5000-7499','7500-9999','10000-14999','15000-19999','20000-29999','30000-39999','40000-49999','50000-59999','60000-69999','70000-79999','80000-89999','90000-99999','100000+'];
if (!in_array($data['debt_amount'], $allowedDebt, true)) {
    $errors[] = 'Please select a valid debt amount.';
}
if (!in_array($data['payment_status'], ['over_60','over_30','not_behind'], true)) {
    $errors[] = 'Please select your payment status.';
}
if ($data['address'] === '' || $data['city'] === '') {
    $errors[] = 'Please provide your full address.';
}
if (!isset(US_STATES[$data['state']])) {
    $errors[] = 'Please select your state.';
}
if (!preg_match('/^\d{5}$/', $data['zip'])) {
    $errors[] = 'Please provide a valid 5-digit ZIP code.';
}
$dob = DateTime::createFromFormat('Y-m-d', $data['dob']);
if (!$dob) {
    $errors[] = 'Please provide a valid date of birth.';
} else {
    $age = $dob->diff(new DateTime('today'))->y;
    if ($age < 18) {
        $errors[] = 'You must be at least 18 years old.';
    }
}
if ($data['first_name'] === '' || $data['last_name'] === '') {
    $errors[] = 'Please provide your first and last name.';
}
if (!$data['credit_consent']) {
    $errors[] = 'Credit profile authorization is required.';
}
if (
    !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
    || strpos($data['email'], '..') !== false
    || !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/', $data['email'])
) {
    $errors[] = 'Please provide a valid email address.';
}
if (!preg_match('/^[\d\s().+-]{10,}$/', $data['phone'])) {
    $errors[] = 'Please provide a valid phone number.';
}
if (!$data['contact_consent']) {
    $errors[] = 'Contact consent is required to submit.';
}

// --- Phone verification ---
if (FIREBASE_PROJECT_ID !== '') {
    // Production: cryptographically verify the Firebase ID token.
    $claims = firebase_verify_id_token($_POST['firebase_token'] ?? '', FIREBASE_PROJECT_ID);
    if (!$claims) {
        $errors[] = 'Please verify your phone number to continue.';
    } else {
        $data['phone_verified'] = true;
        if (!empty($claims['phone_number'])) {
            $data['verified_phone'] = $claims['phone_number'];
        }
    }
} else {
    // Dev (Firebase not configured): trust the client flag.
    $data['phone_verified'] = (($_POST['phone_verified'] ?? '') === '1');
    if (!$data['phone_verified']) {
        $errors[] = 'Please verify your phone number to continue.';
    }
}

// --- Forward + persist the lead on success ---
if (!$errors) {
    $data['ip'] = client_ip();
    $tracking = $_SESSION['tracking'] ?? [];
    // Backfill attribution from the posted hidden fields if the session was lost.
    if (empty($tracking['affid']) && field('hid_affid') !== '') {
        $tracking['affid'] = field('hid_affid');
    }
    if (empty($tracking['ef_transaction_id']) && field('hid_ef_tid') !== '') {
        $tracking['ef_transaction_id'] = field('hid_ef_tid');
    }

    // Equifax soft pull: run a server-side no-SSN soft inquiry to get a verified total
    // debt. Never throws and never blocks the user. On success we record the figure on
    // the lead and mark softpull_returned so downstream qualification + LeadProsper use
    // the real number; on failure the lead is treated as unverified (total debt 0).
    $equifax = equifax_softpull($data);
    if ($equifax['ok']) {
        $data['verified_total_debt'] = $equifax['total_debt'];
        $tracking['softpull_returned'] = '1';
        if (!empty($equifax['transaction_id']) && empty($tracking['ef_transaction_id'])) {
            $tracking['ef_transaction_id'] = $equifax['transaction_id'];
        }
    }

    // Qualification runs on the Equifax-verified total debt, so a lead reaches the
    // thank-you page only when the soft pull confirmed $10k+. Anything under $10k —
    // whether verified low or unverified (soft pull failed, so total debt is 0) — is
    // routed to the decline offerwall instead. The lead is posted to LeadProsper
    // either way.
    $qualifies = lead_total_debt($data, $tracking) >= 10000;

    // Post to LeadProsper. This never throws and must never block the user — a
    // rejection or outage is logged with the lead, but they still get routed on.
    $lp = leadprosper_submit($data, $tracking);

    $storeDir = __DIR__ . '/storage';
    if (!is_dir($storeDir)) {
        @mkdir($storeDir, 0775, true);
    }
    $leadId = bin2hex(random_bytes(8));
    $record = $data;
    $record['lead_id'] = $leadId;
    $record['submitted_at'] = date('c');
    $record['tracking'] = $tracking;
    $record['outcome'] = $qualifies ? 'qualified' : 'offerwall';
    $record['debt_qualified'] = $qualifies;
    $record['equifax'] = $equifax;
    $record['leadprosper'] = $lp;
    @file_put_contents(
        $storeDir . '/leads.jsonl',
        json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    // Primary store: insert into the database. Fails soft — the JSONL append
    // above is the durable fallback, so a DB outage never blocks the lead.
    db_insert_lead($record);

    // Rotate CSRF token to prevent resubmission, then redirect (PRG pattern).
    unset($_SESSION['csrf']);

    // Routing outcome surfaced on the destination page URL, for client-side tags
    // to read/showcase. debt_total mirrors the self-reported debt_amount bucket;
    // the qualified flags reflect the routing decision (thank-you vs offerwall).
    $outcomeParams = [
        'debt_total'          => $data['debt_amount'],
        'debt_qualified_flag' => $qualifies ? 'true' : 'false',
        'verified_debt_10k'   => $qualifies ? 'true' : 'false',
    ];

    if (!$qualifies) {
        // Decline offerwall: keep session attribution so the offerwall can resolve
        // CTA links by affid. Personalize the heading with the first name.
        $_SESSION['lead_offerwall'] = ['first_name' => $data['first_name']];
        header('Location: /offerwall.php?' . http_build_query($outcomeParams));
        exit;
    }

    // Qualified: stash a one-time flash for the thank-you page, clear attribution.
    $_SESSION['lead_success'] = [
        'lead_id'     => $leadId,
        'first_name'  => $data['first_name'],
        'email'       => $data['email'],
        'phone'       => $data['phone'],
        'debt_amount' => $data['debt_amount'],
    ];

    // Carry Google Ads attribution onto the thank-you URL so client-side tags
    // (CallGrid reads ?keyword, plus GTM/Bing) still see them after the redirect.
    $passThrough = [];
    foreach (GOOGLE_ADS_FIELDS as $k) {
        if (!empty($tracking[$k])) {
            $passThrough[$k] = $tracking[$k];
        }
    }

    unset($_SESSION['tracking']);
    header('Location: /thank-you.php?' . http_build_query($outcomeParams + $passThrough));
    exit;
}

$pageTitle = 'There was a problem — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="funnel">
    <div class="container">
        <div class="card">
            <div class="result">
                <div class="result__icon result__icon--err">!</div>
                <h2 class="step__title">We couldn't process your request</h2>
                <ul style="text-align:left;color:#36434f;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="actions">
                    <a class="btn btn--primary" href="/">Start over</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
