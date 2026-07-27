<?php
require_once '../includes/auth.php';
require_once '../config/connection.php';
require_once '../config/app.php';

// Fetch total watches
$totalWatches = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM watches");
if ($result) {
    $totalWatches = $result->fetch_assoc()['total'];
}

// Fetch active watches
$activeWatches = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM watches WHERE is_active = 1");
if ($result) {
    $activeWatches = $result->fetch_assoc()['total'];
}

// Fetch featured watches
$featuredWatches = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM watches WHERE is_featured = 1");
if ($result) {
    $featuredWatches = $result->fetch_assoc()['total'];
}

// Fetch total unique retailers
$totalRetailers = 0;
$result = $conn->query("SELECT COUNT(DISTINCT retailer_name) AS total FROM watch_variants");

if ($result) {
    $totalRetailers = $result->fetch_assoc()['total'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | WatchShelf Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
</head>

<body>
    <div class="page">
        <h1>WatchShelf Admin Dashboard</h1>

        <div class="welcome-card">Welcome, <strong><?= htmlspecialchars($_SESSION['email']); ?></strong>!</div>

        <div class="card">
            <h2>Statistics</h2>

            <table class="stats-table">
                <tr>
                    <td>Total Watches</td>
                    <td><?= $totalWatches; ?></td>
                </tr>

                <tr>
                    <td>Active Watches</td>
                    <td><?= $activeWatches; ?></td>
                </tr>

                <tr>
                    <td>Featured Watches</td>
                    <td><?= $featuredWatches; ?></td>
                </tr>

                <tr>
                    <td>Total Retailers</td>
                    <td><?= $totalRetailers; ?></td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Quick Actions</h2>

            <ul>
                <li><a href="watches/add.php">Add New Watch</a></li>
                <li><a href="watches/list.php">Manage Watches</a></li>
                <li><a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a></li>
            </ul>
        </div>
    </div>
</body>

</html>