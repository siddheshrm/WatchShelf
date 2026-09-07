<?php
require_once 'config/app.php';

$page_title = 'Privacy Policy';
$page_canonical = SITE_URL . '/privacy-policy.php';

include 'includes/header.php';
?>

<main class="site-main">
    <section class="page-hero">
        <div class="site-container">
            <h1>Privacy Policy</h1>
            <p>How information and third-party services are handled on WatchShelf.</p>
        </div>
    </section>

    <section class="page-content">
        <div class="site-container">

            <h2>Information You Provide</h2>

            <p>
                WatchShelf does not require you to create an account or provide personal
                information to browse the website.
            </p>

            <p>
                If you contact WatchShelf by email, the information you provide will be
                used to respond to your message and handle your enquiry or feedback.
            </p>

            <h2>Cookies & Similar Technologies</h2>

            <p>
                WatchShelf may use cookies or similar technologies where required for
                website functionality or services provided by third parties. Third-party
                services may also use their own technologies in accordance with their
                respective privacy policies.
            </p>

            <h2>Analytics & Third-Party Services</h2>

            <p>
                WatchShelf may use third-party services, such as analytics providers or
                affiliate networks, to understand website usage, maintain the service,
                or support affiliate functionality.
            </p>

            <p>
                These services may collect technical or usage information according to
                their own privacy policies and practices.
            </p>

            <h2>External Websites</h2>

            <p>
                WatchShelf contains links to external websites, including retailer
                websites. When you follow an external link, you leave WatchShelf and
                become subject to the privacy practices and terms of that website.
            </p>

            <h2>Policy Updates</h2>

            <p>
                This Privacy Policy may be updated periodically to reflect changes to
                WatchShelf, the services it uses, or applicable requirements.
            </p>

            <h2>Contact</h2>

            <p>
                If you have questions about this Privacy Policy, you may contact
                WatchShelf at
                <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>">
                    <?= htmlspecialchars(CONTACT_EMAIL) ?>
                </a>.
            </p>

        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>