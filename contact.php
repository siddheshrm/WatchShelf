<?php
$page_title = 'Contact';
include 'includes/header.php';
?>

<main class="site-main">
    <section class="page-hero">
        <div class="site-container">
            <h1>Contact Us</h1>
            <p>Have a question, suggestion, or found an issue? We'd love to hear from you.</p>
        </div>
    </section>

    <section class="page-content">
        <div class="site-container">
            <h2>Get in Touch</h2>
            <p>If you'd like to suggest a watch, report incorrect information, or share your feedback, feel free to get
                in touch.</p>

            <p>
                <strong>Email:</strong>
                <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>">
                    <?= htmlspecialchars(CONTACT_EMAIL) ?>
                </a>
            </p>

            <h2>Feedback</h2>
            <p>WatchShelf is continuously improving. Your suggestions help us build a better platform for the watch
                community.</p>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>