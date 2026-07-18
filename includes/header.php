<?php
require_once __DIR__ . '/../config/app.php';

// Site-wide metadata used across the page
$site_name = SITE_NAME;
$site_tagline = "Find Your Perfect Budget Watch";
$site_description = "Explore and compare budget watches with detailed specs and prices from Amazon, Flipkart, Myntra, HMT, and more.";

// Fall back to the site name when a page-specific title isn't provided
$page_title = $page_title ?? $site_name;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars($site_name) ?></title>
    <meta name="description" content="<?= htmlspecialchars($site_description) ?>">

    <link rel="stylesheet" href="<?= CSS_URL ?>/style.css">
</head>

<body>
    <header>
        <form action="<?= BASE_URL ?>/index.php" method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search watches..."
                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

            <button type="submit" name="search_submit" value="1">Search</button>
        </form>

        <div class="logo">
            <a href="<?= BASE_URL ?>/index.php">
                <h2><?= htmlspecialchars($site_name) ?></h2>
            </a>
            <p><?= htmlspecialchars($site_tagline) ?></p>
        </div>

        <nav>
            <ul>
                <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li><a href="<?= BASE_URL ?>/about.php">About</a></li>
                <li><a href="<?= BASE_URL ?>/contact.php">Contact</a></li>
            </ul>
        </nav>
    </header>