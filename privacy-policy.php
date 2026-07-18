<?php
$page_title = 'Privacy Policy';
include 'includes/header.php';
?>

<main>
    <section id="hero">
        <h1>Privacy Policy</h1>
        <p>Your privacy matters to us.</p>
    </section>

    <section id="privacy">
        <h2>Information We Collect</h2>
        <p>WatchShelf does not require users to create an account or provide personal information to browse the website.</p>

        <h2>Cookies</h2>
        <p>WatchShelf currently does not use cookies for tracking users. If cookies or similar technologies are introduced in the future, this Privacy Policy will be updated accordingly.</p>

        <h2>Third-Party Services</h2>
        <p>WatchShelf may use third-party services such as analytics providers or affiliate networks. These services may collect  information according to their own privacy policies.</p>

        <h2>External Websites</h2>
        <p>When you click a retailer link, you leave WatchShelf and are subject to the privacy policy and terms of the respective retailer.</p>

        <h2>Policy Updates</h2>
        <p>This Privacy Policy may be updated periodically to reflect changes in the website or applicable regulations. Continued use of WatchShelf constitutes acceptance of the updated policy.</p>

        <h2>Contact</h2>
        <p>If you have any questions regarding this Privacy Policy, you may contact us at
            <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL) ?>">
                <?= htmlspecialchars(CONTACT_EMAIL) ?>
            </a>.</p>
    </section>
</main>

<?php include 'includes/footer.php'; ?>