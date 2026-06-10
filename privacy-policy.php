<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'Privacy Policy — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="container">
    <article class="page">
        <h1>Privacy Policy</h1>
        <p>Last updated: <?= date('F j, Y') ?></p>
        <p>
            This Privacy Policy explains how <?= e(SITE_NAME) ?> ("we," "us," or "our")
            collects, uses, and shares information about you when you use our website and
            request to be matched with debt relief or lending partners.
        </p>

        <h2>Information we collect</h2>
        <ul>
            <li>Contact details you provide, such as your name, address, email, and phone number.</li>
            <li>Financial information you submit, such as your estimated debt amount and payment status.</li>
            <li>Technical data, such as your IP address, browser type, and pages visited.</li>
        </ul>

        <h2>How we use your information</h2>
        <ul>
            <li>To match you with third-party partners who may offer debt relief or lending services.</li>
            <li>To respond to your requests and provide customer support.</li>
            <li>To improve our website and comply with legal obligations.</li>
        </ul>

        <h2>How we share your information</h2>
        <p>
            When you submit our form, you authorize us to share your information with our
            partners so they can contact you about products and services that may meet your
            needs. We may also share information with service providers who support our
            operations, and as required by law.
        </p>

        <h2>Your choices</h2>
        <p>
            You may request access to, correction of, or deletion of your personal
            information by emailing <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.
            You may also opt out of marketing communications at any time.
        </p>

        <h2>Contact us</h2>
        <p>
            If you have questions about this policy, contact us at
            <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.
        </p>
        <p><a href="/">&larr; Back to home</a></p>
    </article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
