<?php
/**
 * Template Name: Privacy Policy
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Registered address and analytics mode come from Settings → Headline Recorder when the platform plugin is active.
$hr_org     = function_exists( 'HR\\Platform\\Site\\org' ) ? \HR\Platform\Site\org() : array();
$hr_address = implode( ', ', array_filter( array(
    $hr_org['street_address'] ?? 'First Floor, Feroze Heights, Saadi Town',
    implode( ', ', array_filter( array( $hr_org['locality'] ?? 'Karachi', trim( ( $hr_org['region'] ?? 'Sindh' ) . ' ' . ( $hr_org['postal_code'] ?? '75280' ) ) ) ) ),
    function_exists( 'HR\\Platform\\Site\\country_name' ) && ! empty( $hr_org['country'] ) ? \HR\Platform\Site\country_name( $hr_org['country'] ) : 'Pakistan',
) ) );
$hr_email      = $hr_org['email'] ?? 'contact@headlinerecorder.com';
$hr_tag_waits  = ! function_exists( 'HR\\Platform\\Site\\analytics_waits_for_consent' ) || \HR\Platform\Site\analytics_waits_for_consent();

get_header();
?>

<style>
/* ==========================================================================
   HEADLINE RECORDER - PREMIUM PRIVACY POLICY PAGE UI
   ========================================================================== */

/* Force theme wrappers to full width */
#primary, #main, .site-main, .content-area {
    width: 100% !important; max-width: 100% !important; float: none !important; margin: 0 !important; padding: 0 !important;
}
#secondary, .sidebar, .widget-area { display: none !important; }
.twp-breadcrumb-wrapper, .breadcrumb-trail, #breadcrumb, .breadcrumbs, .page-header { display: none !important; }

/* Enable smooth scrolling for sidebar anchor links */
html { scroll-behavior: smooth; }
@media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }

