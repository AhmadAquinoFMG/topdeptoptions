<?php
require __DIR__ . '/includes/config.php';
$pageTitle = SITE_NAME . ' — See If You Qualify For Debt Relief';
require __DIR__ . '/includes/header.php';
$token = csrf_token();
?>
<!-- Full-width progress bar pinned below the header -->
<div class="topbar">
    <div class="topbar__fill" id="progress-fill"></div>
</div>

<section class="funnel">
    <div class="container">
        <div class="card">
            <p class="card__step" id="step-label">Step 1 of 7</p>

            <form id="qualify-form" action="/submit.php" method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= e($token) ?>">
                <!-- Lead-certification tokens, populated client-side before submit. -->
                <input type="hidden" name="xxTrustedFormCertUrl" id="xxTrustedFormCertUrl">
                <input type="hidden" name="xxTrustedFormPingUrl" id="xxTrustedFormPingUrl">
                <input type="hidden" name="xxTrustedFormToken" id="xxTrustedFormToken">
                <input type="hidden" name="universal_leadid" id="leadid_token" value="">
                <!-- Tracking: IP set client-side; affid/ef_tid prefilled from first-touch attribution. -->
                <input type="hidden" name="hid_ip_address" id="hid_ip_address">
                <input type="hidden" name="hid_affid" id="hid_affid" value="<?= e($_SESSION['tracking']['affid'] ?? '') ?>">
                <input type="hidden" name="hid_ef_tid" id="hid_ef_tid" value="<?= e($_SESSION['tracking']['ef_transaction_id'] ?? '') ?>">

                <div class="steps">
                    <!-- Step 1 -->
                    <fieldset class="step is-active" data-step="1">
                        <h2 class="step__title"><?= e(SITE_TAGLINE) ?></h2>
                        <div class="field">
                            <label class="sr-only" for="debt_amount">Estimated total debt</label>
                            <select id="debt_amount" name="debt_amount" data-required>
                                <option value="">Select your total debt…</option>
                                <option value="100000+">$100,000+</option>
                                <option value="90000-99999">$90,000 – $99,999</option>
                                <option value="80000-89999">$80,000 – $89,999</option>
                                <option value="70000-79999">$70,000 – $79,999</option>
                                <option value="60000-69999">$60,000 – $69,999</option>
                                <option value="50000-59999">$50,000 – $59,999</option>
                                <option value="40000-49999">$40,000 – $49,999</option>
                                <option value="30000-39999">$30,000 – $39,999</option>
                                <option value="20000-29999">$20,000 – $29,999</option>
                                <option value="15000-19999">$15,000 – $19,999</option>
                                <option value="10000-14999">$10,000 – $14,999</option>
                                <option value="7500-9999">$7,500 – $9,999</option>
                                <option value="5000-7499">$5,000 – $7,499</option>
                                <option value="0-4999">$0 – $4,999</option>
                            </select>
                            <p class="error-msg" data-error>Please select your estimated debt amount.</p>
                        </div>
                    </fieldset>

                    <!-- Step 2 -->
                    <fieldset class="step" data-step="2">
                        <h2 class="step__title">Are you behind on any payments?</h2>
                        <input type="hidden" name="payment_status" id="payment_status" data-required>
                        <div class="options" data-choice-group data-choice-for="payment_status">
                            <button type="button" class="option option--choice text-center" data-value="over_60">Yes, over 60 days behind</button>
                            <button type="button" class="option option--choice text-center" data-value="over_30">Yes, over 30 days behind</button>
                            <button type="button" class="option option--choice text-center" data-value="not_behind">No, I'm not behind</button>
                        </div>
                        <p class="error-msg" data-error>Please choose an option.</p>
                    </fieldset>

                    <!-- Step 3 -->
                    <fieldset class="step" data-step="3">
                        <h2 class="step__title">What is your address?</h2>
                        <div class="field">
                            <label class="field-label" for="address">Street address</label>
                            <input type="text" id="address" name="address" autocomplete="off" data-required data-address-autocomplete placeholder="Start typing your address…">
                        </div>
                        <div class="field-row field-row--csz">
                            <div class="field">
                                <label class="field-label" for="city">City</label>
                                <input type="text" id="city" name="city" autocomplete="address-level2" data-required>
                            </div>
                            <div class="field field--state">
                                <label class="field-label" for="state">State</label>
                                <select id="state" name="state" autocomplete="address-level1" data-required>
                                    <option value="">State…</option>
                                    <?php foreach (US_STATES as $abbr => $name): ?>
                                        <option value="<?= e($abbr) ?>"><?= e($abbr) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label" for="zip">ZIP code</label>
                                <input type="text" id="zip" name="zip" inputmode="numeric" autocomplete="postal-code" data-required data-pattern="^\d{5}$" data-error-text="Enter a valid 5-digit ZIP.">
                            </div>
                        </div>
                        <p class="error-msg" data-error>Please complete all address fields.</p>
                    </fieldset>

                    <!-- Step 4 -->
                    <fieldset class="step" data-step="4">
                        <h2 class="step__title">What is your date of birth?</h2>
                        <p class="step__sub">You must be at least 18 years old to qualify.</p>
                        <div class="field">
                            <label class="field-label" for="dob">Date of birth</label>
                            <input type="text" id="dob" name="dob" data-required data-adult autocomplete="bday" placeholder="Select your date of birth">
                            <p class="error-msg" data-error>Please enter a valid date of birth (18+).</p>
                        </div>
                    </fieldset>

                    <!-- Step 5 -->
                    <fieldset class="step" data-step="5">
                        <h2 class="step__title">What is your name?</h2>
                        <div class="field-row">
                            <div class="field">
                                <label class="field-label" for="first_name">First name</label>
                                <input type="text" id="first_name" name="first_name" autocomplete="given-name" data-required>
                            </div>
                            <div class="field">
                                <label class="field-label" for="last_name">Last name</label>
                                <input type="text" id="last_name" name="last_name" autocomplete="family-name" data-required>
                            </div>
                        </div>
                        <div class="disclosure" data-disclosure>
                            <div class="disclosure__scroll" data-disclosure-scroll tabindex="0" role="region" aria-label="Credit profile authorization">
                                <p>
                                    By clicking on the &lsquo;Next&rsquo; button below, you agree to the terms and
                                    conditions, acknowledge receipt of our privacy policy and agree to its terms, and
                                    confirm your authorization for <?= e(SITE_NAME) ?> to obtain your credit profile
                                    from any consumer reporting agency to display to you, to confirm your identity, and
                                    to avoid fraudulent transactions in your name.
                                </p>
                                <p>
                                    You understand that by proceeding, you are providing &lsquo;written instructions&rsquo;
                                    under the FCRA authorizing us to obtain information from your personal credit profile
                                    from each credit reporting agency. You authorize us to obtain such information solely
                                    to confirm your identity and display your credit data to you.
                                </p>
                            </div>
                            <p class="disclosure__hint" data-disclosure-hint>Please scroll down and read the full authorization to continue.</p>
                        </div>
                        <input type="hidden" name="credit_consent" id="credit_consent" data-required>
                        <p class="error-msg" data-error>Please enter your name and read the authorization to continue.</p>
                    </fieldset>

                    <!-- Step 6 -->
                    <fieldset class="step" data-step="6">
                        <h2 class="step__title">Where should we send your results?</h2>
                        <div class="field">
                            <label class="field-label" for="email">Email address</label>
                            <input type="email" id="email" name="email" autocomplete="email" data-required data-pattern="^(?!.*\.\.)[^@\s.]+(\.[^@\s.]+)*@[^@\s.]+(\.[^@\s.]+)+$" data-error-text="Enter a valid email address.">
                            <p class="error-msg" data-error>Please enter a valid email address.</p>
                        </div>
                    </fieldset>

                    <!-- Step 7 -->
                    <fieldset class="step" data-step="7">
                        <h2 class="step__title">Verify your phone number</h2>
                        <div class="field">
                            <label class="field-label" for="phone">Phone number</label>
                            <input type="tel" id="phone" name="phone" autocomplete="tel" data-required data-pattern="^[\d\s().+-]{10,}$" data-error-text="Enter a valid phone number." placeholder="(555) 555-1234">
                        </div>
                        <div class="field">
                            <button type="button" class="btn btn--light" id="send-code">Send Verification Code</button>
                        </div>
                        <!-- Invisible reCAPTCHA mount point for Firebase Phone Auth -->
                        <div id="recaptcha-container"></div>
                        <div class="field" id="code-field" hidden>
                            <label class="field-label" for="verify_code">Verification code</label>
                            <div class="code-row">
                                <input type="text" id="verify_code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code">
                                <button type="button" class="btn btn--light" id="verify-code">Verify</button>
                            </div>
                            <p class="step__sub" id="code-note" style="margin:6px 0 0;"></p>
                        </div>
                        <!-- Set by the phone-auth flow once verification succeeds -->
                        <input type="hidden" name="phone_verified" id="phone_verified" data-required>
                        <input type="hidden" name="firebase_token" id="firebase_token">
                        <label class="consent">
                            <input type="checkbox" name="contact_consent" value="1" data-required>
                            <span>By checking this box, I agree to be contacted by <?= e(SITE_NAME) ?> and its partners at the number provided, including by automated dialing and prerecorded messages, even if my number is on a Do Not Call list. Consent is not a condition of purchase. Message and data rates may apply.</span>
                        </label>
                        <p class="error-msg" data-error>Please verify your phone number and provide consent.</p>
                    </fieldset>
                </div>

                <!-- Persistent benefits -->
                <ul class="benefits">
                    <li><span class="check" aria-hidden="true">✔</span> Reduce monthly payments by up to 50%</li>
                    <li><span class="check" aria-hidden="true">✔</span> Combine balances into one affordable payment</li>
                    <li><span class="check" aria-hidden="true">✔</span> Solutions tailored to your financial situation</li>
                </ul>

                <!-- Persistent action bar (labels/visibility controlled by JS) -->
                <div class="actions">
                    <button type="button" class="btn btn--back" id="btn-back" hidden>Back</button>
                    <button type="button" class="btn btn--primary" id="btn-primary">Continue</button>
                </div>
            </form>
        </div>

        <p class="secure-note">🔒 Your information is encrypted and secure.</p>
    </div>
</section>

<!-- Social proof -->
<section class="trust">
    <div class="container">
        <h2 class="trust__title">Trusted by Thousands of Americans</h2>
        <div class="trust__badges">
            <div class="badge">
                <span class="badge__source">
                    <img src="assets/img/google-logo.webp">
                </span>
                <span class="badge__score">4.9</span>
                <span class="badge__stars" aria-hidden="true">★★★★★</span>
                <span class="badge__count">Based on 1,842 Reviews</span>
            </div>
            <div class="badge">
                <img src="assets/img/yelp-logo.webp">
                <span class="badge__score">4.8</span>
                <span class="badge__stars" aria-hidden="true">★★★★★</span>
                <span class="badge__count">Based on 342 Reviews</span>
            </div>
        </div>
    </div>
</section>
<?php // TrustedForm + Jornaya are injected client-side by loadCompliance() in app.js. ?>
<?php require __DIR__ . '/includes/footer.php'; ?>