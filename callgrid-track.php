<?php
/**
 * Records the CallGrid-assigned tracking number against a lead.
 *
 * The number is assigned client-side on the thank-you page, after the lead has
 * already been persisted in submit.php, so it arrives here via a fetch() POST
 * from that page. leads.jsonl is append-only, so we append a correlated
 * `callgrid_assignment` event (joined to the lead by lead_id) rather than
 * mutating the original record.
 */

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// Payload is JSON (navigator.sendBeacon / fetch with a JSON body).
$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

if (!csrf_verify($body['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'bad_csrf']);
    exit;
}

$leadId = trim((string)($body['lead_id'] ?? ''));
$number = trim((string)($body['phone_number'] ?? ''));

// lead_id is a 16-char hex string (bin2hex of 8 random bytes) from submit.php.
if (!preg_match('/^[a-f0-9]{16}$/', $leadId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'bad_lead_id']);
    exit;
}
// Keep only sane phone characters, and require some digits.
$number = substr($number, 0, 32);
if (!preg_match('/\d/', $number)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'bad_number']);
    exit;
}

$event = [
    'type'         => 'callgrid_assignment',
    'lead_id'      => $leadId,
    'phone_number' => $number,
    'assigned_at'  => date('c'),
    'ip'           => $_SERVER['REMOTE_ADDR'] ?? '',
];

// Primary store: record the assigned number in lead_callgrid, matched by lead_id.
$updated = db_update_callgrid($leadId, $number, (string)($_SERVER['REMOTE_ADDR'] ?? ''));

// Durable fallback: append a correlated event to leads.jsonl (append-only).
$storeDir = __DIR__ . '/storage';
if (!is_dir($storeDir)) {
    @mkdir($storeDir, 0775, true);
}
$ok = @file_put_contents(
    $storeDir . '/leads.jsonl',
    json_encode($event, JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

echo json_encode(['ok' => ($ok !== false) || $updated, 'db_updated' => $updated]);
