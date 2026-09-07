<?php
require_once 'config/app.php';

$page_title = 'Contact';
$page_canonical = SITE_URL . '/contact.php';

include 'includes/header.php';
?>

<main class="site-main">
    <section class="page-hero">
        <div class="site-container">
            <h1>Contact WatchShelf</h1>
            <p>Have a question, suggestion, or found an issue? Get in touch.</p>
        </div>
    </section>

    <section class="page-content">
        <div class="site-container">

            <h2>Get in Touch</h2>

            <p>
                If you'd like to suggest a watch, report incorrect specifications,
                pricing or availability, or share feedback about WatchShelf, feel free
                to get in touch.
            </p>

            <p>
                <strong>Email:</strong>
                <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>">
                    <?= htmlspecialchars(CONTACT_EMAIL) ?>
                </a>
            </p>

            <h2>Feedback & Corrections</h2>

            <p>
                Watch information can change over time, and feedback helps keep the
                catalogue useful and accurate. If you notice something that needs
                correcting or have an idea that could improve WatchShelf, your feedback
                is welcome.
            </p>

        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>