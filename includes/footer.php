    </main>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <p class="footer-top__copy">Copyright © 2026 TOP DEBT OPTIONS. All rights reserved.</p>
                <nav class="footer-nav" aria-label="Footer">
                    <a href="/privacy-policy.php">Privacy Policy</a>
                    <a href="/our-partners.php">Our Partners</a>
                    <a href="/terms-of-use.php">Terms of Use</a>
                </nav>
            </div>
            <div class="footer-disclaimer">
                <p>
                    Top Debt Options is not a loan provider; we attempt to match you to partners
                    that may extend a loan or other services to you. All loan approval decisions
                    and terms are determined by the loan providers at the time of your application
                    with them. There is no guarantee that you will be approved for a loan or that
                    you will qualify for the terms offered until your information is verified. The
                    offers and rates presented are estimates based on information you submit to us.
                    Your actual rates depend on your credit history, income, loan terms and other
                    factors. Top Debt Options is a DBA of Fitz Media Group, LLC. Not available in
                    all states.
                </p>
                <p>
                    Top Debt Options trademarks used herein are trademarks or registered trademarks
                    of Top Debt Options and its affiliates. The use of any other trade name,
                    copyright, or trademark is for identification and reference purposes only and
                    does not imply any association with the copyright or trademark holder of their
                    product or brand. Other product and company names mentioned herein are the
                    property of their respective owners.
                </p>
                <p>
                    Third party links are provided as a convenience and for informational purposes
                    only; they do not constitute an endorsement or an approval by Top Debt Options
                    of any of the products, services, or opinions of the corporation or organization
                    or individual. Top Debt Options bears no responsibility for the accuracy,
                    legality, or content of the external site or for that of subsequent links.
                    Contact the external site for answers to questions regarding its content.
                </p>
            </div>
            <p class="footer-copy">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
        </div>
    </footer>
    <script>window.APP_CONFIG = <?= json_encode([
                                    'gmapsKey' => GOOGLE_MAPS_API_KEY,
                                    'firebase' => [
                                        'apiKey'            => FIREBASE_API_KEY,
                                        'authDomain'        => FIREBASE_AUTH_DOMAIN,
                                        'projectId'         => FIREBASE_PROJECT_ID,
                                        'appId'             => FIREBASE_APP_ID,
                                        'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
                                    ],
                                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="/assets/js/app.js" defer></script>
    </body>

    </html>