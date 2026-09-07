<?php
$quickLinks = require __DIR__ . '/../config/quick-links.php';
?>

<footer class="site-footer">
    <div class="site-container">

        <nav class="footer-links" aria-label="Footer navigation">
            <a href="<?= BASE_URL ?>/about.php">About</a>
            <a href="<?= BASE_URL ?>/contact.php">Contact</a>
            <a href="<?= BASE_URL ?>/disclaimer.php">Disclaimer</a>
            <a href="<?= BASE_URL ?>/privacy-policy.php">Privacy Policy</a>
            <a href="<?= BASE_URL ?>/developers-note.php" class="footer-developer-link">Developer's Note</a>
        </nav>

        <div class="footer-quick-links">
            <h3>Watch Guides</h3>

            <div class="footer-quick-links-list">
                <?php foreach ($quickLinks as $slug => $quickLink): ?>
                    <a href="<?= BASE_URL ?>/index.php?quick_link=<?= urlencode($slug) ?>">
                        <?= htmlspecialchars($quickLink['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="footer-back-to-top">
            <a href="#top" class="back-to-top">
                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                <span>Back to top</span>
            </a>
        </div>

        <div class="footer-copyright">
            <p>
                &copy; <?= date('Y') ?>
                <?= htmlspecialchars(SITE_NAME) ?>.
                All rights reserved.
            </p>
        </div>
    </div>
</footer>

</body>

</html>