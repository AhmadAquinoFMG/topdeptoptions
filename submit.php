<?php
require __DIR__ . '/includes/config.php';

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

$data = [
    'debt_amount'    => field('debt_amount'),
    'payment_status' => field('payment_status'),
    'address'        => field('address'),
    'city'           => field('city'),
    'zip'            => field('zip'),
    'dob'            => field('dob'),
    'first_name'     => field('first_name'),
    'last_name'      => field('last_name'),
    'email'          => field('email'),
    'phone'          => field('phone'),
    'credit_consent' => (($_POST['credit_consent'] ?? '') === '1'),
    'contact_consent'=> isset($_POST['contact_consent']),
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
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please provide a valid email address.';
}
if (!preg_match('/^[\d\s().+-]{10,}$/', $data['phone'])) {
    $errors[] = 'Please provide a valid phone number.';
}
if (!$data['contact_consent']) {
    $errors[] = 'Contact consent is required to submit.';
}

// --- Persist the lead on success ---
if (!$errors) {
    $storeDir = __DIR__ . '/storage';
    if (!is_dir($storeDir)) {
        @mkdir($storeDir, 0775, true);
    }
    $record = $data;
    $record['submitted_at'] = date('c');
    $record['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    @file_put_contents(
        $storeDir . '/leads.jsonl',
        json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    // Rotate CSRF token to prevent resubmission.
    unset($_SESSION['csrf']);
}

$pageTitle = $errors
    ? 'There was a problem — ' . SITE_NAME
    : 'You\'re all set — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="funnel">
    <div class="container">
        <div class="card">
            <?php if ($errors): ?>
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
            <?php else: ?>
                <div class="result">
                    <div class="result__icon">✓</div>
                    <h2 class="step__title">Thank you, <?= e($data['first_name']) ?>!</h2>
                    <p class="step__sub" style="margin-top:0;">
                        Your request has been received. One of our debt relief partners will
                        review your information and reach out to <strong><?= e($data['email']) ?></strong>
                        or <strong><?= e($data['phone']) ?></strong> with the options you may qualify for.
                    </p>
                    <div class="actions">
                        <a class="btn btn--primary" href="/">Back to home</a>
                    </div>
                    <p class="secure-note">🔒 Your information was submitted securely.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
