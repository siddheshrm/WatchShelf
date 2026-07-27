<?php
require_once '../../includes/auth.php';
require_once '../../config/connection.php';
require_once '../../config/app.php';

/* Fetch all watches with aggregated variant information.
   - Retrieves the default variant's image folder and color.
   - Counts the number of color options and retailer records.
   - Aggregates retailer details with GROUP_CONCAT so each watch is returned as a single row. */

$sql = "SELECT w.*, MAX(CASE WHEN wv.is_default = 1 THEN wv.image_folder END) AS default_image_folder, COALESCE (MAX(CASE WHEN wv.is_default = 1 THEN wv.color_name END), 'Not Available') AS default_color, COUNT(DISTINCT wv.color_name) AS total_colors, COUNT(wv.id) AS total_retailers, GROUP_CONCAT(CONCAT(wv.retailer_name, '|', COALESCE(wv.base_url, ''), '|', COALESCE(wv.affiliate_url, '')) ORDER BY wv.display_order SEPARATOR '~~') AS retailers
            FROM watches w
            LEFT JOIN watch_variants wv
            ON w.id = wv.watch_id
            GROUP BY w.id
            ORDER BY w.brand, w.model_name";

$result = $conn->query($sql);
if (!$result) {
    die($conn->error);
}

$totalWatches = $result ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Watches | WatchShelf Admin</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
</head>

<body>
    <h1>Manage Watches</h1>

    <p>
        <a href="../dashboard.php">Dashboard</a> |
        <a href="add.php">Add Watch</a>
    </p>

    <hr>

    <p>
        <strong>Total Watches:</strong> <?= $totalWatches; ?>
    </p>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>#</th>
            <th>Image</th>
            <th>Watch</th>
            <th>Owner Status</th>
            <th>MRP</th>
            <th>Gender</th>
            <th>Available Colors</th>
            <th>Retailer Records</th>
            <th>Featured?</th>
            <th>Active?</th>
            <th>Actions</th>
        </tr>

        <?php if ($totalWatches > 0): ?>
            <?php $srNo = 1; ?>

            <?php while ($watch = $result->fetch_assoc()): ?>
                <tr>
                    <!-- Sr. No. (#) -->
                    <td><?= $srNo++; ?></td>


                    <!-- Image -->
                    <?php
                    $imageFile = WATCH_IMAGE_DIR . '/' . $watch['default_image_folder'] . '/1.webp';
                    $imageUrl = WATCH_IMAGE_URL . '/' . $watch['default_image_folder'] . '/1.webp';
                    ?>

                    <td>
                        <?php if (file_exists($imageFile)): ?>
                            <img src="<?= htmlspecialchars($imageUrl); ?>"
                                alt="<?= htmlspecialchars($watch['brand'] . ' ' . $watch['model_name']); ?>" width="80">
                        <?php else: ?>
                            <img src="<?= DEFAULT_WATCH_IMAGE; ?>" alt="No Image" width="80" height="80">
                        <?php endif; ?>
                    </td>

                    <!-- Brand & Model -->
                    <td>
                        <?= htmlspecialchars($watch['brand']); ?>
                        <?= htmlspecialchars($watch['model_name']); ?>
                    </td>

                    <!-- Owner Status -->
                    <td><?= ucfirst(htmlspecialchars($watch['owner_status'])) ?></td>

                    <!-- MRP -->
                    <td>₹<?= number_format($watch['mrp'], 2); ?></td>

                    <!-- Gender -->
                    <td><?= ucfirst($watch['gender']); ?></td>

                    <!-- Colors Variants -->
                    <td>
                        <?= htmlspecialchars($watch['default_color']) ?>

                        <?php if ($watch['total_colors'] > 1): ?>
                            <small>+<?= $watch['total_colors'] - 1 ?> more</small>
                        <?php endif; ?>
                    </td>

                    <!-- Retailer Records -->
                    <td>
                        <?= $watch['total_retailers']; ?>
                        <?= $watch['total_retailers'] == 1 ? 'Retailer' : 'Retailers'; ?>
                    </td>

                    <!-- Featured and Active status are displayed as 'Yes' or 'No' based on their boolean values -->
                    <td><?= $watch['is_featured'] ? 'Yes' : 'No'; ?></td>
                    <td><?= $watch['is_active'] ? 'Yes' : 'No'; ?></td>

                    <!-- Actions -->
                    <td>
                        <a href="edit.php?id=<?= $watch['id']; ?>">Edit</a> |
                        <a href="delete.php?id=<?= $watch['id']; ?>" onclick="return confirm('Delete this watch?');">Delete</a>
                    </td>
                </tr>

            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="10">No watches found.</td>
            </tr>
        <?php endif; ?>

    </table>
</body>

</html>

<?php
$conn->close();
?>