<?php
require __DIR__ . '/includes/config.php';
$pageTitle = 'Our Partners — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="container">
    <article class="page">
        <h1>Our Partners</h1>
        <p>
            <?= e(SITE_NAME) ?> is a free matching service. We are not a lender, debt
            settlement company, or credit counseling agency. Instead, we work with a network
            of independent third-party partners who provide debt relief, debt consolidation,
            and lending services.
        </p>

        <h2>How matching works</h2>
        <p>
            When you complete our form, we use the information you provide to identify partners
            in our network that may be able to help with your situation. The partners that
            contact you operate independently and set their own terms, rates, and eligibility
            requirements.
        </p>

        <h2>What this means for you</h2>
        <ul>
            <li>We do not charge consumers a fee to use our matching service.</li>
            <li>Submitting our form does not guarantee that you will be approved for any product.</li>
            <li>Any agreement you enter into is solely between you and the partner.</li>
            <li>You are under no obligation to use the services of any partner we introduce.</li>
        </ul>

        <h2>Compensation disclosure</h2>
        <p>
            <?= e(SITE_NAME) ?> may receive compensation from partners when we refer
            consumers to them. This compensation may influence which partners we feature, but
            it does not affect the information we collect or the eligibility decisions made by
            our partners.
        </p>
        <p><a href="/">&larr; Back to home</a></p>
    </article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