/* --- MASTER CONTAINER --- */
.hr-priv-master {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #FFFFFF;
    color: #0A0A0A;
    line-height: 1.5;
    margin: 0; padding: 0;
    overflow-wrap: break-word;
}
.hr-priv-master * { box-sizing: border-box; }
.hr-priv-master a { color: #C0001A; text-decoration: underline; text-underline-offset: 2px; }
.hr-priv-master a:hover { color: #0A0A0A; }
.hr-priv-master a:focus-visible, .hr-priv-master button:focus-visible { outline: 2px solid #C0001A; outline-offset: 2px; }

/* --- PREMIUM BREADCRUMB --- */
.es-top-nav-path {
    display: block !important; max-width: 1200px; margin: 0 auto; padding: 14px 48px; font-size: 12px; color: #6B7280; letter-spacing: 1px; text-transform: uppercase; background: #FFFFFF;
}
.hr-priv-master .es-top-nav-path a { display: inline-flex; align-items: center; min-height: 44px; color: #6B7280; text-decoration: none; transition: color 0.2s; }
.hr-priv-master .es-top-nav-path a:hover { color: #C0001A; }
.es-top-nav-path span { margin: 0 8px; }

/* --- HERO SECTION --- */
.hr-hero-section {
    background-color: #0A0A0A;
    background-image: radial-gradient(ellipse at 70% 50%, rgba(192,0,26,0.15) 0%, transparent 60%);
    padding: 80px 48px; position: relative; overflow: hidden;
}
.hr-hero-inner {
    max-width: 1200px; margin: 0 auto; position: relative; z-index: 1;
}
.hr-eyebrow { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.hr-eyebrow .hr-line { width: 32px; height: 2px; background-color: #C0001A; }
.hr-eyebrow span { font-size: 12px; font-weight: 800; letter-spacing: 4px; color: #FF3B52; text-transform: uppercase; }
.hr-hero-inner h1 {
    font-size: 48px; font-weight: 800; color: #FFFFFF; margin: 0 0 16px 0; line-height: 1.1; letter-spacing: -1px;
}
.hr-hero-inner p {
    font-size: 17px; color: #B4B4BB; margin: 0; line-height: 1.6; max-width: 720px;
}
.hr-hero-inner .hr-hero-meta {
    font-size: 14px; color: #9CA3AF; margin: 16px 0 0 0;
}

/* --- MAIN CONTENT --- */
.hr-main-content {
    max-width: 1200px; margin: 0 auto; padding: 60px 48px; display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 64px;
}

/* --- SECTIONS --- */
.hr-priv-section { margin-bottom: 60px; scroll-margin-top: 40px; }
.hr-priv-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.hr-priv-number {
    width: 40px; height: 40px; background: #C0001A; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; border-radius: 2px;
}
.hr-priv-header-text { flex: 1; min-width: 0; }
.hr-priv-header-text h2 { font-size: 22px; font-weight: 800; color: #0A0A0A; margin: 0 0 12px 0; }
.hr-priv-header-text h3 { font-size: 17px; font-weight: 800; color: #0A0A0A; margin: 24px 0 8px 0; }
.hr-priv-text { font-size: 16px; color: #4B5563; line-height: 1.7; margin: 0 0 16px 0; }
.hr-priv-text strong, .hr-priv-list strong, .hr-alert strong { color: #0A0A0A; }

/* --- ALERT BOXES --- */
.hr-alert { padding: 16px; margin-top: 16px; }
.hr-alert-title { font-size: 13px; font-weight: 700; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 1px; }
.hr-alert-text { font-size: 16px; margin: 0; line-height: 1.6; }
.hr-alert-text + .hr-alert-text { margin-top: 10px; }
.hr-alert-list { margin: 0; padding-left: 20px; font-size: 16px; line-height: 1.7; }
.hr-alert-list li { margin-bottom: 8px; }
.hr-alert-list li:last-child { margin-bottom: 0; }

/* Specific Alerts */
.hr-alert-gray { background: #F9F9F9; border-left: 3px solid #C0001A; color: #4B5563; padding: 20px; }
.hr-alert-gray .hr-alert-title { color: #0A0A0A; }
.hr-alert-yellow { background: #FFF3CD; border: 1px solid #FFE69C; border-left: 3px solid #FF9800; color: #6B4F03; }
.hr-alert-yellow .hr-alert-title { color: #6B4F03; font-size: 12px; }
.hr-priv-master .hr-alert-yellow a { color: #6B4F03; }
.hr-alert-green { background: #E8F5E9; border: 1px solid #C8E6C9; border-left: 3px solid #4CAF50; color: #1B5E20; }
.hr-alert-green .hr-alert-title { color: #1B5E20; font-size: 12px; }

/* List styles for standard text */
.hr-priv-list { margin: 0 0 16px 0; padding-left: 20px; font-size: 16px; color: #4B5563; line-height: 1.7; }
.hr-priv-list li { margin-bottom: 10px; }
.hr-priv-list li:last-child { margin-bottom: 0; }

/* Cookie table: a table on wide screens, one card per cookie on phones */
.hr-cookie-table-wrap { margin: 16px 0; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #E5E7EB; }
.hr-cookie-table { width: 100%; border-collapse: collapse; font-size: 15px; line-height: 1.55; color: #4B5563; }
.hr-cookie-table caption { padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #0A0A0A; background: #F9F9F9; border-bottom: 1px solid #E5E7EB; }
.hr-cookie-table th, .hr-cookie-table td { padding: 12px 16px; text-align: left; vertical-align: top; border-bottom: 1px solid #E5E7EB; }
.hr-cookie-table thead th { font-size: 12px; letter-spacing: 1px; text-transform: uppercase; color: #6B7280; background: #FFFFFF; }
.hr-cookie-table tbody th { color: #0A0A0A; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; font-size: 14px; font-weight: 600; white-space: nowrap; }
.hr-cookie-table tr:last-child th, .hr-cookie-table tr:last-child td { border-bottom: 0; }
.hr-cookie-cat { display: inline-block; padding: 2px 8px; font-size: 12px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; border: 1px solid currentColor; white-space: nowrap; }
.hr-cookie-cat--necessary { color: #0A0A0A; }
.hr-cookie-cat--analytics { color: #C0001A; }
.hr-cookie-cat--embeds { color: #6B4F03; }
.hr-consent-btn {
    display: inline-flex; align-items: center; justify-content: center; min-height: 48px; margin-top: 8px; padding: 12px 22px; border: 2px solid #0A0A0A; background: #0A0A0A; color: #FFFFFF !important; font-size: 13px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none !important; cursor: pointer; transition: background-color .2s, border-color .2s;
}
.hr-consent-btn:hover { background: #C0001A; border-color: #C0001A; }

/* --- SIDEBAR --- */
.hr-sidebar { position: sticky; top: 24px; align-self: start; display: flex; flex-direction: column; gap: 0; }
.hr-sb-nav-box { background: #F9F9F9; padding: 24px; border-left: 4px solid #C0001A; }
.hr-sb-title { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #C0001A; text-transform: uppercase; margin-bottom: 12px; }
.hr-sb-nav { display: flex; flex-direction: column; gap: 0; }
.hr-priv-master .hr-sb-link {
    display: flex; align-items: center; min-height: 44px; font-size: 14px; font-weight: 500; color: #4B5563; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #E5E7EB; cursor: pointer; transition: all 0.2s;
}
.hr-sb-link:last-child { border-bottom: none; }
.hr-priv-master .hr-sb-link:hover { color: #0A0A0A; }

.hr-sb-key-points { background: #0A0A0A; color: #FFFFFF; padding: 24px; margin-top: 32px; }
.hr-sb-key-points ul { margin: 0; padding-left: 16px; font-size: 16px; color: #B4B4BB; line-height: 1.6; }
.hr-sb-key-points li { margin-bottom: 10px; }
.hr-sb-key-points li:last-child { margin-bottom: 0; }

.hr-priv-master .hr-cta-btn {
    display: block; background: #C0001A; color: #FFFFFF; padding: 16px 24px; font-size: 13px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none; text-align: center; margin-top: 32px; transition: opacity 0.2s;
}
.hr-priv-master .hr-cta-btn:hover { opacity: 0.85; color: #FFFFFF; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .hr-hero-section, .hr-main-content { padding-left: max(24px, env(safe-area-inset-left)); padding-right: max(24px, env(safe-area-inset-right)); }
    .hr-main-content { grid-template-columns: minmax(0, 1fr); gap: 40px; }
    .hr-sidebar { position: static; }
}
@media (max-width: 768px) {
    .es-top-nav-path { padding: 6px max(24px, env(safe-area-inset-left)); }
    .hr-hero-section { padding-top: 56px; padding-bottom: 56px; }
    .hr-hero-inner h1 { font-size: 34px; }
    .hr-main-content { padding-top: 40px; padding-bottom: 48px; }
    .hr-priv-section { margin-bottom: 48px; }
    .hr-priv-header-text h2 { font-size: 20px; }
    .hr-priv-header { flex-direction: column; }
}
@media (max-width: 640px) {
    .hr-cookie-table-wrap { overflow: visible; border: 0; }
    .hr-cookie-table, .hr-cookie-table tbody, .hr-cookie-table tr, .hr-cookie-table th, .hr-cookie-table td { display: block; width: 100%; }
    .hr-cookie-table caption { display: block; border: 1px solid #E5E7EB; }
    .hr-cookie-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    .hr-cookie-table tr { border: 1px solid #E5E7EB; border-top: 3px solid #C0001A; margin-top: 12px; padding: 4px 0; }
    .hr-cookie-table th, .hr-cookie-table td { border: 0; padding: 6px 16px; font-size: 16px; }
    .hr-cookie-table tbody th { white-space: normal; overflow-wrap: anywhere; }
    .hr-cookie-table td::before { content: attr(data-label); display: block; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #6B7280; }
}
</style>

<div class="hr-priv-master">

    <!-- BREADCRUMB -->
    <div class="es-top-nav-path">
        <a href="/">Home</a>
        <span>·</span>
        <span style="color: #6B7280;">Privacy Policy</span>
    </div>

    <!-- HERO SECTION -->
    <div class="hr-hero-section">
        <div class="hr-hero-inner">
            <div class="hr-eyebrow">
                <div class="hr-line"></div>
                <span>Privacy</span>
            </div>
            <h1>Privacy Policy</h1>
            <p>At Headline Recorder, your privacy is paramount. We're transparent about how we collect, use, and protect your data. This policy explains our practices in clear, human language.</p>
            <p class="hr-hero-meta">Last updated: October 2026</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="hr-main-content">

        <!-- LEFT: CONTENT -->
        <div>

            <!-- INTRODUCTION -->
            <section id="intro" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">1</div>
                    <div class="hr-priv-header-text">
                        <h2>Introduction</h2>
                        <p class="hr-priv-text">
                            We believe in transparency. This Privacy Policy explains how Headline Recorder collects, uses, discloses, and safeguards your information when you read our website, subscribe to our newsletter, create an account, or send us a submission.
                        </p>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            Headline Recorder is responsible for the personal data described here. Our registered address is <?php echo esc_html( $hr_address ); ?>, and you can reach us about privacy at <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>"><?php echo esc_html( $hr_email ); ?></a>.
                        </p>
                    </div>
                </div>
            </section>

            <!-- DATA WE COLLECT -->
            <section id="data" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">2</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Data We Collect Automatically</h2>
                        <p class="hr-priv-text">
                            Like every website, our servers receive some technical information with each request. We use it to deliver pages, keep the site secure (for example, to stop password-guessing and spam) and fix problems.
                        </p>

                        <div class="hr-alert hr-alert-gray">
                            <p class="hr-alert-title">Collected Data</p>
                            <ul class="hr-alert-list">
                                <li>IP address, browser type and version, operating system and device type</li>
                                <li>Referring site and the page requested</li>
                                <li>Date and time of each request</li>
                            </ul>
                        </div>
                        <p class="hr-priv-text" style="margin: 16px 0 0;">
                            Server logs are kept by our hosting provider for a limited period for security and troubleshooting. Our own security records store your IP address only in a pseudonymised (keyed-hash) form.
                        </p>
                    </div>
                </div>
            </section>

            <!-- ANALYTICS -->
            <section id="analytics" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">3</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Third-Party Analytics</h2>
                        <p class="hr-priv-text">
                            We use Google Analytics (via Google Site Kit) to understand which articles readers find useful and how people move around the site — <strong>only if you allow analytics</strong> in our cookie banner. We do not use it for advertising.
                        </p>
                        <p class="hr-priv-text">
                            We use Google Consent Mode. Until you choose, analytics and advertising storage are set to “denied”.
                            <?php if ( $hr_tag_waits ) : ?>
                                Google’s analytics tag is not loaded at all until you accept analytics.
                            <?php else : ?>
                                Google’s tag loads in this denied state: it sets no cookies and sends only cookieless pings (no identifiers) so that Google can model overall visitor numbers.
                            <?php endif; ?>
                            If your browser sends a Global Privacy Control signal, we treat it as “reject” and analytics stay off.
                        </p>

                        <div class="hr-alert hr-alert-yellow">
                            <p class="hr-alert-title">You Have Control</p>
                            <p class="hr-alert-text">
                                You can change your choice at any time with the “Cookie settings” link at the bottom of every page. You can also install the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener noreferrer">Google Analytics Opt-Out Browser Add-on</a>, which stops your browser sending data to Google Analytics on any website.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- INFORMATION YOU PROVIDE -->
            <section id="provided" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">4</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Information You Provide</h2>
                        <p class="hr-priv-text">
                            You can read Headline Recorder without an account. When you choose to contact us, subscribe or sign up, we collect information such as:
                        </p>

                        <ul class="hr-priv-list">
                            <li><strong>Contact and media-kit forms:</strong> your name, email address, subject and message. They are emailed to our team and kept in our inbox only as long as we need them to reply and keep a record of the conversation.</li>
                            <li><strong>Newsletter:</strong> your email address, sent to Mailchimp (Intuit), which delivers the Intelligence Briefing. You confirm your subscription by email, and every newsletter has an unsubscribe link.</li>
                            <li><strong>Comments</strong> on our articles, if you post them (see Public Interactions).</li>
                            <li><strong>Story pitches and inquiries</strong> you send us by email.</li>
                            <li><strong>Account and submission details</strong>, described in the next sections.</li>
                        </ul>

                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            <strong>We use this information only to:</strong> respond to your inquiries, send newsletters you've subscribed to, run your account, review your submissions, and improve our services. We never sell your data to third parties.
                        </p>
                    </div>
                </div>
            </section>

            <!-- ACCOUNTS -->
            <section id="accounts" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">5</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Accounts &amp; Sign-in</h2>
                        <p class="hr-priv-text">
                            An account is optional. You need one to keep a reading streak and to submit articles or op-eds. When you have an account we store:
                        </p>
                        <ul class="hr-priv-list">
                            <li><strong>Your name and email address</strong> — to identify you, sign you in and send account messages (verification, password reset, security alerts).</li>
                            <li><strong>A password hash</strong> — your password is never stored. We keep only a one-way Argon2id hash of it.</li>
                            <li><strong>Passkey public keys</strong>, if you add a passkey. The private key never leaves your device.</li>
                            <li><strong>Your two-step verification secret</strong>, if you turn it on — stored encrypted — plus one-way hashes of your recovery codes.</li>
                            <li><strong>Session records</strong> — for each signed-in session: when it started and expires, how you signed in, the browser and device type, and the IP address it started from. You can see and end your sessions in your account settings.</li>
                            <li><strong>Known devices</strong> — a random device ID in a cookie, of which we store only keyed hashes of your last 10 devices, so we can email you a <strong>new-device alert</strong> when your account is used from a device we have not seen before.</li>
                        </ul>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            We keep this information until you delete your account. Sessions end when you sign out or when they expire.
                        </p>
                    </div>
                </div>
            </section>

            <!-- READING ACTIVITY -->
            <section id="reading" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">6</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Reading Activity &amp; Streaks</h2>
                        <p class="hr-priv-text">
                            When you are signed in, we record which articles you finish reading and on which dates, and work out your streak statistics (current and longest streak, articles read). We do this only to show you your own reading streak and history. Nothing is recorded while you are signed out, and your reading activity is never shared or used for advertising.
                        </p>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            We keep your reading activity until you delete your account. You can download it with the rest of your data, or delete your account and everything with it, from your account settings.
                        </p>
                    </div>
                </div>
            </section>

            <!-- SUBMISSIONS -->
            <section id="submissions" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">7</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Submissions</h2>
                        <p class="hr-priv-text">
                            Signed-in writers can submit articles and op-eds. A submission includes your headshot, a short intro, your title or designation, your email address, the headline, the type (article or op-ed), a category and the text.
                        </p>
                        <div class="hr-alert hr-alert-green">
                            <p class="hr-alert-title">End-to-end encrypted</p>
                            <p class="hr-alert-text">
                                Your piece and the details you add to it are encrypted in your browser before they are sent, using post-quantum ML-KEM-1024 (combined with P-384) and AES-256-GCM. Only our editors can decrypt them, in their own browsers, with a key that is kept off our web server. The headline, type, category, word count, whether a file is attached and the contact email you give stay unencrypted, together with your account and the date, so editors can manage the queue and reply to you. If you tick “Remember my name, title and intro”, those three details are kept unencrypted with your account until you untick it or delete the account.
                            </p>
                        </div>
                        <p class="hr-priv-text" style="margin: 16px 0 0;">
                            Editors receive an email notice when a new piece arrives (without its contents). We keep submissions for as long as we need them to review your piece and keep an editorial record. If we do not publish a piece, you can ask us to delete it at any time; deleting your account deletes all of your unpublished submissions. If we publish it, it becomes a public article with your byline, headshot and intro.
                        </p>
                    </div>
                </div>
            </section>

            <!-- PUBLIC INTERACTIONS -->
            <section id="public" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">8</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Public Interactions</h2>
                        <p class="hr-priv-text">
                            If you post comments on our articles, the name and comment you post become public. We cannot control how others use that information, so please be cautious about sharing personal details publicly.
                        </p>

                        <div class="hr-alert hr-alert-gray" style="padding: 16px;">
                            <p class="hr-alert-text">
                                <strong>Example:</strong> If you leave a comment with your name, that comment and your name will be visible to all site visitors. Your email address is never shown. Avoid sharing sensitive information in public sections.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AI TRANSPARENCY -->
            <section id="ai" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">9</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">AI Transparency</h2>
                        <p class="hr-priv-text">
                            To ensure timely news coverage, Headline Recorder may utilize AI-assisted technologies for content summarization or optimization. All AI-assisted content is reviewed by human editors to maintain our standards of accuracy and integrity. We do not put your personal data, your submissions or your reading activity into AI tools.
                        </p>

                        <div class="hr-alert hr-alert-green">
                            <p class="hr-alert-title">🤖 How We Use AI</p>
                            <ul class="hr-alert-list">
                                <li>Content summarization and headline optimization</li>
                                <li>Drafting Podcast briefings from our published articles</li>
                                <li>All AI output reviewed by human editors before publication</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ERROR MONITORING -->
            <section id="monitoring" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">10</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Error Monitoring</h2>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            We use Sentry to find and fix errors and slow pages. When something breaks, Sentry receives technical details: the error message, the page address (with sensitive values such as sign-in tokens removed), the browser and operating system, and timing information. We configure it not to collect names, email addresses, cookies or form contents, and not to store your IP address.
                        </p>
                    </div>
                </div>
            </section>

            <!-- CONTENT SIGNING -->
            <section id="signing" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">11</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Content Signing</h2>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            Every article and feed we publish is digitally signed (ML-DSA-87 + Ed448) so anyone can check it is genuine on our <a href="<?php echo esc_url( home_url( '/verify/' ) ); ?>">verify page</a>. Signatures cover only published content and contain no personal data. Verification runs in your browser; we do not record what you verify.
                        </p>
                    </div>
                </div>
            </section>

            <!-- COOKIES -->
            <section id="cookies" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">12</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Cookies &amp; Tracking</h2>
                        <p class="hr-priv-text">
                            Cookies are small files your browser stores for a website. We use <strong>necessary</strong> cookies to run the site and keep your account secure — these are always on. <strong>Analytics</strong> and <strong>embedded content</strong> are optional: when you first visit, our cookie banner lets you accept all, reject all or choose. Analytics stay off until you accept them. Embedded YouTube videos and TradingView market widgets load unless you switch them off (or your browser sends Global Privacy Control).
                        </p>

                        <div class="hr-cookie-table-wrap">
                            <table class="hr-cookie-table">
                                <caption>Cookies we use</caption>
                                <thead>
                                    <tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Duration</th><th scope="col">Category</th></tr>
                                </thead>
                                <tbody>
                                    <tr><th scope="row">wordpress_logged_in_*</th><td data-label="Purpose">Keeps you signed in.</td><td data-label="Duration">2 days, or 14 days if you tick “Remember me” (12 hours / 3 days for staff)</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">wordpress_sec_*</th><td data-label="Purpose">Secure sign-in cookie for your account area and the dashboard.</td><td data-label="Duration">Same as sign-in</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">hr_li</th><td data-label="Purpose">Tells cached pages that you are signed in, so they can show your account and streak. Contains only “1”.</td><td data-label="Duration">Same as sign-in</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">hr_dev</th><td data-label="Purpose">Random device ID used to send new-device security alerts.</td><td data-label="Duration">1 year</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">hr_wa</th><td data-label="Purpose">Links a passkey sign-in or set-up to this browser.</td><td data-label="Duration">5 minutes</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">hr_2fa</th><td data-label="Purpose">Links the two-step verification step to the sign-in that started it.</td><td data-label="Duration">5 minutes</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">hr_consent</th><td data-label="Purpose">Remembers your cookie choices.</td><td data-label="Duration">180 days</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">wordpress_test_cookie</th><td data-label="Purpose">Checks that your browser accepts cookies when you sign in.</td><td data-label="Duration">Session</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">wp-settings-*</th><td data-label="Purpose">Remembers dashboard layout preferences (editors and staff only).</td><td data-label="Duration">1 year</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">_lscache_vary</th><td data-label="Purpose">Lets our page cache serve the right version of a page to signed-in readers.</td><td data-label="Duration">Session</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">_GRECAPTCHA</th><td data-label="Purpose">Google reCAPTCHA: protects our forms from spam and abuse. Set by Google.</td><td data-label="Duration">6 months</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--necessary">Necessary</span></td></tr>
                                    <tr><th scope="row">_ga</th><td data-label="Purpose">Google Analytics: tells visits from the same browser apart.</td><td data-label="Duration">2 years</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--analytics">Analytics</span></td></tr>
                                    <tr><th scope="row">_ga_*</th><td data-label="Purpose">Google Analytics: keeps track of the current visit.</td><td data-label="Duration">2 years</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--analytics">Analytics</span></td></tr>
                                    <tr><th scope="row">YouTube, TradingView</th><td data-label="Purpose">Set by these services when their video or market widget loads; governed by their own privacy policies. YouTube videos use the privacy-enhanced youtube-nocookie.com address.</td><td data-label="Duration">Set by the provider</td><td data-label="Category"><span class="hr-cookie-cat hr-cookie-cat--embeds">Embedded content</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="hr-priv-text" style="margin-bottom: 8px;">
                            We also use your browser’s local storage for small conveniences on your own device (for example, remembering a dismissed notice). You can delete cookies and local storage in your browser settings at any time.
                        </p>
                        <a href="#cookies" class="hr-consent-btn" data-hr-consent-open>Change cookie settings</a>
                    </div>
                </div>
            </section>

            <!-- SECURITY -->
            <section id="security" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">13</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Security Measures</h2>
                        <ul class="hr-priv-list" style="margin-bottom: 0;">
                            <li>The whole site uses HTTPS, with security headers that limit what scripts can run.</li>
                            <li>Passwords are stored only as Argon2id hashes, and new passwords are checked against known data breaches.</li>
                            <li>You can sign in with a passkey and turn on two-step verification; staff accounts must use two-step verification.</li>
                            <li>Sign-in, registration and password-reset attempts are rate-limited, and we email you when your account is used from a new device.</li>
                            <li>Submissions are encrypted end to end, and our articles are signed so they cannot be altered unnoticed.</li>
                        </ul>
                        <p class="hr-priv-text" style="margin: 16px 0 0;">
                            No system is perfectly secure. If we ever learn of a breach that affects your personal data, we will tell you without undue delay.
                        </p>
                    </div>
                </div>
            </section>

            <!-- LEGAL DISCLOSURE -->
            <section id="disclosure" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">14</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Service Providers &amp; Legal Disclosure</h2>
                        <p class="hr-priv-text">
                            We share personal data only with service providers that help us run the site, and only what they need: our web host, Mailchimp (newsletter), Google (Analytics, if you allow it, and reCAPTCHA), Sentry (error monitoring) and our email provider. Some of them process data outside Pakistan.
                        </p>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            We will only share your personal data otherwise if required to do so by a competent court of law or an enforceable governmental request. We will notify you of any such disclosure unless legally prohibited from doing so.
                        </p>
                    </div>
                </div>
            </section>

            <!-- YOUR RIGHTS -->
            <section id="rights" class="hr-priv-section">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">15</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Your Rights</h2>
                        <p class="hr-priv-text">You have the right to:</p>

                        <ul class="hr-priv-list">
                            <li><strong>Access and export</strong> the data we hold about you. Account holders can download it from account settings (Privacy &amp; data → Download your data).</li>
                            <li><strong>Correct</strong> inaccurate data. You can change your name and email address in account settings.</li>
                            <li><strong>Delete</strong> your data. You can delete your account, with your reading history, from account settings (Privacy &amp; data → Delete your account).</li>
                            <li><strong>Object</strong> to or withdraw consent for processing — for example, switch analytics off in “Cookie settings”, or unsubscribe from the newsletter at any time.</li>
                            <li><strong>Port</strong> your data to another service, using the export.</li>
                        </ul>

                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            For anything you cannot do yourself, email us at <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>"><?php echo esc_html( $hr_email ); ?></a>. We may need to confirm your identity first, and we reply within 30 days.
                        </p>
                    </div>
                </div>
            </section>

            <!-- CONTACT -->
            <section id="contact" class="hr-priv-section" style="margin-bottom: 0;">
                <div class="hr-priv-header">
                    <div class="hr-priv-number">16</div>
                    <div class="hr-priv-header-text">
                        <h2 style="margin-bottom: 20px;">Contact &amp; Changes</h2>
                        <p class="hr-priv-text">
                            Questions about this policy or your data: <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>"><?php echo esc_html( $hr_email ); ?></a>, or write to Headline Recorder, <?php echo esc_html( $hr_address ); ?>.
                        </p>
                        <p class="hr-priv-text" style="margin-bottom: 0;">
                            When we change this policy we update the date at the top. If a change affects how we use account holders’ data, we will tell you by email or on the site before it takes effect.
                        </p>
                    </div>
                </div>
            </section>

        </div>

        <!-- RIGHT: SIDEBAR -->
        <aside class="hr-sidebar" aria-label="Privacy policy contents">

            <div class="hr-sb-nav-box">
                <div class="hr-sb-title">Quick Navigation</div>
                <nav class="hr-sb-nav" aria-label="Sections">
                    <a href="#intro" class="hr-sb-link">Introduction</a>
                    <a href="#data" class="hr-sb-link">Data We Collect</a>
                    <a href="#analytics" class="hr-sb-link">Analytics</a>
                    <a href="#provided" class="hr-sb-link">Information You Provide</a>
                    <a href="#accounts" class="hr-sb-link">Accounts &amp; Sign-in</a>
                    <a href="#reading" class="hr-sb-link">Reading &amp; Streaks</a>
                    <a href="#submissions" class="hr-sb-link">Submissions</a>
                    <a href="#public" class="hr-sb-link">Public Interactions</a>
                    <a href="#ai" class="hr-sb-link">AI Transparency</a>
                    <a href="#monitoring" class="hr-sb-link">Error Monitoring</a>
                    <a href="#signing" class="hr-sb-link">Content Signing</a>
                    <a href="#cookies" class="hr-sb-link">Cookies</a>
                    <a href="#security" class="hr-sb-link">Security</a>
                    <a href="#disclosure" class="hr-sb-link">Sharing &amp; Disclosure</a>
                    <a href="#rights" class="hr-sb-link">Your Rights</a>
                    <a href="#contact" class="hr-sb-link">Contact</a>
                </nav>
            </div>

            <div class="hr-sb-key-points">
                <div class="hr-sb-title">Key Points</div>
                <ul>
                    <li>We don't sell your data</li>
                    <li>Analytics only with your consent</li>
                    <li>Submissions are encrypted in your browser</li>
                    <li>Export or delete your account any time</li>
                    <li>All AI content reviewed by humans</li>
                </ul>
            </div>

            <a href="<?php echo esc_url( 'mailto:' . $hr_email ); ?>" class="hr-cta-btn">Privacy Questions?</a>

        </aside>

    </div>
</div>

<?php
get_footer();
