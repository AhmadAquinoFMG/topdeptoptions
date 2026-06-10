<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'Terms of Use — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="container">
    <article class="page">
        <h1>Terms of Use</h1>
        <p>Last updated: <?= date('F j, Y') ?></p>
        <p>
            By accessing or using the <?= e(SITE_NAME) ?> website, you agree to these Terms
            of Use. If you do not agree, please do not use the site.
        </p>

        <h2>Eligibility</h2>
        <p>
            You must be at least 18 years old and a legal resident of the United States to use
            our service. By submitting our form, you represent that the information you provide
            is accurate and complete.
        </p>

        <h2>No professional advice</h2>
        <p>
            <?= e(SITE_NAME) ?> does not provide legal, financial, tax, or credit-repair
            advice. The information on this site is for general informational purposes only.
            You should consult a qualified professional before making financial decisions.
        </p>

        <h2>Not a lender</h2>
        <p>
            We are a matching service and do not make loans or credit decisions. We do not
            guarantee that you will be matched with a partner, approved for any product, or
            offered any particular rate or term. Partner availability may vary by state.
        </p>

        <h2>Consent to contact</h2>
        <p>
            By submitting our form and providing your consent, you authorize <?= e(SITE_NAME) ?>
            and its partners to contact you at the phone number and email you provide,
            including by automated means. Consent is not a condition of any purchase.
        </p>

        <h2>Limitation of liability</h2>
        <p>
            The site and our service are provided "as is" without warranties of any kind. To
            the fullest extent permitted by law, <?= e(SITE_NAME) ?> is not liable for any
            damages arising from your use of the site or your dealings with any partner.
        </p>

        <h2>Contact</h2>
        <p>
            Questions about these terms? Email
            <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>.
        </p>
        <p><a href="/">&larr; Back to home</a></p>
    </article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
