<?php
$quickLinks = require __DIR__ . '/../config/quick-links.php';
?>

<footer>
    <div class="footer-links">
        <a href="<?= BASE_URL ?>/index.php">Home</a>
        <a href="<?= BASE_URL ?>/about.php">About</a>
        <a href="<?= BASE_URL ?>/contact.php">Contact</a>
        <a href="<?= BASE_URL ?>/disclaimer.php">Disclaimer</a>
        <a href="<?= BASE_URL ?>/privacy-policy.php">Privacy Policy</a>
    </div>

    <div class="footer-quick-links">
        <h3>Quick Links</h3>

        <?php foreach ($quickLinks as $slug => $quickLink): ?>
            <a href="<?= BASE_URL ?>/index.php?quick_link=<?= urlencode($slug) ?>">
                <?= htmlspecialchars($quickLink['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.</p>
</footer>

</body>

</html>