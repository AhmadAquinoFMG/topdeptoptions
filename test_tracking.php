<?php
/**
 * THROWAWAY TEST HARNESS — delete before deploying.
 *
 * Visit:  /test_tracking.php?oid=42&affid=995
 * Proves URL params are captured first-touch and forwarded into the LeadProsper payload.
 *
 * Note: capture_tracking() is first-touch, so once the session holds a value it
 * won't change. Use the ?reset=1 link below to clear the session and start over.
 */

require __DIR__ . '/includes/config.php';   // runs capture_tracking() against the real $_GET
require __DIR__ . '/includes/leadprosper.php';

if (isset($_GET['reset'])) {
    $_SESSION['tracking'] = [];
    header('Location: /test_tracking.php?oid=42&affid=995');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== \$_GET (this request) ===\n";
print_r($_GET);

echo "\n=== \$_SESSION['tracking'] (captured first-touch) ===\n";
print_r($_SESSION['tracking'] ?? []);

// A minimal fake lead so we can build the exact payload that would POST to LeadProsper.
$fakeLead = [
    'first_name' => 'Test', 'last_name' => 'Lead',
    'email' => 'test@example.com', 'phone' => '5551234567',
    'state' => 'fl', 'zip' => '33101', 'debt_amount' => '20000-29999',
];

echo "\n=== leadprosper_payload() — what gets POSTed ===\n";
$payload = leadprosper_payload($fakeLead, $_SESSION['tracking'] ?? []);
$payload['lp_key'] = '***'; // never print the secret
print_r($payload);

echo "\noid  in payload: " . (isset($payload['oid'])   ? "YES ({$payload['oid']})"   : 'NO') . "\n";
echo "affid in payload: " . (isset($payload['affid']) ? "YES ({$payload['affid']})" : 'NO') . "\n";

echo "\n[reset session] -> /test_tracking.php?reset=1\n";
