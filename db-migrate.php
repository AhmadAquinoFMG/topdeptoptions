<?php
/**
 * One-shot schema migrator. Applies db/schema.sql to the configured database.
 *
 * Usage (from the project root, over SSH on Cloudways or locally):
 *     php db-migrate.php
 *
 * CLI-only by design — it must not be reachable over the web. Alternatively,
 * paste db/schema.sql directly into phpMyAdmin or the Cloudways DB manager.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("db-migrate.php can only be run from the command line.\n");
}

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo = db();
if (!$pdo) {
    fwrite(STDERR, "Database not configured or unreachable. Set DB_HOST/DB_NAME/DB_USER/DB_PASS in .env.\n");
    exit(1);
}

$sqlPath = __DIR__ . '/db/schema.sql';
$sql = @file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "Could not read schema file: $sqlPath\n");
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "Schema applied to database '" . DB_NAME . "' on " . DB_HOST . ".\n";
} catch (Throwable $ex) {
    fwrite(STDERR, "Migration failed: " . $ex->getMessage() . "\n");
    exit(1);
}
