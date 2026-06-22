<?php

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Top Debt Options matches you with debt relief partners. See if you qualify in minutes.">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/cropped-favicon-cmd-32x32.png">
    <link rel="shortcut icon" href="/assets/img/cropped-favicon-cmd-32x32.png">
<?php if (TRUSTEDFORM_ENABLED): ?>
    <link rel="dns-prefetch" href="https://cdn.trustedform.com">
    <link rel="preconnect" href="https://api.trustedform.com" crossorigin>
<?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script defer src="https://cloud.umami.is/script.js" data-website-id="2ee9bab4-05ac-4d89-84aa-c05fdbdec0dd"></script>
    <!-- Analytics core: loaded synchronously so window.TDOAnalytics is defined before any page's inline scripts run. -->
    <script src="/assets/js/analytics.js?v=<?= @filemtime(__DIR__ . '/../assets/js/analytics.js') ?>"></script>
</head>

<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <a class="brand" href="/" aria-label="<?= e(BRAND_SHORT) ?> home">
            <img src="assets/img/top-debt-option-logo.webp" class="logo">
        </a>
    </header>
    <main id="main">